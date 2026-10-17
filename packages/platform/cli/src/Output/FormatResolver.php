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

namespace Coretsia\Platform\Cli\Output;

use Coretsia\Platform\Cli\Exception\CliInputInvalidException;

/**
 * Resolves the invocation-local output format from validated output policy.
 *
 * @internal
 */
final readonly class FormatResolver
{
    public function __construct(private CliOutputPolicy $policy)
    {
    }

    public function resolve(?string $explicitFormat, TerminalCapabilities $capabilities): string
    {
        $format = $explicitFormat ?? $this->policy->formatDefault();

        if (!\in_array($format, ['adaptive', 'json', 'table', 'plain'], true)) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
        }

        if ($format !== 'adaptive') {
            return $format;
        }

        return $capabilities->interactive()
            ? $this->policy->interactiveFormat()
            : $this->policy->nonInteractiveFormat();
    }
}
