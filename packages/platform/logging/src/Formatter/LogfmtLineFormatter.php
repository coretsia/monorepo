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

namespace Coretsia\Platform\Logging\Formatter;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;

/**
 * Stateless deterministic logfmt serializer for canonical log records.
 *
 * The fixed field order below is a serialization order, not a second sink
 * schema. The single canonical shape remains owned by LogRecordPolicy.
 * Nested context JSON is encoded exclusively by Foundation.
 */
final class LogfmtLineFormatter
{
    /** @param array<string, mixed> $record */
    public function format(array $record): string
    {
        if (!LogRecordPolicy::isValidCanonicalRecord($record)) {
            throw new \InvalidArgumentException('logging-record-invalid');
        }

        $context = 'null';

        if ($record['context'] !== null) {
            $json = StableJsonEncoder::encodeStableMap($record['context']);

            if (!\str_ends_with($json, "\n")) {
                throw new \RuntimeException('logging-context-serialization-invalid');
            }

            // Remove only the LF already appended by the Foundation encoder.
            $context = self::quoted(\substr($json, 0, -1));
        }

        // Literal order is intentional; no competing root-field allowlist or
        // independent schema validator is introduced by the formatter.
        return \implode(' ', [
                'ts_utc=' . self::scalar($record['ts_utc']),
                'level=' . self::scalar($record['level']),
                'message=' . self::scalar($record['message']),
                'service_name=' . self::scalar($record['service_name']),
                'app_target=' . self::scalar($record['app_target']),
                'correlation_id=' . self::scalar($record['correlation_id']),
                'uow_id=' . self::scalar($record['uow_id']),
                'uow_type=' . self::scalar($record['uow_type']),
                'context=' . $context,
            ]) . "\n";
    }

    private static function scalar(?string $value): string
    {
        return $value === null ? 'null' : self::quoted($value);
    }

    private static function quoted(string $value): string
    {
        $escaped = \preg_replace_callback(
            '/[\\\\"\x00-\x1F\x7F]/',
            static fn (array $match): string => match ($match[0]) {
                '\\' => '\\\\',
                '"' => '\\"',
                "\n" => '\\n',
                "\r" => '\\r',
                "\t" => '\\t',
                default => \sprintf('\\u%04X', \ord($match[0])),
            },
            $value,
        );

        if ($escaped === null) {
            throw new \RuntimeException('logging-logfmt-serialization-failed');
        }

        return '"' . $escaped . '"';
    }
}
