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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Contracts\Module\ModuleDescriptor;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Module\ModuleManifest;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Observability\Errors\NoopErrorReporter;
use Coretsia\Foundation\Observability\Metrics\NoopMeter;
use Coretsia\Foundation\Observability\Tracing\NoopTracer;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\RuntimeContainerSeedSet;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\ModuleSelection;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Coretsia\Platform\Cli\Mapper\CliExceptionMapper;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Provider\CliServiceProvider;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioRuntime;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliApplicationFixtureHarness;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;

final class CliCanonicalErrorHandlerIntegrationTest extends CliIntegrationTestCase
{
    public function testCanonicalConsoleHostClosesTheModulePlanAndResolvesOneGlobalHandlerAndRedactor(): void
    {
        $root = self::consumer();
        try {
            $container = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $container->get(ModulePlan::class);
            self::assertInstanceOf(ModulePlan::class, $plan);
            foreach (
                [
                    'core.foundation',
                    'core.kernel',
                    'platform.cli',
                    'platform.errors',
                    'platform.redaction',
                ] as $id
            ) {
                self::assertTrue($plan->hasEnabledModule($id), $id);
            }
            $order = \array_map(static fn (ModuleId $id): string => $id->value(), $plan->topologicalOrder());
            self::assertLessThan(
                \array_search('platform.cli', $order, true),
                \array_search('platform.errors', $order, true),
            );
            self::assertLessThan(
                \array_search('platform.errors', $order, true),
                \array_search('platform.redaction', $order, true),
            );

            $handler = $container->get(ErrorHandlerInterface::class);
            self::assertInstanceOf(ErrorHandler::class, $handler);
            self::assertSame($handler, $container->get(ErrorHandler::class));
            self::assertSame($handler, $container->get(ErrorHandlerInterface::class));
            $redactor = $container->get(SensitiveDataRedactorInterface::class);
            self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
            self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));
            self::assertSame($redactor, new \ReflectionProperty(ErrorHandler::class, 'redactor')->getValue($handler));
            self::assertSame(
                $redactor,
                new \ReflectionProperty(OutputFormatter::class, 'redactor')->getValue(
                    $container->get(OutputFormatter::class),
                ),
            );
            self::assertInstanceOf(NoopErrorReporter::class, $container->get(ErrorReporterPortInterface::class));
            self::assertInstanceOf(
                NoopTracer::class,
                $container->get(TracerPortInterface::class),
            );
            self::assertInstanceOf(
                NoopMeter::class,
                $container->get(MeterPortInterface::class),
            );
            self::assertInstanceOf(CliApplication::class, $container->get(CliApplication::class));

            $registered = $container->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
            self::assertSame(
                [CliExceptionMapper::class],
                \array_map(static fn ($tagged): string => $tagged->id(), $registered)
            );
            self::assertSame(100, $registered[0]->priority());
            self::assertSame([], $registered[0]->meta());
            self::assertInstanceOf(CliExceptionMapper::class, $container->get($registered[0]->id()));

