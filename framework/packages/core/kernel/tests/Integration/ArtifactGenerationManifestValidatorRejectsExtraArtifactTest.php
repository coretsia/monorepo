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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationManifestValidator;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationManifestValidatorRejectsExtraArtifactTest extends TestCase
{
    public function testRejectsFourthArtifact(): void
    {
        $envelope = self::validEnvelope();
        $envelope['payload']['artifacts']['routes.php'] = [
            'bytes' => 401,
            'sha256' => \str_repeat('d', 64),
        ];

        self::assertRejected(
            envelope: $envelope,
            expectedReason: ArtifactInvalidException::REASON_SCHEMA_INVALID,
            forbiddenDiagnosticValue: 'routes.php',
        );
    }

    public function testRejectsMissingArtifact(): void
    {
        $envelope = self::validEnvelope();
        unset($envelope['payload']['artifacts']['config.php']);

        self::assertRejected(
            envelope: $envelope,
            expectedReason: ArtifactInvalidException::REASON_SCHEMA_INVALID,
        );
    }

    public function testRejectsAdditionalArtifactEntryKey(): void
    {
        $envelope = self::validEnvelope();
        $entry = $envelope['payload']['artifacts']['config.php'];
        $privatePath = '/private/coretsia/config.php';

        $envelope['payload']['artifacts']['config.php'] = [
            'bytes' => $entry['bytes'],
            'path' => $privatePath,
            'sha256' => $entry['sha256'],
        ];

        self::assertRejected(
            envelope: $envelope,
            expectedReason: ArtifactInvalidException::REASON_SCHEMA_INVALID,
            forbiddenDiagnosticValue: $privatePath,
        );
    }

    public function testRejectsRequiresHeader(): void
    {
        $envelope = self::validEnvelope();
        $header = $envelope['_meta'];

        $envelope['_meta'] = [
            'fingerprint' => $header['fingerprint'],
            'generator' => $header['generator'],
            'name' => $header['name'],
            'requires' => [
                'runtime' => 'php84',
            ],
            'schemaVersion' => $header['schemaVersion'],
        ];

        self::assertRejected(
            envelope: $envelope,
            expectedReason: ArtifactInvalidException::REASON_HEADER_INVALID,
        );
    }

    /**
     * @return array{
     *     _meta: array<string, mixed>,
     *     payload: array<string, mixed>
     * }
     */
    private static function validEnvelope(): array
    {
        $fingerprint = \str_repeat('a', 64);

        return new ArtifactEnvelopeFactory(
            new PayloadNormalizer(),
        )->artifactGeneration(
            fingerprint: $fingerprint,
            payload: [
                'artifacts' => [
                    'config.php' => [
                        'bytes' => 101,
                        'sha256' => \str_repeat('b', 64),
                    ],
                    'container.php' => [
                        'bytes' => 201,
                        'sha256' => \str_repeat('c', 64),
                    ],
                    'module-manifest.php' => [
                        'bytes' => 301,
                        'sha256' => \str_repeat('d', 64),
                    ],
                ],
                'generationId' => $fingerprint,
                'schemaVersion' => 1,
            ],
        );
    }

    /**
     * @param array<int|string, mixed> $envelope
     */
    private static function assertRejected(
        array $envelope,
        string $expectedReason,
        ?string $forbiddenDiagnosticValue = null,
    ): void {
        try {
            new ArtifactGenerationManifestValidator()->validate(
                $envelope,
            );
        } catch (ArtifactInvalidException $exception) {
            self::assertSame(
                ArtifactInvalidException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                $expectedReason,
                $exception->reason(),
            );
            self::assertSame(
                ArtifactInvalidException::ERROR_CODE
                . ': '
                . $expectedReason,
                $exception->getMessage(),
            );

            if ($forbiddenDiagnosticValue !== null) {
                self::assertStringNotContainsString(
                    $forbiddenDiagnosticValue,
                    $exception->getMessage(),
                );
            }

            return;
        }

        self::fail(
            'Artifact generation manifest validator must reject '
            . 'the invalid envelope.',
        );
    }
}
