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

namespace Coretsia\Platform\Redaction\Tests\Unit;

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class DefaultSensitiveDataRedactorFailureStageMappingTest extends TestCase
{
    public function testEveryExplicitStageHasItsExactReasonAndNoPartialResult(): void
    {
        $cases = [
            'unsupported-input-type' => [1.5, RedactionException::REASON_INPUT_INVALID],
            'invalid-branch-encoding' => [
                ['headers' => ['data' => "synthetic\xFFraw"]],
                RedactionException::REASON_INPUT_INVALID,
            ],
            'input-string-limit' => [
                ['password' => \str_repeat('S', 65_537)],
                RedactionException::REASON_INPUT_LIMIT_EXCEEDED,
            ],
            'sensitive-map-key' => [
                ['Authorization: Digest opaque' => 'synthetic-private'],
                RedactionException::REASON_SENSITIVE_MAP_KEY,
            ],
            'output-expansion' => [
                \array_fill(0, 1_500, 'Bearer synthetic-token-value'),
                RedactionException::REASON_OUTPUT_INVALID,
            ],
        ];

        foreach ($cases as $name => [$input, $expectedReason]) {
            $context = new RedactionContext(
                'redaction.failure-stage',
                $name === 'invalid-branch-encoding' ? RedactionMode::Hash : RedactionMode::Placeholder,
            );
            try {
                self::redactor()->redactJsonLike($input, $context);
                self::fail('Expected exact failure stage mapping: ' . $name);
            } catch (RedactionException $exception) {
                self::assertSame($expectedReason, $exception->reason());
                self::assertSame('CORETSIA_REDACTION_FAILED: ' . $expectedReason, $exception->getMessage());
                self::assertSame(0, $exception->getCode());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('synthetic-private', $exception->getMessage());
                self::assertStringNotContainsString('Authorization: Digest opaque', $exception->getMessage());
            }
        }
    }

    public function testUnexpectedRuntimeThrowableMapsOnlyToSafeInternalFailure(): void
    {
        // Skip constructor deliberately to simulate an unexpected broken implementation state.
        $reflection = new \ReflectionClass(DefaultSensitiveDataRedactor::class);
        $uninitialized = $reflection->newInstanceWithoutConstructor();
        $context = new RedactionContext('redaction.failure-stage', RedactionMode::Hash);

        try {
            $uninitialized->redactValue('synthetic-private', RedactionKind::Secret, $context);
            self::fail('Unexpected Throwable must not escape the redaction boundary.');
        } catch (RedactionException $exception) {
            self::assertSame(RedactionException::REASON_INTERNAL_FAILURE, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: internal-failure', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }

        try {
            $uninitialized->redactJsonLike('Bearer synthetic-token-value', $context);
            self::fail('Unexpected recursive Throwable must not escape.');
        } catch (RedactionException $exception) {
            self::assertSame(RedactionException::REASON_INTERNAL_FAILURE, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: internal-failure', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    private static function redactor(): DefaultSensitiveDataRedactor
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }
}
