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

use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContextWriteForbiddenDiagnosticsAreSafeContractTest extends TestCase
{
    /**
     * @return iterable<string,array{0:string}>
     */
    public static function writeForbiddenReasonProvider(): iterable
    {
        yield 'generic write forbidden' => ['context-write-forbidden'];
        yield 'float forbidden' => ['context-write-forbidden-float'];
        yield 'closure forbidden' => ['context-write-forbidden-closure'];
        yield 'object forbidden' => ['context-write-forbidden-object'];
        yield 'resource forbidden' => ['context-write-forbidden-resource'];
        yield 'map key forbidden' => ['context-write-forbidden-map-key'];
        yield 'type forbidden' => ['context-write-forbidden-type'];
    }

    #[DataProvider('writeForbiddenReasonProvider')]
    public function testStableReasonTokensExposeStableRuntimeShape(string $reason): void
    {
        $exception = new ContextWriteForbiddenException(
            path: 'correlation_id.safe[0].value',
            reason: $reason,
        );

        self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
        self::assertSame($reason, $exception->reason());
        self::assertSame('correlation_id.safe[0].value', $exception->safePath());
        self::assertSame($reason . ': value at correlation_id.safe[0].value', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertNull($exception->getPrevious());
    }

    public function testEmptyPathKeepsMessageAsStableReasonOnly(): void
    {
        $exception = new ContextWriteForbiddenException(
            path: '',
            reason: 'context-write-forbidden-type',
        );

        self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-write-forbidden-type', $exception->reason());
        self::assertNull($exception->safePath());
        self::assertSame('context-write-forbidden-type', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    /**
     * @return iterable<string,array{0:string}>
     */
    public static function safePathProvider(): iterable
    {
        yield 'root context path' => ['correlation_id'];
        yield 'dotted safe path' => ['correlation_id.safe.value'];
        yield 'list index path' => ['correlation_id.safe[0].value'];
        yield 'multi digit list index path' => ['correlation_id.items[123456789]'];
        yield 'sanitized map key placeholder path' => ['correlation_id.safe[<key>]'];
        yield 'mixed list and map placeholder path' => ['correlation_id.items[0].safe[<key>]'];
    }

    #[DataProvider('safePathProvider')]
    public function testSafePathsRemainVisibleWhenTheyMatchConservativeSafePathPolicy(
        string $path,
    ): void {
        $exception = new ContextWriteForbiddenException(
            path: $path,
            reason: 'context-write-forbidden-float',
        );

        self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-write-forbidden-float', $exception->reason());
        self::assertSame($path, $exception->safePath());
        self::assertSame('context-write-forbidden-float: value at ' . $path, $exception->getMessage());
        self::assertSame(0, $exception->getCode());
    }

    /**
     * @return iterable<string,array{0:string,1:list<string>}>
     */
    public static function unsafePathProvider(): iterable
    {
        yield 'authorization-like path' => [
            'Authorization',
            ['Authorization'],
        ];

        yield 'bearer-like path' => [
            'Bearer',
            ['Bearer'],
        ];

        yield 'cookie-like path' => [
            'cookie',
            ['cookie'],
        ];

        yield 'session-like path' => [
            'session_id',
            ['session_id', 'session'],
        ];

        yield 'token-like path' => [
            'token',
            ['token'],
        ];

        yield 'credential-like path' => [
            'credential',
            ['credential'],
        ];

        yield 'password-like path' => [
            'password',
            ['password'],
        ];

        yield 'secret-like path' => [
            'secret',
            ['secret'],
        ];

        yield 'api-key-like path' => [
            'api_key',
            ['api_key'],
        ];

        yield 'access-key-like path' => [
            'access_key',
            ['access_key'],
        ];

        yield 'private-key-like path' => [
            'private_key',
            ['private_key'],
        ];

        yield 'sql-like path' => [
            'select.users',
            ['select', 'users'],
        ];

        yield 'raw-sql-like path' => [
            'raw_sql',
            ['raw_sql'],
        ];

        yield 'url-like path' => [
            'https://example.test/path?token=raw-secret-token',
            ['https://example.test/path?token=raw-secret-token', 'https://example.test', 'token', 'raw-secret-token'],
        ];

        yield 'absolute unix path' => [
            '/home/user/project/.env',
            ['/home/user/project/.env', '.env'],
        ];

        yield 'absolute windows path' => [
            'C:\\Users\\coretsia\\.env',
            ['C:\\Users\\coretsia\\.env', '.env'],
        ];

        yield 'slash path' => [
            'safe/path',
            ['safe/path'],
        ];

        yield 'whitespace path' => [
            'safe path',
            ['safe path'],
        ];

        yield 'control-character path' => [
            "safe\npath",
            ["safe\npath", "\n"],
        ];

        yield 'object-dump-like path' => [
            'object(stdClass)#123',
            ['object(stdClass)#123', 'stdClass'],
        ];

        yield 'environment-specific bytes path' => [
            'D:\\Projects\\coretsia\\monorepo\\framework\\.env',
            ['D:\\Projects\\coretsia\\monorepo\\framework\\.env', '.env'],
        ];

        yield 'overlong safe-looking path' => [
            self::overlongSafeLookingPath(),
            [self::overlongSafeLookingPath()],
        ];
    }

    /**
     * @param list<string> $forbiddenFragments
     */
    #[DataProvider('unsafePathProvider')]
    public function testUnsafePathsAreReplacedWithStablePlaceholder(
        string $path,
        array $forbiddenFragments,
    ): void {
        $exception = new ContextWriteForbiddenException(
            path: $path,
            reason: 'context-write-forbidden-type',
        );

        self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-write-forbidden-type', $exception->reason());
        self::assertSame('<path>', $exception->safePath());
        self::assertSame('context-write-forbidden-type: value at <path>', $exception->getMessage());
        self::assertSame(0, $exception->getCode());

        foreach ($forbiddenFragments as $fragment) {
            self::assertStringNotContainsString(
                $fragment,
                $exception->getMessage(),
                'Context write-forbidden messages must not expose unsafe raw path fragments.',
            );

            self::assertStringNotContainsString(
                $fragment,
                $exception->safePath() ?? '',
                'ContextWriteForbiddenException::safePath() must not expose unsafe raw path fragments.',
            );

            self::assertStringNotContainsString(
                $fragment,
                $exception->reason(),
                'ContextWriteForbiddenException::reason() must remain a stable safe reason token.',
            );
        }
    }

    public function testPreviousThrowableMayBePreservedButMessageRemainsSafe(): void
    {
        $previous = new \RuntimeException(
            'raw-rejected-value Authorization Bearer raw-token Cookie session_id=raw-cookie credential=raw-credential password=raw-password SELECT * FROM users /home/user/project/.env object(stdClass)#123',
        );

        $exception = new ContextWriteForbiddenException(
            path: 'correlation_id.safe[<key>]',
            reason: 'context-write-forbidden-object',
            previous: $previous,
        );

        self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
        self::assertSame('context-write-forbidden-object', $exception->reason());
        self::assertSame('correlation_id.safe[<key>]', $exception->safePath());
        self::assertSame(
            'context-write-forbidden-object: value at correlation_id.safe[<key>]',
            $exception->getMessage(),
        );
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());

        foreach (self::unsafePreviousThrowableNeedles() as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $exception->getMessage(),
                'Context write-forbidden message must not copy unsafe previous throwable details.',
            );

            self::assertStringNotContainsString(
                $needle,
                $exception->reason(),
                'Context write-forbidden reason must not copy unsafe previous throwable details.',
            );

            self::assertStringNotContainsString(
                $needle,
                $exception->safePath() ?? '',
                'Context write-forbidden safePath must not copy unsafe previous throwable details.',
            );
        }
    }

    public function testUnknownReasonTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('context-write-forbidden-reason-invalid');

        new ContextWriteForbiddenException(
            path: 'correlation_id.safe',
            reason: 'context-write-forbidden-raw-secret',
        );
    }

    public function testEmptyReasonTokenIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('context-write-forbidden-reason-empty');

        new ContextWriteForbiddenException(
            path: 'correlation_id.safe',
            reason: '',
        );
    }

    private static function overlongSafeLookingPath(): string
    {
        return 'a.' . \str_repeat('safe.', 130) . 'value';
    }

    /**
     * @return list<string>
     */
    private static function unsafePreviousThrowableNeedles(): array
    {
        return [
            'raw-rejected-value',
            'Authorization',
            'Bearer',
            'raw-token',
            'Cookie',
            'session_id',
            'raw-cookie',
            'credential',
            'raw-credential',
            'password',
            'raw-password',
            'SELECT',
            'users',
            '/home/user/project/.env',
            '.env',
            'object(stdClass)#123',
            'stdClass',
        ];
    }
}
