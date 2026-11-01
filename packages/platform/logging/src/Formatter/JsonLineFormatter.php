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
 * Stateless JSON-lines serializer for fully admitted and redacted log records.
 *
 * Foundation owns recursive json-like normalization, deterministic map
 * ordering, and the single final LF. This class does not perform sink
 * admission, shared redaction, record-size filtering, or stream I/O.
 */
final class JsonLineFormatter
{
    /** @param array<string, mixed> $record */
    public function format(array $record): string
    {
        if (!LogRecordPolicy::isValidCanonicalRecord($record)) {
            throw new \InvalidArgumentException('logging-record-invalid');
        }

        return StableJsonEncoder::encodeStableMap($record);
    }
}
