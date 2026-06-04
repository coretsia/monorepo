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

namespace Coretsia\Kernel\Module;

use Composer\InstalledVersions;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;

/**
 * Provides normalized raw Composer installed package metadata.
 *
 * This class is a narrow boundary around Composer installed metadata.
 *
 * Production mode reads Composer installed metadata only through
 * Composer\InstalledVersions::getAllRawData().
 *
 * Test mode can inject deterministic raw Composer installed metadata arrays
 * without depending on the real project vendor/ state.
 *
 * This provider intentionally does not:
 *
 * - scan package directories;
 * - scan framework/packages/*;
 * - scan vendor/* for composer.json files;
 * - read package source trees;
 * - instantiate module classes;
 * - classify Coretsia module metadata;
 * - validate `extra.coretsia`;
 * - build ModuleDescriptor objects.
 *
 * Coretsia-specific metadata validation and module classification belong to
 * ComposerManifestReader.
 *
 * @internal
 */
final readonly class ComposerInstalledMetadataProvider
{
    /**
     * @var list<array<string, mixed>>|null
     */
    private ?array $installedData;

    /**
     * @param list<array<string, mixed>>|null $installedData
     */
    public function __construct(?array $installedData = null)
    {
        if ($installedData !== null && !\array_is_list($installedData)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        $this->installedData = $installedData;
    }

    /**
     * Returns normalized raw Composer package records keyed only by content.
     *
     * The returned records are deterministic and sorted by composer package
     * name using byte-order strcmp.
     *
     * Shape:
     *
     * [
     *     [
     *         'name' => 'coretsia/core-kernel',
     *         'type' => 'library',
     *         'extra' => [...],
     *         'devRequirement' => false,
     *     ],
     * ]
     *
     * `extra` is optional in Composer installed metadata and defaults to [].
     *
     * @return list<array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * }>
     */
    public function packages(): array
    {
        $packages = [];

        foreach ($this->installedRawData() as $dataset) {
            $this->collectRootPackage($packages, $dataset);
            $this->collectVersionPackages($packages, $dataset);
        }

        \ksort($packages, \SORT_STRING);

        return \array_values($packages);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function installedRawData(): array
    {
        if ($this->installedData !== null) {
            return self::normalizeInstalledDataSets($this->installedData);
        }

        if (!\class_exists(InstalledVersions::class)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        try {
            $rawData = InstalledVersions::getAllRawData();
        } catch (\Throwable $exception) {
            throw ModuleManifestInvalidException::installedMetadataInvalid($exception);
        }

        if (!\is_array($rawData) || !\array_is_list($rawData)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        return self::normalizeInstalledDataSets($rawData);
    }

    /**
     * @param array<string, array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * }> $packages
     * @param array<string, mixed> $dataset
     */
    private function collectRootPackage(array &$packages, array $dataset): void
    {
        if (!\array_key_exists('root', $dataset)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        $root = $dataset['root'];

        if (!\is_array($root)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        if (!\array_key_exists('name', $root)) {
            return;
        }

        $package = $this->normalizePackageRecord(
            name: $root['name'],
            metadata: $root,
            defaultDevRequirement: false,
        );

        $packages[$package['name']] = $package;
    }

    /**
     * @param array<string, array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * }> $packages
     * @param array<string, mixed> $dataset
     */
    private function collectVersionPackages(array &$packages, array $dataset): void
    {
        if (!\array_key_exists('versions', $dataset)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        $versions = $dataset['versions'];

        if (!\is_array($versions) || \array_is_list($versions)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        foreach ($versions as $name => $metadata) {
            if (!\is_string($name) || !\is_array($metadata)) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $package = $this->normalizePackageRecord(
                name: $name,
                metadata: $metadata,
                defaultDevRequirement: false,
            );

            /*
             * Duplicate composer package records across loaded Composer datasets
             * collapse deterministically by composer package name.
             *
             * If the same package appears more than once, the last normalized
             * record in dataset iteration wins here. ComposerManifestReader may
             * still apply stricter policy later if needed, but module identity
             * duplicate detection must be based on moduleId, not package record
             * repetition.
             */
            $packages[$package['name']] = $package;
        }
    }

    /**
     * @param mixed $name
     * @param array<string, mixed> $metadata
     * @param bool $defaultDevRequirement
     * @return array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * }
     */
    private function normalizePackageRecord(
        mixed $name,
        array $metadata,
        bool $defaultDevRequirement,
    ): array {
        if (!\is_string($name) || !self::isSafeComposerPackageName($name)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        $type = null;
        if (\array_key_exists('type', $metadata)) {
            if ($metadata['type'] !== null && !\is_string($metadata['type'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            if (\is_string($metadata['type']) && !self::isSafePackageType($metadata['type'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $type = $metadata['type'];
        }

        $extra = [];
        if (\array_key_exists('extra', $metadata)) {
            if (!\is_array($metadata['extra'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            if ($metadata['extra'] !== [] && \array_is_list($metadata['extra'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            /** @var array<string, mixed> $extraMetadata */
            $extraMetadata = $metadata['extra'];

            $extra = self::normalizeJsonLikeMap($extraMetadata);
        }

        $devRequirement = $defaultDevRequirement;
        if (\array_key_exists('dev_requirement', $metadata)) {
            if (!\is_bool($metadata['dev_requirement'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $devRequirement = $metadata['dev_requirement'];
        } elseif (\array_key_exists('dev', $metadata)) {
            if (!\is_bool($metadata['dev'])) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $devRequirement = $metadata['dev'];
        }

        return [
            'name' => $name,
            'type' => $type,
            'extra' => $extra,
            'devRequirement' => $devRequirement,
        ];
    }

    /**
     * @param list<array<string, mixed>> $installedData
     *
     * @return list<array<string, mixed>>
     */
    private static function normalizeInstalledDataSets(array $installedData): array
    {
        $normalized = [];

        foreach ($installedData as $dataset) {
            if (!\is_array($dataset) || \array_is_list($dataset)) {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $normalized[] = $dataset;
        }

        return $normalized;
    }

    private static function isSafeComposerPackageName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (\strlen($name) > 128) {
            return false;
        }

        if (\str_contains($name, "\0") || \str_contains($name, '\\') || \str_contains($name, ':')) {
            return false;
        }

        if (\str_contains($name, '..')) {
            return false;
        }

        $parts = \explode('/', $name);

        if (\count($parts) !== 2) {
            return false;
        }

        foreach ($parts as $part) {
            if ($part === '') {
                return false;
            }

            if (\strspn($part, 'abcdefghijklmnopqrstuvwxyz0123456789_.-') !== \strlen($part)) {
                return false;
            }
        }

        return true;
    }

    private static function isSafePackageType(string $type): bool
    {
        if ($type === '') {
            return false;
        }

        if (\strlen($type) > 64) {
            return false;
        }

        if (\str_contains($type, '..')) {
            return false;
        }

        return \strspn($type, 'abcdefghijklmnopqrstuvwxyz0123456789_.-') === \strlen($type);
    }

    /**
     * @param array<string, mixed> $map
     *
     * @return array<string, mixed>
     */
    private static function normalizeJsonLikeMap(array $map): array
    {
        $normalized = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key) || $key === '') {
                throw ModuleManifestInvalidException::installedMetadataInvalid();
            }

            $normalized[$key] = self::normalizeJsonLikeValue($value);
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function normalizeJsonLikeValue(mixed $value): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return $value;
        }

        if (\is_float($value)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        if (\is_array($value)) {
            if ($value === []) {
                return [];
            }

            if (\array_is_list($value)) {
                $list = [];

                foreach ($value as $item) {
                    $list[] = self::normalizeJsonLikeValue($item);
                }

                return $list;
            }

            return self::normalizeJsonLikeMap($value);
        }

        throw ModuleManifestInvalidException::installedMetadataInvalid();
    }
}
