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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Platform\Cli\Catalog\CommandDescriptor;

/** Shared assertions for the six Kernel-operations transport commands. */
abstract class KernelOpsIntegrationTestCase extends CliIntegrationTestCase
{
    protected static function assertDelegatedExactlyOnce(
        KernelOpsCommandFixture $fixture,
        string $commandClass,
        string $method,
        string $target,
    ): void {
        self::assertSame([['method' => $method, 'appTarget' => $target]], $fixture->ops->calls);
        self::assertSame([$commandClass], $fixture->container->resolvedIds);
        self::assertSame(1, $fixture->runtime->calls);
        self::assertFalse($fixture->runtime->active);
        self::assertSame('cli', $fixture->runtime->invocations[0]['type']);
        self::assertSame([
            'operation' => $commandClass::NAME,
            'output_format' => 'json',
        ], $fixture->runtime->invocations[0]['attributes']);
    }

    /** @param class-string $commandClass */
    protected static function assertPortOnlySource(string $commandClass): void
    {
        $source = new \ReflectionClass($commandClass)->getFileName();
        self::assertIsString($source);
        $contents = (string) \file_get_contents($source);
        self::assertStringContainsString('KernelOpsInterface', $contents);
        self::assertStringContainsString('KernelOpsRequestResolver', $contents);
        self::assertStringNotContainsString('Coretsia\\Kernel\\', $contents);
        foreach (
            [
                'ComposerManifestReader',
                'ManifestReaderInterface',
                'ModulePlanResolver',
                'ModuleResolutionOrchestrator',
                'ConfigKernel',
                'ArtifactCompiler',
                'CacheVerifier',
                'FingerprintCalculator',
                'ArtifactRuntimeBooter',
                'KernelArtifactOperation',
                'file_get_contents(',
                'fopen(',
                'scandir(',
                'glob(',
                'readfile(',
                '->get(',
                'new KernelOpsFacade',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $contents, $commandClass);
        }
    }

    /** @param class-string $commandClass */
    protected static function assertTargetOnlyMetadata(string $commandClass): void
    {
        $descriptor = CommandDescriptor::fromValidatedMetadata($commandClass, [
            'name' => $commandClass::NAME,
            'summary' => $commandClass::SUMMARY,
            'group' => $commandClass::GROUP,
            'hidden' => $commandClass::HIDDEN,
            'arguments' => $commandClass::ARGUMENTS,
            'options' => $commandClass::OPTIONS,
        ]);
        self::assertSame([], $descriptor->arguments());
        self::assertSame(['target'], \array_column($descriptor->options(), 'name'));
    }
}
