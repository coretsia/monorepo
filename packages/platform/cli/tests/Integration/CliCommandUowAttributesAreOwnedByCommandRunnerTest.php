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

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Runtime\KernelRuntimeInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\UnitOfWorkType;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\BoundaryScenarioFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class CliCommandUowAttributesAreOwnedByCommandRunnerTest extends CliIntegrationTestCase
{
    public function testOnlyOperationAndEffectiveOutputFormatReachTheCanonicalUow(): void
    {
        foreach (['json', 'plain', 'table'] as $format) {
            $fixture = new BoundaryScenarioFixture();
            $result = $fixture->run(['external:boundary', '--format=' . $format, '--color=never']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame(1, $fixture->runtime->calls);
            self::assertSame([
                [
                    'type' => UnitOfWorkType::CLI,
                    'attributes' => ['operation' => BoundaryScenarioCommand::NAME, 'output_format' => $format],
                ],
            ], $fixture->runtime->invocations);
            self::assertSame('cli', $fixture->runtime->invocations[0]['type']);
            self::assertTrue(BoundaryScenarioCommand::$constructedInUow);
            self::assertTrue(BoundaryScenarioCommand::$ranInUow);
            self::assertFalse($fixture->runtime->active);
        }
    }

    public function testCliCannotWriteBaseContextKeysOrInventOutputFormatContextKey(): void
    {
        $cli = self::packageRoot() . '/src';
        foreach (
            new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($cli, \FilesystemIterator::SKIP_DOTS),
            ) as $file
        ) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) \file_get_contents($file->getPathname());
            self::assertStringNotContainsString('ContextStore', $source, $file->getFilename());
            self::assertStringNotContainsString('->set(ContextKeys::', $source, $file->getFilename());
        }
        $kernel = self::repositoryRoot() . '/packages/core/kernel/src/Runtime/KernelRuntime.php';
        $source = (string) \file_get_contents($kernel);
        foreach (['ContextKeys::CORRELATION_ID', 'ContextKeys::UOW_ID', 'ContextKeys::UOW_TYPE'] as $key) {
            self::assertStringContainsString('$this->contextStore->set(' . $key, $source);
        }
        self::assertStringNotContainsString('$this->contextStore->set(\'output_format\'', $source);
        self::assertSame(3, \substr_count($source, '$this->contextStore->set(ContextKeys::'));
        self::assertSame('correlation_id', ContextKeys::CORRELATION_ID);
    }

    public function testKernelRuntimeOwnsBaseContextWritesAndResetsAfterBothOutcomes(): void
    {
        $root = self::consumer();
        try {
            $container = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $runtime = $container->get(KernelRuntimeInterface::class);
            $context = $container->get(ContextStore::class);
            $attributes = ['operation' => BoundaryScenarioCommand::NAME, 'output_format' => 'plain'];
            $called = 0;

            $value = $runtime->runUnitOfWork(
                UnitOfWorkType::CLI,
                static function () use ($context, &$called): int {
                    ++$called;
                    $keys = $context->snapshot()->all();
                    self::assertSame(
                        [ContextKeys::CORRELATION_ID, ContextKeys::UOW_ID, ContextKeys::UOW_TYPE],
                        \array_keys($keys),
                    );
                    self::assertSame(UnitOfWorkType::CLI, $keys[ContextKeys::UOW_TYPE]);
                    self::assertArrayNotHasKey('output_format', $keys);
                    return 42;
                },
                $attributes,
            );
            self::assertSame(42, $value);
            self::assertSame(1, $called);
            self::assertSame([], $context->snapshot()->all());

            try {
                $runtime->runUnitOfWork(
                    UnitOfWorkType::CLI,
                    static function () use ($context, &$called): void {
                        ++$called;
                        self::assertTrue($context->has(ContextKeys::UOW_ID));
                        throw new \RuntimeException('PRIVATE_UOW_ERROR');
                    },
                    $attributes,
                );
                self::fail('The original command failure must propagate from KernelRuntime.');
            } catch (\RuntimeException $exception) {
                self::assertSame('PRIVATE_UOW_ERROR', $exception->getMessage());
            }
            self::assertSame(2, $called);
            self::assertSame([], $context->snapshot()->all());
        } finally {
            self::removeTree($root);
        }
    }

    public function testFailedOwnerCommandReturnsControlToKernelAfterPhase(): void
    {
        $fixture = new BoundaryScenarioFixture();
        BoundaryScenarioCommand::$throw = true;
        $result = $fixture->run(['external:boundary', '--format=plain']);
        self::assertSame(1, $result['exit']);
        self::assertSame(['enter', 'resolve', 'construct', 'run', 'leave'], $fixture->runtime->events);
        self::assertSame([
            'type' => UnitOfWorkType::CLI,
            'attributes' => ['operation' => BoundaryScenarioCommand::NAME, 'output_format' => 'plain'],
        ], $fixture->runtime->invocations[0]);
        self::assertFalse($fixture->runtime->active);
    }
}
