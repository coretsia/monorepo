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
use Coretsia\Contracts\Runtime\UnitOfWorkHandle;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookContextNormalizer;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\Internal\UnitOfWorkLifecycleGate;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned UnitOfWork lifecycle runtime.
 *
 * KernelRuntime is the format-neutral orchestrator used by external runtime
 * adapters. It owns UnitOfWork context creation, base context key writes,
 * hook invocation, result export, and reset orchestration.
 *
 * The class is shallow-readonly: dependency and state-holder references are
 * fixed after construction.
 *
 * The privately owned UnitOfWorkLifecycleGate maintains per-KernelRuntime
 * single-active-UnitOfWork state.
 *
 * The privately owned WeakMap maintains exact open low-level handle identity,
 * one-shot completion state, and the private Stopwatch token associated with
 * each open UnitOfWorkHandle.
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

    private UnitOfWorkLifecycleGate $unitOfWorkLifecycleGate;

    /**
     * Canonical registry of exact open low-level lifecycle handles. Presence of
     * the exact handle means it may still be completed; consuming it makes
     * afterUnitOfWork() one-shot. The mapped value is the private Stopwatch
     * token associated with that handle.
     *
     * UnitOfWorkLifecycleGate is the sole owner of runtime-wide single-active
     * UnitOfWork exclusivity. This WeakMap does not determine whether the
     * KernelRuntime as a whole currently has an active UnitOfWork.
     *
     * Stopwatch tokens are never copied into UnitOfWorkHandle::context() or any
     * exported hook, result, observability, diagnostic, or persistence payload.
     *
     * @var \WeakMap<UnitOfWorkHandle, int>
     */
    private \WeakMap $openUnitOfWorkStartTokens;

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
        private int $attributesMaxDepth,
        private int $attributesMaxKeys,
    ) {
        $this->unitOfWorkLifecycleGate = new UnitOfWorkLifecycleGate();
        $this->openUnitOfWorkStartTokens = new \WeakMap();

        if ($attributesMaxDepth < 1) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_UOW_ATTRIBUTES_MAX_DEPTH_INVALID,
            );
        }

        if ($attributesMaxKeys < 1) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_UOW_ATTRIBUTES_MAX_KEYS_INVALID,
            );
        }
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
        $this->unitOfWorkLifecycleGate->acquire();

        try {
            $context = null;
            $bodyResult = null;
            $primaryFailure = null;
            $resetRequired = false;
            $afterPhaseRequired = false;

            try {
                $context = $this->createUnitOfWorkContextAndWriteBaseKeys($type, $attributes);
                $resetRequired = true;

                $contextPayload = HookContextNormalizer::normalizeContext($context);

                $this->hooks->invokeBeforeHooks($contextPayload);

                $afterPhaseRequired = true;
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
        } finally {
            $this->unitOfWorkLifecycleGate->release();
        }
    }

    /**
     * Begins a UnitOfWork and returns an opaque lifecycle handle.
     *
     * @param array<string, mixed> $attributes
     */
    public function beginUnitOfWork(
        string $type,
        array $attributes = [],
    ): UnitOfWorkHandle {
        $this->unitOfWorkLifecycleGate->acquire();

        $releaseGate = true;
        $context = null;
        $resetRequired = false;

        try {
            $context = $this->createUnitOfWorkContextAndWriteBaseKeys($type, $attributes);
            $resetRequired = true;

            // Normalization exports only attributes, correlationId, type, and uowId.
            // Internal startedAtToken state is intentionally omitted from this payload.
            $exportedContext = HookContextNormalizer::normalizeContext($context);

            $this->hooks->invokeBeforeHooks($exportedContext);

            $handle = new UnitOfWorkHandle($exportedContext);

            // Keep private timing state associated by handle identity rather than
            // exporting it through the contracts-owned handle context.
            $this->openUnitOfWorkStartTokens->offsetSet(
                $handle,
                $context->startedAtToken(),
            );

            // The exact handle is now the canonical open low-level lifecycle
            // handle. Completion ownership transfers to the caller only after
            // successful registration.
            $releaseGate = false;

            return $handle;
        } catch (\Throwable $throwable) {
            if ($resetRequired) {
                $failure = $this->resetAndSelectFailure($throwable);

                if ($failure !== null) {
                    throw $failure;
                }
            }

            throw $throwable;
        } finally {
            if ($releaseGate) {
                $this->unitOfWorkLifecycleGate->release();
            }
        }
    }

    /**
     * Completes a previously begun UnitOfWork and returns exported result data.
     *
     * @param array<string, mixed> $extensions
     *
     * @return array<string, mixed>
     */
    public function afterUnitOfWork(
        UnitOfWorkHandle $handle,
        string $outcome,
        ?\Throwable $error = null,
        array $extensions = [],
    ): array {
        $startedAtToken = $this->consumeStartedAtTokenForHandle($handle);

        try {
            $primaryFailure = null;
            $resultPayload = null;

            try {
                $unitOfWorkContext = $this->contextFromHandle($handle, $startedAtToken);

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
        } finally {
            $this->unitOfWorkLifecycleGate->release();
        }
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
     * Creates the UnitOfWork context and writes the base ContextStore keys.
     *
     * Reset responsibility starts only after this method returns successfully.
     *
     * @param array<string, mixed> $attributes
     */
    private function createUnitOfWorkContextAndWriteBaseKeys(
        string $type,
        array $attributes,
    ): UnitOfWorkContext {
        $context = $this->createUnitOfWorkContext($type, $attributes);

        $this->writeBaseContextKeys($context);

        return $context;
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
                startedAtToken: $this->safeStartTimer(),
                correlationId: $this->correlationId(),
                attributes: $attributes,
                attributesMaxDepth: $this->attributesMaxDepth,
                attributesMaxKeys: $this->attributesMaxKeys,
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
                durationMs: $this->safeStopTimer($context->startedAtToken()),
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

    private function consumeStartedAtTokenForHandle(UnitOfWorkHandle $handle): int
    {
        if (!$this->openUnitOfWorkStartTokens->offsetExists($handle)) {
            throw KernelRuntimeException::withReason(
                KernelRuntimeException::REASON_INVALID_CONTEXT,
            );
        }

        /** @var int $startedAtToken */
        $startedAtToken = $this->openUnitOfWorkStartTokens->offsetGet($handle);

        $this->openUnitOfWorkStartTokens->offsetUnset($handle);

        return $startedAtToken;
    }

    /**
     * @param int<0, max> $startedAtToken
     */
    private function contextFromHandle(
        UnitOfWorkHandle $handle,
        int $startedAtToken,
    ): UnitOfWorkContext {
        try {
            $context = $handle->context();

            foreach (['uowId', 'type', 'correlationId', 'attributes'] as $requiredKey) {
                if (!\array_key_exists($requiredKey, $context)) {
                    throw KernelRuntimeException::withReason(
                        KernelRuntimeException::REASON_INVALID_CONTEXT,
                    );
                }
            }

            foreach (['startedAt', 'startedAtToken', 'finishedAt'] as $forbiddenKey) {
                if (\array_key_exists($forbiddenKey, $context)) {
                    throw KernelRuntimeException::withReason(
                        KernelRuntimeException::REASON_INVALID_CONTEXT,
                    );
                }
            }

            if (
                !\is_string($context['uowId'])
                || !\is_string($context['type'])
                || !\is_string($context['correlationId'])
                || !\is_array($context['attributes'])
            ) {
                throw KernelRuntimeException::withReason(
                    KernelRuntimeException::REASON_INVALID_CONTEXT,
                );
            }

            if ($startedAtToken < 0) {
                throw KernelRuntimeException::withReason(
                    KernelRuntimeException::REASON_INVALID_CONTEXT,
                );
            }

            return new UnitOfWorkContext(
                uowId: $context['uowId'],
                type: $context['type'],
                startedAtToken: $startedAtToken,
                correlationId: $context['correlationId'],
                attributes: $context['attributes'],
                attributesMaxDepth: $this->attributesMaxDepth,
                attributesMaxKeys: $this->attributesMaxKeys,
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
     * Runs reset orchestration and selects the exact failure to surface.
     *
     * Reset is attempted before failure selection.
     *
     * Failure precedence is Kernel-owned:
     *
     * - an existing primary lifecycle, hook, or body failure is returned unchanged;
     * - a reset failure never replaces, wraps, or mutates an existing primary failure;
     * - a safe reset failure is returned only when no primary failure exists.
     *
     * Secondary reset failures are not aggregated into the surfaced lifecycle
     * throwable.
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
