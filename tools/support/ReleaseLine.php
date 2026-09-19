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

namespace Coretsia\Tools\Support;

/**
 * Validated repository release-line configuration.
 */
final class ReleaseLine
{
    private const string SCHEMA_VERSION = 'coretsia.releaseLine.v1';
    private const string RELATIVE_PATH = 'tools/release/release-line.json';

    private string $currentMinor;
    private string $devVersion;
    private string $publicConstraint;

    private function __construct(
        string $currentMinor,
        string $devVersion,
        string $publicConstraint,
    ) {
        $this->currentMinor = $currentMinor;
        $this->devVersion = $devVersion;
        $this->publicConstraint = $publicConstraint;
    }

    public static function load(RepositoryContext $repository): self
    {
        return self::fromFile($repository->resolveExistingFile(self::RELATIVE_PATH));
    }

    public static function fromFile(string $path): self
    {
        $raw = DeterministicFile::readTextNormalizedEol($path);

        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \RuntimeException('release-line-json-invalid');
        }

        if (!is_array($data) || array_is_list($data)) {
            throw new \RuntimeException('release-line-json-object-invalid');
        }

        return self::fromArray($data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $keys = array_keys($data);
        sort($keys, SORT_STRING);

        $expectedKeys = [
            'currentMinor',
            'devVersion',
            'publicConstraint',
            'schemaVersion',
        ];

        sort($expectedKeys, SORT_STRING);

        if ($keys !== $expectedKeys) {
            throw new \RuntimeException('release-line-object-keys-invalid');
        }

        $schemaVersion = $data['schemaVersion'] ?? null;
        $currentMinor = $data['currentMinor'] ?? null;
        $devVersion = $data['devVersion'] ?? null;
        $publicConstraint = $data['publicConstraint'] ?? null;

        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \RuntimeException('release-line-schema-version-invalid');
        }

        if (
            !is_string($currentMinor)
            || preg_match('~\A(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\z~', $currentMinor) !== 1
        ) {
            throw new \RuntimeException('release-line-current-minor-invalid');
        }

        if (!is_string($devVersion) || $devVersion !== $currentMinor . '.x-dev') {
            throw new \RuntimeException('release-line-dev-version-invalid');
        }

        if (!is_string($publicConstraint) || $publicConstraint !== '^' . $currentMinor . '.0') {
            throw new \RuntimeException('release-line-public-constraint-invalid');
        }

        return new self($currentMinor, $devVersion, $publicConstraint);
    }

    public function currentMinor(): string
    {
        return $this->currentMinor;
    }

    public function devVersion(): string
    {
        return $this->devVersion;
    }

    public function publicConstraint(): string
    {
        return $this->publicConstraint;
    }

    /**
     * @return array{schemaVersion:string,currentMinor:string,devVersion:string,publicConstraint:string}
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => self::SCHEMA_VERSION,
            'currentMinor' => $this->currentMinor,
            'devVersion' => $this->devVersion,
            'publicConstraint' => $this->publicConstraint,
        ];
    }
}
