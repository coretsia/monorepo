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

namespace Coretsia\Foundation\Runtime\Reset;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Enhanced deterministic reset planner/executor.
 *
 * This class implements only enhanced reset ordering:
 *
 * 1. priority DESC
 * 2. group ASC by strcmp()
 * 3. serviceId ASC by strcmp()
 *
 * It intentionally does not know about `foundation.reset.priority.enabled`.
 * The stable public ResetOrchestrator entrypoint owns mode selection and may
 * delegate here only when enhanced priority/group planning is enabled.
 *
 * This class MUST NOT emit stdout/stderr, log per-service internals, dump tag
 * metadata, dump service instances, or expose payloads, secrets, headers,
 * cookies, Authorization values, tokens, session ids, absolute paths, or raw
 * context values.
 */
final readonly class PriorityResetOrchestrator
{
    private const string ASCII_WHITESPACE = " \t\n\r\f\v";

    private const string SPAN_NAME = 'foundation.reset';

    private const string METRIC_RESET_TOTAL = 'foundation.reset_total';

    private const string METRIC_RESET_DURATION_MS = 'foundation.reset_duration_ms';

    private const string OUTCOME_OK = 'ok';

    private const string OUTCOME_FAILED = 'failed';

    private ResetGroup $defaultGroup;

    public function __construct(
        private ContainerInterface $container,
        private TagRegistry $tagRegistry,
        string $defaultGroup,
        private Stopwatch $stopwatch,
        private ?TracerPortInterface $tracer = null,
        private ?MeterPortInterface $meter = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->defaultGroup = ResetGroup::fromString($defaultGroup);
    }

    /**
     * Executes enhanced reset for all services registered under the effective
     * Foundation reset discovery tag.
     *
     * Empty discovery list is a deterministic noop with summary observability.
     */
    public function resetAll(string $effectiveResetTag): void
    {
        $startedAt = $this->stopwatch->start();

        $servicesCount = 0;
        $groupsCount = 0;
        $span = null;
        $failure = null;

        try {
            $taggedServices = $this->tagRegistry->all($effectiveResetTag);
            $servicesCount = \count($taggedServices);

            $span = $this->startSpan($servicesCount, $groupsCount);

            $plan = $this->plan($taggedServices);
            $groupsCount = self::groupsCount($plan);

            $this->executePlan($plan);
        } catch (ResetException $exception) {
            $failure = $exception;

            throw $exception;
        } finally {
            $durationMs = $this->stopwatch->stop($startedAt);
            $outcome = $failure === null ? self::OUTCOME_OK : self::OUTCOME_FAILED;

            try {
                $this->emitObservabilitySummary(
                    span: $span,
                    servicesCount: $servicesCount,
                    groupsCount: $groupsCount,
                    outcome: $outcome,
                    durationMs: $durationMs,
                    failure: $failure,
                );
            } catch (\Throwable $exception) {
                if ($failure === null) {
                    throw ResetException::observabilityFailed($exception);
                }
            }
        }
    }

    /**
     * @param list<TaggedService> $taggedServices
     *
     * @return list<array{
     *     serviceId: string,
     *     taggedService: TaggedService,
     *     priority: ResetPriority,
     *     group: ResetGroup
     * }>
     */
    private function plan(array $taggedServices): array
    {
        $planned = [];

        foreach ($taggedServices as $taggedService) {
            $planned[] = [
                'serviceId' => $taggedService->id(),
                'taggedService' => $taggedService,
                'priority' => self::resolvePriority($taggedService),
                'group' => $this->resolveGroup($taggedService),
            ];
        }

        \usort(
            $planned,
            static function (array $left, array $right): int {
                $priority = $left['priority']->compareDescending($right['priority']);
                if ($priority !== 0) {
                    return $priority;
                }

                $group = $left['group']->compare($right['group']);
                if ($group !== 0) {
                    return $group;
                }

                return \strcmp($left['serviceId'], $right['serviceId']);
            },
        );

        return $planned;
    }

    /**
     * @param list<array{
     *     serviceId: string,
     *     taggedService: TaggedService,
     *     priority: ResetPriority,
     *     group: ResetGroup
     * }> $plan
     */
    private function executePlan(array $plan): void
    {
        foreach ($plan as $entry) {
            $serviceId = $entry['serviceId'];

            try {
                $service = $this->container->get($serviceId);
            } catch (\Throwable $exception) {
                throw ResetException::serviceFailed($exception);
            }

            if (!$service instanceof ResetInterface) {
                throw ResetException::serviceNotResettable();
            }

            try {
                $service->reset();
            } catch (\Throwable $exception) {
                throw ResetException::serviceFailed($exception);
            }
        }
    }

    private static function resolvePriority(TaggedService $taggedService): ResetPriority
    {
        $meta = $taggedService->meta();

        if (!\array_key_exists('priority', $meta)) {
            return ResetPriority::fromInt($taggedService->priority());
        }

        return ResetPriority::fromMetaValue($meta['priority']);
    }

    private function resolveGroup(TaggedService $taggedService): ResetGroup
    {
        $meta = $taggedService->meta();

        if (!\array_key_exists('group', $meta)) {
            return $this->defaultGroup;
        }

        $rawGroup = $meta['group'];

        if (!\is_string($rawGroup)) {
            throw ResetException::metaInvalid();
        }

        if (\trim($rawGroup, self::ASCII_WHITESPACE) === '') {
            return $this->defaultGroup;
        }

        return ResetGroup::fromString($rawGroup);
    }

    private function startSpan(int $servicesCount, int $groupsCount): ?SpanInterface
    {
        if ($this->tracer === null) {
            return null;
        }

        try {
            return $this->tracer->startSpan(
                self::SPAN_NAME,
                [
                    'services_count' => $servicesCount,
                    'groups_count' => $groupsCount,
                    'outcome' => self::OUTCOME_OK,
                ],
            );
        } catch (\Throwable $exception) {
            throw ResetException::observabilityFailed($exception);
        }
    }

    private function emitObservabilitySummary(
        ?SpanInterface $span,
        int $servicesCount,
        int $groupsCount,
        string $outcome,
        int $durationMs,
        ?ResetException $failure,
    ): void {
        $attributes = [
            'services_count' => $servicesCount,
            'groups_count' => $groupsCount,
            'outcome' => $outcome,
        ];

        if ($span !== null) {
            $span->setAttributes($attributes);

            if ($failure !== null) {
                $span->recordException(
                    $failure,
                    [
                        'outcome' => self::OUTCOME_FAILED,
                    ],
                );
            }

            $span->end();
        }

        if ($this->meter !== null) {
            $labels = [
                'outcome' => $outcome,
            ];

            $this->meter->increment(self::METRIC_RESET_TOTAL, 1, $labels);
            $this->meter->observe(self::METRIC_RESET_DURATION_MS, $durationMs, $labels);
        }

        if ($this->logger !== null) {
            $this->logger->info(
                self::SPAN_NAME,
                [
                    'services_count' => $servicesCount,
                    'groups_count' => $groupsCount,
                    'outcome' => $outcome,
                ],
            );
        }
    }

    /**
     * @param list<array{
     *     serviceId: string,
     *     taggedService: TaggedService,
     *     priority: ResetPriority,
     *     group: ResetGroup
     * }> $plan
     */
    private static function groupsCount(array $plan): int
    {
        $groups = [];

        foreach ($plan as $entry) {
            $groups[$entry['group']->value()] = true;
        }

        return \count($groups);
    }
}
