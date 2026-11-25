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
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;

/**
 * Loads one non-executable bounded Routing artifact snapshot.
 *
 * All decoded declarations are revalidated against the authoritative target,
 * environment, route semantics, reserved paths and the routes@1 fingerprint.
 *
 * @internal
 */
final readonly class RoutesArtifactReader
{
    public function __construct(
        private RoutesArtifactPath $paths,
        private StablePhpArrayParser $parser,
        private RoutesArtifactSchema $schema,
        private ModulePlan $modulePlan,
        private RuntimeDiagnosticsInput $diagnostics,
    ) {
    }

    /**
     * @return list<RouteDefinition>
     */
    public function read(): array
    {
        $handle = null;
        try {
            $path = $this->paths->readableArtifactPath();
            $this->paths->assertReadableArtifact();

            $handle = @\fopen($path, 'rb');
            if (!\is_resource($handle)) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_UNREADABLE,
                );
            }

            $stat = @\fstat($handle);
            \clearstatcache(true, $path);
            $entry = @\lstat($path);
            if (
                !\is_array($stat)
                || ($stat['mode'] & 0170000) !== 0100000
                || !\is_array($entry)
                || ($entry['mode'] & 0170000) !== 0100000
                || $stat['dev'] !== $entry['dev']
                || $stat['ino'] !== $entry['ino']
            ) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_UNSAFE,
                );
            }

            if ($stat['size'] > RoutesArtifactSchema::MAX_ARTIFACT_BYTES) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_OVERSIZED,
                );
            }

            $bytes = @\stream_get_contents($handle, RoutesArtifactSchema::MAX_ARTIFACT_BYTES + 1);
            if (!\is_string($bytes)) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_READ_FAILED,
                );
            }

            if (\strlen($bytes) > RoutesArtifactSchema::MAX_ARTIFACT_BYTES) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_OVERSIZED,
                );
            }
            if (\strlen($bytes) !== $stat['size']) {
                throw RoutesArtifactInvalidException::withReason(
                    RoutesArtifactInvalidException::REASON_READ_FAILED,
                );
            }
        } catch (RoutesArtifactInvalidException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw RoutesArtifactInvalidException::withReason(
                RoutesArtifactInvalidException::REASON_READ_FAILED,
            );
        } finally {
            if (\is_resource($handle)) {
                try {
                    @\fclose($handle);
                } catch (\Throwable) {
                    // Closing cannot expose source exceptions or replace a read result.
                }
            }
        }

        try {
            $envelope = $this->parser->parseBounded($bytes, RoutesArtifactSchema::limits());
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromDecodingFailure($failure);
        }

        try {
            return $this->schema->hydrateEnvelope(
                $envelope,
                $this->modulePlan->app(),
                $this->diagnostics->appEnv(),
            );
        } catch (RoutesArtifactInvalidException $failure) {
            throw $failure;
        } catch (\Throwable $failure) {
            throw RoutesArtifactInvalidException::fromHydrationFailure($failure);
        }
    }
}
