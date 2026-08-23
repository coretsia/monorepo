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

namespace Coretsia\Kernel\Container;

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\ArtifactWriter;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Artifacts\Builders\CompiledContainerBuilder;
use Coretsia\Kernel\Artifacts\Builders\ModuleManifestBuilder;
use Coretsia\Kernel\Artifacts\Compiler\ArtifactCompiler;
use Coretsia\Kernel\Artifacts\Fingerprint\ConfigFingerprintInputBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\ContainerGraphFingerprintBucketBuilder;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintCalculator;
use Coretsia\Kernel\Artifacts\Fingerprint\FingerprintExplainer;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLocator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationLock;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestBuilder;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPublisher;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationValidator;
use Coretsia\Kernel\Artifacts\Operation\KernelArtifactOperation;
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
use Coretsia\Kernel\Config\Source\ComposerPackageInstallPathResolver;
use Coretsia\Kernel\Config\Source\ConfigSourceLocationBuilder;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ComposerManifestReader;
use Coretsia\Kernel\Module\ModePresetLoaderFactory;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModulePlanResolver;
use Coretsia\Kernel\Module\TopologicalSorter;

/**
 * Validates that one compiled runtime container graph is production-complete.
 *
 * The validator accepts only references satisfied by graph bindings or by the
 * canonical runtime seed allowlist. Runtime seed ids and compile-host services
 * must never be defined by the compiled runtime graph.
 *
 * @internal Kernel compiled-container completeness policy.
 */
