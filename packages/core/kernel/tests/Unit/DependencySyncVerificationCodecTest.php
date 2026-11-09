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

    public function testBootstrapIdentityRoundTripsWithUnchangedVersionedEnvelopeStructure(): void
    {
        $plan = self::plan();
        $candidateIdentity = 'sha256:' . \str_repeat('1', 64);
        $lockIdentity = 'sha256:' . \str_repeat('2', 64);
        $payload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload($plan, $candidateIdentity);
        $planning = \json_decode($payload, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(1, $planning['schemaVersion']);
        self::assertSame($plan->bootstrapInstallationIdentity(), $planning['bootstrapInstallationIdentity']);
        self::assertSame(
            [
                'schemaVersion',
                'applications',
                'effectivePresetsByTarget',
                'fixedPresetByTarget',
                'enabledModuleIdsByTarget',
                'excludedModuleIdsByTarget',
                'unionModuleIds',
                'expectedModuleMetadataById',
                'desiredRootRequirements',
                'bootstrapInstallationIdentity',
                'composerJsonCandidateIdentity',
            ],
            \array_keys($planning),
        );
        self::assertStringNotContainsString('CORETSIA_BOOT_', $payload);
        self::assertStringNotContainsString('sourceProvenance', $payload);
        self::assertStringNotContainsString('artifactsCacheDir', $payload);
        self::assertStringNotContainsString('debug', $payload);

        $envelope = DependencySyncVerificationCodec::encodeEnvelope($payload, [], $lockIdentity);
        $outer = \json_decode($envelope, true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $outer['schemaVersion']);
        self::assertSame(
            [
                'schemaVersion',
                'approvedPlanningPayload',
                'approvedPlanningPayloadIdentity',
                'protectedThirdPartyRootState',
                'composerLockIdentity',
            ],
            \array_keys($outer),
        );
        self::assertArrayNotHasKey('bootstrapInstallationIdentity', $outer);

        $decoded = DependencySyncVerificationCodec::decodeEnvelope($envelope);

        self::assertSame($plan->bootstrapInstallationIdentity(), $decoded['plan']->bootstrapInstallationIdentity());
        self::assertSame(
            $envelope,
            DependencySyncVerificationCodec::encodeEnvelope(
                DependencySyncVerificationCodec::encodeApprovedPlanningPayload($decoded['plan'], $candidateIdentity),
                [],
                $lockIdentity,
            ),
        );
    }

    public function testRejectsTamperedCanonicalBootstrapIdentityEvenWithRecomputedEnvelopeDigest(): void
    {
        $plan = self::plan();
        $candidateIdentity = 'sha256:' . \str_repeat('1', 64);
        $lockIdentity = 'sha256:' . \str_repeat('2', 64);
        $planning = \json_decode(
            DependencySyncVerificationCodec::encodeApprovedPlanningPayload($plan, $candidateIdentity),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        $mutations = [];

        $mutations[] = static function (array &$payload): void {
            unset($payload['bootstrapInstallationIdentity']);
        };
        $mutations[] = static function (array &$payload): void {
            $payload['bootstrapInstallationIdentity']['presets']['web'] = 'enterprise';
        };
        $mutations[] = static function (array &$payload): void {
            $payload['bootstrapInstallationIdentity']['debug'] = true;
        };
        $mutations[] = static function (array &$payload): void {
            $payload['bootstrapInstallationIdentity']['moduleOverrides']['worker'] = [
                'include' => ['platform.worker'],
                'exclude' => [],
            ];
        };
        $mutations[] = static function (array &$payload): void {
            $payload['schemaVersion'] = 2;
        };

        foreach ($mutations as $mutate) {
            $changed = $planning;
            $mutate($changed);
            $candidatePayload = \json_encode($changed, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
            $envelope = DependencySyncVerificationCodec::encodeEnvelope($candidatePayload, [], $lockIdentity);

            try {
                DependencySyncVerificationCodec::decodeEnvelope($envelope);
                self::fail('Tampered approved planning payload must be rejected.');
            } catch (DependencySyncException $exception) {
                self::assertSame(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID, $exception->errorCode());
            }
        }
    }

    public function testLegacyVersionOnePlanningPayloadWithoutBootstrapIdentityIsRejected(): void
    {
        $plan = self::plan();
        $composerJsonIdentity = 'sha256:' . \str_repeat('1', 64);
        $composerLockIdentity = 'sha256:' . \str_repeat('2', 64);
        $currentPayload = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $plan,
            $composerJsonIdentity,
        );
        $legacyPlanning = \json_decode($currentPayload, true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame(1, $legacyPlanning['schemaVersion']);
        unset($legacyPlanning['bootstrapInstallationIdentity']);

        $legacyPayload = \json_encode(
            $legacyPlanning,
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE,
        );
        $legacyEnvelope = DependencySyncVerificationCodec::encodeEnvelope(
            $legacyPayload,
            [],
            $composerLockIdentity,
        );

        self::assertStringNotContainsString('bootstrapInstallationIdentity', $legacyPayload);

        try {
            DependencySyncVerificationCodec::decodeEnvelope($legacyEnvelope);
            self::fail('Legacy version-one planning payload without bootstrap identity must fail closed.');
        } catch (DependencySyncException $exception) {
            self::assertSame(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID, $exception->errorCode());
        }

        $currentEnvelope = DependencySyncVerificationCodec::encodeEnvelope(
            $currentPayload,
            [],
            $composerLockIdentity,
        );
        $decoded = DependencySyncVerificationCodec::decodeEnvelope($currentEnvelope);
        self::assertSame($plan->bootstrapInstallationIdentity(), $decoded['plan']->bootstrapInstallationIdentity());
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
            bootstrapInstallationIdentity: [
                'appEnv' => 'local',
                'envSourcePolicy' => 'strict_dotenv',
                'presets' => ['web' => 'micro'],
                'moduleOverrides' => [],
            ],
        );
    }
}
