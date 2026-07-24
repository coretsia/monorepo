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

namespace Coretsia\Kernel\Artifacts\Generation;

use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Exception\ArtifactGenerationPublishException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;

/**
 * Publishes immutable artifact generations through one locked current-pointer
 * switch.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final readonly class ArtifactGenerationPublisher
{
    private const int DIRECTORY_PERMISSIONS = 0775;
    private const int RANDOM_BYTES = 16;

    /**
     * @var list<non-empty-string>
     */
    private const array GENERATION_BASENAMES = [
        ArtifactGeneration::CONFIG_BASENAME,
        ArtifactGeneration::CONTAINER_BASENAME,
        ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
        ArtifactGeneration::MODULE_MANIFEST_BASENAME,
    ];

    public function __construct(
        private ArtifactWriter $artifactWriter,
        private StablePhpArrayDumper $phpArrayDumper,
        private ArtifactGenerationManifestBuilder $manifestBuilder,
        private ArtifactGenerationValidator $validator,
        private ArtifactGenerationLock $lock,
        private ArtifactGenerationPathResolver $pathResolver,
    ) {
    }

    /**
     * @throws ArtifactGenerationPublishException
     */
    public function publish(
        string $artifactRoot,
        ArtifactPublicationSet $publicationSet,
    ): ArtifactGeneration {
        $generationId = $publicationSet->generationId();
        $stagingDirectory = null;
        $pointerTemporaryPath = null;

        try {
            $stagingDirectory = $this->createStagingDirectory(
                artifactRoot: $artifactRoot,
                generationId: $generationId,
            );

            $this->writePublicationFiles(
                stagingDirectory: $stagingDirectory,
                publicationSet: $publicationSet,
            );
            $this->writeGenerationManifest(
                stagingDirectory: $stagingDirectory,
                publicationSet: $publicationSet,
            );

            try {
                $this->validator->validateStaging(
                    stagingDirectory: $stagingDirectory,
                    generationId: $generationId,
                );
            } catch (\Throwable) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_GENERATION_INVALID,
                );
            }

            return $this->lock->exclusive(
                $artifactRoot,
                function () use (
                    $artifactRoot,
                    $generationId,
                    &$stagingDirectory,
                    &$pointerTemporaryPath,
                ): ArtifactGeneration {
                    $generation = $this->publishOrReuseGeneration(
                        artifactRoot: $artifactRoot,
                        generationId: $generationId,
                        stagingDirectory: $stagingDirectory,
                    );
                    $stagingDirectory = null;

                    $currentPath = $this->pathResolver->currentPath($artifactRoot);
                    $pointerTemporary = $this->writeCurrentPointerTemporary(
                        currentPath: $currentPath,
                        generationId: $generationId,
                    );
                    $pointerTemporaryPath = $pointerTemporary['path'];

                    try {
                        $this->artifactWriter->replaceDurableFile(
                            temporaryPath: $pointerTemporaryPath,
                            targetPath: $currentPath,
                            backupBasename: self::randomBasename(
                                prefix: '.current-backup-',
                                failureReason: ArtifactGenerationPublishException::REASON_POINTER_SWITCH_FAILED,
                            ),
                        );
                    } catch (\Throwable) {
                        throw self::failure(
                            ArtifactGenerationPublishException::REASON_POINTER_SWITCH_FAILED,
                        );
                    }

                    $pointerTemporaryPath = null;

                    return $generation;
                },
            );
        } catch (ArtifactGenerationPublishException $exception) {
            if (!$this->cleanupFailureState($stagingDirectory, $pointerTemporaryPath)) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_CLEANUP_FAILED,
                );
            }

            throw $exception;
        } catch (\Throwable) {
            if (!$this->cleanupFailureState($stagingDirectory, $pointerTemporaryPath)) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_CLEANUP_FAILED,
                );
            }

            throw self::failure(
                ArtifactGenerationPublishException::REASON_GENERATION_INVALID,
            );
        }
    }

    private function createStagingDirectory(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
    ): string {
        try {
            $generationsDirectory = $this->pathResolver->generationsDirectory($artifactRoot);

            if (@\is_link($generationsDirectory)) {
                throw new \RuntimeException('invalid');
            }

            if (!@\is_dir($generationsDirectory)) {
                if (
                    @\file_exists($generationsDirectory)
                    || (!@\mkdir($generationsDirectory, self::DIRECTORY_PERMISSIONS, true)
                        && !@\is_dir($generationsDirectory))
                ) {
                    throw new \RuntimeException('invalid');
                }
            }

            $stagingDirectory = $this->pathResolver->newStagingDirectory(
                artifactRoot: $artifactRoot,
                generationId: $generationId,
            );

            if (!@\mkdir($stagingDirectory, self::DIRECTORY_PERMISSIONS, false)) {
                throw new \RuntimeException('invalid');
            }

            return $stagingDirectory;
        } catch (\Throwable) {
            throw self::failure(
                ArtifactGenerationPublishException::REASON_STAGING_CREATE_FAILED,
            );
        }
    }

    private function writePublicationFiles(
        string $stagingDirectory,
        ArtifactPublicationSet $publicationSet,
    ): void {
        $files = [
            ArtifactGeneration::MODULE_MANIFEST_BASENAME => $publicationSet->moduleManifestBytes(),
            ArtifactGeneration::CONFIG_BASENAME => $publicationSet->configBytes(),
            ArtifactGeneration::CONTAINER_BASENAME => $publicationSet->containerBytes(),
        ];

        foreach ($files as $basename => $bytes) {
            try {
                $this->artifactWriter->writeDurableFile(
                    targetPath: self::childPath($stagingDirectory, $basename),
                    bytes: $bytes,
                );
            } catch (ArtifactWriteFailedException $exception) {
                throw $this->mapWriteFailure(
                    exception: $exception,
                    defaultReason: ArtifactGenerationPublishException::REASON_WRITE_FAILED,
                );
            } catch (\Throwable) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_WRITE_FAILED,
                );
            }
        }
    }

    private function writeGenerationManifest(
        string $stagingDirectory,
        ArtifactPublicationSet $publicationSet,
    ): void {
        try {
            $manifestBytes = $this->phpArrayDumper->dumpEnvelope(
                $this->manifestBuilder->build($publicationSet),
            );
            $this->artifactWriter->writeDurableFile(
                targetPath: self::childPath(
                    $stagingDirectory,
                    ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
                ),
                bytes: $manifestBytes,
            );
        } catch (ArtifactWriteFailedException $exception) {
            throw $this->mapWriteFailure(
                exception: $exception,
                defaultReason: ArtifactGenerationPublishException::REASON_WRITE_FAILED,
            );
        } catch (ArtifactGenerationPublishException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw self::failure(
                ArtifactGenerationPublishException::REASON_WRITE_FAILED,
            );
        }
    }

    private function publishOrReuseGeneration(
        string $artifactRoot,
        ArtifactGenerationId $generationId,
        string $stagingDirectory,
    ): ArtifactGeneration {
        $generation = $this->pathResolver->generation(
            artifactRoot: $artifactRoot,
            generationId: $generationId,
        );
        $finalDirectory = $generation->generationDirectory();

        if (@\file_exists($finalDirectory) || @\is_link($finalDirectory)) {
            try {
                $this->validator->validate($generation);
            } catch (\Throwable) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
                );
            }

            if (!self::generationDirectoriesMatch(
                $stagingDirectory,
                $finalDirectory,
            )) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
                );
            }

            if (!self::cleanupDirectory($stagingDirectory)) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_CLEANUP_FAILED,
                );
            }

            return $generation;
        }

        if (!@\rename($stagingDirectory, $finalDirectory)) {
            throw self::failure(
                ArtifactGenerationPublishException::REASON_GENERATION_CONFLICT,
            );
        }

        try {
            $this->validator->validate($generation);
        } catch (\Throwable) {
            if (!self::cleanupDirectory($finalDirectory)) {
                throw self::failure(
                    ArtifactGenerationPublishException::REASON_CLEANUP_FAILED,
                );
            }

            throw self::failure(
                ArtifactGenerationPublishException::REASON_GENERATION_INVALID,
            );
        }

        return $generation;
    }

    /**
     * @return array{path: non-empty-string, bytes: int}
     */
    private function writeCurrentPointerTemporary(
        string $currentPath,
        ArtifactGenerationId $generationId,
    ): array {
        try {
            return $this->artifactWriter->writeDurableTemporaryFile(
                targetPath: $currentPath,
                temporaryBasename: self::randomBasename(
                    prefix: '.current-',
                    failureReason: ArtifactGenerationPublishException::REASON_POINTER_WRITE_FAILED,
                ),
                bytes: $generationId->value() . "\n",
            );
        } catch (ArtifactWriteFailedException $exception) {
            throw $this->mapWriteFailure(
                exception: $exception,
                defaultReason: ArtifactGenerationPublishException::REASON_POINTER_WRITE_FAILED,
            );
        } catch (ArtifactGenerationPublishException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw self::failure(
                ArtifactGenerationPublishException::REASON_POINTER_WRITE_FAILED,
            );
        }
    }

    private function mapWriteFailure(
        ArtifactWriteFailedException $exception,
        string $defaultReason,
    ): ArtifactGenerationPublishException {
        return match ($exception->reason()) {
            ArtifactWriteFailedException::REASON_DURABLE_FILE_SYNC_FAILED,
            ArtifactWriteFailedException::REASON_DURABLE_FILE_FLUSH_FAILED => self::failure(
                ArtifactGenerationPublishException::REASON_SYNC_FAILED,
            ),
            ArtifactWriteFailedException::REASON_TEMP_FILE_CLEANUP_FAILED => self::failure(
                ArtifactGenerationPublishException::REASON_CLEANUP_FAILED,
            ),
            default => self::failure($defaultReason),
        };
    }

    private function cleanupFailureState(
        ?string $stagingDirectory,
        ?string $pointerTemporaryPath,
    ): bool {
        $clean = true;

        if ($pointerTemporaryPath !== null) {
            $clean = self::cleanupFile($pointerTemporaryPath);
        }

        if ($stagingDirectory !== null) {
            $clean = self::cleanupDirectory($stagingDirectory) && $clean;
        }

        return $clean;
    }

    private static function generationDirectoriesMatch(
        string $stagingDirectory,
        string $finalDirectory,
    ): bool {
        foreach (self::GENERATION_BASENAMES as $basename) {
            $stagingPath = self::childPath(
                $stagingDirectory,
                $basename,
            );
            $finalPath = self::childPath(
                $finalDirectory,
                $basename,
            );

            if (
                @\is_link($stagingPath)
                || @\is_link($finalPath)
                || !@\is_file($stagingPath)
                || !@\is_file($finalPath)
            ) {
                return false;
            }

            $stagingBytes = @\file_get_contents($stagingPath);
            $finalBytes = @\file_get_contents($finalPath);

            if (
                !\is_string($stagingBytes)
                || !\is_string($finalBytes)
                || $stagingBytes !== $finalBytes
            ) {
                return false;
            }
        }

        return true;
    }

    private static function cleanupDirectory(string $directory): bool
    {
        if (@\is_link($directory)) {
            return @\unlink($directory);
        }

        if (!@\file_exists($directory)) {
            return true;
        }

        if (!@\is_dir($directory)) {
            return false;
        }

        $entries = @\scandir($directory);

        if (!\is_array($entries)) {
            return false;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = self::childPath($directory, $entry);

            if (@\is_dir($path) && !@\is_link($path)) {
                if (!self::cleanupDirectory($path)) {
                    return false;
                }

                continue;
            }

            if (!self::cleanupFile($path)) {
                return false;
            }
        }

        return @\rmdir($directory);
    }

    private static function cleanupFile(string $path): bool
    {
        if (!@\file_exists($path) && !@\is_link($path)) {
            return true;
        }

        return @\unlink($path);
    }

    private static function randomBasename(
        string $prefix,
        string $failureReason,
    ): string {
        try {
            return $prefix . \bin2hex(\random_bytes(self::RANDOM_BYTES));
        } catch (\Throwable) {
            throw self::failure(
                $failureReason,
            );
        }
    }

    private static function childPath(string $directory, string $basename): string
    {
        return \rtrim($directory, '/\\') . '/' . $basename;
    }

    private static function failure(string $reason): ArtifactGenerationPublishException
    {
        return ArtifactGenerationPublishException::withReason($reason);
    }
}
