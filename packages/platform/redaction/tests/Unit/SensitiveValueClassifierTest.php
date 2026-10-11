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

use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SensitiveValueClassifierTest extends TestCase
{
    #[DataProvider('sensitivePatterns')]
    public function testDocumentedHighConfidenceWholeValuePatterns(
        string $value,
        RedactionKind $expected,
    ): void {
        self::assertSame($expected, new SensitiveValueClassifier()->classify($value));
    }

    #[DataProvider('nearMissesAndControls')]
    public function testNearMissesOwnerOnlyValuesAndOrdinaryTextRemainUnclassified(string $value): void
    {
        self::assertNull(new SensitiveValueClassifier()->classify($value));
    }

    public function testPrecedenceCollisionsChooseTheFirstCanonicalPattern(): void
    {
        $classifier = new SensitiveValueClassifier();

        // Authorization > cookie > credential URI > token > local path > PII.
        self::assertSame(RedactionKind::Authorization, $classifier->classify('Bearer user@example.com'));
        self::assertSame(RedactionKind::Authorization, $classifier->classify('Authorization: tok_AbCd123456'));
        self::assertSame(
            RedactionKind::Authorization,
            $classifier->classify('Proxy-Authorization: Basic synthetic-value'),
        );
        self::assertSame(RedactionKind::Cookie, $classifier->classify('Cookie: tok_AbCd123456'));
        self::assertSame(RedactionKind::Cookie, $classifier->classify('Set-Cookie: user@example.com'));
        self::assertSame(RedactionKind::LocalPath, $classifier->classify('C:\\user@example.com'));
        self::assertSame(RedactionKind::Credential, $classifier->classify('file://alice:pw@host/share'));
    }

    public function testNoHeuristicsDecodingOrUnkeyedClassificationForOwnerOnlyKinds(): void
    {
        $classifier = new SensitiveValueClassifier();

        foreach (
            [
                \str_repeat('Z', 2_000),
                'secret-value',
                'secret-ref-123',
                'session-id-1234',
                'request_body',
                'raw_payload',
                'env_value',
                'SELECT * FROM synthetic_table',
                'INSERT INTO synthetic_table VALUES (1)',
                '{"token":"Bearer synthetic-token"}',
                'dXNlcjpwYXNzd29yZA==',
                'https://example.test/?token=synthetic',
                'token_bucket_capacity',
                'token_generation_mode',
                '/health',
                '/users/{id}',
                '/api/v1/items',
                '\\Coretsia\\Foundation\\ExampleService',
            ] as $value
        ) {
            self::assertNull($classifier->classify($value));
        }
    }

    /**
     * @return array<string, array{string, RedactionKind}>
     */
    public static function sensitivePatterns(): array
    {
        return [
            'bearer' => ['Bearer synthetic-token-value', RedactionKind::Authorization],
            'bearer-tabs' => ["bEaReR\tvalue", RedactionKind::Authorization],
            'authorization-header-digest' => ['Authorization: Digest synthetic-value', RedactionKind::Authorization],
            'authorization-header-basic' => ['Authorization: Basic synthetic-value', RedactionKind::Authorization],
            'proxy-authorization' => ['pRoXy-AuThOrIzAtIoN: Basic synthetic-value', RedactionKind::Authorization],
            'cookie-header' => ['Cookie: session=synthetic', RedactionKind::Cookie],
            'set-cookie' => ['set-cookie: session=synthetic', RedactionKind::Cookie],
            'credential-uri' => ['mysql://alice:secret@db.example.test/db', RedactionKind::Credential],
            'credential-uri-colon-password' => [
                'scheme+v1://alice:p:a@host/path?q#fragment',
                RedactionKind::Credential,
            ],
            'jwt' => ['eyJ' . 'synthetic.payload.signature_12', RedactionKind::Token],
            'aws-akia' => ['AKIA' . \str_repeat('A', 16), RedactionKind::Token],
            'aws-asia' => ['ASIA' . \str_repeat('1', 16), RedactionKind::Token],
            'sk-token-minimum' => ['sk_12345678', RedactionKind::Token],
            'sk-token-maximum' => ['sk_' . \str_repeat('a', 512), RedactionKind::Token],
            'tok-token-minimum' => ['tok_12345678', RedactionKind::Token],
            'tok-token-max-bound' => ['tok_' . \str_repeat('a', 512), RedactionKind::Token],
            'windows-backslash' => ['C:\\synthetic\\private.txt', RedactionKind::LocalPath],
            'windows-forward' => ['D:/synthetic/private.txt', RedactionKind::LocalPath],
            'windows-leading-whitespace' => [" \tC:\\synthetic\\private.txt", RedactionKind::LocalPath],
            'unc' => ['\\\\server\\share\\file.txt', RedactionKind::LocalPath],
            'file-absolute' => ['FiLe:///var/synthetic/config', RedactionKind::LocalPath],
            'file-authority' => ['file://server/share/path', RedactionKind::LocalPath],
            'email' => ['synthetic.user+tag@example.test', RedactionKind::Pii],
            'email-uppercase' => ['Synthetic@Example.TEST', RedactionKind::Pii],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nearMissesAndControls(): array
    {
        $cases = [
            'bare-basic' => 'Basic synthetic-value',
            'basic-plan' => 'Basic plan',
            'basic-configuration' => 'Basic configuration',
            'bare-bearer' => 'Bearer',
            'bearer-leading-space' => ' Bearer token',
            'bearer-crlf' => "Bearer abc\r\nunsafe",
            'authorization-empty' => 'Authorization:',
            'authorization-crlf' => "Authorization: Digest abc\nunsafe",
            'cookie-empty' => 'Cookie:',
            'cookie-crlf' => "Cookie: x=y\nunsafe",
            'credential-empty-user' => 'mysql://:password@host/path',
            'credential-empty-password' => 'mysql://user:@host/path',
            'credential-missing-at' => 'mysql://user:password/host',
            'credential-missing-authority' => 'mysql://user:password@',
            'credential-whitespace' => 'mysql://user:pa ss@host',
            'credential-control' => "mysql://user:pa\x00ss@host",
            'jwt-first-segment' => 'abJ.abc.def',
            'jwt-empty-middle' => 'eyJabc..def',
            'jwt-empty-last' => 'eyJabc.def.',
            'jwt-extra-segment' => 'eyJabc.def.ghi.fourth',
            'jwt-padding' => 'eyJabc.def.ghi=',
            'jwt-non-base64url' => 'eyJabc.def+ghi.third',
            'jwt-ordinary-dotted-version' => '0.7.0',
            'jwt-ordinary-dotted-text' => 'a.b.c',
            'aws-lowercase' => 'akia' . \str_repeat('A', 16),
            'aws-short' => 'AKIA' . \str_repeat('A', 15),
            'aws-long' => 'ASIA' . \str_repeat('A', 17),
            'aws-nonalnum' => 'AKIA' . \str_repeat('A', 15) . '-',
            'prefixed-short' => 'sk_1234567',
            'prefixed-sk-long' => 'sk_' . \str_repeat('a', 513),
            'prefixed-tok-short' => 'tok_1234567',
            'prefixed-long' => 'tok_' . \str_repeat('a', 513),
            'prefixed-invalid-byte' => 'tok_abcd$efgh',
            'generic-token-identifier' => 'token_bucket_capacity',
            'generic-token-generation' => 'token_generation_mode',
            'relative-drive' => 'C:relative',
            'file-relative' => 'file://relative',
            'file-scheme-only' => 'file://',
            'single-backslash-fqcn' => '\\Coretsia\\Foundation\\ExampleService',
            'posix-route-health' => '/health',
            'posix-route-template' => '/users/{id}',
            'posix-route-api' => '/api/v1/items',
            'email-whitespace' => 'user name@example.test',
            'email-control' => "user\x01name@example.test",
            'email-consecutive-dots' => 'user..name@example.test',
            'email-leading-dot' => '.user@example.test',
            'email-trailing-dot' => 'user.@example.test',
            'email-leading-domain-hyphen' => 'user@-example.test',
            'email-trailing-domain-hyphen' => 'user@example-.test',
            'email-single-domain-label' => 'user@localhost',
            'email-unicode-local' => "us\u{00E9}r@example.test",
            'email-unicode-domain' => "user@ex\u{00E4}mple.test",
            'email-trailing-domain-dot' => 'user@example.test.',
            'email-double-at' => 'user@@example.test',
            'non-sensitive-success' => 'success',
            'non-sensitive-error' => 'handled-error',
            'non-sensitive-module' => 'module-id',
            'non-sensitive-generation' => 'artifact-generation',
            'non-sensitive-null' => 'null',
            'non-sensitive-bool' => 'true',
            'non-sensitive-numeric-text' => '42',
        ];

        $provider = [];
        foreach ($cases as $name => $value) {
            $provider[$name] = [$value];
        }

        return $provider;
    }
}
