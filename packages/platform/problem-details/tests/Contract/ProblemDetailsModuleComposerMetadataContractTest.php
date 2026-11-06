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

namespace Coretsia\Platform\ProblemDetails\Tests\Contract;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\ProblemDetails\Module\ProblemDetailsModule;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class ProblemDetailsModuleComposerMetadataContractTest extends TestCase
{
    public function testComposerIdentityAutoloadAndExactRuntimeRequirements(): void
    {
        $root = \dirname(__DIR__, 2);
        $repoRoot = \dirname(__DIR__, 5);
        $composer = self::composer();
        $releaseLine = \json_decode(
            (string) \file_get_contents($repoRoot . '/tools/release/release-line.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
        $publicConstraint = $releaseLine['publicConstraint'];
        self::assertMatchesRegularExpression('/\A\^(?:0|[1-9][0-9]*)\.(?:0|[1-9][0-9]*)\.0\z/D', $publicConstraint);

        self::assertSame('coretsia/platform-problem-details', $composer['name']);
        self::assertSame('library', $composer['type']);
        self::assertSame('Apache-2.0', $composer['license']);
        self::assertSame(
            'Coretsia HTTP Problem Details runtime package: safe error descriptor adaptation, '
            . 'deterministic RFC 9457 JSON rendering, and PSR-15 middleware.',
            $composer['description'],
        );
        self::assertSame([
            'coretsia',
            'php',
            'framework',
            'problem-details',
            'runtime',
            'http',
            'error-handling',
            'error-descriptor',
            'rfc9457',
            'rfc7807',
            'psr-15',
            'deterministic',
        ], $composer['keywords']);
        self::assertSame('^8.4', $composer['require']['php']);
        self::assertSame(['Coretsia\\Platform\\ProblemDetails\\' => 'src/'], $composer['autoload']['psr-4']);
        self::assertSame(
            ['Coretsia\\Platform\\ProblemDetails\\Tests\\' => 'tests/'],
            $composer['autoload-dev']['psr-4'],
        );
        self::assertSame([
            'php' => '^8.4',
            'coretsia/core-contracts' => $publicConstraint,
            'coretsia/core-foundation' => $publicConstraint,
            'coretsia/platform-errors' => $publicConstraint,
            'psr/http-message' => '^2.0',
            'psr/http-factory' => '^1.0',
            'psr/http-server-middleware' => '^1.0',
            'psr/http-server-handler' => '^1.0',
        ], $composer['require']);

        foreach (
            [
                'coretsia/core-kernel',
                'coretsia/platform-http',
                'coretsia/platform-redaction',
                'coretsia/platform-logging',
                'coretsia/platform-tracing',
                'coretsia/platform-metrics',
            ] as $forbidden
        ) {
            self::assertArrayNotHasKey($forbidden, $composer['require']);
        }

        self::assertFileExists($root . '/src/Module/ProblemDetailsModule.php');
        self::assertFileExists($root . '/src/Provider/ProblemDetailsServiceProvider.php');
        self::assertFileExists($root . '/config/problem_details.php');
        self::assertFileExists($root . '/config/rules.php');
    }

    public function testComposerModuleMetadataAndTypedConstantsMatchCanonicalModule(): void
    {
        $module = new ProblemDetailsModule();
        $extra = self::composer()['extra']['coretsia'];

        self::assertSame('platform.problem-details', ProblemDetailsModule::MODULE_ID);
        self::assertSame('platform/problem-details', ProblemDetailsModule::PACKAGE_ID);
        self::assertSame('coretsia/platform-problem-details', ProblemDetailsModule::COMPOSER_PACKAGE);
        self::assertSame('runtime', ProblemDetailsModule::KIND);
        self::assertSame('problem_details', ProblemDetailsModule::CONFIG_ROOT);
        self::assertSame(ProblemDetailsModule::MODULE_ID, $module->id());
        self::assertSame(ProblemDetailsModule::PACKAGE_ID, $module->packageId());
        self::assertSame(ProblemDetailsModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(ProblemDetailsModule::KIND, $module->kind());
        self::assertSame(ProblemDetailsModule::CONFIG_ROOT, $module->configRoot());
        self::assertSame([ProblemDetailsServiceProvider::class], $module->providers());
        self::assertSame([
            'kind' => 'runtime',
            'moduleId' => 'platform.problem-details',
            'moduleClass' => ProblemDetailsModule::class,
            'providers' => [ProblemDetailsServiceProvider::class],
            'requires' => ['core.foundation', 'platform.errors'],
            'conflicts' => [],
            'defaultsConfigPath' => 'config/problem_details.php',
        ], $extra);
        self::assertInstanceOf(ServiceProviderInterface::class, new ProblemDetailsServiceProvider());
        self::assertInstanceOf(ContainerDefinitionProviderInterface::class, new ProblemDetailsServiceProvider());
    }

    public function testProductionSourcesDoNotImportForbiddenConcreteImplementationsOrKernel(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$count;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (
                    \is_array($token)
                    && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_CONSTANT_ENCAPSED_STRING], true)
                ) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertDoesNotMatchRegularExpression(
                '/\bCoretsia\\\\(?:Kernel\\\\|Platform\\\\(?:Errors|Redaction|Http|Logging|Tracing|Metrics)\\\\)/',
                $code,
            );
            self::assertDoesNotMatchRegularExpression('/\bPsr\\\\Log\\\\/', $code);
        }

        self::assertSame(6, $count);
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        return \json_decode(
            (string) \file_get_contents(\dirname(__DIR__, 2) . '/composer.json'),
            true,
            flags: \JSON_THROW_ON_ERROR,
        );
    }
}
