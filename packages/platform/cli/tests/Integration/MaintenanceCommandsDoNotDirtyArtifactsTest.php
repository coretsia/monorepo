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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Composer\InstalledVersions;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class MaintenanceCommandsDoNotDirtyArtifactsTest extends CliIntegrationTestCase
{
    private const array COMMANDS = ['maintenance:enable', 'maintenance:disable', 'maintenance:status'];

    private const string PROBE_MODULE_ID = 'platform.maintenance-test-fixture';

    public function testDiscoveryAndExecutionRequireExplicitConsoleModuleButNoHttpFactoriesOrArtifacts(): void
    {
        $root = self::consumer();
        $other = self::consumer();
        try {
            self::configure($root, false);
            self::configure($other, false);
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            self::assertFalse($host->get(ModulePlan::class)->hasEnabledModule('platform.http'));
            $catalog = $host->get(CommandCatalog::class);
            foreach (self::COMMANDS as $name) {
                self::assertArrayNotHasKey($name, $catalog->descriptors());
            }
            $list = self::payload(self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']));
            self::assertArrayNotHasKey('maintenance', $list['groups']);
            self::assertDirectoryDoesNotExist($root . '/var/maintenance');

            self::configure($root, true);
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $host->get(ModulePlan::class);
            self::assertSame('console', $plan->app());
            self::assertTrue($plan->hasEnabledModule('platform.http'));
            self::assertFalse($host->has(ResponseFactoryInterface::class));
            self::assertFalse($host->has(StreamFactoryInterface::class));
            $catalog = $host->get(CommandCatalog::class);
            $list = self::payload(self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json']));
            $names = \array_column($list['groups']['maintenance'], 'name');
            foreach (self::COMMANDS as $name) {
                self::assertArrayHasKey($name, $catalog->descriptors());
                self::assertContains($name, $names);
                $help = self::payload(self::execute($root . '/vendor/bin/coretsia', ['help', $name, '--format=json']));
                self::assertSame($name, $help['name']);
            }
            self::assertDirectoryDoesNotExist($root . '/var/maintenance');
            self::assertDirectoryDoesNotExist($root . '/var/cache');
            self::assertSame(
                ['enabled' => false, 'message_state' => 'ignored'],
                self::command($root, ['maintenance:status']),
            );
            self::assertSame(['enabled' => true], self::command($root, ['maintenance:enable'], $other));
            self::assertSame(
                ['enabled' => true, 'message_state' => 'absent'],
                self::command($root, ['maintenance:status'], $other),
            );
            self::assertSame(['enabled' => false], self::command($root, ['maintenance:disable']));
            self::assertSame(
                ['enabled' => false, 'message_state' => 'ignored'],
                self::command($root, ['maintenance:status']),
            );
            self::assertDirectoryDoesNotExist($root . '/var/cache');
            self::assertDirectoryDoesNotExist($other . '/var/maintenance');
            self::assertDirectoryDoesNotExist($other . '/var/cache');
            $fresh = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            self::assertSame($plan->toArray(), $fresh->get(ModulePlan::class)->toArray());
        } finally {
            self::removeTree($root);
            self::removeTree($other);
        }
    }

    public function testActualCommandsPreserveCompiledWebAndApiGenerationsAndOneCliUow(): void
    {
        $root = self::consumer();
        $other = self::consumer();
        try {
            self::configure($root, true, true);
            self::configure($other, true);
            self::installProbeModule($root);
            $probe = $other . '/maintenance-probe.log';
            $environment = ['CORETSIA_TEST_MAINTENANCE_PROBE' => $probe];
            $plan = self::operation($root, 'debug:modules', 'console', $environment);
            self::assertContains('platform.http', $plan['enabled']);
            self::assertContains(self::PROBE_MODULE_ID, $plan['enabled']);

            $list = self::payload(
                self::execute($root . '/vendor/bin/coretsia', ['list', '--format=json'], environment: $environment),
            );
            foreach (self::COMMANDS as $name) {
                self::assertContains($name, \array_column($list['groups']['maintenance'], 'name'));
                self::assertSame(
                    $name,
                    self::payload(
                        self::execute(
                            $root . '/vendor/bin/coretsia',
                            ['help', $name, '--format=json'],
                            environment: $environment,
                        ),
                    )['name'],
                );
            }
            self::assertFileDoesNotExist($probe);
            self::assertDirectoryDoesNotExist($root . '/var/maintenance');
            self::assertDirectoryDoesNotExist($root . '/var/cache');

            $hashes = [];
            foreach (['web', 'api'] as $target) {
                $hash = self::operation($root, 'config:hash', $target, $environment);
                $hashes[$target] = $hash['generation_id'];
                self::assertIsString($hashes[$target]);
                $compile = self::operation($root, 'config:compile', $target, $environment);
                self::assertSame($hashes[$target], $compile['generation_id']);
                self::assertCount(4, $compile['artifacts']);
                self::assertCleanGeneration($root, $target, $hashes[$target], $environment);
            }
            self::assertFileDoesNotExist($probe);
            self::assertDirectoryDoesNotExist($root . '/var/maintenance');
            $baseline = self::artifactBytes($root);
            self::assertNotSame([], $baseline);
            foreach (['web', 'api'] as $target) {
                foreach (['module-manifest.php', 'config.php', 'container.php', 'generation-manifest.php'] as $file) {
                    self::assertArrayHasKey($target . '/generations/' . $hashes[$target] . '/' . $file, $baseline);
                }
            }

            self::assertSame(
                ['enabled' => false, 'message_state' => 'ignored'],
                self::runObserved($root, $probe, ['maintenance:status']),
            );
            self::assertSame(['enabled' => true], self::runObserved($root, $probe, [
                'maintenance:enable',
                '--message=Scheduled notice',
                '--retry-after=60',
            ], $other));
            $directory = $root . '/var/maintenance';
            self::assertSame('', \file_get_contents($directory . '/enabled'));
            self::assertFileExists($directory . '/maintenance.lock');
            self::assertSame(
                "{\"message\":\"Scheduled notice\",\"retryAfterSeconds\":60,\"schemaVersion\":1}\n",
                \file_get_contents($directory . '/message.json'),
            );
            self::assertTrue(\touch($directory . '/enabled', 946684800));
            self::assertTrue(\touch($directory . '/message.json', 946684800));
            $flag = self::fileIdentity($directory . '/enabled');
            $message = self::fileIdentity($directory . '/message.json');

            self::assertSame(['enabled' => true], self::runObserved($root, $probe, [
                'maintenance:enable',
                '--message=Scheduled notice',
                '--retry-after=60',
            ]));
            self::assertSame($flag, self::fileIdentity($directory . '/enabled'));
            self::assertSame($message, self::fileIdentity($directory . '/message.json'));
            self::assertSame(
                ['enabled' => true, 'message_state' => 'valid', 'retry_after_seconds' => 60],
                self::runObserved($root, $probe, ['maintenance:status']),
            );
            self::assertTargetState($root, 'web', true, true, 60);
            self::assertTargetState($root, 'api', true, false, 60);
            self::assertDirectoryDoesNotExist($other . '/var/maintenance');

            // Exercise the complete effective exclusion, including an operation-shaped temporary filename.
            $temporary = $directory . '/.maintenance-' . \str_repeat('a', 32) . '.tmp';
            self::write($temporary, 'synthetic');
            foreach ($hashes as $target => $hash) {
                self::assertSame($hash, self::operation($root, 'config:hash', $target, $environment)['generation_id']);
                self::assertCleanGeneration($root, $target, $hash, $environment);
            }
            self::assertTrue($baseline === self::artifactBytes($root), 'maintenance-artifact-bytes-changed');

            self::assertSame(['enabled' => true], self::runObserved($root, $probe, [
                'maintenance:enable',
                '--message=Updated notice',
                '--retry-after=120',
            ]));
            self::assertSame($flag, self::fileIdentity($directory . '/enabled'));
            self::assertSame(
                ['enabled' => true, 'message_state' => 'valid', 'retry_after_seconds' => 120],
                self::runObserved($root, $probe, ['maintenance:status']),
            );
            self::assertTargetState($root, 'web', true, true, 120);
            self::assertTargetState($root, 'api', true, false, 120);
            $retained = self::fileIdentity($directory . '/message.json');

            foreach (
                [
                    ['maintenance:enable', '--target=web'],
                    ['maintenance:disable', '--target=web'],
                    ['maintenance:status', '--target=web'],
                ] as $arguments
            ) {
                self::assertRejectedBeforeResolution($root, $probe, $arguments, 'cli-option-unknown');
            }
            foreach (self::COMMANDS as $command) {
                self::assertRejectedBeforeResolution(
                    $root,
                    $probe,
                    [$command, '--', 'synthetic'],
                    'cli-argument-count-invalid',
                );
            }
            self::assertSame($flag, self::fileIdentity($directory . '/enabled'));
            self::assertSame($retained, self::fileIdentity($directory . '/message.json'));
            self::assertSemanticFailure(
                $root,
                $probe,
                ['maintenance:enable', '--retry-after=1'],
                'CORETSIA_MAINTENANCE_COMMAND_INVALID',
                'http-maintenance-command-invalid',
            );
            self::assertSemanticFailure(
                $root,
                $probe,
                ['maintenance:enable', '--message=Notice', '--retry-after=86401'],
                'CORETSIA_MAINTENANCE_MESSAGE_INVALID',
                'http-maintenance-message-invalid',
            );
            self::assertSame($flag, self::fileIdentity($directory . '/enabled'));
            self::assertSame($retained, self::fileIdentity($directory . '/message.json'));

            foreach ($hashes as $target => $hash) {
                self::assertSame($hash, self::operation($root, 'config:hash', $target, $environment)['generation_id']);
                self::assertCleanGeneration($root, $target, $hash, $environment);
            }
            self::assertTrue($baseline === self::artifactBytes($root), 'maintenance-artifact-bytes-changed');
            self::assertSame(['enabled' => false], self::runObserved($root, $probe, ['maintenance:disable']));
            self::assertSame(['enabled' => false], self::runObserved($root, $probe, ['maintenance:disable']));
            self::assertSame(
                ['enabled' => false, 'message_state' => 'ignored'],
                self::runObserved($root, $probe, ['maintenance:status']),
            );
            self::assertFileDoesNotExist($directory . '/enabled');
            self::assertSame($retained, self::fileIdentity($directory . '/message.json'));
            self::assertSame('synthetic', \file_get_contents($temporary));
            self::assertTargetState($root, 'web', false, true, null);
            self::assertTargetState($root, 'api', false, false, null);
            foreach ($hashes as $target => $hash) {
                self::assertSame($hash, self::operation($root, 'config:hash', $target, $environment)['generation_id']);
                self::assertCleanGeneration($root, $target, $hash, $environment);
            }
            self::assertTrue($baseline === self::artifactBytes($root), 'maintenance-artifact-bytes-changed');
            self::assertSame($plan, self::operation($root, 'debug:modules', 'console', $environment));
            self::assertSame(
                ['enabled' => false, 'message_state' => 'ignored'],
                self::command($other, ['maintenance:status']),
            );
            self::assertDirectoryDoesNotExist($other . '/var/maintenance');
        } finally {
            self::removeTree($root);
            self::removeTree($other);
        }
    }

    private static function configure(string $root, bool $http, bool $probe = false): void
    {
        $include = $http ? ['platform.http'] : [];
        if ($probe) {
            $include[] = self::PROBE_MODULE_ID;
        }
        $overrides = [
            'console' => [
                'include' => $include,
                'exclude' => $http ? [] : ['platform.http', 'platform.problem-details'],
            ],
        ];
        if ($probe) {
            foreach (['web', 'api'] as $target) {
                $overrides[$target] = ['include' => ['platform.http', self::PROBE_MODULE_ID], 'exclude' => []];
            }
        }
        self::write(
            $root . '/config/app.php',
            '<?php return ' . \var_export(['moduleOverrides' => $overrides], true) . ';' . "\n",
        );
        self::write($root . '/config/logging.php', "<?php return ['driver' => 'null'];\n");
        if ($http) {
            self::write($root . '/config/http.php', "<?php return ['factory' => 'external'];\n");
        }
        if ($probe) {
            self::write($root . '/apps/api/config/http.php', "<?php return ['maintenance' => ['enabled' => false]];\n");
        }
    }

    /** @param list<string> $arguments */
    private static function command(string $root, array $arguments, ?string $cwd = null): array
    {
        return self::payload(self::execute($root . '/vendor/bin/coretsia', [...$arguments, '--format=json'], $cwd));
    }

    /** @param array{exit:int,stdout:string,stderr:string} $result */
    private static function payload(array $result): array
    {
        self::assertSame(
            0,
            $result['exit'],
            'maintenance-cli-exit-code'
            . "\nstdout: " . $result['stdout']
            . "\nstderr: " . $result['stderr'],
        );
        self::assertSame('', $result['stderr']);
        $records = self::jsonRecord($result['stdout'])['data']['records'];
        self::assertCount(1, $records);
        self::assertSame('json', $records[0]['type']);
        self::assertIsArray($records[0]['payload']);
        return $records[0]['payload'];
    }

    private static function operation(string $root, string $name, string $target, array $environment): array
    {
        $payload = self::payload(self::execute($root . '/vendor/bin/coretsia', [
            $name,
            '--target=' . $target,
            '--format=json',
        ], environment: $environment));
        self::assertSame('success', $payload['outcome']);
        self::assertSame($target, $payload['app_target']);
        return $payload['data'];
    }

    private static function assertCleanGeneration(string $root, string $target, string $hash, array $environment): void
    {
        $verify = self::operation($root, 'cache:verify', $target, $environment);
        self::assertSame('clean', $verify['state']);
        self::assertSame($hash, $verify['current_generation_id']);
        self::assertSame($hash, $verify['expected_generation_id']);
        self::assertCount(4, $verify['artifacts']);
        foreach ($verify['artifacts'] as $artifact) {
            self::assertSame('clean', $artifact['status']);
        }
    }

    /** @return array<string, string> */
    private static function artifactBytes(string $root): array
    {
        $cache = $root . '/var/cache';
        $bytes = [];
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cache, \FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            if ($file->isFile()) {
                $key = \str_replace('\\', '/', \substr($file->getPathname(), \strlen($cache) + 1));
                $content = \file_get_contents($file->getPathname());
                self::assertIsString($content);
                $bytes[$key] = $content;
            }
        }
        \ksort($bytes, \SORT_STRING);
        return $bytes;
    }

    /** @return array{int, int, int, string} */
    private static function fileIdentity(string $path): array
    {
        \clearstatcache(true, $path);
        $stat = \stat($path);
        $bytes = \file_get_contents($path);
        self::assertIsArray($stat);
        self::assertIsString($bytes);
        return [$stat['dev'], $stat['ino'], $stat['mtime'], $bytes];
    }

    /** @param list<string> $arguments */
    private static function runObserved(string $root, string $probe, array $arguments, ?string $cwd = null): array
    {
        $before = self::probeLines($probe);
        $result = self::execute(
            $root . '/vendor/bin/coretsia',
            [...$arguments, '--format=json'],
            $cwd,
            [
                'CORETSIA_TEST_MAINTENANCE_PROBE' => $probe,
                'CORETSIA_TEST_MAINTENANCE_EXPECT_COMMAND' => $arguments[0],
            ],
        );
        $payload = self::payload($result);
        self::assertSame([...$before, 'resolve:' . $arguments[0], 'after:' . $arguments[0]], self::probeLines($probe));
        self::assertDirectoryDoesNotExist($root . '/var/cache/console');
        return $payload;
    }

    /** @param list<string> $arguments */
    private static function assertRejectedBeforeResolution(
        string $root,
        string $probe,
        array $arguments,
        string $reason,
    ): void {
        $before = self::probeLines($probe);
        // Put global format before the end-of-options marker.
        $result = self::execute($root . '/vendor/bin/coretsia', [
            $arguments[0],
            '--format=plain',
            ...\array_slice($arguments, 1),
        ], environment: [
            'CORETSIA_TEST_MAINTENANCE_PROBE' => $probe,
            'CORETSIA_TEST_MAINTENANCE_EXPECT_COMMAND' => $arguments[0],
        ]);
        self::assertSafeFailure($result, 'CORETSIA_CLI_INPUT_INVALID: ' . $reason);
        self::assertSame($before, self::probeLines($probe));
    }

    /** @param list<string> $arguments */
    private static function assertSemanticFailure(
        string $root,
        string $probe,
        array $arguments,
        string $code,
        string $reason,
    ): void {
        $before = self::probeLines($probe);
        $result = self::execute(
            $root . '/vendor/bin/coretsia',
            [...$arguments, '--format=json'],
            environment: [
                'CORETSIA_TEST_MAINTENANCE_PROBE' => $probe,
                'CORETSIA_TEST_MAINTENANCE_EXPECT_COMMAND' => $arguments[0],
            ],
        );
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        $records = self::jsonRecord($result['stdout'])['data']['records'];
        self::assertSame([['code' => $code, 'message' => $reason, 'type' => 'error']], $records);
        self::assertSame([...$before, 'resolve:' . $arguments[0], 'after:' . $arguments[0]], self::probeLines($probe));
    }

    /** @return list<string> */
    private static function probeLines(string $probe): array
    {
        \clearstatcache(true, $probe);
        if (!\is_file($probe)) {
            return [];
        }
        $lines = \file($probe, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        return $lines;
    }

    private static function assertTargetState(
        string $root,
        string $target,
        bool $active,
        bool $participates,
        ?int $retry,
    ): void {
        $result = self::execute($root . '/vendor/coretsia/test-maintenance-fixture/runtime.php', [$target]);
        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame([
            'same_root' => true,
            'maintenance_ignored' => true,
            'enabled' => $active,
            'message_state' => $active ? 'valid' : 'ignored',
            'retry_after_seconds' => $retry,
            'static_enabled' => $participates,
            'participates' => $participates,
        ], \json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR));
    }

    private static function installProbeModule(string $root): void
    {
        // Isolate Composer runtime metadata; never edit the repository's installed metadata.
        $runtimePath = new \ReflectionClass(InstalledVersions::class)->getFileName();
        self::assertIsString($runtimePath);
        $runtimeDirectory = \dirname($runtimePath);
        $installed = require $runtimeDirectory . '/installed.php';
        $installedJson = \json_decode(
            (string) \file_get_contents($runtimeDirectory . '/installed.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($installed);
        self::assertIsArray($installedJson['packages']);
        $packageName = 'coretsia/test-maintenance-fixture';
        $packagePath = $root . '/vendor/' . $packageName;
        $metadata = [
            'kind' => 'runtime',
            'moduleId' => self::PROBE_MODULE_ID,
            'providers' => ['Coretsia\\TestMaintenance\\ProbeProvider'],
            'requires' => ['platform.http'],
            'conflicts' => [],
        ];
        $installed['versions'][$packageName] = [
            'pretty_version' => '0.0.0',
            'version' => '0.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => $packagePath,
            'aliases' => [],
            'dev_requirement' => false,
        ];
        $package = [
            'name' => $packageName,
            'version' => '0.0.0',
            'type' => 'library',
            'extra' => ['coretsia' => $metadata],
        ];
        $installedJson['packages'][] = $package + ['dev_requirement' => false];
        \usort($installedJson['packages'], static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));
        self::write($packagePath . '/composer.json', \json_encode($package, JSON_THROW_ON_ERROR));
        self::write($root . '/vendor/composer/InstalledVersions.php', (string) \file_get_contents($runtimePath));
        self::write(
            $root . '/vendor/composer/installed.php',
            '<?php return ' . \var_export($installed, true) . ';' . "\n",
        );
        self::write(
            $root . '/vendor/composer/installed.json',
            \json_encode($installedJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n",
        );
        self::write($packagePath . '/ProbeProvider.php', self::providerSource());
        self::write($packagePath . '/runtime.php', self::runtimeSource());
        self::write(
            $root . '/vendor/autoload.php',
            '<?php' . "\n"
            . 'require __DIR__ . \'/composer/InstalledVersions.php\';' . "\n"
            . '$loader = require ' . \var_export(self::repositoryRoot() . '/vendor/autoload.php', true) . ';' . "\n"
            . 'require __DIR__ . \'/coretsia/test-maintenance-fixture/ProbeProvider.php\';' . "\n"
            . 'return $loader;' . "\n",
        );
    }

    private static function providerSource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\TestMaintenance;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Http\Console\MaintenanceDisableCommand;
use Coretsia\Platform\Http\Console\MaintenanceEnableCommand;
use Coretsia\Platform\Http\Console\MaintenanceStatusCommand;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class ProbeProvider implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $store = ContainerValueReference::service(MaintenanceStateStore::class);
        $accessor = ContainerValueReference::service(ContextAccessorInterface::class);
        $definitions
            ->classService(ProbeFactories::class, ProbeFactories::class)
            ->alias(ResponseFactoryInterface::class, ProbeFactories::class)
            ->alias(StreamFactoryInterface::class, ProbeFactories::class)
            ->classService(ProbeAfterHook::class, ProbeAfterHook::class)
            ->tag(ReservedTags::KERNEL_HOOK_AFTER_UOW, ProbeAfterHook::class)
            ->classMethodFactory(MaintenanceEnableCommand::class, ProbeCommands::class, 'enable', [$store, $accessor])
            ->classMethodFactory(MaintenanceDisableCommand::class, ProbeCommands::class, 'disable', [$store, $accessor])
            ->classMethodFactory(MaintenanceStatusCommand::class, ProbeCommands::class, 'status', [$store, $accessor]);
    }
}

final class Probe
{
    public static function record(string $token): void
    {
        $path = \getenv('CORETSIA_TEST_MAINTENANCE_PROBE');
        if (\is_string($path) && $path !== '') {
            $bytes = $token . "\n";
            if (\file_put_contents($path, $bytes, FILE_APPEND | LOCK_EX) !== \strlen($bytes)) {
                throw new \RuntimeException('maintenance-probe-write-failed');
            }
        }
    }
}

final class ProbeCommands
{
    private static function resolved(ContextAccessorInterface $context, string $name): void
    {
        if ($context->get(ContextKeys::UOW_TYPE) !== 'cli' || !$context->has(ContextKeys::UOW_ID)) {
            Probe::record('resolve-outside-uow');
            throw new \RuntimeException('maintenance-probe-outside-uow');
        }
        Probe::record('resolve:' . $name);
    }

    public static function enable(MaintenanceStateStore $state, ContextAccessorInterface $context): MaintenanceEnableCommand
    {
        self::resolved($context, MaintenanceEnableCommand::NAME);
        return new MaintenanceEnableCommand($state);
    }

    public static function disable(MaintenanceStateStore $state, ContextAccessorInterface $context): MaintenanceDisableCommand
    {
        self::resolved($context, MaintenanceDisableCommand::NAME);
        return new MaintenanceDisableCommand($state);
    }

    public static function status(MaintenanceStateStore $state, ContextAccessorInterface $context): MaintenanceStatusCommand
    {
        self::resolved($context, MaintenanceStatusCommand::NAME);
        return new MaintenanceStatusCommand($state);
    }
}

final class ProbeAfterHook implements AfterUowHookInterface
{
    public function afterUow(array $context, array $result): void
    {
        $operation = $context['attributes']['operation'] ?? null;
        if (\is_string($operation) && \in_array($operation, ['maintenance:enable', 'maintenance:disable', 'maintenance:status'], true)) {
            Probe::record(($context['type'] === 'cli' ? 'after:' : 'wrong-uow:') . $operation);
        } elseif (\getenv('CORETSIA_TEST_MAINTENANCE_EXPECT_COMMAND') !== false) {
            Probe::record('unexpected-uow');
        }
    }
}

final class ProbeFactories implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function __construct()
    {
        Probe::record('http-factory-resolved');
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('maintenance-probe-http-factory-not-executable');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('maintenance-probe-http-factory-not-executable');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('maintenance-probe-http-factory-not-executable');
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('maintenance-probe-http-factory-not-executable');
    }
}
PHP;
    }

    private static function runtimeSource(): string
    {
        return <<<'PHP'
<?php

declare(strict_types=1);

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Ops\KernelOpsHostSeedConfigLoader;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Maintenance\MaintenanceMiddleware;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;

require \dirname(__DIR__, 2) . '/autoload.php';
$root = \dirname(__DIR__, 3);
$target = $argv[1];
$container = new ArtifactRuntimeBooter()->boot(new ArtifactRuntimeInput($root, $root . '/var/cache/' . $target));
$paths = $container->get(RuntimePathContext::class);
$snapshot = $container->get(MaintenanceStateStore::class)->read();
$config = $container->get(ConfigRepositoryInterface::class);
$seed = new KernelOpsHostSeedConfigLoader(new ComposerPackageInstallPathResolver())->load();
$prefixes = $seed['kernel']['fingerprint']['application_ignore_prefixes'] ?? null;
echo \json_encode([
    'same_root' => $paths->applicationRoot() === \str_replace('\\', '/', $root),
    'maintenance_ignored' => \is_array($prefixes) && \in_array('var/maintenance', $prefixes, true),
    'enabled' => $snapshot['enabled'],
    'message_state' => $snapshot['messageState'],
    'retry_after_seconds' => $snapshot['message']?->retryAfterSeconds(),
    'static_enabled' => $config->get('http.maintenance.enabled'),
    'participates' => \in_array(
        MaintenanceMiddleware::class,
        $container->get(HttpMiddlewareStackBuilder::class)->build($config->get('http')),
        true,
    ),
], JSON_THROW_ON_ERROR);
PHP;
    }
}
