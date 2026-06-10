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
use Coretsia\Kernel\Module\ModulePlan;

/**
 * Builds the Kernel-owned `module-manifest@1` artifact envelope.
 *
 * This builder is intentionally a thin adapter from the already resolved
 * ModulePlan to the canonical Kernel artifact envelope:
 *
 *     ModulePlan::toArray()
 *     -> ArtifactEnvelopeFactory::moduleManifest(...)
 *
 * It MUST NOT:
 *
 * - re-resolve modules;
 * - read Composer metadata;
 * - read preset/mode files;
 * - scan filesystem paths;
 * - infer package metadata;
 * - mutate ModulePlan data;
 * - rebuild the ModulePlan export shape manually.
 *
 * ModulePlan::toArray() is the canonical payload base. The existing
 * ModulePlan exported key order and schema semantics are preserved by passing
 * that payload through unchanged to the artifact envelope factory.
 *
 * @internal
 */
final readonly class ModuleManifestBuilder
{
    public function __construct(
        private ArtifactEnvelopeFactory $envelopeFactory,
    ) {
    }

    /**
     * Builds a canonical `module-manifest@1` artifact envelope.
     *
     * @return array{_meta: array<string, mixed>, payload: array<string, mixed>}
     *
     * @throws JsonFloatForbiddenException
     * @throws ArtifactPayloadInvalidException
     */
    public function build(
        ModulePlan $modulePlan,
        string $fingerprint,
    ): array {
        return $this->envelopeFactory->moduleManifest(
            fingerprint: $fingerprint,
            payload: $modulePlan->toArray(),
        );
    }
}
