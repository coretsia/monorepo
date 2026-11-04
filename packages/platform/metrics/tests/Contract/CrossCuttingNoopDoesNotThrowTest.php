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

namespace Coretsia\Platform\Metrics\Tests\Contract;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Labels\AllowedLabelSet;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;
use Coretsia\Platform\Metrics\Provider\MetricsServiceProvider;
use PHPUnit\Framework\TestCase;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testSelectedMeterAndRendererImplementExistingPorts(): void
    {
        self::assertInstanceOf(MeterPortInterface::class, new DefaultMeter());
        self::assertInstanceOf(MetricsRendererInterface::class, new NullMetricsRenderer());

        $meter = new DefaultMeter();

        for ($attempt = 0; $attempt < 32; ++$attempt) {
            $meter->increment('kernel.operation_total', 1, [
                'operation' => 'config.validate',
                'outcome' => 'success',
            ]);
            $meter->observe('kernel.operation_duration_ms', $attempt, [
                'operation' => 'config.validate',
                'outcome' => 'success',
            ]);
        }

        self::assertTrue(AllowedLabelSet::accepts(['outcome' => 'ok']));
    }

    public function testForbiddenLabelsAreDiscardedWithoutDiagnosticsOrCoercion(): void
    {
        $stringable = new class() implements \Stringable {
            public int $calls = 0;

            public function __toString(): string
            {
                ++$this->calls;
                throw new \LogicException('stringable-must-not-run');
            }
        };
        $meter = new DefaultMeter();
        $warnings = [];

        \set_error_handler(
            static function (int $severity, string $message) use (&$warnings): bool {
                $warnings[] = [$severity, $message];

                return true;
            },
        );
        \ob_start();

        try {
            $meter->increment('kernel.operation_total', 1, ['token' => 'synthetic-private']);
            $meter->observe('kernel.operation_duration_ms', 3, ['trace_id' => 'synthetic-private']);
            $meter->increment('kernel.operation_total', 1, ['outcome' => $stringable]);
            $meter->observe('kernel.operation_duration_ms', 3, ['operation' => []]);
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame(0, $stringable->calls);
        self::assertSame([], $warnings);
        self::assertSame('', $output);
    }

    public function testRepeatedMeterCallsNeverCreateRenderableState(): void
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
            for ($attempt = 0; $attempt < 64; ++$attempt) {
                $meter->increment('kernel.operation_total', 1, [
                    'operation' => 'config.validate',
                    'outcome' => 'success',
                ]);
                $meter->observe('kernel.operation_duration_ms', $attempt, [
                    'operation' => 'config.validate',
                    'outcome' => 'success',
                ]);
                $meter->increment('kernel.operation_total', 1, ['request_id' => 'forbidden']);

                self::assertSame('', $renderer->render());
                self::assertSame('text/plain; charset=utf-8', $renderer->contentType());
            }
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame([], $warnings);
        self::assertSame('', $output);
        self::assertSame('', new NullMetricsRenderer()->render());

        foreach ([DefaultMeter::class, NullMetricsRenderer::class, AllowedLabelSet::class] as $class) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->isFinal());
            self::assertNull($reflection->getConstructor());
            self::assertSame([], $reflection->getProperties());
            self::assertSame([], $reflection->getStaticProperties());
            self::assertFalse($reflection->hasMethod('reset'));
            self::assertFalse($reflection->hasMethod('snapshot'));
        }
    }

    public function testFoundationOnlyContainerRetainsCanonicalNoopMeterFallback(): void
    {
        $foundation = new ContainerBuilder(['foundation' => []]);
        $foundation->registerProviders([new FoundationServiceProvider()]);
        $fallback = $foundation->build();

        self::assertInstanceOf(NoopMeter::class, $fallback->get(MeterPortInterface::class));
        self::assertFalse($fallback->has(MetricsRendererInterface::class));

        $selected = new ContainerBuilder(['foundation' => [], 'metrics' => []]);
        $selected->registerProviders([
            new FoundationServiceProvider(),
            new MetricsServiceProvider(),
        ]);
        $container = $selected->build();

        self::assertInstanceOf(DefaultMeter::class, $container->get(MeterPortInterface::class));
        self::assertInstanceOf(NullMetricsRenderer::class, $container->get(MetricsRendererInterface::class));
    }
}
