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
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class DefaultSensitiveDataRedactorFailsClosedTest extends TestCase
{
    public function testFoundationForbiddenTypesAreRejectedBeforeAnySemanticTraversal(): void
    {
        $resource = \fopen('php://memory', 'r+');
        self::assertIsResource($resource);

        try {
            $forbidden = [
                'float' => 1.125,
                'nan' => \NAN,
                'infinite' => \INF,
                'object' => new \stdClass(),
                'closure' => static fn (): int => 1,
                'resource' => $resource,
                'non-string-map-key' => ['status' => 1, 42 => 'synthetic'],
            ];

            foreach ($forbidden as $name => $input) {
                self::assertRejected($input, RedactionException::REASON_INPUT_INVALID);
                self::assertRejected(['password' => $input], RedactionException::REASON_INPUT_INVALID);
            }
        } finally {
            \fclose($resource);
        }
    }

    public function testReachedStructuralMapKeysRejectEveryAsciiC0ByteAndDel(): void
    {
        foreach ([...\range(0, 31), 127] as $byte) {
            $key = 'safe' . \chr($byte) . 'synthetic';
            self::assertRejected(['outer' => [$key => 'safe']], RedactionException::REASON_INPUT_INVALID, $key);
        }
    }

    public function testKeysNestedOnlyInReplacedBranchAreNotVisitedForTheControlBytePolicy(): void
    {
        foreach ([...\range(0, 31), 127] as $byte) {
            $input = ['headers' => ['nested' . \chr($byte) . 'key' => 'synthetic-private']];
            $output = self::redactor()->redactJsonLike($input, new RedactionContext('redaction.fails-closed'));

            self::assertSame('payload', $output['headers']['kind']);
            self::assertSame('placeholder', $output['headers']['mode']);
            self::assertSame(
                ['hash', 'kind', 'length', 'mode', 'redacted', 'schemaVersion'],
                \array_keys($output['headers']),
            );
        }
    }

    public function testFoundationLimitPrecedesReachedControlByteKeyError(): void
    {
        $key = 'unsafe' . "\x00" . 'synthetic';
        $input = [$key => \str_repeat('S', 65_537)];

        self::assertRejected($input, RedactionException::REASON_INPUT_LIMIT_EXCEEDED, $key);
        self::assertRejected(
            ['password' => [$key => \array_fill(0, 10_001, 1)]],
            RedactionException::REASON_INPUT_LIMIT_EXCEEDED,
            $key,
        );
    }

    public function testStructuralTypeChecksRemainFoundationOwnedWithoutDuplicateValidators(): void
    {
        $reflection = new \ReflectionClass(DefaultSensitiveDataRedactor::class);
        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);
        $code = '';

        foreach (\token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= \is_array($token) ? $token[1] : $token;
        }

        self::assertStringContainsString('JsonLikeNormalizer::normalize(', $code);
        foreach (
            [
                'is_float(',
                'is_resource(',
                'is_object(',
                'instanceof \\Closure',
                'array_keys($value)',
            ] as $duplicate
        ) {
            self::assertStringNotContainsString($duplicate, $code);
        }
    }

    private static function assertRejected(mixed $input, string $reason, ?string $rawKey = null): void
    {
        try {
            self::redactor()->redactJsonLike($input, new RedactionContext('redaction.fails-closed'));
            self::fail('Forbidden json-like input must never return an unchanged or partial result.');
        } catch (RedactionException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertSame(0, $exception->getCode());

            if ($rawKey !== null) {
                self::assertStringNotContainsString($rawKey, $exception->getMessage());
            }
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
