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

namespace Coretsia\Kernel\Container;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Container\Exception\ContainerArtifactInvalidException;
use Coretsia\Kernel\Container\Exception\ContainerArtifactMissingException;
use Psr\Container\ContainerInterface;

/**
 * Builds a runtime Foundation container from an already generated `container@1`
 * compiled-container artifact.
 *
 * This factory is the artifact-only production runtime boot boundary.
 *
 * It intentionally does not:
 *
 * - read source config files;
 * - run source config discovery;
 * - run module discovery;
 * - run providers as a fallback;
 * - compile a new container graph;
 * - calculate fingerprints;
 * - write artifacts;
 * - mutate existing artifacts;
 * - emit stdout/stderr;
 * - expose absolute paths, raw artifact payloads, raw config values, raw env
 *   values, source snippets, closure dumps, PHP warning text, OS error
 *   messages, stack traces, or previous throwable messages in diagnostics.
 *
 * Runtime config is supplied only through an already-read and already-validated
 * `config@1` payload. This factory uses the `config` field from that payload as
 * the Foundation container config snapshot.
 *
 * @internal
 */
final readonly class CompiledContainerFactory
{
    private const int SCHEMA_VERSION_CONTAINER = 1;

    private const string PAYLOAD_KEY_ALIASES = 'aliases';
    private const string PAYLOAD_KEY_COMPILED = 'compiled';
    private const string PAYLOAD_KEY_KIND = 'kind';
    private const string PAYLOAD_KEY_PARAMETERS = 'parameters';
    private const string PAYLOAD_KEY_SERVICES = 'services';
    private const string PAYLOAD_KEY_TAGS = 'tags';

    private const string KIND_COMPILED = 'compiled';

    private const string SERVICE_TYPE_CLASS = 'class';
    private const string SERVICE_TYPE_FACTORY = 'factory';

    private const string FACTORY_CLASS_METHOD = 'class-method';
    private const string FACTORY_SERVICE_METHOD = 'service-method';

    private const string REF_SERVICE = 'service';
    private const string REF_PARAMETER = 'parameter';
    private const string REF_CLASS = 'class';

    private const string CONSTRUCTION_KEY_CLASS = 'class';
    private const string CONSTRUCTION_KEY_FACTORY = 'factory';
    private const string FACTORY_KEY_KIND = 'kind';
    private const string FACTORY_KEY_CLASS = 'class';
    private const string FACTORY_KEY_SERVICE = 'service';
    private const string FACTORY_KEY_METHOD = 'method';

    private const string CONFIG_PAYLOAD_KEY_CONFIG = 'config';

    private const int MAX_RUNTIME_GRAPH_DEPTH = 32;
    private const int MAX_RUNTIME_LIST_ITEMS = 8192;
    private const int MAX_RUNTIME_MAP_KEYS = 8192;
    private const int MAX_RUNTIME_STRING_BYTES = 2048;

    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string CONTAINER_TAG_PATTERN = '/\A[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)*\z/';

    public function __construct(
        private PhpArtifactReader $artifactReader,
        private ArtifactSchemaValidator $schemaValidator,
    ) {
    }

    /**
     * Builds the runtime Foundation container from a `container@1`
     * compiled-container artifact and an already-read/validated `config@1` payload.
     *
     * @param non-empty-string $containerArtifactPath
     * @param array<string, mixed> $configPayload Already-read/validated config@1 payload.
     *
     * @throws ContainerArtifactMissingException
     * @throws ContainerArtifactInvalidException
     */
    public function build(
        string $containerArtifactPath,
        array $configPayload,
    ): Container {
        if ($containerArtifactPath === '' || !@\is_file($containerArtifactPath)) {
            throw ContainerArtifactMissingException::missing();
        }

        $runtimeConfig = self::runtimeConfigFromConfigPayload($configPayload);

        $envelope = null;

        try {
            $read = $this->artifactReader->read($containerArtifactPath);
            $envelope = $read['envelope'];

            $this->schemaValidator->validateExpected(
                envelope: $envelope,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
                expectedSchemaVersion: self::SCHEMA_VERSION_CONTAINER,
            );

            $payload = self::containerPayloadFromEnvelope($envelope);

            return self::buildRuntimeContainer(
                payload: $payload,
                runtimeConfig: $runtimeConfig,
            );
        } catch (ContainerArtifactInvalidException $exception) {
            throw $exception;
        } catch (ArtifactInvalidException $exception) {
            throw ContainerArtifactInvalidException::withReason(
                self::mapArtifactInvalidReason(
                    exception: $exception,
                    envelope: $envelope,
                ),
            );
        } catch (\Throwable) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_INVALID,
            );
        }
    }

    /**
     * @param array<string, mixed> $configPayload
     *
     * @return array<string, mixed>
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function runtimeConfigFromConfigPayload(array $configPayload): array
    {
        if (
            !\array_key_exists(self::CONFIG_PAYLOAD_KEY_CONFIG, $configPayload)
            || !\is_array($configPayload[self::CONFIG_PAYLOAD_KEY_CONFIG])
            || !self::isMapArray($configPayload[self::CONFIG_PAYLOAD_KEY_CONFIG])
        ) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_INVALID,
            );
        }

        /** @var array<string, mixed> $config */
        $config = $configPayload[self::CONFIG_PAYLOAD_KEY_CONFIG];

        self::assertRuntimeGraphMap($config, 0);

        return $config;
    }

    /**
     * @param array<int|string, mixed> $envelope
     *
     * @return array<string, mixed>
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function containerPayloadFromEnvelope(array $envelope): array
    {
        $payload = $envelope['payload'] ?? null;

        if (!\is_array($payload) || \array_is_list($payload)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        /** @var array<string, mixed> $payload */
        self::assertCompiledPayload($payload);

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertCompiledPayload(array $payload): void
    {
        if (($payload[self::PAYLOAD_KEY_KIND] ?? null) === 'stub') {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_LEGACY_STUB,
            );
        }

        if (($payload[self::PAYLOAD_KEY_KIND] ?? null) !== self::KIND_COMPILED) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_NON_COMPILED,
            );
        }

        if (($payload[self::PAYLOAD_KEY_COMPILED] ?? null) !== true) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_NON_COMPILED,
            );
        }

        foreach (
            [
                self::PAYLOAD_KEY_ALIASES,
                self::PAYLOAD_KEY_PARAMETERS,
                self::PAYLOAD_KEY_SERVICES,
                self::PAYLOAD_KEY_TAGS,
            ] as $key
        ) {
            if (
                !\array_key_exists($key, $payload)
                || !\is_array($payload[$key])
                || !self::isMapArray($payload[$key])
            ) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $runtimeConfig
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function buildRuntimeContainer(
        array $payload,
        array $runtimeConfig,
    ): Container {
        /** @var array<string, string> $aliases */
        $aliases = $payload[self::PAYLOAD_KEY_ALIASES];

        /** @var array<string, mixed> $parameters */
        $parameters = $payload[self::PAYLOAD_KEY_PARAMETERS];

        /** @var array<string, array<string, mixed>> $services */
        $services = $payload[self::PAYLOAD_KEY_SERVICES];

        /** @var array<string, list<array{id: string, priority: int}>> $tags */
        $tags = $payload[self::PAYLOAD_KEY_TAGS];

        self::assertRuntimeGraphMap($parameters, 0);

        $knownServiceIds = self::knownServiceIds(
            services: $services,
            aliases: $aliases,
        );

        $knownParameterNames = self::knownParameterNames($parameters);

        self::assertAliasTargetsKnown(
            aliases: $aliases,
            knownServiceIds: $knownServiceIds,
        );

        self::assertCompiledGraphReferencesKnown(
            services: $services,
            knownServiceIds: $knownServiceIds,
            knownParameterNames: $knownParameterNames,
        );

        self::assertNoReservedRuntimeDefinitionConflicts(
            services: $services,
            aliases: $aliases,
        );

        $tagRegistry = self::tagRegistryFromPayload($tags);

        $builder = new ContainerBuilder(
            config: $runtimeConfig,
            tagRegistry: $tagRegistry,
        );

        foreach ($services as $serviceId => $definition) {
            if (!\is_string($serviceId) || !\is_array($definition) || \array_is_list($definition)) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            $builder->factory(
                id: $serviceId,
                factory: self::runtimeFactoryForService(
                    serviceId: $serviceId,
                    definition: $definition,
                    parameters: $parameters,
                ),
                shared: $definition['shared'],
            );
        }

        foreach ($aliases as $alias => $serviceId) {
            if (
                !\is_string($alias)
                || !\is_string($serviceId)
                || \array_key_exists($alias, $services)
            ) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            $builder->factory(
                id: $alias,
                factory: static fn (Container $container): mixed => $container->get($serviceId),
                shared: false,
            );
        }

        /*
         * TagRegistry is a Foundation runtime support instance derived from the
         * compiled tag payload. It must be resolvable by runtime factories such
         * as reset orchestration without re-running providers.
         */
        $builder->instance(TagRegistry::class, $tagRegistry);

        return $builder->build();
    }

    /**
     * @param array<string, list<array{id: string, priority: int}>> $tags
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function tagRegistryFromPayload(array $tags): TagRegistry
    {
        $tagRegistry = new TagRegistry();

        foreach ($tags as $tag => $entries) {
            if (!\is_string($tag) || \preg_match(self::CONTAINER_TAG_PATTERN, $tag) !== 1) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            if (!\is_array($entries) || !\array_is_list($entries)) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach ($entries as $entry) {
                if (!\is_array($entry) || \array_is_list($entry)) {
                    throw ContainerArtifactInvalidException::withReason(
                        ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                $serviceId = $entry['id'] ?? null;
                $priority = $entry['priority'] ?? null;

                if (!\is_string($serviceId) || !\is_int($priority)) {
                    throw ContainerArtifactInvalidException::withReason(
                        ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                try {
                    $tagRegistry->add(
                        tag: $tag,
                        serviceId: $serviceId,
                        priority: $priority,
                    );
                } catch (\Throwable) {
                    throw ContainerArtifactInvalidException::withReason(
                        ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }
            }
        }

        return $tagRegistry;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $parameters
     *
     * @return callable(Container): mixed
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function runtimeFactoryForService(
        string $serviceId,
        array $definition,
        array $parameters,
    ): callable {
        self::assertServiceDefinition(
            serviceId: $serviceId,
            definition: $definition,
        );

        return static function (Container $container) use ($definition, $parameters): mixed {
            return self::resolveServiceDefinition(
                container: $container,
                definition: $definition,
                parameters: $parameters,
            );
        };
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertServiceDefinition(
        string $serviceId,
        array $definition,
    ): void {
        if (($definition['id'] ?? null) !== $serviceId) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_bool($definition['shared'] ?? null)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_string($definition['type'] ?? null)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_array($definition['construction'] ?? null) || \array_is_list($definition['construction'])) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!\is_array($definition['arguments'] ?? null) || !\array_is_list($definition['arguments'])) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $type = $definition['type'];
        $construction = $definition['construction'];

        if ($type === self::SERVICE_TYPE_CLASS) {
            $class = $construction[self::CONSTRUCTION_KEY_CLASS] ?? null;

            if (!\is_string($class) || $class === '') {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            return;
        }

        if ($type === self::SERVICE_TYPE_FACTORY) {
            $factory = $construction[self::CONSTRUCTION_KEY_FACTORY] ?? null;

            if (!\is_array($factory) || \array_is_list($factory)) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            $factoryType = $factory[self::FACTORY_KEY_KIND] ?? null;

            if ($factoryType === self::FACTORY_CLASS_METHOD) {
                $keys = \array_keys($factory);
                \sort($keys, \SORT_STRING);

                if (
                    $keys !== [self::FACTORY_KEY_CLASS, self::FACTORY_KEY_KIND, self::FACTORY_KEY_METHOD]
                    || !\is_string($factory[self::FACTORY_KEY_CLASS] ?? null)
                    || !\is_string($factory[self::FACTORY_KEY_METHOD] ?? null)
                    || \preg_match(self::METHOD_PATTERN, $factory[self::FACTORY_KEY_METHOD]) !== 1
                ) {
                    throw ContainerArtifactInvalidException::withReason(
                        ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                return;
            }

            if ($factoryType === self::FACTORY_SERVICE_METHOD) {
                $keys = \array_keys($factory);
                \sort($keys, \SORT_STRING);

                if (
                    $keys !== [self::FACTORY_KEY_KIND, self::FACTORY_KEY_METHOD, self::FACTORY_KEY_SERVICE]
                    || !\is_string($factory[self::FACTORY_KEY_SERVICE] ?? null)
                    || !\is_string($factory[self::FACTORY_KEY_METHOD] ?? null)
                    || \preg_match(self::METHOD_PATTERN, $factory[self::FACTORY_KEY_METHOD]) !== 1
                ) {
                    throw ContainerArtifactInvalidException::withReason(
                        ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                    );
                }

                return;
            }
        }

        throw ContainerArtifactInvalidException::withReason(
            ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
        );
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $parameters
     *
     * @throws ContainerException
     */
    private static function resolveServiceDefinition(
        Container $container,
        array $definition,
        array $parameters,
    ): mixed {
        $type = $definition['type'];
        $construction = $definition['construction'];
        $arguments = self::resolveArguments(
            container: $container,
            values: $definition['arguments'],
            parameters: $parameters,
        );

        try {
            return match ($type) {
                self::SERVICE_TYPE_CLASS => self::instantiateClassService(
                    construction: $construction,
                    arguments: $arguments,
                ),
                self::SERVICE_TYPE_FACTORY => self::invokeFactoryService(
                    container: $container,
                    construction: $construction,
                    arguments: $arguments,
                ),
                default => throw new ContainerException('container-compiled-service-type-invalid'),
            };
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-service-resolution-failed');
        }
    }

    /**
     * @param array<string, mixed> $construction
     * @param list<mixed> $arguments
     *
     * @throws ContainerException
     */
    private static function instantiateClassService(
        array $construction,
        array $arguments,
    ): object {
        $class = $construction[self::CONSTRUCTION_KEY_CLASS] ?? null;

        if (!\is_string($class) || $class === '') {
            throw new ContainerException('container-compiled-class-invalid');
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-class-invalid');
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException('container-compiled-class-not-instantiable');
        }

        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-class-instantiation-failed');
        }
    }

    /**
     * @param array<string, mixed> $construction
     * @param list<mixed> $arguments
     *
     * @throws ContainerException
     */
    private static function invokeFactoryService(
        Container $container,
        array $construction,
        array $arguments,
    ): mixed {
        $factory = $construction[self::CONSTRUCTION_KEY_FACTORY] ?? null;

        if (!\is_array($factory) || \array_is_list($factory)) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        $factoryType = $factory[self::FACTORY_KEY_KIND] ?? null;

        return match ($factoryType) {
            self::FACTORY_CLASS_METHOD => self::invokeClassMethodFactory(
                factory: $factory,
                arguments: $arguments,
            ),
            self::FACTORY_SERVICE_METHOD => self::invokeServiceMethodFactory(
                container: $container,
                factory: $factory,
                arguments: $arguments,
            ),
            default => throw new ContainerException('container-compiled-factory-type-invalid'),
        };
    }

    /**
     * @param array<string, mixed> $factory
     * @param list<mixed> $arguments
     *
     * @throws ContainerException
     */
    private static function invokeClassMethodFactory(
        array $factory,
        array $arguments,
    ): mixed {
        $keys = \array_keys($factory);
        \sort($keys, \SORT_STRING);

        if ($keys !== [self::FACTORY_KEY_CLASS, self::FACTORY_KEY_KIND, self::FACTORY_KEY_METHOD]) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        $kind = $factory[self::FACTORY_KEY_KIND] ?? null;
        $class = $factory[self::FACTORY_KEY_CLASS] ?? null;
        $method = $factory[self::FACTORY_KEY_METHOD] ?? null;

        if (
            $kind !== self::FACTORY_CLASS_METHOD
            || !\is_string($class)
            || $class === ''
            || !\is_string($method)
            || \preg_match(self::METHOD_PATTERN, $method) !== 1
        ) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        try {
            $reflection = new \ReflectionMethod($class, $method);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        if (!$reflection->isStatic() || !$reflection->isPublic()) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        try {
            return $reflection->invokeArgs(null, $arguments);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-factory-failed');
        }
    }

    /**
     * @param array<string, mixed> $factory
     * @param list<mixed> $arguments
     *
     * @throws ContainerException
     */
    private static function invokeServiceMethodFactory(
        Container $container,
        array $factory,
        array $arguments,
    ): mixed {
        $keys = \array_keys($factory);
        \sort($keys, \SORT_STRING);

        if ($keys !== [self::FACTORY_KEY_KIND, self::FACTORY_KEY_METHOD, self::FACTORY_KEY_SERVICE]) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        $kind = $factory[self::FACTORY_KEY_KIND] ?? null;
        $factoryServiceId = $factory[self::FACTORY_KEY_SERVICE] ?? null;
        $method = $factory[self::FACTORY_KEY_METHOD] ?? null;

        if (
            $kind !== self::FACTORY_SERVICE_METHOD
            || !\is_string($factoryServiceId)
            || $factoryServiceId === ''
            || !\is_string($method)
            || \preg_match(self::METHOD_PATTERN, $method) !== 1
        ) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        try {
            $factoryService = $container->get($factoryServiceId);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-factory-service-missing');
        }

        if (!\is_object($factoryService) || !\method_exists($factoryService, $method)) {
            throw new ContainerException('container-compiled-factory-invalid');
        }

        try {
            return $factoryService->{$method}(...$arguments);
        } catch (\Throwable) {
            throw new ContainerException('container-compiled-factory-failed');
        }
    }

    /**
     * @param list<mixed> $values
     * @param array<string, mixed> $parameters
     *
     * @return list<mixed>
     *
     * @throws ContainerException
     */
    private static function resolveArguments(
        Container $container,
        array $values,
        array $parameters,
    ): array {
        $arguments = [];

        foreach ($values as $value) {
            $arguments[] = self::resolveValue(
                container: $container,
                value: $value,
                parameters: $parameters,
                depth: 0,
            );
        }

        return $arguments;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws ContainerException
     */
    private static function resolveValue(
        Container $container,
        mixed $value,
        array $parameters,
        int $depth,
    ): mixed {
        if ($depth > self::MAX_RUNTIME_GRAPH_DEPTH) {
            throw new ContainerException('container-compiled-value-depth-exceeded');
        }

        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (!\is_array($value)) {
            throw new ContainerException('container-compiled-value-invalid');
        }

        if (self::isServiceReference($value)) {
            $id = $value['id'];

            if ($id === Container::class || $id === ContainerInterface::class) {
                return $container;
            }

            try {
                return $container->get($id);
            } catch (\Throwable) {
                throw new ContainerException('container-compiled-service-reference-failed');
            }
        }

        if (self::isParameterReference($value)) {
            $name = $value['name'];

            if (!\array_key_exists($name, $parameters)) {
                throw new ContainerException('container-compiled-parameter-missing');
            }

            return $parameters[$name];
        }

        if (self::isClassReference($value)) {
            return $value['class'];
        }

        if (\array_is_list($value)) {
            if (\count($value) > self::MAX_RUNTIME_LIST_ITEMS) {
                throw new ContainerException('container-compiled-list-too-large');
            }

            $resolved = [];

            foreach ($value as $item) {
                $resolved[] = self::resolveValue(
                    container: $container,
                    value: $item,
                    parameters: $parameters,
                    depth: $depth + 1,
                );
            }

            return $resolved;
        }

        if (\count($value) > self::MAX_RUNTIME_MAP_KEYS) {
            throw new ContainerException('container-compiled-map-too-large');
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new ContainerException('container-compiled-map-key-invalid');
            }

            $resolved[$key] = self::resolveValue(
                container: $container,
                value: $item,
                parameters: $parameters,
                depth: $depth + 1,
            );
        }

        return $resolved;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{type: 'service', id: string} $value
     */
    private static function isServiceReference(array $value): bool
    {
        return \array_keys($value) === ['id', 'type']
            && ($value['type'] ?? null) === self::REF_SERVICE
            && \is_string($value['id'] ?? null)
            && $value['id'] !== '';
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{name: string, type: 'parameter'} $value
     */
    private static function isParameterReference(array $value): bool
    {
        return \array_keys($value) === ['name', 'type']
            && ($value['type'] ?? null) === self::REF_PARAMETER
            && \is_string($value['name'] ?? null)
            && $value['name'] !== '';
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{class: string, type: 'class'} $value
     */
    private static function isClassReference(array $value): bool
    {
        return \array_keys($value) === ['class', 'type']
            && ($value['type'] ?? null) === self::REF_CLASS
            && \is_string($value['class'] ?? null)
            && $value['class'] !== '';
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     *
     * @return array<string, true>
     */
    private static function knownServiceIds(array $services, array $aliases): array
    {
        $known = [];

        foreach (self::reservedRuntimeServiceIds() as $reservedId) {
            $known[$reservedId] = true;
        }

        foreach ($services as $id => $_definition) {
            if (\is_string($id)) {
                $known[$id] = true;
            }
        }

        foreach ($aliases as $id => $_target) {
            if (\is_string($id)) {
                $known[$id] = true;
            }
        }

        return $known;
    }

    /**
     * @return list<string>
     */
    private static function reservedRuntimeServiceIds(): array
    {
        return [
            Container::class,
            ContainerInterface::class,
            TagRegistry::class,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, true>
     */
    private static function knownParameterNames(array $parameters): array
    {
        $known = [];

        foreach ($parameters as $name => $_value) {
            if (\is_string($name)) {
                $known[$name] = true;
            }
        }

        return $known;
    }

    /**
     * @param array<string, string> $aliases
     * @param array<string, true> $knownServiceIds
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertAliasTargetsKnown(array $aliases, array $knownServiceIds): void
    {
        foreach ($aliases as $alias => $target) {
            if (
                !\is_string($alias)
                || !\is_string($target)
                || !isset($knownServiceIds[$target])
                || self::isReservedRuntimeServiceId($target)
            ) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, true> $knownServiceIds
     * @param array<string, true> $knownParameterNames
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertCompiledGraphReferencesKnown(
        array $services,
        array $knownServiceIds,
        array $knownParameterNames,
    ): void {
        foreach ($services as $definition) {
            if (!\is_array($definition) || \array_is_list($definition)) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertKnownReferencesInValue(
                value: $definition['arguments'] ?? null,
                knownServiceIds: $knownServiceIds,
                knownParameterNames: $knownParameterNames,
                depth: 0,
            );

            self::assertKnownReferencesInValue(
                value: $definition['construction'] ?? null,
                knownServiceIds: $knownServiceIds,
                knownParameterNames: $knownParameterNames,
                depth: 0,
            );

            self::assertFactoryServiceTargetKnown(
                definition: $definition,
                knownServiceIds: $knownServiceIds,
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertNoReservedRuntimeDefinitionConflicts(
        array $services,
        array $aliases,
    ): void {
        foreach (self::reservedRuntimeServiceIds() as $reservedId) {
            if (\array_key_exists($reservedId, $services) || \array_key_exists($reservedId, $aliases)) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }
        }
    }

    /**
     * @param array<string, true> $knownServiceIds
     * @param array<string, true> $knownParameterNames
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertKnownReferencesInValue(
        mixed $value,
        array $knownServiceIds,
        array $knownParameterNames,
        int $depth,
    ): void {
        if ($depth > self::MAX_RUNTIME_GRAPH_DEPTH) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (!\is_array($value)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (self::isServiceReference($value)) {
            if (!isset($knownServiceIds[$value['id']])) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            return;
        }

        if (self::isParameterReference($value)) {
            if (!isset($knownParameterNames[$value['name']])) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            return;
        }

        foreach ($value as $item) {
            self::assertKnownReferencesInValue(
                value: $item,
                knownServiceIds: $knownServiceIds,
                knownParameterNames: $knownParameterNames,
                depth: $depth + 1,
            );
        }
    }

    /**
     * @param array<string, mixed> $map
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertRuntimeGraphMap(array $map, int $depth): void
    {
        if ($depth > self::MAX_RUNTIME_GRAPH_DEPTH) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if (!self::isMapArray($map) || \count($map) > self::MAX_RUNTIME_MAP_KEYS) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        foreach ($map as $key => $value) {
            if (!\is_string($key) || $key === '' || \strlen($key) > self::MAX_RUNTIME_STRING_BYTES) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            self::assertRuntimeGraphValue($value, $depth + 1);
        }
    }

    /**
     * @throws ContainerArtifactInvalidException
     */
    private static function assertRuntimeGraphValue(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_RUNTIME_GRAPH_DEPTH) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        if (\is_float($value) || \is_object($value) || \is_resource($value)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (!\is_array($value)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_PAYLOAD_INVALID,
            );
        }

        if (\array_is_list($value)) {
            if (\count($value) > self::MAX_RUNTIME_LIST_ITEMS) {
                throw ContainerArtifactInvalidException::withReason(
                    ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
                );
            }

            foreach ($value as $item) {
                self::assertRuntimeGraphValue($item, $depth + 1);
            }

            return;
        }

        self::assertRuntimeGraphMap($value, $depth + 1);
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, true> $knownServiceIds
     *
     * @throws ContainerArtifactInvalidException
     */
    private static function assertFactoryServiceTargetKnown(
        array $definition,
        array $knownServiceIds,
    ): void {
        if (($definition['type'] ?? null) !== self::SERVICE_TYPE_FACTORY) {
            return;
        }

        $construction = $definition['construction'] ?? null;

        if (!\is_array($construction) || \array_is_list($construction)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $factory = $construction[self::CONSTRUCTION_KEY_FACTORY] ?? null;

        if (!\is_array($factory) || \array_is_list($factory)) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }

        $factoryType = $factory[self::FACTORY_KEY_KIND] ?? null;

        if ($factoryType !== self::FACTORY_SERVICE_METHOD) {
            return;
        }

        $factoryServiceId = $factory[self::FACTORY_KEY_SERVICE] ?? null;

        if (
            !\is_string($factoryServiceId)
            || !isset($knownServiceIds[$factoryServiceId])
            || self::isReservedRuntimeServiceId($factoryServiceId)
        ) {
            throw ContainerArtifactInvalidException::withReason(
                ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            );
        }
    }

    /**
     * @param array<int|string, mixed>|null $envelope
     */
    private static function mapArtifactInvalidReason(
        ArtifactInvalidException $exception,
        ?array $envelope,
    ): string {
        $payload = $envelope['payload'] ?? null;

        if (\is_array($payload) && !\array_is_list($payload)) {
            if (($payload[self::PAYLOAD_KEY_KIND] ?? null) === 'stub') {
                return ContainerArtifactInvalidException::REASON_LEGACY_STUB;
            }

            if (
                ($payload[self::PAYLOAD_KEY_KIND] ?? null) !== self::KIND_COMPILED
                || ($payload[self::PAYLOAD_KEY_COMPILED] ?? null) !== true
            ) {
                return ContainerArtifactInvalidException::REASON_NON_COMPILED;
            }
        }

        return match ($exception->reason()) {
            ArtifactInvalidException::REASON_UNREADABLE => ContainerArtifactInvalidException::REASON_UNREADABLE,
            ArtifactInvalidException::REASON_READ_FAILED => ContainerArtifactInvalidException::REASON_READ_FAILED,
            ArtifactInvalidException::REASON_PHP_RETURN_TYPE_INVALID => ContainerArtifactInvalidException::REASON_RETURN_TYPE_INVALID,
            ArtifactInvalidException::REASON_ENVELOPE_INVALID => ContainerArtifactInvalidException::REASON_ENVELOPE_INVALID,
            ArtifactInvalidException::REASON_HEADER_INVALID,
            ArtifactInvalidException::REASON_FINGERPRINT_INVALID,
            ArtifactInvalidException::REASON_NAME_MISMATCH => ContainerArtifactInvalidException::REASON_HEADER_INVALID,
            ArtifactInvalidException::REASON_PAYLOAD_INVALID => ContainerArtifactInvalidException::REASON_PAYLOAD_INVALID,
            ArtifactInvalidException::REASON_SCHEMA_VERSION_MISMATCH => ContainerArtifactInvalidException::REASON_SCHEMA_VERSION_INVALID,
            ArtifactInvalidException::REASON_SCHEMA_INVALID => ContainerArtifactInvalidException::REASON_SCHEMA_INVALID,
            default => ContainerArtifactInvalidException::REASON_INVALID,
        };
    }

    private static function isReservedRuntimeServiceId(string $id): bool
    {
        return \in_array($id, self::reservedRuntimeServiceIds(), true);
    }

    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }
}
