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
use Coretsia\Platform\Cli\Output\CommandOutputBatch;
use Coretsia\Platform\Cli\Output\FormattedOutput;

/**
 * Deterministic JSON envelope renderer. The Foundation encoder owns JSON bytes.
 *
 * @internal
 */
final class JsonFormatter
{
    public function format(CommandOutputBatch $batch, ?string $commandName, int $exitCode): FormattedOutput
    {
        if (
            $exitCode < 0
            || $exitCode > 255
            || ($commandName !== null
                && \preg_match('/\A[a-z][a-z0-9-]*(?::[a-z][a-z0-9-]*)*\z/D', $commandName) !== 1)
        ) {
            throw CliOutputFormatException::failed();
        }

        $document = [
            'schema' => 'coretsia.cli-output@1',
            'meta' => [
                'command' => $commandName,
                'outcome' => $exitCode === 0 ? 'success' : 'failure',
                'exit_code' => $exitCode,
            ],
            'data' => ['records' => $batch->records()],
        ];

        try {
            $encoded = StableJsonEncoder::encodeStableMap($document);
        } catch (\Throwable) {
            throw CliOutputFormatException::failed();
        }

        if (!\str_ends_with($encoded, "\n")) {
            throw CliOutputFormatException::failed();
        }

        return new FormattedOutput(\substr($encoded, 0, -1), '');
    }
}
