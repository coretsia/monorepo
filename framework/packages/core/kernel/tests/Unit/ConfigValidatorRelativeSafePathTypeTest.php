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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigValidatorRelativeSafePathTypeTest extends TestCase
{
    #[DataProvider('acceptedRelativeSafePathProvider')]
    public function testAcceptsRelativeSafePaths(string $path): void
    {
        $config = self::kernelGlobalConfig();

        $config['kernel']['modes']['defaults_path'] = $path;
        $config['kernel']['modes']['overrides_path'] = $path;

        $result = new ConfigValidator()->validate($config, [self::kernelRuleset()]);

        self::assertTrue($result->isSuccess());
        self::assertSame([], $result->violations());
    }

    #[DataProvider('rejectedRelativeSafePathProvider')]
    public function testRejectsUnsafeRelativeSafePaths(string $path): void
    {
        $config = self::kernelGlobalConfig();

        $config['kernel']['modes']['defaults_path'] = $path;

        $result = new ConfigValidator()->validate($config, [self::kernelRuleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());

        $violation = $result->violations()[0];

        self::assertSame('kernel', $violation->root());
        self::assertSame('modes.defaults_path', $violation->path());
        self::assertSame('relative-safe-path', $violation->reason());
        self::assertSame('relative-safe-path', $violation->expected());
        self::assertSame('string', $violation->actualType());

        $diagnostics = \json_encode($result->toArray(), \JSON_THROW_ON_ERROR);

        if ($path !== '') {
            self::assertStringNotContainsString($path, $diagnostics);
        }
    }

    public function testRejectsNulByteWithoutLeakingRawPathValue(): void
    {
        $path = "config/modes\0secret";

        $config = self::kernelGlobalConfig();

        $config['kernel']['modes']['defaults_path'] = $path;

        $result = new ConfigValidator()->validate($config, [self::kernelRuleset()]);

        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());

        $violation = $result->violations()[0];

        self::assertSame('kernel', $violation->root());
        self::assertSame('modes.defaults_path', $violation->path());
        self::assertSame('relative-safe-path', $violation->reason());
        self::assertSame('relative-safe-path', $violation->expected());
        self::assertSame('string', $violation->actualType());

        $diagnostics = \json_encode($result->toArray(), \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('config/modes', $diagnostics);
        self::assertStringNotContainsString('secret', $diagnostics);
        self::assertStringNotContainsString('\u0000', $diagnostics);
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function acceptedRelativeSafePathProvider(): iterable
    {
        yield 'resources-modes' => [
            'resources/modes',
        ];

        yield 'config-modes' => [
            'config/modes',
        ];
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function rejectedRelativeSafePathProvider(): iterable
    {
        yield 'empty-string' => [
            '',
        ];

        yield 'absolute-unix-path' => [
            '/var/app/config/modes',
        ];

        yield 'absolute-windows-drive-letter-path' => [
            'C:\\app\\config\\modes',
        ];

        yield 'path-traversal-segment' => [
            'config/../secrets',
        ];

        yield 'stream-wrapper' => [
            'php://filter/resource=config/modes',
        ];

        yield 'backslash-path' => [
            'config\\modes',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelGlobalConfig(): array
    {
        $config = require self::kernelConfigPath();

        self::assertIsArray($config);

        /** @var array<string,mixed> $config */
        return [
            'kernel' => $config,
        ];
    }

    private static function kernelRuleset(): ConfigRuleset
    {
        $rules = require self::kernelRulesPath();

        self::assertIsArray($rules);

        return ConfigRuleset::fromArray('kernel', $rules);
    }

    private static function kernelConfigPath(): string
    {
        return __DIR__ . '/../../config/kernel.php';
    }

    private static function kernelRulesPath(): string
    {
        return __DIR__ . '/../../config/rules.php';
    }
}
