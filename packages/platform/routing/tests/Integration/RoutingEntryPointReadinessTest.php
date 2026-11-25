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

namespace Coretsia\Platform\Routing\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Runtime\RoutingArtifactReadiness;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class RoutingEntryPointReadinessTest extends TestCase
{
    public function testActualSourceDefinitionsAndSelectedReadinessAreLazyUntilGuard(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $path = $root . '/var/routing-cache/web/routes.php';
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        self::assertTrue($source->get(ModulePlan::class)->hasEnabledModule('platform.routing'));
        self::assertTrue($source->has(RoutingArtifactReadinessInterface::class));
        self::assertTrue($source->has(RouterInterface::class));
        self::assertFileDoesNotExist($path);
        $envelope = $source->get(RoutesCompiler::class)->compile();
        foreach ([null, '<?php return [];'] as $bytes) {
            if ($bytes !== null) {
                RoutingIntegrationFixture::write($path, $bytes);
            }
            $container = RoutingIntegrationFixture::source($root, AppTarget::Web);
            try {
                self::allow(
                    $container->get(ConfigRepositoryInterface::class),
                    $container->get(ModulePlan::class),
                    $container,
                );
                self::fail('Expected readiness failure before HTTP runtime construction.');
            } catch (ContainerException $exception) {
                // Check the safe container boundary and the owner failure independently.
                self::assertSame('container-definition-class-instantiation-failed', $exception->getMessage());
                try {
                    $container->get(RoutesArtifactReader::class)->read();
                    self::fail('Expected owner artifact failure.');
                } catch (RoutesArtifactInvalidException $failure) {
                    self::assertSame(
                        $bytes === null
                            ? RoutesArtifactInvalidException::REASON_MISSING
                            : RoutesArtifactInvalidException::REASON_MALFORMED,
                        $failure->reason(),
                    );
                }
            }
        }
        self::assertTrue($source->get(RoutesArtifactWriter::class)->write($envelope));
        self::allow($source->get(ConfigRepositoryInterface::class), $source->get(ModulePlan::class), $source);
        $table = $source->get(CompiledRouteTable::class);
        $router = $source->get(RouterInterface::class);
        $readiness = $source->get(RoutingArtifactReadinessInterface::class);
        self::assertSame($table, new \ReflectionProperty(CompiledRouter::class, 'routes')->getValue($router));
        self::assertSame(
            $table,
            new \ReflectionProperty(RoutingArtifactReadiness::class, 'routeTable')->getValue($readiness),
        );
        self::assertSame('z.web', $router->match('GET', '/items/42')?->name());

        // Built-in factory validation must also precede selected Routing readiness.
        $factory = RoutingIntegrationFixture::externalFactory();
        $bindings = [ResponseFactoryInterface::class => $factory, StreamFactoryInterface::class => $factory];
        $plan = $source->get(ModulePlan::class);
        $config = self::config('http.classic', 'nyholm');
        $expected = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['nyholm'];
        if (!\class_exists($expected['response'])) {
            $probe = new RoutingReadinessContainer($bindings);
            try {
                self::allow($config, $plan, $probe);
                self::fail('Expected unavailable factory rejection.');
            } catch (\RuntimeException $exception) {
                self::assertSame('http-factory-implementation-unavailable', $exception->getMessage());
            }
            self::assertSame([], $probe->reads);
            // This alias is confined to the isolated consumer process.
            self::assertTrue(\class_alias($factory::class, $expected['response']));
        }
        $probe = new RoutingReadinessContainer($bindings);
        try {
            self::allow($config, $plan, $probe);
            self::fail('Expected mismatched factory rejection.');
        } catch (\RuntimeException $exception) {
            self::assertSame('http-factory-selection-mismatch', $exception->getMessage());
        }
        self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $probe->reads);

