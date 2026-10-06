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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveMixedLevelException;
use Coretsia\Kernel\Config\Exception\ConfigDirectiveTypeMismatchException;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Exception\ConfigReservedNamespaceException;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use ReflectionMethod;

final class KernelOpsHandledErrorsPreserveCanonicalKernelReasonsTest extends TestCase
{
    public function testCanonicalHandledReasonsAreProjectedUnchanged(): void
    {
        $validation = ConfigValidationResult::failure([
            new ConfigValidationViolation(
                root: 'kernel',
                path: 'fixture',
                reason: 'invalid',
                expected: 'map',
                actualType: 'string',
            ),
        ]);

        $cases = [
            BootstrapException::withReason(BootstrapException::REASON_INVALID_APP_TARGET),
            ModePresetNotFoundException::forPreset('missing-safe-preset'),
            ConfigInvalidException::withReason(ConfigInvalidException::REASON_SOURCE_INVALID),
            ConfigReservedNamespaceException::withReason(
                ConfigReservedNamespaceException::REASON_RESERVED_NAMESPACE_USED,
            ),
            ConfigDirectiveMixedLevelException::withReason(
                ConfigDirectiveMixedLevelException::REASON_DIRECTIVE_MIXED_LEVEL,
            ),
            ConfigDirectiveTypeMismatchException::mergeDirectiveValueMustBeMap('kernel.fixture'),
            ConfigInvalidException::fromValidationResult($validation),
        ];

        $handledReason = new ReflectionMethod(KernelOpsFacade::class, 'handledReason');

        foreach ($cases as $exception) {
            self::assertSame(
                $exception->reason(),
                $handledReason->invoke(null, $exception),
            );
        }

        self::assertSame(
            ConfigInvalidException::REASON_VALIDATION_FAILED,
            ConfigInvalidException::fromValidationResult($validation)->reason(),
        );
    }

    public function testModuleFailureBeforePresetEligibilityOmitsRejectedPresetFromResultAndFacadeTelemetry(): void
    {
        $tracer = new KernelOpsHandledReasonTracer();
        $meter = new KernelOpsHandledReasonMeter();
        $logger = new KernelOpsHandledReasonLogger();
        $facade = self::facade($tracer, $meter, $logger);
        $candidate = 'rejected-preset-candidate';

        $result = self::execute(
            facade: $facade,
            operation: 'modules.debug',
            callback: static function () use ($candidate): OpsResult {
                throw ModePresetNotFoundException::forPreset($candidate);
            },
        );

        self::assertSame('handled_error', $result->outcome());
        self::assertSame(ModePresetNotFoundException::REASON_PRESET_NOT_FOUND, $result->reason());
        self::assertSame('console', $result->appTarget());
        self::assertNull($result->preset());
        self::assertSame([], $result->data());

        $surface = \var_export(
            [
                'result' => $result->data(),
                'logs' => $logger->records,
                'spans' => $tracer->spans,
                'metrics' => [$meter->increments, $meter->observations],
            ],
            true,
        );

        self::assertStringNotContainsString($candidate, $surface);

        self::assertCount(1, $tracer->spans);
        self::assertArrayNotHasKey('preset', $tracer->spans[0]->attributes);

        self::assertCount(1, $logger->records);
        self::assertArrayNotHasKey('preset', $logger->records[0]['context']);
    }

    public function testModuleResolutionBoundaryRethrowsCanonicalFailureAndKeepsSafeLogging(): void
    {
        $source = self::moduleResolutionSource();

        self::assertMatchesRegularExpression(
            '/catch\s*\(ModuleResolutionException\s+\$exception\).*?' .
            '\$this->logResolutionFailure\(\s*\$exception,\s*\$bootstrapConfig,\s*\);.*?' .
            'throw\s+\$exception;/s',
            $source,
        );
        self::assertStringContainsString(
            "'reason' => \$exception->reason()",
            $source,
        );
        self::assertStringContainsString(
            "'presetName' => self::safePresetNameForLog(\$bootstrapConfig->preset())",
            $source,
        );
        self::assertStringNotContainsString('$exception->getMessage()', $source);
        self::assertStringNotContainsString('$exception->getPrevious()', $source);
    }

    public function testHandledErrorResultDoesNotExposeExceptionInternals(): void
    {
        $absolutePath = \DIRECTORY_SEPARATOR === '\\'
            ? 'C:\\private\\kernel-ops\\secret.php'
            : '/private/kernel-ops/secret.php';
        $rawMessage = 'raw-previous-throwable-message';
        $previous = new \RuntimeException($rawMessage);

        $result = self::execute(
            facade: self::facade(
                new KernelOpsHandledReasonTracer(),
                new KernelOpsHandledReasonMeter(),
                new KernelOpsHandledReasonLogger(),
            ),
            operation: 'config.debug',
            callback: static function () use ($absolutePath, $previous): OpsResult {
                throw ConfigDirectiveTypeMismatchException::mergeDirectiveValueMustBeMap(
                    $absolutePath,
                    $previous,
                );
            },
        );

        self::assertSame('handled_error', $result->outcome());
        self::assertSame(
            ConfigDirectiveTypeMismatchException::REASON_MERGE_DIRECTIVE_VALUE_MUST_BE_MAP,
            $result->reason(),
        );

        $surface = \var_export(
            [
                'appTarget' => $result->appTarget(),
                'preset' => $result->preset(),
                'reason' => $result->reason(),
                'data' => $result->data(),
            ],
            true,
        );

        self::assertStringNotContainsString($absolutePath, $surface);
        self::assertStringNotContainsString($rawMessage, $surface);
        self::assertStringNotContainsString(\RuntimeException::class, $surface);
    }

