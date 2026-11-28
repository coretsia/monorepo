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

namespace Coretsia\Platform\HttpApp\Response;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Maps admitted application return values through the effective PSR-17 factories.
 *
 * Existing responses pass through unchanged. Explicit HTML strings remain
 * application-owned output; escaping untrusted data is the action owner's
 * responsibility. JSON bytes are owned by the canonical Foundation encoder.
 */
final readonly class ActionResponseMapper
{
    public function __construct(
        private ResponseFactoryInterface $responses,
        private StreamFactoryInterface $streams,
        private bool $jsonEnabled,
        private bool $htmlEnabled,
    ) {
    }

    public function map(mixed $value): ResponseInterface
    {
        if ($value instanceof ResponseInterface) {
            return $value;
        }

        try {
            if (\is_string($value)) {
                if (!$this->htmlEnabled) {
                    throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_RESPONSE_DISABLED);
                }

                $contentType = 'text/html; charset=utf-8';
                $body = $value;
            } elseif ($value === null || \is_bool($value) || \is_int($value) || \is_array($value)) {
                if (!$this->jsonEnabled) {
                    throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_RESPONSE_DISABLED);
                }

                $contentType = 'application/json';
                $body = StableJsonEncoder::encodeStable($value);
            } else {
                throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_RESPONSE_UNSUPPORTED);
            }

            return $this->responses->createResponse(200)
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streams->createStream($body));
        } catch (HttpAppDispatchException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw HttpAppDispatchException::withReason(HttpAppDispatchException::REASON_RESPONSE_MAPPING_FAILED);
        }
    }
}
