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

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RedactionDoesNotExposeRawValuesContractTest extends TestCase
{
    #[DataProvider('canonicalKeyFixtures')]
    public function testEveryCanonicalSensitiveStructuralKeyReplacesItsCompleteBranch(
        string $key,
        RedactionKind $expectedKind,
    ): void {
        $branch = [
            'nested' => [
                'secret-material' => 'synthetic-private-branch-material',
                'items' => ['synthetic-raw-first', 'synthetic-raw-second'],
            ],
        ];
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');

        self::assertSame(
            $expectedKind,
            new SensitiveKeyClassifier()->classify($key),
        );
        $result = $redactor->redactJsonLike([$key => $branch], $context);

        // The structural label is intentionally preserved; it is not a leaked branch value.
        self::assertSame([$key], \array_keys($result));
        self::assertSame(self::placeholder($expectedKind), $result[$key]);
        self::assertNotSame($branch, $result[$key]);
        self::assertFalse(self::containsExactValue($result, 'synthetic-private-branch-material'));
        self::assertFalse(self::containsExactValue($result, 'synthetic-raw-first'));
        self::assertFalse(self::containsExactValue($result, 'synthetic-raw-second'));
    }

    #[DataProvider('canonicalValueFixtures')]
    public function testEveryCanonicalSensitiveStringValueIsReplacedWithoutLeaking(
        string $value,
        RedactionKind $expectedKind,
    ): void {
        $redactor = self::redactor();
        self::assertSame($expectedKind, new SensitiveValueClassifier()->classify($value));

        $result = $redactor->redactJsonLike(
            ['safe_output' => $value],
            new RedactionContext('redaction.fixtures'),
        );

        self::assertSame(['safe_output' => self::placeholder($expectedKind)], $result);
        self::assertFalse(self::containsExactValue($result, $value));
        self::assertSame(
            self::placeholder($expectedKind),
            $redactor->redactJsonLike($value, new RedactionContext('redaction.fixtures')),
        );
    }

    #[DataProvider('canonicalValueFixtures')]
    public function testEveryCanonicalSensitiveValueUsedAsMapKeyFailsClosed(
        string $key,
        RedactionKind $expectedKind,
    ): void {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');
        $rawValue = 'synthetic-private-map-value';

        self::assertSame($expectedKind, new SensitiveValueClassifier()->classify($key));

        try {
            $redactor->redactJsonLike([$key => $rawValue], $context);
            self::fail('Sensitive value-like map key must fail closed.');
        } catch (RedactionException $exception) {
            self::assertSame(RedactionException::REASON_SENSITIVE_MAP_KEY, $exception->reason());
            self::assertSame(
                'CORETSIA_REDACTION_FAILED: sensitive-map-key',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString($key, $exception->getMessage());
            self::assertStringNotContainsString($rawValue, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }

    #[DataProvider('nonSensitiveControls')]
    public function testNonSensitiveCanonicalControlsAreNotRedacted(mixed $value): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');
        self::assertSame($value, $redactor->redactJsonLike($value, $context));
        self::assertSame(['safe_control' => $value], $redactor->redactJsonLike(['safe_control' => $value], $context));
        if (\is_string($value)) {
            self::assertNull(new SensitiveValueClassifier()->classify($value));

            $asMap = [$value => 'success'];

            if (\is_string(\array_key_first($asMap))) {
                self::assertNull(new SensitiveKeyClassifier()->classify($value));
                self::assertSame($asMap, $redactor->redactJsonLike($asMap, $context));
            }
        }
    }

    public function testSensitiveKeyTakesPrecedenceOverRecursiveSemanticMapKeyCheck(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');
        $result = $redactor->redactJsonLike([
            'headers' => [
                'Bearer synthetic-nested-map-key' => 'synthetic-private-nested-value',
            ],
        ], $context);

        self::assertSame(['headers' => self::placeholder(RedactionKind::Payload)], $result);
    }

    public function testSensitiveStructuralKeyNeverBypassesFoundationValidation(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');
        $overDepth = ['value' => 1];

        for ($depth = 0; $depth < 32; $depth++) {
            $overDepth = [$overDepth];
        }

        $invalid = [
            ['password' => ['nested' => new \stdClass()], 'reason' => RedactionException::REASON_INPUT_INVALID],
            ['password' => \str_repeat('S', 65_537), 'reason' => RedactionException::REASON_INPUT_LIMIT_EXCEEDED],
            ['password' => $overDepth, 'reason' => RedactionException::REASON_INPUT_LIMIT_EXCEEDED],
            ['password' => \array_fill(0, 10_001, 1), 'reason' => RedactionException::REASON_INPUT_LIMIT_EXCEEDED],
            [
                'password' => \array_fill(0, 33, \str_repeat('s', 32_768)),
                'reason' => RedactionException::REASON_INPUT_LIMIT_EXCEEDED,
            ],
        ];

        foreach ($invalid as $case) {
            try {
                $redactor->redactJsonLike(['password' => $case['password']], $context);
                self::fail('Invalid sensitive branch must fail before redaction.');
            } catch (RedactionException $exception) {
                self::assertSame($case['reason'], $exception->reason());
                self::assertSame('CORETSIA_REDACTION_FAILED: ' . $case['reason'], $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('password', $exception->getMessage());
                self::assertStringNotContainsString('SSSSSS', $exception->getMessage());
            }
        }
    }

    public function testSensitiveValueLikeMapKeysAndControlBytesFailWithoutRawDiagnostics(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.fixtures');

        foreach (
            [
                ['key' => 'Bearer synthetic-map-key-token', 'reason' => RedactionException::REASON_SENSITIVE_MAP_KEY],
                ['key' => "synthetic\x1Bmap-key", 'reason' => RedactionException::REASON_INPUT_INVALID],
            ] as $case
        ) {
            try {
                $redactor->redactJsonLike([$case['key'] => 'synthetic-private-value'], $context);
                self::fail('Unsafe map key must fail closed.');
            } catch (RedactionException $exception) {
                self::assertSame($case['reason'], $exception->reason());
                self::assertSame('CORETSIA_REDACTION_FAILED: ' . $case['reason'], $exception->getMessage());
                self::assertStringNotContainsString($case['key'], $exception->getMessage());
                self::assertStringNotContainsString('synthetic-private-value', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    /**
     * @return array<string, array{string, RedactionKind}>
     */
    public static function canonicalKeyFixtures(): array
    {
        return [
            'password' => ['password', RedactionKind::Secret],
            'secret-reference' => ['secret_ref', RedactionKind::SecretReference],
            'client-secret' => ['clientSecret', RedactionKind::Secret],
            'credentials' => ['credentials', RedactionKind::Credential],
            'authorization' => ['Authorization', RedactionKind::Authorization],
            'proxy-authorization' => ['proxy-authorization', RedactionKind::Authorization],
            'authorization-header' => ['authorization_header', RedactionKind::Authorization],
            'cookie' => ['Cookie', RedactionKind::Cookie],
            'session-id' => ['session_id', RedactionKind::SessionId],
            'access-token' => ['access_token', RedactionKind::Token],
            'tokens' => ['tokens', RedactionKind::Token],
            'x-api-key' => ['x-api-key', RedactionKind::Token],
            'request-body' => ['request_body', RedactionKind::Payload],
            'raw-payload' => ['raw_payload', RedactionKind::Payload],
            'request-payload' => ['request_payload', RedactionKind::Payload],
            'provider-payload' => ['provider_payload', RedactionKind::Payload],
            'headers' => ['headers', RedactionKind::Payload],
            'query-string' => ['query_string', RedactionKind::Payload],
            'sql' => ['sql', RedactionKind::Sql],
            'raw-sql' => ['raw_sql', RedactionKind::Sql],
            'sql-query' => ['sql_query', RedactionKind::Sql],
            'sql-bindings' => ['sql_bindings', RedactionKind::Sql],
            'sql-statement' => ['sql_statement', RedactionKind::Sql],
            'email-address' => ['email_address', RedactionKind::Pii],
            'env-value' => ['env_value', RedactionKind::EnvValue],
            'raw-env-value' => ['raw_env_value', RedactionKind::EnvValue],
            'absolute-path' => ['absolute_path', RedactionKind::LocalPath],
        ];
    }

    /**
     * @return array<string, array{string, RedactionKind}>
     */
    public static function canonicalValueFixtures(): array
    {
        return [
            'direct-bearer' => ['Bearer synthetic-token-value', RedactionKind::Authorization],
            'authorization-bearer' => ['Authorization: Bearer synthetic-token-value', RedactionKind::Authorization],
            'authorization-digest' => ['Authorization: Digest synthetic-digest-material', RedactionKind::Authorization],
            'proxy-authorization-basic' => [
                'Proxy-Authorization: Basic c3ludGhldGljOnZhbHVl',
                RedactionKind::Authorization,
            ],
            'cookie' => ['Cookie: session=synthetic-session', RedactionKind::Cookie],
            'credential-uri' => [
                'mysql://synthetic-user:synthetic-password@example.test/database',
                RedactionKind::Credential,
            ],
            'jwt' => ['eyJhbGciOiJub25lIn0.eyJzdWIiOiJzeW50aGV0aWMifQ.signature', RedactionKind::Token],
            'aws-access-key' => ['AKIA1234567890ABCDEF', RedactionKind::Token],
            'prefixed-token' => ['tok_synthetic_example', RedactionKind::Token],
            'windows-drive-path' => ['C:\\synthetic\\project\\.env', RedactionKind::LocalPath],
            'unc-path' => ['\\\\synthetic-server\\share\\secret.txt', RedactionKind::LocalPath],
            'file-uri-local' => ['file:///home/synthetic/project/.env', RedactionKind::LocalPath],
            'file-uri-unc' => ['file://synthetic-server/share/secret.txt', RedactionKind::LocalPath],
            'email' => ['synthetic-user@example.test', RedactionKind::Pii],
        ];
    }

    /**
     * @return array<string, array{null|bool|int|string}>
     */
    public static function nonSensitiveControls(): array
    {
        return [
            'php-class-like' => ['\\Coretsia\\Foundation\\ExampleService'],
            'basic-plan' => ['Basic plan'],
            'basic-configuration' => ['Basic configuration'],
            'success' => ['success'],
            'handled-error' => ['handled-error'],
            'module-id' => ['module-id'],
            'artifact-generation' => ['artifact-generation'],
            'version' => ['0.7.0'],
            'dotted-identifier' => ['a.b.c'],
            'string-forty-two' => ['42'],
            'string-true' => ['true'],
            'token-bucket-capacity' => ['token_bucket_capacity'],
            'token-generation-mode' => ['token_generation_mode'],
            'string-null' => ['null'],
            'safe-relative-path' => ['relative/safe-logical-id'],
            'integer' => [42],
            'boolean' => [true],
            'null' => [null],
        ];
    }

    /**
     * @return array{hash: null, kind: string, length: null, mode: string, redacted: true, schemaVersion: 1}
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

    private static function containsExactValue(mixed $value, string $needle): bool
    {
        if (\is_string($value)) {
            return $value === $needle;
        }
        if (!\is_array($value)) {
            return false;
        }
        foreach ($value as $nested) {
            if (self::containsExactValue($nested, $needle)) {
                return true;
            }
        }

        return false;
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
