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

namespace Coretsia\Platform\Routing\Tests\Integration\Support;

use Composer\InstalledVersions;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Contracts\Runtime\Hook\AfterUowHookInterface;
use Coretsia\Contracts\Runtime\Hook\BeforeUowHookInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Filesystem\AtomicFileWriter;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Routing\SystemRouteTable;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactPath;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/** Shared test-only consumer and filesystem fixtures. No production service is replaced. */
final class RoutingIntegrationFixture
{
    private function __construct()
    {
    }

    public static function root(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-routing-integration-' . \bin2hex(\random_bytes(8));
        TestCase::assertTrue(\mkdir($root, 0700));
        $resolved = \realpath($root);
        TestCase::assertIsString($resolved);
        return \str_replace('\\', '/', $resolved);
    }

    public static function remove(string $path): void
    {
        if (\is_link($path)) {
            $removed = \PHP_OS_FAMILY === 'Windows'
                ? (@\rmdir($path) || @\unlink($path))
                : \unlink($path);

            TestCase::assertTrue($removed);
            return;
        }

        if (!\is_dir($path) && \file_exists($path)) {
            \unlink($path);
            return;
        }
        if (\is_dir($path)) {
            foreach (new \FilesystemIterator($path) as $entry) {
                self::remove($entry->getPathname());
            }
            \rmdir($path);
        }
    }

    public static function write(string $path, string $bytes): void
    {
        $directory = \dirname($path);
        if (!\is_dir($directory)) {
            TestCase::assertTrue(\mkdir($directory, 0700, true));
        }
        TestCase::assertSame(\strlen($bytes), \file_put_contents($path, $bytes));
        \clearstatcache(true, $path);
    }

    /** @param array<mixed> $value */
    public static function php(string $path, array $value): void
    {
        self::write($path, '<?php return ' . \var_export($value, true) . ";\n");
    }

    public static function schema(): RoutesArtifactSchema
    {
        $classifier = new SensitiveKeyClassifier();
        return new RoutesArtifactSchema(
            new ReservedRoutesGuard(),
            $classifier,
            new DefaultSensitiveDataRedactor($classifier, new SensitiveValueClassifier(), new StableRedactionHasher()),
            new StablePhpArrayDumper(),
        );
    }

    public static function plan(string $app): ModulePlan
    {
        $id = new ModuleId('platform.routing');
        return new ModulePlan($app, [$id], [], [$id], [new ModulePlanEntry($id, 'coretsia/platform-routing')]);
    }

    public static function reader(string $root): RoutesArtifactReader
    {
        return new RoutesArtifactReader(
            new RoutesArtifactPath(new RuntimePathContext($root, $root)),
            new StablePhpArrayParser(),
            self::schema(),
            self::plan('web'),
            new RuntimeDiagnosticsInput('local', false),
        );
    }

    public static function writer(string $root): RoutesArtifactWriter
    {
        return new RoutesArtifactWriter(
            self::schema(),
            new RoutesArtifactPath(new RuntimePathContext($root, $root)),
            new ScopedFileLock(),
            new AtomicFileWriter(),
            self::plan('web'),
            new RuntimeDiagnosticsInput('local', false),
        );
    }

    /** @return array{_meta: array<string, int|string>, payload: array{app: string, routes: list<array<string, mixed>>}} */
    public static function envelope(string $name): array
    {
        return self::schema()->createEnvelope([
            new RouteDefinition($name, ['GET'], '/items/{id}', 'Handler::show'),
        ], 'web', 'local');
    }

    /** @param array<string, mixed> $payload */
    public static function fingerprint(array $payload, string $env): string
    {
        return \hash('sha256', StableJsonEncoder::encodeStableMap([
            'appEnv' => $env,
            'generator' => 'platform/routing/artifacts',
            'name' => 'routes',
            'payload' => $payload,
            'schemaVersion' => 1,
            'systemRouteTable' => [
                'entries' => SystemRouteTable::entries(),
                'schemaVersion' => SystemRouteTable::SCHEMA_VERSION,
            ],
        ]));
    }

