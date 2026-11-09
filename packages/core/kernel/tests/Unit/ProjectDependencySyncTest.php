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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ProjectDependencySyncTest extends TestCase
{
    public function testFacadeExposesPlanSyncAndInstalledVerification(): void
    {
        $reflection = new \ReflectionClass(ProjectDependencySync::class);

        self::assertTrue($reflection->hasMethod('plan'));
        self::assertTrue($reflection->hasMethod('synchronize'));
        self::assertTrue($reflection->hasMethod('verifyInstalled'));
    }

    public function testConfiguredAndProgrammaticEntryPointsPreserveTheirSignatures(): void
    {
        $reflection = new \ReflectionClass(ProjectDependencySync::class);
        $methods = [
            'plan' => ['projectRoot', 'intent'],
            'planConfigured' => ['projectRoot', 'fixedPresetByTarget'],
            'synchronize' => ['projectRoot', 'intent', 'executionPolicy'],
            'synchronizeConfigured' => ['projectRoot', 'executionPolicy', 'fixedPresetByTarget'],
            'verifyInstalled' => ['projectRoot', 'verificationInput'],
        ];

        foreach ($methods as $methodName => $parameters) {
            self::assertTrue($reflection->hasMethod($methodName));
            $method = $reflection->getMethod($methodName);
            self::assertTrue($method->isPublic());
            self::assertSame(
                $parameters,
                \array_map(
                    static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                    $method->getParameters()
                ),
            );
        }
    }

    public function testConfigurationIntentUsesActiveTargetsAndFallbackWithoutCliDiscovery(): void
    {
        $root = self::applicationRoot();

        try {
            $facade = self::configurationFacade();
            $method = new \ReflectionMethod(ProjectDependencySync::class, 'configurationIntent');
            [$fallbackSnapshot, $fallbackIntent] = $method->invoke($facade, $root);

            self::assertNull($fallbackSnapshot->activeTargets());
            self::assertSame([AppTarget::Web], $fallbackIntent->applications()->targets());
            self::assertSame([], $fallbackIntent->fixedPresetByTarget());

            \file_put_contents(
                $root . '/config/app.php',
                '<?php return '
                . \var_export([
                    'activeTargets' => ['worker', 'web'],
                    'presets' => ['worker' => 'enterprise'],
                ], true)
                . ';',
            );

            [$snapshot, $intent] = $method->invoke($facade, $root, ['web' => 'micro']);
            self::assertSame(['web', 'worker'], $snapshot->activeTargets());
            self::assertSame([AppTarget::Web, AppTarget::Worker], $intent->applications()->targets());
            self::assertSame(['web' => 'micro'], $intent->fixedPresetByTarget());
        } finally {
            self::remove($root);
        }
    }

    public function testInvalidBootstrapSourceMapsSafelyBeforeInstallationIntentConstruction(): void
    {
        $root = self::applicationRoot();

        try {
            \file_put_contents(
                $root . '/config/app.php',
                '<?php return ["activeTargets" => ["unknown-secret-target"]];',
            );
            $facade = self::configurationFacade();
            $method = new \ReflectionMethod(ProjectDependencySync::class, 'configurationIntent');

            try {
                $method->invoke($facade, $root);
                self::fail('Invalid bootstrap source must be rejected before creating installation intent.');
            } catch (DependencySyncException $exception) {
                self::assertSame(DependencySyncErrorCodes::PRESET_POLICY_INVALID, $exception->errorCode());
                self::assertSame(
                    BootstrapException::ERROR_CODE,
                    $exception->context()['sourceErrorCode'],
                );
                self::assertSame(
                    BootstrapException::REASON_OVERRIDES_INVALID,
                    $exception->context()['sourceReason'],
                );
                self::assertStringNotContainsString('unknown-secret-target', $exception->getMessage());
                self::assertStringNotContainsString($root, $exception->getMessage());
            }
        } finally {
            self::remove($root);
        }
    }

    public function testApplyRejectsTemporaryPresetsBeforeProjectAccessOrComposerEffects(): void
    {
        $facade = self::configurationFacade();
        $invalidRoot = '/nonexistent-coretsia-apply-' . \bin2hex(\random_bytes(8));

        try {
            $facade->synchronizeConfigured(
                $invalidRoot,
                new DependencySyncExecutionPolicy(apply: true),
                ['web' => 'micro'],
            );
            self::fail('Apply must reject temporary presets before touching the project.');
        } catch (DependencySyncException $exception) {
            self::assertSame(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID, $exception->errorCode());
        }
    }

    public function testEffectfulSynchronizeRetainsMembershipGuardBeforeComposerCoordinator(): void
    {
        $method = new \ReflectionMethod(ProjectDependencySync::class, 'synchronize');
        $path = $method->getFileName();

        self::assertIsString($path);

        $lines = \file($path);
        self::assertIsArray($lines);

        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );
        $guard = \strpos(
            $source,
            '$intent->applications()->targets() !== $configurationIntent->applications()->targets()',
        );
        $coordinator = \strrpos($source, '$this->syncCoordinator->synchronize(');

        self::assertNotFalse($guard);
        self::assertNotFalse($coordinator);
        self::assertLessThan($coordinator, $guard);
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*\$intent->applications\(\)->targets\(\)\s*!==\s*'
            . '\$configurationIntent->applications\(\)->targets\(\)\s*\)\s*\{'
            . '\s*throw\s+DependencySyncException::forCode\('
            . '\s*DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID\s*\);/s',
            $source,
        );
    }

    private static function configurationFacade(): ProjectDependencySync
    {
        $reflection = new \ReflectionClass(ProjectDependencySync::class);
        $facade = $reflection->newInstanceWithoutConstructor();
        $sourceResolver = ModeInfrastructureTestSupport::bootstrapSourceResolver();
        $config = require \dirname(__DIR__, 2) . '/config/kernel.php';

        $reflection->getProperty('bootstrapSourceResolver')->setValue($facade, $sourceResolver);
        $reflection->getProperty('kernelConfig')->setValue($facade, $config);

        return $facade;
    }

    private static function applicationRoot(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-project-dependency-sync-unit-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root . '/config', 0777, true));

        return $root;
    }

    private static function remove(string $path): void
    {
        if (\is_file($path) || \is_link($path)) {
            self::assertTrue(\unlink($path));
            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        self::assertTrue(\rmdir($path));
    }
}
