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
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Metrics\MetricsRendererInterface;
use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopContextPropagation;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Metrics\Default\DefaultMeter;
use Coretsia\Platform\Metrics\Noop\NullMetricsRenderer;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class KernelOpsHostBootsExplicitTargetTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = ArtifactPipelineTestSupport::temporaryRoot('kernel-ops-explicit-target');
        $excluded = [
            'platform.cli',
            'platform.http',
            'platform.logging',
            'platform.problem-details',
            'platform.routing',
        ];
        $targetModules = [
            'web' => 'platform.metrics',
            'api' => 'platform.tracing',
            'console' => 'platform.redaction',
        ];
        $overrides = [];
        foreach ($targetModules as $target => $module) {
            $overrides[$target] = [
                'include' => [$module],
                'exclude' => [...$excluded, ...\array_values(\array_diff($targetModules, [$module]))],
            ];
        }
        ArtifactPipelineTestSupport::writePhpReturn($this->root . '/config/app.php', [
            'appEnv' => 'local',
            'preset' => 'micro',
            'artifactsCacheDir' => 'var/explicit-target-cache',
            'moduleOverrides' => $overrides,
        ]);
        ArtifactPipelineTestSupport::writeRootConfig($this->root, ['fixture' => ['shared' => 'base']]);

        foreach (
            [
                'web' => 'http.classic',
                'api' => 'http.swoole',
                'console' => 'http.roadrunner',
            ] as $target => $driver
        ) {
            ArtifactPipelineTestSupport::writePhpReturn($this->root . '/apps/' . $target . '/config/roots.php', [
                'fixture' => ['target' => $target, $target . '_only' => true],
                'kernel' => ['runtime' => ['http_driver' => $driver]],
            ]);
            ArtifactPipelineTestSupport::writePhpReturn(
                $this->root . '/apps/' . $target . '/config/environments/local/roots.php',
                [
                    'fixture' => ['environment' => $target . '-local'],
                ],
            );
        }
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->root);
        parent::tearDown();
    }

    public function testExplicitTargetsComposeTheirOwnConfigPlanProvidersAndPaths(): void
    {
        $booter = new KernelOpsHostBooter();
        $input = new KernelOpsHostInput($this->root);
        $before = self::tree($this->root);
        $web = $booter->bootForTarget($input, AppTarget::Web);
        $api = $booter->bootForTarget($input, AppTarget::Api);
        self::assertTarget($web, $input, AppTarget::Web, 'platform.metrics', 'http.classic');
        self::assertTarget($api, $input, AppTarget::Api, 'platform.tracing', 'http.swoole');
        self::assertSame($before, self::tree($this->root));
        self::assertFalse(\file_exists($this->root . '/var/explicit-target-cache'));
    }

    public function testConsoleBootMatchesExplicitConsoleTargetAndDelegates(): void
    {
        $booter = new KernelOpsHostBooter();
        $input = new KernelOpsHostInput($this->root);
        $before = self::tree($this->root);
        $defaultConsole = $booter->boot($input);
        $explicit = $booter->bootForTarget($input, AppTarget::Console);
        foreach ([$defaultConsole, $explicit] as $container) {
            self::assertTarget($container, $input, AppTarget::Console, 'platform.redaction', 'http.roadrunner');
        }
        self::assertSame(
            $defaultConsole->get(ConfigRepositoryInterface::class)->all(),
            $explicit->get(ConfigRepositoryInterface::class)->all(),
        );
        self::assertSame(
            $defaultConsole->get(ModulePlan::class)->toArray(),
            $explicit->get(ModulePlan::class)->toArray(),
        );
        self::assertSame($before, self::tree($this->root));
        self::assertFalse(\file_exists($this->root . '/var/explicit-target-cache'));

        $method = new \ReflectionMethod(KernelOpsHostBooter::class, 'boot');
        $fileName = $method->getFileName();
        self::assertIsString($fileName);
        $source = \file($fileName);
        self::assertIsArray($source);
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        self::assertIsInt($start);
        self::assertIsInt($end);
        $body = \implode('', \array_slice($source, $start - 1, $end - $start + 1));
        $code = '';
        foreach (\token_get_all("<?php\n" . $body) as $token) {
            if (\is_array($token)) {
                if (\in_array($token[0], [\T_OPEN_TAG, \T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= $token[1];
            } else {
                $code .= $token;
            }
        }
        self::assertMatchesRegularExpression(
            '/\{\s*return\s+\$this->bootForTarget\(\s*\$input\s*,\s*AppTarget::Console\s*,?\s*\);\s*}\s*\z/',
            $code,
        );
    }

    public function testDiagnosticsAndPathsRemainFrozenWhenBootstrapSourceChanges(): void
    {
        $input = new KernelOpsHostInput($this->root);
        $booter = new KernelOpsHostBooter();
        $web = $booter->bootForTarget($input, AppTarget::Web);
        $api = $booter->bootForTarget($input, AppTarget::Api);
        // Resolve these lazy host-owned factories only after changing Phase A input.
        $config = require $this->root . '/config/app.php';
        $config['appEnv'] = 'dev';
        $config['artifactsCacheDir'] = 'var/changed-cache';
        ArtifactPipelineTestSupport::writePhpReturn($this->root . '/config/app.php', $config);
        self::assertTarget($web, $input, AppTarget::Web, 'platform.metrics', 'http.classic');
        self::assertTarget($api, $input, AppTarget::Api, 'platform.tracing', 'http.swoole');
        self::assertFalse(\file_exists($this->root . '/var/changed-cache'));
    }

    public function testExplicitTargetBootFailuresUseTheCanonicalSafeBoundary(): void
    {
        $booter = new KernelOpsHostBooter();
        $input = new KernelOpsHostInput($this->root);
        foreach ([AppTarget::Web, AppTarget::Api] as $target) {
            $path = $this->root . '/apps/' . $target->value . '/config/kernel.php';
            ArtifactPipelineTestSupport::writePhpReturn(
                $path,
                ['runtime' => ['http_driver' => 'raw-private-invalid-value']],
            );
            \ob_start();
            try {
                try {
                    $booter->bootForTarget($input, $target);
                    self::fail('Expected explicit source-host boot failure.');
                } catch (KernelOpsFailedException $exception) {
                    self::assertSame(KernelOpsFailedException::REASON_HOST_BOOT_FAILED, $exception->reason());
                    self::assertSame('CORETSIA_KERNEL_OPS_FAILED: host-boot-failed', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                }
            } finally {
                $output = \ob_get_clean();
                \unlink($path);
            }
            self::assertSame('', $output);
        }
        self::assertTarget($booter->boot($input), $input, AppTarget::Console, 'platform.redaction', 'http.roadrunner');
        self::assertFalse(\file_exists($this->root . '/var/explicit-target-cache'));
    }

    private static function assertTarget(
        ContainerInterface $container,
        KernelOpsHostInput $input,
        AppTarget $target,
        string $module,
        string $driver,
    ): void {
        $bootstrap = $container->get(BootstrapConfig::class);
        $plan = $container->get(ModulePlan::class);
        $config = $container->get(ConfigRepositoryInterface::class);
        $paths = $container->get(RuntimePathContext::class);
        $diagnostics = $container->get(RuntimeDiagnosticsInput::class);

        self::assertInstanceOf(BootstrapConfig::class, $bootstrap);
        self::assertInstanceOf(ModulePlan::class, $plan);
        self::assertInstanceOf(ConfigRepositoryInterface::class, $config);
        self::assertInstanceOf(RuntimePathContext::class, $paths);
        self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
        self::assertSame($input, $container->get(KernelOpsHostInput::class));
        self::assertSame($target, $bootstrap->appTarget());
        self::assertSame('local', $bootstrap->appEnv());
        self::assertSame('micro', $bootstrap->preset());
        self::assertSame('var/explicit-target-cache', $bootstrap->artifactsCacheDir());
        self::assertSame($input->applicationRoot(), $bootstrap->applicationRoot());
        self::assertSame($target->value, $plan->app());
        self::assertSame(['core.foundation', 'core.kernel', $module], $plan->toArray()['enabled']);
        self::assertSame('base', $config->get('fixture.shared'));
        self::assertSame($target->value, $config->get('fixture.target'));
        self::assertSame($target->value . '-local', $config->get('fixture.environment'));
        self::assertSame($driver, $config->get('kernel.runtime.http_driver'));
        foreach (['web', 'api', 'console'] as $other) {
            self::assertSame($other === $target->value, $config->has('fixture.' . $other . '_only'));
        }
        foreach (['platform.metrics', 'platform.tracing', 'platform.redaction'] as $candidate) {
            self::assertSame($candidate === $module, $plan->hasEnabledModule($candidate));
            self::assertSame($candidate !== $module, $plan->hasExcludedModule($candidate));
        }
        self::assertSame($module === 'platform.metrics', $container->has(MetricsRendererInterface::class));
        self::assertSame($module === 'platform.redaction', $container->has(SensitiveDataRedactorInterface::class));
        self::assertInstanceOf(
            $module === 'platform.metrics' ? DefaultMeter::class : NoopMeter::class,
            $container->get(MeterPortInterface::class),
        );
        self::assertInstanceOf(
            $module === 'platform.tracing' ? W3cTraceContextPropagation::class : NoopContextPropagation::class,
            $container->get(ContextPropagationInterface::class),
        );
        if ($module === 'platform.metrics') {
            self::assertInstanceOf(NullMetricsRenderer::class, $container->get(MetricsRendererInterface::class));
        }
        if ($module === 'platform.redaction') {
            self::assertInstanceOf(
                DefaultSensitiveDataRedactor::class,
                $container->get(SensitiveDataRedactorInterface::class),
            );
        }
        $root = \rtrim(\str_replace('\\', '/', $input->applicationRoot()), '/');
        self::assertSame($root, $paths->applicationRoot());
        self::assertSame($root . '/var/explicit-target-cache/' . $target->value, $paths->artifactRoot());
        self::assertSame(new ArtifactPathResolver()->artifactRoot($bootstrap), $paths->artifactRoot());
        self::assertSame($bootstrap->appEnv(), $diagnostics->appEnv());
        self::assertFalse($diagnostics->artifactGenerationLoaded());
        self::assertSame($paths, $container->get(RuntimePathContext::class));
        self::assertSame($diagnostics, $container->get(RuntimeDiagnosticsInput::class));
        self::assertInstanceOf(KernelOpsInterface::class, $container->get(KernelOpsInterface::class));
        self::assertSame($container->get(KernelOpsFacade::class), $container->get(KernelOpsInterface::class));
    }

    /** @return list<string> */
    private static function tree(string $root): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $paths[] = \str_replace('\\', '/', \substr($entry->getPathname(), \strlen($root) + 1));
        }
        \sort($paths, \SORT_STRING);

        return $paths;
    }
}