    public static function externalFactory(): RoutingExternalFactory
    {
        return new RoutingExternalFactory();
    }

    public static function source(string $root, AppTarget $target): ContainerInterface
    {
        return new KernelOpsHostBooter()->bootForTarget(new KernelOpsHostInput($root), $target);
    }

    /**
     * The child gets an isolated copy of Composer installed metadata, with the
     * original installed classes still autoloadable. The workspace vendor is never edited.
     *
     * @param class-string $scenario
     */
    public static function runConsumer(string $scenario): void
    {
        self::runScenario($scenario, true, 'exercise');
    }

    /** @param class-string $scenario */
    public static function runFilesystemScenario(string $scenario, string $method = 'exercise'): void
    {
        self::runScenario($scenario, false, $method);
    }

    /** @param class-string $scenario */
    private static function runScenario(string $scenario, bool $consumer, string $method): void
    {
        $root = self::root();
        try {
            if ($consumer) {
                self::installConsumer($root);
            }
            self::executeScenario($root, $scenario, $consumer, $method, 'scenario');
        } finally {
            self::remove($root);
        }
    }

    /** @param class-string $scenario */
    public static function runArtifactRuntimeScenario(string $root, string $scenario): void
    {
        self::executeScenario($root, $scenario, true, 'exerciseRuntime', 'runtime-scenario');
    }

    /** @param class-string $scenario */
    private static function executeScenario(
        string $root,
        string $scenario,
        bool $consumer,
        string $method,
        string $scriptName,
    ): void {
        $autoload = \dirname(__DIR__, 6) . '/vendor/autoload.php';
        TestCase::assertFileExists($autoload);
        $scenarioPath = new \ReflectionClass($scenario)->getFileName();
        TestCase::assertIsString($scenarioPath);
        $script = $root . '/' . $scriptName . '.php';
        self::write(
            $script,
            '<?php' . "\n"
            . ($consumer
                ? 'require ' . \var_export($root . '/vendor/composer/InstalledVersions.php', true) . ";\n"
                : '')
            . 'require ' . \var_export($autoload, true) . ";\n"
            . 'require_once ' . \var_export(__FILE__, true) . ";\n"
            . 'require_once ' . \var_export($scenarioPath, true) . ";\n"
            . $scenario . '::' . $method . '(' . \var_export($root, true) . ");\n"
            . 'echo "routing-scenario-ok\\n";' . "\n",
        );
        $process = \proc_open(
            [\PHP_BINARY, '-d', 'display_errors=stderr', $script],
            [
                0 => ['pipe', 'r'],
                1 => ['file', $root . '/' . $scriptName . '-stdout', 'w'],
                2 => ['file', $root . '/' . $scriptName . '-stderr', 'w'],
            ],
            $pipes,
            $root,
        );
        TestCase::assertIsResource($process);
        \fclose($pipes[0]);
        $exit = \proc_close($process);
        $stderr = \file_get_contents($root . '/' . $scriptName . '-stderr');
        $stdout = \file_get_contents($root . '/' . $scriptName . '-stdout');
        TestCase::assertSame(0, $exit, (string) $stderr . (string) $stdout);
        TestCase::assertSame("routing-scenario-ok\n", $stdout);
        TestCase::assertSame('', $stderr);
    }

