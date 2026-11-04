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

namespace Coretsia\Platform\Tracing\Tests\Contract;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;

final class W3CPropagationDeterministicContractTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';
    private const string PARENT_ID = '00f067aa0ba902b7';

    public function testPortShapeAndCanonicalThreeFieldExtraction(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $header = self::header('03');

        self::assertInstanceOf(ContextPropagationInterface::class, $propagation);
        self::assertSame(
            self::context('03'),
            $propagation->extract([
                'TRACEPARENT' => [$header],
                'tracestate' => 'vendor=private',
                'authorization' => 'caller-owned',
            ]),
        );
        self::assertSame(
            ['x-request-id' => 'safe', 'traceparent' => $header],
            $propagation->inject(['x-request-id' => 'safe'], self::context('03')),
        );
    }

    public function testIdenticalInputsRemainDeterministicWithoutImplicitIdentityGeneration(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [
            'TrAcEpArEnT' => self::header('02'),
            'tracestate' => 'vendor=opaque',
            'x-request-id' => ['first', 'second'],
        ];
        $initialCarrier = $carrier;
        $expectedContext = self::context('02');
        $expectedOutgoing = [
            'tracestate' => 'vendor=opaque',
            'x-request-id' => ['first', 'second'],
            'traceparent' => self::header('02'),
        ];

        for ($iteration = 0; $iteration < 10; $iteration++) {
            self::assertSame($expectedContext, $propagation->extract($carrier));
            self::assertSame($expectedOutgoing, $propagation->inject($carrier, $expectedContext));
            self::assertSame([], $propagation->extract([]));
            self::assertSame([], $propagation->inject([], []));
            self::assertSame(['x-request-id' => 'safe'], $propagation->inject(['x-request-id' => 'safe']));
            self::assertSame($initialCarrier, $carrier);
        }
    }

    public function testMalformedAndUnsupportedValuesFailClosedDeterministically(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $invalid = [
            'invalid',
            self::header('04'),
            self::header('03', 'ff'),
            self::header('03', '01') . 'X',
            self::header('03') . '-extension',
            '00-' . \str_repeat('0', 32) . '-' . self::PARENT_ID . '-01',
        ];

        foreach ($invalid as $value) {
            $carrier = [
                'traceparent' => $value,
                'tracestate' => 'vendor=opaque',
                'x-request-id' => 'safe',
            ];

            for ($iteration = 0; $iteration < 3; $iteration++) {
                self::assertSame([], $propagation->extract($carrier));
                self::assertSame(['x-request-id' => 'safe'], $propagation->inject($carrier, self::context()));
            }
        }
    }

    public function testForwardVersionDowngradeAndSafeCarrierBoundaries(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $forward = self::header('ff', '01') . '-future';
        $expected = self::context('03');

        self::assertSame($expected, $propagation->extract(['traceparent' => $forward]));
        self::assertSame(
            ['x-request-id' => 'safe', 'traceparent' => self::header('03')],
            $propagation->inject([
                'traceparent' => $forward,
                'tracestate' => 'vendor=opaque',
                'x-request-id' => 'safe',
            ], $expected),
        );
        self::assertSame(
            ['x-request-id' => 'safe'],
            $propagation->inject([
                'traceparent' => self::header('02'),
                'tracestate' => 'vendor=opaque',
                'x-request-id' => 'safe',
            ], self::context('00')),
        );
    }

    /**
     * @return array{trace_id:string,parent_id:string,trace_flags:string}
     */
    private static function context(string $flags = '01'): array
    {
        return [
            'trace_id' => self::TRACE_ID,
            'parent_id' => self::PARENT_ID,
            'trace_flags' => $flags,
        ];
    }

    private static function header(string $flags, string $version = '00'): string
    {
        return $version . '-' . self::TRACE_ID . '-' . self::PARENT_ID . '-' . $flags;
    }
}
