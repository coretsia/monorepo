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

namespace Coretsia\Kernel\Artifacts\Builders;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;

/**
 * Builds the Kernel-owned `container@1` deterministic stub artifact envelope.
 *
 * This builder exists only to materialize the `container@1` artifact slot in
 * epic 1.330.0. It does not build a real compiled container.
 *
 * The stub payload is intentionally minimal and forward-compatible with the
 * later 1.340.0 compiled container work:
 *
 *     [
 *         'aliases' => [],
 *         'compiled' => false,
 *         'kind' => 'stub',
 *         'services' => [],
 *         'tags' => [],
 *     ]
 *
 * Later 1.340.0 work MAY emit `kind = "compiled"` only if the compiled payload
 * remains compatible with `container@1`. If the real compiled container payload
 * shape is incompatible, 1.340.0 MUST introduce `container@2`.
 *
 * This builder MUST NOT:
 *
 * - compile a real container;
 * - inspect the DI container;
 * - read service definitions;
 * - scan filesystem paths;
 * - resolve tags;
 * - instantiate services;
 * - infer aliases/services/tags from runtime state;
 * - assemble artifact envelopes directly.
 *
 * @internal
 */
final readonly class StubContainerBuilder
{
    public function __construct(
        private ArtifactEnvelopeFactory $envelopeFactory,
    ) {
    }

    /**
     * Builds a canonical `container@1` stub artifact envelope.
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(string $fingerprint): array
    {
        return $this->envelopeFactory->container(
            fingerprint: $fingerprint,
            payload: self::payload(),
        );
    }

    /**
     * @return array{
     *     aliases: array{},
     *     compiled: false,
     *     kind: 'stub',
     *     services: array{},
     *     tags: array{}
     * }
     */
    private static function payload(): array
    {
        return [
            'aliases' => [],
            'compiled' => false,
            'kind' => 'stub',
            'services' => [],
            'tags' => [],
        ];
    }
}
