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

namespace Coretsia\Platform\Errors\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Errors\Provider\ErrorsServiceFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class ErrorsServiceFactoryTest extends TestCase
{
    public function testDefaultConfigurationConstructsCanonicalRegistryAndHandler(): void
    {
        $config = $this->config(self::defaults());
        $tags = new TagRegistry();
        $container = $this->createStub(ContainerInterface::class);
        $registry = ErrorsServiceFactory::exceptionMapperRegistry($tags, $container, new NullLogger(), $config);
        $handler = $this->handler($registry, $config);

        self::assertInstanceOf(ExceptionMapperRegistry::class, $registry);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $handler->handle(new \RuntimeException('PRIVATE'))->code());
        self::assertSame(false, self::property($registry, 'reportingEnabled'));
        self::assertSame(false, self::property($registry, 'mapperFailureLoggingEnabled'));
        self::assertSame(false, self::property($handler, 'reportingEnabled'));
        self::assertSame(ErrorSeverity::Info, self::property($handler, 'minSeverity'));
        self::assertSame([], self::property($handler, 'excludeCodes'));
        self::assertSame(10000, self::property($handler, 'sampleRate'));
    }

    public function testAllFiveReportingValuesAreInjectedAsImmutableConstructorPolicies(): void
    {
        $values = self::defaults();
        $values['errors.reporting.enabled'] = true;
        $values['errors.reporting.min_severity'] = 'critical';
        $values['errors.reporting.exclude_codes'] = ['CODE_A', 'CODE_B'];
        $values['errors.reporting.sample_rate'] = 1234;
        $values['errors.reporting.mapper_failures.enabled'] = true;
        $config = $this->config($values);
        $container = $this->createStub(ContainerInterface::class);
        $registry = ErrorsServiceFactory::exceptionMapperRegistry(
            new TagRegistry(),
            $container,
            new NullLogger(),
            $config,
        );
        $redactor = $this->createStub(SensitiveDataRedactorInterface::class);
        $redactor->method('redactJsonLike')->willReturnArgument(0);
        $reporter = new NoopErrorReporter();
        $handler = $this->handler($registry, $config, $redactor, $reporter);

        self::assertTrue(new \ReflectionClass($registry)->isReadOnly());
        self::assertTrue(new \ReflectionClass($handler)->isReadOnly());
        self::assertSame(true, self::property($registry, 'reportingEnabled'));
        self::assertSame(true, self::property($registry, 'mapperFailureLoggingEnabled'));
        self::assertSame(true, self::property($handler, 'reportingEnabled'));
        self::assertSame(ErrorSeverity::Critical, self::property($handler, 'minSeverity'));
        self::assertSame(['CODE_A', 'CODE_B'], self::property($handler, 'excludeCodes'));
        self::assertSame(1234, self::property($handler, 'sampleRate'));
        self::assertSame($registry, self::property($handler, 'registry'));
        self::assertSame($redactor, self::property($handler, 'redactor'));
        self::assertSame($reporter, self::property($handler, 'reporter'));
    }

    /**
     * @return array<string,array{string,mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'enabled string' => ['errors.reporting.enabled', 'false'],
            'enabled integer' => ['errors.reporting.enabled', 1],
            'severity type' => ['errors.reporting.min_severity', 1],
            'severity unknown' => ['errors.reporting.min_severity', 'fatal'],
            'severity wrong case' => ['errors.reporting.min_severity', 'Error'],
            'excluded codes scalar' => ['errors.reporting.exclude_codes', 'CODE'],
            'excluded codes map' => ['errors.reporting.exclude_codes', ['code' => 'CODE']],
            'excluded codes empty entry' => ['errors.reporting.exclude_codes', ['']],
            'excluded codes whitespace' => ['errors.reporting.exclude_codes', ["CODE\tINVALID"]],
            'excluded codes non-string' => ['errors.reporting.exclude_codes', [123]],
            'sample rate negative' => ['errors.reporting.sample_rate', -1],
            'sample rate fraction' => ['errors.reporting.sample_rate', 0.5],
            'sample rate string' => ['errors.reporting.sample_rate', '10000'],
            'sample rate too large' => ['errors.reporting.sample_rate', 10001],
            'mapper diagnostics string' => ['errors.reporting.mapper_failures.enabled', 'true'],
            'mapper diagnostics integer' => ['errors.reporting.mapper_failures.enabled', 1],
        ];
    }

    #[DataProvider('invalidValues')]
    public function testMalformedReportingValueFailsBothFactoriesDuringConstruction(string $key, mixed $value): void
    {
        $values = self::defaults();
        $values[$key] = $value;
        $this->assertRejectedByBothFactories($values);
    }

    public function testEveryMissingReportingValueFailsBothFactoriesDuringConstruction(): void
    {
        foreach (\array_keys(self::defaults()) as $missing) {
            $values = self::defaults();
            unset($values[$missing]);
            $this->assertRejectedByBothFactories($values);
        }
    }

    public function testSamplingRateAcceptsBothInclusiveIntegerBoundaries(): void
    {
        foreach ([0, 10000] as $boundary) {
            $values = self::defaults();
            $values['errors.reporting.sample_rate'] = $boundary;
            $config = $this->config($values);
            $registry = ErrorsServiceFactory::exceptionMapperRegistry(
                new TagRegistry(),
                $this->createStub(ContainerInterface::class),
                new NullLogger(),
                $config,
            );
            $handler = $this->handler($registry, $config);

            self::assertSame($boundary, self::property($handler, 'sampleRate'));
        }
    }

    public function testMapperDiagnosticsFlagIsIndependentOfReporterFilters(): void
    {
        $values = self::defaults();
        $values['errors.reporting.enabled'] = true;
        $values['errors.reporting.mapper_failures.enabled'] = true;
        $values['errors.reporting.min_severity'] = 'critical';
        $values['errors.reporting.exclude_codes'] = ['CORETSIA_UNHANDLED_EXCEPTION'];
        $values['errors.reporting.sample_rate'] = 0;
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'invalid.mapper');
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new \stdClass());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with(
            'error.mapper_failed',
            ['operation' => 'map', 'reason' => 'invalid_service'],
        );
        $registry = ErrorsServiceFactory::exceptionMapperRegistry($tags, $container, $logger, $this->config($values));

        self::assertNull($registry->map(new \RuntimeException('SYNTHETIC_PRIVATE')));
    }

    public function testReportingConfigurationIsNotReadDuringMapperDispatchOrHandle(): void
    {
        $values = self::defaults();
        $readCounts = ['has' => 0, 'get' => 0];
        $config = $this->config($values, $readCounts);
        $tags = new TagRegistry();
        $tags->add(ReservedTags::ERROR_MAPPER, 'owner.mapper');
        $mapper = $this->createMock(ExceptionMapperInterface::class);
        $mapper->expects(self::exactly(2))->method('map')->willReturn(null);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($mapper);
        $registry = ErrorsServiceFactory::exceptionMapperRegistry($tags, $container, new NullLogger(), $config);
        $handler = $this->handler($registry, $config);
        self::assertSame(['has' => 10, 'get' => 10], $readCounts);

        foreach ([1, 2] as $index) {
            self::assertSame(
                'CORETSIA_UNHANDLED_EXCEPTION',
                $handler->handle(new \RuntimeException('SYNTHETIC_PRIVATE_' . $index))->code(),
            );
        }

        self::assertSame(['has' => 10, 'get' => 10], $readCounts);
    }

    public function testFactoryUsesInjectedPortsInsteadOfConstructingPackageLocalReplacements(): void
    {
        $source = \file_get_contents(new \ReflectionClass(ErrorsServiceFactory::class)->getFileName());
        self::assertIsString($source);

        foreach (
            [
                'DefaultSensitiveDataRedactor',
                'RedactionServiceProvider',
                'new NoopErrorReporter',
                'new ConfigValidator',
                'new ArrayConfigRepository',
                'new TagRegistry',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    /** @return array<string,mixed> */
    private static function defaults(): array
    {
        $reporting = (require \dirname(__DIR__, 2) . '/config/errors.php')['reporting'];

        return [
            'errors.reporting.enabled' => $reporting['enabled'],
            'errors.reporting.min_severity' => $reporting['min_severity'],
            'errors.reporting.exclude_codes' => $reporting['exclude_codes'],
            'errors.reporting.sample_rate' => $reporting['sample_rate'],
            'errors.reporting.mapper_failures.enabled' => $reporting['mapper_failures']['enabled'],
        ];
    }

    /**
     * @param array<string,mixed> $values
     * @param array{has:int,get:int}|null $readCounts
     */
    private function config(array $values, ?array &$readCounts = null): ConfigRepositoryInterface
    {
        $config = $this->createStub(ConfigRepositoryInterface::class);
        $config->method('has')->willReturnCallback(
            static function (string $key) use ($values, &$readCounts): bool {
                if ($readCounts !== null) {
                    ++$readCounts['has'];
                }

                return \array_key_exists($key, $values);
            },
        );
        $config->method('get')->willReturnCallback(
            static function (string $key, mixed $default = null) use ($values, &$readCounts): mixed {
                if ($readCounts !== null) {
                    ++$readCounts['get'];
                }

                return $values[$key] ?? $default;
            },
        );

        return $config;
    }

    /** @param array<string,mixed> $values */
    private function assertRejectedByBothFactories(array $values): void
    {
        $config = $this->config($values);
        $registry = new ExceptionMapperRegistry(
            new TagRegistry(),
            $this->createStub(ContainerInterface::class),
            new NullLogger(),
            false,
            false,
        );

        foreach (
            [
                fn (): ExceptionMapperRegistry => ErrorsServiceFactory::exceptionMapperRegistry(
                    new TagRegistry(),
                    $this->createStub(ContainerInterface::class),
                    new NullLogger(),
                    $config,
                ),
                fn (): ErrorHandler => $this->handler($registry, $config),
            ] as $construct
        ) {
            try {
                $construct();
                self::fail('Invalid reporting configuration must fail during service construction.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('Invalid errors reporting configuration.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    private function handler(
        ExceptionMapperRegistry $registry,
        ConfigRepositoryInterface $config,
        ?SensitiveDataRedactorInterface $redactor = null,
        ?NoopErrorReporter $reporter = null,
    ): ErrorHandler {
        $redactor ??= $this->createStub(SensitiveDataRedactorInterface::class);
        $correlations = $this->createStub(CorrelationIdProviderInterface::class);
        $correlations->method('correlationId')->willReturn(null);

        return ErrorsServiceFactory::errorHandler(
            $registry,
            new DefaultExceptionMapper(),
            $redactor,
            $reporter ?? new NoopErrorReporter(),
            $correlations,
            new NoopTracer(),
            new NoopMeter(),
            new Stopwatch(),
            $config,
        );
    }

    private static function property(object $service, string $name): mixed
    {
        return new \ReflectionProperty($service, $name)->getValue($service);
    }
}
