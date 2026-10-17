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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Platform\Cli\Provider\CliServiceFactory;
use PHPUnit\Framework\TestCase;

final class CliDoesNotImplementConfigPipelineContractTest extends TestCase
{
    public function testCliConsumesOnlyTheKernelValidatedConfigRepositoryPort(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $configFactory = new \ReflectionClass(CliServiceFactory::class);
        $source = \file_get_contents($configFactory->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('ConfigRepositoryInterface', $source);
        self::assertSame(
            ConfigRepositoryInterface::class,
            new \ReflectionMethod(
                CliServiceFactory::class,
                'cliOutputPolicy',
            )->getParameters()[0]->getType()?->getName(),
        );

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $visited = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$visited;
            $code = self::executablePhp($file->getPathname());
            foreach (
                [
                    'Coretsia\\Kernel\\Config\\',
                    'Coretsia\\Foundation\\Config\\',
                    'ConfigLoader',
                    'ConfigMerger',
                    'ConfigValidator',
                    'ConfigRulesLoader',
                    'ConfigDirectiveProcessor',
                    'ConfigRepositoryInterface::class, new ',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $code, $file->getFilename());
            }
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:file_get_contents|readfile|parse_ini_file|scandir|glob)\s*\(/',
                $code,
                $file->getFilename(),
            );
            $raw = \file_get_contents($file->getPathname());
            self::assertIsString($raw);
            $previous = null;
            foreach (\token_get_all($raw) as $token) {
                if (\is_array($token) && \in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                if (
                    \is_array($token)
                    && (
                        \in_array($token[0], [T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE], true)
                        || ($token[0] === T_STRING && $token[1] === 'file')
                        || ($token[0] === T_NAME_FULLY_QUALIFIED && $token[1] === '\\file')
                    )
                ) {
                    self::assertContains(
                        $previous,
                        [T_FUNCTION, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON],
                        $file->getFilename(),
                    );
                }
                $previous = \is_array($token) ? $token[0] : $token;
            }
            self::assertDoesNotMatchRegularExpression(
                '~[\x27\x22](?:[^\x27\x22]*?/)?config/(?:cli|rules)\.php[\x27\x22]~',
                $code,
                $file->getFilename(),
            );
            if (!\str_ends_with(\str_replace('\\', '/', $file->getPathname()), '/Output/TerminalCapabilitiesDetector.php')) {
                self::assertDoesNotMatchRegularExpression(
                    '/\b(?:getenv|putenv)\s*\(/',
                    $code,
                    $file->getFilename(),
                );
            }
        }
        self::assertGreaterThan(0, $visited);
        self::assertDirectoryDoesNotExist($root . '/Config');
    }

    private static function executablePhp(string $path): string
    {
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $result = '';
        foreach (\token_get_all($source) as $token) {
            if (\is_string($token)) {
                $result .= $token;
            } elseif (!\in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $result .= $token[1];
            }
        }
        return $result;
    }
}
