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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Platform\Http\Debug\DebugModuleListHandler;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class DebugModuleListUsesRuntimeModulePlanTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testTerminalEndpointOnlyProjectsEnabledRuntimeModules(string $mode): void
    {
        foreach ([false, true] as $metrics) {
            $fixture = $this->boot($mode, $metrics);

            try {
                $plan = $fixture->container->get(ModulePlan::class);
                self::assertInstanceOf(ModulePlan::class, $plan);
                $expected = [
                    'core.foundation',
                    'core.kernel',
                    'platform.errors',
                    'platform.http',
                    ...($metrics ? ['platform.metrics'] : []),
                    'platform.problem-details',
                    'platform.redaction',
                ];
                self::assertSame(
                    $expected,
                    \array_map(static fn (ModuleId $id): string => $id->value(), $plan->enabled()),
                );
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/modules',
                        headers: ['X-Debug-Token' => [self::TOKEN]],
                        serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                    ),
                );
                self::assertProjection($response, $expected);
                self::assertStringNotContainsString('platform.worker', (string) $response->getBody());
                self::assertStringNotContainsString('platform.cli', (string) $response->getBody());
                if (!$metrics) {
                    self::assertStringNotContainsString('platform.metrics', (string) $response->getBody());
                }
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testProjectionUsesCanonicalDeduplicationAndByteOrderNotTopologicalOrder(string $mode): void
    {
        $fixture = $this->boot($mode, false);
        try {
            $a = ModuleId::fromString('platform.http');
            $z = ModuleId::fromString('platform.worker');
            $excluded = ModuleId::fromString('platform.metrics');
            $plan = new ModulePlan('web', [$z, $a, $z], [$excluded], [$z, $a], [
                new ModulePlanEntry($z, 'coretsia/platform-worker', []),
                new ModulePlanEntry($a, 'coretsia/platform-http', []),
            ]);

            $handler = new DebugModuleListHandler($plan, $fixture->container->get(FallbackResponses::class));
            $body = self::assertProjection($handler->handle(), ['platform.http', 'platform.worker']);
            self::assertSame($body, self::assertProjection($handler->handle(), ['platform.http', 'platform.worker']));
            self::assertStringNotContainsString('platform.metrics', $body);
            self::assertStringNotContainsString('platform.redaction', $body);
            self::assertStringNotContainsString('coretsia/', $body);
        } finally {
            $fixture->close();
        }
    }

    public function testHandlerHasNoDiscoveryDependency(): void
    {
        $reflection = new \ReflectionClass(DebugModuleListHandler::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(
            [ModulePlan::class, FallbackResponses::class],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                $constructor->getParameters(),
            ),
        );

        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'InstalledVersions',
                'Composer\\',
                'ModuleManifest',
                'ModuleResolver',
                'file_get_contents(',
                'file_exists(',
                'fopen(',
                'glob(',
                'scandir(',
                'DirectoryIterator',
                'require ',
                'include ',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    /** @param list<string> $modules */
    private static function assertProjection(ResponseInterface $response, array $modules): string
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $body = (string) $response->getBody();
        self::assertSame(['modules' => $modules], \json_decode($body, true, 512, \JSON_THROW_ON_ERROR));
        self::assertSame(\json_encode(['modules' => $modules], \JSON_THROW_ON_ERROR) . "\n", $body);

        return $body;
    }

    private function boot(string $mode, bool $metrics): SystemEndpointRuntimeFixture
    {
        return SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), [
            'appEnv' => 'local',
            'problemDetails' => true,
            'metricsModule' => $metrics,
            'http' => [
                'debug' => [
                    'endpoints' => ['enabled' => true],
                    'token_sha256' => \hash('sha256', self::TOKEN),
                ],
            ],
        ]);
    }
}
