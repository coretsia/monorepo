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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ContainerDiagnostics;
use PHPUnit\Framework\TestCase;

final class ContainerDiagnosticsDoesNotLeakSensitiveServiceIdsContractTest extends TestCase
{
    private const string TAG = 'kernel.reset';

    public function testNormalFqcnServiceIdsRemainReadable(): void
    {
        $diagnostics = self::diagnosticsFor(
            serviceIds: [
                self::class,
            ],
            taggedServiceIds: [
                self::class,
            ],
        );

        $array = $diagnostics->toArray();

        self::assertContains(self::class, $array['services']);
        self::assertContains(self::class, self::taggedIds($array));

        self::assertStringContainsString(
            \str_replace('\\', '\\\\', self::class),
            $diagnostics->toJson(),
        );
    }

    public function testNormalSafeAliasesRemainReadable(): void
    {
        $safeAliases = [
            'logger',
            'cache.pool',
            'http.client',
            'app.service:main',
        ];

        $diagnostics = self::diagnosticsFor(
            serviceIds: $safeAliases,
            taggedServiceIds: $safeAliases,
        );

        $array = $diagnostics->toArray();

        foreach ($safeAliases as $alias) {
            self::assertContains($alias, $array['services']);
            self::assertContains($alias, self::taggedIds($array));
            self::assertStringContainsString($alias, $diagnostics->toJson());
        }
    }

    public function testSensitiveAndSuspiciousServiceIdsAreHashedDeterministically(): void
    {
        $unsafeIds = [
            'absolute path' => '/home/user/project/.env',
            'relative path' => 'config/secrets.php',
            'url-like id' => 'https://example.test/service?token=abc',
            'www-like id' => 'www.example.test.service',
            'token-like alias' => 'token:abc',
            'secret-like alias' => 'secret.value',
            'password-like alias' => 'password:raw',
            'credential-like alias' => 'credential.token',
            'api-key-like id' => 'api_key',
            'access-key-like id' => 'access_key',
            'private-key-like id' => 'private_key',
            'authorization-like id' => 'authorization.header',
            'cookie-like id' => 'cookie.session',
            'sql-like id' => 'select_query',
            'control-character id' => "control\x01id",
            'overlong id' => 'service.' . \str_repeat('a', 130),
            'outside-readable-pattern id' => 'unsafe[id]',
        ];

        $diagnostics = self::diagnosticsFor(
            serviceIds: \array_values($unsafeIds),
            taggedServiceIds: \array_values($unsafeIds),
        );

        $array = $diagnostics->toArray();
        $json = $diagnostics->toJson();
        $taggedIds = self::taggedIds($array);

        foreach ($unsafeIds as $rawId) {
            $expectedHash = self::expectedDiagnosticHash($rawId);

            self::assertContains($expectedHash, $array['services']);
            self::assertContains($expectedHash, $taggedIds);

            self::assertMatchesRegularExpression(
                '/\Ahash:sha256:[a-f0-9]{64};len:[0-9]+\z/',
                $expectedHash,
            );

            self::assertStringStartsWith('hash:sha256:', $expectedHash);
            self::assertStringContainsString(';len:' . \strlen($rawId), $expectedHash);

            self::assertStringNotContainsString(
                $rawId,
                $json,
                'Container diagnostics JSON must not contain unsafe raw service ids.',
            );

            self::assertNotContains(
                $rawId,
                $array['services'],
                'Container diagnostics services list must not contain unsafe raw service ids.',
            );

            self::assertNotContains(
                $rawId,
                $taggedIds,
                'Container diagnostics tags list must not contain unsafe raw tagged service ids.',
            );
        }

        self::assertSame(
            $json,
            self::diagnosticsFor(
                serviceIds: \array_values($unsafeIds),
                taggedServiceIds: \array_values($unsafeIds),
            )->toJson(),
        );
    }

