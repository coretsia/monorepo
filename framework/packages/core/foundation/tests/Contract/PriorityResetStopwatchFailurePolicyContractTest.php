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

use PHPUnit\Framework\TestCase;

final class PriorityResetStopwatchFailurePolicyContractTest extends TestCase
{
    public function testPriorityResetOrchestratorUsesSafeStopwatchWrappersOnly(): void
    {
        $source = self::sourceFile('src/Runtime/Reset/PriorityResetOrchestrator.php');

        self::assertStringContainsString('private function safeStartTimer(): mixed', $source);
        self::assertStringContainsString('private function safeStopTimer(mixed $startedAt): int', $source);

        $sourceWithoutComments = self::stripPhpComments($source);

        $outsideWrappers = self::withoutMethodBodies(
            source: $sourceWithoutComments,
            methodNames: [
                'safeStartTimer',
                'safeStopTimer',
            ],
        );

        self::assertStringNotContainsString(
            '->stopwatch->start(',
            $outsideWrappers,
            'PriorityResetOrchestrator must access Stopwatch::start() only inside safeStartTimer().',
        );

        self::assertStringNotContainsString(
            '->stopwatch->stop(',
            $outsideWrappers,
            'PriorityResetOrchestrator must access Stopwatch::stop() only inside safeStopTimer().',
        );
    }

    public function testResetAllDoesNotAccessStopwatchDirectly(): void
    {
        $source = self::stripPhpComments(
            self::sourceFile('src/Runtime/Reset/PriorityResetOrchestrator.php'),
        );

        $resetAll = self::methodBody($source, 'resetAll');

        self::assertStringContainsString('$startedAt = $this->safeStartTimer();', $resetAll);
        self::assertStringContainsString('$durationMs = $this->safeStopTimer($startedAt);', $resetAll);

        self::assertStringNotContainsString(
            '->stopwatch->start(',
            $resetAll,
            'resetAll() must not call Stopwatch::start() directly.',
        );

        self::assertStringNotContainsString(
            '->stopwatch->stop(',
            $resetAll,
            'resetAll() must not call Stopwatch::stop() directly.',
        );
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

    /**
     * @param list<non-empty-string> $methodNames
     */
    private static function withoutMethodBodies(string $source, array $methodNames): string
    {
        foreach ($methodNames as $methodName) {
            $source = self::withoutMethodBody($source, $methodName);
        }

        return $source;
    }

    private static function withoutMethodBody(string $source, string $methodName): string
    {
        $range = self::methodBodyRange($source, $methodName);

        return \substr($source, 0, $range['openBrace'] + 1)
            . "\n/* method body removed by contract test */\n"
            . \substr($source, $range['closeBrace']);
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
