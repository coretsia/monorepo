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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultSensitiveDataRedactorRejectsSensitiveMapKeysTest extends TestCase
{
    #[DataProvider('valueLikeMapKeys')]
    public function testValueClassifiedStructuralMapKeyFailsBeforeChildSemanticTraversal(string $key): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.map-key');
        $rawValue = 'synthetic-private-value';

        self::assertNull(new SensitiveKeyClassifier()->classify($key));
        self::assertNotNull(new SensitiveValueClassifier()->classify($key));

        // This control-byte child is Foundation-valid, but semantic traversal must never reach it.
        try {
            $redactor->redactJsonLike([$key => ['bad' . "\x00" . 'key' => $rawValue]], $context);
            self::fail('Classified map key must fail closed before inspecting its value.');
        } catch (RedactionException $exception) {
            self::assertSame(RedactionException::REASON_SENSITIVE_MAP_KEY, $exception->reason());
            self::assertSame('CORETSIA_REDACTION_FAILED: sensitive-map-key', $exception->getMessage());
            self::assertStringNotContainsString($key, $exception->getMessage());
            self::assertStringNotContainsString($rawValue, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valueLikeMapKeys(): array
    {
        return [
            'bearer-token' => ['Bearer synthetic-token-value'],
            'authorization-bearer-header' => ['Authorization: Bearer synthetic-token-value'],
            'authorization-digest-header' => ['Authorization: Digest synthetic-value'],
            'proxy-basic-header' => ['Proxy-Authorization: Basic synthetic-value'],
            'cookie-header' => ['Cookie: session=synthetic'],
            'credential-uri' => ['mysql://user:pass@db.example.test/db'],
            'jwt' => ['eyJhbGci.eyJzdWI.signature'],
            'windows-path' => ['C:\\synthetic\\private.txt'],
            'email' => ['test@example.test'],
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
