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

final class DefaultSensitiveDataRedactorJsonLikeTraversalTest extends TestCase
{
    public function testKeyFirstReplacementDoesNotTraverseOrRetainTheClassifiedBranch(): void
    {
        $redactor = self::redactor();
        $branch = [
            'secret' => 'nested synthetic private content',
            'Bearer opaque' => ['raw' => 'synthetic'],
            'items' => ['token-looking content', 'Basic configuration'],
        ];
        $input = ['zeta' => 8, 'headers' => $branch, 'alpha' => 'ok'];
        $output = $redactor->redactJsonLike($input, new RedactionContext('redaction.traversal'));

        self::assertSame(['alpha', 'headers', 'zeta'], \array_keys($output));
        self::assertSame('ok', $output['alpha']);
        self::assertSame(8, $output['zeta']);
        self::assertSame(self::placeholder(RedactionKind::Payload), $output['headers']);
        self::assertStringNotContainsString('synthetic private content', \json_encode($output, \JSON_THROW_ON_ERROR));
        self::assertArrayNotHasKey('secret', $output['headers']);
    }

    public function testFoundationValidationCompletesBeforeAnyKeyFirstSemanticReplacement(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.traversal');

        foreach (
            [
                [['headers' => ['nested' => new \stdClass()]], RedactionException::REASON_INPUT_INVALID],
                [['password' => \str_repeat('S', 65_537)], RedactionException::REASON_INPUT_LIMIT_EXCEEDED],
                [['password' => \array_fill(0, 10_001, 1)], RedactionException::REASON_INPUT_LIMIT_EXCEEDED],
            ] as [$input, $expectedReason]
        ) {
            try {
                $redactor->redactJsonLike($input, $context);
                self::fail('Foundation must reject invalid input before redaction traversal.');
            } catch (RedactionException $exception) {
                self::assertSame($expectedReason, $exception->reason());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testNonStringSummaryUsesStableNormalizedJsonWithoutEncoderFinalLineFeed(): void
    {
        $context = new RedactionContext('redaction.traversal', RedactionMode::HashAndLength);
        $branch = ['zulu' => 2, 'alpha' => ['omega' => true, 'beta' => 'Basic plan']];
        $bytes = '{"alpha":{"beta":"Basic plan","omega":true},"zulu":2}';
        $output = self::redactor()->redactJsonLike(['request_payload' => $branch], $context);
        $summary = $output['request_payload'];

        self::assertSame('payload', $summary['kind']);
        self::assertSame(\strlen($bytes), $summary['length']);
        self::assertSame(
            'sha256:' . \hash('sha256', "coretsia.redaction@1\0redaction.traversal\0payload\0json-like\0" . $bytes),
            $summary['hash'],
        );
        self::assertSame(
            $summary,
            self::redactor()->redactJsonLike(
                ['request_payload' => ['alpha' => ['beta' => 'Basic plan', 'omega' => true], 'zulu' => 2]],
                $context,
            )['request_payload'],
        );
    }

    public function testRecursiveValueClassificationAndListOrderingPreserveSafeScalars(): void
    {
        $input = [
            'zeta' => ['items' => ['success', 'Bearer synthetic-token', 42, true, null, 'handled-error']],
            'alpha' => ['status' => 'Basic plan', 'cookie' => 'synthetic raw cookie'],
        ];
        $output = self::redactor()->redactJsonLike($input, new RedactionContext('redaction.traversal'));

        self::assertSame(['alpha', 'zeta'], \array_keys($output));
        self::assertSame(['cookie', 'status'], \array_keys($output['alpha']));
        self::assertSame(self::placeholder(RedactionKind::Cookie), $output['alpha']['cookie']);
        self::assertSame('Basic plan', $output['alpha']['status']);
        self::assertSame('success', $output['zeta']['items'][0]);
        self::assertSame(self::placeholder(RedactionKind::Authorization), $output['zeta']['items'][1]);
        self::assertSame(42, $output['zeta']['items'][2]);
        self::assertTrue($output['zeta']['items'][3]);
        self::assertNull($output['zeta']['items'][4]);
        self::assertSame('handled-error', $output['zeta']['items'][5]);
    }

    public function testOpaqueEncodedStringsAreNeverParsedOrDecoded(): void
    {
        $opaque = [
            'json_text' => '{"password":"synthetic-secret"}',
            'base64_text' => 'QmVhcmVyIHN5bnRoZXRpYy10b2tlbg==',
            'sql_text' => 'SELECT password FROM accounts',
            'url_text' => 'https://example.test/?secret=synthetic',
            'provider_text' => '{"Authorization":"Bearer opaque"}',
        ];
        $result = self::redactor()->redactJsonLike($opaque, new RedactionContext('redaction.traversal'));
        $expected = $opaque;
        \uksort($expected, static fn (string $a, string $b): int => \strcmp($a, $b));

        self::assertSame($expected, $result);
        self::assertSame(
            self::placeholder(RedactionKind::Sql),
            self::redactor()->redactJsonLike(
                ['sql_query' => $opaque['sql_text']],
                new RedactionContext('redaction.traversal'),
            )['sql_query'],
        );
        self::assertSame(
            self::placeholder(RedactionKind::Token),
            self::redactor()->redactJsonLike(
                'eyJinvalid.payload.signature',
                new RedactionContext('redaction.traversal'),
            ),
        );
    }

    /**
     * @return array{hash: null, kind: string, length: null, mode: 'placeholder', redacted: true, schemaVersion: 1}
     */
    private static function placeholder(RedactionKind $kind): array
    {
        return [
            'hash' => null,
            'kind' => $kind->value,
            'length' => null,
            'mode' => 'placeholder',
            'redacted' => true,
            'schemaVersion' => 1,
        ];
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
