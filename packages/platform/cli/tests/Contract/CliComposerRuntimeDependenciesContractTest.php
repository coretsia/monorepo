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

namespace Coretsia\Platform\Cli\Tests\Contract;

use PHPUnit\Framework\TestCase;

final class CliComposerRuntimeDependenciesContractTest extends TestCase
{
    public function testRuntimeRequirementsMatchPublicReleaseLineAndModuleClosure(): void
    {
        $root = \dirname(__DIR__, 5);
        $manifest = self::readJson($root . '/packages/platform/cli/composer.json');
        $release = self::readJson($root . '/tools/release/release-line.json');
        $constraint = $release['publicConstraint'];
        self::assertIsString($constraint);
        self::assertMatchesRegularExpression('/\A\^[0-9]+\.[0-9]+\.0\z/', $constraint);

        $required = [
            'coretsia/core-contracts',
            'coretsia/core-foundation',
            'coretsia/core-kernel',
            'coretsia/platform-redaction',
        ];
        foreach ($required as $name) {
            self::assertSame($constraint, $manifest['require'][$name] ?? null, $name);
        }
        $internal = [];
        foreach ($manifest['require'] as $name => $version) {
            if (\str_starts_with($name, 'coretsia/')) {
                self::assertSame($constraint, $version, $name);
                $internal[] = $name;
            }
        }
        \sort($internal);
        \sort($required);
        self::assertSame($required, $internal, 'CLI must not depend on a command-owner package for discovery.');
        self::assertSame(['core.kernel', 'platform.redaction'], $manifest['extra']['coretsia']['requires']);
        self::assertSame(['bin/coretsia'], $manifest['bin']);
        self::assertSame('runtime', $manifest['extra']['coretsia']['kind']);
        self::assertSame('platform.cli', $manifest['extra']['coretsia']['moduleId']);
        self::assertSame('config/cli.php', $manifest['extra']['coretsia']['defaultsConfigPath']);
        self::assertFileExists($root . '/packages/platform/cli/bin/coretsia');
        foreach (\array_keys($manifest['require']) as $name) {
            self::assertFalse(
                \preg_match(
                    '~\Acoretsia/(?:platform-worker|.*(?:migration|database|queue|storage|integration).*)\z~',
                    $name,
                ) === 1,
                $name,
            );
        }
    }

    public function testRedactionIsAnInstallationDependencyNotAConcretePhpDependency(): void
    {
        $sourceRoot = \dirname(__DIR__, 2) . '/src';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $tokens = \token_get_all($source);
            $code = '';
            foreach ($tokens as $token) {
                if (\is_array($token)) {
                    if (!\in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        $code .= $token[1];
                    }
                } else {
                    $code .= $token;
                }
            }
            self::assertSame(
                0,
                \preg_match('~Coretsia\\\\Platform\\\\Redaction(?:\\\\|\b)~', $code),
                $file->getFilename(),
            );
        }
    }

    /** @return array<string, mixed> */
    private static function readJson(string $path): array
    {
        $raw = \file_get_contents($path);
        self::assertIsString($raw);
        $value = \json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($value);
        return $value;
    }
}
