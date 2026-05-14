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

namespace Coretsia\Foundation\Tests\Integration;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Provider\FoundationServiceFactory;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\AbstractLogger;

final class PriorityResetUsesConfiguredResetTagTest extends TestCase
{
    public function testProviderAndPriorityResetUseConfiguredEffectiveResetTag(): void
    {
        $effectiveResetTag = 'foundation.custom_reset';
        $foundationConfig = self::foundationConfig($effectiveResetTag);

        self::assertSame(
            $effectiveResetTag,
            FoundationServiceFactory::effectiveResetTag($foundationConfig),
        );
        self::assertNotSame('kernel.reset', $effectiveResetTag);

        $providerTagRegistry = new TagRegistry();
        $builder = new ContainerBuilder(
            config: ['foundation' => $foundationConfig],
            tagRegistry: $providerTagRegistry,
        );

        $builder->register(new FoundationServiceProvider());

        self::assertContains(
            ContextStore::class,
            self::taggedServiceIds($providerTagRegistry->all($effectiveResetTag)),
            'FoundationServiceProvider must tag ContextStore under the configured effective reset tag.',
        );
        self::assertNotContains(
            ContextStore::class,
            self::taggedServiceIds($providerTagRegistry->all('kernel.reset')),
            'FoundationServiceProvider must not hardcode ContextStore under literal kernel.reset when foundation.reset.tag differs.',
        );
        self::assertContains(
            ContextStore::class,
            self::taggedServiceIds($providerTagRegistry->all('kernel.stateful')),
            'ContextStore must still keep the fixed kernel.stateful enforcement marker.',
        );

        $recorder = new PriorityResetUsesConfiguredResetTagRecorder();
        $tracer = new PriorityResetUsesConfiguredResetTagFakeTracer();
        $meter = new PriorityResetUsesConfiguredResetTagFakeMeter();
        $logger = new PriorityResetUsesConfiguredResetTagFakeLogger();

        $customAlpha = new PriorityResetUsesConfiguredResetTagService('service.alpha', $recorder);
        $customBeta = new PriorityResetUsesConfiguredResetTagService('service.beta', $recorder);
        $kernelOnly = new PriorityResetUsesConfiguredResetTagService('service.kernel_only', $recorder);

        $runtimeTagRegistry = new TagRegistry();

        $runtimeTagRegistry->add(
            $effectiveResetTag,
            'service.alpha',
            0,
            ['priority' => 10, 'group' => 'beta'],
        );
        $runtimeTagRegistry->add(
            $effectiveResetTag,
            'service.beta',
            0,
            ['priority' => 20, 'group' => 'alpha'],
        );

        /*
         * This service is deliberately more attractive than the configured-tag
         * services. If reset discovery accidentally hardcodes literal
         * kernel.reset, this service will be executed and the assertion below
         * will fail.
         */
        $runtimeTagRegistry->add(
            'kernel.reset',
            'service.kernel_only',
            0,
            ['priority' => 999, 'group' => 'aaa'],
        );

        $resetOrchestrator = FoundationServiceFactory::resetOrchestrator(
            container: new PriorityResetUsesConfiguredResetTagContainer([
                'service.alpha' => $customAlpha,
                'service.beta' => $customBeta,
                'service.kernel_only' => $kernelOnly,
            ]),
            tagRegistry: $runtimeTagRegistry,
            foundationConfig: $foundationConfig,
            stopwatch: new Stopwatch(),
            tracer: $tracer,
            meter: $meter,
            logger: $logger,
        );

        self::assertInstanceOf(ResetOrchestrator::class, $resetOrchestrator);
        self::assertSame($effectiveResetTag, $resetOrchestrator->effectiveResetTag());
        self::assertTrue($resetOrchestrator->priorityEnabled());

        $resetOrchestrator->resetAll();

        self::assertSame(
            ['service.beta', 'service.alpha'],
            $recorder->ids(),
            'Enhanced reset must discover only services under the configured effective tag and must not enumerate literal kernel.reset.',
        );
        self::assertFalse($kernelOnly->wasReset());

        self::assertResetObservabilityIsSummaryOnly($tracer, $meter, $logger);
    }

    /**
     * @return array<string,mixed>
     */
    private static function foundationConfig(string $effectiveResetTag): array
    {
        return [
            'container' => [
                'autowire_concrete' => true,
                'allow_reflection_for_concrete' => true,
            ],
            'ids' => [
                'default' => 'ulid',
            ],
            'reset' => [
                'tag' => $effectiveResetTag,
                'priority' => [
                    'enabled' => true,
                ],
                'group' => [
                    'default' => 'default',
                ],
            ],
        ];
    }

    /**
     * @param iterable<TaggedService> $taggedServices
     *
     * @return list<string>
     */
    private static function taggedServiceIds(iterable $taggedServices): array
    {
        $ids = [];

        foreach ($taggedServices as $taggedService) {
            $ids[] = $taggedService->id();
        }

        return $ids;
    }

