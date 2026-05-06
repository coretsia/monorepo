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

namespace Coretsia\Foundation\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Deterministic PSR-11 not-found failure.
 *
 * The service id is retained for in-process handling, but the exception message
 * remains stable and does not embed the id. This avoids accidental leakage of
 * unsafe user-controlled identifiers through logs or diagnostics.
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    public const string ERROR_CODE = 'CORETSIA_CONTAINER_NOT_FOUND';

    private readonly string $serviceId;

    public function __construct(
        string $serviceId,
        ?\Throwable $previous = null,
    ) {
        if ($serviceId === '') {
            throw new \InvalidArgumentException('container-service-id-empty');
        }

        if (\trim($serviceId) !== $serviceId || \preg_match('/\s/u', $serviceId) === 1) {
            throw new \InvalidArgumentException('container-service-id-whitespace-forbidden');
        }

        $this->serviceId = $serviceId;

        parent::__construct('container-service-not-found', $previous);
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }
}
