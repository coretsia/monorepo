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

namespace Coretsia\Platform\Http;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Canonical PSR-15 HTTP runtime entrypoint contract.
 *
 * The inherited RequestHandlerInterface::handle() contract is the only
 * request/response surface. HTTP lifecycle coordination belongs to HttpKernel;
 * Kernel retains UnitOfWork and reset ownership. This interface introduces no
 * additional request, response, transport, or routing abstraction.
 */
interface HttpKernelInterface extends RequestHandlerInterface
{
}