    private static function installConsumer(string $root): void
    {
        $runtime = new \ReflectionClass(InstalledVersions::class)->getFileName();
        TestCase::assertIsString($runtime);
        $directory = \dirname($runtime);
        $installed = require $directory . '/installed.php';
        $metadata = \json_decode(
            (string) \file_get_contents($directory . '/installed.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        TestCase::assertIsArray($installed);
        TestCase::assertIsArray($metadata['packages']);
        // Requiring the original installed.php already evaluates its __DIR__-relative paths.
        $installed['root']['install_path'] = $root;
        foreach (
            [
                'web' => RoutingWebDefinitions::class,
                'api' => RoutingApiDefinitions::class,
                'hooks' => RoutingHookDefinitions::class,
            ] as $target => $provider
        ) {
            $name = 'coretsia/test-routing-' . $target;
            $path = $root . '/vendor/' . $name;
            $record = [
                'name' => $name,
                'version' => '0.0.0',
                'type' => 'library',
                'extra' => [
                    'coretsia' => [
                        'kind' => 'runtime',
                        'moduleId' => 'platform.routing-fixture-' . $target,
                        'providers' => [$provider],
                        'requires' => match ($target) {
                            'web' => ['platform.routing', 'platform.http'],
                            'api' => ['platform.routing'],
                            'hooks' => ['core.kernel'],
                        },
                        'conflicts' => [],
                    ],
                ],
            ];
            $metadata['packages'][] = $record + ['dev_requirement' => false];
            $installed['versions'][$name] = [
                'pretty_version' => '0.0.0',
                'version' => '0.0.0.0',
                'reference' => null,
                'type' => 'library',
                'install_path' => $path,
                'aliases' => [],
                'dev_requirement' => false,
            ];
            self::write($path . '/composer.json', \json_encode($record, \JSON_THROW_ON_ERROR));
        }
        self::write($root . '/vendor/composer/InstalledVersions.php', (string) \file_get_contents($runtime));
        self::php($root . '/vendor/composer/installed.php', $installed);
        self::write($root . '/vendor/composer/installed.json', \json_encode($metadata, \JSON_THROW_ON_ERROR));
        $excluded = [
            'platform.routing-fixture-hooks',
            'platform.cli',
            'platform.logging',
            'platform.metrics',
            'platform.tracing',
            'platform.problem-details',
        ];
        self::php($root . '/config/app.php', [
            'appEnv' => 'local',
            'preset' => 'micro',
            'artifactsCacheDir' => 'var/routing-cache',
            'moduleOverrides' => [
                'web' => [
                    'include' => ['platform.routing-fixture-web'],
                    'exclude' => [...$excluded, 'platform.routing-fixture-api'],
                ],
                'api' => [
                    'include' => ['platform.routing-fixture-api'],
                    'exclude' => [...$excluded, 'platform.http', 'platform.routing-fixture-web'],
                ],
                'console' => [
                    'include' => ['platform.routing'],
                    'exclude' => [
                        ...$excluded,
                        'platform.http',
                        'platform.routing-fixture-web',
                        'platform.routing-fixture-api',
                    ],
                ],
            ],
        ]);
        self::php($root . '/apps/web/config/http.php', ['factory' => 'external']);
        self::php($root . '/apps/web/config/routing.php', ['logging' => ['enabled' => false]]);
        self::php($root . '/apps/api/config/routing.php', ['metrics' => ['enabled' => false]]);
    }

    /** Select real CLI services and recording lifecycle hooks in the isolated consumer only. */
    public static function enableCli(string $root): void
    {
        $app = require $root . '/config/app.php';
        foreach (['web', 'api', 'console'] as $target) {
            $override = &$app['moduleOverrides'][$target];
            $modules = ['platform.cli', 'platform.routing-fixture-hooks'];
            $override['exclude'] = \array_values(\array_diff($override['exclude'], $modules));
            $override['include'] = [...$override['include'], ...$modules];
            unset($override);
        }
        self::php($root . '/config/app.php', $app);
    }

    /** @param class-string $command */
    public static function assertCommandMetadata(
        ContainerInterface $container,
        string $command,
        string $name,
        string $summary,
    ): void {
        $descriptor = $container->get(CommandCatalog::class)->require($name);
        $options = [
            [
                'name' => 'app',
                'summary' => 'Application target (web or api).',
                'value' => 'required',
                'repeatable' => false,
            ],
        ];
        TestCase::assertSame($command, $descriptor->serviceId());
        TestCase::assertSame($name, $descriptor->name());
        TestCase::assertSame($summary, $descriptor->summary());
        TestCase::assertSame('routes', $descriptor->group());
        TestCase::assertFalse($descriptor->hidden());
        TestCase::assertSame([], $descriptor->arguments());
        TestCase::assertSame(JsonLikeNormalizer::normalize($options), $descriptor->options());
        TestCase::assertSame($name, $command::NAME);
        TestCase::assertSame($summary, $command::SUMMARY);
        TestCase::assertSame('routes', $command::GROUP);
        TestCase::assertFalse($command::HIDDEN);
        TestCase::assertSame([], $command::ARGUMENTS);
        TestCase::assertSame($options, $command::OPTIONS);
    }

    /**
     * @param list<string> $options
     * @param array<string, mixed> $record
     */
    public static function assertCli(
        ContainerInterface $container,
        string $command,
        array $options,
        int $exit,
        array $record,
        int $uowCount = 1,
    ): void {
        RoutingLifecycleProbe::$before = RoutingLifecycleProbe::$after = [];
        $stdout = \fopen('php://temp', 'w+');
        $stderr = \fopen('php://temp', 'w+');
        TestCase::assertIsResource($stdout);
        TestCase::assertIsResource($stderr);
        try {
            TestCase::assertSame(
                $exit,
                $container->get(CliApplication::class)->run(
                    ['coretsia', $command, '--format=json', ...$options],
                    new TerminalCapabilities(false, false),
                    new ConsoleOutputWriter($stdout, $stderr),
                ),
            );
            \rewind($stdout);
            \rewind($stderr);
            TestCase::assertSame('', \stream_get_contents($stderr));
            $expected = [
                'schema' => 'coretsia.cli-output@1',
                'meta' => [
                    'command' => $command,
                    'outcome' => $exit === 0 ? 'success' : 'failure',
                    'exit_code' => $exit,
                ],
                'data' => ['records' => [$record]],
            ];
            TestCase::assertSame(StableJsonEncoder::encodeStableMap($expected), \stream_get_contents($stdout));
            TestCase::assertCount($uowCount, RoutingLifecycleProbe::$before);
            TestCase::assertCount($uowCount, RoutingLifecycleProbe::$after);
            if ($uowCount === 1) {
                TestCase::assertSame('cli', RoutingLifecycleProbe::$before[0]['type']);
                TestCase::assertSame(RoutingLifecycleProbe::$before[0], RoutingLifecycleProbe::$after[0]);
            }
        } finally {
            \fclose($stdout);
            \fclose($stderr);
        }
    }

    /** Exercise handled target/host errors through the same real CLI runner. */
    public static function assertCliTargetFailures(string $root, ContainerInterface $console, string $command): void
    {
        $code = RoutesCommandTargetInvalidException::ERROR_CODE;
        $before = RoutingDeclarationProvider::$definitions;
        self::assertCli(
            $console,
            $command,
            [],
            1,
            self::error($code, RoutesCommandTargetInvalidException::REASON_TARGET_REQUIRED),
        );
        foreach (['console', 'worker', 'WEB', 'private-target'] as $target) {
            self::assertCli(
                $console,
                $command,
                ['--app=' . $target],
                1,
                self::error($code, RoutesCommandTargetInvalidException::REASON_TARGET_INVALID),
            );
        }
        foreach (
            [
                [['--app'], CliInputInvalidException::REASON_OPTION_INVALID],
                [['--app=web', '--app=api'], CliInputInvalidException::REASON_OPTION_REPEATED],
            ] as [$options, $reason]
        ) {
            self::assertCli(
                $console,
                $command,
                $options,
                1,
                self::error(CliInputInvalidException::ERROR_CODE, $reason),
                0,
            );
        }
        TestCase::assertSame($before, RoutingDeclarationProvider::$definitions);
        $web = self::source($root, AppTarget::Web);
        TestCase::assertSame(
            'success',
            $console->get(KernelOpsInterface::class)->compileConfig(new KernelOpsRequest('console'))->outcome(),
        );
        $compiled = new ArtifactRuntimeBooter()->boot(
            new ArtifactRuntimeInput($root, $root . '/var/routing-cache/console'),
        );
        TestCase::assertTrue($compiled->get(RuntimeDiagnosticsInput::class)->artifactGenerationLoaded());
        $appPath = $root . '/config/app.php';
        $app = require $appPath;
        $marker = $root . '/unexpected-host-boot';
        self::write(
            $appPath,
            '<?php file_put_contents(' . \var_export($marker, true)
            . ', "booted"); throw new \RuntimeException("private source failure");',
        );
        try {
            $before = RoutingDeclarationProvider::$definitions;
            foreach ([$web, $compiled] as $invalidHost) {
                self::assertCli(
                    $invalidHost,
                    $command,
                    ['--app=web'],
                    1,
                    self::error($code, RoutesCommandTargetInvalidException::REASON_HOST_INVALID),
                );
                TestCase::assertFileDoesNotExist($marker);
                TestCase::assertSame($before, RoutingDeclarationProvider::$definitions);
            }
        } finally {
            self::php($appPath, $app);
        }

        $disabled = $app;
        $disabled['moduleOverrides']['api']['include'] = \array_values(
            \array_diff(
                $disabled['moduleOverrides']['api']['include'],
                ['platform.routing-fixture-api'],
            ),
        );
        $disabled['moduleOverrides']['api']['exclude'][] = 'platform.routing-fixture-api';
        $disabled['moduleOverrides']['api']['exclude'][] = 'platform.routing';
        // An unselected module's configuration must not be supplied.
        $configPath = $root . '/apps/api/config/routing.php';
        $config = \file_get_contents($configPath);
        TestCase::assertIsString($config);
        TestCase::assertTrue(\unlink($configPath));
        try {
            self::php($root . '/config/app.php', $disabled);
            self::assertCli(
                $console,
                $command,
                ['--app=api'],
                1,
                self::error($code, RoutesCommandTargetInvalidException::REASON_MODULE_NOT_ENABLED),
            );
        } finally {
            self::php($root . '/config/app.php', $app);
            self::write($configPath, $config);
        }
        try {
            self::write($configPath, '<?php throw new \\RuntimeException("private source failure");');
            self::assertCli(
                $console,
                $command,
                ['--app=api'],
                1,
                self::error(KernelOpsFailedException::ERROR_CODE, KernelOpsFailedException::REASON_HOST_BOOT_FAILED),
            );
        } finally {
            self::write($configPath, $config);
        }
    }

    /** @return array{type: string, code: string, message: string} */
    public static function error(string $code, string $reason): array
    {
        return ['type' => 'error', 'code' => $code, 'message' => $reason];
    }

    /** @return array<string, string> */
    public static function generationBytes(string $artifactRoot): array
    {
        $paths = new ArtifactGenerationPathResolver();
        $pointer = \file_get_contents($paths->currentPath($artifactRoot));
        TestCase::assertIsString($pointer);
        $generation = $paths->generation(
            $artifactRoot,
            ArtifactGenerationId::fromString(\rtrim($pointer, "\n")),
        );
        $bytes = ['current' => $pointer];
        foreach (
            [
                $generation->moduleManifestPath(),
                $generation->configPath(),
                $generation->containerPath(),
                $generation->generationManifestPath(),
            ] as $path
        ) {
            $content = \file_get_contents($path);
            TestCase::assertIsString($content);
            $bytes[\basename($path)] = $content;
        }
        return $bytes;
    }
}

final class RoutingHookDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $definitions
            ->classService(RoutingLifecycleProbe::class, RoutingLifecycleProbe::class)
            ->tag(ReservedTags::KERNEL_HOOK_BEFORE_UOW, RoutingLifecycleProbe::class)
            ->tag(ReservedTags::KERNEL_HOOK_AFTER_UOW, RoutingLifecycleProbe::class);
    }
}

