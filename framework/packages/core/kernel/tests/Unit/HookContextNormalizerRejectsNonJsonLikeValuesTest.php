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

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookContextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HookContextNormalizerRejectsNonJsonLikeValuesTest extends TestCase
{
    #[DataProvider('forbiddenValues')]
    public function testNormalizeContextRejectsNonJsonLikeValues(
        mixed $value,
        string $unsafeNeedle,
    ): void {
        $this->assertRejectedSafely(
            static fn (): array => HookContextNormalizer::normalizeContext([
                'operation' => 'kernel.test',
                'payload' => $value,
            ]),
            $unsafeNeedle,
        );
    }

    #[DataProvider('forbiddenValues')]
    public function testNormalizeResultRejectsNonJsonLikeValues(
        mixed $value,
        string $unsafeNeedle,
    ): void {
        $this->assertRejectedSafely(
            static fn (): array => HookContextNormalizer::normalizeResult([
                'outcome' => 'failed',
                'payload' => $value,
            ]),
            $unsafeNeedle,
        );
    }

    public function testFailureMessageIsDeterministicAndDoesNotLeakRawObjectDiagnostics(): void
    {
        $previous = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret',
        );

        $this->assertRejectedSafely(
            static fn (): array => HookContextNormalizer::normalizeContext([
                'operation' => 'kernel.test',
                'payload' => $previous,
            ]),
            'unsafe-token',
        );
    }

    /**
     * @return iterable<string, array{0:mixed,1:string}>
     */
    public static function forbiddenValues(): iterable
    {
        yield 'float' => [
            1.5,
            '1.5',
        ];

        yield 'nan' => [
            \NAN,
            'NAN',
        ];

        yield 'inf' => [
            \INF,
            'INF',
        ];

        yield 'negative-inf' => [
            -\INF,
            '-INF',
        ];

        yield 'object' => [
            new \RuntimeException(
                'unsafe-token Authorization Cookie session_id password SELECT * FROM users /tmp/coretsia-secret',
            ),
            'unsafe-token',
        ];

        $resource = \fopen('php://memory', 'rb');
        if (!\is_resource($resource)) {
            throw new \RuntimeException('test-resource-open-failed');
        }

        yield 'resource' => [
            $resource,
            'Resource id',
        ];
    }

    /**
     * @param callable(): array<string,mixed> $callback
     */
    private function assertRejectedSafely(
        callable $callback,
        string $unsafeNeedle,
    ): void {
        try {
            $callback();
        } catch (KernelRuntimeException $exception) {
            self::assertSame(KernelRuntimeException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                KernelRuntimeException::REASON_HOOK_PAYLOAD_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_KERNEL_RUNTIME_ERROR: kernel-runtime-hook-payload-invalid',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());

            self::assertInstanceOf(JsonLikeNormalizationException::class, $exception->getPrevious());

            self::assertStringNotContainsString($unsafeNeedle, $exception->getMessage());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Cookie', $exception->getMessage());
            self::assertStringNotContainsString('session_id', $exception->getMessage());
            self::assertStringNotContainsString('password', $exception->getMessage());
            self::assertStringNotContainsString('SELECT * FROM users', $exception->getMessage());
            self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());
            self::assertStringNotContainsString(__DIR__, $exception->getMessage());

            return;
        }

        self::fail('Expected HookContextNormalizer to reject non-json-like hook payload value.');
    }
}