final class ContainerGraphCompletenessValidator
{
    private const string FACTORY_SERVICE_METHOD = 'service-method';

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
        ComposerPackageInstallPathResolver::class,
        ConfigSourceLocationBuilder::class,
        PayloadNormalizer::class,
        StablePhpArrayDumper::class,
        ArtifactEnvelopeFactory::class,
        ArtifactGenerationPathResolver::class,
        ArtifactGenerationManifestBuilder::class,
        ArtifactGenerationManifestValidator::class,
        ArtifactGenerationLock::class,
        ArtifactGenerationValidator::class,
        ArtifactGenerationPublisher::class,
        ArtifactGenerationLocator::class,
        ArtifactPathResolver::class,
        DeterministicFileLister::class,
        ContainerGraphFingerprintBucketBuilder::class,
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
        KernelArtifactOperation::class,
    ];

    public function validate(
        DefinitionGraph $graph,
        ContainerDefinitionSet $definitions,
    ): void {
        $payload = $graph->toArray();
        $services = $payload['services'];
        $aliases = $payload['aliases'];
        $parameters = $payload['parameters'];
        $tags = $payload['tags'];
        $parameterNames = self::parameterNameSet($parameters);
        $runtimeSeedIds = self::serviceIdSet(
            RuntimeContainerSeedIds::all(),
        );
        $compileHostServiceIds = self::serviceIdSet(
            self::COMPILE_HOST_SERVICE_IDS,
        );

        self::assertForbiddenBindingsAbsent(
            services: $services,
            aliases: $aliases,
            forbiddenServiceIds: $runtimeSeedIds,
        );

        self::assertForbiddenBindingsAbsent(
            services: $services,
            aliases: $aliases,
            forbiddenServiceIds: $compileHostServiceIds,
        );

        self::assertServiceAndAliasBindingIdsDistinct(
            services: $services,
            aliases: $aliases,
        );

        foreach ($aliases as $alias => $_target) {
            self::assertGraphAliasResolves(
                alias: $alias,
                services: $services,
                aliases: $aliases,
                compileHostServiceIds: $compileHostServiceIds,
                path: [],
            );
        }

        foreach ($services as $service) {
            self::assertServiceComplete(
                service: $service,
                services: $services,
                aliases: $aliases,
                parameterNames: $parameterNames,
                runtimeSeedIds: $runtimeSeedIds,
                compileHostServiceIds: $compileHostServiceIds,
            );
        }

        foreach ($tags as $taggedServices) {
            foreach ($taggedServices as $taggedService) {
                self::assertGraphBindingResolvable(
                    serviceId: $taggedService['id'],
                    services: $services,
                    aliases: $aliases,
                    compileHostServiceIds: $compileHostServiceIds,
                );
            }
        }

        foreach ($definitions->requiredServiceIds() as $serviceId) {
            self::assertExternalReferenceResolvable(
                serviceId: $serviceId,
                services: $services,
                aliases: $aliases,
                runtimeSeedIds: $runtimeSeedIds,
                compileHostServiceIds: $compileHostServiceIds,
                reason: ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
            );
        }
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $forbiddenServiceIds
     */
    private static function assertForbiddenBindingsAbsent(
        array $services,
        array $aliases,
        array $forbiddenServiceIds,
    ): void {
        foreach ($forbiddenServiceIds as $serviceId => $_true) {
            if (
                isset($services[$serviceId])
                || isset($aliases[$serviceId])
            ) {
                throw self::invalidDefinition();
            }
        }
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     */
    private static function assertServiceAndAliasBindingIdsDistinct(
        array $services,
        array $aliases,
    ): void {
        foreach ($aliases as $alias => $_target) {
            if (\array_key_exists($alias, $services)) {
                throw self::invalidDefinition();
            }
        }
    }

    /**
     * @param array<string, mixed> $service
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $parameterNames
     * @param array<string, true> $runtimeSeedIds
     * @param array<string, true> $compileHostServiceIds
     */
    private static function assertServiceComplete(
        array $service,
        array $services,
        array $aliases,
        array $parameterNames,
        array $runtimeSeedIds,
        array $compileHostServiceIds,
    ): void {
        $construction = $service['construction'] ?? null;
        $arguments = $service['arguments'] ?? null;

        if (
            !\is_array($construction)
            || !\is_array($arguments)
        ) {
            throw self::invalidDefinition();
        }

        $class = $construction['class'] ?? null;

        if (
            \is_string($class)
            && isset($compileHostServiceIds[$class])
        ) {
            throw self::invalidDefinition();
        }

        $factory = $construction['factory'] ?? null;

        if (\is_array($factory)) {
            $factoryKind = $factory['kind'] ?? null;

            if ($factoryKind === self::FACTORY_SERVICE_METHOD) {
                $factoryServiceId = $factory['service'] ?? null;

                if (!\is_string($factoryServiceId)) {
                    throw self::invalidReference();
                }

                self::assertGraphBindingResolvable(
                    serviceId: $factoryServiceId,
                    services: $services,
                    aliases: $aliases,
                    compileHostServiceIds: $compileHostServiceIds,
                );
            }
        }

        self::assertValueReferencesResolvable(
            value: $arguments,
            services: $services,
            aliases: $aliases,
            parameterNames: $parameterNames,
            runtimeSeedIds: $runtimeSeedIds,
            compileHostServiceIds: $compileHostServiceIds,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $compileHostServiceIds
     * @param array<string, true> $path
     */
    private static function assertGraphAliasResolves(
        string $alias,
        array $services,
        array $aliases,
        array $compileHostServiceIds,
        array $path,
    ): void {
        if (isset($path[$alias])) {
            throw self::invalidReference();
        }

        $path[$alias] = true;
        $target = $aliases[$alias] ?? null;

        if (!\is_string($target)) {
            throw self::invalidReference();
        }

        if (isset($compileHostServiceIds[$target])) {
            throw self::invalidReference();
        }

        if (isset($services[$target])) {
            return;
        }

        if (isset($aliases[$target])) {
            self::assertGraphAliasResolves(
                alias: $target,
                services: $services,
                aliases: $aliases,
                compileHostServiceIds: $compileHostServiceIds,
                path: $path,
            );

            return;
        }

        throw self::invalidReference();
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $compileHostServiceIds
     */
    private static function assertGraphBindingResolvable(
        string $serviceId,
        array $services,
        array $aliases,
        array $compileHostServiceIds,
    ): void {
        if (isset($compileHostServiceIds[$serviceId])) {
            throw self::invalidReference();
        }

        if (isset($services[$serviceId])) {
            return;
        }

        if (isset($aliases[$serviceId])) {
            self::assertGraphAliasResolves(
                alias: $serviceId,
                services: $services,
                aliases: $aliases,
                compileHostServiceIds: $compileHostServiceIds,
                path: [],
            );

            return;
        }

        throw self::invalidReference();
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $runtimeSeedIds
     * @param array<string, true> $compileHostServiceIds
     */
    private static function assertExternalReferenceResolvable(
        string $serviceId,
        array $services,
        array $aliases,
        array $runtimeSeedIds,
        array $compileHostServiceIds,
        string $reason,
    ): void {
        if (isset($compileHostServiceIds[$serviceId])) {
            throw ContainerDefinitionInvalidException::withReason(
                reason: $reason,
            );
        }

        if (
            isset($services[$serviceId])
            || isset($runtimeSeedIds[$serviceId])
        ) {
            return;
        }

        if (isset($aliases[$serviceId])) {
            try {
                self::assertGraphAliasResolves(
                    alias: $serviceId,
                    services: $services,
                    aliases: $aliases,
                    compileHostServiceIds: $compileHostServiceIds,
                    path: [],
                );
            } catch (ContainerDefinitionInvalidException) {
                throw ContainerDefinitionInvalidException::withReason(
                    reason: $reason,
                );
            }

            return;
        }

        throw ContainerDefinitionInvalidException::withReason(
            reason: $reason,
        );
    }

    /**
     * @param array<string, array<string, mixed>> $services
     * @param array<string, string> $aliases
     * @param array<string, true> $parameterNames
     * @param array<string, true> $runtimeSeedIds
     * @param array<string, true> $compileHostServiceIds
     */
    private static function assertValueReferencesResolvable(
        mixed $value,
        array $services,
        array $aliases,
        array $parameterNames,
        array $runtimeSeedIds,
        array $compileHostServiceIds,
    ): void {
        if (!\is_array($value)) {
            return;
        }

        if (self::isServiceReference($value)) {
            self::assertExternalReferenceResolvable(
                serviceId: $value['id'],
                services: $services,
                aliases: $aliases,
                runtimeSeedIds: $runtimeSeedIds,
                compileHostServiceIds: $compileHostServiceIds,
                reason: ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
            );

            return;
        }

        if (self::isParameterReference($value)) {
            if (!isset($parameterNames[$value['name']])) {
                throw self::invalidReference();
            }

            return;
        }

        foreach ($value as $item) {
            self::assertValueReferencesResolvable(
                value: $item,
                services: $services,
                aliases: $aliases,
                parameterNames: $parameterNames,
                runtimeSeedIds: $runtimeSeedIds,
                compileHostServiceIds: $compileHostServiceIds,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isServiceReference(array $value): bool
    {
        return \array_keys($value) === [
                'id',
                'type',
            ]
            && \is_string($value['id'])
            && $value['type'] === ContainerValueReference::TYPE_SERVICE;
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private static function isParameterReference(array $value): bool
    {
        return \array_keys($value) === [
                'name',
                'type',
            ]
            && \is_string($value['name'])
            && $value['type'] === ContainerValueReference::TYPE_PARAMETER;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, true>
     */
    private static function parameterNameSet(array $parameters): array
    {
        $set = [];

        foreach ($parameters as $parameterName => $_value) {
            $set[$parameterName] = true;
        }

        return $set;
    }

    /**
     * @param list<string> $serviceIds
     *
     * @return array<string, true>
     */
    private static function serviceIdSet(array $serviceIds): array
    {
        $set = [];

        foreach ($serviceIds as $serviceId) {
            $set[$serviceId] = true;
        }

        return $set;
    }

    private static function invalidDefinition(): ContainerDefinitionInvalidException
    {
        return ContainerDefinitionInvalidException::withReason(
            reason: ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
        );
    }

    private static function invalidReference(): ContainerDefinitionInvalidException
    {
        return ContainerDefinitionInvalidException::withReason(
            reason: ContainerDefinitionInvalidException::REASON_REFERENCE_INVALID,
        );
    }
}
