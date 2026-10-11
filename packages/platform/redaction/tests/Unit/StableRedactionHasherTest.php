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
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class StableRedactionHasherTest extends TestCase
{
    public function testExactFixedVectorsPinRepresentationDomainFraming(): void
    {
        $hasher = new StableRedactionHasher();
        $context = new RedactionContext('cli.output');

        $stringHash = $hasher->hashString('null', RedactionKind::Secret, $context);
        $jsonLikeHash = $hasher->hashJsonLike('null', RedactionKind::Secret, $context);

        self::assertSame('sha256:5766c080857ef006b1bd51448aed5fc55727f2e11216fa7961cc2b07fee19151', $stringHash);
        self::assertSame('sha256:7057aa75ab01ec4fb0e07ed6fc8ad0bde295c759a85919c960ef3b92fa28d567', $jsonLikeHash);
        self::assertNotSame($stringHash, $jsonLikeHash);
        self::assertSame(
            'sha256:' . \hash('sha256', "coretsia.redaction@1\0cli.output\0secret\0string\0null"),
            $stringHash,
        );
        self::assertSame(
            'sha256:' . \hash('sha256', "coretsia.redaction@1\0cli.output\0secret\0json-like\0null"),
            $jsonLikeHash,
        );
        // This fixed vector proves only the specified framing, not general SHA-256 collision freedom.
    }

    public function testExactBytePreimageIncludesScopeKindDomainAndUnmodifiedBytes(): void
    {
        $hasher = new StableRedactionHasher();
        $bytes = "  \x00synthetic\xFF\n";
        $scope = new RedactionContext('cli.output');
        $otherScope = new RedactionContext('logging.record');
        $expected = static fn (string $s, string $k, string $domain): string => 'sha256:' . \hash(
            'sha256',
            "coretsia.redaction@1\0" . $s . "\0" . $k . "\0" . $domain . "\0" . $bytes,
        );

        $direct = $hasher->hashString($bytes, RedactionKind::Secret, $scope);
        self::assertSame($expected('cli.output', 'secret', 'string'), $direct);
        self::assertSame($direct, $hasher->hashString($bytes, RedactionKind::Secret, $scope));
        self::assertSame(
            $expected('logging.record', 'secret', 'string'),
            $hasher->hashString($bytes, RedactionKind::Secret, $otherScope),
        );
        self::assertSame(
            $expected('cli.output', 'token', 'string'),
            $hasher->hashString($bytes, RedactionKind::Token, $scope),
        );
        self::assertSame(
            $expected('cli.output', 'secret', 'json-like'),
            $hasher->hashJsonLike($bytes, RedactionKind::Secret, $scope),
        );
        self::assertNotSame($direct, $hasher->hashString($bytes, RedactionKind::Token, $scope));
        self::assertNotSame($direct, $hasher->hashString($bytes, RedactionKind::Secret, $otherScope));
        self::assertNotSame($direct, $hasher->hashJsonLike($bytes, RedactionKind::Secret, $scope));
        self::assertMatchesRegularExpression('/\Asha256:[a-f0-9]{64}\z/', $direct);
    }

    public function testImplementationHasNoExternalEntropySaltTimeOrHostInput(): void
    {
        $reflection = new \ReflectionClass(StableRedactionHasher::class);
        self::assertSame([], $reflection->getProperties());
        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);

        foreach (
            [
                'getenv(',
                '$_ENV',
                '$_SERVER',
                'microtime(',
                'time(',
                'hrtime(',
                'random_bytes(',
                'random_int(',
                'mt_rand(',
                'uniqid(',
                'getmypid(',
                'gethostname(',
                'php_uname(',
                'date(',
                'salt',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function testHashDoesNotDependOnEnvironmentOrProcessState(): void
    {
        $hasher = new StableRedactionHasher();
        $context = new RedactionContext('cli.output');
        $expected = $hasher->hashString('synthetic-bytes', RedactionKind::Secret, $context);
        $previous = \getenv('CORETSIA_REDACTION_TEST_ENV');

        try {
            \putenv('CORETSIA_REDACTION_TEST_ENV=changed-value');
            self::assertSame($expected, $hasher->hashString('synthetic-bytes', RedactionKind::Secret, $context));
            self::assertSame(
                $expected,
                new StableRedactionHasher()->hashString('synthetic-bytes', RedactionKind::Secret, $context),
            );
        } finally {
            if ($previous === false) {
                \putenv('CORETSIA_REDACTION_TEST_ENV');
            } else {
                \putenv('CORETSIA_REDACTION_TEST_ENV=' . $previous);
            }
        }
    }
}
