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

use PHPUnit\Framework\TestCase;

final class KernelRuntimeResetResponsibilityContractTest extends TestCase
{
    public function testResetResponsibilityStartsOnlyAfterBaseContextKeysAreWritten(): void
    {
        $source = self::stripPhpComments(
            self::sourceFile('src/Runtime/KernelRuntime.php'),
        );

        $runUnitOfWork = self::methodBody($source, 'runUnitOfWork');
        $beginUnitOfWork = self::methodBody($source, 'beginUnitOfWork');
        $helper = self::methodBody($source, 'createUnitOfWorkContextAndWriteBaseKeys');

        self::assertStringContainsString(
            '$context = $this->createUnitOfWorkContextAndWriteBaseKeys($type, $attributes);',
            $runUnitOfWork,
        );

        self::assertStringContainsString(
            '$context = $this->createUnitOfWorkContextAndWriteBaseKeys($type, $attributes);',
            $beginUnitOfWork,
        );

        self::assertStringNotContainsString(
            '$this->writeBaseContextKeys($context);',
            $runUnitOfWork,
            'runUnitOfWork() must not duplicate base context writes outside the reset-boundary helper.',
        );

        self::assertStringNotContainsString(
            '$this->writeBaseContextKeys($context);',
            $beginUnitOfWork,
            'beginUnitOfWork() must not duplicate base context writes outside the reset-boundary helper.',
        );

        self::assertStringContainsString(
            '$context = $this->createUnitOfWorkContext($type, $attributes);',
            $helper,
        );

        self::assertStringContainsString(
            '$this->writeBaseContextKeys($context);',
            $helper,
        );

        self::assertStringContainsString(
            'return $context;',
            $helper,
        );

        self::assertLessThan(
            \strpos($helper, 'return $context;'),
            \strpos($helper, '$this->writeBaseContextKeys($context);'),
            'Base context keys must be written before the resettable context is returned.',
        );

        foreach ([$runUnitOfWork, $beginUnitOfWork] as $methodBody) {
            self::assertLessThan(
                \strpos($methodBody, '$resetRequired = true;'),
                \strpos($methodBody, '$context = $this->createUnitOfWorkContextAndWriteBaseKeys($type, $attributes);'),
                'resetRequired must be set only after the reset-boundary helper returns successfully.',
            );
        }
    }

    private static function stripPhpComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function methodBody(string $source, string $methodName): string
    {
        $range = self::methodBodyRange($source, $methodName);

        return \substr(
            $source,
            $range['openBrace'],
            $range['closeBrace'] - $range['openBrace'] + 1,
        );
    }

    /**
     * @return array{openBrace:int,closeBrace:int}
     */
    private static function methodBodyRange(string $source, string $methodName): array
    {
        $offset = \strpos($source, 'function ' . $methodName . '(');

        self::assertIsInt($offset, 'Missing method ' . $methodName . '().');

        $openBrace = \strpos($source, '{', $offset);

        self::assertIsInt($openBrace, 'Missing method body for ' . $methodName . '().');

        $depth = 0;
        $length = \strlen($source);

        for ($i = $openBrace; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;

                continue;
            }

            if ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return [
                        'openBrace' => $openBrace,
                        'closeBrace' => $i,
                    ];
                }
            }
        }

        self::fail('Could not find end of method ' . $methodName . '().');
    }

    private static function sourceFile(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
