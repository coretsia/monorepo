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

use Coretsia\Contracts\Validation\Violation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValidationViolationShapeIsSafeContractTest extends TestCase
{
    public function testViolationCreatesSafeScalarShape(): void
    {
        $violation = new Violation(
            path: 'profile.email',
            code: 'VALIDATION_INVALID_FORMAT',
            rule: 'email',
            index: 7,
            message: 'Invalid email format.',
            meta: [
                'expectedType' => 'string',
                'maxLength' => 255,
                'nullable' => false,
            ],
        );

        self::assertSame(1, $violation->schemaVersion());
        self::assertSame('profile.email', $violation->path());
        self::assertSame('VALIDATION_INVALID_FORMAT', $violation->code());
        self::assertSame('email', $violation->rule());
        self::assertSame(7, $violation->index());
        self::assertSame('Invalid email format.', $violation->message());
        self::assertSame(
            [
                'expectedType' => 'string',
                'maxLength' => 255,
                'nullable' => false,
            ],
            $violation->meta(),
        );
    }

    public function testToArrayUsesStableKeyOrderWhenOptionalFieldsArePresent(): void
    {
        $violation = new Violation(
            path: 'items[0].sku',
            code: 'VALIDATION_REQUIRED',
            rule: 'required',
            index: 0,
            message: 'SKU is required.',
            meta: ['expectedType' => 'string'],
        );

        self::assertSame(
            [
                'code',
                'index',
                'message',
                'meta',
                'path',
                'rule',
                'schemaVersion',
            ],
            array_keys($violation->toArray()),
        );

        self::assertSame(
            [
                'code' => 'VALIDATION_REQUIRED',
                'index' => 0,
                'message' => 'SKU is required.',
                'meta' => [
                    'expectedType' => 'string',
                ],
                'path' => 'items[0].sku',
                'rule' => 'required',
                'schemaVersion' => 1,
            ],
            $violation->toArray(),
        );
    }

    public function testToArrayOmitsNullOptionalFieldsAndKeepsStableKeyOrder(): void
    {
        $violation = new Violation(
            path: '',
            code: 'VALIDATION_INVALID_TYPE',
        );

        self::assertSame(
            [
                'code',
                'meta',
                'path',
                'schemaVersion',
            ],
            array_keys($violation->toArray()),
        );

        self::assertSame(
            [
                'code' => 'VALIDATION_INVALID_TYPE',
                'meta' => [],
                'path' => '',
                'schemaVersion' => 1,
            ],
            $violation->toArray(),
        );
    }

    public function testMetaAcceptsJsonLikeValuesAndNormalizesMapOrderRecursively(): void
    {
        $violation = new Violation(
            path: 'profile',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'z' => 1,
                'a' => [
                    'z' => true,
                    'a' => false,
                ],
                'list' => [
                    [
                        'b' => 2,
                        'a' => 1,
                    ],
                    'value',
                    null,
                ],
                'nullValue' => null,
            ],
        );

        self::assertSame(
            [
                'a',
                'list',
                'nullValue',
                'z',
            ],
            array_keys($violation->meta()),
        );

        self::assertSame(
            [
                'a' => [
                    'a' => false,
                    'z' => true,
                ],
                'list' => [
                    [
                        'a' => 1,
                        'b' => 2,
                    ],
                    'value',
                    null,
                ],
                'nullValue' => null,
                'z' => 1,
            ],
            $violation->meta(),
        );
    }

    public function testListsInsideMetaPreserveOrder(): void
    {
        $violation = new Violation(
            path: 'items',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'allowedTypes' => [
                    'string',
                    'int',
                    'bool',
                ],
            ],
        );

        self::assertSame(
            [
                'string',
                'int',
                'bool',
            ],
            $violation->meta()['allowedTypes'],
        );
    }

    #[DataProvider('invalidFloatProvider')]
    public function testMetaRejectsFloats(float $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid float validation violation meta at meta.value');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'value' => $value,
            ],
        );
    }

    /**
     * @return iterable<string, array{0: float}>
     */
    public static function invalidFloatProvider(): iterable
    {
        yield 'float' => [1.1];
        yield 'nan' => [\NAN];
        yield 'inf' => [\INF];
        yield 'negative-inf' => [-\INF];
    }

    public function testMetaRejectsObjects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation meta at meta.value');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'value' => new \stdClass(),
            ],
        );
    }

    public function testMetaRejectsClosures(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation meta at meta.value');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'value' => static function (): void {
                },
            ],
        );
    }

    public function testMetaRejectsResources(): void
    {
        $resource = tmpfile();

        self::assertIsResource($resource);

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('Invalid validation violation meta at meta.value');

            new Violation(
                path: 'email',
                code: 'VALIDATION_INVALID_TYPE',
                meta: [
                    'value' => $resource,
                ],
            );
        } finally {
            fclose($resource);
        }
    }

    public function testMetaRejectsRootNonEmptyList(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation violation meta must be a map.');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'first',
                'second',
            ],
        );
    }

    public function testMetaRejectsNonStringMapKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation meta key at meta');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                1 => 'value',
            ],
        );
    }

    public function testMetaRejectsUnsafeMapKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation meta key at meta');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                "unsafe\nkey" => 'value',
            ],
        );
    }

    public function testMetaRejectsUnsafeStrings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation meta string at meta.value');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            meta: [
                'value' => "unsafe\x01value",
            ],
        );
    }

    #[DataProvider('invalidPathProvider')]
    public function testPathRejectsUnsafeValues(string $path): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation path.');

        new Violation(
            path: $path,
            code: 'VALIDATION_INVALID_TYPE',
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidPathProvider(): iterable
    {
        yield 'leading-space' => [' email'];
        yield 'trailing-space' => ['email '];
        yield 'lf' => ["profile\nemail"];
        yield 'cr' => ["profile\remail"];
        yield 'control-char' => ["profile\x01email"];
        yield 'unix-absolute-path' => ['/tmp/email'];
        yield 'home-absolute-path' => ['/home/user/email'];
        yield 'windows-absolute-path-backslash' => ['C:\\Users\\user\\email'];
        yield 'windows-absolute-path-slash' => ['C:/Users/user/email'];
        yield 'unc-path' => ['\\server\\share'];
    }

    #[DataProvider('invalidCodeProvider')]
    public function testCodeRejectsUnsafeOrNonCanonicalValues(string $code): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation code.');

        new Violation(
            path: 'email',
            code: $code,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidCodeProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces-only' => ['   '];
        yield 'lowercase' => ['validation_required'];
        yield 'dot-separated' => ['VALIDATION.REQUIRED'];
        yield 'dash-separated' => ['VALIDATION-REQUIRED'];
        yield 'starts-with-digit' => ['1_VALIDATION_REQUIRED'];
        yield 'lf' => ["VALIDATION\nREQUIRED"];
        yield 'cr' => ["VALIDATION\rREQUIRED"];
        yield 'control-char' => ["VALIDATION\x01REQUIRED"];
    }

    #[DataProvider('invalidOptionalSingleLineProvider')]
    public function testRuleRejectsUnsafeValues(?string $rule): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation rule.');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            rule: $rule,
        );
    }

    #[DataProvider('invalidOptionalSingleLineProvider')]
    public function testMessageRejectsUnsafeValues(?string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation message.');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            message: $message,
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidOptionalSingleLineProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces-only' => ['   '];
        yield 'lf' => ["invalid\nvalue"];
        yield 'cr' => ["invalid\rvalue"];
        yield 'control-char' => ["invalid\x01value"];
    }

    public function testIndexRejectsNegativeValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid validation violation index.');

        new Violation(
            path: 'email',
            code: 'VALIDATION_INVALID_TYPE',
            index: -1,
        );
    }
}
