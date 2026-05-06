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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ContainerDiagnostics;
use PHPUnit\Framework\TestCase;

final class ContainerDiagnosticsDoesNotContainAbsolutePathsContractTest extends TestCase
{
    public function testDiagnosticsRedactsAbsolutePathLikeServiceIds(): void
    {
        $paths = self::absolutePathLikeServiceIds();
        $builder = new ContainerBuilder(config: self::validConfig());

        foreach ($paths as $path) {
            $builder->set($path, 'redacted');
        }

        $builder->tag('kernel.reset', $paths[0], 100);
        $builder->tag('kernel.reset', $paths[1], 50);
        $builder->tag('health.check', $paths[2], 0);
        $builder->tag('cli.command', $paths[3], 0);
        $builder->tag('http.middleware.app', $paths[4], 0);

        $json = ContainerDiagnostics::fromBuilder($builder)->toJson();

        foreach ($paths as $path) {
            self::assertStringNotContainsString($path, $json);
            self::assertStringContainsString(self::redactedPathId($path), $json);
        }
    }

    public function testDiagnosticsJsonDoesNotContainAbsolutePathPatterns(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        foreach (self::absolutePathLikeServiceIds() as $path) {
            $builder->set($path, 'redacted');
            $builder->tag('kernel.reset', $path, 0);
        }

        $json = ContainerDiagnostics::fromBuilder($builder)->toJson();

        self::assertStringNotContainsString('/home/', $json);
        self::assertStringNotContainsString('/Users/', $json);
        self::assertStringNotContainsString('C:', $json);
        self::assertStringNotContainsString('server', $json);
        self::assertStringNotContainsString('share', $json);

        self::assertDoesNotMatchRegularExpression(
            '~(/home/|/Users/|[A-Za-z]:[\\\\/]|\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+)~',
            $json,
        );
    }

    public function testDiagnosticsKeepsNonPathServiceIdsReadable(): void
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->set('service.alpha', 'alpha');
        $builder->tag('kernel.reset', 'service.alpha', 0);

        $json = ContainerDiagnostics::fromBuilder($builder)->toJson();

        self::assertStringContainsString('service.alpha', $json);
        self::assertStringNotContainsString('hash:sha256:', $json);
    }

    /**
     * @return list<string>
     */
    private static function absolutePathLikeServiceIds(): array
    {
        return [
            '/home/vlad/project/src/SecretService.php',
            '/Users/vlad/project/src/SecretService.php',
            'C:/Users/Vlad/project/SecretService.php',
            'C:\\Users\\Vlad\\project\\SecretService.php',
            '\\\\server\\share\\project\\SecretService.php',
        ];
    }

    private static function redactedPathId(string $path): string
    {
        return 'hash:sha256:' . \hash('sha256', $path) . ';len:' . \strlen($path);
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}