    public function testValidationAndSuccessReasonSemanticsRemainStableWithoutDuplicateDebugValidation(): void
    {
        $success = self::invokeFacadeResult(
            method: 'successResult',
            operation: 'config.debug',
            reason: null,
        );

        self::assertSame('success', $success->outcome());
        self::assertNull($success->reason());

        $handled = self::invokeFacadeResult(
            method: 'handledResult',
            operation: 'config.debug',
            reason: ConfigInvalidException::REASON_VALIDATION_FAILED,
        );

        self::assertSame('handled_error', $handled->outcome());
        self::assertSame('config-validation-failed', $handled->reason());

        $source = self::methodSource('debugConfig', 'compileConfig');

        self::assertSame(1, \substr_count($source, '$this->configKernel->compile('));
        self::assertStringNotContainsString('ConfigValidator', $source);
        self::assertStringNotContainsString('getMessage()', $source);
        self::assertStringNotContainsString('getPrevious()', $source);
        self::assertMatchesRegularExpression(
            '/\$validation = self::validationResult\(\$compiled\);\s*' .
            'if \(\$validation->isFailure\(\)\) \{\s*' .
            'return \$this->handledResult\(.*?' .
            'reason: ConfigInvalidException::REASON_VALIDATION_FAILED/s',
            $source,
        );
    }

    private static function execute(
        KernelOpsFacade $facade,
        string $operation,
        callable $callback,
    ): OpsResult {
        $method = new ReflectionMethod(KernelOpsFacade::class, 'execute');
        $result = $method->invoke(
            $facade,
            $operation,
            new KernelOpsRequest('console'),
            $callback,
        );

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    private static function invokeFacadeResult(
        string $method,
        string $operation,
        ?string $reason,
    ): OpsResult {
        $facade = new ReflectionClass(KernelOpsFacade::class)->newInstanceWithoutConstructor();
        $reflection = new ReflectionMethod(KernelOpsFacade::class, $method);

        $result = $method === 'successResult'
            ? $reflection->invoke($facade, $operation, 'console', 'test', [])
            : $reflection->invoke($facade, $operation, 'console', 'test', $reason, []);

        self::assertInstanceOf(OpsResult::class, $result);

        return $result;
    }

    private static function facade(
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        AbstractLogger $logger,
    ): KernelOpsFacade {
        return new KernelOpsFacade(
            hostInput: new KernelOpsHostInput('/fixture/application'),
            bootstrapConfigResolver: self::uninitialized(BootstrapConfigResolver::class),
            envRepositoryBuilder: self::uninitialized(EnvRepositoryBuilder::class),
            moduleResolutionOrchestrator: self::uninitialized(ModuleResolutionOrchestrator::class),
            configKernel: self::uninitialized(ConfigKernel::class),
            runtimeContainerGraphCompiler: self::uninitialized(RuntimeContainerGraphCompiler::class),
            configFingerprintInputBuilder: self::uninitialized(ConfigFingerprintInputBuilder::class),
            fingerprintCalculator: self::uninitialized(FingerprintCalculator::class),
            configSourceLocationBuilder: self::uninitialized(ConfigSourceLocationBuilder::class),
            kernelArtifactOperation: self::uninitialized(KernelArtifactOperation::class),
            kernelConfig: [],
            contextAccessor: new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    return null;
                }
            },
            correlationIdProvider: new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
            stopwatch: new Stopwatch(),
        );
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private static function uninitialized(string $class): object
    {
        /** @var T $instance */
        $instance = new ReflectionClass($class)->newInstanceWithoutConstructor();

        return $instance;
    }

    private static function moduleResolutionSource(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Module/ModuleResolutionOrchestrator.php');

        self::assertIsString($source);

        return $source;
    }

    private static function methodSource(string $start, string $end): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsFacade.php');
        self::assertIsString($source);
        $from = \strpos($source, 'public function ' . $start);
        $to = \strpos($source, 'public function ' . $end);
        self::assertIsInt($from);
        self::assertIsInt($to);

        return \substr($source, $from, $to - $from);
    }
}

final class KernelOpsHandledReasonTracer implements TracerPortInterface
{
    /** @var list<KernelOpsHandledReasonSpan> */
    public array $spans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new KernelOpsHandledReasonSpan($name, $attributes);
        $this->spans[] = $span;

        return $span;
    }

    public function inSpan(string $name, callable $callback, array $attributes = []): mixed
    {
        return $callback($this->startSpan($name, $attributes));
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }
}

final class KernelOpsHandledReasonSpan implements SpanInterface
{
    public function __construct(
        private readonly string $spanName,
        public array $attributes,
    ) {
    }

    public function name(): string
    {
        return $this->spanName;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        $this->attributes = [...$this->attributes, ...$attributes];
    }

    public function addEvent(string $name, array $attributes = []): void
    {
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
    }

    public function end(): void
    {
    }
}

final class KernelOpsHandledReasonMeter implements MeterPortInterface
{
    public array $increments = [];
    public array $observations = [];

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = ['name' => $name, 'labels' => $labels];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = ['name' => $name, 'labels' => $labels];
    }
}

final class KernelOpsHandledReasonLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['message' => (string) $message, 'context' => $context];
    }
}
