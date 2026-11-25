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
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Runtime\RoutingArtifactReadiness;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingDeclarationProvider;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ArtifactOnlyRoutingBootTest extends TestCase
{
    public function testActualArtifactBootRequiresOwnerReadinessAndRetainsOneImmutableTable(): void
    {
        RoutingIntegrationFixture::runConsumer(self::class);
    }

    public static function exercise(string $root): void
    {
        $source = RoutingIntegrationFixture::source($root, AppTarget::Web);
        $envelope = $source->get(RoutesCompiler::class)->compile();
        $writer = $source->get(RoutesArtifactWriter::class);
        self::assertTrue($writer->write($envelope));
        self::assertSame(
            'success',
            $source->get(KernelOpsInterface::class)->compileConfig(new KernelOpsRequest('web'))->outcome(),
        );
        $artifactRoot = $root . '/var/routing-cache/web';
        $artifact = $artifactRoot . '/routes.php';
        $original = \file_get_contents($artifact);
        self::assertIsString($original);
        $generation = RoutingIntegrationFixture::generationBytes($artifactRoot);
        $graph = new StablePhpArrayParser()->parse($generation['container.php'])['payload'];
        self::assertSame(
            RoutingDeclarationProvider::class,
            $graph['services']['fixture.web.high']['construction']['class'],
        );
        self::assertTrue(\class_exists(RoutingDeclarationProvider::class));
        $definitions = RoutingDeclarationProvider::$definitions;
        $constructed = RoutingDeclarationProvider::$constructed;
        $identityReads = RoutingDeclarationProvider::$identityReads;
        $invoked = RoutingDeclarationProvider::$invoked;
        self::assertGreaterThan(0, $definitions);
        self::assertGreaterThan(0, $constructed);
        self::assertGreaterThan(0, $identityReads);
        self::assertGreaterThan(0, $invoked);
        RoutingDeclarationProvider::$forbidSource = true;
        $marker = $root . '/runtime-source-read';
        $poison = '<?php file_put_contents(' . \var_export($marker, true)
            . ', "read"); throw new \RuntimeException("runtime source read");';
        RoutingIntegrationFixture::write($root . '/config/app.php', $poison);
        RoutingIntegrationFixture::write($root . '/apps/web/config/http.php', $poison);
        RoutingIntegrationFixture::write(
            $root . '/vendor/composer/installed.json',
            'invalid runtime discovery metadata',
        );
        $input = new ArtifactRuntimeInput($root, $artifactRoot);
        self::assertTrue(\unlink($artifact));
        $missing = new ArtifactRuntimeBooter()->boot($input);
        self::assertTrue($missing->has('fixture.web.high'));
        self::assertReadinessRejected($missing, RoutesArtifactInvalidException::REASON_MISSING);
        self::assertFileDoesNotExist($artifact);
        RoutingIntegrationFixture::write($artifact, '<?php return [];');
        $invalid = new ArtifactRuntimeBooter()->boot($input);
        self::assertReadinessRejected($invalid, RoutesArtifactInvalidException::REASON_MALFORMED);
        self::assertSame('<?php return [];', \file_get_contents($artifact));
        RoutingIntegrationFixture::write($artifact, $original);
        $runtime = new ArtifactRuntimeBooter()->boot($input);
        self::assertTrue($runtime->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded());
        self::assertSame('local', $runtime->get(RuntimeDiagnosticsInput::class)->appEnv());
        self::assertAllowed($runtime);
        $table = $runtime->get(CompiledRouteTable::class);
        $readiness = $runtime->get(RoutingArtifactReadinessInterface::class);
        $router = $runtime->get(RouterInterface::class);
        self::assertSame($table, new \ReflectionProperty(CompiledRouter::class, 'routes')->getValue($router));
        self::assertSame(
            $table,
            new \ReflectionProperty(RoutingArtifactReadiness::class, 'routeTable')->getValue($readiness),
        );
        self::assertSame('z.web', $router->match('GET', '/items/fixed')?->name());
        self::assertSame(['id' => '42'], $router->match('GET', '/items/42')?->parameters());
        self::assertNull($router->match('GET', '/health'));
        self::assertNull($router->match('HEAD', '/items/42'));
        $replacement = RoutingIntegrationFixture::envelope('replacement');
        self::assertTrue($writer->write($replacement));
        $replacedBytes = \file_get_contents($artifact);
        self::assertNotSame($original, $replacedBytes);
        self::assertSame($table, $runtime->get(CompiledRouteTable::class));
        $readiness->assertReady();
        self::assertSame('z.web', $router->match('GET', '/items/42')?->name());
        $next = new ArtifactRuntimeBooter()->boot($input);
        self::assertAllowed($next);
        self::assertSame('replacement', $next->get(RouterInterface::class)->match('GET', '/items/42')?->name());
        self::assertSame($replacedBytes, \file_get_contents($artifact));
        self::assertSame($definitions, RoutingDeclarationProvider::$definitions);
        self::assertSame($constructed, RoutingDeclarationProvider::$constructed);
        self::assertSame($identityReads, RoutingDeclarationProvider::$identityReads);
        self::assertSame($invoked, RoutingDeclarationProvider::$invoked);
        self::assertFileDoesNotExist($marker);
        self::assertSame(
            'invalid runtime discovery metadata',
            \file_get_contents($root . '/vendor/composer/installed.json'),
        );
        self::assertSame($generation, RoutingIntegrationFixture::generationBytes($artifactRoot));
    }

    private static function assertAllowed(ContainerInterface $container): void
    {
        new HttpRuntimeEntrypointGuard(new RuntimeDriverResolver())->assertEntrypointAllowed(
            $container->get(ConfigRepositoryInterface::class),
            $container->get(ModulePlan::class),
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );
    }

    private static function assertReadinessRejected(ContainerInterface $container, string $reason): void
    {
        try {
            self::assertAllowed($container);
            self::fail('Expected HTTP entrypoint to reject an unavailable Routing artifact.');
        } catch (ContainerException $exception) {
            // Compiled service references deliberately translate the underlying failure.
            self::assertSame('container-compiled-service-reference-failed', $exception->getMessage());
        }
        try {
            $container->get(RoutesArtifactReader::class)->read();
            self::fail('Expected the corresponding owner-artifact read failure.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
        }
    }
}
