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
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SensitiveKeyClassifierTest extends TestCase
{
    #[DataProvider('canonicalAliases')]
    public function testEveryDocumentedAliasAndItsAsciiVariantsHaveExactlyOneKind(
        string $alias,
        RedactionKind $kind,
    ): void {
        $classifier = new SensitiveKeyClassifier();

        self::assertSame($kind, $classifier->classify($alias));
        self::assertSame($kind, $classifier->classify(\strtoupper($alias)));
        self::assertSame($kind, $classifier->classify(\ucfirst($alias)));

        foreach (['-', '_', '.', ' '] as $separator) {
            $separated = \implode($separator, \str_split(\strtoupper($alias)));
            self::assertSame($kind, $classifier->classify($separated));
        }
    }

    public function testDocumentedAliasTableIsExactWithNoUndocumentedAdditions(): void
    {
        $expected = [];

        foreach (self::aliasGroups() as $kind => $aliases) {
            foreach (\explode('|', $aliases) as $alias) {
                self::assertArrayNotHasKey($alias, $expected);
                $expected[$alias] = RedactionKind::from($kind);
            }
        }

        $reflection = new ReflectionClass(SensitiveKeyClassifier::class);
        $constant = $reflection->getReflectionConstant('ALIASES');
        self::assertNotFalse($constant);
        self::assertTrue($constant->isPrivate());
        self::assertSame($expected, $constant->getValue());
    }

    #[DataProvider('unclassifiedStructuralKeys')]
    public function testAmbiguousAndOwnerOnlyKeysRemainUnclassified(string $key): void
    {
        $classifier = new SensitiveKeyClassifier();

        self::assertNull($classifier->classify($key));
        self::assertNull($classifier->classify(\strtoupper($key)));
    }

    public function testSqlAndEnvValueRequireExplicitStructuralAliases(): void
    {
        $classifier = new SensitiveKeyClassifier();

        self::assertNull($classifier->classify('bindings'));
        self::assertSame(RedactionKind::Sql, $classifier->classify('sql_bindings'));
        self::assertNull($classifier->classify('statement'));
        self::assertSame(RedactionKind::Sql, $classifier->classify('sql_statement'));

        foreach (['env', 'environment', 'dotenv'] as $ambiguous) {
            self::assertNull($classifier->classify($ambiguous));
        }

        self::assertSame(RedactionKind::EnvValue, $classifier->classify('env_value'));
        self::assertSame(RedactionKind::EnvValue, $classifier->classify('raw_env_value'));
        self::assertSame(RedactionKind::Payload, $classifier->classify('query_string'));
    }

    public function testAsciiOnlyFoldingIgnoresLocaleAndDoesNotNormalizeUnicode(): void
    {
        $classifier = new SensitiveKeyClassifier();
        $before = \setlocale(\LC_CTYPE, 0);

        try {
            foreach (['C', 'C.UTF-8', 'en_US.UTF-8', 'tr_TR.UTF-8'] as $locale) {
                if (\setlocale(\LC_CTYPE, $locale) === false) {
                    continue;
                }

                self::assertSame(RedactionKind::Authorization, $classifier->classify('AUTHORIZATION'));
                self::assertSame(RedactionKind::Secret, $classifier->classify('CLIENT.SECRET'));
                self::assertNull($classifier->classify("passw\u{00F6}rd"));
                self::assertNull($classifier->classify("\u{0130}dtoken"));
                self::assertNull($classifier->classify('auth' . "\u{00A0}" . 'data'));
            }
        } finally {
            if (\is_string($before)) {
                \setlocale(\LC_CTYPE, $before);
            }
        }
    }

    /**
     * @return array<string, array{string, RedactionKind}>
     */
    public static function canonicalAliases(): array
    {
        $cases = [];

        foreach (self::aliasGroups() as $kind => $aliases) {
            foreach (\explode('|', $aliases) as $alias) {
                $cases[$alias] = [$alias, RedactionKind::from($kind)];
            }
        }

        return $cases;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unclassifiedStructuralKeys(): array
    {
        $keys = [
            'bindings',
            'statement',
            'auth_identifier',
            'auth_identifiers',
            'authidentifier',
            'authidentifiers',
            'user_id',
            'tenant_id',
            'request_id',
            'correlation_id',
            'userid',
            'tenantid',
            'requestid',
            'correlationid',
            'customer',
            'customer_data',
            'private_customer_data',
            'customerdata',
            'privatecustomerdata',
            'address',
            'path',
            'raw_path',
            'directory',
            'env',
            'environment',
            'dotenv',
            'rawpath',
            'authorization/path',
            "auth\taction",
            'module_id',
            'token_bucket_capacity',
            'token_generation_mode',
            'unknown_key',
            'output_status',
            'source',
            'provenance',
        ];

        $cases = [];
        foreach ($keys as $key) {
            $cases[$key] = [$key];
        }

        return $cases;
    }

    /**
     * Canonical aliases are pinned to the exact table specified by epic 2.25.0.
     *
     * @return array<string, string>
     */
    private static function aliasGroups(): array
    {
        return [
            'secret-reference' => 'secretref|secretreference|keyref',
            'secret' => 'secret|secrets|secretvalue|password|passwords|passwd|pwd|clientsecret|privatekey|privatekeys|secretkey',
            'credential' => 'credential|credentials|dsn|connectionstring',
            'authorization' => 'authorization|authorizationdata|authorizationheader|auth|authdata|' . 'proxyauthorization|proxyauthorizationheader',
            'cookie' => 'cookie|cookies|setcookie',
            'session-id' => 'session|sessionid|sessionidentifier|sessionidentifiers',
            'token' => 'token|tokens|accesstoken|refreshtoken|idtoken|bearertoken|apikey|xapikey|accesskey|csrf|csrftoken|xsrf|xsrftoken',
            'payload' => 'payload|rawpayload|body|rawbody|header|headers|rawheader|rawheaders|query|rawquery|querystring|'
                . 'requestheader|requestheaders|rawrequestheader|rawrequestheaders|responseheader|responseheaders|'
                . 'rawresponseheader|rawresponseheaders|requestbody|rawrequestbody|requestpayload|rawrequestpayload|'
                . 'responsebody|rawresponsebody|responsepayload|rawresponsepayload|providerpayload|rawproviderpayload',
            'sql' => 'sql|rawsql|sqlquery|rawsqlquery|sqlbindings|sqlstatement',
            'pii' => 'email|emailaddress|phone|phonenumber|username|firstname|lastname|fullname|dateofbirth|birthdate|dob',
            'env-value' => 'envvalue|rawenvvalue',
            'local-path' => 'localpath|filepath|absolutepath|workingdirectory|cwd',
        ];
    }
}
