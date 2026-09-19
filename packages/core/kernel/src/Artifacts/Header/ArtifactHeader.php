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

namespace Coretsia\Kernel\Artifacts\Header;

use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;

/**
 * Immutable canonical artifact header value object.
 *
 * The canonical artifact header fields are defined by docs/ssot/artifacts.md:
 *
 * - name;
 * - schemaVersion;
 * - fingerprint;
 * - generator;
 * - optional requires.
 *
 * This value object intentionally contains no timestamps, no absolute paths, no
 * hostnames, no user names, no process ids, no tool auto-detected versions, and
 * no runtime/environment-specific bytes.
 *
 * It does not calculate fingerprints, build envelopes, validate artifact
 * payload schemas, read files, write files, or infer artifact ownership.
 *
 * @internal
 */
final readonly class ArtifactHeader
{
    private const int MIN_SCHEMA_VERSION = 1;

    private const int MAX_NAME_BYTES = 64;
    private const int MAX_GENERATOR_BYTES = 128;
    private const int MAX_FINGERPRINT_BYTES = 256;

    private const string SAFE_NAME_PATTERN = '/\A[a-z][a-z0-9-]{0,63}\z/';
    private const string SAFE_GENERATOR_PATTERN = '/\A[a-z][a-z0-9_.\/-]{0,127}\z/';
    private const string SAFE_FINGERPRINT_PATTERN = '/\A[a-z0-9][a-z0-9:._-]{0,255}\z/';

    private string $name;
    private int $schemaVersion;
    private string $fingerprint;
    private string $generator;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $requires;

    /**
     * @param array<string, mixed>|null $requires
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function __construct(
        string $name,
        int $schemaVersion,
        string $fingerprint,
        string $generator,
        ?array $requires = null,
    ) {
        $this->name = self::normalizeName($name);
        $this->schemaVersion = self::normalizeSchemaVersion($schemaVersion);
        $this->fingerprint = self::normalizeFingerprint($fingerprint);
        $this->generator = self::normalizeGenerator($generator);
        $this->requires = self::normalizeRequires($requires);
    }

    /**
     * @param array<string, mixed>|null $requires
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public static function create(
        string $name,
        int $schemaVersion,
        string $fingerprint,
        string $generator,
        ?array $requires = null,
    ): self {
        return new self(
            name: $name,
            schemaVersion: $schemaVersion,
            fingerprint: $fingerprint,
            generator: $generator,
            requires: $requires,
        );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint;
    }

    public function generator(): string
    {
        return $this->generator;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function requires(): ?array
    {
        return $this->requires;
    }

    /**
     * Stable exported scalar/json-like header shape.
     *
     * Logical header fields:
     *
     * - name
     * - schemaVersion
     * - fingerprint
     * - generator
     * - requires, only when present
     *
     * Emitted map key order is normalized later by PayloadNormalizer /
     * StablePhpArrayDumper according to the global bytewise strcmp artifact law.
     *
     * PayloadNormalizer / StablePhpArrayDumper may normalize map order again at
     * emission time, but this method keeps the logical header shape explicit.
     *
     * @return array{
     *     name: string,
     *     schemaVersion: int,
     *     fingerprint: string,
     *     generator: string,
     *     requires?: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        $header = [
            'name' => $this->name,
            'schemaVersion' => $this->schemaVersion,
            'fingerprint' => $this->fingerprint,
            'generator' => $this->generator,
        ];

        if ($this->requires !== null) {
            $header['requires'] = $this->requires;
        }

        return $header;
    }

    private static function normalizeName(string $name): string
    {
        if ($name === '') {
            throw new \InvalidArgumentException('artifact-header-name-empty');
        }

        if (\strlen($name) > self::MAX_NAME_BYTES) {
            throw new \InvalidArgumentException('artifact-header-name-invalid');
        }

        if (\preg_match(self::SAFE_NAME_PATTERN, $name) !== 1) {
            throw new \InvalidArgumentException('artifact-header-name-invalid');
        }

        return $name;
    }

    private static function normalizeSchemaVersion(int $schemaVersion): int
    {
        if ($schemaVersion < self::MIN_SCHEMA_VERSION) {
            throw new \InvalidArgumentException('artifact-header-schema-version-invalid');
        }

        return $schemaVersion;
    }

    private static function normalizeFingerprint(string $fingerprint): string
    {
        if ($fingerprint === '') {
            throw new \InvalidArgumentException('artifact-header-fingerprint-empty');
        }

        if (\strlen($fingerprint) > self::MAX_FINGERPRINT_BYTES) {
            throw new \InvalidArgumentException('artifact-header-fingerprint-invalid');
        }

        if (\preg_match(self::SAFE_FINGERPRINT_PATTERN, $fingerprint) !== 1) {
            throw new \InvalidArgumentException('artifact-header-fingerprint-invalid');
        }

        return $fingerprint;
    }

    private static function normalizeGenerator(string $generator): string
    {
        if ($generator === '') {
            throw new \InvalidArgumentException('artifact-header-generator-empty');
        }

        if (\strlen($generator) > self::MAX_GENERATOR_BYTES) {
            throw new \InvalidArgumentException('artifact-header-generator-invalid');
        }

        if (\preg_match(self::SAFE_GENERATOR_PATTERN, $generator) !== 1) {
            throw new \InvalidArgumentException('artifact-header-generator-invalid');
        }

        if (
            \str_contains($generator, '..')
            || \str_contains($generator, '//')
            || \str_contains($generator, '://')
            || \str_contains($generator, '\\')
            || \str_starts_with($generator, '/')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $generator) === 1
        ) {
            throw new \InvalidArgumentException('artifact-header-generator-invalid');
        }

        return $generator;
    }

    /**
     * @param array<string, mixed>|null $requires
     *
     * @return array<string, mixed>|null
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    private static function normalizeRequires(?array $requires): ?array
    {
        if ($requires === null) {
            return null;
        }

        if ($requires === [] || \array_is_list($requires)) {
            throw ArtifactPayloadInvalidException::atPath(
                'header.requires',
                ArtifactPayloadInvalidException::REASON_TYPE_FORBIDDEN,
            );
        }

        /** @var array<string, mixed> $normalized */
        $normalized = PayloadNormalizer::normalizePayloadMap($requires, 'header.requires');

        return $normalized;
    }
}
