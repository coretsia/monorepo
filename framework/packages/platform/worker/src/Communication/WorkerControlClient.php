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

namespace Coretsia\Platform\Worker\Communication;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Worker\Exception\WorkerCommunicationFailedException;
use Coretsia\Platform\Worker\Exception\WorkerNotRunningException;
use Coretsia\Platform\Worker\Internal\WorkerControlClientInterface;
use Coretsia\Platform\Worker\Runtime\WorkerHealthState;
use Coretsia\Platform\Worker\Runtime\WorkerLifecycleLock;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Runtime\WorkerPoolState;
use Psr\Log\LoggerInterface;

/**
 * Sends typed status, health, and terminal stop requests to the live supervisor.
 *
 * Supervisor liveness is established by WorkerLifecycleLock before connecting.
 * This client never reads the diagnostic state snapshot and never writes the
 * stop flag or any other runtime artifact.
 */
final class WorkerControlClient implements WorkerControlClientInterface
{
    private const string SPAN_WORKER_PROCESS = 'worker.process';
    private const string METRIC_WORKER_PROCESS_TOTAL = 'worker.process_total';
    private const string LOG_EVENT_WORKER_STATUS = 'worker.process.status';
    private const string STATUS_SUCCESS = 'status_success';
    private const string STATUS_FAILURE = 'status_failure';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    private int $requestCounter = 0;

    public function __construct(
        private readonly WorkerControlTransport $transport,
        private readonly WorkerControlProtocol $protocol,
        private readonly WorkerLifecycleLock $lifecycleLock,
        private readonly TracerPortInterface $tracer,
        private readonly MeterPortInterface $meter,
        private readonly LoggerInterface $logger,
        private readonly Stopwatch $stopwatch,
    ) {
    }

    public function status(
        WorkerPoolSpec $spec,
    ): WorkerPoolState {
        $span = $this->safeStartProcessSpan();
        $startedAt = $this->safeStartTimer();
        $state = null;
        $outcome = self::OUTCOME_FAILURE;

        try {
            $response = $this->request(
                $spec,
                WorkerControlOperation::STATUS,
                1000,
            );

            $state = $this->stateResult(
                $response,
                WorkerControlResponse::STATUS_OK,
            );

            $outcome = self::OUTCOME_SUCCESS;

            return $state;
        } finally {
            $status = $outcome === self::OUTCOME_SUCCESS
                ? self::STATUS_SUCCESS
                : self::STATUS_FAILURE;

            $this->safeFinishProcessSpan(
                span: $span,
                state: $state,
                outcome: $outcome,
            );

            $this->safeEmitProcessMetric($status);

            $this->safeLogStatusSummary(
                status: $status,
                outcome: $outcome,
                durationMs: $this->safeStopTimer($startedAt),
                state: $state,
            );
        }
    }

    public function health(WorkerPoolSpec $spec): WorkerHealthState
    {
        $response = $this->request($spec, WorkerControlOperation::HEALTH, 1000);
        if ($response->status() !== WorkerControlResponse::STATUS_OK) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        $result = $response->result();
        if (
            !\is_array($result)
            || !isset($result['health'])
            || !\is_array($result['health'])
            || \array_is_list($result['health'])
        ) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        try {
            return WorkerHealthState::fromArray($result['health']);
        } catch (\Throwable) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
    }

    public function stop(WorkerPoolSpec $spec): WorkerPoolState
    {
        $timeout = $spec->stopTimeoutMs() + (2 * $spec->forceKillTimeoutMs()) + 2000;
        $response = $this->request($spec, WorkerControlOperation::STOP, $timeout);

        return $this->stateResult($response, WorkerControlResponse::STATUS_STOPPED);
    }

    private function request(
        WorkerPoolSpec $spec,
        WorkerControlOperation $operation,
        int $timeoutMs
    ): WorkerControlResponse {
        if (!$this->lifecycleLock->isHeld($spec)) {
            throw WorkerNotRunningException::notRunning();
        }
        $connection = null;
        $request = new WorkerControlRequest($operation, $this->nextRequestId());
        try {
            $connection = $this->transport->connect($spec, \min(1000, $timeoutMs));
            @\stream_set_timeout($connection, intdiv($timeoutMs, 1000), ($timeoutMs % 1000) * 1000);
            $this->transport->writeFrame($connection, $this->protocol->encodeRequest($request));
            $response = $this->protocol->decodeResponse(
                $this->transport->readFrame($connection, WorkerControlProtocol::MAX_FRAME_BYTES),
            );
            if (
                $response->requestId() !== $request->requestId()
                || $response->status() === WorkerControlResponse::STATUS_ERROR
            ) {
                throw WorkerCommunicationFailedException::communicationFailed();
            }
            return $response;
        } catch (WorkerCommunicationFailedException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw WorkerCommunicationFailedException::communicationFailed();
        } finally {
            if (\is_resource($connection)) {
                $this->transport->close($connection);
            }
        }
    }

    private function stateResult(WorkerControlResponse $response, string $expectedStatus): WorkerPoolState
    {
        if ($response->status() !== $expectedStatus) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        $result = $response->result();
        if (
            !\is_array($result)
            || !isset($result['state'])
            || !\is_array($result['state'])
            || \array_is_list($result['state'])
        ) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        try {
            return WorkerPoolState::fromArray($result['state']);
        } catch (\Throwable) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
    }

    private function safeStartProcessSpan(): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(self::SPAN_WORKER_PROCESS);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFinishProcessSpan(
        ?SpanInterface $span,
        ?WorkerPoolState $state,
        string $outcome,
    ): void {
        if ($span === null) {
            return;
        }

        $attributes = [
            'outcome' => $outcome,
        ];

        if ($state !== null) {
            $attributes['pid'] = $state->pid();
        }

        try {
            $span->setAttributes($attributes);
        } catch (\Throwable) {
        }

        try {
            $span->end();
        } catch (\Throwable) {
        }
    }

    private function safeEmitProcessMetric(
        string $status,
    ): void {
        try {
            $this->meter->increment(
                self::METRIC_WORKER_PROCESS_TOTAL,
                1,
                [
                    'status' => $status,
                ],
            );
        } catch (\Throwable) {
        }
    }

    private function safeLogStatusSummary(
        string $status,
        string $outcome,
        int $durationMs,
        ?WorkerPoolState $state,
    ): void {
        $context = [
            'status' => $status,
            'outcome' => $outcome,
            'duration_ms' => $durationMs,
        ];

        if ($state !== null) {
            $context = [
                ...$context,
                'pid' => $state->pid(),
                'worker_count' => $state->workerCount(),
                'driver' => $state->driver(),
                'control_transport' => $state->controlTransport(),
                'endpoint_hash' => $state->endpointHash(),
            ];
        }

        try {
            $this->logger->info(
                self::LOG_EVENT_WORKER_STATUS,
                $context,
            );
        } catch (\Throwable) {
        }
    }

    private function safeStartTimer(): mixed
    {
        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeStopTimer(
        mixed $startedAt,
    ): int {
        if (!\is_int($startedAt) || $startedAt <= 0) {
            return 0;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0
                ? $durationMs
                : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function nextRequestId(): string
    {
        $this->requestCounter++;
        return 'request-' . (string)\getmypid() . '-' . (string)$this->requestCounter;
    }
}
