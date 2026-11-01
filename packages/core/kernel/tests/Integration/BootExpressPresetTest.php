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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Provider\KernelServiceFactory;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Tests\Support\AppBuilder;
use Coretsia\Kernel\Tests\Support\AppBuilderBootResult;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class BootExpressPresetTest extends TestCase
{
    public function testExpressPresetBootsWithCanonicalModuleClosure(): void
    {
        $result = AppBuilder::bootExpress($this);
        try {
            $plan = $result->modulePlan();
            self::assertSame('web', $plan->app());
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.logging',
                    'platform.redaction',
                ],
                \array_map(
                    static fn (ModuleId $id): string => $id->value(),
                    $plan->enabled(),
                ),
            );
            self::assertSame(
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.redaction',
                    'platform.errors',
                    'platform.cli',
                    'platform.logging',
                ],
                \array_map(
                    static fn (ModuleId $id): string => $id->value(),
                    $plan->topologicalOrder(),
                ),
            );
            self::assertNotContains('platform.http', $plan->toArray()['enabled']);
            foreach (['module-manifest.php', 'config.php', 'container.php', 'generation-manifest.php'] as $name) {
                self::assertArrayHasKey($name, $result->artifactPaths());
                self::assertFileExists($result->artifactPaths()[$name]);
            }
            self::assertInstanceOf(ContainerInterface::class, $result->container());
            self::assertLoggingRuntimeIdentity($result);
        } finally {
            AppBuilder::removeTree($result->applicationRoot());
        }
    }

    private static function assertLoggingRuntimeIdentity(AppBuilderBootResult $result): void
    {
        $compiled = $result->container();
        $seed = $compiled->get(ModulePlan::class);
        $config = $compiled->get(ConfigRepositoryInterface::class);
        self::assertInstanceOf(ModulePlan::class, $seed);
        self::assertInstanceOf(ConfigRepositoryInterface::class, $config);
        self::assertSame('auto', $config->get('logging.service_name'));
        self::assertSame('stream', $config->get('logging.driver'));
        self::assertNotSame($result->modulePlan(), $seed);
        self::assertSame($result->modulePlan()->toArray(), $seed->toArray());

        $sourceBuilder = new ContainerBuilder(config: $config->all());
        $sourceBuilder->instance(ModulePlan::class, $seed);
        $sourceBuilder->instance(ConfigRepositoryInterface::class, $config);
        $sourceBuilder->register(
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
            new RedactionServiceProvider(),
            new LoggingServiceProvider(),
        );
        $source = $sourceBuilder->build();

        foreach ([$source, $compiled] as $container) {
            self::assertSame($seed, $container->get(ModulePlan::class));
            self::assertSame($seed, $container->get(AppTargetProviderInterface::class));
            self::assertSame('web', $container->get(AppTargetProviderInterface::class)->app());

            $logger = $container->get(LoggerInterface::class);
            self::assertInstanceOf(StreamLogger::class, $logger);
            self::assertSame($logger, $container->get(LoggerInterface::class));
            self::assertSame('web', new \ReflectionProperty(StreamLogger::class, 'serviceName')->getValue($logger));
            self::assertSame('web', new \ReflectionProperty(StreamLogger::class, 'appTarget')->getValue($logger));
        }

        $artifact = new PhpArtifactReader()->readExact($result->artifactPaths()['container.php']);
        $payload = $artifact['envelope']['payload'];
        self::assertSame(
            [
                'arguments' => [
                    ['id' => ModulePlan::class, 'type' => 'service'],
                ],
                'construction' => [
                    'factory' => [
                        'class' => KernelServiceFactory::class,
                        'kind' => 'class-method',
                        'method' => 'appTargetProvider',
                    ],
                ],
                'id' => AppTargetProviderInterface::class,
                'shared' => true,
                'type' => 'factory',
            ],
            $payload['services'][AppTargetProviderInterface::class],
        );
        self::assertArrayNotHasKey(AppTargetProviderInterface::class, $payload['aliases']);
        self::assertArrayNotHasKey(ModulePlan::class, $payload['services']);
        self::assertSame(
            LoggingServiceFactory::class,
            $payload['services'][LoggerInterface::class]['construction']['factory']['class'],
        );

        $directory = $result->applicationRoot() . '/logs';
        self::assertTrue(\mkdir($directory));
        $fileConfig = $config->all();
        $fileConfig['logging']['driver'] = 'file';
        $fileConfig['logging']['file']['path'] = $directory;

        foreach ([$source, $compiled] as $container) {
            $fileLogger = LoggingServiceFactory::createLogger(
                new ArrayConfigRepository($fileConfig),
                $container->get(ContextAccessorInterface::class),
                $container->get(CorrelationIdProviderInterface::class),
                $container->get(SensitiveDataRedactorInterface::class),
                $container->get(SensitiveKeyClassifierInterface::class),
                $container->get(ClockInterface::class),
                $container->get(AppTargetProviderInterface::class),
            );
            self::assertInstanceOf(StreamLogger::class, $fileLogger);
            $fileLogger->info('kernel.boot.logging.identity');
        }

        $files = \glob($directory . '/web-app-*.jsonl');
        self::assertIsArray($files);
        self::assertNotEmpty($files);
        $records = [];

        foreach ($files as $file) {
            $contents = \file_get_contents($file);
            self::assertIsString($contents);
            $records = [...$records, ...\explode("\n", \rtrim($contents, "\n"))];
        }

        self::assertCount(2, $records);

        foreach ($records as $line) {
            $record = \json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame('web', $record['service_name']);
            self::assertSame('web', $record['app_target']);
            self::assertSame('kernel.boot.logging.identity', $record['message']);
        }
    }
}
