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

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultSensitiveDataRedactorValueTest extends TestCase
{
    #[DataProvider('allKinds')]
    public function testExplicitRedactionWorksForEveryKindEvenWithoutHeuristicMatch(RedactionKind $kind): void
    {
        $rawValue = 'Basic configuration synthetic-owner-selected';
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.value');
        $redacted = $redactor->redactValue($rawValue, $kind, $context);

        self::assertSame($kind, $redacted->kind());
        self::assertSame(
            [
                'hash' => null,
                'kind' => $kind->value,
                'length' => null,
                'mode' => 'placeholder',
                'redacted' => true,
                'schemaVersion' => 1,
            ],
            $redacted->toArray(),
        );
        self::assertFalse(\property_exists($redacted, 'value'));
        self::assertFalse(\property_exists($redacted, 'rawValue'));
        self::assertStringNotContainsString($rawValue, \serialize($redacted));
        self::assertStringNotContainsString($rawValue, \json_encode($redacted->toArray(), \JSON_THROW_ON_ERROR));
    }

    public function testExplicitStringSummaryUsesOriginalBytesAndRetainsNoRawInput(): void
    {
        $raw = "Synthetic\x00bytes\xFF";
        $context = new RedactionContext('redaction.value', RedactionMode::HashAndLength);
        $result = self::redactor()->redactValue($raw, RedactionKind::Unknown, $context);
        $expected = 'sha256:' . \hash(
            'sha256',
            "coretsia.redaction@1\0redaction.value\0unknown\0string\0" . $raw,
        );

        self::assertSame(\strlen($raw), $result->length());
        self::assertSame($expected, $result->hash());
        self::assertSame('unknown', $result->toArray()['kind']);
        self::assertStringNotContainsString($raw, \serialize($result));
    }

    /**
     * @return array<string, array{RedactionKind}>
     */
    public static function allKinds(): array
    {
        $cases = [];

        foreach (RedactionKind::cases() as $kind) {
            $cases[$kind->value] = [$kind];
        }

        return $cases;
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
