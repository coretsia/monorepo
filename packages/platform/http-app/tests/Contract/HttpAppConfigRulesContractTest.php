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

namespace Coretsia\Platform\HttpApp\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\ArrayEnvRepository;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapEnvSourcePolicy;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\ApplicationConfigLoader;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Source\ConfigSourceSet;
use Coretsia\Kernel\Config\Source\ConfigValueSourceArtifactHydrator;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use Coretsia\Platform\HttpApp\Middleware\RouterMiddleware;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpAppConfigRulesContractTest extends TestCase
{
    public function testDefaultsAndRulesAreExactDeclarativeSubtrees(): void
    {
        self::assertSame([
            'handlers' => [],
            'route_middleware' => [],
            'arguments' => [
                'allow_request' => true,
                'allow_route_params' => true,
                'allow_container_types' => [],
            ],
            'response' => ['json' => ['enabled' => true], 'html' => ['enabled' => true]],
            'tracing' => ['enabled' => true],
            'metrics' => ['enabled' => true],
            'logging' => ['enabled' => true],
        ], self::defaults());
        $bool = ['required' => true, 'type' => 'bool'];
        $map = static fn (array $keys): array => [
            'required' => true,
            'type' => 'map',
            'additionalKeys' => false,
            'keys' => $keys,
        ];
        $string = ['required' => true, 'type' => 'string'];
        $rules = require \dirname(__DIR__, 2) . '/config/rules.php';
        self::assertSame([
            'schemaVersion' => 1,
            'configRoot' => 'http_app',
            'additionalKeys' => false,
            'keys' => [
                'handlers' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => [
                        'type' => 'map',
                        'additionalKeys' => false,
                        'keys' => ['action_id' => $string, 'service_id' => $string],
                    ],
                ],
                'route_middleware' => [
                    'required' => true,
                    'type' => 'list',
                    'items' => ['type' => 'non-empty-string-no-ws'],
                ],
                'arguments' => $map([
                    'allow_request' => $bool,
                    'allow_route_params' => $bool,
                    'allow_container_types' => ['required' => true, 'type' => 'list', 'items' => ['type' => 'string']],
                ]),
                'response' => $map(['json' => $map(['enabled' => $bool]), 'html' => $map(['enabled' => $bool])]),
                'tracing' => $map(['enabled' => $bool]),
                'metrics' => $map(['enabled' => $bool]),
                'logging' => $map(['enabled' => $bool]),
            ],
        ], $rules);
        self::assertTrue(
            new ConfigValidator()->validate(['http_app' => self::defaults()], [self::ruleset()])->isSuccess(),
        );
        $disabled = self::defaults();
        foreach (
            [
                'arguments.allow_request',
                'arguments.allow_route_params',
                'response.json.enabled',
                'response.html.enabled',
                'tracing.enabled',
                'metrics.enabled',
                'logging.enabled',
            ] as $path
        ) {
            self::change($disabled, $path, false, false);
        }
        self::assertTrue(new ConfigValidator()->validate(['http_app' => $disabled], [self::ruleset()])->isSuccess());
        // A rules file is data only: disallow executable tokens, calls, variables and class references.
        $source = \file_get_contents(\dirname(__DIR__, 2) . '/config/rules.php');
        self::assertIsString($source);
        foreach (\token_get_all($source) as $token) {
            if (\is_array($token)) {
                self::assertContains($token[0], [
                    T_OPEN_TAG,
                    T_DECLARE,
                    T_STRING,
                    T_LNUMBER,
                    T_CONSTANT_ENCAPSED_STRING,
                    T_RETURN,
                    T_DOUBLE_ARROW,
                    T_WHITESPACE,
                    T_COMMENT,
                    T_DOC_COMMENT,
                ]);
                if ($token[0] === T_STRING) {
                    self::assertContains($token[1], ['strict_types', 'true', 'false']);
                }
            } else {
                self::assertContains($token, ['(', ')', '=', ';', '[', ']', ',']);
            }
        }
    }

    #[DataProvider('invalidStructures')]
    public function testCanonicalValidatorRejectsInvalidStructure(array $config, string $path, string $reason): void
    {
        $result = new ConfigValidator()->validate(['http_app' => $config], [self::ruleset()]);
        self::assertTrue($result->isFailure());
        self::assertCount(1, $result->violations());
        $violation = $result->violations()[0];
        self::assertSame('http_app', $violation->root());
        self::assertSame($path, $violation->path());
        self::assertSame($reason, $violation->reason());
        self::assertStringNotContainsString(
            'SYNTHETIC_PRIVATE',
            \json_encode($result->toArray(), \JSON_THROW_ON_ERROR),
        );
    }

    /** @return iterable<string, array{array, string, string}> */
    public static function invalidStructures(): iterable
    {
        $required = [
            'handlers',
            'route_middleware',
            'arguments',
            'arguments.allow_request',
            'arguments.allow_route_params',
            'arguments.allow_container_types',
            'response',
            'response.json',
            'response.json.enabled',
            'response.html',
            'response.html.enabled',
            'tracing',
            'tracing.enabled',
            'metrics',
            'metrics.enabled',
            'logging',
            'logging.enabled',
        ];
        foreach ($required as $path) {
            $config = self::defaults();
            self::change($config, $path, null, true);
            yield 'missing-' . $path => [$config, $path, 'required'];
        }
        $flags = [
            'arguments.allow_request',
            'arguments.allow_route_params',
            'response.json.enabled',
            'response.html.enabled',
            'tracing.enabled',
            'metrics.enabled',
            'logging.enabled',
        ];
        foreach ($flags as $path) {
            foreach ([null, 0, 1, 'true', 'false', [], 'SYNTHETIC_PRIVATE'] as $i => $value) {
                $config = self::defaults();
                self::change($config, $path, $value, false);
                yield 'flag-' . $path . '-' . $i => [$config, $path, 'type'];
            }
        }
        foreach (
            [
                '',
                'arguments.',
                'response.',
                'response.json.',
                'response.html.',
                'tracing.',
                'metrics.',
                'logging.',
            ] as $prefix
        ) {
            $config = self::defaults();
            $path = $prefix . 'unexpected';
            self::change($config, $path, 'SYNTHETIC_PRIVATE', false);
            yield 'unknown-' . $path => [$config, $path, 'unknown-key'];
        }
        foreach (
            [
                'missing-action' => [['service_id' => 'action.service'], 'handlers[0].action_id', 'required'],
                'missing-service' => [['action_id' => 'action.show'], 'handlers[0].service_id', 'required'],
                'extra-field' => [
                    [
                        'action_id' => 'action.show',
                        'service_id' => 'action.service',
                        'extra' => 'SYNTHETIC_PRIVATE',
                    ],
                    'handlers[0].extra',
                    'unknown-key',
                ],
                'wrong-action-type' => [
                    ['action_id' => 42, 'service_id' => 'action.service'],
                    'handlers[0].action_id',
                    'type',
                ],
                'wrong-service-type' => [
                    ['action_id' => 'action.show', 'service_id' => false],
                    'handlers[0].service_id',
                    'type',
                ],
                'scalar-record' => ['SYNTHETIC_PRIVATE', 'handlers[0]', 'type'],
            ] as $name => [$record, $path, $reason]
        ) {
            $config = self::defaults();
            $config['handlers'] = [$record];
            yield $name => [$config, $path, $reason];
        }
        foreach (['handlers', 'route_middleware', 'arguments.allow_container_types'] as $path) {
            foreach (
                [
                    null,
                    'SYNTHETIC_PRIVATE',
                    ['action.show' => 'action.service'],
                    [1 => 'action.service'],
                ] as $i => $value
            ) {
                $config = self::defaults();
                self::change($config, $path, $value, false);
                yield 'not-list-' . $path . '-' . $i => [$config, $path, 'type'];
            }
        }
        foreach ([42, false, null, []] as $i => $type) {
            $config = self::defaults();
            $config['arguments']['allow_container_types'] = [$type];
            yield 'allowlist-item-' . $i => [$config, 'arguments.allow_container_types[0]', 'type'];
        }
        foreach (['', 'a b', "a\tb", "a\nb", 42, null, false, []] as $i => $id) {
            $config = self::defaults();
            $config['route_middleware'] = [$id];
            yield 'route-id-' . $i => [$config, 'route_middleware[0]', 'type'];
        }
    }

    #[DataProvider('semanticFailures')]
    public function testSemanticValidationIsProviderOwnedAndSafe(string $path, array $value, string $reason): void
    {
        $config = self::defaults();
        self::change($config, $path, $value, false);
        // Structurally valid data must reach the provider's semantic boundary.
        self::assertTrue(new ConfigValidator()->validate(['http_app' => $config], [self::ruleset()])->isSuccess());
        self::assertProviderRejects($config, $reason);
    }

    #[DataProvider('invalidProviderInputs')]
    public function testProviderRejectsMissingListsAndInvalidFlagsWithoutFallback(array $config, string $reason): void
    {
        self::assertProviderRejects($config, $reason);
    }

    /** @return iterable<string, array{array, string}> */
    public static function invalidProviderInputs(): iterable
    {
        $lists = [
            'handlers' => 'http-app-handlers-invalid',
            'arguments.allow_container_types' => 'http-app-container-types-invalid',
            'route_middleware' => 'http-app-route-middleware-invalid',
        ];
        foreach ($lists as $path => $reason) {
            $config = self::defaults();
            self::change($config, $path, null, true);
            yield 'missing-' . $path => [$config, $reason];
            $config = self::defaults();
            self::change($config, $path, null, false);
            yield 'null-' . $path => [$config, $reason];
        }
        $flags = [
            'arguments.allow_request',
            'arguments.allow_route_params',
            'response.json.enabled',
            'response.html.enabled',
            'tracing.enabled',
            'metrics.enabled',
            'logging.enabled',
        ];
        foreach ($flags as $path) {
            $config = self::defaults();
            self::change($config, $path, null, true);
            yield 'missing-' . $path => [$config, 'http-app-config-invalid'];
            $config = self::defaults();
            self::change($config, $path, 'false', false);
            yield 'string-' . $path => [$config, 'http-app-config-invalid'];
        }
    }

    private static function assertProviderRejects(array $config, string $reason): void
    {
        $definitions = new ContainerDefinitionBuilder();
        $builder = new ContainerBuilder(['http_app' => $config]);
        $provider = new HttpAppServiceProvider();
        foreach (
            [
                static fn () => $provider->define(
                    $definitions,
                    new ContainerDefinitionContext(['http_app' => $config]),
                ),
                static fn () => $builder->register($provider),
            ] as $operation
        ) {
            try {
                $operation();
            } catch (\InvalidArgumentException $exception) {
                self::assertSame(\InvalidArgumentException::class, $exception::class);
                self::assertSame($reason, $exception->getMessage());
                self::assertNull($exception->getPrevious());
                continue;
            }

            self::fail('invalid-http-app-semantic-config-accepted');
        }
        self::assertTrue($definitions->build()->isEmpty());
        self::assertSame([], $builder->serviceIds());
    }

    /** @return iterable<string, array{string, array, string}> */
    public static function semanticFailures(): iterable
    {
        $record = ['action_id' => '_show.item-edit:detail', 'service_id' => 'action.service'];
        yield 'duplicate-action' => ['handlers', [$record, $record], 'http-app-handlers-invalid'];
        foreach (['', '1action', 'a b', 'A\\B', \str_repeat('a', 257)] as $i => $id) {
            yield 'action-' . $i => [
                'handlers',
                [['action_id' => $id, 'service_id' => 'action.service']],
                'http-app-handlers-invalid',
            ];
        }
        foreach (['', 'SYNTHETIC_PRIVATE value', '0', \str_repeat('s', 257), "SYNTHETIC_PRIVATE\x7f"] as $i => $id) {
            yield 'service-' . $i => [
                'handlers',
                [['action_id' => 'action.show', 'service_id' => $id]],
                'http-app-handlers-invalid',
            ];
        }
        yield 'duplicate-type' => [
            'arguments.allow_container_types',
            ['App\\Service', 'App\\Service'],
            'http-app-container-types-invalid',
        ];
        foreach (['', 'App\\Bad-Type', 'App\\Service::factory', 'SYNTHETIC_PRIVATE value'] as $i => $id) {
            yield 'type-' . $i => ['arguments.allow_container_types', [$id], 'http-app-container-types-invalid'];
        }
        yield 'duplicate-route-service' => [
            'route_middleware',
            ['route.one', 'route.one'],
            'http-app-route-middleware-invalid',
        ];
        foreach (['0', \str_repeat('s', 257), "SYNTHETIC_PRIVATE\x7f", RouterMiddleware::class] as $i => $id) {
            yield 'route-service-' . $i => ['route_middleware', [$id], 'http-app-route-middleware-invalid'];
        }
    }

    public function testCanonicalConfigCompilationAndArtifactHydrationPreserveRegistrationValuesAndSourcePaths(): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-http-app-config-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root . '/config', 0700, true));
        $file = $root . '/config/http_app.php';
        $handlers = [
            ['action_id' => '_Show.item-edit:detail', 'service_id' => 'action:show'],
            ['action_id' => \str_repeat('a', 256), 'service_id' => 'App\\Action'],
        ];
        $middleware = ['z.middleware', 'App\\RouteMiddleware', 'route:custom'];
        try {
            self::assertNotSame(
                false,
                \file_put_contents(
                    $file,
                    '<?php return ' . \var_export([
                        'handlers' => $handlers,
                        'route_middleware' => $middleware,
                    ], true) . ';',
                ),
            );
            $processor = new DirectiveProcessor(new ConfigNamespaceGuard(['coretsia', '_internal']));
            $kernel = new ConfigKernel(
                new ConfigMerger($processor),
                new ConfigRulesLoader(),
                new ConfigValidator(),
                new ConfigExplainer(),
                new PackageDefaultsConfigLoader($processor),
                new ApplicationConfigLoader($processor),
                new EnvironmentOverlayLoader(),
                new NoopMeter(),
                new NoopTracer(),
                new Stopwatch(),
                new NoopLogger(),
            );
            // Only the HttpApp-owned config sources participate in this isolated config contract.
            $id = ModuleId::fromString('platform.http-app');
            $plan = new ModulePlan(
                'web',
                [$id],
                [],
                [$id],
                [new ModulePlanEntry($id, 'coretsia/platform-http-app', [], [])],
            );
            $source = ['root' => 'http_app', 'packageId' => 'platform/http-app', 'moduleId' => 'platform.http-app'];
            $package = \dirname(__DIR__, 2);
            $sources = new ConfigSourceSet(
                [[...$source, 'path' => 'config/http_app.php', 'filesystemPath' => $package . '/config/http_app.php']],
                [[...$source, 'path' => 'config/rules.php', 'filesystemPath' => $package . '/config/rules.php']],
                ['http_app'],
                [],
                [],
                [],
            );
            $compiled = $kernel->compile(
                new BootstrapConfig(
                    'test',
                    'default',
                    false,
                    'var/cache',
                    BootstrapEnvSourcePolicy::StrictDotenv,
                    AppTarget::Web,
                    $root,
                    new ResolvedModuleOverrides([], []),
                ),
                $plan,
                new ArrayEnvRepository([], []),
                $sources,
            );
            self::assertTrue($compiled['validation']->isSuccess());
            self::assertSame($handlers, $compiled['config']['http_app']['handlers']);
            self::assertSame($middleware, $compiled['config']['http_app']['route_middleware']);
            $envelope = new CompiledConfigBuilder(new ArtifactEnvelopeFactory())->build(
                $compiled,
                \str_repeat('a', 64),
                'test',
            );
            new ArtifactSchemaValidator()->validateExpected($envelope, 'config', 1);
            $hydrated = ConfigValueSourceArtifactHydrator::hydrate($envelope['payload']['sources']);
            $repository = new ArrayConfigRepository($envelope['payload']['config'], $hydrated);
            self::assertSame($compiled['config'], $repository->all());
            $sourceRepository = new ArrayConfigRepository($compiled['config'], $compiled['sources']);
            self::assertSame(
                \array_map(static fn ($source): array => $source->toArray(), $sourceRepository->explain()),
                \array_map(static fn ($source): array => $source->toArray(), $repository->explain()),
            );
            self::assertSame($handlers, $repository->get('http_app.handlers'));
            self::assertSame($middleware, $repository->get('http_app.route_middleware'));
            $paths = \array_map(static fn ($source): ?string => $source->keyPath(), $hydrated);
            foreach (
                [
                    'http_app.handlers[0].action_id',
                    'http_app.handlers[0].service_id',
                    'http_app.handlers[1].action_id',
                    'http_app.route_middleware[0]',
                    'http_app.route_middleware[1]',
                    'http_app.route_middleware[2]',
                ] as $path
            ) {
                self::assertContains($path, $paths);
            }
            $json = \json_encode($envelope['payload']['sources'], \JSON_THROW_ON_ERROR);
            foreach (['_Show.item-edit:detail', 'action:show', 'route:custom', \str_repeat('a', 256)] as $value) {
                self::assertStringNotContainsString($value, $json);
            }
            foreach (
                [
                    'http_app.handlers._Show.item-edit:detail',
                    'http_app.route_middleware.App\\RouteMiddleware',
                ] as $path
            ) {
                $invalid = $envelope['payload']['sources'];
                $invalid[0]['keyPath'] = $path;
                try {
                    ConfigValueSourceArtifactHydrator::hydrate($invalid);
                } catch (ArtifactInvalidException $exception) {
                    self::assertSame('artifact-schema-invalid', $exception->reason());
                    self::assertSame('CORETSIA_ARTIFACT_INVALID: artifact-schema-invalid', $exception->getMessage());
                    self::assertNull($exception->getPrevious());
                    continue;
                }

                self::fail('noncanonical-http-app-source-path-accepted');
            }
            $definitions = new ContainerDefinitionBuilder();
            new HttpAppServiceProvider()->define($definitions, new ContainerDefinitionContext($repository->all()));
            foreach (['action:show', 'App\\Action', ...$middleware] as $id) {
                self::assertContains($id, $definitions->build()->requiredServiceIds());
            }
        } finally {
            if (\is_file($file)) {
                \unlink($file);
            }
            \rmdir($root . '/config');
            \rmdir($root);
        }
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return require \dirname(__DIR__, 2) . '/config/http_app.php';
    }

    private static function ruleset(): ConfigRuleset
    {
        return ConfigRuleset::fromArray('http_app', require \dirname(__DIR__, 2) . '/config/rules.php');
    }

    private static function change(array &$config, string $path, mixed $value, bool $remove): void
    {
        $segments = \explode('.', $path);
        $key = \array_pop($segments);
        $node = &$config;
        foreach ($segments as $segment) {
            $node = &$node[$segment];
        }
        if ($remove) {
            unset($node[$key]);
        } else {
            $node[$key] = $value;
        }
    }
}
