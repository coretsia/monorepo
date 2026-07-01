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

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Runtime\Exception\KernelRuntimeException;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeRejectsInvalidExportedContextTest extends TestCase
{
    #[DataProvider('missingRequiredContextFields')]
    public function testAfterUnitOfWorkRejectsMissingRequiredContextFieldsAndResetsOnce(
        string $missingKey,
    ): void {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        unset($context[$missingKey]);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_CONTEXT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    #[DataProvider('invalidContextFieldTypes')]
    public function testAfterUnitOfWorkRejectsInvalidContextFieldTypesAndResetsOnce(
        string $field,
        mixed $value,
    ): void {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $context[$field] = $value;

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_CONTEXT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    #[DataProvider('invalidStartedAtValues')]
    public function testAfterUnitOfWorkRejectsNegativeStartedAtAndResetsOnce(
        int $startedAt,
    ): void {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $context['startedAt'] = $startedAt;

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_CONTEXT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testAfterUnitOfWorkRejectsInvalidExportedContextTypeTokenAndResetsOnce(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        $context['type'] = 'invalid-uow-type Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret';

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_CONTEXT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testRunUnitOfWorkRejectsInvalidTypeSafelyBeforeResetResponsibility(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $bodyWasCalled = false;

        $exception = self::assertKernelRuntimeFailure(
            callback: static function () use ($runtime, &$bodyWasCalled): mixed {
                return $runtime->runUnitOfWork(
                    'invalid-uow-type Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
                    static function () use (&$bodyWasCalled): void {
                        $bodyWasCalled = true;
                    },
                );
            },
            expectedReason: KernelRuntimeException::REASON_INVALID_TYPE,
        );

        self::assertFalse($bodyWasCalled);
        self::assertSame(0, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testAfterUnitOfWorkRejectsInvalidOutcomeAndResetsOnce(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: 'invalid-outcome Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_OUTCOME,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testAfterUnitOfWorkRejectsInvalidExtensionsWithInvalidResultAndResetsOnce(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();
        $runtime = self::runtime($recorder);

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
                extensions: [
                    'raw_extensions_marker' => 'Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
                    'unsafe' => \NAN,
                ],
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_RESULT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testInvalidContextFailureRemainsPrimaryWhenResetAlsoFails(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();

        $runtime = self::runtime(
            recorder: $recorder,
            resetFailure: new \RuntimeException(
                'reset unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);
        unset($context['uowId']);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_CONTEXT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testInvalidOutcomeFailureRemainsPrimaryWhenResetAlsoFails(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();

        $runtime = self::runtime(
            recorder: $recorder,
            resetFailure: new \RuntimeException(
                'reset unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: 'invalid-outcome Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_OUTCOME,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    public function testInvalidResultFailureRemainsPrimaryWhenResetAlsoFails(): void
    {
        $recorder = new KernelRuntimeRejectsInvalidExportedContextRecorder();

        $runtime = self::runtime(
            recorder: $recorder,
            resetFailure: new \RuntimeException(
                'reset unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
            ),
        );

        $context = $runtime->beginUnitOfWork(UnitOfWorkType::HTTP);

        $exception = self::assertKernelRuntimeFailure(
            callback: static fn (): array => $runtime->afterUnitOfWork(
                context: $context,
                outcome: Outcome::SUCCESS,
                extensions: [
                    'raw_extensions_marker' => 'Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
                    'unsafe' => \NAN,
                ],
            ),
            expectedReason: KernelRuntimeException::REASON_INVALID_RESULT,
        );

        self::assertSame(1, $recorder->resetCount);
        self::assertSafeValidationFailure($exception);
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function missingRequiredContextFields(): iterable
    {
        yield 'missing-uowId' => ['uowId'];
        yield 'missing-type' => ['type'];
        yield 'missing-startedAt' => ['startedAt'];
        yield 'missing-correlationId' => ['correlationId'];
        yield 'missing-attributes' => ['attributes'];
    }

    /**
     * @return iterable<string, array{0:string,1:mixed}>
     */
    public static function invalidContextFieldTypes(): iterable
    {
        yield 'uowId-not-string' => [
            'uowId',
            123,
        ];

        yield 'type-not-string' => [
            'type',
            123,
        ];

        yield 'startedAt-not-int' => [
            'startedAt',
            'raw_context_marker Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        ];

        yield 'correlationId-not-string' => [
            'correlationId',
            123,
        ];

        yield 'attributes-not-array' => [
            'attributes',
            'raw_context_marker Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        ];
    }

    /**
     * @return iterable<string, array{0:int}>
     */
    public static function invalidStartedAtValues(): iterable
    {
        yield 'negative' => [-1];
    }

    private static function runtime(
        KernelRuntimeRejectsInvalidExportedContextRecorder $recorder,
        ?\Throwable $resetFailure = null,
    ): KernelRuntime {
        $contextStore = new ContextStore();

        $container = new KernelRuntimeRejectsInvalidExportedContextContainer([
            KernelRuntimeRejectsInvalidExportedContextResetService::class => new KernelRuntimeRejectsInvalidExportedContextResetService(
                contextStore: $contextStore,
                recorder: $recorder,
                failure: $resetFailure,
            ),
        ]);

        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            ReservedTags::KERNEL_RESET,
            KernelRuntimeRejectsInvalidExportedContextResetService::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: new ResetOrchestrator(
                container: $container,
                tagRegistry: $resetRegistry,
            ),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeRejectsInvalidExportedContextIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeRejectsInvalidExportedContextCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: new KernelRuntimeRejectsInvalidExportedContextContainer([]),
                tags: new TagRegistry(),
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
    }

    /**
     * @param callable(): mixed $callback
     */
    private static function assertKernelRuntimeFailure(
        callable $callback,
        string $expectedReason,
    ): KernelRuntimeException {
        try {
            $callback();
        } catch (KernelRuntimeException $exception) {
            self::assertSame(KernelRuntimeException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertSame(
                KernelRuntimeException::ERROR_CODE . ': ' . $expectedReason,
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());

            return $exception;
        }

        self::fail('Expected KernelRuntimeException.');
    }

    private static function assertSafeValidationFailure(KernelRuntimeException $exception): void
    {
        $messageChain = self::messageChain($exception);

        self::assertStringNotContainsString('raw_context_marker', $messageChain);
        self::assertStringNotContainsString('raw_extensions_marker', $messageChain);
        self::assertStringNotContainsString('unsafe-token', $messageChain);
        self::assertStringNotContainsString('Authorization', $messageChain);
        self::assertStringNotContainsString('Cookie', $messageChain);
        self::assertStringNotContainsString('session_id', $messageChain);
        self::assertStringNotContainsString('SELECT * FROM users', $messageChain);
        self::assertStringNotContainsString('/tmp/', $messageChain);
        self::assertStringNotContainsString('/tmp/coretsia-secret', $messageChain);
        self::assertStringNotContainsString(__DIR__, $messageChain);
    }

    private static function messageChain(\Throwable $throwable): string
    {
        $messages = [];

        do {
            $messages[] = $throwable->getMessage();
            $throwable = $throwable->getPrevious();
        } while ($throwable !== null);

        return \implode("\n", $messages);
    }
}

final class KernelRuntimeRejectsInvalidExportedContextRecorder
{
    public int $resetCount = 0;
}

final readonly class KernelRuntimeRejectsInvalidExportedContextResetService implements ResetInterface
{
    public function __construct(
        private ContextStore $contextStore,
        private KernelRuntimeRejectsInvalidExportedContextRecorder $recorder,
        private ?\Throwable $failure = null,
    ) {
    }

    public function reset(): void
    {
        ++$this->recorder->resetCount;

        $this->contextStore->reset();

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}

final readonly class KernelRuntimeRejectsInvalidExportedContextIdGenerator implements IdGeneratorInterface
{
    /**
     * @param non-empty-string $id
     */
    public function __construct(
        private string $id,
    ) {
    }

    public function generate(): string
    {
        return $this->id;
    }
}

final readonly class KernelRuntimeRejectsInvalidExportedContextCorrelationIdProvider implements CorrelationIdProviderInterface
{
    /**
     * @param non-empty-string|null $correlationId
     */
    public function __construct(
        private ?string $correlationId,
    ) {
    }

    public function correlationId(): ?string
    {
        return $this->correlationId;
    }
}

final readonly class KernelRuntimeRejectsInvalidExportedContextContainer implements ContainerInterface
{
    /**
     * @param array<string, mixed> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new \RuntimeException('test-container-service-not-found');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}
