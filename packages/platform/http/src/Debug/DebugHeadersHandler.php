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

namespace Coretsia\Platform\Http\Debug;

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bounded header presence diagnostics for an already authorized request.
 *
 * Only fixed header names are examined. Sensitive presence summaries use
 * the shared Placeholder redaction contract without reading header values.
 *
 * @internal
 */
final readonly class DebugHeadersHandler
{
    public function __construct(
        private SensitiveDataRedactorInterface $redactor,
        private FallbackResponses $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responses->debugJson([
            'headers' => [
                'accept' => $request->hasHeader('Accept'),
                'authorization' => $this->sensitivePresence($request, 'Authorization', RedactionKind::Authorization),
                'content-type' => $request->hasHeader('Content-Type'),
                'cookie' => $this->sensitivePresence($request, 'Cookie', RedactionKind::Cookie),
                'set-cookie' => $this->sensitivePresence($request, 'Set-Cookie', RedactionKind::Cookie),
                'user-agent' => $request->hasHeader('User-Agent'),
                'x-debug-token' => $this->sensitivePresence($request, 'X-Debug-Token', RedactionKind::Token),
            ],
        ]);
    }

    /** @return array<string, mixed>|null */
    private function sensitivePresence(
        ServerRequestInterface $request,
        string $header,
        RedactionKind $kind,
    ): ?array {
        if (!$request->hasHeader($header)) {
            return null;
        }

        // Placeholder mode requires no sensitive bytes or header cardinality.
        $summary = $this->redactor->redactValue(
            value: '',
            kind: $kind,
            context: new RedactionContext('http.debug.headers', RedactionMode::Placeholder),
        );

        if (
            $summary->kind() !== $kind
            || $summary->mode() !== RedactionMode::Placeholder
            || $summary->length() !== null
            || $summary->hash() !== null
        ) {
            throw RedactionException::outputInvalid();
        }

        return $summary->toArray();
    }
}