    private static function assertResetObservabilityIsSummaryOnly(
        PriorityResetUsesConfiguredResetTagFakeTracer $tracer,
        PriorityResetUsesConfiguredResetTagFakeMeter $meter,
        PriorityResetUsesConfiguredResetTagFakeLogger $logger,
    ): void {
        self::assertCount(1, $tracer->startedSpans());
        $span = $tracer->startedSpans()[0];

        self::assertSame('foundation.reset', $span->name());
        self::assertSame(2, $span->attributes()['services_count'] ?? null);
        self::assertSame(2, $span->attributes()['groups_count'] ?? null);
        self::assertSame('ok', $span->attributes()['outcome'] ?? null);
        self::assertTrue($span->ended());

        self::assertSame(
            [
                [
                    'name' => 'foundation.reset_total',
                    'delta' => 1,
                    'labels' => ['outcome' => 'ok'],
                ],
            ],
            $meter->increments(),
        );

        self::assertCount(1, $meter->observations());
        self::assertSame('foundation.reset_duration_ms', $meter->observations()[0]['name']);
        self::assertGreaterThanOrEqual(0, $meter->observations()[0]['value']);
        self::assertSame(['outcome' => 'ok'], $meter->observations()[0]['labels']);

        foreach ($logger->records() as $record) {
            self::assertIsString($record['message']);
            self::assertNotSame('', $record['message']);

            foreach ($record['context'] as $key => $value) {
                self::assertIsString($key);
                self::assertNotContains($key, [
                    'path',
                    'raw_path',
                    'query',
                    'headers',
                    'cookies',
                    'body',
                    'token',
                    'password',
                    'secret',
                    'raw_sql',
                ]);
                self::assertTrue(
                    \is_scalar($value) || $value === null,
                    'Summary-only reset log context must stay scalar/null.',
                );
            }
        }
    }
}

final class PriorityResetUsesConfiguredResetTagRecorder
{
    /**
     * @var list<string>
     */
    private array $ids = [];

    public function record(string $id): void
    {
        $this->ids[] = $id;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return $this->ids;
    }
}

final class PriorityResetUsesConfiguredResetTagService implements ResetInterface
{
    private bool $wasReset = false;

    public function __construct(
        private readonly string $id,
        private readonly PriorityResetUsesConfiguredResetTagRecorder $recorder,
    ) {
    }

    public function reset(): void
    {
        $this->wasReset = true;
        $this->recorder->record($this->id);
    }

    public function wasReset(): bool
    {
        return $this->wasReset;
    }
}

final readonly class PriorityResetUsesConfiguredResetTagContainer implements ContainerInterface
{
    /**
     * @param array<string, object> $services
     */
    public function __construct(
        private array $services,
    ) {
    }

    public function get(string $id): mixed
    {
        if (!\array_key_exists($id, $this->services)) {
            throw new \RuntimeException('test-service-not-found');
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->services);
    }
}

final class PriorityResetUsesConfiguredResetTagFakeTracer implements TracerPortInterface
{
    /**
     * @var list<PriorityResetUsesConfiguredResetTagFakeSpan>
     */
    private array $startedSpans = [];

    public function startSpan(string $name, array $attributes = []): SpanInterface
    {
        $span = new PriorityResetUsesConfiguredResetTagFakeSpan($name, $attributes);
        $this->startedSpans[] = $span;

        return $span;
    }

    public function inSpan(
        string $name,
        callable $callback,
        array $attributes = [],
    ): mixed {
        $span = $this->startSpan($name, $attributes);

        try {
            return $callback($span);
        } finally {
            $span->end();
        }
    }

    public function currentSpan(): ?SpanInterface
    {
        return null;
    }

    /**
     * @return list<PriorityResetUsesConfiguredResetTagFakeSpan>
     */
    public function startedSpans(): array
    {
        return $this->startedSpans;
    }
}

final class PriorityResetUsesConfiguredResetTagFakeSpan implements SpanInterface
{
    /**
     * @var array<string,mixed>
     */
    private array $attributes;

    private bool $ended = false;

    /**
     * @param array<string,mixed> $attributes
     */
    public function __construct(
        private readonly string $name,
        array $attributes = [],
    ) {
        $this->attributes = $attributes;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (\is_string($key)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    public function addEvent(string $name, array $attributes = []): void
    {
        unset($name, $attributes);
    }

    public function recordException(\Throwable $throwable, array $attributes = []): void
    {
        unset($throwable, $attributes);
    }

    public function end(): void
    {
        $this->ended = true;
    }

    /**
     * @return array<string,mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    public function ended(): bool
    {
        return $this->ended;
    }
}

final class PriorityResetUsesConfiguredResetTagFakeMeter implements MeterPortInterface
{
    /**
     * @var list<array{name:string,delta:int,labels:array<string,string|int|bool>}>
     */
    private array $increments = [];

    /**
     * @var list<array{name:string,value:int,labels:array<string,string|int|bool>}>
     */
    private array $observations = [];

    public function increment(string $name, int $delta = 1, array $labels = []): void
    {
        $this->increments[] = [
            'name' => $name,
            'delta' => $delta,
            'labels' => $labels,
        ];
    }

    public function observe(string $name, int $value, array $labels = []): void
    {
        $this->observations[] = [
            'name' => $name,
            'value' => $value,
            'labels' => $labels,
        ];
    }

    /**
     * @return list<array{name:string,delta:int,labels:array<string,string|int|bool>}>
     */
    public function increments(): array
    {
        return $this->increments;
    }

    /**
     * @return list<array{name:string,value:int,labels:array<string,string|int|bool>}>
     */
    public function observations(): array
    {
        return $this->observations;
    }
}

final class PriorityResetUsesConfiguredResetTagFakeLogger extends AbstractLogger
{
    /**
     * @var list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'message' => (string)$message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level:mixed,message:string,context:array<string,mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }
}
