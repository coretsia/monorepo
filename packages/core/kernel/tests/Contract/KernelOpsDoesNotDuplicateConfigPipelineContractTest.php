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

use PHPUnit\Framework\TestCase;

final class KernelOpsDoesNotDuplicateConfigPipelineContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array FORBIDDEN_LOCAL_CONFIG_ROLES = [
        'ConfigMerger',
        'ConfigRulesLoader',
        'DirectiveProcessor',
        'ConfigValidator',
        'ConfigExplainer',
    ];

    /**
     * @var list<string>
     */
    private const array EXPECTED_OPS_TYPES = [
        'KernelOpsExecutionServices',
        'KernelOpsFacade',
        'KernelOpsHostBooter',
        'KernelOpsHostInput',
        'KernelOpsHostSeedConfigLoader',
        'KernelOpsSourceDefinitionProviderAdapter',
    ];

    public function testKernelOpsDoesNotImplementConfigPipelineContractsLocally(): void
    {
        foreach (self::opsSources() as $relativePath => $source) {
            $code = self::phpCodeWithoutCommentsAndStrings($source);

            foreach (
                [
                    'ConfigLoaderInterface',
                    'MergeStrategyInterface',
                    'ConfigValidatorInterface',
                    'ConfigRepositoryInterface',
                ] as $interface
            ) {
                self::assertDoesNotMatchRegularExpression(
                    '/\bimplements\s+[^\{;]*\b' . \preg_quote($interface, '/') . '\b/s',
                    $code,
                    $relativePath . ' must not implement ' . $interface . ' inside Kernel Ops.',
                );
            }
        }
    }

    public function testKernelOpsDoesNotDeclareParallelConfigPipelineOrSourcePlanClasses(): void
    {
        $declaredTypes = [];

        foreach (self::opsSources() as $relativePath => $source) {
            $code = self::phpCodeWithoutCommentsAndStrings($source);

            foreach (self::FORBIDDEN_LOCAL_CONFIG_ROLES as $role) {
                self::assertDoesNotMatchRegularExpression(
                    '/\bclass\s+[A-Za-z_][A-Za-z0-9_]*' . \preg_quote($role, '/') . '[A-Za-z0-9_]*\b/',
                    $code,
                    $relativePath . ' must not declare a Kernel Ops-local ' . $role . '.',
                );
            }

            self::assertDoesNotMatchRegularExpression(
                '/\bclass\s+[A-Za-z_][A-Za-z0-9_]*' .
                '(?:ConfigSourcePlan|ConfigPlan|ConfigPipeline|ConfigSourceModel)' .
                '[A-Za-z0-9_]*\b/',
                $code,
                $relativePath . ' must not introduce a parallel config source-plan model.',
            );

            self::assertStringNotContainsString('ConfigSourcePlan', $code, $relativePath);

            \preg_match_all(
                '/\b(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)\b/',
                $code,
                $matches,
            );

            foreach ($matches[1] ?? [] as $declaredType) {
                $declaredTypes[] = $declaredType;
            }
        }

        \sort($declaredTypes, \SORT_STRING);

        self::assertSame(
            self::EXPECTED_OPS_TYPES,
            $declaredTypes,
            'src/Ops must not introduce a parallel config model or an unreviewed Kernel Ops-local type.',
        );
    }

    public function testOnlySeedConfigLoaderMayDirectlyRequireOrIncludeConfigFiles(): void
    {
        foreach (self::opsSources() as $relativePath => $source) {
            $includeCount = self::includeTokenCount($source);

            if ($relativePath === 'src/Ops/KernelOpsHostSeedConfigLoader.php') {
                self::assertSame(1, $includeCount);

                continue;
            }

            self::assertSame(
                0,
                $includeCount,
                $relativePath . ' must not directly require/include config files.',
            );
        }
    }

    public function testSeedConfigLoaderLoadsOnlyFoundationAndKernelDeclaredSeedConfigFiles(): void
    {
        $source = self::opsSource('KernelOpsHostSeedConfigLoader.php');
        $code = self::phpWithoutComments($source);

        self::assertStringContainsString("private const string FOUNDATION_CONFIG = 'config/foundation.php';", $code);
        self::assertStringContainsString("private const string KERNEL_CONFIG = 'config/kernel.php';", $code);
        self::assertStringContainsString('ComposerPackageInstallPathResolver', $code);
        self::assertStringContainsString('FoundationModule::COMPOSER_PACKAGE', $code);
        self::assertStringContainsString('KernelModule::COMPOSER_PACKAGE', $code);
        self::assertStringContainsString('FoundationModule::CONFIG_ROOT', $code);
        self::assertStringContainsString('KernelModule::CONFIG_ROOT', $code);

        self::assertMatchesRegularExpression(
            '/\$foundationRoot\s*=\s*\$this->installPathResolver->resolve\(\s*' .
            'FoundationModule::COMPOSER_PACKAGE\s*\)\s*;/s',
            $code,
        );
        self::assertMatchesRegularExpression(
            '/\$kernelRoot\s*=\s*\$this->installPathResolver->resolve\(\s*' .
            'KernelModule::COMPOSER_PACKAGE\s*\)\s*;/s',
            $code,
        );
        self::assertMatchesRegularExpression(
            '/FoundationModule::CONFIG_ROOT\s*=>\s*\$this->loadRequiredMap\(\s*' .
            'packageRoot:\s*\$foundationRoot\s*,\s*' .
            'relativePath:\s*self::FOUNDATION_CONFIG\s*,?\s*\)/s',
            $code,
        );
        self::assertMatchesRegularExpression(
            '/KernelModule::CONFIG_ROOT\s*=>\s*\$this->loadRequiredMap\(\s*' .
            'packageRoot:\s*\$kernelRoot\s*,\s*' .
            'relativePath:\s*self::KERNEL_CONFIG\s*,?\s*\)/s',
            $code,
        );

        self::assertSame(1, self::includeTokenCount($source));

        self::assertMatchesRegularExpression('/\brequire\s+\$configPath\s*;/', $code);

        \preg_match_all(
            '/[\"\']([^\"\']+\.php)[\"\']/',
            $code,
            $phpPathMatches,
        );
        $phpPaths = \array_values(\array_unique($phpPathMatches[1] ?? []));
        \sort($phpPaths, \SORT_STRING);

        self::assertSame(
            [
                'config/foundation.php',
                'config/kernel.php',
            ],
            $phpPaths,
            'KernelOpsHostSeedConfigLoader may name only the two declared package seed config files.',
        );

        foreach (
            [
                'config/app.php',
                'config/environments/',
                'config/modes/',
                'resources/modes/',
                'config.php',
                'module-manifest.php',
                'container.php',
                'generation-manifest.php',
            ] as $forbiddenPath
        ) {
            self::assertStringNotContainsString($forbiddenPath, $code);
        }
    }

    public function testKernelOpsFacadeUsesExistingOrchestrationServicesInsteadOfLowerLevelPipelineParts(): void
    {
        $source = self::phpCodeWithoutCommentsAndStrings(
            self::opsSource('KernelOpsFacade.php'),
        );

        foreach (
            [
                'BootstrapConfigResolver',
                'EnvRepositoryBuilder',
                'ModuleResolutionOrchestrator',
                'ConfigKernel',
                'RuntimeContainerGraphCompiler',
                'ConfigFingerprintInputBuilder',
                'FingerprintCalculator',
                'ConfigSourceLocationBuilder',
                'KernelArtifactOperation',
            ] as $requiredOrchestrationService
        ) {
            self::assertStringContainsString($requiredOrchestrationService, $source);
        }

        self::assertSame(
            [
                'bootstrapConfigResolver::resolve',
                'configFingerprintInputBuilder::build',
                'configKernel::compile',
                'configSourceLocationBuilder::build',
                'contextAccessor::get',
                'contextAccessor::has',
                'correlationIdProvider::correlationId',
                'envRepositoryBuilder::build',
                'fingerprintCalculator::calculate',
                'hostInput::applicationRoot',
                'kernelArtifactOperation::compile',
                'kernelArtifactOperation::verify',
                'logger::info',
                'meter::increment',
                'meter::observe',
                'moduleResolutionOrchestrator::resolve',
                'runtimeContainerGraphCompiler::compile',
                'stopwatch::start',
                'stopwatch::stop',
                'tracer::startSpan',
            ],
            self::dependencyMethodCalls($source),
            'KernelOpsFacade may call only the reviewed orchestration, context, and observability dependencies.',
        );

        foreach (
            [
                'ConfigMerger',
                'ConfigRulesLoader',
                'DirectiveProcessor',
                'ConfigValidator',
                'ConfigExplainer',
                'PackageDefaultsConfigLoader',
                'ApplicationConfigLoader',
                'EnvironmentOverlayLoader',
                'ModulePlanResolver',
                'ManifestReaderInterface',
                'ComposerManifestReader',
                'ContainerProviderPlanResolver',
                'ArtifactCompiler',
                'CacheVerifier',
                'ConfigLoaderInterface',
                'MergeStrategyInterface',
                'ConfigValidatorInterface',
            ] as $forbiddenLowerLevelDependency
        ) {
            self::assertStringNotContainsString(
                $forbiddenLowerLevelDependency,
                $source,
                'KernelOpsFacade must orchestrate through existing high-level owners only.',
            );
        }

        self::assertStringNotContainsString('new ConfigSourceSet(', $source);
        self::assertStringNotContainsString('new ArrayConfigRepository(', $source);
    }

    public function testKernelOpsHostBooterUsesOnlyTheAllowedAdditionalCompositionBoundary(): void
    {
        $source = self::phpCodeWithoutCommentsAndStrings(
            self::opsSource('KernelOpsHostBooter.php'),
        );

        foreach (
            [
                'ContainerBuilder',
                'ContainerDefinitionProviderInterface',
                'ServiceProviderInterface',
                'FoundationModule',
                'KernelModule',
                'ContainerProviderPlan',
                'ContainerProviderPlanResolver',
                'KernelOpsHostSeedConfigLoader',
                'KernelOpsSourceDefinitionProviderAdapter',
            ] as $allowedCompositionDependency
        ) {
            self::assertStringContainsString($allowedCompositionDependency, $source);
        }

        self::assertSame(
            [
                'BootstrapConfigResolver',
                'ConfigKernel',
                'ConfigSourceLocationBuilder',
                'ContainerProviderPlanResolver',
                'EnvRepositoryBuilder',
                'ModuleResolutionOrchestrator',
            ],
            self::hostBooterServiceIds($source),
            'KernelOpsHostBooter may resolve only the reviewed source-host preparation services.',
        );

        self::assertSame(
            [
                '$id',
                'KernelOpsFacade::class',
                'KernelOpsInterface::class',
                'LoggerInterface::class',
                'MeterPortInterface::class',
                'Stopwatch::class',
                'TracerPortInterface::class',
            ],
            self::containerGetArguments($source),
            'KernelOpsHostBooter may not add an unreviewed direct container resolution path.',
        );

        self::assertSame(
            [
                'ArrayConfigRepository',
                'BootstrapInput',
                'ContainerBuilder',
                'FoundationModule',
                'KernelModule',
                'KernelOpsHostSeedConfigLoader',
                'KernelOpsSourceDefinitionProviderAdapter',
            ],
            self::directCompositionClasses($source),
            'KernelOpsHostBooter may directly construct only reviewed source-host composition values.',
        );

        foreach (
            [
                'ConfigMerger',
                'ConfigRulesLoader',
                'DirectiveProcessor',
                'ConfigValidator',
                'ConfigExplainer',
                'PackageDefaultsConfigLoader',
                'ApplicationConfigLoader',
                'EnvironmentOverlayLoader',
                'ConfigLoaderInterface',
                'MergeStrategyInterface',
                'ConfigValidatorInterface',
            ] as $forbiddenDuplicatePipelineDependency
        ) {
            self::assertStringNotContainsString(
                $forbiddenDuplicatePipelineDependency,
                $source,
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function dependencyMethodCalls(string $source): array
    {
        \preg_match_all(
            '/\$this->([A-Za-z_][A-Za-z0-9_]*)->([A-Za-z_][A-Za-z0-9_]*)\s*\(/',
            $source,
            $matches,
            \PREG_SET_ORDER,
        );

        $calls = [];

        foreach ($matches as $match) {
            $calls[] = $match[1] . '::' . $match[2];
        }

        $calls = \array_values(\array_unique($calls));
        \sort($calls, \SORT_STRING);

        return $calls;
    }

    /**
     * @return list<string>
     */
    private static function hostBooterServiceIds(string $source): array
    {
        \preg_match_all(
            '/self::service\s*\(\s*[^,]+,\s*([A-Za-z_][A-Za-z0-9_]*)::class\s*,?\s*\)/s',
            $source,
            $matches,
        );

        $ids = \array_values(\array_unique($matches[1] ?? []));
        \sort($ids, \SORT_STRING);

        return $ids;
    }

    /**
     * @return list<string>
     */
    private static function containerGetArguments(string $source): array
    {
        \preg_match_all(
            '/->get\s*\(\s*([^\)\r\n]+?)\s*\)/',
            $source,
            $matches,
        );

        $arguments = [];

        foreach ($matches[1] ?? [] as $argument) {
            $arguments[] = \preg_replace('/\s+/', '', $argument) ?? $argument;
        }

        $arguments = \array_values(\array_unique($arguments));
        \sort($arguments, \SORT_STRING);

        return $arguments;
    }

    /**
     * @return list<string>
     */
    private static function directCompositionClasses(string $source): array
    {
        \preg_match_all(
            '/\bnew\s+([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)/',
            $source,
            $matches,
        );

        $classes = [];

        foreach ($matches[1] ?? [] as $class) {
            if (\str_ends_with($class, 'Exception')) {
                continue;
            }

            $classes[] = $class;
        }

        $classes = \array_values(\array_unique($classes));
        \sort($classes, \SORT_STRING);

        return $classes;
    }

    /**
     * @return array<string, string>
     */
    private static function opsSources(): array
    {
        $root = self::kernelRoot() . '/src/Ops';

        self::assertDirectoryExists($root);

        $sources = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $source = \file_get_contents($fileInfo->getPathname());

            self::assertIsString($source);

            $relativePath = 'src/Ops/' . \substr(
                \str_replace('\\', '/', $fileInfo->getPathname()),
                \strlen(\str_replace('\\', '/', $root)) + 1,
            );
            $sources[$relativePath] = $source;
        }

        \ksort($sources, \SORT_STRING);

        return $sources;
    }

    private static function opsSource(string $fileName): string
    {
        $path = self::kernelRoot() . '/src/Ops/' . $fileName;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function includeTokenCount(string $source): int
    {
        $count = 0;

        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            if (
                $token[0] === \T_REQUIRE
                || $token[0] === \T_REQUIRE_ONCE
                || $token[0] === \T_INCLUDE
                || $token[0] === \T_INCLUDE_ONCE
            ) {
                ++$count;
            }
        }

        return $count;
    }

    private static function phpWithoutComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function phpCodeWithoutCommentsAndStrings(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if (
                $token[0] === \T_COMMENT
                || $token[0] === \T_DOC_COMMENT
                || $token[0] === \T_CONSTANT_ENCAPSED_STRING
                || $token[0] === \T_ENCAPSED_AND_WHITESPACE
            ) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }
}
