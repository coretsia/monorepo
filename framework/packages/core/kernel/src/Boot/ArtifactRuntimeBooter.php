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
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Psr\Container\ContainerInterface;

/**
 * Public artifact-only production runtime boot facade.
 *
 * This class is the public boundary for building a runtime container from
 * Kernel-owned generated artifacts.
 *
 * It intentionally hides Kernel artifact/container implementation classes from
 * other packages. Callers provide already resolved artifact paths and receive a
 * PSR container.
 *
 * This class MUST NOT:
 *
 * - read source config files;
 * - run ConfigKernel;
 * - run module discovery;
 * - run providers as fallback;
 * - compile a new container graph;
 * - calculate fingerprints;
 * - write or repair artifacts;
 * - emit stdout/stderr;
 * - expose raw paths, raw config values, raw artifact payloads, env values,
 *   secrets, tokens, command lines, or previous throwable messages.
 */
final readonly class ArtifactRuntimeBooter
{
    /**
     * Builds a runtime container from Kernel-owned artifacts.
     *
     * @throws ArtifactRuntimeBootException
     */
    public function boot(
        string $configArtifactPath,
        string $containerArtifactPath,
    ): ContainerInterface {
        $reader = new PhpArtifactReader();
        $validator = new ArtifactSchemaValidator();

        $configPayload = self::readConfigPayload(
            reader: $reader,
            validator: $validator,
            configArtifactPath: $configArtifactPath,
        );

        try {
            $container = new CompiledContainerFactory(
                artifactReader: $reader,
                schemaValidator: $validator,
            )->build(
                containerArtifactPath: $containerArtifactPath,
                configPayload: $configPayload,
            );
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::containerArtifactInvalid();
        }

        return $container;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ArtifactRuntimeBootException
     */
    private static function readConfigPayload(
        PhpArtifactReader $reader,
        ArtifactSchemaValidator $validator,
        string $configArtifactPath,
    ): array {
        try {
            $read = $reader->read($configArtifactPath);
            $envelope = $read['envelope'];

            $validator->validateExpected(
                envelope: $envelope,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
                expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONFIG,
            );

            $payload = $envelope['payload'] ?? null;

            if (!\is_array($payload) || \array_is_list($payload)) {
                throw ArtifactRuntimeBootException::configArtifactInvalid();
            }

            /** @var array<string, mixed> $payload */
            return $payload;
        } catch (ArtifactRuntimeBootException $exception) {
            throw $exception;
        } catch (ArtifactInvalidException) {
            throw ArtifactRuntimeBootException::configArtifactInvalid();
        } catch (\Throwable) {
            throw ArtifactRuntimeBootException::configArtifactInvalid();
        }
    }
}
