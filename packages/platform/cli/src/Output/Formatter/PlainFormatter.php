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

namespace Coretsia\Platform\Cli\Output\Formatter;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Output\Ansi\AnsiDecorator;
use Coretsia\Platform\Cli\Output\CommandOutputBatch;
use Coretsia\Platform\Cli\Output\FormattedOutput;

/**
 * Stateless plain stream renderer. Each record preserves its stream and order.
 *
 * @internal
 */
final readonly class PlainFormatter
{
    public function __construct(private AnsiDecorator $ansi)
    {
    }

    public function format(CommandOutputBatch $batch, bool $color): FormattedOutput
    {
        $stdout = [];
        $stderr = [];

        try {
            foreach ($batch->records() as $record) {
                switch ($record['type']) {
                    case 'text':
                        $stdout[] = $record['text'];
                        break;
                    case 'json':
                        $stdout[] = self::stableValue($record['payload']);
                        break;
                    case 'error':
                        $stderr[] = $this->ansi->decorate(
                            $record['code'] . ': ' . $record['message'],
                            'error',
                            $color,
                            'plain',
                        );
                        break;
                    default:
                        throw CliOutputFormatException::failed();
                }
            }

            return new FormattedOutput(
                self::streamBytes($stdout),
                self::streamBytes($stderr),
            );
        } catch (\Throwable) {
            throw CliOutputFormatException::failed();
        }
    }

    /** @param list<string> $pieces */
    private static function streamBytes(array $pieces): string
    {
        return \rtrim(\implode("\n", $pieces), "\r\n");
    }

    public static function stableValue(mixed $value): string
    {
        $encoded = StableJsonEncoder::encodeStable($value);

        if (!\str_ends_with($encoded, "\n")) {
            throw CliOutputFormatException::failed();
        }

        return \substr($encoded, 0, -1);
    }
}
