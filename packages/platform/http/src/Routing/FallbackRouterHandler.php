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

namespace Coretsia\Platform\Http\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Canonical terminal handler for supported system endpoints and unmatched requests.
 *
 * Delegates endpoint dispatch and safe unmatched response construction to
 * their canonical owners. General application routing remains separate.
 *
 * @internal
 */
final readonly class FallbackRouterHandler implements RequestHandlerInterface
{
    public function __construct(
        private FallbackRouter $router,
        private FallbackResponses $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->router->dispatch($request) ?? $this->responses->notFound();
    }
}
