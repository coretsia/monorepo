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

namespace Coretsia\Platform\Metrics\Tests\Unit;

use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;

final class AllowedLabelSetRejectsForbiddenKeysTest extends TestCase
{
    public function testCanonicalGlobalLabelKeysAreExactAndPrivate(): void
    {
        $reflection = new ReflectionClass(AllowedLabelSet::class);
        $constant = $reflection->getReflectionConstant('ALLOWED_KEYS');

        self::assertInstanceOf(ReflectionClassConstant::class, $constant);
        self::assertTrue($constant->isPrivate());
        self::assertSame(
            [
                'method' => true,
                'status' => true,
                'driver' => true,
                'operation' => true,
                'table' => true,
                'outcome' => true,
            ],
            $constant->getValue(),
        );

        foreach (\array_keys($constant->getValue()) as $key) {
            self::assertTrue(AllowedLabelSet::accepts([$key => 'synthetic']));
        }
    }

    public function testEmptyLabelsAndAllAllowedScalarTypesAreAccepted(): void
    {
        self::assertTrue(AllowedLabelSet::accepts([]));
        self::assertTrue(AllowedLabelSet::accepts([
            'method' => 'GET',
            'status' => 200,
            'driver' => '',
            'operation' => 0,
            'table' => true,
            'outcome' => false,
        ]));

        foreach (['synthetic', '', 0, -1, true, false] as $value) {
            self::assertTrue(AllowedLabelSet::accepts(['outcome' => $value]));
        }
    }

    public function testForbiddenAndNonStringLabelKeysAreRejected(): void
    {
        foreach (
            [
                'trace_id',
                'span_id',
                'correlation_id',
                'request_id',
                'uow_id',
                'raw_path',
                'token',
                'unknown',
                'METHOD',
                'method ',
            ] as $key
        ) {
            self::assertFalse(AllowedLabelSet::accepts([$key => 'synthetic']), $key);
        }

        self::assertFalse(AllowedLabelSet::accepts([0 => 'synthetic']));
        self::assertFalse(AllowedLabelSet::accepts(['method' => 'GET', 1 => 'unexpected']));
    }

    public function testUnsupportedLabelValueTypesAreRejectedWithoutConversions(): void
    {
        foreach (
            [
                null,
                1.5,
                [],
                ['nested' => 'synthetic'],
                new \stdClass(),
                static fn (): string => 'synthetic',
            ] as $value
        ) {
            self::assertFalse(AllowedLabelSet::accepts(['method' => $value]));
        }

        $stream = \fopen('php://memory', 'r+');
        self::assertIsResource($stream);

        try {
            self::assertFalse(AllowedLabelSet::accepts(['method' => $stream]));
        } finally {
            \fclose($stream);
        }
    }

    public function testAdmissionIsIndependentOfAssociativeArrayInsertionOrder(): void
    {
        $valid = [
            'method' => 'GET',
            'status' => 200,
            'driver' => 'synthetic',
            'operation' => 'read',
            'table' => 'synthetic_table',
            'outcome' => true,
        ];
        $invalid = ['method' => 'GET', 'token' => 'synthetic', 'status' => 200];

        self::assertTrue(AllowedLabelSet::accepts($valid));
        self::assertTrue(AllowedLabelSet::accepts(\array_reverse($valid, true)));
        self::assertFalse(AllowedLabelSet::accepts($invalid));
        self::assertFalse(AllowedLabelSet::accepts(\array_reverse($invalid, true)));
    }

    public function testInvalidValuesDoNotInvokeStringableOrEmitDiagnostics(): void
    {
        $stringable = new class() implements \Stringable {
            public int $calls = 0;

            public function __toString(): string
            {
                ++$this->calls;

                throw new \RuntimeException('stringable-must-not-run');
            }
        };
        $labels = ['method' => 'GET', 'status' => $stringable];
        $original = $labels;
        $warnings = [];

        \set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = [$severity, $message];

                return true;
            },
        );
        \ob_start();

        try {
            self::assertFalse(AllowedLabelSet::accepts($labels));
            self::assertTrue(AllowedLabelSet::accepts(['method' => 'GET']));
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame(0, $stringable->calls);
        self::assertSame($original, $labels);
        self::assertSame([], $warnings);
        self::assertSame('', $output);
    }

    public function testHelperHasNoConstructorOrMutableInstanceOrStaticState(): void
    {
        $reflection = new ReflectionClass(AllowedLabelSet::class);
        $method = $reflection->getMethod('accepts');

        self::assertTrue($reflection->isFinal());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getStaticProperties());
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertSame(1, $method->getNumberOfRequiredParameters());
        self::assertSame('bool', (string) $method->getReturnType());
    }
}
