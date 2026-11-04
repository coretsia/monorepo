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

namespace Coretsia\Platform\Tracing\Tests\Contract;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Foundation\Observability\Tracing\NoopSpan;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceFactory;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Tracing\Module\TracingModule;
use Coretsia\Platform\Tracing\Provider\TracingServiceProvider;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;

final class TracingBoundaryNoForbiddenDependenciesContractTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_IMPORT_PREFIXES = [
        'Coretsia\\Kernel\\',
        'Coretsia\\Platform\\Http\\',
        'Coretsia\\Platform\\Logging\\',
        'Coretsia\\Platform\\Metrics\\',
        'Coretsia\\Platform\\Redaction\\',
        'Coretsia\\Platform\\Cli\\',
        'Coretsia\\Platform\\Worker\\',
        'Coretsia\\Integrations\\',
        'Coretsia\\Enterprise\\',
        'Coretsia\\Devtools\\',
        'Psr\\Http\\',
        'Psr\\Log\\',
        'OpenTelemetry\\',
        'Prometheus\\',
    ];

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_PRODUCTION_SYMBOLS = [
        'ContextStore',
        'KernelRuntime',
        'TraceContextMiddleware',
        'TracerPortInterface',
        'SamplerInterface',
        'SpanExporterInterface',
        'MeterPortInterface',
        'LoggerInterface',
        'ContextAccessorInterface',
        'CorrelationIdProviderInterface',
        'SensitiveDataRedactorInterface',
        'SensitiveKeyClassifierInterface',
        'NoopTracer',
        'NoopSpan',
        'snapshot',
        'reset',
        'setAttribute',
        'startSpan',
        'currentSpan',
    ];

    public function testProductionSourceHasNoForbiddenImportsOrInfrastructureSymbols(): void
    {
        foreach (self::productionFiles() as $file) {
            $source = (string) \file_get_contents($file);
            $tokens = \token_get_all($source, TOKEN_PARSE);
            $symbols = [];

            foreach ($tokens as $token) {
                if (!\is_array($token)) {
                    continue;
                }

                [$id, $value] = $token;

                if (\in_array($id, [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_STRING], true)) {
                    $symbols[] = \ltrim($value, '\\');
                }
            }

            foreach ($symbols as $symbol) {
                foreach (self::FORBIDDEN_IMPORT_PREFIXES as $prefix) {
                    self::assertFalse(
                        \str_starts_with($symbol, $prefix),
                        'Forbidden dependency in ' . \basename($file),
                    );
                }

                foreach (self::FORBIDDEN_PRODUCTION_SYMBOLS as $forbidden) {
                    self::assertNotSame($forbidden, $symbol, 'Forbidden symbol in ' . \basename($file));
                }
            }

            \preg_match_all('/^use\s+([^;]+);/m', $source, $matches);
            foreach ($matches[1] as $import) {
                self::assertTrue(
                    \str_starts_with($import, 'Coretsia\\Contracts\\')
                    || \str_starts_with($import, 'Coretsia\\Foundation\\')
                    || \str_starts_with($import, 'Coretsia\\Platform\\Tracing\\'),
                    'Unexpected production import in ' . \basename($file),
                );
            }
        }
    }

    public function testW3cPropagatorHasNoStateOrContextLifecycleDependencies(): void
    {
        $reflection = new \ReflectionClass(W3cTraceContextPropagation::class);
        $propagation = new W3cTraceContextPropagation();
        $header = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-03';

        self::assertTrue($reflection->isFinal());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertSame([ContextPropagationInterface::class], \array_keys($reflection->getInterfaces()));
        self::assertSame([], $propagation->extract([]));
        self::assertSame(
            [
                'trace_id' => '4bf92f3577b34da6a3ce929d0e0e4736',
                'parent_id' => '00f067aa0ba902b7',
                'trace_flags' => '03',
            ],
            $propagation->extract([
                'traceparent' => $header,
                'correlation_id' => 'caller-owned',
                'request_id' => 'caller-owned',
                'uow_id' => 'caller-owned',
            ]),
        );
        self::assertSame(
            ['traceparent' => $header],
            $propagation->inject([], $propagation->extract(['traceparent' => $header])),
        );
        self::assertSame([], $propagation->inject([], []));
        self::assertFalse($reflection->hasMethod('reset'));
        self::assertFalse($reflection->hasMethod('snapshot'));
    }

    public function testFoundationOwnsNoopTracerSpanAndContextStore(): void
    {
        $root = \dirname(__DIR__, 2);
        $reflection = new \ReflectionClass(ContextStore::class);

        self::assertSame('Coretsia\\Foundation\\Context', $reflection->getNamespaceName());
        self::assertSame(
            'Coretsia\\Foundation\\Observability\\Tracing',
            new \ReflectionClass(NoopTracer::class)->getNamespaceName(),
        );
        self::assertSame(
            'Coretsia\\Foundation\\Observability\\Tracing',
            new \ReflectionClass(NoopSpan::class)->getNamespaceName(),
        );
        self::assertSame(
            'Coretsia\\Foundation\\Observability\\Tracing',
            new \ReflectionClass(NoopContextPropagation::class)->getNamespaceName(),
        );

        foreach (['NoopTracer', 'NoopSpan', 'NoopContextPropagation', 'ContextStore'] as $className) {
            self::assertFileDoesNotExist($root . '/src/' . $className . '.php');
            self::assertFileDoesNotExist($root . '/src/Observability/Tracing/' . $className . '.php');
        }

        $tracingReflection = new \ReflectionClass(TracingModule::class);
        self::assertSame([], $tracingReflection->getProperties());
    }

    public function testOptionalTracingDefinitionDoesNotChangeFoundationResetOrTracerOwnership(): void
    {
        $foundationRoot = \dirname(__DIR__, 5) . '/packages/core/foundation';
        $config = ['foundation' => require $foundationRoot . '/config/foundation.php'];
        $context = new ContainerDefinitionContext($config);
        $provider = new FoundationServiceProvider();
        $baselineBuilder = new ContainerDefinitionBuilder();
        $provider->define($baselineBuilder, $context);
        $baseline = $baselineBuilder->build()->toDescriptorStream();

        $selectedBuilder = new ContainerDefinitionBuilder();
        $provider->define($selectedBuilder, $context);
        new TracingServiceProvider()->define($selectedBuilder, $context);
        $selected = $selectedBuilder->build()->toDescriptorStream();

        self::assertSame($baseline, \array_slice($selected, 0, -1));
        self::assertSame([
            'arguments' => [],
            'class' => W3cTraceContextPropagation::class,
            'id' => ContextPropagationInterface::class,
            'kind' => 'service.class',
            'shared' => true,
        ], \end($selected));

        $tracerServices = \array_values(
            \array_filter(
                $selected,
                static fn (array $operation): bool => ($operation['id'] ?? null) === TracerPortInterface::class,
            ),
        );
        self::assertCount(1, $tracerServices);
        self::assertSame(NoopTracer::class, $tracerServices[0]['class']);

        $baselineReset = \array_values(
            \array_filter(
                $baseline,
                static fn (array $operation): bool => ($operation['kind'] ?? null) === 'tag',
            ),
        );
        $selectedReset = \array_values(
            \array_filter(
                $selected,
                static fn (array $operation): bool => ($operation['kind'] ?? null) === 'tag',
            ),
        );
        self::assertNotSame([], $baselineReset);

        $effectiveResetTag = FoundationServiceFactory::effectiveResetTag($config['foundation']);

        self::assertCount(
            1,
            \array_filter(
                $baselineReset,
                static fn (array $operation): bool => ($operation['tag'] ?? null) === $effectiveResetTag
                    && ($operation['serviceId'] ?? null) === ContextStore::class,
            ),
        );

        self::assertCount(
            1,
            \array_filter(
                $baselineReset,
                static fn (array $operation): bool => ($operation['tag'] ?? null) === ReservedTags::KERNEL_STATEFUL
                    && ($operation['serviceId'] ?? null) === ContextStore::class,
            ),
        );

        self::assertSame($baselineReset, $selectedReset);
    }

    /**
     * @return list<string>
     */
    private static function productionFiles(): array
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        \sort($files, SORT_STRING);

        self::assertNotSame([], $files);

        return $files;
    }
}