final class RoutingLifecycleProbe implements BeforeUowHookInterface, AfterUowHookInterface
{
    /** @var list<array<string, mixed>> */
    public static array $before = [];
    /** @var list<array<string, mixed>> */
    public static array $after = [];

    public function beforeUow(array $context): void
    {
        self::$before[] = $context;
    }

    public function afterUow(array $context, array $result): void
    {
        self::$after[] = $context;
    }
}

final class RoutingWebDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        RoutingDeclarationProvider::$definitions++;
        RoutingDeclarationProvider::assertSourceAllowed();
        RoutingDeclarationProvider::$configurations[] = $context->configRoot('routing');
        $definitions
            ->classService('fixture.web.low', RoutingDeclarationProvider::class, ['web.low'])
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, 'fixture.web.low', 0)
            ->classService('fixture.web.high', RoutingDeclarationProvider::class, ['web.high'])
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, 'fixture.web.high', 10)
            ->classService(RoutingExternalFactory::class, RoutingExternalFactory::class)
            ->alias(ResponseFactoryInterface::class, RoutingExternalFactory::class)
            ->alias(StreamFactoryInterface::class, RoutingExternalFactory::class);
    }
}

final class RoutingApiDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        RoutingDeclarationProvider::$definitions++;
        RoutingDeclarationProvider::assertSourceAllowed();
        RoutingDeclarationProvider::$configurations[] = $context->configRoot('routing');
        $definitions
            ->classService('fixture.api', RoutingDeclarationProvider::class, ['api'])
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, 'fixture.api', 0);
    }
}

