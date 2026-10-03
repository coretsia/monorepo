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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;
use PHPUnit\Framework\TestCase;

final class DependencySyncVerificationCodecTest extends TestCase
{
    public function testEnvelopeRoundTripsCanonicalPlanningPayload(): void
    {
        $plan = self::plan();
        $composerJsonIdentity = 'sha256:' . \str_repeat('1', 64);
        $composerLockIdentity = 'sha256:' . \str_repeat('2', 64);
        $planningPayload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $plan,
            $composerJsonIdentity,
        );
        $envelope = DependencySyncVerificationCodec::encodeEnvelope(
            $planningPayload,
            [],
            $composerLockIdentity,
        );
        $decoded = DependencySyncVerificationCodec::decodeEnvelope($envelope);

        self::assertTrue($plan->equals($decoded['plan']));
        self::assertSame(
            'sha256:' . \str_repeat('1', 64),
            $decoded['composerJsonCandidateIdentity'],
        );
        self::assertSame(
            'sha256:' . \str_repeat('2', 64),
            $decoded['composerLockIdentity'],
        );
        self::assertSame(
            $planningPayload,
            DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
                $decoded['plan'],
                $decoded['composerJsonCandidateIdentity'],
            ),
        );
    }

    public function testRejectsMalformedNonCanonicalAndUnsupportedSchemaInput(): void
    {
        $plan = self::plan();
        $composerJsonIdentity = 'sha256:' . \str_repeat('1', 64);
        $composerLockIdentity = 'sha256:' . \str_repeat('2', 64);
        $planningPayload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $plan,
            $composerJsonIdentity,
        );
        $envelope = DependencySyncVerificationCodec::encodeEnvelope(
            $planningPayload,
            [],
            $composerLockIdentity,
        );

        $outerCount = 0;
        $unsupportedEnvelopeSchema = \str_replace(
            '"schemaVersion":1',
            '"schemaVersion":99',
            $envelope,
            $outerCount,
        );

        self::assertSame(1, $outerCount);

        $planningCount = 0;
        $unsupportedPlanningPayload = \str_replace(
            '"schemaVersion":1',
            '"schemaVersion":99',
            $planningPayload,
            $planningCount,
        );

        self::assertSame(1, $planningCount);

        $unsupportedPlanningSchema = DependencySyncVerificationCodec::encodeEnvelope(
            $unsupportedPlanningPayload,
            [],
            $composerLockIdentity,
        );

        foreach (
            [
                '',
                '{}',
                " {\"schemaVersion\":1}\n",
                $unsupportedEnvelopeSchema,
                $unsupportedPlanningSchema,
            ] as $input
        ) {
            try {
                DependencySyncVerificationCodec::decodeEnvelope($input);
                self::fail('Expected invalid verification input.');
            } catch (DependencySyncException $exception) {
                self::assertSame(
                    DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID,
                    $exception->errorCode(),
                );
            }
        }
    }

    private static function plan(): ProjectPackagePlan
    {
        return new ProjectPackagePlan(
            applications: ['web'],
            effectivePresetsByTarget: ['web' => 'micro'],
            fixedPresetByTarget: [],
            enabledModuleIdsByTarget: [
                'web' => ['core.foundation', 'core.kernel'],
            ],
            excludedModuleIdsByTarget: ['web' => []],
            unionModuleIds: ['core.foundation', 'core.kernel'],
            expectedModuleMetadataById: [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'requires' => [],
                    'conflicts' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'requires' => ['core.foundation'],
                    'conflicts' => [],
                ],
            ],
            desiredRootRequirements: [
                'coretsia/core-foundation' => '^0.7.0',
                'coretsia/core-kernel' => '^0.7.0',
            ],
        );
    }
}
