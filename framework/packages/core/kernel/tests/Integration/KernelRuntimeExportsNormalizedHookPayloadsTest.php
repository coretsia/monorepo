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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
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
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\Outcome;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;

final class KernelRuntimeExportsNormalizedHookPayloadsTest extends TestCase
{
    public function testHooksReceiveNormalizedJsonLikeContextAndResultPayloads(): void
    {
        $contextStore = new ContextStore();
        $recorder = new KernelRuntimeExportsNormalizedHookPayloadsRecorder();

        $runtime = self::runtime($contextStore, $recorder);

        $context = $runtime->beginUnitOfWork(
            UnitOfWorkType::HTTP,
            [
                'zeta' => [
                    'second' => 2,
                    'first' => 1,
                ],
                'alpha' => [
                    'b',
                    'a',
                ],
                'nested' => [
                    'map' => [
                        'two' => 2,
                        'one' => 1,
                    ],
                    'list' => [
                        [
                            'b' => 2,
                            'a' => 1,
                        ],
                        [
                            'd' => 4,
                            'c' => 3,
                        ],
                    ],
                ],
            ],
        );

        $result = $runtime->afterUnitOfWork(
            context: $context,
            outcome: Outcome::SUCCESS,
            extensions: [
                'zeta' => [
                    'second' => 2,
                    'first' => 1,
                ],
                'alpha' => [
                    'b',
                    'a',
                ],
                'nested' => [
                    'map' => [
                        'two' => 2,
                        'one' => 1,
                    ],
                    'list' => [
                        [
                            'b' => 2,
                            'a' => 1,
                        ],
                    ],
                ],
            ],
        );

        self::assertCount(1, $recorder->beforeContexts);
        self::assertCount(1, $recorder->afterContexts);
        self::assertCount(1, $recorder->afterResults);

        $beforeContext = $recorder->beforeContexts[0];
        $afterContext = $recorder->afterContexts[0];
        $afterResult = $recorder->afterResults[0];

        self::assertSame($context, $beforeContext);
        self::assertSame($context, $afterContext);
        self::assertSame($result, $afterResult);

        self::assertSame(
            [
                'attributes',
                'correlationId',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($beforeContext),
        );

        self::assertSame(
            [
                'alpha',
                'nested',
                'zeta',
            ],
            \array_keys($beforeContext['attributes']),
        );

        self::assertSame(
            [
                'list',
                'map',
            ],
            \array_keys($beforeContext['attributes']['nested']),
        );

        self::assertSame(
            [
                'one',
                'two',
            ],
            \array_keys($beforeContext['attributes']['nested']['map']),
        );

        self::assertSame(
            [
                'b',
                'a',
            ],
            $beforeContext['attributes']['alpha'],
        );

        self::assertSame(
            [
                'a',
                'b',
            ],
            \array_keys($beforeContext['attributes']['nested']['list'][0]),
        );

        self::assertSame(
            [
                'c',
                'd',
            ],
            \array_keys($beforeContext['attributes']['nested']['list'][1]),
        );

        self::assertSame('01B7X3NDEKTSV4RRFFQ69G5FAV', $beforeContext['correlationId']);
        self::assertIsInt($beforeContext['startedAt']);
        self::assertSame(UnitOfWorkType::HTTP, $beforeContext['type']);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $beforeContext['uowId']);

        self::assertJsonLikePayload($beforeContext);
        self::assertMapsSortedRecursively($beforeContext);

        self::assertSame(
            [
                'correlationId',
                'durationMs',
                'extensions',
                'finishedAt',
                'outcome',
                'startedAt',
                'type',
                'uowId',
            ],
            \array_keys($afterResult),
        );

        self::assertSame('01B7X3NDEKTSV4RRFFQ69G5FAV', $afterResult['correlationId']);
        self::assertIsInt($afterResult['durationMs']);
        self::assertGreaterThanOrEqual(0, $afterResult['durationMs']);
        self::assertIsInt($afterResult['finishedAt']);
        self::assertSame(Outcome::SUCCESS, $afterResult['outcome']);
        self::assertSame($beforeContext['startedAt'], $afterResult['startedAt']);
        self::assertSame(UnitOfWorkType::HTTP, $afterResult['type']);
        self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $afterResult['uowId']);

