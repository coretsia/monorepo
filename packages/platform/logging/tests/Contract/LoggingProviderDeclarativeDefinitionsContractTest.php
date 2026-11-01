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

namespace Coretsia\Platform\Logging\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class LoggingProviderDeclarativeDefinitionsContractTest extends TestCase
{
    public function testProviderDeclaresExactlyOneDeterministicFactoryAndSevenRequiredPorts(): void
    {
        $provider = new LoggingServiceProvider();
        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, $provider);

        $set = self::definitions([]);
        $operations = $set->toDescriptorStream();
        $ports = self::ports();

        self::assertCount(1, $operations);
        self::assertSame('service.factory.class-method', $operations[0]['kind']);
        self::assertSame(LoggerInterface::class, $operations[0]['id']);
        self::assertSame(LoggingServiceFactory::class, $operations[0]['factoryClass']);
        self::assertSame('createLogger', $operations[0]['method']);
        self::assertSame(true, $operations[0]['shared']);
        self::assertSame($ports, \array_column($operations[0]['arguments'], 'id'));
        foreach ($operations[0]['arguments'] as $argument) {
            self::assertSame('service', $argument['type']);
        }
        $required = $set->requiredServiceIds();
        self::assertSame($ports, \array_values(\array_intersect($ports, $required)));
        self::assertCount(7, $required);
        self::assertContains(SensitiveKeyClassifierInterface::class, $required);
        self::assertSame(
            $operations,
            ContainerDefinitionSet::fromValidatedState($operations, $required)->toDescriptorStream(),
        );

        $method = new \ReflectionMethod(LoggingServiceFactory::class, 'createLogger');
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertSame(LoggerInterface::class, $method->getReturnType()->getName());
        self::assertSame(
            $ports,
            \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getType()->getName(),
                $method->getParameters(),
            ),
        );
    }

    public function testSourceDefinitionsAndCompiledGraphHaveIdenticalFactoryReferences(): void
    {
        $set = self::definitions([]);
        // Compiler-only graph stubs: no service is instantiated or resolved.
        // Required ports must exist in the compiled graph before reference validation.
        $composition = new ContainerDefinitionBuilder();
        foreach (self::ports() as $port) {
            $composition->classService($port, \stdClass::class);
        }
        new LoggingServiceProvider()->define($composition, new ContainerDefinitionContext([]));
        $compiledSet = $composition->build();
        $compiler = new ContainerCompiler(new NoopTracer(), new NoopMeter(), new NoopLogger(), new Stopwatch());
        $compiled = $compiler->compile($compiledSet)->toArray();
        $service = $compiled['services'][LoggerInterface::class];
        $operation = $set->toDescriptorStream()[0];

        self::assertSame('factory', $service['type']);
        self::assertSame([
            'factory' => [
                'class' => LoggingServiceFactory::class,
                'kind' => 'class-method',
                'method' => 'createLogger',
            ],
        ], $service['construction']);
        self::assertSame($operation['arguments'], $service['arguments']);
        self::assertSame($compiled, $compiler->compile($compiledSet)->toArray());
        self::assertSame(
            $set->toDescriptorStream(),
            self::definitions([
                'logging' => ['driver' => 'file', 'file' => ['path' => '/not-used', 'name' => 'app']],
            ])->toDescriptorStream(),
        );
        self::assertSame(
            $set->requiredServiceIds(),
            self::definitions(['logging' => ['driver' => 'null']])->requiredServiceIds(),
        );
    }

    public function testRegisterDelegatesOnlyThroughDeclarativeDefinitionsAndAddsNoTags(): void
    {
        $provider = new LoggingServiceProvider();
        $builder = new ContainerBuilder([]);
        $provider->register($builder);
        self::assertTrue($builder->build()->has(LoggerInterface::class));

        $source = (string) \file_get_contents(new \ReflectionClass($provider)->getFileName());
        self::assertStringContainsString('$builder->assertDefinitionProviderRegistrationAllowed();', $source);
        self::assertStringContainsString('$builder->registerDefinitionProvider($this);', $source);
        self::assertStringNotContainsString('->tag(', $source);
        self::assertStringNotContainsString('->instance(', $source);
        self::assertStringNotContainsString('->factory(', $source);
        self::assertStringNotContainsString('Coretsia\\Platform\\Redaction\\', $source);
        foreach (['reset', 'ScopedFileLock', 'php://stderr', 'logging.config'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                \json_encode(self::definitions([])->toDescriptorStream(), \JSON_THROW_ON_ERROR),
            );
        }
    }

    public function testDescriptorsContainOnlyDeterministicScalarsAndServiceReferences(): void
    {
        $set = self::definitions([]);
        $this->assertOnlyScalars($set->toDescriptorStream());
        $encoded = \json_encode([
            'operations' => $set->toDescriptorStream(),
            'required' => $set->requiredServiceIds(),
        ], \JSON_THROW_ON_ERROR);
        self::assertIsString($encoded);
        self::assertStringNotContainsString('php://stderr', $encoded);
        self::assertStringNotContainsString('logging.config', $encoded);
        self::assertStringNotContainsString('fileDirectory', $encoded);
    }

    /** @return list<string> */
    private static function ports(): array
    {
        return [
            ConfigRepositoryInterface::class,
            ContextAccessorInterface::class,
            CorrelationIdProviderInterface::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifierInterface::class,
            ClockInterface::class,
            AppTargetProviderInterface::class,
        ];
    }

    /** @param array<string,mixed> $config */
    private static function definitions(array $config): ContainerDefinitionSet
    {
        $builder = new ContainerDefinitionBuilder();
        new LoggingServiceProvider()->define($builder, new ContainerDefinitionContext($config));
        return $builder->build();
    }

    private static function assertOnlyScalars(mixed $value): void
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                self::assertOnlyScalars($item);
            }
            return;
        }
        self::assertTrue($value === null || \is_string($value) || \is_int($value) || \is_bool($value));
    }
}