        $app = require $root . '/config/app.php';
        $app['moduleOverrides']['web']['include'] = ['platform.http'];
        $app['moduleOverrides']['web']['exclude'][] = 'platform.routing-fixture-web';
        $app['moduleOverrides']['web']['exclude'][] = 'platform.routing';
        self::assertTrue(\unlink($root . '/apps/web/config/routing.php'));
        self::assertTrue(\unlink($path));
        RoutingIntegrationFixture::php($root . '/config/app.php', $app);
        $withoutRouting = RoutingIntegrationFixture::source($root, AppTarget::Web);
        self::assertFalse($withoutRouting->get(ModulePlan::class)->hasEnabledModule('platform.routing'));
        self::assertFalse($withoutRouting->has(RoutingArtifactReadinessInterface::class));
        $factory = RoutingIntegrationFixture::externalFactory();
        $probe = new RoutingReadinessContainer([
            ResponseFactoryInterface::class => $factory,
            StreamFactoryInterface::class => $factory,
        ]);
        self::allow(
            $withoutRouting->get(ConfigRepositoryInterface::class),
            $withoutRouting->get(ModulePlan::class),
            $probe,
        );
        self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $probe->reads);
        self::assertFileDoesNotExist($path);
    }

    public function testSelectionAndFailurePrecedenceAtActualGuardBoundary(): void
    {
        $http = new ModuleId('platform.http');
        $routing = new ModuleId('platform.routing');
        $httpEntry = new ModulePlanEntry($http, 'coretsia/platform-http');
        $routingEntry = new ModulePlanEntry($routing, 'coretsia/platform-routing');
        $selected = new ModulePlan('web', [$http, $routing], [], [$http, $routing], [$httpEntry, $routingEntry]);
        $unselected = new ModulePlan('web', [$http], [], [$http], [$httpEntry]);
        $noHttp = new ModulePlan('web', [$routing], [], [$routing], [$routingEntry]);
        $config = self::config('http.classic', 'external');
        $factory = RoutingIntegrationFixture::externalFactory();
        $bindings = [ResponseFactoryInterface::class => $factory, StreamFactoryInterface::class => $factory];
        $container = new RoutingReadinessContainer($bindings);
        self::allow($config, $unselected, $container);
        self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $container->reads);
        $factoryReads = [ResponseFactoryInterface::class, StreamFactoryInterface::class];
        $readinessReads = [...$factoryReads, RoutingArtifactReadinessInterface::class];
        foreach (
            [
                [$noHttp, self::config('bad', 'bad'), [], 'http-runtime-module-not-enabled', []],
                [
                    $selected,
                    self::config('bad', 'bad'),
                    [],
                    RuntimeDriverInvalidConfigException::ERROR_CODE . ': '
                    . RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID,
                    [],
                ],
                [$selected, self::config('http.classic', 'bad'), [], 'http-factory-selection-invalid', []],
                [$selected, $config, [], 'container-service-not-found', [ResponseFactoryInterface::class]],
                [
                    $selected,
                    $config,
                    [ResponseFactoryInterface::class => $factory],
                    'container-service-not-found',
                    $factoryReads,
                ],
                [
                    $selected,
                    $config,
                    [ResponseFactoryInterface::class => new \stdClass(), StreamFactoryInterface::class => $factory],
                    'http-factory-contract-invalid',
                    $factoryReads,
                ],
                [
                    $selected,
                    $config,
                    [ResponseFactoryInterface::class => $factory, StreamFactoryInterface::class => new \stdClass()],
                    'http-factory-contract-invalid',
                    $factoryReads,
                ],
                [$selected, $config, $bindings, 'container-service-not-found', $readinessReads],
                [
                    $selected,
                    $config,
                    $bindings + [RoutingArtifactReadinessInterface::class => new \stdClass()],
                    'http-routing-artifact-readiness-invalid',
                    $readinessReads,
                ],
            ] as [$plan, $caseConfig, $services, $message, $reads]
        ) {
            $probe = new RoutingReadinessContainer($services);
            try {
                self::allow($caseConfig, $plan, $probe);
                self::fail('Expected entrypoint rejection.');
            } catch (\RuntimeException|\InvalidArgumentException $failure) {
                self::assertSame($message, $failure->getMessage());
            }
            self::assertSame($reads, $probe->reads);
        }
        $readiness = new class() implements RoutingArtifactReadinessInterface {
            public int $calls = 0;

            public function assertReady(): void
            {
                ++$this->calls;
            }
        };
        $probe = new RoutingReadinessContainer($bindings + [RoutingArtifactReadinessInterface::class => $readiness]);
        self::allow($config, $selected, $probe);
        self::assertSame(1, $readiness->calls);
        self::assertSame($readinessReads, $probe->reads);
        $probe = new RoutingReadinessContainer($bindings + [RoutingArtifactReadinessInterface::class => $readiness]);
        self::allow($config, $unselected, $probe);
        self::assertSame(1, $readiness->calls);
        self::assertSame($factoryReads, $probe->reads);

        $failure = new \RuntimeException('routing-readiness-failed');
        $throwing = new class($failure) implements RoutingArtifactReadinessInterface {
            public function __construct(private readonly \RuntimeException $failure)
            {
            }

            public function assertReady(): void
            {
                throw $this->failure;
            }
        };
        $probe = new RoutingReadinessContainer($bindings + [RoutingArtifactReadinessInterface::class => $throwing]);
        try {
            self::allow($config, $selected, $probe);
            self::fail('Expected readiness failure to propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        self::assertSame($readinessReads, $probe->reads);

        $probe = new RoutingReadinessContainer(
            $bindings + [RoutingArtifactReadinessInterface::class => new \stdClass()],
        );
        self::allow($config, $unselected, $probe);
        self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $probe->reads);
    }

    private static function config(string $driver, string $factory): ConfigRepositoryInterface
    {
        return new ArrayConfigRepository(
            ['kernel' => ['runtime' => ['http_driver' => $driver]], 'http' => ['factory' => $factory]],
            [],
        );
    }

    private static function allow(
        ConfigRepositoryInterface $config,
        ModulePlan $plan,
        ContainerInterface $container,
    ): void {
        new HttpRuntimeEntrypointGuard(new RuntimeDriverResolver())->assertEntrypointAllowed(
            $config,
            $plan,
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );
    }
}

final class RoutingReadinessContainer implements ContainerInterface
{
    /** @var list<string> */
    public array $reads = [];

    /** @param array<string, object> $services */
    public function __construct(private readonly array $services)
    {
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }

    public function get(string $id): mixed
    {
        $this->reads[] = $id;
        return $this->services[$id] ?? throw new NotFoundException($id);
    }
}
