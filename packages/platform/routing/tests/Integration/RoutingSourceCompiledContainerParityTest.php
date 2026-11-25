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
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\ArtifactRuntimeSeedFactory;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Container\RuntimeContainerSeedIds;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Ops\KernelOpsHostSeedConfigLoader;
use Coretsia\Kernel\Ops\KernelOpsSourceDefinitionProviderAdapter;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Console\RoutesCompileCommand;
use Coretsia\Platform\Routing\Console\RoutesValidateCommand;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;
use Coretsia\Platform\Routing\Provider\RoutingServiceProvider;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Runtime\RoutingArtifactReadiness;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingCompileInput;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingCompileOutput;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class RoutingSourceCompiledContainerParityTest extends TestCase
{
    public function testActualSourceAndCompiledGraphHaveEquivalentRoutingAndExactRuntimeSeeds(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        $config = $source->get(ConfigRepositoryInterface::class)->all();
        $definitions = new ContainerDefinitionBuilder();
        new RoutingServiceProvider()->define($definitions, new ContainerDefinitionContext($config));
        $required = $definitions->build()->requiredServiceIds();
        $expected = [
            ContainerInterface::class,
            ConfigRepositoryInterface::class,
            TagRegistry::class,
            ModulePlan::class,
            RuntimePathContext::class,
            RuntimeDiagnosticsInput::class,
            ContextWriterInterface::class,
            TracerPortInterface::class,
            MeterPortInterface::class,
            Stopwatch::class,
            ScopedFileLock::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifierInterface::class,
            LoggerInterface::class,
        ];
        \sort($expected, \SORT_STRING);
        \sort($required, \SORT_STRING);
        self::assertSame($expected, $required);

        $envelope = $source->get(RoutesCompiler::class)->compile();
        self::assertTrue($source->get(RoutesArtifactWriter::class)->write($envelope));
        $ops = $source->get(KernelOpsInterface::class);
        self::assertSame('success', $ops->compileConfig(new KernelOpsRequest('web'))->outcome());
        self::assertSame('success', $ops->compileConfig(new KernelOpsRequest('console'))->outcome());
        $artifactRoot = $root . '/var/routing-cache/web';
        $generation = RoutingIntegrationFixture::generationBytes($artifactRoot);
        $graph = new StablePhpArrayParser()->parse($generation['container.php'])['payload'];
        self::assertSame(CompiledRouter::class, $graph['aliases'][RouterInterface::class]);
        self::assertSame(RoutingArtifactReadiness::class, $graph['aliases'][RoutingArtifactReadinessInterface::class]);
        self::assertSame([
            Container::class,
            ContainerInterface::class,
            TagRegistry::class,
            ConfigRepositoryInterface::class,
            ModulePlan::class,
            RuntimePathContext::class,
            RuntimeDiagnosticsInput::class,
        ], RuntimeContainerSeedIds::all());
        $configPayload = new StablePhpArrayParser()->parse($generation['config.php'])['payload'];
        $seedFactory = new ArtifactRuntimeSeedFactory();
        $seeds = $seedFactory->create(
            new ArtifactRuntimeInput($root, $artifactRoot),
            $seedFactory->hydrateConfigRepository($configPayload),
            $seedFactory->hydrateModulePlan(
                new StablePhpArrayParser()->parse($generation['module-manifest.php'])['payload'],
            ),
            $configPayload,
            true,
        );
        $expectedSeeds = [
            ConfigRepositoryInterface::class,
            ModulePlan::class,
            RuntimePathContext::class,
            RuntimeDiagnosticsInput::class,
        ];
        \sort($expectedSeeds, \SORT_STRING);
        self::assertSame($expectedSeeds, $seeds->ids());
        foreach (RuntimeContainerSeedIds::all() as $id) {
            self::assertArrayNotHasKey($id, $graph['services']);
            self::assertArrayNotHasKey($id, $graph['aliases']);
        }
        $graphBytes = \json_encode($graph, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        self::assertStringNotContainsString($root, $graphBytes);
        foreach (['/items/{id}', '/items/fixed', 'Handler::show', 'pathTemplate', 'z.web'] as $literal) {
            self::assertStringNotContainsString($literal, $graphBytes);
        }
        $forbidden = [
            BootstrapConfig::class,
            KernelOpsHostInput::class,
            KernelOpsHostBooter::class,
            KernelOpsHostSeedConfigLoader::class,
            KernelOpsExecutionServices::class,
            KernelOpsSourceDefinitionProviderAdapter::class,
            KernelOpsFacade::class,
            KernelOpsInterface::class,
        ];
        foreach ($forbidden as $id) {
            self::assertArrayNotHasKey($id, $graph['services']);
            self::assertArrayNotHasKey($id, $graph['aliases']);
        }
        $forbidden = \array_map(\strtolower(...), $forbidden);
        $entries = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($graph),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($entries as $key => $value) {
            foreach ([$key, $value] as $candidate) {
                if (!\is_string($candidate)) {
                    continue;
                }
                self::assertDoesNotMatchRegularExpression('~\A(?:/|[A-Za-z]:[/\\\\]|\\\\\\\\|file://)~i', $candidate);
                self::assertFalse(\in_array(\strtolower(\ltrim($candidate, '\\')), $forbidden, true));
            }
        }
        $compiled = new ArtifactRuntimeBooter()->boot(new ArtifactRuntimeInput($root, $artifactRoot));
        foreach ([$source, $compiled] as $container) {
            self::assertSame($container->get(CompiledRouter::class), $container->get(RouterInterface::class));
            $ready = $container->get(RoutingArtifactReadinessInterface::class);
            self::assertSame($container->get(RoutingArtifactReadiness::class), $ready);
            $ready->assertReady();
            self::assertInstanceOf(CompiledRouteTable::class, $container->get(CompiledRouteTable::class));
            // The Foundation resolver supplies container self-references directly.
            $registry = $container->get(RouteProviderRegistry::class);
            self::assertSame(
                $container,
                new \ReflectionProperty(RouteProviderRegistry::class, 'container')->getValue($registry),
            );
            self::assertInstanceOf(
                TagRegistry::class,
                new \ReflectionProperty(RouteProviderRegistry::class, 'tags')->getValue($registry),
            );
            foreach ($required as $id) {
                if (\in_array($id, [ContainerInterface::class, TagRegistry::class], true)) {
                    continue;
                }
                self::assertTrue($container->has($id), $id);
                self::assertInstanceOf($id, $container->get($id));
            }
        }
        foreach (
            [
                ['GET', '/items/fixed'],
                ['GET', '/items/42'],
                ['POST', '/other'],
                ['GET', '/health'],
                ['HEAD', '/items/42'],
            ] as [$method, $path]
        ) {
            $a = $source->get(RouterInterface::class)->match($method, $path);
            $b = $compiled->get(RouterInterface::class)->match($method, $path);
            self::assertSame($a?->toArray(), $b?->toArray());
            if ($path === '/items/fixed') {
                self::assertSame('z.web', $b?->name());
            }
        }
        // Source bootstrap must not be entered by command construction or by an artifact-runtime command.
        $marker = $root . '/source-booted';
        RoutingIntegrationFixture::write(
            $root . '/config/app.php',
            '<?php file_put_contents(' . \var_export(
                $marker,
                true,
            ) . ', "booted"); throw new \RuntimeException("source boot forbidden");',
        );
        $console = new ArtifactRuntimeBooter()->boot(
            new ArtifactRuntimeInput($root, $root . '/var/routing-cache/console'),
        );
        foreach ([$source, $compiled, $console] as $container) {
            self::assertInstanceOf(RoutesCompileCommand::class, $container->get(RoutesCompileCommand::class));
            self::assertInstanceOf(RoutesValidateCommand::class, $container->get(RoutesValidateCommand::class));
            self::assertFileDoesNotExist($marker);
        }
        self::assertTrue($console->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded());
        self::assertSame('console', $console->get(ModulePlan::class)->app());
        foreach ([$compiled, $console] as $container) {
            $output = new RoutingCompileOutput();
            self::assertSame(1, $container->get(RoutesCompileCommand::class)->run(new RoutingCompileInput(), $output));
            self::assertSame(
                [
                    [
                        RoutesCommandTargetInvalidException::ERROR_CODE,
                        RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
                    ],
                ],
                $output->errors,
            );
            self::assertFileDoesNotExist($marker);
        }
        self::assertSame($generation, RoutingIntegrationFixture::generationBytes($artifactRoot));
    }
}
