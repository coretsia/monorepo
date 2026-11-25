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

use Coretsia\Foundation\Filesystem\AtomicFileWriter;
use Coretsia\Foundation\Filesystem\Exception\AtomicFileWriteException;
use Coretsia\Foundation\Filesystem\Exception\ScopedFileLockException;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Routing\Exception\RoutesArtifactWriteFailedException;

/**
 * Publishes only fully validated canonical Routing artifact bytes.
 *
 * The successful atomic replacement is the publication commit point. A
 * subsequent lock-release failure cannot undo or invalidate that commit.
 *
 * @internal
 */
final readonly class RoutesArtifactWriter
{
    public function __construct(
        private RoutesArtifactSchema $schema,
        private RoutesArtifactPath $paths,
        private ScopedFileLock $lock,
        private AtomicFileWriter $atomicWriter,
        private ModulePlan $modulePlan,
        private RuntimeDiagnosticsInput $diagnostics,
    ) {
    }

    /**
     * @param array<mixed> $envelope
     * @return bool True when new bytes were published; false when identical.
     */
    public function write(array $envelope): bool
    {
        // Validation and serialization MUST precede all publication operations.
        $bytes = $this->schema->serializeEnvelope(
            $envelope,
            $this->modulePlan->app(),
            $this->diagnostics->appEnv(),
        );

        $lockPath = $this->paths->lockPath();
        $published = false;
        $operationCompleted = false;

        try {
            return $this->lock->exclusive(
                $lockPath,
                function () use ($bytes, &$published, &$operationCompleted): bool {
                    // The lock helper may have created the target directory.
                    // Revalidate both targets after acquiring the lock.
                    $this->paths->assertSafeTargets();
                    $path = $this->paths->artifactPath();
                    if ($this->existingBytesEqual($path, $bytes)) {
                        $operationCompleted = true;
                        return false;
                    }

                    // No target deletion or rollback: AtomicFileWriter publishes
                    // a complete temporary file by replacing the target.
                    try {
                        $this->atomicWriter->write($path, $bytes);
                    } catch (AtomicFileWriteException $failure) {
                        throw RoutesArtifactWriteFailedException::fromAtomicWriteFailure($failure);
                    }

                    $published = true;
                    $operationCompleted = true;
                    return true;
                },
            );
        } catch (ScopedFileLockException $failure) {
            // ScopedFileLock throws after its callback only if cleanup fails.
            // A successful atomic rename has already committed publication.
            if ($published) {
                return true;
            }

            if ($operationCompleted) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_WRITE_FAILED,
                );
            }

            throw RoutesArtifactWriteFailedException::fromLockAcquisitionFailure($failure);
        } catch (RoutesArtifactWriteFailedException $failure) {
            throw $failure;
        } catch (\Throwable) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_WRITE_FAILED,
            );
        }
    }

    private function existingBytesEqual(string $path, string $replacement): bool
    {
        \clearstatcache(true, $path);
        $stat = @\lstat($path);
        if ($stat === false) {
            return false;
        }
        if (($stat['mode'] & 0170000) !== 0100000) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
            );
        }

        // No read is necessary when lengths differ. In particular, oversized
        // previous artifacts are never loaded for an equality comparison.
        $length = \strlen($replacement);
        if ($stat['size'] !== $length) {
            return false;
        }

        $handle = @\fopen($path, 'rb');
        if (!\is_resource($handle)) {
            throw RoutesArtifactWriteFailedException::withReason(
                RoutesArtifactWriteFailedException::REASON_EXISTING_READ_FAILED,
            );
        }

        try {
            $opened = @\fstat($handle);
            \clearstatcache(true, $path);
            $current = @\lstat($path);
            if (
                !\is_array($opened)
                || ($opened['mode'] & 0170000) !== 0100000
                || !\is_array($current)
                || ($current['mode'] & 0170000) !== 0100000
            ) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE,
                );
            }

            if (
                $opened['size'] !== $length
                || $opened['dev'] !== $current['dev']
                || $opened['ino'] !== $current['ino']
            ) {
                return false;
            }

            // Read at most the replacement length plus one byte, never an
            // unbounded or independently configured artifact snapshot.
            $existing = @\stream_get_contents($handle, $length + 1);
            if (!\is_string($existing)) {
                throw RoutesArtifactWriteFailedException::withReason(
                    RoutesArtifactWriteFailedException::REASON_EXISTING_READ_FAILED,
                );
            }

            return $existing === $replacement;
        } finally {
            @\fclose($handle);
        }
    }
}
