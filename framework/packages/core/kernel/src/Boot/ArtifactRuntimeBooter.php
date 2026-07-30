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

namespace Coretsia\Kernel\Boot;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Psr\Container\ContainerInterface;

/**
 * Public artifact-only production runtime boot facade.
 *
 * This boundary selects exactly one immutable generation through the current
 * pointer and builds the runtime container only from that validated generation.
 * Callers provide the Kernel artifact root, not individual artifact paths.
 *
 * This class MUST NOT:
 *
 * - read source config files;
 * - run ConfigKernel;
 * - run module discovery;
 * - read Composer metadata;
 * - run providers as fallback;
 * - compile a new container graph;
 * - calculate fingerprints;
 * - write or repair artifacts;
 * - accept caller-selected module-manifest/config/container paths;
 * - emit stdout/stderr;
 * - expose raw paths, raw config values, raw artifact payloads, env values,
 *   secrets, tokens, command lines, or previous throwable messages.
 */
final readonly class ArtifactRuntimeBooter
{
    /**
     * Builds a runtime container from the immutable generation
     * selected by `<artifact-root>/current`.
     *
     * @throws ArtifactRuntimeBootException
     */
    public function boot(
        ArtifactRuntimeInput $input,
    ): ContainerInterface {
        $reader = new PhpArtifactReader();
        $schemaValidator = new ArtifactSchemaValidator();
        $generationManifestValidator = new ArtifactGenerationManifestValidator(
            schemaValidator: $schemaValidator,
        );

        $generation = self::locateCurrentGeneration(
            artifactRoot: $input->artifactRoot(),
            reader: $reader,
            schemaValidator: $schemaValidator,
            generationManifestValidator: $generationManifestValidator,
        );

        try {
            $artifacts = self::readGenerationArtifacts(
                generation: $generation,
                reader: $reader,
            );

            self::validateGenerationArtifacts(
                generation: $generation,
                artifacts: $artifacts,
                schemaValidator: $schemaValidator,
                generationManifestValidator: $generationManifestValidator,
            );
        } catch (ArtifactRuntimeBootException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        $moduleManifestPayload = self::payload(
            $artifacts[ArtifactGeneration::MODULE_MANIFEST_BASENAME]['envelope'],
        );

        $configPayload = self::payload(
            $artifacts[ArtifactGeneration::CONFIG_BASENAME]['envelope'],
        );

        $seedFactory = new ArtifactRuntimeSeedFactory();

        try {
            $configRepository = $seedFactory->hydrateConfigRepository($configPayload);
        } catch (\Throwable) {
            /*
             * A config payload that cannot hydrate after config@1 schema
             * validation is an invalid generation, not a separate runtime
             * failure category.
             */
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        try {
            $modulePlan = $seedFactory->hydrateModulePlan($moduleManifestPayload);
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::moduleManifestArtifactInvalid();
        }

        try {
            $seeds = $seedFactory->create(
                input: $input,
                configRepository: $configRepository,
                modulePlan: $modulePlan,
            );
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::runtimeContainerInvalid();
        }

        try {
            return new CompiledContainerFactory(
                schemaValidator: $schemaValidator,
            )->buildFromEnvelope(
                containerEnvelope: $artifacts[ArtifactGeneration::CONTAINER_BASENAME]['envelope'],
                seeds: $seeds,
            );
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::containerArtifactInvalid();
        }
    }

    private static function locateCurrentGeneration(
        string $artifactRoot,
        PhpArtifactReader $reader,
        ArtifactSchemaValidator $schemaValidator,
        ArtifactGenerationManifestValidator $generationManifestValidator,
    ): ArtifactGeneration {
        $generationPathResolver = new ArtifactGenerationPathResolver();

        try {
            $generation = new ArtifactGenerationLocator(
                lock: new ArtifactGenerationLock(
                    pathResolver: $generationPathResolver,
                ),
                pathResolver: $generationPathResolver,
                validator: new ArtifactGenerationValidator(
                    artifactReader: $reader,
                    schemaValidator: $schemaValidator,
                    manifestValidator: $generationManifestValidator,
                ),
            )->locate($artifactRoot);
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        if (!$generation instanceof ArtifactGeneration) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        return $generation;
    }

    /**
     * @return array{
     *     'module-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'config.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'container.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'generation-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     }
     * }
     */
    private static function readGenerationArtifacts(
        ArtifactGeneration $generation,
        PhpArtifactReader $reader,
    ): array {
        return [
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => self::readArtifact(
                reader: $reader,
                path: $generation->moduleManifestPath(),
            ),
            ArtifactGeneration::CONFIG_BASENAME => self::readArtifact(
                reader: $reader,
                path: $generation->configPath(),
            ),
            ArtifactGeneration::CONTAINER_BASENAME => self::readArtifact(
                reader: $reader,
                path: $generation->containerPath(),
            ),
            ArtifactGeneration::GENERATION_MANIFEST_BASENAME => self::readArtifact(
                reader: $reader,
                path: $generation->generationManifestPath(),
            ),
        ];
    }

    /**
     * @return array{
     *     bytes: string,
     *     envelope: array<int|string, mixed>
     * }
     */
    private static function readArtifact(
        PhpArtifactReader $reader,
        string $path,
    ): array {
        return $reader->readExact($path);
    }

    /**
     * @param array{
     *     'module-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'config.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'container.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'generation-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     }
     * } $artifacts
     */
    private static function validateGenerationArtifacts(
        ArtifactGeneration $generation,
        array $artifacts,
        ArtifactSchemaValidator $schemaValidator,
        ArtifactGenerationManifestValidator $generationManifestValidator,
    ): void {
        $schemaValidator->validateExpected(
            envelope: $artifacts[ArtifactGeneration::MODULE_MANIFEST_BASENAME]['envelope'],
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_MODULE_MANIFEST,
        );

        $schemaValidator->validateExpected(
            envelope: $artifacts[ArtifactGeneration::CONFIG_BASENAME]['envelope'],
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONFIG,
        );

        $schemaValidator->validateExpected(
            envelope: $artifacts[ArtifactGeneration::CONTAINER_BASENAME]['envelope'],
            expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
            expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
        );

        $generationManifestEnvelope = $artifacts[ArtifactGeneration::GENERATION_MANIFEST_BASENAME]['envelope'];

        $generationManifestValidator->validate($generationManifestEnvelope);

        $generationId = $generation->generationId()->value();

        foreach ($artifacts as $artifact) {
            self::assertEnvelopeFingerprint(
                envelope: $artifact['envelope'],
                generationId: $generationId,
            );
        }

        self::assertGenerationManifestMatchesRuntimeArtifacts(
            generationManifestEnvelope: $generationManifestEnvelope,
            artifacts: $artifacts,
            generationId: $generationId,
        );
    }

    /**
     * @param array<int|string, mixed> $envelope
     */
    private static function assertEnvelopeFingerprint(
        array $envelope,
        string $generationId,
    ): void {
        $meta = $envelope['_meta'] ?? null;
        $fingerprint = \is_array($meta)
            ? ($meta['fingerprint'] ?? null)
            : null;

        if ($fingerprint !== $generationId) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }
    }

    /**
     * @param array<int|string, mixed> $generationManifestEnvelope
     * @param array{
     *     'module-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'config.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'container.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     },
     *     'generation-manifest.php': array{
     *         bytes: string,
     *         envelope: array<int|string, mixed>
     *     }
     * } $artifacts
     */
    private static function assertGenerationManifestMatchesRuntimeArtifacts(
        array $generationManifestEnvelope,
        array $artifacts,
        string $generationId,
    ): void {
        $payload = $generationManifestEnvelope['payload']
            ?? null;

        $manifestGenerationId = \is_array($payload)
            ? ($payload['generationId'] ?? null)
            : null;

        $metadata = \is_array($payload)
            ? ($payload['artifacts'] ?? null)
            : null;

        if (
            $manifestGenerationId !== $generationId
            || !\is_array($metadata)
            || \array_is_list($metadata)
        ) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        self::assertManifestEntryMatchesBytes(
            metadata: $metadata,
            basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
            bytes: $artifacts[ArtifactGeneration::MODULE_MANIFEST_BASENAME]['bytes'],
        );

        self::assertManifestEntryMatchesBytes(
            metadata: $metadata,
            basename: ArtifactGeneration::CONFIG_BASENAME,
            bytes: $artifacts[ArtifactGeneration::CONFIG_BASENAME]['bytes'],
        );

        self::assertManifestEntryMatchesBytes(
            metadata: $metadata,
            basename: ArtifactGeneration::CONTAINER_BASENAME,
            bytes: $artifacts[ArtifactGeneration::CONTAINER_BASENAME]['bytes'],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private static function assertManifestEntryMatchesBytes(
        array $metadata,
        string $basename,
        string $bytes,
    ): void {
        $entry = $metadata[$basename] ?? null;

        $expectedBytes = \is_array($entry)
            ? ($entry['bytes'] ?? null)
            : null;

        $expectedHash = \is_array($entry)
            ? ($entry['sha256'] ?? null)
            : null;

        if (
            !\is_int($expectedBytes)
            || !\is_string($expectedHash)
            || \strlen($bytes) !== $expectedBytes
            || !\hash_equals(
                $expectedHash,
                \hash('sha256', $bytes),
            )
        ) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }
    }

    /**
     * @param array<int|string, mixed> $envelope
     *
     * @return array<string, mixed>
     */
    private static function payload(
        array $envelope,
    ): array {
        $payload = $envelope['payload'] ?? null;

        if (
            !\is_array($payload)
            || \array_is_list($payload)
        ) {
            throw ArtifactRuntimeBootException::generationInvalid();
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
