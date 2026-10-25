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

namespace Coretsia\Platform\Errors\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Errors\Provider\ErrorsServiceFactory;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class ErrorsProviderSourceDefinitionsParityTest extends TestCase
{
    public function testSourceContainerAndCompiledGraphPreserveExactlyTheSameServicesAliasesAndDependencies(): void
    {
        $provider = new ErrorsServiceProvider();
        $declarations = new ContainerDefinitionBuilder();
        $provider->define($declarations, new ContainerDefinitionContext([]));
        $definitionSet = $declarations->build();

        $foundationConfig = require \dirname(__DIR__, 4) . '/core/foundation/config/foundation.php';
        $config = ['foundation' => $foundationConfig];
        $context = new ContainerDefinitionContext($config);
        $composition = new ContainerDefinitionBuilder();
        new FoundationServiceProvider()->define($composition, $context);
        new RedactionServiceProvider()->define($composition, $context);
        $provider->define($composition, $context);

        $compiledDefinitions = $composition->build();
        $compiler = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch());
        $compiled = $compiler->compile($compiledDefinitions)->toArray();

        $sourceBuilder = new ContainerBuilder($config);
        $sourceBuilder->registerProviders([
            new FoundationServiceProvider(),
            new RedactionServiceProvider(),
            $provider,
        ]);
        $source = $sourceBuilder->build();

        $ids = [
            DefaultExceptionMapper::class,
            ExceptionMapperRegistry::class,
            ErrorHandler::class,
            ErrorHandlerInterface::class,
        ];
        foreach ($ids as $id) {
            self::assertTrue($source->has($id), $id);
        }
        self::assertSame(ErrorHandler::class, $compiled['aliases'][ErrorHandlerInterface::class]);
        $errorsServices = \array_intersect_key($compiled['services'], \array_fill_keys(\array_slice($ids, 0, 3), true));
        self::assertSame([
            ErrorHandler::class,
            DefaultExceptionMapper::class,
            ExceptionMapperRegistry::class,
        ], \array_keys($errorsServices));

        $operations = $definitionSet->toDescriptorStream();
        $expected = [];
        foreach ($operations as $operation) {
            if (!isset($operation['id'])) {
                continue;
            }
            $expected[$operation['id']] = $operation;
        }
        self::assertSame(
            [DefaultExceptionMapper::class, ExceptionMapperRegistry::class, ErrorHandler::class],
            \array_keys($expected)
        );
        self::assertSame('service.class', $expected[DefaultExceptionMapper::class]['kind']);
        self::assertSame([], $expected[DefaultExceptionMapper::class]['arguments']);
        self::assertSame(ErrorsServiceFactory::class, $expected[ExceptionMapperRegistry::class]['factoryClass']);
        self::assertSame('exceptionMapperRegistry', $expected[ExceptionMapperRegistry::class]['method']);
        self::assertSame(ErrorsServiceFactory::class, $expected[ErrorHandler::class]['factoryClass']);
        self::assertSame('errorHandler', $expected[ErrorHandler::class]['method']);
        self::assertSame([], $errorsServices[DefaultExceptionMapper::class]['arguments']);
        self::assertSame('class', $errorsServices[DefaultExceptionMapper::class]['type']);
        self::assertSame(
            ['class' => $expected[DefaultExceptionMapper::class]['class']],
            $errorsServices[DefaultExceptionMapper::class]['construction'],
        );

        foreach ([ExceptionMapperRegistry::class, ErrorHandler::class] as $id) {
            self::assertSame('factory', $errorsServices[$id]['type']);
            self::assertSame([
                'factory' => [
                    'class' => $expected[$id]['factoryClass'],
                    'kind' => 'class-method',
                    'method' => $expected[$id]['method'],
                ],
            ], $errorsServices[$id]['construction']);
        }

        self::assertSame(
            $expected[ExceptionMapperRegistry::class]['arguments'],
            $errorsServices[ExceptionMapperRegistry::class]['arguments'],
        );
        self::assertSame(
            $expected[ErrorHandler::class]['arguments'],
            $errorsServices[ErrorHandler::class]['arguments'],
        );
        foreach ($errorsServices as $service) {
            self::assertTrue($service['shared']);
        }

        self::assertSame([
            TagRegistry::class,
            ContainerInterface::class,
            LoggerInterface::class,
            ConfigRepositoryInterface::class,
        ], self::referenceIds($expected[ExceptionMapperRegistry::class]['arguments']));
        self::assertSame([
            ExceptionMapperRegistry::class,
            DefaultExceptionMapper::class,
            SensitiveDataRedactorInterface::class,
            ErrorReporterPortInterface::class,
            CorrelationIdProviderInterface::class,
            TracerPortInterface::class,
            MeterPortInterface::class,
            Stopwatch::class,
            ConfigRepositoryInterface::class,
        ], self::referenceIds($expected[ErrorHandler::class]['arguments']));

        $required = $definitionSet->requiredServiceIds();
        foreach (
            \array_merge(
                self::referenceIds($expected[ExceptionMapperRegistry::class]['arguments']),
                self::referenceIds($expected[ErrorHandler::class]['arguments']),
            ) as $id
        ) {
            if (\in_array($id, [ExceptionMapperRegistry::class, DefaultExceptionMapper::class], true)) {
                continue;
            }
            self::assertContains($id, $required);
        }

        self::assertCount(3, $errorsServices);
        self::assertSame(4, \count($operations));
        self::assertSame($compiled, $compiler->compile($compiledDefinitions)->toArray());
    }

    /**
     * @param list<array{id: string, type: string}> $references
     * @return list<string>
     */
    private static function referenceIds(array $references): array
    {
        foreach ($references as $reference) {
            self::assertSame('service', $reference['type']);
        }

        return \array_column($references, 'id');
    }
}
