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

namespace Coretsia\Platform\ProblemDetails\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class ProblemDetailsProviderDefinitionsParityTest extends TestCase
{
    public function testProviderDefinesOnlyCanonicalServicesRequiredPortsAndOnePriorityTag(): void
    {
        $set = self::definitions();
        $operations = $set->toDescriptorStream();
        $reference = static fn (string $id): array => ContainerValueReference::service($id)->toArray();

        self::assertSame([
            ErrorHandlerInterface::class,
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
        ], $set->requiredServiceIds());
        self::assertSame([
            [
                'arguments' => [],
                'class' => ErrorDescriptorToProblemDetailsAdapter::class,
                'id' => ErrorDescriptorToProblemDetailsAdapter::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
            [
                'arguments' => [],
                'class' => ProblemDetailsJsonRenderer::class,
                'id' => ProblemDetailsJsonRenderer::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
            [
                'arguments' => [
                    $reference(ErrorHandlerInterface::class),
                    $reference(ErrorDescriptorToProblemDetailsAdapter::class),
                    $reference(ProblemDetailsJsonRenderer::class),
                    $reference(ResponseFactoryInterface::class),
                    $reference(StreamFactoryInterface::class),
                ],
                'class' => HttpErrorHandlingMiddleware::class,
                'id' => HttpErrorHandlingMiddleware::class,
                'kind' => 'service.class',
                'shared' => true,
            ],
            [
                'kind' => 'tag',
                'meta' => [],
                'priority' => 1000,
                'serviceId' => HttpErrorHandlingMiddleware::class,
                'tag' => ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE,
            ],
        ], $operations);

        self::assertSame('http.middleware.system_pre', ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE);
        self::assertSame([], $operations[3]['meta']);
        self::assertSame([], \array_intersect(['owner', 'path', 'mode'], \array_keys($operations[3]['meta'])));
        self::assertSame(
            1,
            \count(
                \array_filter(
                    $operations,
                    static fn (array $operation): bool => $operation['kind'] === 'tag',
                ),
            ),
        );
    }

    public function testCanonicalHttpMiddlewareCatalogOwnsSameMiddlewareAndPriority(): void
    {
        $catalog = \file_get_contents(\dirname(__DIR__, 5) . '/docs/ssot/http-middleware-catalog.md');
        self::assertIsString($catalog);
        self::assertSame(
            1,
            \substr_count(
                $catalog,
                '| `1000`   | `platform/problem-details` | '
                . '`\\Coretsia\\Platform\\ProblemDetails\\Http\\Middleware\\HttpErrorHandlingMiddleware::class` |',
            ),
        );
        self::assertStringContainsString('## Slot: `http.middleware.system_pre`', $catalog);
    }

    public function testSourceAndCompiledGraphsRetainTheSameProblemDetailsDeclarations(): void
    {
        $providerSet = self::definitions();
        $source = new ContainerBuilder(['problem_details' => []]);
        $source->applyDefinitions($providerSet);
        $sourceIds = $source->serviceIds();
        $ids = [
            ErrorDescriptorToProblemDetailsAdapter::class,
            ProblemDetailsJsonRenderer::class,
            HttpErrorHandlingMiddleware::class,
        ];
        foreach ($ids as $id) {
            self::assertContains($id, $sourceIds);
        }
        self::assertCount(1, $source->tagRegistry()->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE));

        // Model-only port declarations: the HTTP owner supplies real factories at runtime.
        // This contract compares descriptors; it does not claim a runnable HTTP pipeline.
        $ports = new ContainerDefinitionBuilder();
        foreach ($providerSet->requiredServiceIds() as $id) {
            $ports->classService($id, \stdClass::class);
        }
        $merged = ContainerDefinitionSet::merge($ports->build(), $providerSet);
        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )->compile($merged)->toArray();

        foreach ($providerSet->toDescriptorStream() as $operation) {
            if ($operation['kind'] !== 'service.class') {
                continue;
            }
            self::assertSame([
                'arguments' => $operation['arguments'],
                'construction' => ['class' => $operation['class']],
                'id' => $operation['id'],
                'shared' => $operation['shared'],
                'type' => 'class',
            ], $graph['services'][$operation['id']]);
        }
        self::assertSame([], $graph['aliases']);
        self::assertSame([], $graph['parameters']);
        self::assertSame([ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE], \array_keys($graph['tags']));
        self::assertSame([
            [
                'id' => HttpErrorHandlingMiddleware::class,
                'meta' => [],
                'priority' => 1000,
            ],
        ], $graph['tags'][ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE]);
        $tagged = $source->tagRegistry()->all(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE)[0];
        self::assertSame(HttpErrorHandlingMiddleware::class, $tagged->id());
        self::assertSame(1000, $tagged->priority());
        self::assertSame([], $tagged->meta());
    }

    public function testProviderDescriptionsAreDeterministicAndContainNoRuntimeObjects(): void
    {
        $first = self::definitions();
        $second = self::definitions(['problem_details' => ['unexpected' => 'ignored']]);
        self::assertSame($first->toDescriptorStream(), $second->toDescriptorStream());
        self::assertSame($first->requiredServiceIds(), $second->requiredServiceIds());
        self::assertSame(
            $first->toDescriptorStream(),
            ContainerDefinitionSet::fromValidatedState(
                $first->toDescriptorStream(),
                $first->requiredServiceIds(),
            )->toDescriptorStream(),
        );
        self::assertIsString(\json_encode($first->toDescriptorStream(), \JSON_THROW_ON_ERROR));
        self::assertNoRuntimeValues($first->toDescriptorStream());
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function definitions(array $config = ['problem_details' => []]): ContainerDefinitionSet
    {
        $definitions = new ContainerDefinitionBuilder();
        new ProblemDetailsServiceProvider()->define($definitions, new ContainerDefinitionContext($config));

        return $definitions->build();
    }

    private static function assertNoRuntimeValues(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::assertNoRuntimeValues($item);
            }

            return;
        }

        self::assertTrue(
            $value === null || \is_string($value) || \is_int($value) || \is_bool($value),
            'Provider descriptors must not contain closures, runtime objects, or resources.',
        );
    }
}
