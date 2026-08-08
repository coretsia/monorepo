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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptorExtensionsEnforceRedactionContractTest extends TestCase
{
    #[DataProvider('unsafeExtensionKeyProvider')]
    public function testExtensionsRejectKnownUnsafeSemanticKeys(string $key, string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                $key => $value,
            ],
        );
    }

    public function testExtensionsRejectUnsafeKeysAtAnyNestingDepth(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'diagnostic' => [
                    'items' => [
                        [
                            'accessToken' => 'secret-token',
                        ],
                    ],
                ],
            ],
        );
    }

    #[DataProvider('absoluteLocalPathProvider')]
    public function testExtensionsRejectAbsoluteLocalPathStrings(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'reference' => $path,
            ],
        );
    }

    public function testExtensionsRejectAbsolutePathInsideNestedList(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'references' => [
                    'logical-reference',
                    '/home/app/.env',
                ],
            ],
        );
    }

    public function testExtensionsAcceptSafeHashLengthAndCountDerivations(): void
    {
        $hash = str_repeat('a', 64);

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'authorizationHash' => $hash,
                'emailHash' => $hash,
                'fieldCount' => 3,
                'passwordLength' => 12,
                'rawSqlHash' => $hash,
                'requestBodyLength' => 128,
                'resourceType' => 'order',
                'tokenHash' => $hash,
                'tokenLength' => 64,
                'violationCount' => 2,
            ],
        );

        self::assertSame(
            [
                'authorizationHash' => $hash,
                'emailHash' => $hash,
                'fieldCount' => 3,
                'passwordLength' => 12,
                'rawSqlHash' => $hash,
                'requestBodyLength' => 128,
                'resourceType' => 'order',
                'tokenHash' => $hash,
                'tokenLength' => 64,
                'violationCount' => 2,
            ],
            $descriptor->extensions(),
        );
    }

    public function testRejectionDiagnosticsDoNotEchoRawExtensionValuesOrUnsafeKeys(): void
    {
        $secret = 'highly-sensitive-token-123';

        try {
            new ErrorDescriptor(
                code: 'core.example',
                message: 'Example message.',
                extensions: [
                    'authorization' => $secret,
                ],
            );

            self::fail('Expected unsafe extension metadata to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringNotContainsString(
                $secret,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                'authorization',
                $exception->getMessage(),
            );
        }
    }

    public function testAbsolutePathRejectionDiagnosticsDoNotEchoRawValue(): void
    {
        $path = '/home/app/private/.env';

        try {
            new ErrorDescriptor(
                code: 'core.example',
                message: 'Example message.',
                extensions: [
                    'reference' => $path,
                ],
            );

            self::fail('Expected absolute local path metadata to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertStringNotContainsString(
                $path,
                $exception->getMessage(),
            );
        }
    }

    /** @return iterable<string, array{0: string, 1: string}> */
    public static function unsafeExtensionKeyProvider(): iterable
    {
        yield 'password' => ['password', 'secret-123'];
        yield 'secret' => ['secret', 'secret-123'];
        yield 'token' => ['token', 'token-123'];
        yield 'access snake' => ['access_token', 'token-123'];
        yield 'access kebab' => ['access-token', 'token-123'];
        yield 'access dot' => ['access.token', 'token-123'];
        yield 'access camel' => ['accessToken', 'token-123'];
        yield 'authorization' => ['authorization', 'Bearer token-123'];
        yield 'header' => ['headers', 'Authorization: Bearer token-123'];
        yield 'cookie' => ['cookie', 'session=secret'];
        yield 'session' => ['sessionId', 'session-123'];
        yield 'sql' => ['rawSql', 'SELECT * FROM users'];
        yield 'dsn' => ['dsn', 'mysql://user:password@localhost/db'];
        yield 'request body' => ['requestBody', '{"secret":"value"}'];
        yield 'response body' => ['responseBody', '{"secret":"value"}'];
        yield 'payload' => ['payload', 'raw-payload'];
        yield 'private key' => ['privateKey', 'private-key-bytes'];
        yield 'email' => ['email', 'user@example.com'];
        yield 'user id' => ['userId', 'user-123'];
        yield 'environment' => ['environment', 'production'];
        yield 'request id' => ['requestId', 'request-123'];
        yield 'correlation id' => ['correlationId', 'correlation-123'];
    }

    /** @return iterable<string, array{0: string}> */
    public static function absoluteLocalPathProvider(): iterable
    {
        yield 'posix' => ['/home/app/.env'];
        yield 'windows backslash' => ['C:\\Users\\App\\.env'];
        yield 'windows slash' => ['C:/Users/App/.env'];
        yield 'windows rooted' => ['\\Windows\\System32\\config'];
        yield 'unc' => ['\\\\server\\share\\secret.txt'];
        yield 'file uri posix' => ['file:///home/app/.env'];
        yield 'file uri windows' => ['file://C:/Users/App/.env'];
        yield 'file uri localhost' => ['file://localhost/home/app/.env'];
        yield 'file uri unc authority' => ['file://server/share/secret.txt'];
    }
}
