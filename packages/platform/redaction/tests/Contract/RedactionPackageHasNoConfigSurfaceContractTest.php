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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Platform\Redaction\Module\RedactionModule;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class RedactionPackageHasNoConfigSurfaceContractTest extends TestCase
{
    public function testPackageIntroducesNoConfigDirectoryOrModuleConfigRoot(): void
    {
        $root = \dirname(__DIR__, 2);
        self::assertDirectoryDoesNotExist($root . '/config');
        self::assertFileDoesNotExist($root . '/config/redaction.php');
        self::assertFileDoesNotExist($root . '/config/rules.php');

        $module = new RedactionModule();
        self::assertFalse(\defined(RedactionModule::class . '::CONFIG_ROOT'));
        self::assertFalse(new ReflectionClass($module)->hasMethod('configRoot'));

        $contents = \file_get_contents($root . '/composer.json');
        self::assertIsString($contents);
        $composer = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertArrayNotHasKey('defaultsConfigPath', $composer['extra']['coretsia']);
    }

    public function testProductionSourceHasNoRuntimeConfigEnvOrDisableSwitch(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $sources = 0;
        $forbidden = [
            'redaction.enabled',
            'redaction.mode',
            'redaction.disable',
            'redaction.policy',
            'redaction.patterns',
            'redaction.hash_algorithm',
            'security.redaction.enabled',
            'foundation.redaction.enabled',
            'cli.redaction.enabled',
        ];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$sources;
            $contents = \file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $code = self::codeWithoutComments($contents);

            foreach ($forbidden as $key) {
                self::assertStringNotContainsString($key, $code);
            }

            self::assertDoesNotMatchRegularExpression(
                '/\b(?:getenv|putenv|apache_getenv|ini_get|parse_ini_file)\s*\(|\$_(?:ENV|SERVER|REQUEST)\b|\bconfigRoot\s*\(/',
                $code,
            );
        }

        self::assertSame(6, $sources);
    }

    private static function codeWithoutComments(string $source): string
    {
        $code = '';

        foreach (\token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= \is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
