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

use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;

final class CliHostResolvesCanonicalSensitiveDataRedactorTest extends CliIntegrationTestCase
{
    public function testComposedConsoleHostUsesOneCanonicalRedactionPort(): void
    {
        $root = self::consumer();
        try {
            $container = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $plan = $container->get(ModulePlan::class);
            self::assertTrue($plan->hasEnabledModule('platform.redaction'));
            self::assertTrue($plan->hasEnabledModule('platform.errors'));
            $redactor = $container->get(SensitiveDataRedactorInterface::class);
            self::assertInstanceOf(DefaultSensitiveDataRedactor::class, $redactor);
            self::assertSame($redactor, $container->get(SensitiveDataRedactorInterface::class));
            self::assertSame($redactor, $container->get(DefaultSensitiveDataRedactor::class));
            $formatter = $container->get(OutputFormatter::class);
            self::assertSame(
                $redactor,
                new \ReflectionProperty(OutputFormatter::class, 'redactor')->getValue($formatter),
            );
            $handler = $container->get(ErrorHandlerInterface::class);
            self::assertInstanceOf(ErrorHandler::class, $handler);
            self::assertSame($handler, $container->get(ErrorHandler::class));
            self::assertSame(
                $redactor,
                new \ReflectionProperty(ErrorHandler::class, 'redactor')->getValue($handler),
            );
        } finally {
            self::removeTree($root);
        }
    }

    public function testCliAndErrorsProductionSourcesDoNotImportOrInstantiatePlatformRedactor(): void
    {
        foreach (
            [
                self::packageRoot() . '/src',
                self::repositoryRoot() . '/packages/platform/errors/src',
            ] as $source
        ) {
            foreach (
                new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                ) as $file
            ) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $contents = (string) \file_get_contents($file->getPathname());
                self::assertStringNotContainsString(
                    'Coretsia\\Platform\\Redaction\\',
                    $contents,
                    $file->getFilename(),
                );
                self::assertStringNotContainsString(
                    'DefaultSensitiveDataRedactor',
                    $contents,
                    $file->getFilename(),
                );
            }
        }
    }
}
