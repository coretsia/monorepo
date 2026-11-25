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

namespace Coretsia\Platform\Routing\Exception;

/**
 * Deterministic rejection of an application route intersecting a reserved path.
 *
 * The diagnostic is fixed. Rejected templates and concrete intersection
 * witnesses MUST NOT appear in the message or previous throwable chain.
 *
 * @internal
 */
final class ReservedRouteViolationException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN';
    public const string REASON_RESERVED_ROUTE_FORBIDDEN = 'routing-reserved-route-forbidden';

    private function __construct()
    {
        parent::__construct(self::ERROR_CODE . ': ' . self::REASON_RESERVED_ROUTE_FORBIDDEN);
    }

    public static function forbidden(): self
    {
        return new self();
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return self::REASON_RESERVED_ROUTE_FORBIDDEN;
    }
}
