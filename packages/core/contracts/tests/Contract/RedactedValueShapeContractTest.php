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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;

final class RedactedValueShapeContractTest extends TestCase
{
    public function testValueObjectHasExactImmutableStateAndPublicApi(): void
    {
        $reflection = new ReflectionClass(RedactedValue::class);

        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            $properties[] = $property->getName();
            self::assertTrue($property->isPrivate());
        }

        self::assertSame(['kind', 'mode', 'length', 'hash'], $properties);

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertSame(4, $constructor->getNumberOfRequiredParameters());
        self::assertSame(4, $constructor->getNumberOfParameters());

        $parameters = $constructor->getParameters();

        foreach (
            [
                ['kind', RedactionKind::class],
                ['mode', RedactionMode::class],
                ['length', 'int'],
                ['hash', 'string'],
            ] as $index => [$name, $type]
        ) {
            self::assertSame($name, $parameters[$index]->getName());
            $parameterType = $parameters[$index]->getType();

            self::assertInstanceOf(ReflectionNamedType::class, $parameterType);
            self::assertSame($type, $parameterType->getName());
            self::assertSame($index >= 2, $parameters[$index]->allowsNull());
            self::assertFalse($parameters[$index]->isDefaultValueAvailable());
        }

        $methodNames = \array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        );

        self::assertSame(
            ['__construct', 'schemaVersion', 'kind', 'mode', 'length', 'hash', 'toArray'],
            $methodNames,
        );

        foreach (
            [
                'schemaVersion' => 'int',
                'kind' => RedactionKind::class,
                'mode' => RedactionMode::class,
                'length' => 'int',
                'hash' => 'string',
                'toArray' => 'array',
            ] as $methodName => $returnType
        ) {
            $method = $reflection->getMethod($methodName);

            self::assertFalse($method->isStatic());
            self::assertSame(0, $method->getNumberOfParameters());
            $methodType = $method->getReturnType();

            self::assertInstanceOf(ReflectionNamedType::class, $methodType);
            self::assertSame($returnType, $methodType->getName());
            self::assertSame(
                \in_array($methodName, ['length', 'hash'], true),
                $methodType->allowsNull(),
            );
        }

        $constants = $reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC);

        self::assertCount(1, $constants);
        self::assertSame('SCHEMA_VERSION', $constants[0]->getName());
        $constantType = $constants[0]->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $constantType);
        self::assertSame('int', $constantType->getName());
        self::assertSame(1, $constants[0]->getValue());
    }

    #[DataProvider('validShapeProvider')]
    public function testAllowedModesProduceTheExactSixKeyShape(
        RedactionMode $mode,
        ?int $length,
        ?string $hash,
    ): void {
        $value = new RedactedValue(RedactionKind::Secret, $mode, $length, $hash);
        $expected = [
            'hash' => $hash,
            'kind' => 'secret',
            'length' => $length,
            'mode' => $mode->value,
            'redacted' => true,
            'schemaVersion' => 1,
        ];

        self::assertSame(1, $value->schemaVersion());
        self::assertSame(RedactionKind::Secret, $value->kind());
        self::assertSame($mode, $value->mode());
        self::assertSame($length, $value->length());
        self::assertSame($hash, $value->hash());
        self::assertSame($expected, $value->toArray());
        self::assertSame(
            ['hash', 'kind', 'length', 'mode', 'redacted', 'schemaVersion'],
            \array_keys($value->toArray()),
        );
    }

    #[DataProvider('invalidShapeProvider')]
    public function testInvalidConstructorCombinationsFailWithOneSafeError(
        RedactionMode $mode,
        ?int $length,
        ?string $hash,
    ): void {
        try {
            new RedactedValue(RedactionKind::Secret, $mode, $length, $hash);
            self::fail('Invalid redacted summary shape must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('redacted-value-shape-invalid', $exception->getMessage());
            self::assertStringNotContainsString('synthetic-malformed-hash', $exception->getMessage());
            self::assertStringNotContainsString('-1', $exception->getMessage());
        }
    }

    public function testSerializedSummaryIsRecursiveJsonLikeWithoutRawValueStorage(): void
    {
        $summary = new RedactedValue(
            RedactionKind::SecretReference,
            RedactionMode::HashAndLength,
            15,
            'sha256:' . \str_repeat('a', 64),
        );

        $nested = [
            'items' => [
                $summary->toArray(),
                [
                    'metadata' => $summary->toArray(),
                ],
            ],
        ];

        self::assertJsonLike($nested);
        self::assertIsString(\json_encode($nested, \JSON_THROW_ON_ERROR));
        self::assertSame(
            ['kind', 'mode', 'length', 'hash'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                new ReflectionClass($summary)->getProperties(),
            ),
        );

        $doc = new ReflectionClass($summary)->getDocComment();

        self::assertIsString($doc);
        self::assertStringContainsString('never retains the original value', $doc);
    }

    public function testToArrayHasTheExactDocumentedReturnShape(): void
    {
        $method = new ReflectionMethod(RedactedValue::class, 'toArray');
        $doc = $method->getDocComment();

        self::assertIsString($doc);

        foreach (
            [
                'hash: ?string',
                'kind: string',
                'length: ?int',
                'mode: string',
                'redacted: true',
                'schemaVersion: 1',
            ] as $field
        ) {
            self::assertStringContainsString($field, $doc);
        }
    }

    /**
     * @return array<string, array{RedactionMode, ?int, ?string}>
     */
    public static function validShapeProvider(): array
    {
        $hash = 'sha256:' . \str_repeat('a', 64);

        return [
            'placeholder' => [RedactionMode::Placeholder, null, null],
            'length-zero' => [RedactionMode::Length, 0, null],
            'length-positive' => [RedactionMode::Length, 12, null],
            'hash' => [RedactionMode::Hash, null, $hash],
            'hash-and-length-zero' => [RedactionMode::HashAndLength, 0, $hash],
            'hash-and-length-positive' => [RedactionMode::HashAndLength, 12, $hash],
        ];
    }

    /**
     * @return array<string, array{RedactionMode, ?int, ?string}>
     */
    public static function invalidShapeProvider(): array
    {
        $hash = 'sha256:' . \str_repeat('a', 64);

        return [
            'placeholder-with-length' => [RedactionMode::Placeholder, 1, null],
            'placeholder-with-hash' => [RedactionMode::Placeholder, null, $hash],
            'placeholder-with-both' => [RedactionMode::Placeholder, 1, $hash],
            'placeholder-with-negative-length' => [RedactionMode::Placeholder, -1, null],
            'length-missing' => [RedactionMode::Length, null, null],
            'length-with-hash' => [RedactionMode::Length, 1, $hash],
            'length-negative' => [RedactionMode::Length, -1, null],
            'hash-missing' => [RedactionMode::Hash, null, null],
            'hash-with-length' => [RedactionMode::Hash, 1, $hash],
            'hash-malformed' => [RedactionMode::Hash, null, 'synthetic-malformed-hash'],
            'hash-uppercase-digest' => [RedactionMode::Hash, null, 'sha256:' . \str_repeat('A', 64)],
            'hash-short-digest' => [RedactionMode::Hash, null, 'sha256:' . \str_repeat('a', 63)],
            'hash-long-digest' => [RedactionMode::Hash, null, 'sha256:' . \str_repeat('a', 65)],
            'hash-non-hex-digest' => [RedactionMode::Hash, null, 'sha256:' . \str_repeat('g', 64)],
            'hash-wrong-prefix' => [RedactionMode::Hash, null, 'sha512:' . \str_repeat('a', 64)],
            'hash-and-length-missing-both' => [RedactionMode::HashAndLength, null, null],
            'hash-and-length-missing-length' => [RedactionMode::HashAndLength, null, $hash],
            'hash-and-length-missing-hash' => [RedactionMode::HashAndLength, 1, null],
            'hash-and-length-negative' => [RedactionMode::HashAndLength, -1, $hash],
            'hash-and-length-malformed' => [RedactionMode::HashAndLength, 1, 'synthetic-malformed-hash'],
        ];
    }

    private static function assertJsonLike(mixed $value): void
    {
        if ($value === null || \is_bool($value) || \is_int($value) || \is_string($value)) {
            return;
        }

        self::assertIsArray($value);

        if (\array_is_list($value)) {
            foreach ($value as $item) {
                self::assertJsonLike($item);
            }

            return;
        }

        foreach ($value as $key => $item) {
            self::assertIsString($key);
            self::assertJsonLike($item);
        }
    }
}
