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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use Coretsia\Platform\Http\Module\HttpModule;
use Coretsia\Platform\Http\Runtime\HttpRuntimeEntrypointGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpRuntimeEntrypointGuardTest extends TestCase
{
    public function testMissingHttpModuleFailsBeforeDriverResolutionOrContainerProbing(): void
    {
        $config = $this->createMock(ConfigRepositoryInterface::class);
        $config->expects(self::never())->method('has');
        $config->expects(self::never())->method('get');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        try {
            self::guard()->assertEntrypointAllowed(
                $config,
                self::modulePlan(false),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\RuntimeException $exception) {
            self::assertSame('http-runtime-module-not-enabled', $exception->getMessage());

            return;
        }

        self::fail('HTTP module selection must precede driver and factory checks.');
    }

    public function testExplicitEmptyContributionsAreAcceptedForClassicHttp(): void
    {
        $config = $this->config('http.classic', 'external');
        $container = $this->compatibleExternalContainer();

        self::guard()->assertEntrypointAllowed(
            $config,
            self::modulePlan(true),
            RuntimeDriverContributions::fromDrivers([], []),
            $container,
        );
    }

    public function testExactCallerSuppliedWorkerHttpContributionIsPreserved(): void
    {
        $config = $this->config('http.classic', 'external');
        $container = $this->compatibleExternalContainer();

        self::guard()->assertEntrypointAllowed(
            $config,
            self::modulePlan(true),
            RuntimeDriverContributions::fromDrivers([HttpDriver::WORKER], []),
            $container,
        );
    }

    public function testModuleNamesDoNotManufactureWorkerDriverContributions(): void
    {
        $plan = self::modulePlan(true, true);
        self::assertTrue($plan->hasEnabledModule('platform.worker'));

        self::guard()->assertEntrypointAllowed(
            $this->config('http.classic', 'external'),
            $plan,
            RuntimeDriverContributions::fromDrivers([], []),
            $this->compatibleExternalContainer(),
        );
    }

    #[DataProvider('conflictingKernelDrivers')]
    public function testIncompatibleWorkerContributionsPreserveCanonicalConflict(
        string $kernelDriver,
        string $expectedReason,
    ): void {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        try {
            self::guard()->assertEntrypointAllowed(
                $this->config($kernelDriver, 'external'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([HttpDriver::WORKER], []),
                $container,
            );
        } catch (RuntimeDriverConflictException $exception) {
            self::assertSame(RuntimeDriverConflictException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedReason, $exception->reason());
            self::assertContains('http.worker', $exception->activeDriverIds());
            self::assertContains($kernelDriver, $exception->activeDriverIds());

            return;
        }

        self::fail('Kernel driver matrix conflicts must propagate unchanged.');
    }

    /** @return iterable<string, array{string, string}> */
    public static function conflictingKernelDrivers(): iterable
    {
        yield 'swoole' => [
            'http.swoole',
            RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
        ];
        yield 'roadrunner' => [
            'http.roadrunner',
            RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
        ];
        yield 'frankenphp' => [
            'http.frankenphp',
            RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
        ];
    }

    #[DataProvider('invalidKernelDriverConfigurations')]
    public function testCanonicalKernelConfigFailuresAreNotReinterpreted(
        ?string $driver,
        string $reason,
    ): void {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        try {
            self::guard()->assertEntrypointAllowed(
                $this->config($driver, 'external'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (RuntimeDriverInvalidConfigException $exception) {
            self::assertSame(RuntimeDriverInvalidConfigException::ERROR_CODE, $exception->errorCode());
            self::assertSame($reason, $exception->reason());

            return;
        }

        self::fail('Invalid Kernel HTTP driver must preserve canonical exception taxonomy.');
    }

    /** @return iterable<string, array{?string, string}> */
    public static function invalidKernelDriverConfigurations(): iterable
    {
        yield 'missing' => [null, RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_MISSING];
        yield 'unknown' => ['http.unknown', RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID];
    }

    public function testExternalFactoriesAreCheckedThroughCanonicalInterfaceBindings(): void
    {
        self::guard()->assertEntrypointAllowed(
            $this->config('http.classic', 'external'),
            self::modulePlan(true),
            RuntimeDriverContributions::fromDrivers([], []),
            $this->compatibleExternalContainer(),
        );
    }

    public function testMissingOrIncompatibleEffectiveFactoriesFailClosed(): void
    {
        $missing = $this->createStub(ContainerInterface::class);
        $missing->method('get')->willReturn(null);
        $this->assertFactoryFailure($missing, 'http-factory-contract-invalid');

        $wrong = $this->createStub(ContainerInterface::class);
        $wrong->method('get')->willReturn(new \stdClass());
        $this->assertFactoryFailure($wrong, 'http-factory-contract-invalid');
    }

    public function testInvalidHttpFactorySelectionFailsWithoutFactoryResolution(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');

        try {
            self::guard()->assertEntrypointAllowed(
                $this->config('http.classic', 'auto'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('http-factory-selection-invalid', $exception->getMessage());

            return;
        }

        self::fail('HTTP factory selection must be explicit.');
    }

    public function testBuiltInFactoryReadinessNeverSilentlyAcceptsAnOverridingBinding(): void
    {
        $expected = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['nyholm'];
        $available = \class_exists($expected['response']) && \class_exists($expected['stream']);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn(new \stdClass());

        try {
            self::guard()->assertEntrypointAllowed(
                $this->config('http.classic', 'nyholm'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\RuntimeException $exception) {
            self::assertSame(
                $available ? 'http-factory-contract-invalid' : 'http-factory-implementation-unavailable',
                $exception->getMessage(),
            );

            return;
        }

        self::fail('A wrong or unavailable built-in PSR-17 implementation cannot pass readiness.');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCompatibleButIncorrectBuiltInBindingFailsAtReadiness(): void
    {
        $expected = HttpRuntimeEntrypointGuard::BUILTIN_FACTORY_CLASSES['nyholm'];
        if (!\class_exists($expected['response'])) {
            \class_alias(HttpFactoryAvailabilityFixture::class, $expected['response']);
        }

        $response = $this->createStub(ResponseFactoryInterface::class);
        $stream = $this->createStub(StreamFactoryInterface::class);
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            static fn (string $id): object => match ($id) {
                ResponseFactoryInterface::class => $response,
                StreamFactoryInterface::class => $stream,
                default => throw new \LogicException('Unexpected service lookup.'),
            },
        );

        try {
            self::guard()->assertEntrypointAllowed(
                $this->config('http.classic', 'nyholm'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\RuntimeException $exception) {
            self::assertSame('http-factory-selection-mismatch', $exception->getMessage());

            return;
        }

        self::fail('Later compatible but incorrect factory bindings must fail readiness.');
    }

    private function assertFactoryFailure(ContainerInterface $container, string $expected): void
    {
        try {
            self::guard()->assertEntrypointAllowed(
                $this->config('http.classic', 'external'),
                self::modulePlan(true),
                RuntimeDriverContributions::fromDrivers([], []),
                $container,
            );
        } catch (\RuntimeException $exception) {
            self::assertSame($expected, $exception->getMessage());

            return;
        }

        self::fail('Incompatible PSR-17 services must be rejected.');
    }

    private function compatibleExternalContainer(): ContainerInterface
    {
        $response = $this->createStub(ResponseFactoryInterface::class);
        $stream = $this->createStub(StreamFactoryInterface::class);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            static fn (string $id): object => match ($id) {
                ResponseFactoryInterface::class => $response,
                StreamFactoryInterface::class => $stream,
                default => throw new \LogicException('Unexpected container service lookup.'),
            },
        );

        return $container;
    }

    private function config(?string $kernelDriver, string $factory): ConfigRepositoryInterface
    {
        $config = $this->createStub(ConfigRepositoryInterface::class);
        $config->method('has')->willReturnCallback(
            static fn (string $key): bool => $key === 'kernel.runtime.http_driver' && $kernelDriver !== null,
        );
        $config->method('get')->willReturnCallback(
            static fn (string $key, mixed $default = null): mixed => match ($key) {
                'kernel.runtime.http_driver' => $kernelDriver,
                'http.factory' => $factory,
                default => $default,
            },
        );

        return $config;
    }

    private static function guard(): HttpRuntimeEntrypointGuard
    {
        return new HttpRuntimeEntrypointGuard(new RuntimeDriverResolver());
    }

    private static function modulePlan(bool $withHttp, bool $withWorker = false): ModulePlan
    {
        $names = $withHttp ? [HttpModule::MODULE_ID] : ['core.kernel'];
        if ($withWorker) {
            $names[] = 'platform.worker';
        }
        $ids = \array_map(static fn (string $name): ModuleId => ModuleId::fromString($name), $names);

        return new ModulePlan(
            app: 'web',
            enabled: $ids,
            excluded: [],
            topologicalOrder: $ids,
            modules: \array_map(
                static fn (ModuleId $id): ModulePlanEntry => new ModulePlanEntry(
                    moduleId: $id,
                    composerName: match ($id->value()) {
                        HttpModule::MODULE_ID => 'coretsia/platform-http',
                        'platform.worker' => 'coretsia/platform-worker',
                        default => 'coretsia/core-kernel',
                    },
                ),
                $ids,
            ),
        );
    }
}

/** Test-only class identity for the missing-vendor readiness branch. */
final class HttpFactoryAvailabilityFixture
{
}
