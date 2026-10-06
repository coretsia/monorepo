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

namespace Coretsia\Kernel\Ops;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use Coretsia\Kernel\Module\ModuleResolution;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned transport-neutral operations façade.
 *
 * This façade composes the existing Bootstrap, module-resolution, ConfigKernel,
 * graph, fingerprint, artifact, and verification services. It does not own a
 * parallel config pipeline, service locator, runtime lifecycle, or redaction
 * layer.
 *
 * @internal Callers depend on KernelOpsInterface, not this implementation.
 */
final readonly class KernelOpsFacade implements KernelOpsInterface
{
    private const string OPERATION_CONFIG_VALIDATE = 'config.validate';
    private const string OPERATION_CONFIG_DEBUG = 'config.debug';
    private const string OPERATION_CONFIG_COMPILE = 'config.compile';
    private const string OPERATION_CONFIG_HASH = 'config.hash';
    private const string OPERATION_CACHE_VERIFY = 'cache.verify';
    private const string OPERATION_MODULES_DEBUG = 'modules.debug';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_HANDLED_ERROR = 'handled_error';
    private const string OUTCOME_FAILURE = 'failure';

    private const string SPAN_OPERATION = 'kernel.operation';
    private const string METRIC_OPERATION_TOTAL = 'kernel.operation_total';
    private const string METRIC_OPERATION_DURATION_MS = 'kernel.operation_duration_ms';

    private const array COMPILE_ARTIFACTS = [
        [
            'identity' => 'module-manifest@1',
            'basename' => 'module-manifest.php',
        ],
        [
            'identity' => 'config@1',
            'basename' => 'config.php',
        ],
        [
            'identity' => 'container@1',
            'basename' => 'container.php',
        ],
        [
            'identity' => 'artifact-generation@1',
            'basename' => 'generation-manifest.php',
        ],
    ];

    private const array VERIFY_ARTIFACT_BASENAMES = [
        'module-manifest' => 'module-manifest.php',
        'config' => 'config.php',
        'container' => 'container.php',
        'artifact-generation' => 'generation-manifest.php',
    ];

    private const array VERIFY_STATES = [
        'clean' => true,
        'dirty' => true,
        'invalid' => true,
    ];

    private const array VERIFY_REASONS = [
        'ok' => true,
        'missing' => true,
        'changed' => true,
        'fingerprint_mismatch' => true,
        'invalid' => true,
    ];

    /**
     * @param array<string,mixed> $kernelConfig Exact baseline `kernel` config subtree.
     */
    public function __construct(
        private KernelOpsHostInput $hostInput,
        private BootstrapConfigResolver $bootstrapConfigResolver,
        private EnvRepositoryBuilder $envRepositoryBuilder,
        private ModuleResolutionOrchestrator $moduleResolutionOrchestrator,
        private ConfigKernel $configKernel,
        private RuntimeContainerGraphCompiler $runtimeContainerGraphCompiler,
        private ConfigFingerprintInputBuilder $configFingerprintInputBuilder,
        private FingerprintCalculator $fingerprintCalculator,
        private ConfigSourceLocationBuilder $configSourceLocationBuilder,
        private KernelArtifactOperation $kernelArtifactOperation,
        private array $kernelConfig,
        private ContextAccessorInterface $contextAccessor,
        private CorrelationIdProviderInterface $correlationIdProvider,
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
        if (!self::isStringMap($this->kernelConfig)) {
            throw new \InvalidArgumentException('kernel-ops-kernel-config-invalid');
        }
    }

    public function validateConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_CONFIG_VALIDATE,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $prepared = $this->prepareConfigOperation(
                    input: $input,
                    markPreset: $markPreset,
                );

                $compiled = $this->configKernel->compile(
                    bootstrapConfig: $prepared['bootstrapConfig'],
                    modulePlan: $prepared['moduleResolution']->plan(),
                    env: $prepared['env'],
                    configSources: $prepared['configSources'],
                    explain: false,
                );

                $validation = self::validationResult($compiled);
                $data = self::validationData($compiled, $validation);

                if ($validation->isFailure()) {
                    return $this->handledResult(
                        operation: self::OPERATION_CONFIG_VALIDATE,
                        appTarget: $appTarget,
                        preset: $prepared['bootstrapConfig']->preset(),
                        reason: ConfigInvalidException::REASON_VALIDATION_FAILED,
                        data: $data,
                    );
                }

                return $this->successResult(
                    operation: self::OPERATION_CONFIG_VALIDATE,
                    appTarget: $appTarget,
                    preset: $prepared['bootstrapConfig']->preset(),
                    data: $data,
                );
            },
        );
    }

    public function debugConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_CONFIG_DEBUG,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $prepared = $this->prepareConfigOperation(
                    input: $input,
                    markPreset: $markPreset,
                );

                $compiled = $this->configKernel->compile(
                    bootstrapConfig: $prepared['bootstrapConfig'],
                    modulePlan: $prepared['moduleResolution']->plan(),
                    env: $prepared['env'],
                    configSources: $prepared['configSources'],
                    explain: true,
                );

                $validation = self::validationResult($compiled);

                if ($validation->isFailure()) {
                    return $this->handledResult(
                        operation: self::OPERATION_CONFIG_DEBUG,
                        appTarget: $appTarget,
                        preset: $prepared['bootstrapConfig']->preset(),
                        reason: ConfigInvalidException::REASON_VALIDATION_FAILED,
                    );
                }

                $explain = $compiled['explain'] ?? null;

                if (!\is_array($explain)) {
                    throw new \UnexpectedValueException('kernel-ops-debug-config-explain-invalid');
                }

                return $this->successResult(
                    operation: self::OPERATION_CONFIG_DEBUG,
                    appTarget: $appTarget,
                    preset: $prepared['bootstrapConfig']->preset(),
                    data: [
                        'explain' => $explain,
                    ],
                );
            },
        );
    }

    public function compileConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_CONFIG_COMPILE,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $compiled = $this->kernelArtifactOperation->compile($input);
                $effectivePreset = self::requiredStringField(
                    $compiled,
                    'effectivePreset',
                    'kernel-ops-compile-effective-preset-invalid',
                );

                $result = $this->successResult(
                    operation: self::OPERATION_CONFIG_COMPILE,
                    appTarget: $appTarget,
                    preset: $effectivePreset,
                    data: self::compileData($compiled),
                );

                $markPreset($effectivePreset);

                return $result;
            },
        );
    }

    public function hashConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_CONFIG_HASH,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $prepared = $this->prepareConfigOperation(
                    input: $input,
                    markPreset: $markPreset,
                );

                $compiled = $this->configKernel->compile(
                    bootstrapConfig: $prepared['bootstrapConfig'],
                    modulePlan: $prepared['moduleResolution']->plan(),
                    env: $prepared['env'],
                    configSources: $prepared['configSources'],
                    explain: false,
                );

                $validation = self::validationResult($compiled);

                if ($validation->isFailure()) {
                    return $this->handledResult(
                        operation: self::OPERATION_CONFIG_HASH,
                        appTarget: $appTarget,
                        preset: $prepared['bootstrapConfig']->preset(),
                        reason: ConfigInvalidException::REASON_VALIDATION_FAILED,
                    );
                }

                $compiledConfig = $compiled['config'] ?? null;

                if (!\is_array($compiledConfig)) {
                    throw new \UnexpectedValueException('kernel-ops-compiled-config-invalid');
                }

                $containerGraph = $this->runtimeContainerGraphCompiler->compile(
                    moduleResolution: $prepared['moduleResolution'],
                    compiledConfig: $compiledConfig,
                );

                $fingerprintInput = $this->configFingerprintInputBuilder->build(
                    bootstrapConfig: $prepared['bootstrapConfig'],
                    modulePlan: $prepared['moduleResolution']->plan(),
                    containerGraph: $containerGraph,
                    env: $prepared['env'],
                    kernelConfig: $this->kernelConfig,
                    compiledConfig: $compiled,
                    configSources: $prepared['configSources'],
                );

                $generationId = $this->fingerprintCalculator->calculate($fingerprintInput);

                return $this->successResult(
                    operation: self::OPERATION_CONFIG_HASH,
                    appTarget: $appTarget,
                    preset: $prepared['bootstrapConfig']->preset(),
                    data: [
                        'generation_id' => $generationId,
                    ],
                );
            },
        );
    }

    public function verifyCache(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_CACHE_VERIFY,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $verified = $this->kernelArtifactOperation->verify($input);
                $effectivePreset = self::requiredStringField(
                    $verified,
                    'effectivePreset',
                    'kernel-ops-verify-effective-preset-invalid',
                );

                $result = $this->successResult(
                    operation: self::OPERATION_CACHE_VERIFY,
                    appTarget: $appTarget,
                    preset: $effectivePreset,
                    data: self::verifyData($verified),
                );

                $markPreset($effectivePreset);

                return $result;
            },
        );
    }

    public function debugModules(KernelOpsRequest $request): OpsResult
    {
        return $this->execute(
            operation: self::OPERATION_MODULES_DEBUG,
            request: $request,
            callback: function (
                BootstrapInput $input,
                string $appTarget,
                callable $markPreset,
            ): OpsResult {
                $bootstrapConfig = $this->bootstrapConfigResolver->resolve(
                    $input,
                    $this->kernelConfig,
                );

                $moduleResolution = $this->moduleResolutionOrchestrator->resolve($bootstrapConfig);

                $markPreset($bootstrapConfig->preset());

                $plan = $moduleResolution->plan();

                return $this->successResult(
                    operation: self::OPERATION_MODULES_DEBUG,
                    appTarget: $appTarget,
                    preset: $bootstrapConfig->preset(),
                    data: [
                        'enabled' => self::moduleIds($plan->enabled()),
                        'excluded' => self::moduleIds($plan->excluded()),
                        'topological_order' => self::moduleIds($plan->topologicalOrder()),
                    ],
                );
            },
        );
    }

    /**
     * @param callable(BootstrapInput,string,callable(string):void):OpsResult $callback
     */
    private function execute(
        string $operation,
        KernelOpsRequest $request,
        callable $callback,
    ): OpsResult {
        $startedAt = $this->safeStartTimer();
        $span = $this->safeStartSpan($operation);
        $appTarget = null;
        $preset = null;
        $outcome = self::OUTCOME_FAILURE;

        $markPreset = static function (string $effectivePreset) use (&$preset): void {
            $preset = $effectivePreset;
        };

        try {
            $target = AppTarget::fromString($request->appTarget());
            $appTarget = $target->value;

            $input = new BootstrapInput(
                applicationRoot: $this->hostInput->applicationRoot(),
                appTarget: $target,
                preset: null,
            );

            $result = $callback(
                $input,
                $appTarget,
                $markPreset,
            );

            $outcome = $result->outcome();

            return $result;
        } catch (\Throwable $exception) {
            $reason = self::handledReason($exception);

            if ($reason !== null) {
                $outcome = self::OUTCOME_HANDLED_ERROR;

                return $this->handledResult(
                    operation: $operation,
                    appTarget: $appTarget,
                    preset: $preset,
                    reason: $reason,
                );
            }

            $outcome = self::OUTCOME_FAILURE;

            throw new KernelOpsFailedException(
                KernelOpsFailedException::REASON_OPERATION_FAILED,
            );
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->safeFinishSpan(
                span: $span,
                operation: $operation,
                appTarget: $appTarget,
                preset: $preset,
                outcome: $outcome,
            );
            $this->safeEmitMetrics(
                operation: $operation,
                outcome: $outcome,
                durationMs: $durationMs,
            );
            $this->safeLogSummary(
                operation: $operation,
                appTarget: $appTarget,
                preset: $preset,
                outcome: $outcome,
            );
        }
    }

    /**
     * @param callable(string):void $markPreset
     *
     * @return array{
     *     bootstrapConfig: BootstrapConfig,
     *     env: EnvRepositoryInterface,
     *     moduleResolution: ModuleResolution,
     *     configSources: ConfigSourceSet
     * }
     */
    private function prepareConfigOperation(
        BootstrapInput $input,
        callable $markPreset,
    ): array {
        $bootstrapConfig = $this->bootstrapConfigResolver->resolve(
            $input,
            $this->kernelConfig,
        );

        $env = $this->envRepositoryBuilder->build(
            $bootstrapConfig,
            $this->kernelConfig,
        );

        $moduleResolution = $this->moduleResolutionOrchestrator->resolve($bootstrapConfig);

        $markPreset($bootstrapConfig->preset());

        $configSources = $this->configSourceLocationBuilder->build(
            $bootstrapConfig,
            $moduleResolution,
        );

        return [
            'bootstrapConfig' => $bootstrapConfig,
            'env' => $env,
            'moduleResolution' => $moduleResolution,
            'configSources' => $configSources,
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function successResult(
        string $operation,
        string $appTarget,
        string $preset,
        array $data = [],
    ): OpsResult {
        return new OpsResult(
            operation: $operation,
            appTarget: $appTarget,
            preset: $preset,
            outcome: self::OUTCOME_SUCCESS,
            reason: null,
            data: self::normalizeData($data),
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    private function handledResult(
        string $operation,
        ?string $appTarget,
        ?string $preset,
        string $reason,
        array $data = [],
    ): OpsResult {
        return new OpsResult(
            operation: $operation,
            appTarget: $appTarget,
            preset: $preset,
            outcome: self::OUTCOME_HANDLED_ERROR,
            reason: $reason,
            data: self::normalizeData($data),
        );
    }

    /**
     * @param array<string,mixed> $compiled
     */
    private static function validationResult(array $compiled): ConfigValidationResult
    {
        $validation = $compiled['validation'] ?? null;

        if (!$validation instanceof ConfigValidationResult) {
            throw new \UnexpectedValueException('kernel-ops-config-validation-result-invalid');
        }

        return $validation;
    }

    /**
     * @param array<string,mixed> $compiled
     *
     * @return array<string,mixed>
     */
    private static function validationData(
        array $compiled,
        ConfigValidationResult $validation,
    ): array {
        $subjects = $compiled['validationSubjects'] ?? null;

        if (!\is_array($subjects)) {
            throw new \UnexpectedValueException('kernel-ops-validation-subjects-invalid');
        }

        $unvalidated = $subjects['unvalidated'] ?? null;
        $validated = $subjects['validated'] ?? null;

        if (
            !\is_array($unvalidated)
            || !\array_is_list($unvalidated)
            || !\is_array($validated)
            || !\array_is_list($validated)
        ) {
            throw new \UnexpectedValueException('kernel-ops-validation-subjects-invalid');
        }

        return [
            'counts' => [
                'unvalidated_root_count' => \count($unvalidated),
                'validated_root_count' => \count($validated),
                'violation_count' => \count($validation->violations()),
            ],
            'valid' => $validation->isSuccess(),
        ];
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private static function compileData(array $result): array
    {
        $generationId = self::requiredStringField(
            $result,
            'generationId',
            'kernel-ops-compile-generation-id-invalid',
        );
        $artifacts = $result['artifacts'] ?? null;

        if (!\is_array($artifacts) || !\array_is_list($artifacts)) {
            throw new \UnexpectedValueException('kernel-ops-compile-artifacts-invalid');
        }

        if (\count($artifacts) !== \count(self::COMPILE_ARTIFACTS)) {
            throw new \UnexpectedValueException('kernel-ops-compile-artifacts-invalid');
        }

        $projected = [];

        foreach (self::COMPILE_ARTIFACTS as $index => $expected) {
            $artifact = $artifacts[$index] ?? null;

            if (!\is_array($artifact)) {
                throw new \UnexpectedValueException('kernel-ops-compile-artifact-invalid');
            }

            $identity = $artifact['identity'] ?? null;
            $basename = $artifact['basename'] ?? null;

            if (
                $identity !== $expected['identity']
                || $basename !== $expected['basename']
            ) {
                throw new \UnexpectedValueException('kernel-ops-compile-artifact-invalid');
            }

            $projected[] = [
                'basename' => $basename,
                'identity' => $identity,
            ];
        }

        return [
            'artifacts' => $projected,
            'generation_id' => $generationId,
        ];
    }

    /**
     * @param array<string,mixed> $result
     *
     * @return array<string,mixed>
     */
    private static function verifyData(array $result): array
    {
        $expectedGenerationId = self::requiredStringField(
            $result,
            'expectedGenerationId',
            'kernel-ops-verify-expected-generation-id-invalid',
        );

        $currentGenerationId = $result['currentGenerationId'] ?? null;

        if ($currentGenerationId !== null && !\is_string($currentGenerationId)) {
            throw new \UnexpectedValueException('kernel-ops-verify-current-generation-id-invalid');
        }

        $state = $result['outcome'] ?? null;

        if (!\is_string($state) || !isset(self::VERIFY_STATES[$state])) {
            throw new \UnexpectedValueException('kernel-ops-verify-state-invalid');
        }

        $artifacts = $result['artifacts'] ?? null;

        if (!\is_array($artifacts) || !\array_is_list($artifacts) || \count($artifacts) !== 4) {
            throw new \UnexpectedValueException('kernel-ops-verify-artifacts-invalid');
        }

        $seenNames = [];
        $projected = [];

        foreach ($artifacts as $artifact) {
            if (!\is_array($artifact)) {
                throw new \UnexpectedValueException('kernel-ops-verify-artifact-invalid');
            }

            $name = $artifact['name'] ?? null;
            $basename = $artifact['basename'] ?? null;
            $status = $artifact['status'] ?? null;
            $reason = $artifact['reason'] ?? null;
            $expectedBytes = $artifact['expectedBytes'] ?? null;
            $existingBytes = $artifact['existingBytes'] ?? null;

            if (
                !\is_string($name)
                || !isset(self::VERIFY_ARTIFACT_BASENAMES[$name])
                || isset($seenNames[$name])
                || $basename !== self::VERIFY_ARTIFACT_BASENAMES[$name]
                || !\is_string($status)
                || !isset(self::VERIFY_STATES[$status])
                || !\is_string($reason)
                || !isset(self::VERIFY_REASONS[$reason])
                || !\is_int($expectedBytes)
                || $expectedBytes < 0
                || ($existingBytes !== null && (!\is_int($existingBytes) || $existingBytes < 0))
            ) {
                throw new \UnexpectedValueException('kernel-ops-verify-artifact-invalid');
            }

            $seenNames[$name] = true;
            $projected[] = [
                'basename' => $basename,
                'existing_byte_count' => $existingBytes,
                'expected_byte_count' => $expectedBytes,
                'name' => $name,
                'reason' => $reason,
                'status' => $status,
            ];
        }

        if (\count($seenNames) !== \count(self::VERIFY_ARTIFACT_BASENAMES)) {
            throw new \UnexpectedValueException('kernel-ops-verify-artifacts-invalid');
        }

        return [
            'artifacts' => $projected,
            'current_generation_id' => $currentGenerationId,
            'expected_generation_id' => $expectedGenerationId,
            'state' => $state,
        ];
    }

    /**
     * @param array<string,mixed> $data
     *
     * @return array<int|string,mixed>
     */
    private static function normalizeData(array $data): array
    {
        $normalized = JsonLikeNormalizer::normalize(
            $data,
            'data',
        );

        if (!\is_array($normalized)) {
            throw new \LogicException('kernel-ops-normalized-data-invalid');
        }

        return $normalized;
    }

    /**
     * @param list<\Coretsia\Contracts\Module\ModuleId> $moduleIds
     *
     * @return list<string>
     */
    private static function moduleIds(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }

    private static function handledReason(\Throwable $exception): ?string
    {
        return match (true) {
            $exception instanceof BootstrapException => $exception->reason(),
            $exception instanceof ModuleResolutionException => $exception->reason(),
            $exception instanceof ConfigInvalidException => $exception->reason(),
            $exception instanceof ConfigReservedNamespaceException => $exception->reason(),
            $exception instanceof ConfigDirectiveMixedLevelException => $exception->reason(),
            $exception instanceof ConfigDirectiveTypeMismatchException => $exception->reason(),
            default => null,
        };
    }

    /**
     * @param array<string,mixed> $source
     *
     * @return non-empty-string
     */
    private static function requiredStringField(
        array $source,
        string $key,
        string $reason,
    ): string {
        $value = $source[$key] ?? null;

        if (!\is_string($value) || $value === '') {
            throw new \UnexpectedValueException($reason);
        }

        return $value;
    }

    private function safeStartTimer(): mixed
    {
        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeStopTimer(mixed $startedAt): int
    {
        if (!\is_int($startedAt)) {
            return 0;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0 ? $durationMs : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeStartSpan(string $operation): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(
                self::SPAN_OPERATION,
                [
                    'operation' => $operation,
                ],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeFinishSpan(
        ?SpanInterface $span,
        string $operation,
        ?string $appTarget,
        ?string $preset,
        string $outcome,
    ): void {
        if ($span === null) {
            return;
        }

        $attributes = [
            'operation' => $operation,
            'outcome' => $outcome,
        ];

        if ($appTarget !== null) {
            $attributes['app_target'] = $appTarget;
        }

        if ($preset !== null) {
            $attributes['preset'] = $preset;
        }

        try {
            $span->setAttributes($attributes);
        } catch (\Throwable) {
            // Observability is best-effort and must not alter operation semantics.
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // Observability is best-effort and must not alter operation semantics.
        }
    }

    private function safeEmitMetrics(
        string $operation,
        string $outcome,
        int $durationMs,
    ): void {
        $labels = [
            'operation' => $operation,
            'outcome' => $outcome,
        ];

        try {
            $this->meter->increment(
                self::METRIC_OPERATION_TOTAL,
                1,
                $labels,
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter operation semantics.
        }

        try {
            $this->meter->observe(
                self::METRIC_OPERATION_DURATION_MS,
                $durationMs,
                $labels,
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter operation semantics.
        }
    }

    private function safeLogSummary(
        string $operation,
        ?string $appTarget,
        ?string $preset,
        string $outcome,
    ): void {
        $context = [
            'operation' => $operation,
        ];

        if ($appTarget !== null) {
            $context['app_target'] = $appTarget;
        }

        if ($preset !== null) {
            $context['preset'] = $preset;
        }

        $context['outcome'] = $outcome;

        $correlationId = $this->safeCorrelationId();

        if ($correlationId !== null) {
            $context['correlation_id'] = $correlationId;
        }

        $uowId = $this->safeUowId();

        if ($uowId !== null) {
            $context['uow_id'] = $uowId;
        }

        try {
            $this->logger->info(
                'coretsia.kernel.operation',
                $context,
            );
        } catch (\Throwable) {
            // Logging is best-effort and must not alter operation semantics.
        }
    }

    private function safeCorrelationId(): ?string
    {
        try {
            $correlationId = $this->correlationIdProvider->correlationId();
        } catch (\Throwable) {
            return null;
        }

        if ($correlationId === null || !self::isSafeLogId($correlationId)) {
            return null;
        }

        return $correlationId;
    }

    private function safeUowId(): ?string
    {
        try {
            if (!$this->contextAccessor->has(ContextKeys::UOW_ID)) {
                return null;
            }

            $uowId = $this->contextAccessor->get(ContextKeys::UOW_ID);
        } catch (\Throwable) {
            return null;
        }

        if (!\is_string($uowId) || !self::isSafeLogId($uowId)) {
            return null;
        }

        return $uowId;
    }

    private static function isSafeLogId(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $match = \preg_match('/[\s\x00-\x1F\x7F]/u', $value);

        return $match === 0;
    }

    /**
     * @param array<array-key,mixed> $value
     */
    private static function isStringMap(array $value): bool
    {
        if ($value !== [] && \array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                return false;
            }
        }

        return true;
    }
}
