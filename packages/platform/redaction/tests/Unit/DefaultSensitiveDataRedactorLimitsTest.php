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

final class DefaultSensitiveDataRedactorLimitsTest extends TestCase
{
    public function testDepthBoundaryIsExactly32Containers(): void
    {
        $context = new RedactionContext('redaction.limits');
        $within = self::nested(32);
        $over = self::nested(33);

        self::assertSame($within, self::redactor()->redactJsonLike($within, $context));
        self::assertRejected($over, RedactionException::REASON_INPUT_LIMIT_EXCEEDED);
    }

    public function testNodeBoundaryIsExactly10000Children(): void
    {
        $within = \array_fill(0, 10_000, 1);

        self::assertSame($within, self::redactor()->redactJsonLike($within, new RedactionContext('redaction.limits')));
        self::assertRejected(\array_fill(0, 10_001, 1), RedactionException::REASON_INPUT_LIMIT_EXCEEDED);
    }

    public function testIndividualStringByteBoundaryIsExactly65536ForDirectAndRecursive(): void
    {
        $context = new RedactionContext('redaction.limits', RedactionMode::Length);
        $within = \str_repeat('S', 65_536);
        $over = $within . 'X';

        self::assertSame(65_536, self::redactor()->redactValue($within, RedactionKind::Secret, $context)->length());
        try {
            self::redactor()->redactValue($over, RedactionKind::Secret, $context);
            self::fail('Foundation must enforce direct string limit.');
        } catch (RedactionException $exception) {
            self::assertSame(RedactionException::REASON_INPUT_LIMIT_EXCEEDED, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: input-limit-exceeded', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString($over, $exception->getMessage());
        }
        self::assertRejected(['status' => $over], RedactionException::REASON_INPUT_LIMIT_EXCEEDED);
    }

    public function testAggregateStringBudgetCountsExactly1048576InputBytes(): void
    {
        $within = \array_fill(0, 16, \str_repeat('S', 65_536));
        $over = \array_fill(0, 17, \str_repeat('S', 65_536));

        self::assertSame($within, self::redactor()->redactJsonLike($within, new RedactionContext('redaction.limits')));
        self::assertRejected($over, RedactionException::REASON_INPUT_LIMIT_EXCEEDED);

        // Map structural keys also participate in the same Foundation aggregate byte budget.
        $keysAndValues = [];
        for ($i = 0; $i < 17; $i++) {
            $keysAndValues['field' . $i] = \str_repeat('S', 65_536);
        }
        self::assertRejected($keysAndValues, RedactionException::REASON_INPUT_LIMIT_EXCEEDED);
    }

    public function testSensitiveInputExpansionBeyondOutputNodeBudgetMapsToOutputInvalid(): void
    {
        // 1,500 safe input list items become 1,500 canonical six-field summaries.
        $input = \array_fill(0, 1_500, 'Bearer synthetic-token-value');
        self::assertRejected($input, RedactionException::REASON_OUTPUT_INVALID);
    }

    public function testResourceLimitsAreFixedFoundationOwnedAndNotConstructorParameters(): void
    {
        $reflection = new \ReflectionClass(DefaultSensitiveDataRedactor::class);
        foreach (
            [
                'MAX_DEPTH' => 32,
                'MAX_NODES' => 10_000,
                'MAX_STRING_BYTES' => 65_536,
                'MAX_TOTAL_STRING_BYTES' => 1_048_576,
            ] as $constantName => $expected
        ) {
            $constant = $reflection->getReflectionConstant($constantName);
            self::assertNotFalse($constant);
            self::assertTrue($constant->isPrivate());
            self::assertSame($expected, $constant->getValue());
        }
        self::assertSame(3, $reflection->getConstructor()->getNumberOfParameters());

        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('JsonLikeNormalizer::normalize(', $source);
        self::assertStringNotContainsString('\\strlen($value) >', $source);
        self::assertStringNotContainsString('if (\\strlen($value)', $source);
    }

    private static function nested(int $depth): array
    {
        $value = 1;
        for ($index = 0; $index < $depth; $index++) {
            $value = [$value];
        }

        return $value;
    }

    private static function assertRejected(mixed $input, string $reason): void
    {
        try {
            self::redactor()->redactJsonLike($input, new RedactionContext('redaction.limits'));
            self::fail('Expected a fail-closed resource budget violation.');
        } catch (RedactionException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame(0, $exception->getCode());
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
