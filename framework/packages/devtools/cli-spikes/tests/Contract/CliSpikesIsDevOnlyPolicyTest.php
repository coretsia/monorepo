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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class CliSpikesIsDevOnlyPolicyTest extends TestCase
{
    public function testCliSpikesPackageIsRequireDevOnlyInFrameworkComposerJson(): void
    {
        $repoRoot = \dirname(__DIR__, 6);
        $composerJsonPath = $repoRoot . '/framework/composer.json';

        self::assertTrue(\is_file($composerJsonPath), 'framework/composer.json is missing');
        self::assertTrue(\is_readable($composerJsonPath), 'framework/composer.json is not readable');

        $bytes = \file_get_contents($composerJsonPath);
        self::assertIsString($bytes, 'framework/composer.json read failed');
        self::assertNotSame('', $bytes, 'framework/composer.json is empty');

        try {
            $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            self::fail('framework/composer.json parse failed');
        }

        self::assertIsArray($decoded, 'framework/composer.json must decode to an object');
        self::assertFalse(\array_is_list($decoded), 'framework/composer.json must decode to an object (not a list)');

        /** @var array<string, mixed> $composer */
        $composer = $decoded;

        self::assertArrayHasKey('require', $composer, 'framework/composer.json must have "require"');
        self::assertIsArray($composer['require'], '"require" must be an object');
        self::assertFalse(\array_is_list($composer['require']), '"require" must be an object (not a list)');

        self::assertArrayHasKey('require-dev', $composer, 'framework/composer.json must have "require-dev"');
        self::assertIsArray($composer['require-dev'], '"require-dev" must be an object');
        self::assertFalse(\array_is_list($composer['require-dev']), '"require-dev" must be an object (not a list)');

        /** @var array<string, mixed> $require */
        $require = $composer['require'];

        /** @var array<string, mixed> $requireDev */
        $requireDev = $composer['require-dev'];

        $package = 'coretsia/devtools-cli-spikes';

        self::assertArrayNotHasKey(
            $package,
            $require,
            'Policy violation: coretsia/devtools-cli-spikes MUST NOT appear under "require" in framework/composer.json'
        );

        self::assertArrayHasKey(
            $package,
            $requireDev,
            'Policy violation: coretsia/devtools-cli-spikes MUST appear under "require-dev" in framework/composer.json'
        );

        $constraint = $requireDev[$package] ?? null;
        self::assertIsString($constraint, 'require-dev constraint must be a string');
        self::assertNotSame('', \trim($constraint), 'require-dev constraint must be non-empty');
    }
}
