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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Kernel\Config\ConfigValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KernelRuntimeDriverConfigRulesContractTest extends TestCase
{
    /**
     * @param non-empty-string $path
     */
    #[DataProvider('nonBoolRuntimeDriverFlagProvider')]
    public function testKernelRuntimeDriverEnabledFlagsRejectNonBoolValues(
        string $path,
        mixed $value,
    ): void {
        $config = self::kernelGlobalConfig();

        self::setNestedValue($config['kernel'], \explode('.', $path), $value);

        $result = self::validateKernelConfig($config);

        self::assertTrue(
            $result->isFailure(),
            'Config validation must reject non-bool kernel runtime driver enabled values.',
        );

        self::assertHasViolation(
            result: $result,
            path: $path,
            reason: 'type',
            expected: 'bool',
        );
    }

    public function testKernelRuntimeRulesRejectUnknownRuntimeDriverKeys(): void
    {
        $config = self::kernelGlobalConfig();

        $config['kernel']['runtime']['reactphp'] = [
            'enabled' => true,
        ];

        $result = self::validateKernelConfig($config);

        self::assertTrue(
            $result->isFailure(),
            'Config validation must reject unknown kernel.runtime.* keys.',
        );

        self::assertHasViolationWithPathPrefix(
            result: $result,
            pathPrefix: 'runtime.',
            reason: 'unknown-key',
        );
    }

    public function testKernelRuntimeRulesRejectUnknownNestedRuntimeDriverKeys(): void
    {
        $config = self::kernelGlobalConfig();

        $config['kernel']['runtime']['roadrunner']['adapter'] = 'psr15';

        $result = self::validateKernelConfig($config);

        self::assertTrue(
            $result->isFailure(),
            'Config validation must reject unknown nested kernel.runtime.<driver>.* keys.',
        );

        self::assertHasViolationWithPathPrefix(
            result: $result,
            pathPrefix: 'runtime.roadrunner.',
            reason: 'unknown-key',
        );
    }

    public function testWorkerRootIsNotIntroducedIntoKernelConfigDefaultsOrRules(): void
    {
        $config = self::kernelConfig();
        $rules = self::kernelRules();

        self::assertArrayNotHasKey(
            'worker',
            $config,
            'core/kernel config defaults must not introduce worker root.',
        );

        self::assertSame(
            [],
            self::pathsForArrayKey($config, 'worker'),
            'core/kernel config defaults must not define worker.* keys.',
        );

        self::assertSame(
            'kernel',
            $rules['configRoot'] ?? null,
            'core/kernel config rules must validate only the kernel root.',
        );

        self::assertArrayNotHasKey(
            'worker',
            $rules['keys'] ?? [],
            'core/kernel config rules must not introduce worker root rules.',
        );

        self::assertSame(
            [],
            self::pathsForArrayKey($rules, 'worker'),
            'core/kernel config rules must not define worker.* rules at any depth.',
        );
    }

    /**
     * @return iterable<string, array{0:non-empty-string,1:mixed}>
     */
    public static function nonBoolRuntimeDriverFlagProvider(): iterable
    {
        foreach (
            [
                'kernel.runtime.frankenphp.enabled' => 'runtime.frankenphp.enabled',
                'kernel.runtime.swoole.enabled' => 'runtime.swoole.enabled',
                'kernel.runtime.roadrunner.enabled' => 'runtime.roadrunner.enabled',
            ] as $case => $path
        ) {
            yield $case . ': string true' => [
                $path,
                'true',
            ];

            yield $case . ': int one' => [
                $path,
                1,
            ];

            yield $case . ': null' => [
                $path,
                null,
            ];

            yield $case . ': list' => [
                $path,
                [true],
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelGlobalConfig(): array
    {
        return [
            'kernel' => self::kernelConfig(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelConfigPath();

        self::assertIsArray($config);

        /** @var array<string,mixed> $config */
        return $config;
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelRules(): array
    {
        $rules = require self::kernelRulesPath();

        self::assertIsArray($rules);

        /** @var array<string,mixed> $rules */
        return $rules;
    }

    private static function kernelRuleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('kernel', self::kernelRules());
    }

    /**
     * @param array<string,mixed> $config
     */
    private static function validateKernelConfig(array $config): ConfigValidationResult
    {
        return new ConfigValidator()->validate($config, [self::kernelRuleset()]);
    }

    /**
     * @param array<string,mixed> $target
     * @param list<string> $path
     */
    private static function setNestedValue(array &$target, array $path, mixed $value): void
    {
        self::assertNotSame([], $path);

        $cursor = &$target;
        $last = \array_pop($path);

        self::assertIsString($last);

        foreach ($path as $segment) {
            if (!isset($cursor[$segment]) || !\is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor[$last] = $value;
    }

    private static function assertHasViolation(
        ConfigValidationResult $result,
        string $path,
        string $reason,
        ?string $expected = null,
    ): void {
        foreach ($result->violations() as $violation) {
            if ($violation->path() !== $path) {
                continue;
            }

            if ($violation->reason() !== $reason) {
                continue;
            }

            if ($expected !== null && $violation->expected() !== $expected) {
                continue;
            }

            return;
        }

        self::fail(
            \sprintf(
                'Expected config validation violation path="%s", reason="%s". Actual violations: %s',
                $path,
                $reason,
                self::formatViolations($result->violations()),
            ),
        );
    }

    private static function assertHasViolationWithPathPrefix(
        ConfigValidationResult $result,
        string $pathPrefix,
        string $reason,
    ): void {
        foreach ($result->violations() as $violation) {
            if (!\str_starts_with($violation->path(), $pathPrefix)) {
                continue;
            }

            if ($violation->reason() !== $reason) {
                continue;
            }

            return;
        }

        self::fail(
            \sprintf(
                'Expected config validation violation with path prefix "%s" and reason "%s". Actual violations: %s',
                $pathPrefix,
                $reason,
                self::formatViolations($result->violations()),
            ),
        );
    }

    /**
     * @param list<ConfigValidationViolation> $violations
     */
    private static function formatViolations(array $violations): string
    {
        $formatted = [];

        foreach ($violations as $violation) {
            $formatted[] = \sprintf(
                '%s:%s:%s:%s',
                $violation->root(),
                $violation->path(),
                $violation->reason(),
                $violation->expected(),
            );
        }

        \usort(
            $formatted,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \implode(', ', $formatted);
    }

    /**
     * @param array<array-key,mixed> $value
     * @param list<string> $path
     *
     * @return list<string>
     */
    private static function pathsForArrayKey(array $value, string $needle, array $path = []): array
    {
        $matches = [];

        foreach ($value as $key => $item) {
            $keyAsString = (string)$key;
            $currentPath = [...$path, $keyAsString];

            if ($key === $needle) {
                $matches[] = \implode('.', $currentPath);
            }

            if (\is_array($item)) {
                $matches = [
                    ...$matches,
                    ...self::pathsForArrayKey($item, $needle, $currentPath),
                ];
            }
        }

        \usort(
            $matches,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $matches;
    }

    private static function kernelConfigPath(): string
    {
        return \dirname(__DIR__, 2) . '/config/kernel.php';
    }

    private static function kernelRulesPath(): string
    {
        return \dirname(__DIR__, 2) . '/config/rules.php';
    }
}
