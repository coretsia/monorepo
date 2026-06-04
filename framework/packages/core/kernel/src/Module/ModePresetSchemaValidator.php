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
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;

/**
 * Validates raw mode preset PHP array payloads.
 *
 * This validator owns user/config-facing preset schema validation.
 *
 * It accepts only the canonical preset array shape and returns an immutable
 * Kernel-owned ModePreset value object.
 *
 * The validator intentionally does not know where the preset payload came from.
 * It must not store or expose source file paths, skeleton root, defaults path,
 * overrides path, filesystem layout, raw preset payloads, services, closures,
 * resources, or runtime wiring objects.
 *
 * @internal
 */
final class ModePresetSchemaValidator
{
    private const int MAX_PRESET_NAME_BYTES = 64;
    private const int MAX_DESCRIPTION_BYTES = 512;
    private const int MAX_JSON_DEPTH = 16;
    private const int MAX_JSON_MAP_KEYS = 256;
    private const int MAX_JSON_STRING_BYTES = 1024;

    private const string SAFE_PRESET_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789-';

    /**
     * @var array<string, true>
     */
    private const array REQUIRED_TOP_LEVEL_KEYS = [
        'description' => true,
        'disabled' => true,
        'featureBundles' => true,
        'metadata' => true,
        'name' => true,
        'optional' => true,
        'required' => true,
        'schemaVersion' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array ROOT_WRAPPER_KEYS = [
        'kernel' => true,
        'mode' => true,
        'modes' => true,
    ];

    public function validate(string $requestedPresetName, mixed $payload): ModePreset
    {
        $requestedPresetName = $this->validateRequestedPresetName($requestedPresetName);

        if (!\is_array($payload)) {
            throw ModePresetInvalidException::forPreset(
                $requestedPresetName,
                ModePresetInvalidException::REASON_PRESET_RETURN_TYPE_INVALID,
            );
        }

        if ($payload !== [] && \array_is_list($payload)) {
            throw ModePresetInvalidException::forPreset(
                $requestedPresetName,
                ModePresetInvalidException::REASON_PRESET_RETURN_TYPE_INVALID,
            );
        }

        $this->assertNoRootWrapper($requestedPresetName, $payload);
        $this->assertTopLevelKeys($requestedPresetName, $payload);

        $schemaVersion = $this->validateSchemaVersion($requestedPresetName, $payload['schemaVersion']);
        $name = $this->validatePresetName($requestedPresetName, $payload['name']);

        if ($name !== $requestedPresetName) {
            throw ModePresetInvalidException::forPreset(
                $requestedPresetName,
                ModePresetInvalidException::REASON_NAME_MISMATCH,
            );
        }

        $description = $this->validateDescription($requestedPresetName, $payload['description']);

        $required = $this->normalizeModuleIdList($requestedPresetName, $payload['required']);
        $optional = $this->normalizeModuleIdList($requestedPresetName, $payload['optional']);
        $disabled = $this->normalizeModuleIdList($requestedPresetName, $payload['disabled']);

        $this->assertDisjoint($requestedPresetName, $required, $optional);
        $this->assertDisjoint($requestedPresetName, $required, $disabled);
        $this->assertDisjoint($requestedPresetName, $optional, $disabled);

        $featureBundles = $this->normalizeJsonLikeMap(
            presetName: $requestedPresetName,
            value: $payload['featureBundles'],
            reason: ModePresetInvalidException::REASON_FEATURE_BUNDLES_INVALID,
        );

        $metadata = $this->normalizeJsonLikeMap(
            presetName: $requestedPresetName,
            value: $payload['metadata'],
            reason: ModePresetInvalidException::REASON_METADATA_INVALID,
        );

        try {
            return new ModePreset(
                schemaVersion: $schemaVersion,
                name: $name,
                description: $description,
                required: $required,
                optional: $optional,
                disabled: $disabled,
                featureBundles: $featureBundles,
                metadata: $metadata,
            );
        } catch (\InvalidArgumentException) {
            throw ModePresetInvalidException::forPreset(
                $requestedPresetName,
                ModePresetInvalidException::REASON_PRESET_INVALID,
            );
        }
    }

    private function validateRequestedPresetName(string $requestedPresetName): string
    {
        if (!$this->isSafePresetName($requestedPresetName)) {
            /*
             * This is an internal call-site contract violation. The loader owns
             * input preset-name validation before invoking this validator.
             */
            throw new \InvalidArgumentException('mode-preset-validator-requested-preset-name-invalid');
        }

        return $requestedPresetName;
    }

    /**
     * @param array<mixed> $payload
     */
    private function assertNoRootWrapper(string $presetName, array $payload): void
    {
        foreach (self::ROOT_WRAPPER_KEYS as $key => $_true) {
            if (\array_key_exists($key, $payload)) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_PRESET_ROOT_WRAPPER_FORBIDDEN,
                );
            }
        }
    }

    /**
     * @param array<mixed> $payload
     */
    private function assertTopLevelKeys(string $presetName, array $payload): void
    {
        foreach (self::REQUIRED_TOP_LEVEL_KEYS as $key => $_true) {
            if (!\array_key_exists($key, $payload)) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_PRESET_INVALID,
                );
            }
        }

        foreach ($payload as $key => $_value) {
            if (!\is_string($key) || !isset(self::REQUIRED_TOP_LEVEL_KEYS[$key])) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_PRESET_INVALID,
                );
            }
        }
    }

    private function validateSchemaVersion(string $presetName, mixed $value): int
    {
        if ($value !== ModePresetInterface::SCHEMA_VERSION) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID,
            );
        }

        return ModePresetInterface::SCHEMA_VERSION;
    }

    private function validatePresetName(string $presetName, mixed $value): string
    {
        if (!\is_string($value) || !$this->isSafePresetName($value)) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_NAME_INVALID,
            );
        }

        return $value;
    }

    private function validateDescription(string $presetName, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value)) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_DESCRIPTION_INVALID,
            );
        }

        if ($value === '' || \strlen($value) > self::MAX_DESCRIPTION_BYTES) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_DESCRIPTION_INVALID,
            );
        }

        if (!$this->hasNoControlCharacters($value) || $this->isPathLikeString($value)) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_DESCRIPTION_INVALID,
            );
        }

        return $value;
    }

    /**
     * @return list<ModuleId>
     */
    private function normalizeModuleIdList(string $presetName, mixed $value): array
    {
        if (!\is_array($value) || !\array_is_list($value)) {
            throw ModePresetInvalidException::forPreset(
                $presetName,
                ModePresetInvalidException::REASON_LIST_INVALID,
            );
        }

        $set = [];

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_MODULE_ID_INVALID,
                );
            }

            try {
                $moduleId = ModuleId::fromString($item);
            } catch (\InvalidArgumentException) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_MODULE_ID_INVALID,
                );
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
    private function assertDisjoint(string $presetName, array $first, array $second): void
    {
        $seen = [];

        foreach ($first as $moduleId) {
            $seen[$moduleId->value()] = true;
        }

        foreach ($second as $moduleId) {
            if (isset($seen[$moduleId->value()])) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_SETS_OVERLAP,
                );
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeJsonLikeMap(
        string $presetName,
        mixed $value,
        string $reason,
    ): array {
        if (!\is_array($value)) {
            throw ModePresetInvalidException::forPreset($presetName, $reason);
        }

        if ($value !== [] && \array_is_list($value)) {
            throw ModePresetInvalidException::forPreset($presetName, $reason);
        }

        return $this->normalizeJsonLikeArray(
            presetName: $presetName,
            value: $value,
            depth: 0,
            reason: $reason,
        );
    }

    /**
     * @param array<mixed> $value
     *
     * @return array<mixed>
     */
    private function normalizeJsonLikeArray(
        string $presetName,
        array $value,
        int $depth,
        string $reason,
    ): array {
        if ($depth > self::MAX_JSON_DEPTH) {
            throw ModePresetInvalidException::forPreset($presetName, $reason);
        }

        if (\count($value) > self::MAX_JSON_MAP_KEYS) {
            throw ModePresetInvalidException::forPreset($presetName, $reason);
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = $this->normalizeJsonLikeValue(
                    presetName: $presetName,
                    value: $item,
                    depth: $depth + 1,
                    reason: $reason,
                );
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key) || $key === '') {
                throw ModePresetInvalidException::forPreset($presetName, $reason);
            }

            if (!$this->hasNoControlCharacters($key)) {
                throw ModePresetInvalidException::forPreset($presetName, $reason);
            }

            if ($this->isPathLikeString($key)) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_PATH_LIKE_VALUE_FORBIDDEN,
                );
            }

            $normalized[$key] = $this->normalizeJsonLikeValue(
                presetName: $presetName,
                value: $item,
                depth: $depth + 1,
                reason: $reason,
            );
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private function normalizeJsonLikeValue(
        string $presetName,
        mixed $value,
        int $depth,
        string $reason,
    ): mixed {
        if ($value === null || \is_bool($value) || \is_int($value)) {
            return $value;
        }

        if (\is_float($value)) {
            throw ModePresetInvalidException::forPreset($presetName, $reason);
        }

        if (\is_string($value)) {
            if (!$this->hasNoControlCharacters($value)) {
                throw ModePresetInvalidException::forPreset($presetName, $reason);
            }

            if (\strlen($value) > self::MAX_JSON_STRING_BYTES) {
                throw ModePresetInvalidException::forPreset($presetName, $reason);
            }

            if ($this->isPathLikeString($value)) {
                throw ModePresetInvalidException::forPreset(
                    $presetName,
                    ModePresetInvalidException::REASON_PATH_LIKE_VALUE_FORBIDDEN,
                );
            }

            return $value;
        }

        if (\is_array($value)) {
            return $this->normalizeJsonLikeArray(
                presetName: $presetName,
                value: $value,
                depth: $depth,
                reason: $reason,
            );
        }

        /*
         * Objects, closures, resources, service instances, filesystem handles,
         * and runtime wiring objects are rejected here.
         */
        throw ModePresetInvalidException::forPreset($presetName, $reason);
    }

    private function isSafePresetName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        if (\strlen($name) > self::MAX_PRESET_NAME_BYTES) {
            return false;
        }

        if (!$this->isAsciiLowerAlpha($name[0])) {
            return false;
        }

        if (\str_contains($name, '..')) {
            return false;
        }

        return \strspn($name, self::SAFE_PRESET_NAME_CHARS) === \strlen($name);
    }

    private function isPathLikeString(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (\str_contains($value, "\0")) {
            return true;
        }

        if ($value[0] === '/' || $value[0] === '\\') {
            return true;
        }

        if (\str_contains($value, '\\')) {
            return true;
        }

        if (\str_contains($value, '://')) {
            return true;
        }

        if (\strlen($value) >= 2 && $value[1] === ':' && $this->isAsciiAlpha($value[0])) {
            return true;
        }

        if (
            $value === '.'
            || $value === '..'
            || \str_starts_with($value, './')
            || \str_starts_with($value, '../')
            || \str_ends_with($value, '/.')
            || \str_ends_with($value, '/..')
        ) {
            return true;
        }

        if (\str_contains($value, '/./') || \str_contains($value, '/../')) {
            return true;
        }

        return false;
    }

    private function hasNoControlCharacters(string $value): bool
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

    private function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }

    private function isAsciiAlpha(string $char): bool
    {
        return ($char >= 'a' && $char <= 'z')
            || ($char >= 'A' && $char <= 'Z');
    }
}
