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
 * Resolves one effective ANSI color decision without probing the terminal.
 *
 * @internal
 */
final readonly class ColorResolver
{
    public function __construct(private CliOutputPolicy $policy)
    {
    }

    public function resolve(
        ?string $explicitColor,
        TerminalCapabilities $capabilities,
        string $effectiveFormat,
    ): bool {
        $color = $explicitColor ?? $this->policy->colorDefault();

        if (
            !\in_array($color, ['auto', 'always', 'never'], true)
            || !\in_array($effectiveFormat, ['json', 'table', 'plain'], true)
        ) {
            throw CliInputInvalidException::withReason(CliInputInvalidException::REASON_OPTION_INVALID);
        }

        if ($effectiveFormat === 'json') {
            return false;
        }

        return match ($color) {
            'always' => true,
            'never' => false,
            'auto' => $capabilities->interactive() && $capabilities->ansiSupported(),
        };
    }
}
