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

namespace Coretsia\Platform\Cli\Tests\Fixture\ExternalCommand;

/**
 * Invocation recorder for an owner-package command fixture.
 */
final class ExternalOwnerService
{
    /** @var list<array{mode: ?string, items: list<string>}> */
    private array $invocations = [];

    /** @param list<string> $items */
    public function record(?string $mode, array $items): void
    {
        $this->invocations[] = [
            'mode' => $mode,
            'items' => $items,
        ];
    }

    /** @return list<array{mode: ?string, items: list<string>}> */
    public function invocations(): array
    {
        return $this->invocations;
    }
}
