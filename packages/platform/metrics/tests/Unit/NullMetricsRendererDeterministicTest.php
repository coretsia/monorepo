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

use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NullMetricsRendererDeterministicTest extends TestCase
{
    public function testRendererImplementsCanonicalPortWithoutMutableState(): void
    {
        $renderer = new NullMetricsRenderer();
        $reflection = new ReflectionClass($renderer);

        self::assertInstanceOf(MetricsRendererInterface::class, $renderer);
        self::assertTrue($reflection->isFinal());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([], $reflection->getStaticProperties());
    }

    public function testContentTypeAndRenderedBodyAreExact(): void
    {
        $renderer = new NullMetricsRenderer();

        self::assertSame('text/plain; charset=utf-8', $renderer->contentType());
        self::assertSame('', $renderer->render());
    }

    public function testRepeatedCallsAndIndependentInstancesRemainByteIdentical(): void
    {
        $first = new NullMetricsRenderer();
        $second = new NullMetricsRenderer();

        for ($attempt = 0; $attempt < 64; ++$attempt) {
            self::assertSame('text/plain; charset=utf-8', $first->contentType());
            self::assertSame($first->contentType(), $second->contentType());
            self::assertSame('', $first->render());
            self::assertSame($first->render(), $second->render());
        }
    }

    public function testMeterCallsDoNotProduceRenderableSamplesOrDiagnostics(): void
    {
        $meter = new DefaultMeter();
        $renderer = new NullMetricsRenderer();
        $warnings = [];

        \set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = [$severity, $message];

                return true;
            },
        );
        \ob_start();

        try {
            for ($attempt = 0; $attempt < 16; ++$attempt) {
                $meter->increment('synthetic.total', 1, ['outcome' => 'ok']);
                $meter->observe('synthetic.duration', $attempt, ['operation' => 'synthetic']);
                $meter->increment('synthetic.total', 1, ['token' => 'forbidden']);

                self::assertSame('text/plain; charset=utf-8', $renderer->contentType());
                self::assertSame('', $renderer->render());
            }
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertSame('', $output);
        self::assertSame('', new NullMetricsRenderer()->render());
    }
}
