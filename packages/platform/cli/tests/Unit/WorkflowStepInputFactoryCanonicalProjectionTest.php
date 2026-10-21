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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use Coretsia\Platform\Cli\Workflow\WorkflowStepInputFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowStepInputFactoryCanonicalProjectionTest extends TestCase
{
    public function testFactoryProjectsCanonicalOptionOrderFlagsRepeatedValuesAndPositionalMarker(): void
    {
        $definitions = new WorkflowSchema()->normalize([
            'verify' => [
                'summary' => 'Exercise structured workflow input.',
                'steps' => [
                    [
                        'command' => 'external:mode',
                        'arguments' => ['--format=json', 'file with spaces', '--color=never'],
                        'options' => [
                            'z-last' => 'tail',
                            'values' => ['third', 'first', 'second'],
                            'a-flag' => true,
                            'b-mode' => 'fast',
                        ],
                    ],
                ],
            ],
        ]);
        $step = $definitions['verify']->steps()[0];
        $input = new WorkflowStepInputFactory()->create($step);

        self::assertInstanceOf(ArgvInput::class, $input);
        self::assertSame(['a-flag', 'b-mode', 'values', 'z-last'], \array_keys($input->options()));
        self::assertSame([
            'a-flag' => true,
            'b-mode' => 'fast',
            'values' => ['third', 'first', 'second'],
            'z-last' => 'tail',
        ], $input->options());
        self::assertSame([
            'external:mode',
            '--a-flag',
            '--b-mode=fast',
            '--values=third',
            '--values=first',
            '--values=second',
            '--z-last=tail',
            '--',
            '--format=json',
            'file with spaces',
            '--color=never',
        ], $input->tokens());
        self::assertSame(['--format=json', 'file with spaces', '--color=never'], $input->arguments());
        self::assertTrue($input->option('a-flag'));
        self::assertSame(['third', 'first', 'second'], $input->option('values'));
        self::assertFalse($input->hasOption('format'));
        self::assertFalse($input->hasOption('color'));
        self::assertSame($input->tokens(), new WorkflowStepInputFactory()->create($step)->tokens());
    }

    public function testFactoryDoesNotIntroducePositionalMarkerWithoutArgumentsOrNormalizeScalarRepeatables(): void
    {
        $definition = new WorkflowSchema()->normalize([
            'verify' => [
                'summary' => 'Retain structured scalar values.',
                'steps' => [
                    [
                        'command' => 'external:mode',
                        'arguments' => [],
                        'options' => ['item' => 'single'],
                    ],
                ],
            ],
        ]);
        $input = new WorkflowStepInputFactory()->create($definition['verify']->steps()[0]);

        self::assertSame(['external:mode', '--item=single'], $input->tokens());
        self::assertSame(['item' => 'single'], $input->options());
        self::assertSame([], $input->arguments());
    }

    #[DataProvider('invalidOptionValues')]
    public function testWorkflowSchemaRejectsFalseAndNullBeforeFactoryConstruction(mixed $value): void
    {
        try {
            new WorkflowSchema()->normalize([
                'verify' => [
                    'summary' => 'Reject unsupported option values.',
                    'steps' => [
                        [
                            'command' => 'external:mode',
                            'arguments' => [],
                            'options' => ['unsafe-option' => $value],
                        ],
                    ],
                ],
            ]);
            self::fail('WorkflowSchema must reject false and null configuration values.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(WorkflowDefinitionInvalidException::REASON_DEFINITION_INVALID, $exception->reason());
            self::assertStringNotContainsString('unsafe-option', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidOptionValues(): iterable
    {
        yield 'false' => [false];
        yield 'null' => [null];
    }
}
