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

use Coretsia\Foundation\Routing\SystemRouteTable;
use PHPUnit\Framework\TestCase;

final class SystemRouteTableShapeContractTest extends TestCase
{
    public function testRegistryExposesOnlyTheCanonicalImmutableMetadataApi(): void
    {
        $reflection = new \ReflectionClass(SystemRouteTable::class);
        self::assertSame('Coretsia\\Foundation\\Routing', $reflection->getNamespaceName());
        self::assertSame('SystemRouteTable', $reflection->getShortName());
        self::assertTrue($reflection->isFinal());
        self::assertFalse($reflection->isInstantiable());
        self::assertFalse($reflection->getParentClass());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getTraitNames());
        self::assertSame([], $reflection->getProperties());

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertFalse($constructor->isStatic());
        self::assertSame([], $constructor->getParameters());

        $methods = \array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(),
        );
        \sort($methods, \SORT_STRING);
        self::assertSame(['__construct', 'entries'], $methods);

        $publicMethods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        self::assertCount(1, $publicMethods);
        $entries = $publicMethods[0];
        self::assertSame('entries', $entries->getName());
        self::assertTrue($entries->isStatic());
        self::assertSame([], $entries->getParameters());
        self::assertSame('array', (string) $entries->getReturnType());
        self::assertFalse($entries->returnsReference());

        $constants = \array_keys($reflection->getConstants());
        \sort($constants, \SORT_STRING);
        self::assertSame(['ALIVE_PATH', 'ENTRIES', 'SCHEMA_VERSION'], $constants);

        $version = $reflection->getReflectionConstant('SCHEMA_VERSION');
        self::assertNotFalse($version);
        self::assertTrue($version->isPublic());
        self::assertSame('int', (string) $version->getType());
        self::assertSame(1, $version->getValue());

        $alive = $reflection->getReflectionConstant('ALIVE_PATH');
        self::assertNotFalse($alive);
        self::assertTrue($alive->isPublic());
        self::assertSame('string', (string) $alive->getType());
        self::assertSame('/_alive', $alive->getValue());

        $declarations = $reflection->getReflectionConstant('ENTRIES');
        self::assertNotFalse($declarations);
        self::assertTrue($declarations->isPrivate());
        self::assertSame('array', (string) $declarations->getType());
        self::assertSame(SystemRouteTable::entries(), $declarations->getValue());

        // Empty construction and a direct constant return exclude runtime behavior
        // and service dependencies even when the public signatures stay unchanged.
        self::assertSame(
            ['private', 'function', '__construct', '(', ')', '{', '}'],
            self::methodTokens($constructor),
        );
        self::assertSame(
            [
                'public',
                'static',
                'function',
                'entries',
                '(',
                ')',
                ':',
                'array',
                '{',
                'return',
                'self',
                '::',
                'ENTRIES',
                ';',
                '}',
            ],
            self::methodTokens($entries),
        );
    }

    public function testRegistrySourceDoesNotIntroduceExternalClassDependencies(): void
    {
        $reflection = new \ReflectionClass(SystemRouteTable::class);
        $file = $reflection->getFileName();
        self::assertIsString($file);
        $source = \file_get_contents($file);
        self::assertIsString($source);

        $previous = null;
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->isIgnorable()) {
                continue;
            }

            self::assertNotContains($token->id, [\T_USE, \T_NEW, \T_NAME_FULLY_QUALIFIED, \T_NAME_RELATIVE]);
            if ($token->id === \T_NAME_QUALIFIED) {
                self::assertSame($reflection->getNamespaceName(), $token->text);
            }
            if ($token->id === \T_DOUBLE_COLON) {
                self::assertSame('self', $previous);
            }

            $previous = $token->text;
        }
    }

    public function testCurrentRegistryContainsExactlyTheEightCanonicalDeclarations(): void
    {
        self::assertSame([
            ['owner' => 'platform/http', 'pattern' => '/_alive', 'status' => 'active', 'type' => 'exact'],
            ['owner' => 'platform/http', 'pattern' => '/_debug', 'status' => 'dev_only', 'type' => 'exact'],
            ['owner' => 'platform/http', 'pattern' => '/_debug/', 'status' => 'dev_only', 'type' => 'prefix'],
            ['owner' => 'platform/http', 'pattern' => '/error', 'status' => 'active', 'type' => 'exact'],
            ['owner' => 'platform/health', 'pattern' => '/health', 'status' => 'reserved', 'type' => 'exact'],
            ['owner' => 'platform/health', 'pattern' => '/health/', 'status' => 'reserved', 'type' => 'prefix'],
            ['owner' => 'platform/metrics', 'pattern' => '/metrics', 'status' => 'reserved', 'type' => 'exact'],
            ['owner' => 'platform/http', 'pattern' => '/ping', 'status' => 'active', 'type' => 'exact'],
        ], SystemRouteTable::entries());
    }

    public function testDeclarationsPreserveCanonicalFieldsGrammarAndOrdering(): void
    {
        $entries = SystemRouteTable::entries();
        self::assertTrue(\array_is_list($entries));
        $seen = [];
        $previous = null;

        foreach ($entries as $entry) {
            self::assertSame(['owner', 'pattern', 'status', 'type'], \array_keys($entry));
            foreach ($entry as $value) {
                self::assertIsString($value);
            }

            self::assertContains($entry['status'], ['active', 'reserved', 'dev_only']);
            self::assertContains($entry['type'], ['exact', 'prefix']);
            self::assertMatchesRegularExpression(
                '~\A(?:core|platform|integrations|enterprise|devtools|presets)/[a-z0-9]+(?:[._-][a-z0-9]+)*\z~',
                $entry['owner'],
            );
            self::assertDoesNotMatchRegularExpression(
                '~\Acore/(?:core|platform|integrations|enterprise|devtools|presets)\z~',
                $entry['owner'],
            );

            $pattern = $entry['pattern'];
            self::assertMatchesRegularExpression('~\A/[a-z0-9_-]+(?:/[a-z0-9_-]+)*/?\z~', $pattern);
            self::assertSame($entry['type'] === 'prefix', \str_ends_with($pattern, '/'));

            self::assertArrayNotHasKey($pattern, $seen);
            foreach ($seen as $other) {
                if ($other['type'] === 'prefix') {
                    self::assertFalse(\str_starts_with($pattern, $other['pattern']));
                }
                if ($entry['type'] === 'prefix') {
                    self::assertFalse(\str_starts_with($other['pattern'], $pattern));
                }
            }

            if ($previous !== null) {
                $order = \strcmp($previous['pattern'], $pattern);
                if ($order === 0) {
                    $order = \strcmp($previous['type'], $entry['type']);
                }
                self::assertLessThan(0, $order);
            }

            $previous = $entry;
            $seen[$pattern] = $entry;
        }

        self::assertSame('exact', $seen['/health']['type']);
        self::assertSame('prefix', $seen['/health/']['type']);
        self::assertSame('exact', $seen['/_debug']['type']);
        self::assertSame('prefix', $seen['/_debug/']['type']);
        self::assertSame('dev_only', $seen['/_debug']['status']);
        self::assertSame('dev_only', $seen['/_debug/']['status']);
    }

    public function testReturnedDeclarationsAreDeterministicIndependentValues(): void
    {
        $expected = SystemRouteTable::entries();
        $copy = SystemRouteTable::entries();
        self::assertSame($expected, $copy);

        $copy[0]['pattern'] = '/changed';
        $copy[1]['status'] = 'reserved';
        unset($copy[2]);
        $copy[] = ['owner' => 'platform/http', 'pattern' => '/extra', 'status' => 'reserved', 'type' => 'exact'];

        self::assertNotSame($expected, $copy);
        self::assertSame($expected, SystemRouteTable::entries());
        self::assertSame($expected, SystemRouteTable::entries());
    }

    /** @return list<string> */
    private static function methodTokens(\ReflectionMethod $method): array
    {
        $file = $method->getFileName();
        self::assertIsString($file);
        $lines = \file($file);
        self::assertIsArray($lines);
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        self::assertIsInt($start);
        self::assertIsInt($end);

        $source = \implode('', \array_slice($lines, $start - 1, $end - $start + 1));
        $tokens = [];
        foreach (\PhpToken::tokenize("<?php\n" . $source) as $token) {
            if (!$token->isIgnorable()) {
                $tokens[] = $token->text;
            }
        }

        return $tokens;
    }
}
