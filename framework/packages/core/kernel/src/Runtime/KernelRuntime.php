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

namespace Coretsia\Kernel\Runtime;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookContextNormalizer;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned UnitOfWork lifecycle runtime.
 *
 * KernelRuntime is the format-neutral orchestrator used by external runtime
 * adapters. It owns UnitOfWork context creation, base context key writes,
 * hook invocation, result export, and reset orchestration.
 *
 * Diagnostics are intentionally stable and safe. Runtime validation failures
 * surface KernelRuntimeException messages that contain only the package error
 * code and stable reason token. This class must not log, dump, or expose raw
 * context arrays, hook payloads, transport payloads, tokens, cookies, raw SQL,
 * object dumps, local paths, environment-specific values, or stack traces.
 */
final readonly class KernelRuntime implements KernelRuntimeInterface
{
    private const string ERROR_DESCRIPTOR_CODE = 'coretsia.kernel.runtime.error';
    private const string ERROR_DESCRIPTOR_MESSAGE = 'kernel-runtime-error';

    private const int TIMER_UNAVAILABLE = 0;

    public function __construct(
        private ContextStore $contextStore,
        private ResetOrchestrator $resetOrchestrator,
        private Stopwatch $stopwatch,
        private IdGeneratorInterface $uowIds,
        private CorrelationIdProviderInterface $correlationIdProvider,
        private CorrelationIdGenerator $correlationIds,
        private HookInvoker $hooks,
        private LoggerInterface $logger,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
    ) {
    }

    /**
     * Runs an external body inside Kernel-owned UnitOfWork lifecycle handling.
     *
     * @param array<string, mixed> $attributes
     */
    public function runUnitOfWork(
        string $type,
        callable $body,
        array $attributes = [],
    ): mixed {
        $context = null;
        $bodyResult = null;
        $primaryFailure = null;
        $resetRequired = false;
        $afterPhaseRequired = false;

        try {
            $context = $this->createUnitOfWorkContext($type, $attributes);
            $resetRequired = true;

            $this->writeBaseContextKeys($context);

            $contextPayload = HookContextNormalizer::normalizeContext($context);
            $afterPhaseRequired = true;

            $this->hooks->invokeBeforeHooks($contextPayload);

            $primaryFailure = $this->runBodyAndCaptureFailure($body, $bodyResult);
        } catch (\Throwable $throwable) {
            $primaryFailure = $throwable;
        }

        if ($context !== null && $afterPhaseRequired) {
            $primaryFailure = $this->runAfterPhaseAndSelectFailure($context, $primaryFailure);
        }

        if ($resetRequired) {
            $failure = $this->resetAndSelectFailure($primaryFailure);

            if ($failure !== null) {
                throw $failure;
            }

            return $bodyResult;
        }

        if ($primaryFailure !== null) {
            throw $primaryFailure;
        }

        return $bodyResult;
    }

    /**
     * Begins a UnitOfWork and returns the normalized exported context array.
     *
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public function beginUnitOfWork(
        string $type,
        array $attributes = [],
    ): array {
        $context = null;
        $resetRequired = false;

        try {
            $context = $this->createUnitOfWorkContext($type, $attributes);
            $resetRequired = true;

            $this->writeBaseContextKeys($context);

            $contextPayload = HookContextNormalizer::normalizeContext($context);

            $this->hooks->invokeBeforeHooks($contextPayload);

            return $contextPayload;
        } catch (\Throwable $throwable) {
            if ($resetRequired) {
                $failure = $this->resetAndSelectFailure($throwable);

                if ($failure !== null) {
                    throw $failure;
                }
            }

            throw $throwable;
        }
    }

    /**
     * Completes a previously begun UnitOfWork and returns exported result data.
     *
     * @param array<string, mixed> $context
     * @param array<string, mixed> $extensions
     *
     * @return array<string, mixed>
     */
    public function afterUnitOfWork(
        array $context,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        $primaryFailure = null;
        $resultPayload = null;

        try {
            $unitOfWorkContext = $this->contextFromExport($context);

            $resultPayload = $this->runAfterPhase(
                context: $unitOfWorkContext,
                outcome: $outcome,
                error: $error,
                extensions: $extensions,
            );
        } catch (\Throwable $throwable) {
            $primaryFailure = $throwable;
        }

        $failure = $this->resetAndSelectFailure($primaryFailure);

        if ($failure !== null) {
            throw $failure;
        }

        if ($resultPayload === null) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_RESULT,
            );
        }

        return $resultPayload;
    }

    /**
     * Executes the UnitOfWork body and captures its primary failure.
     *
     * @param mixed $bodyResult
     */
    private function runBodyAndCaptureFailure(callable $body, mixed &$bodyResult): ?\Throwable
    {
        try {
            $bodyResult = $body();

            return null;
        } catch (\Throwable $throwable) {
            return $throwable;
        }
    }

    /**
     * Runs the after phase and preserves Kernel failure precedence.
     *
     * If a primary failure already exists, an after-phase failure is suppressed
     * and the original primary failure remains the surfaced failure.
     */
    private function runAfterPhaseAndSelectFailure(
        UnitOfWorkContext $context,
        ?\Throwable $primaryFailure,
    ): ?\Throwable {
        try {
            $this->runAfterPhase(
                context: $context,
                outcome: $primaryFailure === null ? Outcome::SUCCESS : Outcome::FATAL_ERROR,
                error: $primaryFailure,
                extensions: [],
            );
        } catch (\Throwable $throwable) {
            if ($primaryFailure === null) {
                return $throwable;
            }
        }

        return $primaryFailure;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function createUnitOfWorkContext(
        string $type,
        array $attributes,
    ): UnitOfWorkContext {
        if (!UnitOfWorkType::isValid($type)) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_TYPE,
            );
        }

        try {
            return new UnitOfWorkContext(
                uowId: $this->uowIds->generate(),
                type: $type,
                startedAt: $this->safeStartTimer(),
                correlationId: $this->correlationId(),
                attributes: $attributes,
            );
        } catch (KernelRuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
                $throwable,
            );
        }
    }

    private function correlationId(): string
    {
        $correlationId = $this->correlationIdProvider->correlationId();

        if ($correlationId !== null) {
            return $correlationId;
        }

        return $this->correlationIds->generate();
    }

    private function writeBaseContextKeys(UnitOfWorkContext $context): void
    {
        try {
            $this->contextStore->set(ContextKeys::CORRELATION_ID, $context->correlationId());
            $this->contextStore->set(ContextKeys::UOW_ID, $context->uowId());
            $this->contextStore->set(ContextKeys::UOW_TYPE, $context->type());
        } catch (\Throwable $throwable) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
                $throwable,
            );
        }
    }

    /**
     * @param array<string, mixed> $extensions
     *
     * @return array<string, mixed>
     */
    private function runAfterPhase(
        UnitOfWorkContext $context,
        string $outcome,
        ?\Throwable $error,
        array $extensions,
    ): array {
        if (!Outcome::isValid($outcome)) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_OUTCOME,
            );
        }

        $result = $this->createUnitOfWorkResult(
            context: $context,
            outcome: $outcome,
            error: $error,
            extensions: $extensions,
        );

        $contextPayload = HookContextNormalizer::normalizeContext($context);
        $resultPayload = HookContextNormalizer::normalizeResult($result);

        try {
            $this->hooks->invokeAfterHooks($contextPayload, $resultPayload);
        } catch (\Throwable $throwable) {
            $this->emitLifecycleSummary($resultPayload, $throwable);

            throw $throwable;
        }

        $this->emitLifecycleSummary($resultPayload);

        return $resultPayload;
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private function createUnitOfWorkResult(
        UnitOfWorkContext $context,
        string $outcome,
        ?\Throwable $error,
        array $extensions,
    ): UnitOfWorkResult {
        try {
            return UnitOfWorkResult::fromContext(
                context: $context,
                finishedAt: $this->safeStartTimer(),
                durationMs: $this->safeStopTimer($context->startedAt()),
                outcome: $outcome,
                error: $this->errorDescriptor($error),
                extensions: $extensions,
            );
        } catch (KernelRuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_RESULT,
                $throwable,
            );
        }
    }

    private function safeStartTimer(): int
    {
        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return self::TIMER_UNAVAILABLE;
        }
    }

    private function safeStopTimer(int $startedAt): int
    {
        if ($startedAt <= self::TIMER_UNAVAILABLE) {
            return 0;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0 ? $durationMs : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function errorDescriptor(?\Throwable $error): ?ErrorDescriptor
    {
        if ($error === null) {
            return null;
        }

        return new ErrorDescriptor(
            code: self::ERROR_DESCRIPTOR_CODE,
            message: self::ERROR_DESCRIPTOR_MESSAGE,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function contextFromExport(array $context): UnitOfWorkContext
    {
        try {
            foreach (['uowId', 'type', 'startedAt', 'correlationId', 'attributes'] as $requiredKey) {
                if (!\array_key_exists($requiredKey, $context)) {
                    throw KernelRuntimeException::withReason(
                        KernelRuntimeException::REASON_INVALID_CONTEXT,
                    );
                }
            }

            if (
                !\is_string($context['uowId'])
                || !\is_string($context['type'])
                || !\is_int($context['startedAt'])
                || !\is_string($context['correlationId'])
                || !\is_array($context['attributes'])
            ) {
                throw KernelRuntimeException::withReason(
                    KernelRuntimeException::REASON_INVALID_CONTEXT,
                );
            }

            if ($context['startedAt'] < 0) {
                throw KernelRuntimeException::withReason(
                    KernelRuntimeException::REASON_INVALID_CONTEXT,
                );
            }

            return new UnitOfWorkContext(
                uowId: $context['uowId'],
                type: $context['type'],
                startedAt: $context['startedAt'],
                correlationId: $context['correlationId'],
                attributes: $context['attributes'],
            );
        } catch (KernelRuntimeException $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
                $throwable,
            );
        }
    }

    /**
     * Emits safe Kernel UnitOfWork lifecycle summary telemetry.
     *
     * The payload is intentionally summary-only:
     *
     * - no uow id;
     * - no correlation id;
     * - no context array;
     * - no hook payload;
     * - no transport payload;
     * - no throwable message;
     * - no stack trace;
     * - no local path.
     *
     * Observability port failures are swallowed so telemetry cannot change
     * KernelRuntime lifecycle failure precedence.
     *
     * @param array<string, mixed> $resultPayload
     */
    private function emitLifecycleSummary(
        array $resultPayload,
        ?\Throwable $failure = null,
    ): void {
        $durationMs = self::durationMsFromResult($resultPayload);
        $operation = self::operationFromResult($resultPayload);
        $outcome = self::outcomeForLifecycleSummary($resultPayload, $failure);

        $labels = [
            'operation' => $operation,
            'outcome' => $outcome,
        ];

        try {
            $span = $this->tracer->startSpan('kernel.uow', $labels);

            try {
                $span->setAttributes($labels);
            } finally {
                $span->end();
            }
        } catch (\Throwable) {
        }

        try {
            $this->meter->increment('kernel.uow_total', 1, $labels);
            $this->meter->observe('kernel.uow_duration_ms', $durationMs, $labels);
        } catch (\Throwable) {
        }

        try {
            $this->logger->info('kernel.uow', [
                'duration_ms' => $durationMs,
                'operation' => $operation,
                'outcome' => $outcome,
            ]);
        } catch (\Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $resultPayload
     */
    private static function durationMsFromResult(array $resultPayload): int
    {
        $durationMs = $resultPayload['durationMs'] ?? 0;

        if (!\is_int($durationMs) || $durationMs < 0) {
            return 0;
        }

        return $durationMs;
    }

    /**
     * @param array<string, mixed> $resultPayload
     */
    private static function operationFromResult(array $resultPayload): string
    {
        $type = $resultPayload['type'] ?? null;

        if (\is_string($type) && UnitOfWorkType::isValid($type)) {
            return $type;
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $resultPayload
     */
    private static function outcomeForLifecycleSummary(
        array $resultPayload,
        ?\Throwable $failure,
    ): string {
        if ($failure !== null) {
            return Outcome::FATAL_ERROR;
        }

        $outcome = $resultPayload['outcome'] ?? null;

        if (\is_string($outcome) && Outcome::isValid($outcome)) {
            return $outcome;
        }

        return Outcome::FATAL_ERROR;
    }

    /**
     * Runs reset orchestration and returns the failure that must be surfaced.
     *
     * Failure precedence is Kernel-owned:
     *
     * - primary lifecycle/body failure wins;
     * - reset failure is surfaced only when there is no primary failure.
     */
    private function resetAndSelectFailure(?\Throwable $primaryFailure): ?\Throwable
    {
        $resetFailure = $this->resetFailure();

        if ($primaryFailure !== null) {
            return $primaryFailure;
        }

        return $resetFailure;
    }

    private function resetFailure(): ?KernelRuntimeException
    {
        try {
            $this->resetOrchestrator->resetAll();

            return null;
        } catch (\Throwable $throwable) {
            return KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_RESET_FAILED,
                $throwable,
            );
        }
    }
}
