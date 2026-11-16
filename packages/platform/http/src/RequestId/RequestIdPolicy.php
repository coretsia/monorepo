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

namespace Coretsia\Platform\Http\RequestId;

use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Immutable HTTP-owned request identity policy.
 *
 * Upstream acceptance requires an explicitly trusted ingress boundary. This
 * policy validates effective PSR-7 header values, not their wire-level form,
 * and does not establish sender identity, upstream trust, or uniqueness.
 * Request-local identity lives in the canonical ContextStore and is cleared
 * through the existing UnitOfWork reset lifecycle.
 *
 * @internal
 */
final readonly class RequestIdPolicy
{
    private const int MAX_HEADER_NAME_BYTES = 128;
    private const int MIN_ID_LENGTH_LIMIT = 26;
    private const int MAX_ID_LENGTH_LIMIT = 128;

    public function __construct(
        private ContextStore $context,
        private IdGeneratorInterface $generator,
        private bool $enabled,
        private string $headerName,
        private bool $acceptFromUpstream,
        private int $maxLength,
    ) {
        if (
            \strlen($this->headerName) > self::MAX_HEADER_NAME_BYTES
            || \preg_match('/\A[!#$%&\'\x2a+.^_`|~0-9A-Za-z-]+\z/D', $this->headerName) !== 1
            || \preg_match('/\A(?:[A-Za-z0-9]+-)*request-id\z/iD', $this->headerName) !== 1
        ) {
            throw new \InvalidArgumentException('http-request-id-header-invalid');
        }

        if ($this->maxLength < self::MIN_ID_LENGTH_LIMIT || $this->maxLength > self::MAX_ID_LENGTH_LIMIT) {
            throw new \InvalidArgumentException('http-request-id-max-length-invalid');
        }
    }

    /**
     * Selects request identity once before the PSR-15 pipeline executes.
     * Returns the exact stored value for invocation-local finalization checks.
     */
    public function initialize(ServerRequestInterface $request): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $requestId = null;
        if ($this->acceptFromUpstream) {
            $values = $request->getHeader($this->headerName);
            if (\count($values) === 1) {
                $requestId = $this->validatedIdentifier(\reset($values));
            }
        }

        if ($requestId === null) {
            $requestId = $this->generator->generate();
            if (\preg_match('/\A[0-7][0-9A-HJKMNP-TV-Z]{25}\z/D', $requestId) !== 1) {
                throw new \RuntimeException('http-request-id-generation-invalid');
            }
        }

        $this->context->set(ContextKeys::REQUEST_ID, $requestId);

        return $requestId;
    }

    /**
     * Reads back only the HTTP-owned request identity for response finalization.
     * Missing or invalid enabled state is never repaired or regenerated.
     */
    public function current(): ?string
    {
        if (!$this->enabled) {
            return null;
        }

        $requestId = $this->validatedIdentifier($this->context->get(ContextKeys::REQUEST_ID));
        if ($requestId === null) {
            throw new \RuntimeException('http-request-id-context-invalid');
        }

        return $requestId;
    }

    public function headerName(): string
    {
        return $this->headerName;
    }

    private function validatedIdentifier(mixed $value): ?string
    {
        if (
            !\is_string($value)
            || \strlen($value) > $this->maxLength
            || \preg_match('/\A[A-Za-z0-9._-]+\z/D', $value) !== 1
        ) {
            return null;
        }

        return $value;
    }
}
