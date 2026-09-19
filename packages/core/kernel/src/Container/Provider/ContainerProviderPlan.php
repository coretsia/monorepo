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

namespace Coretsia\Kernel\Container\Provider;

use Coretsia\Contracts\Module\ModuleId;

/**
 * Immutable ordered compile-time container provider plan.
 *
 * Entries remain in ModulePlan topological module order and in each module's
 * declared provider order. The plan stores provider class names only and never
 * provider instances.
 *
 * @internal Kernel compile-time provider planning value.
 */
final readonly class ContainerProviderPlan
{
    private const int MAX_PROVIDER_CLASS_BYTES = 512;

    /**
     * @var list<array{
     *     moduleId: non-empty-string,
     *     providerClass: non-empty-string,
     *     moduleOrder: int<0, max>,
     *     providerOrder: int<0, max>
     * }>
     */
    private array $entries;

    /**
     * @param list<array{
     *     moduleId: string,
     *     providerClass: string,
     *     moduleOrder: int,
     *     providerOrder: int
     * }> $entries
     */
    public function __construct(array $entries)
    {
        if (!\array_is_list($entries)) {
            throw new \InvalidArgumentException('container-provider-plan-entries-invalid');
        }

        $normalized = [];
        $providerClasses = [];
        $previousModuleOrder = -1;
        $previousProviderOrder = -1;
        $previousModuleId = null;

        foreach ($entries as $entry) {
            if (\array_keys($entry) !== [
                    'moduleId',
                    'providerClass',
                    'moduleOrder',
                    'providerOrder',
                ]) {
                throw new \InvalidArgumentException('container-provider-plan-entry-invalid');
            }

            $moduleId = $entry['moduleId'];
            $providerClass = $entry['providerClass'];
            $moduleOrder = $entry['moduleOrder'];
            $providerOrder = $entry['providerOrder'];

            if (!\is_string($moduleId)) {
                throw new \InvalidArgumentException('container-provider-plan-module-id-invalid');
            }

            try {
                $moduleId = ModuleId::fromString($moduleId)->value();
            } catch (\InvalidArgumentException $exception) {
                throw new \InvalidArgumentException('container-provider-plan-module-id-invalid', 0, $exception);
            }

            if (
                !\is_string($providerClass)
                || !self::isProviderClassName($providerClass)
            ) {
                throw new \InvalidArgumentException('container-provider-plan-provider-class-invalid');
            }

            if (!\is_int($moduleOrder) || $moduleOrder < 0) {
                throw new \InvalidArgumentException('container-provider-plan-module-order-invalid');
            }

            if (!\is_int($providerOrder) || $providerOrder < 0) {
                throw new \InvalidArgumentException('container-provider-plan-provider-order-invalid');
            }

            if ($moduleOrder < $previousModuleOrder) {
                throw new \InvalidArgumentException('container-provider-plan-order-invalid');
            }

            if ($moduleOrder === $previousModuleOrder) {
                if (
                    $moduleId !== $previousModuleId
                    || $providerOrder !== $previousProviderOrder + 1
                ) {
                    throw new \InvalidArgumentException('container-provider-plan-order-invalid');
                }
            } elseif ($providerOrder !== 0) {
                throw new \InvalidArgumentException('container-provider-plan-order-invalid');
            }

            $providerKey = \strtolower($providerClass);

            if (isset($providerClasses[$providerKey])) {
                throw new \InvalidArgumentException('container-provider-plan-provider-duplicate');
            }

            $providerClasses[$providerKey] = true;
            $normalized[] = [
                'moduleId' => $moduleId,
                'providerClass' => $providerClass,
                'moduleOrder' => $moduleOrder,
                'providerOrder' => $providerOrder,
            ];

            $previousModuleId = $moduleId;
            $previousModuleOrder = $moduleOrder;
            $previousProviderOrder = $providerOrder;
        }

        $this->entries = $normalized;
    }

    /**
     * @return list<array{
     *     moduleId: non-empty-string,
     *     providerClass: non-empty-string,
     *     moduleOrder: int<0, max>,
     *     providerOrder: int<0, max>
     * }>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<non-empty-string>
     */
    public function providerClasses(): array
    {
        return \array_map(
            static fn (array $entry): string => $entry['providerClass'],
            $this->entries,
        );
    }

    private static function isProviderClassName(
        string $providerClass,
    ): bool {
        if (
            $providerClass === ''
            || \strlen($providerClass) > self::MAX_PROVIDER_CLASS_BYTES
        ) {
            return false;
        }

        return \preg_match(
            '/\A(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+'
                . '[A-Za-z_][A-Za-z0-9_]*\z/D',
            $providerClass,
        ) === 1;
    }
}
