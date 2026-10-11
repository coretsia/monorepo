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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedactionOutputIsDeterministicContractTest extends TestCase
{
    #[DataProvider('allModes')]
    public function testRepeatedOperationsReturnIdenticalNormalizedShapesAndCanonicalOrder(RedactionMode $mode): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.determinism', $mode);
        $input = [
            'zeta' => [
                'zulu' => 'Basic plan',
                'password' => 'synthetic-private-value',
                'alpha' => 7,
            ],
            'items' => [
                'success',
                'Bearer synthetic-token-value',
                42,
                'handled-error',
            ],
            'alpha' => true,
        ];

        $first = $redactor->redactJsonLike($input, $context);
        self::assertSame($first, $redactor->redactJsonLike($input, $context));
        self::assertSame(['alpha', 'items', 'zeta'], \array_keys($first));
        self::assertSame(['alpha', 'password', 'zulu'], \array_keys($first['zeta']));
        self::assertSame('success', $first['items'][0]);
        self::assertSame('handled-error', $first['items'][3]);
        self::assertSame(42, $first['items'][2]);
        self::assertSame('authorization', $first['items'][1]['kind']);
        self::assertSame('secret', $first['zeta']['password']['kind']);
        self::assertSame($mode->value, $first['items'][1]['mode']);
        self::assertSame($mode->value, $first['zeta']['password']['mode']);
    }

    #[DataProvider('allModes')]
    public function testEachDisclosureModeHasExactlyItsSelectedMetadata(RedactionMode $mode): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.determinism', $mode);
        $bytes = 'synthetic-secret-bytes';
        $summary = $redactor->redactValue($bytes, RedactionKind::Secret, $context)->toArray();

        self::assertSame(['hash', 'kind', 'length', 'mode', 'redacted', 'schemaVersion'], \array_keys($summary));
        self::assertSame('secret', $summary['kind']);
        self::assertSame($mode->value, $summary['mode']);
        self::assertTrue($summary['redacted']);
        self::assertSame(1, $summary['schemaVersion']);
        self::assertSame(
            \in_array($mode, [RedactionMode::Length, RedactionMode::HashAndLength], true) ? \strlen($bytes) : null,
            $summary['length'],
        );
        self::assertSame(
            \in_array($mode, [RedactionMode::Hash, RedactionMode::HashAndLength], true)
                ? self::expectedHash($bytes, RedactionKind::Secret, $context, 'string')
                : null,
            $summary['hash'],
        );
    }

    public function testHashDomainSeparatesScopeKindAndRepresentation(): void
    {
        $redactor = self::redactor();
        $bytes = 'null';
        $first = new RedactionContext('redaction.one', RedactionMode::Hash);
        $second = new RedactionContext('redaction.two', RedactionMode::Hash);

        $hash = $redactor->redactValue($bytes, RedactionKind::Secret, $first)->hash();
        $changedScope = $redactor->redactValue($bytes, RedactionKind::Secret, $second)->hash();
        $changedKind = $redactor->redactValue($bytes, RedactionKind::Token, $first)->hash();
        $stringBranch = $redactor->redactJsonLike(['password' => 'null'], $first)['password']['hash'];
        $nonStringBranch = $redactor->redactJsonLike(['password' => null], $first)['password']['hash'];

        self::assertSame(self::expectedHash($bytes, RedactionKind::Secret, $first, 'string'), $hash);
        self::assertSame(self::expectedHash($bytes, RedactionKind::Secret, $second, 'string'), $changedScope);
        self::assertSame(self::expectedHash($bytes, RedactionKind::Token, $first, 'string'), $changedKind);
        self::assertSame(self::expectedHash($bytes, RedactionKind::Secret, $first, 'string'), $stringBranch);
        self::assertSame(self::expectedHash($bytes, RedactionKind::Secret, $first, 'json-like'), $nonStringBranch);
        self::assertSame($hash, $redactor->redactValue($bytes, RedactionKind::Secret, $first)->hash());
        self::assertNotSame($hash, $changedScope);
        self::assertNotSame($hash, $changedKind);
        self::assertNotSame($stringBranch, $nonStringBranch);
        self::assertMatchesRegularExpression('/\Asha256:[a-f0-9]{64}\z/', $hash);
    }

    public function testStructuralNonStringBranchLengthCountsCanonicalBytesNotHashDomain(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.determinism', RedactionMode::HashAndLength);
        $branch = ['zeta' => 4, 'alpha' => 'Basic plan'];
        $expectedBytes = '{"alpha":"Basic plan","zeta":4}';
        $summary = $redactor->redactJsonLike(['headers' => $branch], $context)['headers'];

        self::assertSame('payload', $summary['kind']);
        self::assertSame(\strlen($expectedBytes), $summary['length']);
        self::assertSame(
            self::expectedHash($expectedBytes, RedactionKind::Payload, $context, 'json-like'),
            $summary['hash'],
        );
        self::assertSame(
            $summary,
            $redactor->redactJsonLike(['headers' => ['alpha' => 'Basic plan', 'zeta' => 4]], $context)['headers'],
        );
    }

    /**
     * @return array<string, array{RedactionMode}>
     */
    public static function allModes(): array
    {
        return [
            'placeholder' => [RedactionMode::Placeholder],
            'length' => [RedactionMode::Length],
            'hash' => [RedactionMode::Hash],
            'hash-and-length' => [RedactionMode::HashAndLength],
        ];
    }

    private static function expectedHash(
        string $bytes,
        RedactionKind $kind,
        RedactionContext $context,
        string $representation,
    ): string {
        return 'sha256:' . \hash(
            'sha256',
            "coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $representation . "\0" . $bytes,
        );
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