            $definitions = new ContainerDefinitionBuilder();
            new CliServiceProvider()->define($definitions, new ContainerDefinitionContext(self::config()));
            $operations = $definitions->build()->toDescriptorStream();
            self::assertContains(ErrorHandlerInterface::class, $definitions->build()->requiredServiceIds());
            foreach ($operations as $operation) {
                self::assertNotSame(ErrorHandlerInterface::class, $operation['id'] ?? null);
                self::assertNotSame(ErrorHandlerInterface::class, $operation['alias'] ?? null);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testCanonicalHandlerPreservesKnownCliAndKernelOpsCodesWhileUnknownsRemainFallback(): void
    {
        $container = self::sourceContainer();
        $handler = $container->get(ErrorHandlerInterface::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        foreach (self::publicFailures() as [$failure, $code, $reason]) {
            $result = $handler->handle($failure);
            self::assertSame($code, $result->code());
            self::assertSame($reason, $result->message());
            self::assertSame([], $result->extensions());
            self::assertNull($result->httpStatus());
        }
        $unknown = new \RuntimeException('PRIVATE_RAW_EXCEPTION', 0, new \LogicException('PRIVATE_PREVIOUS'));
        $descriptor = $handler->handle(
            $unknown,
            new ErrorHandlingContext('cli', null, ['marker' => 'PRIVATE_CONTEXT']),
        );
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        self::assertSame('Unhandled exception.', $descriptor->message());
        $json = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
        foreach (['PRIVATE_RAW_EXCEPTION', 'PRIVATE_PREVIOUS', 'PRIVATE_CONTEXT'] as $private) {
            self::assertStringNotContainsString($private, $json);
        }
    }

    public function testEarlierOwnerMapperClaimsTheExceptionWithoutCliPriorityOverridesOrCandidateFiltering(): void
    {
        $builder = self::sourceBuilder();
        $builder->instance(CliCanonicalOwnerPriorityMapper::class, new CliCanonicalOwnerPriorityMapper());
        $builder->tag(ReservedTags::ERROR_MAPPER, CliCanonicalOwnerPriorityMapper::class, priority: 200);
        $container = $builder->build();
        $tags = $container->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
        self::assertSame(
            [CliCanonicalOwnerPriorityMapper::class, CliExceptionMapper::class],
            \array_map(static fn ($tagged): string => $tagged->id(), $tags)
        );
        self::assertSame([200, 100], \array_map(static fn ($tagged): int => $tagged->priority(), $tags));
        $handler = $container->get(ErrorHandlerInterface::class);
        $claimed = $handler->handle(
            CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_UNKNOWN),
        );
        self::assertSame('OWNER_PREEMPTED', $claimed->code());
        self::assertSame('Earlier owner mapper.', $claimed->message());
        $kernel = $handler->handle(new KernelOpsFailedException(KernelOpsFailedException::REASON_OPERATION_FAILED));
        self::assertSame(KernelOpsFailedException::ERROR_CODE, $kernel->code());
        self::assertSame(KernelOpsFailedException::REASON_OPERATION_FAILED, $kernel->message());
        self::assertSame([], $kernel->extensions());
    }

    public function testCliRenderingPreservesJsonPlainAndTableKnownErrorsAndUnknownPublicFallback(): void
    {
        $container = self::sourceContainer();
        $fixture = self::cliFixture($container);
        foreach (['json', 'plain', 'table'] as $format) {
            $result = $fixture->run(['cli:missing-command', '--format=' . $format, '--color=never']);
            self::assertSame(1, $result['exit']);
            if ($format === 'json') {
                self::assertSame('', $result['stderr']);
                $document = self::jsonRecord($result['stdout']);
                self::assertSame('failure', $document['meta']['outcome']);
                self::assertSame(1, $document['meta']['exit_code']);
                self::assertSame([
                    'code' => CliInputInvalidException::ERROR_CODE,
                    'message' => CliInputInvalidException::REASON_COMMAND_UNKNOWN,
                    'type' => 'error',
                ], $document['data']['records'][0]);
            } else {
                self::assertSame('', $result['stdout']);
                self::assertSame(
                    CliInputInvalidException::ERROR_CODE . ': ' . CliInputInvalidException::REASON_COMMAND_UNKNOWN . "\n",
                    $result['stderr'],
                );
            }
            self::assertStringNotContainsString('CORETSIA_UNHANDLED_EXCEPTION', $result['stdout'] . $result['stderr']);
        }
        $raw = new \RuntimeException('PRIVATE_THROWABLE', 0, new \LogicException('PRIVATE_PREVIOUS'));
        $descriptor = $container->get(ErrorHandlerInterface::class)->handle(
            $raw,
            new ErrorHandlingContext('cli', null, ['marker' => 'PRIVATE_CONTEXT']),
        );
        self::assertSame('CORETSIA_UNHANDLED_EXCEPTION', $descriptor->code());
        $output = new CommandOutputBuffer();
        new ExceptionRenderer()->render($descriptor, $output);
        $records = $output->finalize()->records();
        self::assertSame([
            ['code' => 'CORETSIA_CLI_INTERNAL_ERROR', 'message' => 'Internal CLI error.', 'type' => 'error'],
        ], $records);
        $rendered = \json_encode($records, \JSON_THROW_ON_ERROR);
        foreach (['PRIVATE_THROWABLE', 'PRIVATE_PREVIOUS', 'PRIVATE_CONTEXT', __FILE__] as $private) {
            self::assertStringNotContainsString($private, $rendered);
        }
    }

    public function testAllKnownFailuresPreservePublicPairsThroughEachRealOutputFormatter(): void
    {
        $container = self::sourceContainer();
        $handler = $container->get(ErrorHandlerInterface::class);
        $renderer = new ExceptionRenderer();
        $formatter = $container->get(OutputFormatter::class);

        foreach (self::publicFailures() as [$failure, $code, $reason]) {
            $descriptor = $handler->handle($failure);
            $output = new CommandOutputBuffer();
            $renderer->render($descriptor, $output);
            $batch = $output->finalize();
            self::assertSame([['code' => $code, 'message' => $reason, 'type' => 'error']], $batch->records());

            foreach (['json', 'plain', 'table'] as $format) {
                $result = $formatter->format($batch, null, 1, $format, false);
                if ($format === 'json') {
                    self::assertSame('', $result->stderrBytes());
                    $document = \json_decode($result->stdoutBytes(), true, flags: \JSON_THROW_ON_ERROR);
                    self::assertSame(
                        [['code' => $code, 'message' => $reason, 'type' => 'error']],
                        $document['data']['records']
                    );
                    self::assertSame(1, $document['meta']['exit_code']);
                } else {
                    self::assertSame('', $result->stdoutBytes());
                    self::assertStringContainsString($code, $result->stderrBytes());
                    self::assertStringContainsString($reason, $result->stderrBytes());
                }
                self::assertStringNotContainsString(
                    'Stack trace',
                    $result->stdoutBytes() . $result->stderrBytes(),
                );
            }
        }
    }

    public function testNormalCliExitCodesAndUnknownThrowableTranslationArePreserved(): void
    {
        foreach ([0, 17, 255] as $exitCode) {
            $fixture = new BoundaryScenarioFixture();
            BoundaryScenarioCommand::$exitCode = $exitCode;
            $result = $fixture->run(['external:boundary', '--format=json']);
            self::assertSame($exitCode, $result['exit']);
            self::assertSame('', $result['stderr']);
            $document = self::jsonRecord($result['stdout']);
            self::assertSame($exitCode, $document['meta']['exit_code']);
            self::assertSame($exitCode === 0 ? 'success' : 'failure', $document['meta']['outcome']);
            self::assertSame(1, $fixture->runtime->calls);
        }

        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$throw = true;
        $failed = $fixture->run(['external:boundary', '--format=json']);
        self::assertSame(1, $failed['exit']);
        $record = self::jsonRecord($failed['stdout'])['data']['records'][0];
        self::assertSame('CORETSIA_CLI_INTERNAL_ERROR', $record['code']);
        self::assertSame('Internal CLI error.', $record['message']);
        self::assertStringNotContainsString('PRIVATE_OWNER_THROWABLE', $failed['stdout']);
    }

    public function testUnknownCommandSuggestionsAndWorkflowFailuresRetainExitAndRecordSemantics(): void
    {
        $container = self::sourceContainer();
        $runtime = new BoundaryScenarioRuntime();
        $fixture = self::cliFixture($container, $runtime);
        $unknown = $fixture->run(['workflw:run', '--format=json']);
        self::assertSame(1, $unknown['exit']);
        self::assertSame('', $unknown['stderr']);
        $records = self::jsonRecord($unknown['stdout'])['data']['records'];
        self::assertSame(CliInputInvalidException::ERROR_CODE, $records[0]['code']);
        self::assertSame(CliInputInvalidException::REASON_COMMAND_UNKNOWN, $records[0]['message']);
        self::assertCount(2, $records);
        self::assertSame('json', $records[1]['type']);
        self::assertContains('workflow:run', $records[1]['payload']['suggestions']);

        $workflow = $fixture->run(['workflow:run', 'missing', '--format=json']);
        self::assertSame(1, $workflow['exit']);
        self::assertSame('', $workflow['stderr']);
        $error = self::jsonRecord($workflow['stdout'])['data']['records'][0];
        self::assertSame(WorkflowNotFoundException::ERROR_CODE, $error['code']);
        self::assertSame(
            WorkflowNotFoundException::REASON_WORKFLOW_NOT_FOUND,
            $error['message'],
        );
        self::assertSame('error', $error['type']);
        self::assertStringNotContainsString('PRIVATE_', $workflow['stdout']);
        self::assertSame(0, $runtime->calls);
    }

    public function testReportingDisabledStillMapsAndRedactsThroughTheCanonicalPort(): void
    {
        $config = self::config();
        self::assertFalse($config['errors']['reporting']['enabled']);
        $builder = self::sourceBuilder();
        $builder->instance(CliCanonicalSensitiveMapper::class, new CliCanonicalSensitiveMapper());
        $builder->tag(ReservedTags::ERROR_MAPPER, CliCanonicalSensitiveMapper::class, priority: 200);
        $container = $builder->build();
        $handler = $container->get(ErrorHandlerInterface::class);
        $redactor = $container->get(SensitiveDataRedactorInterface::class);
        self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
        self::assertSame($redactor, new \ReflectionProperty(ErrorHandler::class, 'redactor')->getValue($handler));
        self::assertInstanceOf(NoopErrorReporter::class, $container->get(ErrorReporterPortInterface::class));

        $descriptor = $handler->handle(new \RuntimeException('PRIVATE_THROWABLE_MESSAGE'));
        self::assertSame('OWNER_PUBLIC', $descriptor->code());
        self::assertSame('Publicly approved reason.', $descriptor->message());
        self::assertArrayHasKey('contact', $descriptor->extensions());
        self::assertNotSame('owner@example.com', $descriptor->extensions()['contact']);
        $encoded = \json_encode($descriptor->toArray(), \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('owner@example.com', $encoded);
        self::assertStringNotContainsString('PRIVATE_THROWABLE_MESSAGE', $encoded);

        $failure = CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_UNKNOWN);
        self::assertSame(CliInputInvalidException::ERROR_CODE, $handler->handle($failure)->code());
        self::assertSame(CliInputInvalidException::REASON_OPTION_UNKNOWN, $handler->handle($failure)->message());
    }

    public function testCompiledAndSourceDefinitionsResolveEquivalentCanonicalMapperAndHandlerBehavior(): void
    {
        $config = self::config();
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);
        foreach (self::providers() as $provider) {
            $provider->define($definitions, $context);
        }
        $definitions->classService(KernelRuntimeInterface::class, BoundaryScenarioRuntime::class);
        $graph = new ContainerCompiler(
            new NoopTracer(),
            new NoopMeter(),
            new NoopLogger(),
            new Stopwatch(),
        )
            ->compile($definitions->build())->toArray();
        self::assertSame(ErrorHandler::class, $graph['aliases'][ErrorHandlerInterface::class]);
        $envelope = new ArtifactEnvelopeFactory()->container(
            \str_repeat('a', 64),
            ['kind' => 'compiled', 'compiled' => true, ...$graph],
        );
        $seeds = new RuntimeContainerSeedSet([
            ConfigRepositoryInterface::class => new ArrayConfigRepository($config),
            ModulePlan::class => self::modulePlan(),
            RuntimePathContext::class => new RuntimePathContext('/tmp/coretsia-cli-fixture', '/tmp/coretsia-cli-cache'),
        ]);
        $compiled = new CompiledContainerFactory(new ArtifactSchemaValidator())->buildFromEnvelope($envelope, $seeds);
        $source = self::sourceContainer();
        foreach (
            [
                ErrorHandlerInterface::class,
                SensitiveDataRedactorInterface::class,
                CliExceptionMapper::class,
            ] as $id
        ) {
            self::assertSame($source->get($id)::class, $compiled->get($id)::class);
        }
        self::assertSame($compiled->get(ErrorHandlerInterface::class), $compiled->get(ErrorHandler::class));
        $sourceTags = $source->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
        $compiledTags = $compiled->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
        self::assertSame(
            \array_map(
                static fn ($tagged): array => [$tagged->id(), $tagged->priority(), $tagged->meta()],
                $sourceTags,
            ),
            \array_map(
                static fn ($tagged): array => [$tagged->id(), $tagged->priority(), $tagged->meta()],
                $compiledTags
            ),
        );
        foreach (
            [
                CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_UNKNOWN),
                new KernelOpsFailedException(KernelOpsFailedException::REASON_OPERATION_FAILED),
                new \RuntimeException('PRIVATE_UNHANDLED'),
            ] as $failure
        ) {
            $sourceDescriptor = $source->get(ErrorHandlerInterface::class)->handle($failure);
            $compiledDescriptor = $compiled->get(ErrorHandlerInterface::class)->handle($failure);
            self::assertSame($sourceDescriptor->toArray(), $compiledDescriptor->toArray());
        }
        self::assertSame(
            CliInputInvalidException::ERROR_CODE,
            $compiled->get(ErrorHandlerInterface::class)
                ->handle(
                    CliInputInvalidException::withReason(CliInputInvalidException::REASON_COMMAND_UNKNOWN),
                )->code(),
        );
    }

    public function testDoctorIsIndependentAndExcludedErrorsFailClosedBeforeNormalCliDispatch(): void
    {
        $root = self::consumer();
        try {
            self::write($root . '/config/app.php', '<?php throw new \\RuntimeException("PRIVATE_CONFIG_BOOM");');
            $doctor = self::execute($root . '/vendor/bin/coretsia', ['doctor']);
            self::assertSame(0, $doctor['exit']);
            self::assertSame('', $doctor['stderr']);
            self::assertStringNotContainsString('PRIVATE_CONFIG_BOOM', $doctor['stdout']);
            self::assertStringContainsString('Status: READY', $doctor['stdout']);
            self::assertSafeFailure(
                self::execute($root . '/vendor/bin/coretsia', ['list']),
                'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed',
            );

            self::write(
                $root . '/config/app.php',
                "<?php return ['moduleOverrides' => ['console' => ['include' => [], 'exclude' => ['platform.errors']]]];",
            );
            self::assertSafeFailure(
                self::execute($root . '/vendor/bin/coretsia', ['list']),
                'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed',
            );
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testMissingAndExplicitlyExcludedErrorsFailCanonicalKernelModuleSelection(): void
    {
        $resolver = new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()));
        $unselected = $resolver->resolve('console', self::manifest(true), new ModuleSelection([], []));
        self::assertFalse($unselected->hasEnabledModule('platform.cli'));
        self::assertFalse($unselected->hasEnabledModule('platform.errors'));
        self::assertFalse($unselected->hasEnabledModule('platform.redaction'));
        $selected = new ModuleSelection([ModuleId::fromString('platform.cli')], []);
        try {
            $resolver->resolve('console', self::manifest(false), $selected);
            self::fail('Missing platform.errors must fail module composition.');
        } catch (ModuleRequiredMissingException) {
            // Canonical dependency closure rejects missing modules.
        }
        try {
            $resolver->resolve(
                'console',
                self::manifest(true),
                new ModuleSelection(
                    [ModuleId::fromString('platform.cli')],
                    [ModuleId::fromString('platform.errors')],
                ),
            );
            self::fail('Explicit platform.errors exclusion must fail module composition.');
        } catch (ModuleConflictException) {
            // Canonical exclusion precedence rejects forbidden module resurrection.
        }
    }

    /** @return list<array{\Throwable, string, string}> */
    private static function publicFailures(): array
    {
        return [
            [
                CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_UNKNOWN),
                'CORETSIA_CLI_INPUT_INVALID',
                'cli-option-unknown',
            ],
            [
                CliConfigInvalidException::withReason(
                    CliConfigInvalidException::REASON_OUTPUT_POLICY_INVALID,
                ),
                'CORETSIA_CLI_CONFIG_INVALID',
                'cli-output-policy-invalid',
            ],
            [
                InvalidCommandTagMetaException::withReason(
                    InvalidCommandTagMetaException::REASON_NAME_COLLISION,
                ),
                'CORETSIA_CLI_INVALID_COMMAND_META',
                'command-name-collision',
            ],
            [
                CliCommandFailedException::failed(),
                'CORETSIA_CLI_COMMAND_FAILED',
                'cli-command-failed',
            ],
            [
                WorkflowDefinitionInvalidException::withReason(
                    WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                ),
                'CORETSIA_CLI_WORKFLOW_DEFINITION_INVALID',
                'workflow-command-unknown',
            ],
            [
                WorkflowNotFoundException::notFound(),
                'CORETSIA_CLI_WORKFLOW_NOT_FOUND',
                'workflow-not-found',
            ],
            [
                new KernelOpsFailedException(KernelOpsFailedException::REASON_OPERATION_FAILED),
                'CORETSIA_KERNEL_OPS_FAILED',
                'operation-failed',
            ],
        ];
    }

    /** @return array<string,mixed> */
    private static function config(): array
    {
        return [
            'foundation' => require self::repositoryRoot() . '/packages/core/foundation/config/foundation.php',
            'errors' => require self::repositoryRoot() . '/packages/platform/errors/config/errors.php',
            'cli' => require self::packageRoot() . '/config/cli.php',
        ];
    }

    /** @return list<object> */
    private static function providers(): array
    {
        return [
            new FoundationServiceProvider(),
            new RedactionServiceProvider(),
            new ErrorsServiceProvider(),
            new CliServiceProvider(),
        ];
    }

    private static function sourceBuilder(): ContainerBuilder
    {
        $config = self::config();
        $builder = new ContainerBuilder($config);
        $builder->instance(ConfigRepositoryInterface::class, new ArrayConfigRepository($config));
        $builder->instance(KernelRuntimeInterface::class, new BoundaryScenarioRuntime());
        $builder->registerProviders(self::providers());
        return $builder;
    }

    private static function sourceContainer(): Container
    {
        return self::sourceBuilder()->build();
    }

    private static function cliFixture(
        Container $container,
        ?BoundaryScenarioRuntime $runtime = null,
    ): CliApplicationFixtureHarness {
        return new CliApplicationFixtureHarness(
            $container->get(CommandCatalog::class),
            $runtime ?? new BoundaryScenarioRuntime(),
            $container,
            $container->get(SensitiveDataRedactorInterface::class),
            errors: $container->get(ErrorHandlerInterface::class),
        );
    }

    private static function modulePlan(): ModulePlan
    {
        return new ModulePlanResolver(new ModuleGraphResolver(new TopologicalSorter()))->resolve(
            'console',
            self::manifest(true),
            new ModuleSelection([ModuleId::fromString('platform.cli')], []),
        );
    }

    private static function manifest(bool $withErrors): ModuleManifest
    {
        $descriptors = [
            self::module('core.foundation'),
            self::module('core.kernel', ['core.foundation']),
            self::module('platform.redaction', ['core.foundation']),
            self::module('platform.cli', ['core.kernel', 'platform.errors', 'platform.redaction']),
        ];
        if ($withErrors) {
            $descriptors[] = self::module('platform.errors', ['core.foundation', 'platform.redaction']);
        }
        return new ModuleManifest($descriptors);
    }

    /** @param list<string> $requires */
    private static function module(string $id, array $requires = []): ModuleDescriptor
    {
        return new ModuleDescriptor(
            id: ModuleId::fromString($id),
            composerName: 'coretsia/' . \str_replace('.', '-', $id),
            packageKind: 'runtime',
            metadata: ['requires' => $requires, 'conflicts' => []],
        );
    }
}

/** Test-only earlier mapper contribution, using the canonical tag priority policy. */
final class CliCanonicalOwnerPriorityMapper implements ExceptionMapperInterface
{
    public function map(\Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        return $throwable instanceof CliInputInvalidException
            ? new ErrorDescriptor('OWNER_PREEMPTED', 'Earlier owner mapper.')
            : null;
    }
}

/** Test-only owner descriptor with classified private values in permitted extension fields. */
final class CliCanonicalSensitiveMapper implements ExceptionMapperInterface
{
    public function map(\Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        return $throwable instanceof \RuntimeException && !$throwable instanceof CliInputInvalidException
            ? new ErrorDescriptor(
                'OWNER_PUBLIC',
                'Publicly approved reason.',
                extensions: ['contact' => 'owner@example.com'],
            )
            : null;
    }
}