final class RoutingDeclarationProvider implements RouteProviderInterface
{
    public static int $definitions = 0;
    public static int $constructed = 0;
    public static int $identityReads = 0;
    public static int $invoked = 0;
    public static bool $forbidSource = false;
    public static bool $invalid = false;
    /** @var list<array<string, mixed>> */
    public static array $configurations = [];
    /** @var array<string, \Closure(): list<RouteDefinition>> */
    public static array $routeFactories = [];

    public function __construct(private readonly string $identity)
    {
        self::$constructed++;
        self::assertSourceAllowed();
    }

    public static function assertSourceAllowed(): void
    {
        if (self::$forbidSource) {
            throw new \LogicException('Runtime must not resolve source providers.');
        }
    }

    public function id(): string
    {
        self::$identityReads++;
        self::assertSourceAllowed();
        return $this->identity;
    }

    /** @return list<RouteDefinition> */
    public function routes(): array
    {
        self::$invoked++;
        self::assertSourceAllowed();
        if (self::$invalid) {
            return [new RouteDefinition('invalid', ['GET'], '/health', 'Handler::show')];
        }
        if (isset(self::$routeFactories[$this->identity])) {
            return self::$routeFactories[$this->identity]();
        }
        return match ($this->identity) {
            'web.high' => [
                new RouteDefinition('z.web', ['GET'], '/items/{id}', 'Handler::show'),
                new RouteDefinition('a.web', ['GET'], '/items/fixed', 'Handler::fixed'),
            ],
            'web.low' => [new RouteDefinition('m.web', ['POST'], '/other', 'Handler::other')],
            'api' => [new RouteDefinition('api.index', ['GET'], '/api-items', 'Handler::index')],
            default => throw new \LogicException('Unexpected fixture identity.'),
        };
    }
}

