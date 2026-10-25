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

namespace Coretsia\Platform\Errors;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Throwable;

/**
 * Format-neutral, fail-closed Throwable normalization boundary.
 *
 * Mapping and mandatory redaction run regardless of optional reporting.
 * Every context, span, timing token, and selected descriptor is invocation-local.
 * This handler does not own Kernel UnitOfWork, runtime rendering, or resets.
 */
final readonly class ErrorHandler implements ErrorHandlerInterface
{
    /**
     * @param list<non-empty-string> $excludeCodes
     */
    public function __construct(
        private ExceptionMapperRegistry $registry,
        private DefaultExceptionMapper $fallback,
        private SensitiveDataRedactorInterface $redactor,
        private ErrorReporterPortInterface $reporter,
        private CorrelationIdProviderInterface $correlations,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private Stopwatch $stopwatch,
        private bool $reportingEnabled,
        private ErrorSeverity $minSeverity,
        private array $excludeCodes,
        private int $sampleRate,
    ) {
    }

    public function handle(Throwable $throwable, ?ErrorHandlingContext $context = null): ErrorDescriptor
    {
        $correlationId = null;

        try {
            $correlationId = $this->correlations->correlationId();
        } catch (Throwable) {
            // Missing correlation enrichment is not a normalization failure.
        }

        $mappingContext = $context;

        if ($mappingContext === null) {
            try {
                $mappingContext = new ErrorHandlingContext(
                    operation: 'map',
                    correlationId: $correlationId,
                    metadata: [],
                );
            } catch (Throwable) {
                $correlationId = null;

                try {
                    $mappingContext = new ErrorHandlingContext(
                        operation: 'map',
                        correlationId: null,
                        metadata: [],
                    );
                } catch (Throwable) {
                    $mappingContext = null;
                }
            }
        }

        $startedAt = null;
        $span = null;

        if ($this->reportingEnabled) {
            try {
                $startedAt = $this->stopwatch->start();
            } catch (Throwable) {
                // Optional timing cannot prevent mapper dispatch.
            }

            try {
                $span = $this->tracer->startSpan('error.map', [
                    'operation' => 'map',
                ]);
            } catch (Throwable) {
                // Optional tracing cannot prevent mapper dispatch.
            }
        }

        $outcome = 'fallback';

        try {
            try {
                $candidate = $this->registry->map($throwable, $mappingContext);
            } catch (Throwable) {
                // Registry-level failures must not retry mapper discovery.
                $candidate = null;
            }

            $registeredMapperSelected = $candidate !== null;

            if ($candidate === null) {
                $candidate = $this->fallbackDescriptor($throwable, $mappingContext);
            }

            try {
                $redacted = $this->redactor->redactJsonLike(
                    $candidate->toArray(),
                    new RedactionContext(
                        scope: 'errors.descriptor',
                        mode: RedactionMode::Placeholder,
                    ),
                );

                if (!is_array($redacted)) {
                    $descriptor = $this->fallbackDescriptor($throwable, $mappingContext);
                } else {
                    $descriptor = ErrorDescriptor::fromArray($redacted);
                    $outcome = $registeredMapperSelected ? 'ok' : 'fallback';
                }
            } catch (Throwable) {
                // Do not return the candidate or re-enter failing redaction.
                $descriptor = $this->fallbackDescriptor($throwable, $mappingContext);
            }
        } catch (Throwable) {
            // Even unexpected normalization failures use the fixed safe fallback.
            $descriptor = $this->fallbackDescriptor($throwable, $mappingContext);
        } finally {
            if ($this->reportingEnabled) {
                $durationMs = 0;

                if ($startedAt !== null) {
                    try {
                        $durationMs = $this->stopwatch->stop($startedAt);
                    } catch (Throwable) {
                        // A failed Stopwatch read uses the safe zero duration.
                    }
                }

                $labels = [
                    'operation' => 'map',
                    'outcome' => $outcome,
                ];

                try {
                    $this->meter->increment('error.map_total', 1, $labels);
                } catch (Throwable) {
                    // Counter failures do not suppress duration observations.
                }

                try {
                    $this->meter->observe('error.map_duration_ms', $durationMs, $labels);
                } catch (Throwable) {
                    // Duration failures do not suppress span finalization.
                }

                if ($span !== null) {
                    try {
                        $span->setAttributes($labels);
                    } catch (Throwable) {
                        // Final attributes are optional and independently isolated.
                    }

                    try {
                        $span->end();
                    } catch (Throwable) {
                        // End is attempted exactly once for every created span.
                    }
                }
            }
        }

        if ($this->reportingEnabled && $this->isReporterEligible($descriptor)) {
            $reportingContext = null;

            try {
                $reportingContext = new ErrorHandlingContext(
                    operation: 'map',
                    correlationId: $correlationId,
                    metadata: [],
                );
            } catch (Throwable) {
                $correlationId = null;

                try {
                    $reportingContext = new ErrorHandlingContext(
                        operation: 'map',
                        correlationId: null,
                        metadata: [],
                    );
                } catch (Throwable) {
                    // A null context is safe under the reporter port contract.
                }
            }

            try {
                $this->reporter->report($descriptor, $reportingContext);
            } catch (Throwable) {
                // Reporter errors must not affect the normalized result.
            }
        }

        return $descriptor;
    }

    /**
     * DefaultExceptionMapper is the sole owner of the fixed safe fallback.
     */
    private function fallbackDescriptor(Throwable $throwable, ?ErrorHandlingContext $context): ErrorDescriptor
    {
        $descriptor = $this->fallback->map($throwable, $context);

        return $descriptor;
    }

    /**
     * Reporter-only filtering; must never modify mapping or telemetry outcome.
     */
    private function isReporterEligible(ErrorDescriptor $descriptor): bool
    {
        try {
            $rank = static fn (ErrorSeverity $severity): int => match ($severity) {
                ErrorSeverity::Info => 0,
                ErrorSeverity::Warning => 1,
                ErrorSeverity::Error => 2,
                ErrorSeverity::Critical => 3,
            };

            if ($rank($descriptor->severity()) < $rank($this->minSeverity)) {
                return false;
            }

            if (in_array($descriptor->code(), $this->excludeCodes, true)) {
                return false;
            }

            if ($this->sampleRate === 0) {
                return false;
            }

            if ($this->sampleRate === 10000) {
                return true;
            }

            return random_int(0, 9999) < $this->sampleRate;
        } catch (Throwable) {
            // Randomness and policy evaluation may only suppress reporting.
            return false;
        }
    }
}
