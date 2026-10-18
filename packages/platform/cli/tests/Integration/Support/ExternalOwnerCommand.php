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

/** A non-CLI package's command, consuming only CLI contracts and its own service. */
final class ExternalOwnerCommand implements CommandInterface
{
    public const string NAME = 'external:service';
    public const string SUMMARY = 'Dispatch an external owner service.';
    public const string GROUP = 'external';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [
        ['name' => 'mode', 'summary' => 'Owner domain mode.', 'value' => 'required', 'repeatable' => false],
    ];

    public static int $constructed = 0;
    public static int $executed = 0;
    public static ?string $receivedMode = null;
    /** @var array<string,mixed> */
    public static array $receivedOptions = [];

    public function __construct(private ExternalOwnerService $ownerService)
    {
        ++self::$constructed;
    }

    public static function reset(): void
    {
        self::$constructed = 0;
        self::$executed = 0;
        self::$receivedMode = null;
        self::$receivedOptions = [];
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        ++self::$executed;
        self::$receivedMode = $input->option('mode');
        self::$receivedOptions = $input->options();
        $output->json($this->ownerService->handle(self::$receivedMode));
        return 0;
    }
}
