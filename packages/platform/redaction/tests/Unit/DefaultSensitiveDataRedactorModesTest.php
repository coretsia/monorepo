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
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultSensitiveDataRedactorModesTest extends TestCase
{
    #[DataProvider('allModes')]
    public function testAllFourModesUseExactShapeAndSameModeAcrossAllSelectedKinds(RedactionMode $mode): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.modes', $mode);
        $input = ['password' => 'private bytes', 'headers' => ['a' => 1], 'items' => ['Bearer synthetic-token']];
        $output = $redactor->redactJsonLike($input, $context);

        foreach ([$output['password'], $output['headers'], $output['items'][0]] as $summary) {
            self::assertSame(['hash', 'kind', 'length', 'mode', 'redacted', 'schemaVersion'], \array_keys($summary));
            self::assertSame($mode->value, $summary['mode']);
            self::assertTrue($summary['redacted']);
            self::assertSame(1, $summary['schemaVersion']);
            self::assertSame(
                \in_array($mode, [RedactionMode::Length, RedactionMode::HashAndLength], true),
                $summary['length'] !== null,
            );
            self::assertSame(
                \in_array($mode, [RedactionMode::Hash, RedactionMode::HashAndLength], true),
                $summary['hash'] !== null,
            );
        }

        self::assertSame('secret', $output['password']['kind']);
        self::assertSame('payload', $output['headers']['kind']);
        self::assertSame('authorization', $output['items'][0]['kind']);

        foreach (
            [
                [$output['password'], 'secret', 'string', 'private bytes'],
                [$output['headers'], 'payload', 'json-like', '{"a":1}'],
                [$output['items'][0], 'authorization', 'string', 'Bearer synthetic-token'],
            ] as [$summary, $kind, $representation, $bytes]
        ) {
            $hasLength = \in_array($mode, [RedactionMode::Length, RedactionMode::HashAndLength], true);
            $hasHash = \in_array($mode, [RedactionMode::Hash, RedactionMode::HashAndLength], true);

            self::assertSame($hasLength ? \strlen($bytes) : null, $summary['length']);
            self::assertSame(
                $hasHash
                    ? 'sha256:' . \hash(
                        'sha256',
                        "coretsia.redaction@1\0redaction.modes\0" . $kind . "\0" . $representation . "\0" . $bytes,
                    )
                    : null,
                $summary['hash'],
            );
        }
    }

    public function testPlaceholderNeverEncodesOrHashesMalformedUtf8NonStringBranch(): void
    {
        $invalidUtf8 = "synthetic\xFFprivate";
        $value = ['headers' => ['opaque' => $invalidUtf8, 'status' => 1]];
        $result = self::redactor()->redactJsonLike($value, new RedactionContext('redaction.modes'));

        self::assertSame('placeholder', $result['headers']['mode']);
        self::assertNull($result['headers']['length']);
        self::assertNull($result['headers']['hash']);
        self::assertSame(
            ['hash', 'kind', 'length', 'mode', 'redacted', 'schemaVersion'],
            \array_keys($result['headers'])
        );

        foreach ([RedactionMode::Length, RedactionMode::Hash, RedactionMode::HashAndLength] as $mode) {
            try {
                self::redactor()->redactJsonLike($value, new RedactionContext('redaction.modes', $mode));
                self::fail('Canonical JSON materialization must fail on malformed UTF-8.');
            } catch (RedactionException $exception) {
                self::assertSame(RedactionException::REASON_INPUT_INVALID, $exception->reason());
                self::assertSame('CORETSIA_REDACTION_FAILED: input-invalid', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testHashAndLengthReuseCanonicalBytesButDiscriminateStringFromNonString(): void
    {
        $context = new RedactionContext('redaction.modes', RedactionMode::HashAndLength);
        $output = self::redactor()->redactJsonLike(
            ['password' => 'null', 'secret' => null],
            $context,
        );
        $text = $output['password'];
        $scalar = $output['secret'];

        self::assertSame(4, $text['length']);
        self::assertSame(4, $scalar['length']);
        self::assertSame(
            'sha256:' . \hash('sha256', "coretsia.redaction@1\0redaction.modes\0secret\0string\0null"),
            $text['hash'],
        );
        self::assertSame(
            'sha256:' . \hash('sha256', "coretsia.redaction@1\0redaction.modes\0secret\0json-like\0null"),
            $scalar['hash'],
        );
        self::assertNotSame($text['hash'], $scalar['hash']);
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

    private static function redactor(): DefaultSensitiveDataRedactor
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }
}
