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

namespace Coretsia\Kernel\DependencySync\Verification;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;

/** @internal */
final class DependencySyncVerificationCodec
{
    private function __construct()
    {
    }

    public static function encodeApprovedPlanningPayload(
        ProjectPackagePlan $plan,
        string $composerJsonCandidateIdentity,
    ): string {
        self::assertIdentity($composerJsonCandidateIdentity);

        $payload = new \stdClass();
        $payload->schemaVersion = 1;
        $payload->applications = $plan->applications();
        $payload->effectivePresetsByTarget = self::stringMapObject(
            $plan->effectivePresetsByTarget(),
        );
        $payload->fixedPresetByTarget = self::stringMapObject(
            $plan->fixedPresetByTarget(),
        );
        $payload->enabledModuleIdsByTarget = self::listMapObject(
            $plan->enabledModuleIdsByTarget(),
        );
        $payload->excludedModuleIdsByTarget = self::listMapObject(
            $plan->excludedModuleIdsByTarget(),
        );
        $payload->unionModuleIds = $plan->unionModuleIds();
        $payload->expectedModuleMetadataById = self::metadataMapObject(
            $plan->expectedModuleMetadataById(),
        );
        $payload->desiredRootRequirements = self::stringMapObject(
            $plan->desiredRootRequirements(),
        );
        $payload->composerJsonCandidateIdentity = $composerJsonCandidateIdentity;

        return self::encodeCanonical($payload);
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $protectedThirdPartyRootState
     */
    public static function encodeEnvelope(
        string $approvedPlanningPayload,
        array $protectedThirdPartyRootState,
        string $composerLockIdentity,
    ): string {
        self::assertIdentity($composerLockIdentity);
        $state = self::validateProtectedState($protectedThirdPartyRootState);

        $envelope = new \stdClass();
        $envelope->schemaVersion = 1;
        $envelope->approvedPlanningPayload = $approvedPlanningPayload;
        $envelope->approvedPlanningPayloadIdentity = self::identity($approvedPlanningPayload);
        $envelope->protectedThirdPartyRootState = self::protectedStateObject($state);
        $envelope->composerLockIdentity = $composerLockIdentity;

        return self::encodeCanonical($envelope);
    }

    /**
     * @return array{
     *     plan: ProjectPackagePlan,
     *     composerJsonCandidateIdentity: non-empty-string,
     *     protectedThirdPartyRootState: array<string, array{
     *         runtimeRequired: bool,
     *         installed: bool,
     *         type: string|null,
     *         version: non-empty-string,
     *         sourceReference: non-empty-string|null,
     *         distReference: non-empty-string|null,
     *         installedReference: non-empty-string|null
     *     }>,
     *     composerLockIdentity: non-empty-string
     * }
     */
    public static function decodeEnvelope(string $input): array
    {
        if (\strlen($input) > ProjectDependencySync::MAX_VERIFICATION_INPUT_BYTES) {
            self::invalid();
        }

        try {
            $envelope = StableJsonDecoder::decodeStableMap($input);
            self::assertExactKeys(
                $envelope,
                [
                    'schemaVersion',
                    'approvedPlanningPayload',
                    'approvedPlanningPayloadIdentity',
                    'protectedThirdPartyRootState',
                    'composerLockIdentity',
                ],
            );

            if (
                $envelope['schemaVersion'] !== 1
                || !\is_string($envelope['approvedPlanningPayload'])
                || !\is_string($envelope['approvedPlanningPayloadIdentity'])
                || !\is_array($envelope['protectedThirdPartyRootState'])
                || !\is_string($envelope['composerLockIdentity'])
            ) {
                self::invalid();
            }

            $approvedPlanningPayload = $envelope['approvedPlanningPayload'];
            self::assertIdentity($envelope['approvedPlanningPayloadIdentity']);
            self::assertIdentity($envelope['composerLockIdentity']);

            if ($envelope['approvedPlanningPayloadIdentity'] !== self::identity($approvedPlanningPayload)) {
                self::invalid();
            }

            $planning = StableJsonDecoder::decodeStableMap($approvedPlanningPayload);
            self::assertExactKeys(
                $planning,
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
                    'composerJsonCandidateIdentity',
                ],
            );

            if (
                $planning['schemaVersion'] !== 1
                || !\is_array($planning['applications'])
                || !\is_array($planning['effectivePresetsByTarget'])
                || !\is_array($planning['fixedPresetByTarget'])
                || !\is_array($planning['enabledModuleIdsByTarget'])
                || !\is_array($planning['excludedModuleIdsByTarget'])
                || !\is_array($planning['unionModuleIds'])
                || !\is_array($planning['expectedModuleMetadataById'])
                || !\is_array($planning['desiredRootRequirements'])
                || !\is_string($planning['composerJsonCandidateIdentity'])
            ) {
                self::invalid();
            }

            self::assertIdentity($planning['composerJsonCandidateIdentity']);

            $plan = new ProjectPackagePlan(
                self::stringList($planning['applications']),
                self::stringMap($planning['effectivePresetsByTarget']),
                self::stringMap($planning['fixedPresetByTarget']),
                self::stringListMap($planning['enabledModuleIdsByTarget']),
                self::stringListMap($planning['excludedModuleIdsByTarget']),
                self::stringList($planning['unionModuleIds']),
                self::metadataMap($planning['expectedModuleMetadataById']),
                self::stringMap($planning['desiredRootRequirements']),
            );

            $protectedState = self::protectedStateFromDecoded(
                $envelope['protectedThirdPartyRootState'],
            );

            $canonicalPlanning = self::encodeApprovedPlanningPayload(
                $plan,
                $planning['composerJsonCandidateIdentity'],
            );

            if ($canonicalPlanning !== $approvedPlanningPayload) {
                self::invalid();
            }

            $canonicalEnvelope = self::encodeEnvelope(
                $canonicalPlanning,
                $protectedState,
                $envelope['composerLockIdentity'],
            );

            if ($canonicalEnvelope !== $input) {
                self::invalid();
            }

            return [
                'plan' => $plan,
                'composerJsonCandidateIdentity' => $planning['composerJsonCandidateIdentity'],
                'protectedThirdPartyRootState' => $protectedState,
                'composerLockIdentity' => $envelope['composerLockIdentity'],
            ];
        } catch (DependencySyncException $exception) {
            if ($exception->errorCode() === DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID) {
                throw $exception;
            }

            self::invalid();
        } catch (BootstrapException|\InvalidArgumentException) {
            self::invalid();
        }
    }

