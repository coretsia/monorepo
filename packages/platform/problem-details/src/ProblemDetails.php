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

namespace Coretsia\Platform\ProblemDetails;

use InvalidArgumentException;

/**
 * Immutable, safe-by-construction HTTP Problem Details representation.
 *
 * This transport model is distinct from the format-neutral ErrorDescriptor.
 * Only an HTTP error status is accepted from callers; type and title are
 * fixed by this class and cannot contain arbitrary application diagnostics.
 */
final readonly class ProblemDetails
{
    private const string DEFAULT_TYPE = 'about:blank';
    private const string UNKNOWN_TITLE = 'HTTP Error';

    /**
     * Fixed status/title mapping for standard HTTP error responses.
     *
     * @var array<int, non-empty-string>
     */
    private const array STATUS_TITLES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Content Too Large',
        414 => 'URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Range Not Satisfiable',
        417 => 'Expectation Failed',
        421 => 'Misdirected Request',
        422 => 'Unprocessable Content',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Too Early',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];

    private string $type;
    private string $title;
    private int $status;

    public function __construct(int $status)
    {
        if ($status < 400 || $status > 599) {
            throw new InvalidArgumentException('Invalid HTTP error status.');
        }

        $this->type = self::DEFAULT_TYPE;
        $this->title = self::STATUS_TITLES[$status] ?? self::UNKNOWN_TITLE;
        $this->status = $status;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array{status: int, title: string, type: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'title' => $this->title,
            'type' => $this->type,
        ];
    }
}
