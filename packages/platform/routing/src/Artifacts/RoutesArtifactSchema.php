<?php

declare(strict_types=1);

/*
 * Coretsia Framework (Monorepo)
 *
 * Project: Coretsia Framework (Monorepo)
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

namespace Coretsia\Platform\Routing\Artifacts;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteTemplate;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;

/**
 * Single source of routes@1 envelope, hydration, safety and byte policy.
 *
 * Route compilation, publication and artifact hydration use the same validation
 * and normalization boundary. No filesystem operation occurs in this class.
 *
 * @internal
 */
final readonly class RoutesArtifactSchema
{
    public const string ARTIFACT_NAME = 'routes';
    public const string GENERATOR = 'platform/routing/artifacts';
    public const string BASENAME = 'routes.php';
    public const int SCHEMA_VERSION = 1;
    public const int MAX_ARTIFACT_BYTES = 16_777_216;
    public const int MAX_ROUTES = 4096;
    public const int MAX_DEPTH = 32;
    public const int MAX_NODES = 100_000;
    public const int MAX_STRING_BYTES = 65_536;
    public const int MAX_TOTAL_STRING_BYTES = 8_388_608;

    private const array METHODS = [
        'GET',
        'HEAD',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
        'TRACE',
        'CONNECT',
    ];

    public function __construct(
        private ReservedRoutesGuard $reservedRoutes,
        private SensitiveKeyClassifierInterface $keyClassifier,
        private SensitiveDataRedactorInterface $redactor,
        private StablePhpArrayDumper $dumper,
    ) {
    }

    public static function limits(): JsonLikeNormalizationLimits
    {
        return new JsonLikeNormalizationLimits(
            self::MAX_DEPTH,
            self::MAX_NODES,
            self::MAX_STRING_BYTES,
            self::MAX_TOTAL_STRING_BYTES,
        );
    }

    /**
     * @param list<RouteDefinition> $routes
     * @return array{_meta: array<string, int|string>, payload: array{app: string, routes: list<array<string, mixed>>}}
     */
    public function createEnvelope(array $routes, string $app, string $appEnv): array
    {
        self::assertTarget($app);
        self::assertEnvironment($appEnv);
        if (!\array_is_list($routes) || \count($routes) > self::MAX_ROUTES) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
            );
        }

        $descriptors = [];
        foreach ($routes as $route) {
            if (!$route instanceof RouteDefinition) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_PROVIDER_OUTPUT_INVALID,
                );
            }
            $descriptors[] = $route->toArray();
        }
        $this->validateRouteDescriptors($descriptors);
        $payload = ['app' => $app, 'routes' => $descriptors];
        try {
            JsonLikeNormalizer::normalize($payload, 'routingPayload', self::limits());
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromHydrationFailure($failure);
        }
        $envelope = [
            '_meta' => [
                'fingerprint' => self::fingerprint($payload, $appEnv),
                'generator' => self::GENERATOR,
                'name' => self::ARTIFACT_NAME,
                'schemaVersion' => self::SCHEMA_VERSION,
            ],
            'payload' => $payload,
        ];

        // Apply the full envelope budget before returning any compiler result.
        $this->normalizeCompleteEnvelope($envelope);
        $this->serializeEnvelope($envelope, $app, $appEnv);
        return $envelope;
    }

    /**
     * Validates a decoded artifact and returns canonical route definitions.
     *
     * @param array<mixed> $envelope
     * @return list<RouteDefinition>
     */
    public function hydrateEnvelope(array $envelope, string $app, string $appEnv): array
    {
        try {
            self::assertTarget($app);
            self::assertEnvironment($appEnv);
            $this->normalizeCompleteEnvelope($envelope);
            self::assertExactKeys($envelope, ['_meta', 'payload']);
            if (!\is_array($envelope['_meta']) || !\is_array($envelope['payload'])) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_MALFORMED,
                );
            }
            $header = $envelope['_meta'];
            $payload = $envelope['payload'];
            self::assertExactKeys($header, ['fingerprint', 'generator', 'name', 'schemaVersion']);
            self::assertExactKeys($payload, ['app', 'routes']);

            if (
                $header['name'] !== self::ARTIFACT_NAME
                || $header['generator'] !== self::GENERATOR
                || $header['schemaVersion'] !== self::SCHEMA_VERSION
            ) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_INCOMPATIBLE,
                );
            }
            if (
                !\is_string($header['fingerprint'])
                || \preg_match('/\A[a-f0-9]{64}\z/D', $header['fingerprint']) !== 1
            ) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_HEADER_INVALID,
                );
            }
            if ($payload['app'] !== $app || !\is_array($payload['routes'])) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_INCOMPATIBLE,
                );
            }
            if ($header['fingerprint'] !== self::fingerprint($payload, $appEnv)) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
                );
            }

            return $this->validateRouteDescriptors($payload['routes']);
        } catch (RoutesArtifactInvalidException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromHydrationFailure($failure);
        }
    }

    /**
     * Validates a complete artifact and emits exactly its canonical PHP bytes.
     *
     * @param array<mixed> $envelope
     */
    public function serializeEnvelope(array $envelope, string $app, string $appEnv): string
    {
        $this->hydrateEnvelope($envelope, $app, $appEnv);
        try {
            $normalized = $this->normalizeCompleteEnvelope($envelope);
            $bytes = $this->dumper->dumpNormalized($normalized);
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromHydrationFailure($failure);
        }
        if (\strlen($bytes) > self::MAX_ARTIFACT_BYTES) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_OVERSIZED,
            );
        }
        return $bytes;
    }

    /**
     * @param array<mixed> $envelope
     * @return array<string, mixed>
     */
    private function normalizeCompleteEnvelope(array $envelope): array
    {
        try {
            $normalized = JsonLikeNormalizer::normalize($envelope, 'routingEnvelope', self::limits());
            if (!\is_array($normalized) || $normalized !== $envelope) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_MALFORMED,
                );
            }
            return $normalized;
        } catch (RoutesArtifactInvalidException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromHydrationFailure($failure);
        }
    }

    /**
     * @param array<mixed> $descriptors
     * @return list<RouteDefinition>
     */
    private function validateRouteDescriptors(array $descriptors): array
    {
        if (!\array_is_list($descriptors)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        if (\count($descriptors) > self::MAX_ROUTES) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
            );
        }

        $routes = [];
        $names = [];
        $domains = [];
        foreach ($descriptors as $descriptor) {
            $route = $this->validateDescriptor($descriptor);
            if (isset($names[$route->name()])) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_ROUTE_NAME_DUPLICATE,
                );
            }
            $names[$route->name()] = true;
            $template = RouteTemplate::parse($route->pathTemplate(), $route->requirements());
            $domain = $template->domainSignature();
            foreach ($route->methods() as $method) {
                if (isset($domains[$domain][$method])) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_MATCHING_DOMAIN_EQUIVALENT,
                    );
                }
                $domains[$domain][$method] = true;
            }
            $routes[] = $route;
        }
        return $routes;
    }

    private function validateDescriptor(mixed $descriptor): RouteDefinition
    {
        if (!\is_array($descriptor)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        try {
            // Enforce structural limits before any recursive classification or hydration.
            JsonLikeNormalizer::normalize($descriptor, 'routingDescriptor', self::limits());
        } catch (\Throwable $failure) {
            if ($failure instanceof JsonLikeNormalizationException && \in_array($failure->reason(), [
                    JsonLikeNormalizationException::REASON_MAX_DEPTH_EXCEEDED,
                    JsonLikeNormalizationException::REASON_MAX_NODES_EXCEEDED,
                    JsonLikeNormalizationException::REASON_STRING_BYTES_EXCEEDED,
                    JsonLikeNormalizationException::REASON_TOTAL_STRING_BYTES_EXCEEDED,
                ], true)) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_RESOURCE_LIMIT_EXCEEDED,
                );
            }
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
        self::assertExactKeys(
            $descriptor,
            ['defaults', 'handler', 'metadata', 'methods', 'name', 'pathTemplate', 'requirements', 'schemaVersion'],
        );
        if (
            $descriptor['schemaVersion'] !== RouteDefinition::SCHEMA_VERSION
            || !\is_string($descriptor['name'])
            || !\is_string($descriptor['handler'])
            || !\is_string($descriptor['pathTemplate'])
            || !\is_array($descriptor['methods'])
            || !\is_array($descriptor['requirements'])
            || !\is_array($descriptor['defaults'])
            || !\is_array($descriptor['metadata'])
        ) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        if ($descriptor['defaults'] !== []) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_DECLARATION_UNSUPPORTED,
            );
        }
        self::assertLogicalIdentifier($descriptor['name']);
        self::assertLogicalIdentifier($descriptor['handler']);
        if (!\array_is_list($descriptor['methods']) || $descriptor['methods'] === []) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METHOD_INVALID,
            );
        }
        foreach ($descriptor['methods'] as $method) {
            if (!\is_string($method) || !\in_array($method, self::METHODS, true)) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_METHOD_INVALID,
                );
            }
        }
        if ($descriptor['requirements'] !== [] && \array_is_list($descriptor['requirements'])) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
            );
        }
        if ($descriptor['metadata'] !== [] && \array_is_list($descriptor['metadata'])) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
        try {
            $template = RouteTemplate::parse($descriptor['pathTemplate'], $descriptor['requirements']);
            $this->reservedRoutes->assertAllowed($template);
            $route = new RouteDefinition(
                name: $descriptor['name'],
                methods: $descriptor['methods'],
                pathTemplate: $descriptor['pathTemplate'],
                handler: $descriptor['handler'],
                requirements: $descriptor['requirements'],
                defaults: $descriptor['defaults'],
                metadata: $descriptor['metadata'],
            );
        } catch (RouteDefinitionInvalidException|ReservedRouteViolationException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
        if ($route->toArray() !== $descriptor) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }

        $this->assertMetadataSafe($descriptor['metadata']);
        foreach ($descriptor['requirements'] as $name => $_) {
            $this->assertKeyNotSensitive($name);
        }
        $this->assertDescriptorRedactionSafe($descriptor);
        return $route;
    }

    private static function assertLogicalIdentifier(string $value): void
    {
        if (
            \strlen($value) > 256
            || \preg_match('/\A[A-Za-z_][A-Za-z0-9_.:-]*\z/D', $value) !== 1
        ) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function assertMetadataSafe(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            if (!\is_string($key)) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
                );
            }

            $this->assertKeyNotSensitive($key);
            $this->assertMetadataValueSafe($value);
        }
    }

    private function assertMetadataValueSafe(mixed $value): void
    {
        if (\is_string($value)) {
            if (
                \str_starts_with($value, '/')
                || \str_starts_with($value, '\\')
                || \preg_match('~\A[A-Za-z]:[/\\\\]~D', $value) === 1
                || \strncasecmp($value, 'file://', 7) === 0
            ) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
                );
            }
        } elseif (\is_array($value)) {
            if (\array_is_list($value)) {
                foreach ($value as $nested) {
                    $this->assertMetadataValueSafe($nested);
                }
            } else {
                $this->assertMetadataSafe($value);
            }
        }
    }

    private function assertKeyNotSensitive(string $key): void
    {
        try {
            $classified = $this->keyClassifier->classify($key);
        } catch (\Throwable) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
        if ($classified !== null) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
    }

    /**
     * @param array<string, mixed> $descriptor
     */
    private function assertDescriptorRedactionSafe(array $descriptor): void
    {
        try {
            $context = new RedactionContext('routing.artifact', RedactionMode::Placeholder);
            $redacted = $this->redactor->redactJsonLike($descriptor, $context);
            $normalized = JsonLikeNormalizer::normalize($descriptor, 'routingDescriptor');
            if ($redacted !== $normalized) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
                );
            }
        } catch (RouteDefinitionInvalidException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_METADATA_UNSAFE,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     * @param list<string> $expected
     */
    private static function assertExactKeys(array $map, array $expected): void
    {
        $actual = \array_keys($map);
        \sort($actual, \SORT_STRING);
        if ($actual !== $expected) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }
    }

    /**
     * @param array{app: string, routes: list<array<string, mixed>>} $payload
     */
    private static function fingerprint(array $payload, string $appEnv): string
    {
        $input = [
            'appEnv' => $appEnv,
            'generator' => self::GENERATOR,
            'name' => self::ARTIFACT_NAME,
            'payload' => $payload,
            'schemaVersion' => self::SCHEMA_VERSION,
            'systemRouteTable' => [
                'entries' => SystemRouteTable::entries(),
                'schemaVersion' => SystemRouteTable::SCHEMA_VERSION,
            ],
        ];

        try {
            return \hash('sha256', StableJsonEncoder::encodeStableMap($input));
        } catch (\InvalidArgumentException) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID,
            );
        }
    }

    private static function assertTarget(string $app): void
    {
        if (!\in_array($app, ['web', 'api'], true)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
    }

    private static function assertEnvironment(string $appEnv): void
    {
        if (
            $appEnv === ''
            || \trim($appEnv) !== $appEnv
            || \str_contains($appEnv, "\r")
            || \str_contains($appEnv, "\n")
            || \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $appEnv) === 1
        ) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_INVALID,
            );
        }
    }
}