    public function testSuspiciousAliasesAreHashedBeforeReadableAliasAllowlist(): void
    {
        $suspiciousAliases = [
            'token:abc',
            'secret.value',
            'password:raw',
            'credential.token',
        ];

        $diagnostics = self::diagnosticsFor(
            serviceIds: $suspiciousAliases,
            taggedServiceIds: $suspiciousAliases,
        );

        $array = $diagnostics->toArray();
        $json = $diagnostics->toJson();
        $taggedIds = self::taggedIds($array);

        foreach ($suspiciousAliases as $alias) {
            $expectedHash = self::expectedDiagnosticHash($alias);

            self::assertMatchesRegularExpression(
                '/\A[A-Za-z_][A-Za-z0-9_.:-]{0,127}\z/',
                $alias,
                'Fixture must prove precedence over the conservative readable alias allowlist.',
            );

            self::assertContains($expectedHash, $array['services']);
            self::assertContains($expectedHash, $taggedIds);

            self::assertNotContains($alias, $array['services']);
            self::assertNotContains($alias, $taggedIds);
            self::assertStringNotContainsString($alias, $json);
        }
    }

    public function testDiagnosticsJsonDoesNotContainUnsafeRawServiceIdsOrTagMetadata(): void
    {
        $unsafeIds = [
            '/home/user/project/.env',
            'https://example.test/service?token=abc',
            'token:abc',
            'secret.value',
            'password:raw',
            'credential.token',
            'select_query',
        ];

        $diagnostics = self::diagnosticsFor(
            serviceIds: [
                self::class,
                'logger',
                ...$unsafeIds,
            ],
            taggedServiceIds: $unsafeIds,
        );

        $json = $diagnostics->toJson();

        self::assertStringEndsWith("\n", $json);

        $decoded = \json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);
        self::assertSame('coretsia.foundation.containerDiagnostics.v1', $decoded['schemaVersion'] ?? null);

        foreach ($unsafeIds as $rawId) {
            self::assertStringNotContainsString($rawId, $json);
            self::assertStringContainsString(self::expectedDiagnosticHash($rawId), $json);
        }

        foreach (self::forbiddenDiagnosticsNeedles() as $needle) {
            self::assertStringNotContainsString(
                $needle,
                $json,
                'Container diagnostics JSON must not expose unsafe service ids or tag metadata.',
            );
        }

        self::assertStringContainsString('logger', $json);
        self::assertStringContainsString(\str_replace('\\', '\\\\', self::class), $json);
    }

    /**
     * @param list<string> $serviceIds
     * @param list<string> $taggedServiceIds
     */
    private static function diagnosticsFor(
        array $serviceIds,
        array $taggedServiceIds = [],
    ): ContainerDiagnostics {
        $builder = self::builderFor($serviceIds);

        foreach ($taggedServiceIds as $index => $serviceId) {
            $builder->tag(
                self::TAG,
                $serviceId,
                priority: 100 - $index,
                meta: [
                    'unsafe_meta' => 'Authorization Bearer token Cookie session_id password raw SQL SELECT * FROM users /tmp/coretsia-secret',
                ],
            );
        }

        return ContainerDiagnostics::fromBuilder($builder);
    }

    /**
     * @param list<string> $serviceIds
     */
    private static function builderFor(array $serviceIds): ContainerBuilder
    {
        $builder = new ContainerBuilder();

        foreach ($serviceIds as $serviceId) {
            $builder->instance($serviceId, new \stdClass());
        }

        return $builder;
    }

    /**
     * @param array{
     *     schemaVersion: string,
     *     services: list<string>,
     *     tags: array<string, list<array{id: string, priority: int}>>
     * } $diagnostics
     *
     * @return list<string>
     */
    private static function taggedIds(array $diagnostics): array
    {
        return \array_map(
            static fn (array $service): string => $service['id'],
            $diagnostics['tags'][self::TAG] ?? [],
        );
    }

    private static function expectedDiagnosticHash(string $id): string
    {
        return 'hash:sha256:' . \hash('sha256', $id) . ';len:' . \strlen($id);
    }

    /**
     * @return list<string>
     */
    private static function forbiddenDiagnosticsNeedles(): array
    {
        return [
            '/home/user/project/.env',
            'https://example.test',
            'token=abc',
            'token:abc',
            'secret.value',
            'password:raw',
            'credential.token',
            'select_query',
            'Authorization',
            'Bearer',
            'Cookie',
            'session_id',
            'password',
            'raw SQL',
            'SELECT',
            'users',
            '/tmp/coretsia-secret',
            'unsafe_meta',
        ];
    }
}
