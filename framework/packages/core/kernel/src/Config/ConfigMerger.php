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

namespace Coretsia\Kernel\Config;

use Coretsia\Contracts\Config\ConfigDirective;
use Coretsia\Contracts\Config\MergeStrategyInterface;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;

/**
 * Deterministic Kernel config merge strategy.
 *
 * ConfigMerger owns only the deterministic two-node merge operation:
 *
 * - lower rank source is passed as $base;
 * - higher rank source is passed as $patch;
 * - higher rank scalar/list values replace lower rank values;
 * - maps are merged recursively;
 * - map keys are sorted deterministically by byte-order string comparison;
 * - lists preserve caller-supplied order unless a directive explicitly changes
 *   the list;
 * - normalized directives are applied at merge time, when the previous/base
 *   value is known.
 *
 * Multi-source precedence is intentionally not encoded here. ConfigKernel owns
 * the active Phase B rank order and folds sources through this strategy in that
 * explicit order.
 *
 * This class MUST NOT:
 *
 * - discover config files;
 * - load package defaults;
 * - read skeleton/application config;
 * - read environment variables;
 * - build env overlays;
 * - validate config rulesets;
 * - produce explain traces;
 * - invent source precedence.
 *
 * Env overlays are prepared by EnvironmentOverlayLoader and are merged here
 * only as ordinary higher-rank config patches.
 *
 * @internal
 */
