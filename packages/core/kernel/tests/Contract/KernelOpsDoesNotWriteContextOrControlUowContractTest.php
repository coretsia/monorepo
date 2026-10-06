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

final class KernelOpsDoesNotWriteContextOrControlUowContractTest extends TestCase
{
    public function testKernelOpsFacadeReadsContextOnlyAndDoesNotOwnRuntimeLifecycle(): void
    {
        $source = self::phpWithoutComments(
            self::opsSource('KernelOpsFacade.php'),
        );

        self::assertStringNotContainsString('ContextStore', $source);
        self::assertStringNotContainsString('KernelRuntimeInterface', $source);
        self::assertStringNotContainsString('ResetOrchestrator', $source);
        self::assertStringNotContainsString('kernel.reset', $source);

        self::assertSame(
            ['get', 'has'],
            self::contextAccessorMethodCalls($source),
            'KernelOpsFacade may read context through ContextAccessorInterface only.',
        );

        foreach (self::forbiddenLifecycleNeedles() as $needle) {
            self::assertStringNotContainsString($needle, $source);
        }
    }

    public function testKernelOpsHostBooterDoesNotResolveOrInvokeKernelRuntime(): void
    {
        $source = self::phpWithoutComments(
            self::opsSource('KernelOpsHostBooter.php'),
        );

        self::assertStringNotContainsString(
            'KernelRuntimeInterface',
            $source,
            'KernelOpsHostBooter must not reference or resolve KernelRuntimeInterface directly.',
        );
        self::assertDoesNotMatchRegularExpression(
            '/\bKernelRuntime\b/',
            $source,
            'KernelOpsHostBooter must not construct, resolve, or invoke KernelRuntime directly.',
        );

        foreach (self::forbiddenLifecycleNeedles() as $needle) {
            self::assertStringNotContainsString($needle, $source);
        }

        self::assertStringNotContainsString('ResetOrchestrator', $source);
        self::assertStringNotContainsString('HookInvoker', $source);
        self::assertStringNotContainsString('ReservedTags::KERNEL_RESET', $source);
        self::assertStringNotContainsString('kernel.reset', $source);
    }

    /**
     * @return list<string>
     */
    private static function contextAccessorMethodCalls(string $source): array
    {
        \preg_match_all(
            '/\$this->contextAccessor->([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            $source,
            $matches,
        );

        $methods = \array_values(\array_unique($matches[1] ?? []));
        \sort($methods, \SORT_STRING);

        return $methods;
    }

    /**
     * @return list<string>
     */
    private static function forbiddenLifecycleNeedles(): array
    {
        return [
            'runUnitOfWork(',
            'beginUnitOfWork(',
            'afterUnitOfWork(',
            'beforeUnitOfWork(',
            'invokeBefore',
            'invokeAfter',
            'resetAll(',
            'resetTagged(',
        ];
    }

    private static function opsSource(string $fileName): string
    {
        $path = self::kernelRoot() . '/src/Ops/' . $fileName;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function phpWithoutComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if (
                $token[0] === \T_COMMENT
                || $token[0] === \T_DOC_COMMENT
            ) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }
}
