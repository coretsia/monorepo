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

namespace Coretsia\Platform\Cli\Diagnostics;

use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;

/**
 * Emits only the public error descriptor fields through the output port.
 *
 * @internal
 */
final class ExceptionRenderer
{
    public function render(ErrorDescriptor $descriptor, OutputInterface $output): void
    {
        if ($descriptor->code() === 'CORETSIA_UNHANDLED_EXCEPTION') {
            $output->error('CORETSIA_CLI_INTERNAL_ERROR', 'Internal CLI error.');

            return;
        }

        $output->error($descriptor->code(), $descriptor->message());
    }
}