        self::assertSame(
            [
                'alpha',
                'nested',
                'zeta',
            ],
            \array_keys($afterResult['extensions']),
        );

        self::assertSame(
            [
                'list',
                'map',
            ],
            \array_keys($afterResult['extensions']['nested']),
        );

        self::assertSame(
            [
                'one',
                'two',
            ],
            \array_keys($afterResult['extensions']['nested']['map']),
        );

        self::assertSame(
            [
                'b',
                'a',
            ],
            $afterResult['extensions']['alpha'],
        );

        self::assertSame(
            [
                'a',
                'b',
            ],
            \array_keys($afterResult['extensions']['nested']['list'][0]),
        );

        self::assertJsonLikePayload($afterContext);
        self::assertJsonLikePayload($afterResult);
        self::assertMapsSortedRecursively($afterContext);
        self::assertMapsSortedRecursively($afterResult);

        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    public function testAfterHookReceivesErrorDescriptorAsNormalizedErrorMap(): void
    {
        $contextStore = new ContextStore();
        $recorder = new KernelRuntimeExportsNormalizedHookPayloadsRecorder();

        $runtime = self::runtime($contextStore, $recorder);

        $bodyFailure = new \RuntimeException(
            'unsafe-token Authorization Cookie session_id SELECT * FROM users /tmp/coretsia-secret',
        );

        try {
            $runtime->runUnitOfWork(
                UnitOfWorkType::HTTP,
                static function () use ($bodyFailure): never {
                    throw $bodyFailure;
                },
            );
        } catch (\RuntimeException $throwable) {
            self::assertSame($bodyFailure, $throwable);
        }

        self::assertCount(1, $recorder->afterResults);

        $result = $recorder->afterResults[0];

        self::assertSame(Outcome::FATAL_ERROR, $result['outcome']);
        self::assertArrayHasKey('error', $result);
        self::assertIsArray($result['error']);
        self::assertNotInstanceOf(ErrorDescriptor::class, $result['error']);

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'schemaVersion',
                'severity',
            ],
            \array_keys($result['error']),
        );

        self::assertArrayHasKey('code', $result['error']);
        self::assertArrayHasKey('message', $result['error']);

        self::assertJsonLikePayload($result);
        self::assertMapsSortedRecursively($result);
        self::assertPayloadDoesNotLeakUnsafeThrowableMessage($result);

        self::assertFalse($contextStore->has(ContextKeys::CORRELATION_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_ID));
        self::assertFalse($contextStore->has(ContextKeys::UOW_TYPE));
    }

    private static function runtime(
        ContextStore $contextStore,
        KernelRuntimeExportsNormalizedHookPayloadsRecorder $recorder,
    ): KernelRuntime {
        $hookRegistry = new TagRegistry();

        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_BEFORE_UOW,
            KernelRuntimeExportsNormalizedHookPayloadsBeforeHook::class,
        );

        $hookRegistry->add(
            ReservedTags::KERNEL_HOOK_AFTER_UOW,
            KernelRuntimeExportsNormalizedHookPayloadsAfterHook::class,
        );

        return new KernelRuntime(
            contextStore: $contextStore,
            resetOrchestrator: self::resetOrchestrator($contextStore),
            stopwatch: new Stopwatch(),
            uowIds: new KernelRuntimeExportsNormalizedHookPayloadsIdGenerator(
                '01ARZ3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIdProvider: new KernelRuntimeExportsNormalizedHookPayloadsCorrelationIdProvider(
                '01B7X3NDEKTSV4RRFFQ69G5FAV',
            ),
            correlationIds: new CorrelationIdGenerator(new UlidGenerator()),
            hooks: new HookInvoker(
                container: new KernelRuntimeExportsNormalizedHookPayloadsContainer([
                    KernelRuntimeExportsNormalizedHookPayloadsBeforeHook::class => new KernelRuntimeExportsNormalizedHookPayloadsBeforeHook(
                        $recorder
                    ),
                    KernelRuntimeExportsNormalizedHookPayloadsAfterHook::class => new KernelRuntimeExportsNormalizedHookPayloadsAfterHook(
                        $recorder
                    ),
                ]),
                tags: $hookRegistry,
            ),
            logger: new NullLogger(),
            tracer: new NoopTracer(),
            meter: new NoopMeter(),
            attributesMaxDepth: 10,
            attributesMaxKeys: 200,
        );
    }

    private static function resetOrchestrator(ContextStore $contextStore): ResetOrchestrator
    {
        $resetRegistry = new TagRegistry();

        $resetRegistry->add(
            ReservedTags::KERNEL_RESET,
            ContextStore::class,
        );

        return new ResetOrchestrator(
            container: new KernelRuntimeExportsNormalizedHookPayloadsContainer([
                ContextStore::class => $contextStore,
            ]),
            tagRegistry: $resetRegistry,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertJsonLikePayload(array $payload): void
    {
        self::assertJsonLikeValue($payload);
    }

    private static function assertJsonLikeValue(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        self::assertIsNotFloat($value);
        self::assertIsNotObject($value);
        self::assertIsNotResource($value);
        self::assertIsArray($value);

        foreach ($value as $nestedValue) {
            self::assertJsonLikeValue($nestedValue);
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private static function assertMapsSortedRecursively(array $payload): void
    {
        if (!\array_is_list($payload)) {
            $keys = \array_keys($payload);
            $sortedKeys = $keys;

            \usort(
                $sortedKeys,
                static fn (int|string $left, int|string $right): int => \strcmp((string)$left, (string)$right),
            );

            self::assertSame($sortedKeys, $keys);
        }

        foreach ($payload as $value) {
            if (\is_array($value)) {
                self::assertMapsSortedRecursively($value);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function assertPayloadDoesNotLeakUnsafeThrowableMessage(array $payload): void
    {
        $encoded = \json_encode($payload, \JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('unsafe-token', $encoded);
        self::assertStringNotContainsString('Authorization', $encoded);
        self::assertStringNotContainsString('Cookie', $encoded);
        self::assertStringNotContainsString('session_id', $encoded);
        self::assertStringNotContainsString('SELECT * FROM users', $encoded);
        self::assertStringNotContainsString('/tmp/coretsia-secret', $encoded);
    }
}

final class KernelRuntimeExportsNormalizedHookPayloadsRecorder
{
    /**
     * @var list<array<string, mixed>>
     */
    public array $beforeContexts = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $afterContexts = [];

    /**
     * @var list<array<string, mixed>>
     */
    public array $afterResults = [];
}

final readonly class KernelRuntimeExportsNormalizedHookPayloadsBeforeHook implements BeforeUowHookInterface
{
    public function __construct(
        private KernelRuntimeExportsNormalizedHookPayloadsRecorder $recorder,
    ) {
    }

    public function beforeUow(array $context): void
    {
        $this->recorder->beforeContexts[] = $context;
    }
}

final readonly class KernelRuntimeExportsNormalizedHookPayloadsAfterHook implements AfterUowHookInterface
{
    public function __construct(
        private KernelRuntimeExportsNormalizedHookPayloadsRecorder $recorder,
    ) {
    }

    public function afterUow(array $context, array $result): void
    {
        $this->recorder->afterContexts[] = $context;
        $this->recorder->afterResults[] = $result;
    }
}

final readonly class KernelRuntimeExportsNormalizedHookPayloadsIdGenerator implements IdGeneratorInterface
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

final readonly class KernelRuntimeExportsNormalizedHookPayloadsCorrelationIdProvider implements CorrelationIdProviderInterface
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

final readonly class KernelRuntimeExportsNormalizedHookPayloadsContainer implements ContainerInterface
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
