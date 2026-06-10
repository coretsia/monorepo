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

use Coretsia\Kernel\Artifacts\Exception\ArtifactPayloadInvalidException;
use Coretsia\Kernel\Artifacts\Exception\JsonFloatForbiddenException;
use Coretsia\Kernel\Artifacts\PayloadNormalizer;
use PHPUnit\Framework\TestCase;

final class PayloadNormalizerRejectsUnsafeValuesTest extends TestCase
{
    public function testRejectsFloatsWithoutLeakingRawValue(): void
    {
        self::assertFloatRejected(
            value: 12.34,
            forbiddenNeedles: [
                '12.34',
            ],
        );
    }

    public function testRejectsNanInfAndNegativeInfWithoutLeakingRawValue(): void
    {
        self::assertFloatRejected(
            value: \NAN,
            forbiddenNeedles: [
                'NAN',
                'nan',
            ],
        );

        self::assertFloatRejected(
            value: \INF,
            forbiddenNeedles: [
                'INF',
                'inf',
            ],
        );

        self::assertFloatRejected(
            value: -\INF,
            forbiddenNeedles: [
                'INF',
                'inf',
                '-INF',
                '-inf',
            ],
        );
    }

    public function testRejectsObjectsWithoutLeakingClassNameOrRawProperties(): void
    {
        $object = new class() {
            public string $secret = 'raw-object-secret-value';
        };

        try {
            PayloadNormalizer::normalizePayload([
                'safe' => $object,
            ]);

            self::fail('Expected ArtifactPayloadInvalidException was not thrown.');
        } catch (ArtifactPayloadInvalidException $exception) {
            self::assertSame(ArtifactPayloadInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ArtifactPayloadInvalidException::REASON_OBJECT_FORBIDDEN, $exception->reason());
            self::assertSame('payload.safe', $exception->path());
            self::assertStringContainsString('payload.safe', $exception->getMessage());

            self::assertNoDiagnosticLeak($exception, [
                'raw-object-secret-value',
                $object::class,
                'class@anonymous',
            ]);
        }
    }

    public function testRejectsClosuresWithoutLeakingClosureDetails(): void
    {
        try {
            PayloadNormalizer::normalizePayload([
                'safe' => static fn (): string => 'raw-closure-secret-value',
            ]);

            self::fail('Expected ArtifactPayloadInvalidException was not thrown.');
        } catch (ArtifactPayloadInvalidException $exception) {
            self::assertSame(ArtifactPayloadInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ArtifactPayloadInvalidException::REASON_CLOSURE_FORBIDDEN, $exception->reason());
            self::assertSame('payload.safe', $exception->path());
            self::assertStringContainsString('payload.safe', $exception->getMessage());

            self::assertNoDiagnosticLeak($exception, [
                'raw-closure-secret-value',
                'Closure',
            ]);
        }
    }

    public function testRejectsResourcesWithoutLeakingResourceDetails(): void
    {
        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            PayloadNormalizer::normalizePayload([
                'safe' => $resource,
            ]);

            self::fail('Expected ArtifactPayloadInvalidException was not thrown.');
        } catch (ArtifactPayloadInvalidException $exception) {
            self::assertSame(ArtifactPayloadInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ArtifactPayloadInvalidException::REASON_RESOURCE_FORBIDDEN, $exception->reason());
            self::assertSame('payload.safe', $exception->path());
            self::assertStringContainsString('payload.safe', $exception->getMessage());

            self::assertNoDiagnosticLeak($exception, [
                'php://memory',
            ]);
        } finally {
            \fclose($resource);
        }
    }

    /**
     * @param list<string> $forbiddenNeedles
     */
    private static function assertFloatRejected(float $value, array $forbiddenNeedles): void
    {
        try {
            PayloadNormalizer::normalizePayload(
                [
                    'safe' => $value,
                ],
                'artifact',
            );

            self::fail('Expected JsonFloatForbiddenException was not thrown.');
        } catch (JsonFloatForbiddenException $exception) {
            self::assertSame(JsonFloatForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(JsonFloatForbiddenException::REASON_FLOAT_FORBIDDEN, $exception->reason());
            self::assertSame('artifact.safe', $exception->path());
            self::assertStringContainsString('artifact.safe', $exception->getMessage());

            self::assertNoDiagnosticLeak($exception, $forbiddenNeedles);
        }
    }

    /**
     * @param list<string> $forbiddenNeedles
     */
    private static function assertNoDiagnosticLeak(\Throwable $exception, array $forbiddenNeedles): void
    {
        foreach ($forbiddenNeedles as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $exception->getMessage(),
                'Artifact payload diagnostics must not leak rejected raw values or unsafe details.',
            );
        }
    }
}
