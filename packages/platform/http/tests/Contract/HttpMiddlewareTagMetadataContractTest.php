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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Http\Middleware\HttpMiddlewareStackBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpMiddlewareTagMetadataContractTest extends TestCase
{
    public function testEmptyMetadataIsUnconditionalEvenWhenUnrelatedToggleIsFalse(): void
    {
        $http = self::defaults();
        $http['tracing']['enabled'] = false;
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM, 'App\\UnconditionalMiddleware', 42, []);

        self::assertSame(['App\\UnconditionalMiddleware'], new HttpMiddlewareStackBuilder($tags)->build($http));
    }

    #[DataProvider('canonicalToggles')]
    public function testCanonicalBooleanToggleControlsParticipation(string $key): void
    {
        $http = self::defaults();
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP, 'App\\ConditionalMiddleware', 10, ['toggle' => $key]);

        self::setToggle($http, $key, true);
        self::assertSame(['App\\ConditionalMiddleware'], new HttpMiddlewareStackBuilder($tags)->build($http));
        self::setToggle($http, $key, false);
        self::assertSame([], new HttpMiddlewareStackBuilder($tags)->build($http));
        self::assertTrue(self::validate($http));
    }

    /** @return iterable<string, array{string}> */
    public static function canonicalToggles(): iterable
    {
        foreach (
            [
                'http.correlation.enabled',
                'http.tracing.enabled',
                'http.metrics.enabled',
                'http.access_log.enabled',
                'http.proxy.enabled',
                'http.context.enrich.enabled',
                'http.hosts.enabled',
                'http.request.json.enabled',
            ] as $key
        ) {
            yield $key => [$key];
        }
    }

    #[DataProvider('invalidMetadata')]
    public function testUnsupportedMetadataFailsClosed(array $metadata): void
    {
        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP, 'App\\InvalidMetadata', 15, $metadata);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-tag-meta-invalid');
        new HttpMiddlewareStackBuilder($tags)->build(self::defaults());
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidMetadata(): iterable
    {
        yield 'unknown-key' => [['feature' => true]];
        yield 'mixed-toggle-and-priority' => [['toggle' => 'http.tracing.enabled', 'priority' => 900]];
        yield 'numeric-toggle' => [['toggle' => 1]];
        yield 'empty-toggle' => [['toggle' => '']];
        yield 'unknown-path' => [['toggle' => 'http.request.unknown.enabled']];
        yield 'short-path' => [['toggle' => 'http.tracing']];
        yield 'different-case' => [['toggle' => 'http.Tracing.enabled']];
        yield 'null-toggle' => [['toggle' => null]];
    }

    public function testFoundationRejectsNonMapMetadataBeforeHttpStackAssembly(): void
    {
        $tags = new TagRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('tagged-service-meta-key-must-be-string');
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_APP, 'App\\BadMetadata', 15, [
            0 => 'http.tracing.enabled',
        ]);
    }

    public function testMalformedEffectiveToggleFailsClosedWithoutCoercion(): void
    {
        $http = self::defaults();
        $http['tracing']['enabled'] = 'false';
        self::assertFalse(self::validate($http));

        $tags = new TagRegistry();
        $tags->add(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, 'App\\Trace', 930, [
            'toggle' => 'http.tracing.enabled',
        ]);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-middleware-toggle-invalid');
        new HttpMiddlewareStackBuilder($tags)->build($http);
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return require __DIR__ . '/../../config/http.php';
    }

    /** @param array<string, mixed> $http */
    private static function setToggle(array &$http, string $key, bool $enabled): void
    {
        $parts = \explode('.', $key);
        \array_shift($parts);
        $value = &$http;
        foreach ($parts as $part) {
            $value = &$value[$part];
        }
        $value = $enabled;
        unset($value);
    }

    /** @param array<string, mixed> $http */
    private static function validate(array $http): bool
    {
        return new ConfigValidator()->validate(
            ['http' => $http],
            [ConfigRuleset::fromArray('http', require __DIR__ . '/../../config/rules.php')],
        )->isSuccess();
    }
}
