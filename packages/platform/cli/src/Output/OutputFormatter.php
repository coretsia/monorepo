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

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Exception\CliOutputFormatException;
use Coretsia\Platform\Cli\Exception\RedactionViolationException;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use Coretsia\Platform\Cli\Output\Formatter\TableFormatter;

/**
 * One-call output boundary: shared redaction, canonical batch, and one formatter.
 *
 * @internal
 */
final readonly class OutputFormatter
{
    private const string REDACTED = '[REDACTED]';

    public function __construct(
        private SensitiveDataRedactorInterface $redactor,
        private JsonFormatter $jsonFormatter,
        private TableFormatter $tableFormatter,
        private PlainFormatter $plainFormatter,
    ) {
    }

    public function format(
        CommandOutputBatch $batch,
        ?string $commandName,
        int $exitCode,
        string $format,
        bool $color,
    ): FormattedOutput {
        if (!\in_array($format, ['json', 'table', 'plain'], true)) {
            throw CliOutputFormatException::failed();
        }

        try {
            $context = new RedactionContext('cli.output', RedactionMode::Placeholder);
            $records = [];

            foreach ($batch->records() as $record) {
                switch ($record['type']) {
                    case 'text':
                        $redacted = $this->redactor->redactJsonLike($record['text'], $context);
                        $records[] = [
                            'text' => \is_string($redacted) ? $redacted : self::REDACTED,
                            'type' => 'text',
                        ];
                        break;
                    case 'json':
                        $redacted = $this->redactor->redactJsonLike($record['payload'], $context);
                        if (!\is_array($redacted)) {
                            throw RedactionViolationException::failed();
                        }
                        $records[] = ['payload' => $redacted, 'type' => 'json'];
                        break;
                    case 'error':
                        $redacted = $this->redactor->redactJsonLike($record['message'], $context);
                        $records[] = [
                            'code' => $record['code'],
                            'message' => \is_string($redacted) ? $redacted : self::REDACTED,
                            'type' => 'error',
                        ];
                        break;
                    default:
                        throw RedactionViolationException::failed();
                }
            }

            $redactedBatch = new CommandOutputBatch($records);
        } catch (\Throwable) {
            throw RedactionViolationException::failed();
        }

        try {
            return match ($format) {
                'json' => $this->jsonFormatter->format($redactedBatch, $commandName, $exitCode),
                'table' => $this->tableFormatter->format($redactedBatch, $color),
                'plain' => $this->plainFormatter->format($redactedBatch, $color),
            };
        } catch (\Throwable) {
            throw CliOutputFormatException::failed();
        }
    }
}
