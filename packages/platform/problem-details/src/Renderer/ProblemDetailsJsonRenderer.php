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

namespace Coretsia\Platform\ProblemDetails\Renderer;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\ProblemDetails\ProblemDetails;

/**
 * Stateless RFC 9457 JSON renderer with RFC 7807-compatible base members.
 *
 * ProblemDetails already constrains its public schema to safe, bounded
 * primitives. The shared encoder owns stable JSON ordering and final LF.
 */
final class ProblemDetailsJsonRenderer
{
    public const string CONTENT_TYPE = 'application/problem+json';

    public function render(ProblemDetails $problem): string
    {
        return StableJsonEncoder::encodeStableMap($problem->toArray());
    }

    public function contentType(): string
    {
        return self::CONTENT_TYPE;
    }
}
