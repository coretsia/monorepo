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

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;

/**
 * Composer metadata backed installed runtime module manifest reader.
 *
 * This reader owns Coretsia-specific runtime module classification.
 *
 * Discovery source is Composer installed metadata only, through
 * ComposerInstalledMetadataProvider.
 *
 * This reader intentionally does not:
 *
 * - scan framework/packages/*;
 * - scan package directories;
 * - scan source trees;
 * - scan skeleton directories;
 * - read package composer.json files from disk;
 * - instantiate module classes;
 * - require package filesystem paths to derive module identity;
 * - use Composer package-level require/conflict as runtime graph edges.
 *
 * A package is a Coretsia runtime module only when:
 *
 * - extra.coretsia.moduleId is present and valid;
 * - extra.coretsia.kind is exactly "runtime".
 *
 * Runtime graph metadata is read only from:
 *
 * - extra.coretsia.requires;
 * - extra.coretsia.conflicts.
 *
 * Composer package-level require/conflict are installation/build constraints
 * and are intentionally ignored by this reader.
 *
 * @internal Kernel module-plan wiring implementation. Consumers should depend
 * on ManifestReaderInterface or ModulePlan output, not this concrete reader.
 */
final readonly class ComposerManifestReader implements ManifestReaderInterface
{
    private const string CORETSIA_EXTRA_KEY = 'coretsia';
    private const string RUNTIME_KIND = 'runtime';

    private const int MAX_SAFE_STRING_BYTES = 512;

    public function __construct(
        private ComposerInstalledMetadataProvider $metadataProvider,
    ) {
    }

    public function read(): ModuleManifest
    {
        $byModuleId = [];

        foreach ($this->metadataProvider->packages() as $package) {
            $descriptor = $this->descriptorFromPackageRecord($package);

            if ($descriptor === null) {
                continue;
            }

            $moduleId = $descriptor->id();

            if (isset($byModuleId[$moduleId->value()])) {
                throw ModuleManifestInvalidException::duplicateModuleId($moduleId);
            }

            $byModuleId[$moduleId->value()] = $descriptor;
        }

        \ksort($byModuleId, \SORT_STRING);

        try {
            return new ModuleManifest(\array_values($byModuleId));
        } catch (\InvalidArgumentException $exception) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid($exception);
        }
    }

    /**
     * @param array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * } $package
     */
    private function descriptorFromPackageRecord(array $package): ?ModuleDescriptor
    {
        $composerName = $this->readComposerName($package);
        $extra = $this->readPackageExtra($package);

        if (!\array_key_exists(self::CORETSIA_EXTRA_KEY, $extra)) {
            return null;
        }

        $coretsia = $extra[self::CORETSIA_EXTRA_KEY];

        if (!\is_array($coretsia) || \array_is_list($coretsia)) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid();
        }

        if (!\array_key_exists('moduleId', $coretsia)) {
            if (($coretsia['kind'] ?? null) === self::RUNTIME_KIND) {
                throw ModuleManifestInvalidException::coretsiaMetadataInvalid();
            }

            return null;
        }

        $moduleId = $this->readModuleId($coretsia['moduleId']);
        $kind = $this->readRuntimeKind($coretsia);
        $moduleClass = $this->readOptionalSafeSingleLineString(
            value: $coretsia['moduleClass'] ?? null,
            invalid: static fn (): ModuleManifestInvalidException => ModuleManifestInvalidException::moduleClassInvalid(
                $moduleId
            ),
        );

        $providers = $this->readOptionalSafeStringList(
            value: $coretsia['providers'] ?? null,
            invalid: static fn (): ModuleManifestInvalidException => ModuleManifestInvalidException::providersInvalid(
                $moduleId
            ),
        );

        $defaultsConfigPath = $this->readOptionalDefaultsConfigPath($moduleId, $coretsia['defaultsConfigPath'] ?? null);

        [$requires, $conflicts] = $this->readDependencyMetadata($moduleId, $coretsia);

        $metadata = [
            'conflicts' => $conflicts,
            'requires' => $requires,
        ];

        if ($defaultsConfigPath !== null) {
            $metadata['defaultsConfigPath'] = $defaultsConfigPath;
        }

        if ($providers !== []) {
            $metadata['providers'] = $providers;
        }

        try {
            return new ModuleDescriptor(
                id: $moduleId,
                composerName: $composerName,
                packageKind: $kind,
                moduleClass: $moduleClass,
                capabilities: [],
                metadata: $metadata,
            );
        } catch (\InvalidArgumentException $exception) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid($exception);
        }
    }

    /**
     * @param array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * } $package
     */
    private function readComposerName(array $package): string
    {
        if (!isset($package['name']) || !\is_string($package['name']) || $package['name'] === '') {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        return $package['name'];
    }

    /**
     * @param array{
     *     name: string,
     *     type: string|null,
     *     extra: array<string, mixed>,
     *     devRequirement: bool
     * } $package
     *
     * @return array<string, mixed>
     */
    private function readPackageExtra(array $package): array
    {
        if (!\array_key_exists('extra', $package)) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        if (!\is_array($package['extra'])) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        if ($package['extra'] !== [] && \array_is_list($package['extra'])) {
            throw ModuleManifestInvalidException::installedMetadataInvalid();
        }

        /** @var array<string, mixed> $extra */
        $extra = $package['extra'];

        return $extra;
    }

    private function readModuleId(mixed $value): ModuleId
    {
        if (!\is_string($value)) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid();
        }

        try {
            return ModuleId::fromString($value);
        } catch (\InvalidArgumentException $exception) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid($exception);
        }
    }

    /**
     * @param array<string, mixed> $coretsia
     */
    private function readRuntimeKind(array $coretsia): string
    {
        if (!\array_key_exists('kind', $coretsia)) {
            throw ModuleManifestInvalidException::kindInvalid();
        }

        if ($coretsia['kind'] !== self::RUNTIME_KIND) {
            throw ModuleManifestInvalidException::kindInvalid();
        }

        return self::RUNTIME_KIND;
    }

    /**
     * @return non-empty-string|null
     */
    private function readOptionalSafeSingleLineString(
        mixed $value,
        \Closure $invalid,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || !self::isSafeSingleLineString($value)) {
            throw $invalid();
        }

        return $value;
    }

    /**
     * @return list<non-empty-string>
     */
    private function readOptionalSafeStringList(
        mixed $value,
        \Closure $invalid,
    ): array {
        if ($value === null) {
            return [];
        }

        if (!\is_array($value) || !\array_is_list($value)) {
            throw $invalid();
        }

        $set = [];

        foreach ($value as $item) {
            if (!\is_string($item) || !self::isSafeSingleLineString($item)) {
                throw $invalid();
            }

            $set[$item] = true;
        }

        $values = \array_keys($set);

        \usort(
            $values,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $values;
    }

    /**
     * @return non-empty-string|null
     */
    private function readOptionalDefaultsConfigPath(ModuleId $moduleId, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || !self::isSafeRelativePathString($value)) {
            throw ModuleManifestInvalidException::coretsiaMetadataInvalid();
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $coretsia
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    private function readDependencyMetadata(ModuleId $moduleId, array $coretsia): array
    {
        $requires = $this->readModuleIdListMetadata(
            ownerModuleId: $moduleId,
            value: $coretsia['requires'] ?? [],
        );

        $conflicts = $this->readModuleIdListMetadata(
            ownerModuleId: $moduleId,
            value: $coretsia['conflicts'] ?? [],
        );

        $this->assertNoSelfDependency($moduleId, $requires);
        $this->assertNoSelfConflict($moduleId, $conflicts);
        $this->assertNoRequireConflictOverlap($moduleId, $requires, $conflicts);

        return [$requires, $conflicts];
    }

    /**
     * @return list<string>
     */
    private function readModuleIdListMetadata(ModuleId $ownerModuleId, mixed $value): array
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId);
        }

        $set = [];

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId);
            }

            try {
                $moduleId = ModuleId::fromString($item);
            } catch (\InvalidArgumentException $exception) {
                throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId, $exception);
            }

            $set[$moduleId->value()] = true;
        }

        $values = \array_keys($set);

        \usort(
            $values,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $values;
    }

    /**
     * @param list<string> $requires
     */
    private function assertNoSelfDependency(ModuleId $ownerModuleId, array $requires): void
    {
        if (\in_array($ownerModuleId->value(), $requires, true)) {
            throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId);
        }
    }

    /**
     * @param list<string> $conflicts
     */
    private function assertNoSelfConflict(ModuleId $ownerModuleId, array $conflicts): void
    {
        if (\in_array($ownerModuleId->value(), $conflicts, true)) {
            throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId);
        }
    }

    /**
     * @param list<string> $requires
     * @param list<string> $conflicts
     */
    private function assertNoRequireConflictOverlap(
        ModuleId $ownerModuleId,
        array $requires,
        array $conflicts,
    ): void {
        $requiresMap = [];

        foreach ($requires as $moduleId) {
            $requiresMap[$moduleId] = true;
        }

        foreach ($conflicts as $moduleId) {
            if (isset($requiresMap[$moduleId])) {
                throw ModuleManifestInvalidException::dependencyMetadataInvalid($ownerModuleId);
            }
        }
    }

    private static function isSafeSingleLineString(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (\strlen($value) > self::MAX_SAFE_STRING_BYTES) {
            return false;
        }

        if (\preg_match('/^\s|\s$/', $value) === 1) {
            return false;
        }

        return self::hasNoUnsafeControlCharacters($value)
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n");
    }

    private static function isSafeRelativePathString(string $value): bool
    {
        if (!self::isSafeSingleLineString($value)) {
            return false;
        }

        if (\str_contains($value, "\0")) {
            return false;
        }

        if (\str_contains($value, '://')) {
            return false;
        }

        if ($value[0] === '/' || $value[0] === '\\') {
            return false;
        }

        if (\strlen($value) >= 2 && $value[1] === ':' && self::isAsciiAlpha($value[0])) {
            return false;
        }

        $normalized = \str_replace('\\', '/', $value);

        if ($normalized !== \trim($normalized, '/')) {
            return false;
        }

        $segments = \explode('/', $normalized);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private static function hasNoUnsafeControlCharacters(string $value): bool
    {
        return \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    private static function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z')
            || ($char >= 'A' && $char <= 'Z');
    }
}