/** Readiness must resolve the factories but never start HTTP response creation. */
final class RoutingExternalFactory implements ResponseFactoryInterface, StreamFactoryInterface
{
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new \LogicException('HTTP runtime started during readiness.');
    }

    public function createStream(string $content = ''): StreamInterface
    {
        throw new \LogicException('HTTP runtime started during readiness.');
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new \LogicException('HTTP runtime started during readiness.');
    }

    /** @param resource $resource */
    public function createStreamFromResource($resource): StreamInterface
    {
        throw new \LogicException('HTTP runtime started during readiness.');
    }
}

final class RoutingCompileInput implements InputInterface
{
    public function tokens(): array
    {
        return ['routes:compile', '--app=web'];
    }

    public function commandName(): string
    {
        return 'routes:compile';
    }

    public function arguments(): array
    {
        return [];
    }

    public function options(): array
    {
        return ['app' => 'web'];
    }

    public function hasOption(string $name): bool
    {
        return $name === 'app';
    }

    public function option(string $name): string|bool|array|null
    {
        return $name === 'app' ? 'web' : null;
    }
}

final class RoutingCompileOutput implements OutputInterface
{
    /** @var list<array{string, string}> */
    public array $errors = [];

    public function text(string $text): void
    {
        throw new \LogicException('Unexpected text output.');
    }

