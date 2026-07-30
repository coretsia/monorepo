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

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;

/**
 * Validates one immutable artifact generation as a complete filesystem unit.
 *
 * @internal Kernel atomic artifact generation publication boundary.
 */
final readonly class ArtifactGenerationValidator
{
    /**
     * @var list<non-empty-string>
     */
    private const array REQUIRED_BASENAMES = [
        ArtifactGeneration::CONFIG_BASENAME,
        ArtifactGeneration::CONTAINER_BASENAME,
        ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
        ArtifactGeneration::MODULE_MANIFEST_BASENAME,
    ];

    public function __construct(
        private PhpArtifactReader $artifactReader = new PhpArtifactReader(),
        private ArtifactSchemaValidator $schemaValidator = new ArtifactSchemaValidator(),
        private ArtifactGenerationManifestValidator $manifestValidator = new ArtifactGenerationManifestValidator(),
    ) {
    }

    /**
     * @throws ArtifactInvalidException
     */
    public function validate(ArtifactGeneration $generation): void
    {
        $this->validateDirectory(
            directory: $generation->generationDirectory(),
            generationId: $generation->generationId(),
            staging: false,
        );
    }

    /**
     * @throws ArtifactInvalidException
     */
    public function validateStaging(
        string $stagingDirectory,
        ArtifactGenerationId $generationId,
    ): void {
        $this->validateDirectory(
            directory: $stagingDirectory,
            generationId: $generationId,
            staging: true,
        );
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function validateDirectory(
        string $directory,
        ArtifactGenerationId $generationId,
        bool $staging,
    ): void {
        try {
            $this->assertDirectoryIdentity(
                directory: $directory,
                generationId: $generationId,
                staging: $staging,
            );
            $this->assertExactRequiredFiles($directory);

            $manifestPath = self::childPath(
                $directory,
                ArtifactGeneration::GENERATION_MANIFEST_BASENAME,
            );
            $manifestRead = $this->artifactReader->readExact(
                $manifestPath,
            );
            $manifestEnvelope = $manifestRead['envelope'];

            $this->manifestValidator->validate($manifestEnvelope);
            self::assertEnvelopeFingerprint(
                envelope: $manifestEnvelope,
                generationId: $generationId,
            );

            $payload = $manifestEnvelope['payload'] ?? null;
            $manifestGenerationId = \is_array($payload)
                ? ($payload['generationId'] ?? null)
                : null;
            $metadata = \is_array($payload)
                ? ($payload['artifacts'] ?? null)
                : null;

            if (
                $manifestGenerationId !== $generationId->value()
                || !\is_array($metadata)
                || \array_is_list($metadata)
            ) {
                throw self::invalid();
            }

            $this->validateArtifact(
                directory: $directory,
                basename: ArtifactGeneration::MODULE_MANIFEST_BASENAME,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_MODULE_MANIFEST,
                expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_MODULE_MANIFEST,
                generationId: $generationId,
                metadata: $metadata,
            );
            $this->validateArtifact(
                directory: $directory,
                basename: ArtifactGeneration::CONFIG_BASENAME,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONFIG,
                expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONFIG,
                generationId: $generationId,
                metadata: $metadata,
            );
            $this->validateArtifact(
                directory: $directory,
                basename: ArtifactGeneration::CONTAINER_BASENAME,
                expectedName: ArtifactEnvelopeFactory::ARTIFACT_CONTAINER,
                expectedSchemaVersion: ArtifactEnvelopeFactory::SCHEMA_VERSION_CONTAINER,
                generationId: $generationId,
                metadata: $metadata,
            );
        } catch (ArtifactInvalidException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw self::invalid();
        }
    }

    /**
     * @param array<string, mixed> $metadata
     *
     * @throws ArtifactInvalidException
     */
    private function validateArtifact(
        string $directory,
        string $basename,
        string $expectedName,
        int $expectedSchemaVersion,
        ArtifactGenerationId $generationId,
        array $metadata,
    ): void {
        $entry = $metadata[$basename] ?? null;
        $expectedBytes = \is_array($entry)
            ? ($entry['bytes'] ?? null)
            : null;
        $expectedHash = \is_array($entry)
            ? ($entry['sha256'] ?? null)
            : null;

        if (!\is_int($expectedBytes) || !\is_string($expectedHash)) {
            throw self::invalid();
        }

        $path = self::childPath($directory, $basename);
        self::assertRegularNonSymlinkFile($path);

        $read = $this->artifactReader->readExact($path);
        $bytes = $read['bytes'];

        if (
            \strlen($bytes) !== $expectedBytes
            || !\hash_equals($expectedHash, \hash('sha256', $bytes))
        ) {
            throw self::invalid();
        }

        $envelope = $read['envelope'];

        $this->schemaValidator->validateExpected(
            envelope: $envelope,
            expectedName: $expectedName,
            expectedSchemaVersion: $expectedSchemaVersion,
        );
        self::assertEnvelopeFingerprint(
            envelope: $envelope,
            generationId: $generationId,
        );
    }

    /**
     * @param array<int|string, mixed> $envelope
     *
     * @throws ArtifactInvalidException
     */
    private static function assertEnvelopeFingerprint(
        array $envelope,
        ArtifactGenerationId $generationId,
    ): void {
        $meta = $envelope['_meta'] ?? null;
        $fingerprint = \is_array($meta)
            ? ($meta['fingerprint'] ?? null)
            : null;

        if ($fingerprint !== $generationId->value()) {
            throw self::invalid();
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function assertDirectoryIdentity(
        string $directory,
        ArtifactGenerationId $generationId,
        bool $staging,
    ): void {
        if (@\is_link($directory) || !@\is_dir($directory)) {
            throw self::invalid();
        }

        $normalized = \rtrim(\str_replace('\\', '/', $directory), '/');
        $basename = self::pathBasename($normalized);
        $parent = self::pathParent($normalized);

        if (
            @\is_link($parent)
            || !@\is_dir($parent)
            || self::pathBasename($parent)
            !== ArtifactGenerationPathResolver::GENERATIONS_DIRECTORY
        ) {
            throw self::invalid();
        }

        if (!$staging && $basename !== $generationId->value()) {
            throw self::invalid();
        }

        if (
            $staging
            && \preg_match(
                '/\A\.staging-'
                . \preg_quote($generationId->value(), '/')
                . '-[a-f0-9]{32}\z/',
                $basename,
            ) !== 1
        ) {
            throw self::invalid();
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private function assertExactRequiredFiles(string $directory): void
    {
        $entries = @\scandir($directory);

        if (!\is_array($entries)) {
            throw self::invalid();
        }

        $actual = \array_values(
            \array_filter(
                $entries,
                static fn (string $entry): bool => $entry !== '.' && $entry !== '..',
            ),
        );
        $expected = self::REQUIRED_BASENAMES;

        \sort($actual, \SORT_STRING);
        \sort($expected, \SORT_STRING);

        if ($actual !== $expected) {
            throw self::invalid();
        }

        foreach ($expected as $basename) {
            self::assertRegularNonSymlinkFile(
                self::childPath($directory, $basename),
            );
        }
    }

    /**
     * @throws ArtifactInvalidException
     */
    private static function assertRegularNonSymlinkFile(string $path): void
    {
        if (@\is_link($path) || !@\is_file($path) || !@\is_readable($path)) {
            throw self::invalid();
        }
    }

    private static function childPath(string $directory, string $basename): string
    {
        return \rtrim($directory, '/\\') . '/' . $basename;
    }

    private static function pathBasename(string $path): string
    {
        $position = \strrpos($path, '/');

        return $position === false
            ? $path
            : \substr($path, $position + 1);
    }

    private static function pathParent(string $path): string
    {
        $position = \strrpos($path, '/');

        if ($position === false) {
            return '';
        }

        return $position === 0
            ? '/'
            : \substr($path, 0, $position);
    }

    private static function invalid(): ArtifactInvalidException
    {
        return ArtifactInvalidException::withReason(
            ArtifactInvalidException::REASON_INVALID,
        );
    }
}
