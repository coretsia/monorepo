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

use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Module\ModuleResolution;

/**
 * Resolves the ordered container definition provider plan from one module
 * resolution snapshot.
 *
 * The resolver never instantiates providers. It validates declared provider
 * classes and returns class-name entries in topological module order followed
 * by module-declared provider order.
 *
 * @internal Kernel compile-time provider planning service.
 */
final readonly class ContainerProviderPlanResolver
{
    private const int MAX_PROVIDER_CLASS_BYTES = 512;

    public function resolve(
        ModuleResolution $resolution,
    ): ContainerProviderPlan {
        $entries = [];
        $seenProviders = [];
        $manifest = $resolution->manifest();

        foreach (
            $resolution->plan()->topologicalOrder() as $moduleOrder => $moduleId
        ) {
            $descriptor = $manifest->get($moduleId->value());

            if (!$descriptor instanceof ModuleDescriptor) {
                throw self::providerInvalid();
            }

            foreach (
                self::providerClasses($descriptor) as $providerOrder => $providerClass
            ) {
                self::assertDefinitionProviderClass($providerClass);

                $providerKey = \strtolower($providerClass);

                if (isset($seenProviders[$providerKey])) {
                    throw self::providerInvalid();
                }

                $seenProviders[$providerKey] = true;
                $entries[] = [
                    'moduleId' => $moduleId->value(),
                    'providerClass' => $providerClass,
                    'moduleOrder' => $moduleOrder,
                    'providerOrder' => $providerOrder,
                ];
            }
        }

        try {
            return new ContainerProviderPlan($entries);
        } catch (\InvalidArgumentException $exception) {
            throw self::providerInvalid($exception);
        }
    }

    /**
     * @return list<non-empty-string>
     */
    private static function providerClasses(
        ModuleDescriptor $descriptor,
    ): array {
        $providers = $descriptor->metadata()['providers'] ?? [];

        if (!\is_array($providers) || !\array_is_list($providers)) {
            throw self::providerInvalid();
        }

        $normalized = [];

        foreach ($providers as $providerClass) {
            if (
                !\is_string($providerClass)
                || !self::isProviderClassName($providerClass)
            ) {
                throw self::providerInvalid();
            }

            $normalized[] = $providerClass;
        }

        /** @var list<non-empty-string> $normalized */
        return $normalized;
    }

    private static function assertDefinitionProviderClass(
        string $providerClass,
    ): void {
        try {
            $exists = \class_exists($providerClass);
        } catch (\Throwable $exception) {
            throw self::providerInvalid($exception);
        }

        if (!$exists) {
            throw self::providerInvalid();
        }

        $reflection = new \ReflectionClass($providerClass);

        if (
            $reflection->getName() !== $providerClass
            || !$reflection->isInstantiable()
            || !$reflection->implementsInterface(
                ContainerDefinitionProviderInterface::class,
            )
        ) {
            throw self::providerInvalid();
        }
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

    private static function providerInvalid(
        ?\Throwable $previous = null,
    ): ContainerDefinitionInvalidException {
        return ContainerDefinitionInvalidException::withReason(
            reason: ContainerDefinitionInvalidException::REASON_PROVIDER_INVALID,
            previous: $previous,
        );
    }
}