    public function json(array $payload): void
    {
        throw new \LogicException('Unexpected success output.');
    }

    public function error(string $code, string $message): void
    {
        $this->errors[] = [$code, $message];
    }
}

/**
 * Process-local file-wrapper probe. All ordinary operations use native files;
 * only the selected fault is injected at the real PHP stream boundary.
 * Never install this wrapper in the PHPUnit runner process.
 */
final class RoutingFilesystemProbe
{
    /** @var resource|null PHP supplies the stream context. */
    public $context;

    public static int $artifactOpens = 0;
    public static int $artifactCloses = 0;
    public static int $artifactWriteOpens = 0;
    public static int $lockOpens = 0;
    public static int $temporaryBytes = 0;
    public static int $readBytes = 0;
    public static int $renames = 0;
    public static bool $comparisonUnderLock = false;
    public static bool $renameUnderLock = false;
    public static bool $faultReached = false;
    private static bool $locked = false;
    private static string $root;
    private static string $fault;

    /** @var resource|null */
    private $handle = null;
    private string $path;
    private bool $partial = false;

    public static function run(string $root, string $fault, \Closure $operation): mixed
    {
        self::$root = $root;
        self::$fault = $fault;
        self::$artifactOpens = self::$artifactCloses = self::$artifactWriteOpens = self::$lockOpens = 0;
        self::$temporaryBytes = self::$readBytes = self::$renames = 0;
        self::$comparisonUnderLock = self::$renameUnderLock = self::$faultReached = self::$locked = false;
        $unregistered = \stream_wrapper_unregister('file');
        $registered = $unregistered && \stream_wrapper_register('file', self::class);

        if (!$registered) {
            if ($unregistered) {
                \stream_wrapper_restore('file');
            }
            TestCase::fail('Could not install the filesystem probe.');
        }

        try {
            \clearstatcache();
            return $operation();
        } finally {
            \stream_wrapper_restore('file');
            \clearstatcache();
        }
    }

    private static function native(\Closure $operation): mixed
    {
        \stream_wrapper_restore('file');
        try {
            return $operation();
        } finally {
            \stream_wrapper_unregister('file');
            \stream_wrapper_register('file', self::class);
        }
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $path = \str_replace('\\', '/', $path);
        $this->path = $path;
        if ($path === self::$root . '/routes.php') {
            self::$artifactOpens++;
            if ($mode !== 'rb' && $mode !== 'r') {
                self::$artifactWriteOpens++;
            }
            self::$comparisonUnderLock = self::$locked;

            if (self::$fault === 'unreadable') {
                self::$faultReached = true;
                return false;
            }
        }
        if ($path === self::$root . '/routes.lock') {
            self::$lockOpens++;
        }
        $this->handle = self::native(static fn () => @\fopen($path, $mode));
        if (!\is_resource($this->handle)) {
            return false;
        }
        $openedPath = $path;
        return true;
    }

    public function stream_read(int $count): string|false
    {
        if (
            $this->path === self::$root . '/routes.php'
            && \in_array(self::$fault, ['grow', 'shrink'], true)
            && !self::$faultReached
        ) {
            self::$faultReached = true;
            self::native(function (): void {
                $handle = \fopen($this->path, 'r+b');
                TestCase::assertIsResource($handle);
                try {
                    $stat = \fstat($handle);
                    TestCase::assertIsArray($stat);
                    $size = self::$fault === 'grow'
                        ? RoutesArtifactSchema::MAX_ARTIFACT_BYTES * 2 + 1
                        : $stat['size'] - 1;
                    TestCase::assertTrue(\ftruncate($handle, $size));
                } finally {
                    \fclose($handle);
                }
            });
        }
        $bytes = \fread($this->handle, $count);
        if ($this->path === self::$root . '/routes.php' && \is_string($bytes)) {
            self::$readBytes += \strlen($bytes);
        }
        return $bytes;
    }

