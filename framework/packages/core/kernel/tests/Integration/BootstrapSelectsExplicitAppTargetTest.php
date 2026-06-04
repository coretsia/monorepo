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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BootstrapSelectsExplicitAppTargetTest extends TestCase
{
    #[DataProvider('appTargets')]
    public function testBootstrapAcceptsExplicitAppTargetAndDerivesAppRoot(
        string $targetValue,
        AppTarget $expectedTarget,
    ): void {
        $skeletonRoot = self::nonExistingSkeletonRoot('accepted-' . $targetValue);

        self::assertDirectoryDoesNotExist($skeletonRoot);

        $input = new BootstrapInput(
            skeletonRoot: $skeletonRoot,
            appTarget: AppTarget::fromString($targetValue),
        );

        $config = self::resolveBootstrapConfig($input);

        self::assertSame($expectedTarget, $config->appTarget());
        self::assertSame($skeletonRoot, $config->skeletonRoot());
        self::assertSame(
            $skeletonRoot . '/apps/' . $targetValue,
            $config->appRoot(),
        );
    }

    public function testBootstrapDoesNotScanSkeletonAppsDirectory(): void
    {
        $skeletonRoot = self::nonExistingSkeletonRoot('no-scan');

        self::assertDirectoryDoesNotExist($skeletonRoot);
        self::assertDirectoryDoesNotExist($skeletonRoot . '/apps');

        $input = new BootstrapInput(
            skeletonRoot: $skeletonRoot,
            appTarget: AppTarget::Web,
        );

        $config = self::resolveBootstrapConfig($input);

        self::assertSame(AppTarget::Web, $config->appTarget());
        self::assertSame($skeletonRoot . '/apps/web', $config->appRoot());
    }

    public function testInvalidAppTargetFailsWithSafeBootstrapException(): void
    {
        $rawInput = 'invalid-target Authorization Bearer SECRET /tmp/coretsia-secret';

        try {
            AppTarget::fromString($rawInput);
        } catch (BootstrapException $exception) {
            self::assertSame(BootstrapException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                BootstrapException::REASON_INVALID_APP_TARGET,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_BOOTSTRAP_FAILED: bootstrap-invalid-app-target',
                $exception->getMessage(),
            );
            self::assertSame(0, $exception->getCode());

            self::assertStringNotContainsString($rawInput, $exception->getMessage());
            self::assertStringNotContainsString('invalid-target', $exception->getMessage());
            self::assertStringNotContainsString('Authorization', $exception->getMessage());
            self::assertStringNotContainsString('Bearer', $exception->getMessage());
            self::assertStringNotContainsString('SECRET', $exception->getMessage());
            self::assertStringNotContainsString('/tmp/coretsia-secret', $exception->getMessage());

            return;
        }

        self::fail('Expected invalid app target to fail with BootstrapException.');
    }

    /**
     * @return iterable<string, array{0:non-empty-string,1:AppTarget}>
     */
    public static function appTargets(): iterable
    {
        yield 'web' => [
            'web',
            AppTarget::Web,
        ];

        yield 'api' => [
            'api',
            AppTarget::Api,
        ];

        yield 'console' => [
            'console',
            AppTarget::Console,
        ];

        yield 'worker' => [
            'worker',
            AppTarget::Worker,
        ];
    }

    private static function resolveBootstrapConfig(BootstrapInput $input): BootstrapConfig
    {
        return new BootstrapConfigResolver(
            overridesLoader: new BootstrapOverridesLoader(),
        )->resolve(
            input: $input,
            kernelConfig: self::kernelConfig(),
        );
    }

    /**
     * @return array<string,mixed>
     */
    private static function kernelConfig(): array
    {
        $config = require self::kernelRoot() . '/config/kernel.php';

        self::assertIsArray($config);

        /**
         * @var array<string,mixed> $config
         */
        return $config;
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }

    /**
     * Returns a deterministic path that should not exist.
     *
     * The test intentionally uses a missing skeleton root to prove Bootstrap
     * Phase A target selection does not scan or require skeleton/apps/<app>.
     */
    private static function nonExistingSkeletonRoot(string $case): string
    {
        $root = self::kernelRoot()
            . '/build/test-non-existing-skeleton/bootstrap-selects-explicit-app-target/'
            . $case;

        return \str_replace('\\', '/', $root);
    }
}