final readonly class ConfigMerger implements MergeStrategyInterface
{
    public function __construct(
        private DirectiveProcessor $directiveProcessor,
    ) {
    }

    /**
     * Merges a lower-rank base node with a higher-rank patch node.
     *
     * The caller is responsible for folding sources in the canonical Phase B
     * precedence order. This method performs no source ranking by itself.
     */
    public function merge(mixed $base, mixed $patch): mixed
    {
        return $this->mergeNode($base, $patch);
    }

    private function mergeNode(mixed $base, mixed $patch): mixed
    {
        if (!\is_array($patch)) {
            return $patch;
        }

        $directive = $this->directiveProcessor->directiveOf($patch);

        if ($directive !== null) {
            return $this->applyDirective(
                base: $base,
                directive: $directive,
                value: $this->directiveProcessor->directiveValue($patch),
            );
        }

        if (\array_is_list($patch)) {
            return $this->replaceList($patch);
        }

        $baseMap = self::isMapLike($base)
            ? $base
            : [];

        return $this->mergeMap(
            base: $baseMap,
            patch: $patch,
        );
    }

    /**
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> $patch
     *
     * @return array<array-key, mixed>
     */
    private function mergeMap(array $base, array $patch): array
    {
        $merged = [];

        foreach ($base as $key => $value) {
            $merged[$key] = self::normalizeExistingNode($value);
        }

        foreach ($patch as $key => $value) {
            $merged[$key] = $this->mergeNode(
                base: \array_key_exists($key, $merged) ? $merged[$key] : null,
                patch: $value,
            );
        }

        \ksort($merged, \SORT_STRING);

        return $merged;
    }

    /**
     * Replaces the lower-rank node with a higher-rank list.
     *
     * List order is preserved. Nested array values are normalized
     * deterministically, and any already-normalized nested directive nodes are
     * applied with a missing/null base.
     *
     * @param list<mixed> $patch
     *
     * @return list<mixed>
     */
    private function replaceList(array $patch): array
    {
        $out = [];

        foreach ($patch as $item) {
            if (\is_array($item)) {
                $out[] = $this->mergeNode(
                    base: null,
                    patch: $item,
                );

                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    private function applyDirective(
        mixed $base,
        ConfigDirective $directive,
        mixed $value,
    ): mixed {
        return match ($directive) {
            ConfigDirective::Append => $this->applyAppend($base, $value),
            ConfigDirective::Prepend => $this->applyPrepend($base, $value),
            ConfigDirective::Remove => $this->applyRemove($base, $value),
            ConfigDirective::Merge => $this->applyMerge($base, $value),
            ConfigDirective::Replace => $this->applyReplace($value),
        };
    }

    private function applyAppend(mixed $base, mixed $value): array
    {
        if ($base !== null && !self::isList($base)) {
            throw ConfigDirectiveTypeMismatchException::listDirectiveBaseMustBeList(
                directive: ConfigDirective::Append,
            );
        }

        $baseList = self::isList($base)
            ? self::normalizeExistingList($base)
            : [];

        if (!self::isList($value)) {
            self::invalidNormalizedDirectivePayload();
        }

        $appendList = $this->replaceList($value);

        return [
            ...$baseList,
            ...$appendList,
        ];
    }

    private function applyPrepend(mixed $base, mixed $value): array
    {
        if ($base !== null && !self::isList($base)) {
            throw ConfigDirectiveTypeMismatchException::listDirectiveBaseMustBeList(
                directive: ConfigDirective::Prepend,
            );
        }

        $baseList = self::isList($base)
            ? self::normalizeExistingList($base)
            : [];

        if (!self::isList($value)) {
            self::invalidNormalizedDirectivePayload();
        }

        $prependList = $this->replaceList($value);

        return [
            ...$prependList,
            ...$baseList,
        ];
    }

    private function applyRemove(mixed $base, mixed $value): array
    {
        if ($base === null) {
            return [];
        }

        if (!self::isList($base)) {
            throw ConfigDirectiveTypeMismatchException::listDirectiveBaseMustBeList(
                directive: ConfigDirective::Remove,
            );
        }

        $baseList = self::normalizeExistingList($base);

        if (!self::isList($value)) {
            self::invalidNormalizedDirectivePayload();
        }

        $removeList = $this->replaceList($value);

        if ($removeList === []) {
            return $baseList;
        }

        $out = [];

        foreach ($baseList as $item) {
            if (self::containsStrict($removeList, $item)) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    }

    private function applyMerge(mixed $base, mixed $value): array
    {
        if (!\is_array($value)) {
            self::invalidNormalizedDirectivePayload();
        }

        if ($value !== [] && \array_is_list($value)) {
            self::invalidNormalizedDirectivePayload();
        }

        if ($base !== null && !self::isMapLike($base)) {
            throw ConfigDirectiveTypeMismatchException::mergeDirectiveBaseMustBeMap();
        }

        $baseMap = self::isMapLike($base)
            ? $base
            : [];

        return $this->mergeMap(
            base: $baseMap,
            patch: $value,
        );
    }

    private function applyReplace(mixed $value): mixed
    {
        return $this->mergeNode(
            base: null,
            patch: $value,
        );
    }

    private static function invalidNormalizedDirectivePayload(): never
    {
        throw new \InvalidArgumentException('config-merger-normalized-directive-payload-invalid');
    }

    private static function isList(mixed $value): bool
    {
        return \is_array($value) && \array_is_list($value);
    }

    private static function isMapLike(mixed $value): bool
    {
        return \is_array($value) && ($value === [] || !\array_is_list($value));
    }

    private static function containsStrict(array $values, mixed $candidate): bool
    {
        foreach ($values as $value) {
            if ($value === $candidate) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeExistingNode(mixed $value): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if (\array_is_list($value)) {
            return self::normalizeExistingList($value);
        }

        return self::normalizeExistingMap($value);
    }

    /**
     * @param list<mixed> $value
     *
     * @return list<mixed>
     */
    private static function normalizeExistingList(array $value): array
    {
        $out = [];

        foreach ($value as $item) {
            $out[] = self::normalizeExistingNode($item);
        }

        return $out;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @return array<array-key, mixed>
     */
    private static function normalizeExistingMap(array $value): array
    {
        $out = [];

        foreach ($value as $key => $item) {
            $out[$key] = self::normalizeExistingNode($item);
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }
}
