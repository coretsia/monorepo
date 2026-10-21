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

namespace Coretsia\Platform\Cli\Workflow;

use Coretsia\Platform\Cli\Input\ArgvInput;

/**
 * Stateless structured-input adapter for a validated workflow step.
 *
 * The incoming option map is already byte-order canonical from WorkflowSchema.
 * Repeatability and required-value normalization remain solely with the
 * shared CommandInputValidator; generated tokens are never parsed again.
 *
 * @internal
 */
final class WorkflowStepInputFactory
{
    public function create(WorkflowStep $step): ArgvInput
    {
        $tokens = [$step->command()];

        foreach ($step->options() as $name => $value) {
            if ($value === true) {
                $tokens[] = '--' . $name;
            } elseif (\is_string($value)) {
                $tokens[] = '--' . $name . '=' . $value;
            } else {
                foreach ($value as $item) {
                    $tokens[] = '--' . $name . '=' . $item;
                }
            }
        }

        $arguments = $step->arguments();
        if ($arguments !== []) {
            $tokens[] = '--';
            foreach ($arguments as $argument) {
                $tokens[] = $argument;
            }
        }

        return new ArgvInput(
            tokens: $tokens,
            commandName: $step->command(),
            arguments: $arguments,
            options: $step->options(),
        );
    }
}
