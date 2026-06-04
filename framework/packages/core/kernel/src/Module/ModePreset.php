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

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModuleId;

/**
 * Immutable Kernel-owned loaded mode preset value object.
 *
 * This class represents an already validated mode preset in deterministic
 * runtime form.
 *
 * It intentionally stores only schema/preset/module metadata. It must not store
 * or expose source file paths, skeleton root, defaults path, overrides path,
 * filesystem handles, PHP objects, services, runtime wiring, or loader state.
 *
 * Source list order is not semantic. Module id sets are canonicalized and
 * exported in byte-order strcmp order.
 *
 * @internal Kernel preset loader/validator implementation. Cross-package code
 * should rely on ModePresetInterface.
 */
final readonly class ModePreset implements ModePresetInterface
{
    private const int MAX_JSON_DEPTH = 16;
    private const int MAX_JSON_MAP_KEYS = 256;
    private const int MAX_SAFE_DESCRIPTION_BYTES = 512;

    private const string SAFE_PRESET_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

    /**
     * @var list<ModuleId>
     */
    private array $required;

    /**
     * @var list<ModuleId>
     */
    private array $optional;

    /**
     * @var list<ModuleId>
     */
    private array $disabled;

    /**
     * @var array<string, mixed>
     */
    private array $featureBundles;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;

    /**
     * @param list<ModuleId> $required
     * @param list<ModuleId> $optional
     * @param list<ModuleId> $disabled
     * @param array<string, mixed> $featureBundles
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private int $schemaVersion,
        private string $name,
        private ?string $description,
        array $required,
        array $optional,
        array $disabled,
        array $featureBundles = [],
        array $metadata = [],
    ) {
        if ($schemaVersion !== self::SCHEMA_VERSION) {
            throw new \InvalidArgumentException('mode-preset-schema-version-invalid');
        }

        if (!self::isSafePresetName($name)) {
            throw new \InvalidArgumentException('mode-preset-name-invalid');
        }

        if ($description !== null && !self::isSafeDescription($description)) {
            throw new \InvalidArgumentException('mode-preset-description-invalid');
        }

        $requiredSet = self::normalizeModuleIdSet($required, 'required');
        $optionalSet = self::normalizeModuleIdSet($optional, 'optional');
        $disabledSet = self::normalizeModuleIdSet($disabled, 'disabled');

        self::assertDisjoint($requiredSet, $optionalSet, 'mode-preset-required-optional-overlap');
        self::assertDisjoint($requiredSet, $disabledSet, 'mode-preset-required-disabled-overlap');
        self::assertDisjoint($optionalSet, $disabledSet, 'mode-preset-optional-disabled-overlap');

        $this->required = $requiredSet;
        $this->optional = $optionalSet;
        $this->disabled = $disabledSet;
        $this->featureBundles = self::normalizeJsonLikeMap($featureBundles, 'featureBundles');
        $this->metadata = self::normalizeJsonLikeMap($metadata, 'metadata');
    }

    public function schemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    /**
     * @return list<ModuleId>
     */
    public function required(): array
    {
        return $this->required;
    }

    /**
     * @return list<ModuleId>
     */
    public function optional(): array
    {
        return $this->optional;
    }

    /**
     * @return list<ModuleId>
     */
    public function disabled(): array
    {
        return $this->disabled;
    }

    /**
     * @return list<ModuleId>
     */
    public function moduleIds(): array
    {
        $enabled = [];

        foreach ($this->required as $moduleId) {
            $enabled[$moduleId->value()] = $moduleId;
        }

        foreach ($this->optional as $moduleId) {
            $enabled[$moduleId->value()] = $moduleId;
        }

        foreach ($this->disabled as $moduleId) {
            unset($enabled[$moduleId->value()]);
        }

        \ksort($enabled, \SORT_STRING);

        return \array_values($enabled);
    }

    /**
     * @return array<string, mixed>
     */
    public function featureBundles(): array
    {
        return $this->featureBundles;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @return array{
     *     schemaVersion: int,
     *     name: string,
     *     description: string|null,
     *     required: list<string>,
     *     optional: list<string>,
     *     disabled: list<string>,
     *     featureBundles: array<string, mixed>,
     *     metadata: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'schemaVersion' => $this->schemaVersion,
            'name' => $this->name,
            'description' => $this->description,
            'required' => self::moduleIdsToStrings($this->required),
            'optional' => self::moduleIdsToStrings($this->optional),
            'disabled' => self::moduleIdsToStrings($this->disabled),
            'featureBundles' => $this->featureBundles,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<ModuleId>
     */
    private static function normalizeModuleIdSet(array $moduleIds, string $field): array
    {
        $set = [];

        foreach ($moduleIds as $moduleId) {
            if (!$moduleId instanceof ModuleId) {
                throw new \InvalidArgumentException('mode-preset-' . $field . '-module-id-invalid');
            }

            $set[$moduleId->value()] = $moduleId;
        }

        \ksort($set, \SORT_STRING);

        return \array_values($set);
    }

    /**
     * @param list<ModuleId> $first
     * @param list<ModuleId> $second
     */
    private static function assertDisjoint(array $first, array $second, string $reason): void
    {
        $seen = [];

        foreach ($first as $moduleId) {
            $seen[$moduleId->value()] = true;
        }

        foreach ($second as $moduleId) {
            if (isset($seen[$moduleId->value()])) {
                throw new \InvalidArgumentException($reason);
            }
        }
    }

    /**
     * @param list<ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function normalizeJsonLikeMap(array $value, string $field): array
    {
        if ($value === []) {
            return [];
        }

        if (\array_is_list($value)) {
            throw new \InvalidArgumentException('mode-preset-' . $field . '-map-required');
        }

        return self::normalizeJsonLikeArray($value, 0, $field);
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private static function normalizeJsonLikeArray(array $value, int $depth, string $field): array
    {
        if ($depth > self::MAX_JSON_DEPTH) {
            throw new \InvalidArgumentException('mode-preset-' . $field . '-depth-exceeded');
        }

        if (\count($value) > self::MAX_JSON_MAP_KEYS) {
            throw new \InvalidArgumentException('mode-preset-' . $field . '-too-many-keys');
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalizeJsonLikeValue($item, $depth + 1, $field);
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || $key === '') {
                throw new \InvalidArgumentException('mode-preset-' . $field . '-map-key-invalid');
            }

            $normalized[$key] = self::normalizeJsonLikeValue($item, $depth + 1, $field);
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function normalizeJsonLikeValue(mixed $value, int $depth, string $field): mixed
    {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_string($value)) {
            if (!self::isSafeJsonLikeString($value)) {
                throw new \InvalidArgumentException('mode-preset-' . $field . '-string-invalid');
            }

            return $value;
        }

        if (\is_array($value)) {
            return self::normalizeJsonLikeArray($value, $depth, $field);
        }

        throw new \InvalidArgumentException('mode-preset-' . $field . '-value-invalid');
    }

    private static function isSafePresetName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (!self::isAsciiLowerAlpha($name[0])) {
            return false;
        }

        return \strspn($name, self::SAFE_PRESET_NAME_CHARS) === \strlen($name);
    }

    private static function isSafeDescription(string $description): bool
    {
        if ($description === '') {
            return false;
        }

        if (\strlen($description) > self::MAX_SAFE_DESCRIPTION_BYTES) {
            return false;
        }

        return self::hasNoControlCharacters($description);
    }

    private static function isSafeJsonLikeString(string $value): bool
    {
        return self::hasNoControlCharacters($value);
    }

    private static function hasNoControlCharacters(string $value): bool
    {
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $ord = \ord($value[$i]);

            if ($ord < 32 || $ord === 127) {
                return false;
            }
        }

        return true;
    }

    private static function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }
}