    private static function samePath(string $left, string $right): bool
    {
        $left = \str_replace('\\', '/', $left);
        $right = \str_replace('\\', '/', $right);

        return \PHP_OS_FAMILY === 'Windows'
            ? \strcasecmp($left, $right) === 0
            : $left === $right;
    }

    public function stream_write(string $bytes): int
    {
        if (
            self::$fault === 'write'
            && self::samePath(\dirname($this->path), self::$root)
            && !self::samePath($this->path, self::$root . '/routes.php')
            && !self::samePath($this->path, self::$root . '/routes.lock')
        ) {
            self::$faultReached = true;
            if ($this->partial) {
                return 0;
            }
            $this->partial = true;
            $written = (int) \fwrite($this->handle, \substr($bytes, 0, 8));
            self::$temporaryBytes += $written;
            return $written;
        }
        return (int) \fwrite($this->handle, $bytes);
    }

    public function stream_lock(int $operation): bool
    {
        if ($operation === 0) {
            return true;
        }
        $lock = $this->path === self::$root . '/routes.lock';
        if ($lock && self::$fault === 'lock' && ($operation & \LOCK_EX) === \LOCK_EX) {
            self::$faultReached = true;
            return false;
        }
        $result = \flock($this->handle, $operation);
        if ($lock && $result) {
            self::$locked = $operation !== \LOCK_UN;
            if (self::$fault === 'unlock' && $operation === \LOCK_UN) {
                self::$faultReached = true;
                return false;
            }
        }
        return $result;
    }

    /** @return array<mixed>|false */
    public function stream_stat(): array|false
    {
        $stat = \fstat($this->handle);
        if (\is_array($stat) && $this->path === self::$root . '/routes.php') {
            if (self::$fault === 'opened-nonregular') {
                self::$faultReached = true;
                $stat['mode'] = $stat[2] = 0040000 | 0700;
            } elseif (self::$fault === 'opened-identity') {
                self::$faultReached = true;
                $stat['ino'] = $stat[1] = $stat['ino'] ^ 1;
            }
        }
        return $stat;
    }

    public function stream_tell(): int|false
    {
        return \ftell($this->handle);
    }

    public function stream_seek(int $offset, int $whence = \SEEK_SET): bool
    {
        return \fseek($this->handle, $offset, $whence) === 0;
    }

    public function stream_eof(): bool
    {
        return \feof($this->handle);
    }

    public function stream_flush(): bool
    {
        return \fflush($this->handle);
    }

    public function stream_set_option(int $option, int $arg1, int $arg2): bool
    {
        return false;
    }

    public function stream_close(): void
    {
        if ($this->path === self::$root . '/routes.php') {
            self::$artifactCloses++;
        }
        \fclose($this->handle);
        if ($this->path === self::$root . '/routes.php' && self::$fault === 'replace-after-read' && !self::$faultReached) {
            self::$faultReached = true;
            self::native(function (): void {
                TestCase::assertTrue(\rename(self::$root . '/replacement.php', $this->path));
                \clearstatcache(true, $this->path);
            });
        }
    }

    /** @return array<mixed>|false */
    public function url_stat(string $path, int $flags): array|false
    {
        return self::native(static fn () => ($flags & \STREAM_URL_STAT_LINK) !== 0 ? @\lstat($path) : @\stat($path));
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return $option === \STREAM_META_ACCESS && self::native(static fn () => @\chmod($path, $value));
    }

    public function rename(string $from, string $to): bool
    {
        if (self::samePath($to, self::$root . '/routes.php')) {
            self::$renames++;
            self::$renameUnderLock = self::$locked;
            if (self::$fault === 'rename') {
                self::$faultReached = true;
                return false;
            }
        }
        return self::native(static fn () => @\rename($from, $to));
    }

    public function unlink(string $path): bool
    {
        return self::native(static fn () => @\unlink($path));
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return self::native(static fn () => @\mkdir($path, $mode, ($options & \STREAM_MKDIR_RECURSIVE) !== 0));
    }
}
