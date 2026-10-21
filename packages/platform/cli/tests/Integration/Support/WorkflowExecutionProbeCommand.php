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

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;

/** Controllable owner fixture for real workflow dispatch and nested output. */
final class WorkflowExecutionProbeCommand implements CommandInterface
{
    public const string NAME = 'owner:workflow-probe';
    public const string SUMMARY = 'Exercise workflow lifecycle and output boundaries.';
    public const string GROUP = 'owner';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [
        ['name' => 'subject', 'summary' => 'Step identity.', 'required' => true, 'variadic' => false],
    ];
    public const array OPTIONS = [
        ['name' => 'mode', 'summary' => 'Fixture execution mode.', 'value' => 'required', 'repeatable' => false],
        ['name' => 'item', 'summary' => 'Ordered fixture values.', 'value' => 'required', 'repeatable' => true],
    ];

    /**
     * @var list<array{
     *     subject: string,
     *     input: InputInterface,
     *     output: OutputInterface,
     *     mode: string,
     *     uow: ?string,
     *     previous: mixed,
     * }>
     */
    public static array $calls = [];
    public static ?ContextStore $context = null;
    public static bool $wrongName = false;
    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public static function reset(): void
    {
        self::$calls = [];
        self::$context = null;
        self::$wrongName = false;
        self::$constructed = 0;
    }

    public function name(): string
    {
        return self::$wrongName ? 'owner:incorrect' : self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $subject = $input->arguments()[0] ?? '';
        $mode = $input->option('mode') ?? 'mixed';
        if (!\is_string($mode)) {
            throw new \LogicException('workflow-fixture-mode-invalid');
        }
        $previous = self::$context?->get(ContextKeys::ACTOR_ID);
        $uow = self::$context?->get(ContextKeys::UOW_ID);
        self::$calls[] = [
            'subject' => $subject,
            'input' => $input,
            'output' => $output,
            'mode' => $mode,
            'uow' => \is_string($uow) ? $uow : null,
            'previous' => $previous,
        ];

        if (self::$context !== null) {
            self::$context->set(ContextKeys::ACTOR_ID, 'actor-' . $subject);
        }

        if ($mode === 'large') {
            $output->text(\str_repeat('L', 580000));
            return 0;
        }

        $output->text('text-' . $subject);
        $output->json([
            'label' => $subject,
            'nested' => ['safe' => 'visible-' . $subject, 'password' => 'PRIVATE_PASSWORD_' . $subject],
        ]);
        $output->error('PROBE_NOTICE', 'notice-' . $subject);

        if ($mode === 'throw') {
            throw new \RuntimeException('PRIVATE_THROW_' . $subject);
        }

        return $mode === 'fail' ? 17 : ($mode === 'invalid-exit' ? 300 : 0);
    }
}
