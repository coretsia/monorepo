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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptorExtensionsAreBoundedContractTest extends TestCase
{
    private const int MAX_DEPTH = 8;
    private const int MAX_NODES = 256;
    private const int MAX_STRING_BYTES = 4096;

    private const string INVALID_EXTENSIONS_MESSAGE = 'Invalid error descriptor extensions.';

    public function testExtensionsAcceptPayloadAtMaxDepth(): void
    {
        $extensions = self::nestedMapAtDepth(self::MAX_DEPTH);

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: $extensions,
        );

        self::assertSame(
            $extensions,
            $descriptor->extensions(),
        );
    }

    public function testExtensionsRejectPayloadExceedingMaxDepth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: self::nestedMapAtDepth(self::MAX_DEPTH + 1),
        );
    }

    public function testExtensionsAcceptPayloadAtMaxNodes(): void
    {
        $extensions = self::flatIntegerMap(self::MAX_NODES);

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: $extensions,
        );

        self::assertCount(
            self::MAX_NODES,
            $descriptor->extensions(),
        );
    }

    public function testExtensionsRejectPayloadExceedingMaxNodes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: self::flatIntegerMap(self::MAX_NODES + 1),
        );
    }

    public function testExtensionsApplyNodeBudgetToNestedLists(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'items' => array_fill(
                    0,
                    self::MAX_NODES,
                    1,
                ),
            ],
        );
    }

    public function testExtensionsAcceptStringAtMaxByteSize(): void
    {
        $value = str_repeat('a', self::MAX_STRING_BYTES);

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'value' => $value,
            ],
        );

        self::assertSame(
            $value,
            $descriptor->extensions()['value'],
        );
    }

    public function testExtensionsRejectExcessiveStringSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                'value' => str_repeat(
                    'a',
                    self::MAX_STRING_BYTES + 1,
                ),
            ],
        );
    }

    public function testExtensionsAcceptMapKeyAtMaxByteSize(): void
    {
        $key = str_repeat('a', self::MAX_STRING_BYTES);

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                $key => 1,
            ],
        );

        self::assertSame(
            [
                $key => 1,
            ],
            $descriptor->extensions(),
        );
    }

    public function testExtensionsRejectExcessiveMapKeySize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: [
                str_repeat(
                    'a',
                    self::MAX_STRING_BYTES + 1,
                ) => 1,
            ],
        );
    }

    public function testExtensionsAcceptPayloadAtTotalStringByteBudget(): void
    {
        $extensions = self::aggregateStringMap(
            valueBytes: self::MAX_STRING_BYTES - 4,
        );

        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: $extensions,
        );

        self::assertSame(
            $extensions,
            $descriptor->extensions(),
        );
    }

    public function testExtensionsRejectPayloadExceedingTotalStringBudget(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_EXTENSIONS_MESSAGE);

        new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            extensions: self::aggregateStringMap(
                valueBytes: self::MAX_STRING_BYTES - 3,
            ),
        );
    }

    #[RunInSeparateProcess]
    public function testExtensionsRejectRecursiveArrayWithoutMemoryExhaustion(): void
    {
        $extensions = [];
        $extensions['self'] = &$extensions;

        try {
            new ErrorDescriptor(
                code: 'core.example',
                message: 'Example message.',
                extensions: $extensions,
            );

            self::fail('Expected recursive extensions to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                self::INVALID_EXTENSIONS_MESSAGE,
                $exception->getMessage(),
            );
        }
    }

    public function testExtensionBudgetFailuresDoNotEchoRawData(): void
    {
        $marker = 'sensitive-marker-';
        $value = $marker . str_repeat(
            'x',
            self::MAX_STRING_BYTES,
        );

        try {
            new ErrorDescriptor(
                code: 'core.example',
                message: 'Example message.',
                extensions: [
                    'reference' => $value,
                ],
            );

            self::fail('Expected oversized extensions to be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                self::INVALID_EXTENSIONS_MESSAGE,
                $exception->getMessage(),
            );

            self::assertStringNotContainsString(
                $marker,
                $exception->getMessage(),
            );
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function nestedMapAtDepth(int $depth): array
    {
        if ($depth < 1) {
            throw new \InvalidArgumentException('Depth must be positive.');
        }

        $payload = [
            'value' => 1,
        ];

        for ($currentDepth = 2; $currentDepth <= $depth; ++$currentDepth) {
            $payload = [
                'nested' => $payload,
            ];
        }

        return $payload;
    }

    /**
     * @return array<string,int>
     */
    private static function flatIntegerMap(int $nodes): array
    {
        $payload = [];

        for ($index = 0; $index < $nodes; ++$index) {
            $payload[sprintf('node%03d', $index)] = $index;
        }

        return $payload;
    }

    /**
     * Sixteen entries are used so that a four-byte key plus the requested
     * string value size forms the aggregate string-byte boundary exactly.
     *
     * @return array<string,string>
     */
    private static function aggregateStringMap(int $valueBytes): array
    {
        $payload = [];

        for ($index = 0; $index < 16; ++$index) {
            $payload[sprintf('k%03d', $index)] = str_repeat(
                'a',
                $valueBytes,
            );
        }

        return $payload;
    }
}