    private static function encodeCanonical(\stdClass $value): string
    {
        try {
            return \json_encode(
                $value,
                \JSON_THROW_ON_ERROR
                | \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE,
                512,
            );
        } catch (\JsonException) {
            self::invalid();
        }
    }

    /** @param array<string, non-empty-string> $map */
    private static function stringMapObject(array $map): \stdClass
    {
        $object = new \stdClass();

        foreach ($map as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }

    /** @param array<string, list<string>> $map */
    private static function listMapObject(array $map): \stdClass
    {
        $object = new \stdClass();

        foreach ($map as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }

    /** @param array<string, array{composerName: non-empty-string, requires: list<string>, conflicts: list<string>}> $map */
    private static function metadataMapObject(array $map): \stdClass
    {
        $object = new \stdClass();

        foreach ($map as $moduleId => $metadata) {
            $record = new \stdClass();
            $record->composerName = $metadata['composerName'];
            $record->requires = $metadata['requires'];
            $record->conflicts = $metadata['conflicts'];
            $object->{$moduleId} = $record;
        }

        return $object;
    }

    /**
     * @param array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }> $state
     */
    private static function protectedStateObject(array $state): \stdClass
    {
        $object = new \stdClass();

        foreach ($state as $packageName => $record) {
            $item = new \stdClass();
            $item->runtimeRequired = $record['runtimeRequired'];
            $item->installed = $record['installed'];
            $item->type = $record['type'];
            $item->version = $record['version'];
            $item->sourceReference = $record['sourceReference'];
            $item->distReference = $record['distReference'];
            $item->installedReference = $record['installedReference'];
            $object->{$packageName} = $item;
        }

        return $object;
    }

    /** @param array<string, mixed> $map @param list<string> $expected */
    private static function assertExactKeys(array $map, array $expected): void
    {
        $keys = \array_keys($map);
        \sort($keys, \SORT_STRING);
        \sort($expected, \SORT_STRING);

        if ($keys !== $expected) {
            self::invalid();
        }
    }

    /** @param array<mixed> $values @return list<string> */
    private static function stringList(array $values): array
    {
        if (!\array_is_list($values)) {
            self::invalid();
        }

        foreach ($values as $value) {
            if (!\is_string($value)) {
                self::invalid();
            }
        }

        /** @var list<string> $values */
        return $values;
    }

    /** @param array<string, mixed> $map @return array<string, non-empty-string> */
    private static function stringMap(array $map): array
    {
        $result = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key) || !\is_string($value) || $value === '') {
                self::invalid();
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /** @param array<string, mixed> $map @return array<string, list<string>> */
    private static function stringListMap(array $map): array
    {
        $result = [];

        foreach ($map as $key => $value) {
            if (!\is_string($key) || !\is_array($value)) {
                self::invalid();
            }

            $result[$key] = self::stringList($value);
        }

        return $result;
    }

    /** @param array<string, mixed> $map @return array<string, array{composerName: non-empty-string, requires: list<string>, conflicts: list<string>}> */
    private static function metadataMap(array $map): array
    {
        $result = [];

        foreach ($map as $moduleId => $record) {
            if (!\is_string($moduleId) || !\is_array($record)) {
                self::invalid();
            }

            self::assertExactKeys($record, ['composerName', 'requires', 'conflicts']);

            if (
                !\is_string($record['composerName'])
                || $record['composerName'] === ''
                || !\is_array($record['requires'])
                || !\is_array($record['conflicts'])
            ) {
                self::invalid();
            }

            $result[$moduleId] = [
                'composerName' => $record['composerName'],
                'requires' => self::stringList($record['requires']),
                'conflicts' => self::stringList($record['conflicts']),
            ];
        }

        return $result;
    }

    /** @param array<string, mixed> $decoded */
    private static function protectedStateFromDecoded(array $decoded): array
    {
        $state = [];

        foreach ($decoded as $packageName => $record) {
            if (!\is_string($packageName) || !\is_array($record)) {
                self::invalid();
            }

            self::assertExactKeys(
                $record,
                [
                    'runtimeRequired',
                    'installed',
                    'type',
                    'version',
                    'sourceReference',
                    'distReference',
                    'installedReference',
                ],
            );

            $state[$packageName] = $record;
        }

        return self::validateProtectedState($state);
    }

    /**
     * @param array<string, mixed> $state
     *
     * @return array<string, array{
     *     runtimeRequired: bool,
     *     installed: bool,
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     *     installedReference: non-empty-string|null
     * }>
     */
    private static function validateProtectedState(array $state): array
    {
        $keys = \array_keys($state);
        $sorted = $keys;
        \sort($sorted, \SORT_STRING);

        if ($keys !== $sorted) {
            self::invalid();
        }

        $result = [];

        foreach ($state as $packageName => $record) {
            if (!\is_string($packageName) || !\is_array($record)) {
                self::invalid();
            }

            try {
                new ModulePlanEntry(
                    ModuleId::fromString('core.kernel'),
                    $packageName,
                );
            } catch (\InvalidArgumentException) {
                self::invalid();
            }

            self::assertExactKeys(
                $record,
                [
                    'runtimeRequired',
                    'installed',
                    'type',
                    'version',
                    'sourceReference',
                    'distReference',
                    'installedReference',
                ],
            );

            if (
                !\is_bool($record['runtimeRequired'])
                || !\is_bool($record['installed'])
                || ($record['type'] !== null && !\is_string($record['type']))
                || !\is_string($record['version'])
                || !self::isSafeSingleLine($record['version'])
                || !self::isNullableSafeSingleLine($record['sourceReference'])
                || !self::isNullableSafeSingleLine($record['distReference'])
                || !self::isNullableSafeSingleLine($record['installedReference'])
            ) {
                self::invalid();
            }

            if (!$record['installed']) {
                if ($record['installedReference'] !== null) {
                    self::invalid();
                }
            } else {
                $references = [];

                foreach ([$record['sourceReference'], $record['distReference']] as $reference) {
                    if ($reference !== null) {
                        $references[$reference] = true;
                    }
                }

                if (
                    ($references === [] && $record['installedReference'] !== null)
                    || ($references !== [] && (
                        $record['installedReference'] === null
                            || !isset($references[$record['installedReference']])
                    ))
                ) {
                    self::invalid();
                }
            }

            /**
             * @var array{
             *     runtimeRequired: bool,
             *     installed: bool,
             *     type: string|null,
             *     version: non-empty-string,
             *     sourceReference: non-empty-string|null,
             *     distReference: non-empty-string|null,
             *     installedReference: non-empty-string|null,
             * } $record
             */
            $result[$packageName] = $record;
        }

        return $result;
    }

    private static function assertIdentity(string $identity): void
    {
        if (\preg_match('/\Asha256:[0-9a-f]{64}\z/D', $identity) !== 1) {
            self::invalid();
        }
    }

    private static function identity(string $bytes): string
    {
        return 'sha256:' . \hash('sha256', $bytes);
    }

    private static function isNullableSafeSingleLine(mixed $value): bool
    {
        return $value === null || (\is_string($value) && self::isSafeSingleLine($value));
    }

    private static function isSafeSingleLine(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && \preg_match('/[\x00-\x1F\x7F]/', $value) === 0;
    }

    private static function invalid(): never
    {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID);
    }
}
