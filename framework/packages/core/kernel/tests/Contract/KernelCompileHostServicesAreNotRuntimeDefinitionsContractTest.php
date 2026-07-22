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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionKind;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use Coretsia\Kernel\Artifacts\Paths\ArtifactPathResolver;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Artifacts\Verifier\CacheVerifier;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\DotenvLoader;
use Coretsia\Kernel\Boot\EnvRepositoryBuilder;
use Coretsia\Kernel\Config\ConfigKernel;
use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Container\CompiledContainerFactory;
use Coretsia\Kernel\Container\ContainerCompiler;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Definition\ServiceDefinition;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Container\RuntimeContainerGraphCompiler;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use Coretsia\Kernel\Runtime\Entrypoint\RuntimeEntrypointGuard;
use Coretsia\Kernel\Runtime\Hook\HookInvoker;
use Coretsia\Kernel\Runtime\KernelRuntime;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final class KernelCompileHostServicesAreNotRuntimeDefinitionsContractTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const array COMPILE_HOST_SERVICE_IDS = [
        BootstrapOverridesLoader::class,
        BootstrapConfigResolver::class,
        DotenvLoader::class,
        EnvRepositoryBuilder::class,
        ModePresetSchemaValidator::class,
        TopologicalSorter::class,
        ComposerManifestReader::class,
        ManifestReaderInterface::class,
        ModePresetLoaderFactory::class,
        ModuleGraphResolver::class,
        ModulePlanResolver::class,
        ContainerProviderPlanResolver::class,
        ConfigNamespaceGuard::class,
        DirectiveProcessor::class,
        ConfigMerger::class,
        ConfigRulesLoader::class,
        ConfigValidator::class,
        ConfigExplainer::class,
        PackageDefaultsConfigLoader::class,
        SkeletonConfigLoader::class,
        EnvironmentOverlayLoader::class,
        ConfigKernel::class,
        PayloadNormalizer::class,
        StablePhpArrayDumper::class,
        ArtifactEnvelopeFactory::class,
        ArtifactPathResolver::class,
        DeterministicFileLister::class,
        ConfigFingerprintInputBuilder::class,
        FingerprintExplainer::class,
        FingerprintCalculator::class,
        ArtifactWriter::class,
        ModuleManifestBuilder::class,
        CompiledConfigBuilder::class,
        CompiledContainerBuilder::class,
        PhpArtifactReader::class,
        ArtifactSchemaValidator::class,
        CompiledContainerFactory::class,
        ContainerCompiler::class,
        ContainerGraphCompletenessValidator::class,
        RuntimeContainerGraphCompiler::class,
        ArtifactCompiler::class,
        CacheVerifier::class,
    ];

    public function testKernelCompileHostServicesAreExcludedFromRuntimeDefinitions(): void
    {
        $config = self::validConfig();
        $runtimeDefinitions = self::completeRuntimeDefinitionSet($config);
        $runtimeBindingIds = self::bindingIds(
            $runtimeDefinitions->toDescriptorStream(),
        );

        $sourceBuilder = new ContainerBuilder(config: $config);
        $sourceBuilder->register(
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
        );

        $sourceCompileHostIds = \array_values(
            \array_diff(
                $sourceBuilder->serviceIds(),
                $runtimeBindingIds,
                [
                    TagRegistry::class,
                    RuntimePathContext::class,
                ],
            ),
        );

        self::assertSame(
            self::sorted(self::COMPILE_HOST_SERVICE_IDS),
            self::sorted($sourceCompileHostIds),
        );

        foreach (self::COMPILE_HOST_SERVICE_IDS as $serviceId) {
            self::assertNotContains(
                $serviceId,
                $runtimeBindingIds,
            );
            self::assertNotContains(
                $serviceId,
                $runtimeDefinitions->requiredServiceIds(),
            );
        }
    }

    public function testCompletenessValidatorRejectsEveryCompileHostServiceBinding(): void
    {
        $validator = new ContainerGraphCompletenessValidator();

        foreach (self::COMPILE_HOST_SERVICE_IDS as $serviceId) {
            try {
                $validator->validate(
                    graph: DefinitionGraph::empty()->withService(
                        ServiceDefinition::class(
                            id: $serviceId,
                            class: \stdClass::class,
                        ),
                    ),
                    definitions: ContainerDefinitionSet::empty(),
                );

                self::fail(
                    'Expected compile-host service binding to be rejected.',
                );
            } catch (ContainerDefinitionInvalidException $exception) {
                self::assertSame(
                    ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                    $exception->reason(),
                );
            }
        }
    }

    public function testRuntimePathContextIsRegisteredOnlyAsSourceHostRuntimeSeed(): void
    {
        $config = self::validConfig();
        $runtimeDefinitions = self::completeRuntimeDefinitionSet($config);
        $runtimeBindingIds = self::bindingIds(
            $runtimeDefinitions->toDescriptorStream(),
        );

        $sourceBuilder = new ContainerBuilder(config: $config);
        $sourceBuilder->register(
            new FoundationServiceProvider(),
            new KernelServiceProvider(),
        );

        self::assertContains(
            RuntimePathContext::class,
            $sourceBuilder->serviceIds(),
            'RuntimePathContext must be registered for source-host runtime wiring.',
        );

        self::assertNotContains(
            RuntimePathContext::class,
            $runtimeBindingIds,
            'RuntimePathContext must not enter Kernel runtime definitions.',
        );

        self::assertNotContains(
            RuntimePathContext::class,
            $runtimeDefinitions->requiredServiceIds(),
            'The Kernel runtime contribution must not require RuntimePathContext.',
        );

        self::assertNotContains(
            RuntimePathContext::class,
            self::COMPILE_HOST_SERVICE_IDS,
            'RuntimePathContext is a source-host runtime seed, not a compile-host service.',
        );
    }

    public function testKernelRuntimeDefinitionContributionContainsOnlyRuntimeBindings(): void
    {
        $definitions = new ContainerDefinitionBuilder();

        new KernelServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(
                self::validConfig(),
            ),
        );

        self::assertSame(
            self::sorted([
                RuntimeEntrypointGuard::class,
                HookInvoker::class,
                KernelRuntime::class,
                KernelRuntimeInterface::class,
            ]),
            self::bindingIds(
                $definitions->build()->toDescriptorStream(),
            ),
        );
    }

    public function testKernelRuntimeDefinitionContributionDeclaresContainerResolvedDependencies(): void
    {
        $definitions = new ContainerDefinitionBuilder();

        new KernelServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(
                self::validConfig(),
            ),
        );

        self::assertSame(
            self::sorted([
                ContainerInterface::class,
                TagRegistry::class,
                ContextAccessorInterface::class,
                ResetOrchestrator::class,
                Stopwatch::class,
                IdGeneratorInterface::class,
                CorrelationIdProviderInterface::class,
                CorrelationIdGenerator::class,
                HookInvoker::class,
                LoggerInterface::class,
                TracerPortInterface::class,
                MeterPortInterface::class,
            ]),
            $definitions->build()->requiredServiceIds(),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function completeRuntimeDefinitionSet(
        array $config,
    ): ContainerDefinitionSet {
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);

        new FoundationServiceProvider()->define(
            $definitions,
            $context,
        );
        new KernelServiceProvider()->define(
            $definitions,
            $context,
        );

        return $definitions->build();
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return list<string>
     */
    private static function bindingIds(array $operations): array
    {
        $ids = [];

        foreach ($operations as $operation) {
            $kind = $operation['kind'] ?? null;

            if (!\is_string($kind)) {
                throw new \LogicException('kernel-runtime-definition-operation-kind-invalid');
            }

            if (self::isServiceKind($kind)) {
                $id = $operation['id'] ?? null;

                if (!\is_string($id)) {
                    throw new \LogicException('kernel-runtime-definition-service-id-invalid');
                }

                $ids[] = $id;

                continue;
            }

            if ($kind !== ContainerDefinitionKind::ALIAS->value) {
                continue;
            }

            $alias = $operation['alias'] ?? null;

            if (!\is_string($alias)) {
                throw new \LogicException('kernel-runtime-definition-alias-id-invalid');
            }

            $ids[] = $alias;
        }

        return self::sorted($ids);
    }

    private static function isServiceKind(string $kind): bool
    {
        return $kind
            === ContainerDefinitionKind::SERVICE_CLASS->value
            || $kind
            === ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD->value
            || $kind
            === ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD->value;
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private static function sorted(array $values): array
    {
        \usort(
            $values,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => 'ulid',
                ],
                'reset' => [
                    'tag' => ReservedTags::KERNEL_RESET,
                    'priority' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'kernel' => [
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }
}
