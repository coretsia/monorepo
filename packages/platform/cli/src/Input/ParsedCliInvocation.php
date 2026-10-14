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

namespace Coretsia\Platform\Cli\Input;

/**
 * Immutable parsed CLI invocation with separated global presentation options.
 *
 * Command-facing input contains no launcher token or recognized CLI-global
 * options. The global format and color tokens are retained for invocation-
 * local presentation resolution only.
 *
 * No streams, container services, configuration, or catalog state are held.
 *
 * @internal
 */
final readonly class ParsedCliInvocation
{
    public function __construct(
        public ArgvInput $commandInput,
        public ?string $formatOverride = null,
        public ?string $colorOverride = null,
    ) {
    }
}
