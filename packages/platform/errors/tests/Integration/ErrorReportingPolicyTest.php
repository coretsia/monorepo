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

namespace Coretsia\Platform\Errors\Tests\Integration;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ErrorReportingPolicyTest extends TestCase
{
    public function testDisabledReportingStillMapsAndRedactsWithoutInvokingAnyOptionalSink(): void
    {
        $calls = [];
        $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Operation failed.');
        $handler = $this->handler($descriptor, false, ErrorSeverity::Info, [], 10000, $calls);
        self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
        self::assertSame(['map', 'redact'], $calls);
    }

    public function testDefaultReportingPolicyIsDisabledAndAllowsAllCanonicalSeveritiesWhenEnabled(): void
    {
        $defaults = require \dirname(__DIR__, 2) . '/config/errors.php';
        self::assertSame([
            'enabled' => false,
            'min_severity' => 'info',
            'exclude_codes' => [],
            'sample_rate' => 10000,
            'mapper_failures' => ['enabled' => false],
        ], $defaults['reporting']);

        foreach (ErrorSeverity::cases() as $severity) {
            $events = [];
            $descriptor = new ErrorDescriptor('APPROVED_' . \strtoupper($severity->value), 'Public reason.', $severity);
            $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 10000, $events);
            self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
            self::assertSame(1, \count(\array_filter($events, static fn (string $e): bool => $e === 'report')));
        }
    }

    public function testSeverityThresholdUsesCanonicalFourLevelOrderingAndOnlyFiltersReporter(): void
    {
        $severityOrder = [ErrorSeverity::Info, ErrorSeverity::Warning, ErrorSeverity::Error, ErrorSeverity::Critical];
        foreach ($severityOrder as $thresholdIndex => $threshold) {
            foreach ($severityOrder as $levelIndex => $level) {
                $events = [];
                $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.', $level);
                $handler = $this->handler($descriptor, true, $threshold, [], 10000, $events);
                self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
                self::assertSame($levelIndex >= $thresholdIndex, \in_array('report', $events, true));
                self::assertContains('map', $events);
                self::assertContains('redact', $events);
                self::assertContains('counter:ok', $events);
                self::assertContains('duration:ok', $events);
                self::assertContains('span.end', $events);
            }
        }
    }

    public function testExcludedCodesMatchExactlyWithCaseSensitivityWithoutSuppressingNormalization(): void
    {
        foreach ([['OWNER_APPROVED', false], ['owner_approved', true]] as [$excluded, $expectedReport]) {
            $events = [];
            $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
            $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [$excluded], 10000, $events);
            self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
            self::assertSame($expectedReport, \in_array('report', $events, true));
            self::assertContains('counter:ok', $events);
            self::assertContains('span.end', $events);
        }
    }

    public function testSamplingBoundsPreserveDescriptorsAndOnlySuppressReporter(): void
    {
        foreach ([0, 10000] as $rate) {
            $events = [];
            $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
            $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], $rate, $events);
            for ($i = 0; $i < 3; ++$i) {
                self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
            }
            self::assertSame(
                $rate === 10000 ? 3 : 0,
                \count(\array_filter($events, static fn (string $e): bool => $e === 'report')),
            );
            self::assertSame(3, \count(\array_filter($events, static fn (string $e): bool => $e === 'counter:ok')));
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testIntermediateSamplingUsesBoundedDecisionWithoutFlakyStatisticalAssertions(): void
    {
        eval(
            'namespace Coretsia\\Platform\\Errors;'
            . ' function random_int(int $min, int $max): int {'
            . ' $GLOBALS["coretsia_sampling_bounds"][] = [$min, $max];'
            . ' return \\array_shift($GLOBALS["coretsia_sampling_draws"]);'
            . ' }'
        );

        $GLOBALS['coretsia_sampling_draws'] = [0, 3455, 3456, 9999];
        $GLOBALS['coretsia_sampling_bounds'] = [];

        $events = [];
        $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
        $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 3456, $events);

        for ($i = 0; $i < 4; ++$i) {
            self::assertSame(
                $descriptor->toArray(),
                $handler->handle(new \RuntimeException('PRIVATE'))->toArray(),
            );
        }

        self::assertSame([
            [0, 9999],
            [0, 9999],
            [0, 9999],
            [0, 9999],
        ], $GLOBALS['coretsia_sampling_bounds']);

        self::assertSame([], $GLOBALS['coretsia_sampling_draws']);

        self::assertSame(
            2,
            \count(\array_filter($events, static fn (string $e): bool => $e === 'report')),
        );
        self::assertSame(
            4,
            \count(\array_filter($events, static fn (string $e): bool => $e === 'counter:ok')),
        );
        self::assertSame(
            4,
            \count(\array_filter($events, static fn (string $e): bool => $e === 'span.end')),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSamplingGeneratorFailureDoesNotReplaceDescriptorOrEscape(): void
    {
        // Isolated process: an exact namespace-level function seam exercises random_int failure.
        eval('namespace Coretsia\\Platform\\Errors; function random_int(int $min, int $max): int { throw new \\RuntimeException("SYNTHETIC_RANDOM_FAILURE"); }');
        $events = [];
        $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
        $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 5000, $events);
        self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
        self::assertNotContains('report', $events);
        self::assertContains('counter:ok', $events);
        self::assertContains('span.end', $events);
    }

    public function testOutcomeFollowsAcceptedDescriptorOrFallbackNotReporterFilters(): void
    {
        foreach ([true, false] as $accepted) {
            $events = [];
            $handler = $this->handler(
                $accepted ? new ErrorDescriptor('OWNER_APPROVED', 'Public reason.') : null,
                true,
                ErrorSeverity::Critical,
                ['OWNER_APPROVED', 'CORETSIA_UNHANDLED_EXCEPTION'],
                0,
                $events,
            );
            $result = $handler->handle(new \RuntimeException('PRIVATE'));
            $outcome = $accepted ? 'ok' : 'fallback';
            self::assertSame($accepted ? 'OWNER_APPROVED' : 'CORETSIA_UNHANDLED_EXCEPTION', $result->code());
            self::assertContains('counter:' . $outcome, $events);
            self::assertContains('duration:' . $outcome, $events);
            self::assertContains('span.attributes:' . $outcome, $events);
            self::assertNotContains('report', $events);
        }
    }

    public function testRejectedRedactedDescriptorOverridesMapperSuccessWithFallbackOutcome(): void
    {
        $events = [];
        $handler = $this->handler(
            new ErrorDescriptor('OWNER_APPROVED', 'Public reason.'),
            true,
            ErrorSeverity::Critical,
            ['CORETSIA_UNHANDLED_EXCEPTION'],
            0,
            $events,
            'redactor.malformed',
        );
        $descriptor = $handler->handle(new \RuntimeException('PRIVATE'));
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        self::assertContains('map', $events);
        self::assertContains('redact', $events);
        self::assertContains('counter:fallback', $events);
        self::assertContains('duration:fallback', $events);
        self::assertContains('span.attributes:fallback', $events);
        self::assertNotContains('report', $events);
    }

    public function testMapperDiagnosticsRequireBothFlagsButIgnoreReporterOnlyFilters(): void
    {
        foreach (
            [
                [false, true, false],
                [true, false, false],
                [true, true, true],
            ] as [$reporting, $diagnostics, $expectedLog]
        ) {
            $tags = new TagRegistry();
            $tags->add(ReservedTags::ERROR_MAPPER, 'throwing.mapper');
            $mapper = $this->createStub(ExceptionMapperInterface::class);
            $mapper->method('map')->willThrowException(new \RuntimeException('PRIVATE_MAPPER_FAILURE'));
            $container = $this->createStub(ContainerInterface::class);
            $container->method('get')->willReturn($mapper);
            $logger = $this->createMock(LoggerInterface::class);
            $logger->expects($expectedLog ? self::once() : self::never())
                ->method('warning')->with('error.mapper_failed', ['operation' => 'map', 'reason' => 'mapper_failed']);
            $registry = new ExceptionMapperRegistry($tags, $container, $logger, $reporting, $diagnostics);
            $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
            $redactor->method('redactJsonLike')->willReturnArgument(0);
            $correlation = $this->createStub(CorrelationIdProviderInterface::class);
            $correlation->method('correlationId')->willReturn(null);
            $reporter = $this->createMock(ErrorReporterPortInterface::class);
            $reporter->expects(self::never())->method('report');
            $handler = new ErrorHandler(
                $registry,
                new DefaultExceptionMapper(),
                $redactor,
                $reporter,
                $correlation,
                $this->createStub(TracerPortInterface::class),
                $this->createStub(MeterPortInterface::class),
                new Stopwatch(),
                $reporting,
                ErrorSeverity::Critical,
                ['CORETSIA_UNHANDLED_EXCEPTION'],
                0,
            );
            self::assertSame(
                'CORETSIA_UNHANDLED_EXCEPTION',
                $handler->handle(new \RuntimeException('PRIVATE'))->code(),
            );
        }
    }

    public function testSpanBeginsBeforeMapperWithOperationOnlyAndEndsBeforeReporterOutsideTiming(): void
    {
        $events = [];
        $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
        $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 10000, $events);
        self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
        $source = \file_get_contents(new \ReflectionClass(ErrorHandler::class)->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('$durationMs = $this->stopwatch->stop($startedAt)', $source);
        self::assertSame([
            'span.start',
            'map',
            'redact',
            'counter:ok',
            'duration:ok',
            'span.attributes:ok',
            'span.end',
            'report',
        ], $events);
    }

    public function testMetricAndSpanFailuresDoNotStopOtherSinksOrReporting(): void
    {
        foreach (['counter', 'duration', 'span.attributes', 'span.end', 'span.start'] as $failure) {
            $events = [];
            $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
            $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 10000, $events, $failure);
            self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
            self::assertContains('map', $events);
            self::assertContains('redact', $events);
            self::assertContains('counter:ok', $events);
            self::assertContains('duration:ok', $events);
            self::assertContains('report', $events);
            if ($failure !== 'span.start') {
                self::assertSame(1, \count(\array_filter($events, static fn (string $e): bool => $e === 'span.end')));
            }
            if ($failure === 'counter') {
                self::assertContains('duration:ok', $events);
                self::assertContains('span.end', $events);
            }
        }
    }

    public function testReporterExecutesAtMostOnceEvenIfItThrows(): void
    {
        $events = [];
        $descriptor = new ErrorDescriptor('OWNER_APPROVED', 'Public reason.');
        $handler = $this->handler($descriptor, true, ErrorSeverity::Info, [], 10000, $events, 'report');
        self::assertSame($descriptor->toArray(), $handler->handle(new \RuntimeException('PRIVATE'))->toArray());
        self::assertSame(1, \count(\array_filter($events, static fn (string $e): bool => $e === 'report')));
        self::assertContains('span.end', $events);
    }

    /**
     * @param list<string> $events
     * @param list<string> $excluded
     */
    private function handler(
        ?ErrorDescriptor $candidate,
        bool $enabled,
        ErrorSeverity $minimum,
        array $excluded,
        int $sampleRate,
        array &$events,
        ?string $fail = null,
    ): ErrorHandler {
        $tags = new TagRegistry();
        if ($candidate !== null) {
            $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        }
        $mapper = $this->createStub(ExceptionMapperInterface::class);
        $mapper->method('map')->willReturnCallback(
            static function () use (&$events, $candidate): ?ErrorDescriptor {
                $events[] = 'map';
                return $candidate;
            },
        );
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnCallback(
            static function (mixed $value) use (&$events, $fail): mixed {
                $events[] = 'redact';
                if ($fail === 'redactor.malformed') {
                    $value['message'] = ['redacted' => true];
                }
                return $value;
            },
        );
        $reporter = $this->createStub(ErrorReporterPortInterface::class);
        $reporter->method('report')->willReturnCallback(
            static function (ErrorDescriptor $descriptor, ?ErrorHandlingContext $context) use (&$events, $fail): void {
                $events[] = 'report';
                if ($fail === 'report') {
                    throw new \RuntimeException('SYNTHETIC_REPORTER_FAILURE');
                }
            },
        );
        $meter = $this->createStub(MeterPortInterface::class);
        $meter->method('increment')->willReturnCallback(
            static function (string $name, int $delta, array $labels) use (&$events, $fail): void {
                self::assertSame('error.map_total', $name);
                self::assertSame(1, $delta);
                self::assertSame('map', $labels['operation']);
                $events[] = 'counter:' . $labels['outcome'];
                if ($fail === 'counter') {
                    throw new \RuntimeException('SYNTHETIC_COUNTER_FAILURE');
                }
            },
        );
        $meter->method('observe')->willReturnCallback(
            static function (string $name, int $duration, array $labels) use (&$events, $fail): void {
                self::assertSame('error.map_duration_ms', $name);
                self::assertGreaterThanOrEqual(0, $duration);
                self::assertSame('map', $labels['operation']);
                $events[] = 'duration:' . $labels['outcome'];
                if ($fail === 'duration') {
                    throw new \RuntimeException('SYNTHETIC_DURATION_FAILURE');
                }
            },
        );
        $span = $this->createStub(SpanInterface::class);
        $span->method('setAttributes')->willReturnCallback(
            static function (array $labels) use (&$events, $fail): void {
                self::assertSame('map', $labels['operation']);
                $events[] = 'span.attributes:' . $labels['outcome'];
                if ($fail === 'span.attributes') {
                    throw new \RuntimeException('SYNTHETIC_ATTRIBUTES_FAILURE');
                }
            },
        );
        $span->method('end')->willReturnCallback(
            static function () use (&$events, $fail): void {
                $events[] = 'span.end';
                if ($fail === 'span.end') {
                    throw new \RuntimeException('SYNTHETIC_SPAN_END_FAILURE');
                }
            },
        );
        $tracer = $this->createStub(TracerPortInterface::class);
        $tracer->method('startSpan')->willReturnCallback(
            static function (string $name, array $attributes) use (&$events, $span, $fail): SpanInterface {
                self::assertSame('error.map', $name);
                self::assertSame(['operation' => 'map'], $attributes);
                $events[] = 'span.start';
                if ($fail === 'span.start') {
                    throw new \RuntimeException('SYNTHETIC_SPAN_START_FAILURE');
                }
                return $span;
            },
        );
        $stopwatch = new Stopwatch();
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn(null);

        return new ErrorHandler(
            new ExceptionMapperRegistry($tags, $container, $this->createStub(LoggerInterface::class), $enabled, false),
            new DefaultExceptionMapper(),
            $redactor,
            $reporter,
            $correlations,
            $tracer,
            $meter,
            $stopwatch,
            $enabled,
            $minimum,
            $excluded,
            $sampleRate,
        );
    }
}
