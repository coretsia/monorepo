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

namespace Coretsia\Foundation\Tests\Unit;

use Coretsia\Foundation\Id\CorrelationIdGenerator;
use Coretsia\Foundation\Id\UlidGenerator;
use PHPUnit\Framework\TestCase;

final class CorrelationIdGeneratorDelegatesToUlidGeneratorTest extends TestCase
{
    public function testConstructorRequiresCanonicalUlidGenerator(): void
    {
        $constructor = new \ReflectionMethod(CorrelationIdGenerator::class, '__construct');
        $parameters = $constructor->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame('ulids', $parameters[0]->getName());

        $type = $parameters[0]->getType();

        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertFalse($type->isBuiltin());
        self::assertSame(UlidGenerator::class, $type->getName());
    }

    public function testGenerateDelegatesToUlidGenerator(): void
    {
        $source = self::correlationIdGeneratorSource();

        self::assertStringContainsString(
            'return $this->ulids->generate();',
            $source,
        );
    }

    public function testCorrelationIdGeneratorDoesNotContainOwnUlidImplementationTokens(): void
    {
        $source = self::correlationIdGeneratorSource();
        $tokens = \token_get_all($source);

        $forbiddenNames = [
            'bindec',
            'chr',
            'decbin',
            'explode',
            'microtime',
            'ord',
            'random_bytes',
            'str_pad',
            'substr',
            'timestampBytes',
            'unixTimeMilliseconds',
        ];

        foreach ($tokens as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$id, $text, $line] = $token;

            if (!self::isNameToken($id)) {
                continue;
            }

            self::assertNotContains(
                $text,
                $forbiddenNames,
                \sprintf(
                    'CorrelationIdGenerator must not contain ULID implementation token "%s" on line %d.',
                    $text,
                    $line,
                ),
            );
        }
    }

    private static function correlationIdGeneratorSource(): string
    {
        $packageRoot = \dirname(__DIR__, 2);
        $file = $packageRoot . '/src/Id/CorrelationIdGenerator.php';

        self::assertFileExists($file);

        $source = \file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }

    private static function isNameToken(int $id): bool
    {
        return $id === \T_STRING
            || $id === \T_NAME_FULLY_QUALIFIED
            || $id === \T_NAME_QUALIFIED
            || $id === \T_NAME_RELATIVE;
    }
}
