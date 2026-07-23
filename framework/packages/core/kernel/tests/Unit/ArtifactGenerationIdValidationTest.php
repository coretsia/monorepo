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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactGenerationIdValidationTest extends TestCase
{
    public function testAcceptsExactLowercaseSha256GenerationId(): void
    {
        $value = \hash(
            'sha256',
            'coretsia-artifact-generation',
        );
        $generationId = ArtifactGenerationId::fromString($value);

        self::assertSame($value, $generationId->value());
        self::assertSame($value, (string)$generationId);
        self::assertTrue(
            $generationId->equals(new ArtifactGenerationId($value)),
        );
    }

    #[DataProvider('invalidGenerationIdProvider')]
    public function testRejectsValuesOutsideExactLowercaseSha256Domain(
        string $value,
    ): void {
        try {
            new ArtifactGenerationId($value);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(
                'artifact-generation-id-invalid',
                $exception->getMessage(),
            );
            self::assertStringNotContainsString(
                $value,
                $exception->getMessage(),
            );

            return;
        }

        self::fail(
            'ArtifactGenerationId must reject an invalid generation id.',
        );
    }

    /**
     * @return iterable<string, array{0:string}>
     */
    public static function invalidGenerationIdProvider(): iterable
    {
        yield 'uppercase' => [
            \str_repeat('A', 64),
        ];

        yield '63-characters' => [
            \str_repeat('a', 63),
        ];

        yield '65-characters' => [
            \str_repeat('a', 65),
        ];

        yield 'non-hex-character' => [
            \str_repeat('a', 63) . 'g',
        ];

        yield 'sha256-prefix' => [
            'sha256:' . \str_repeat('a', 64),
        ];

        yield 'leading-whitespace' => [
            ' ' . \str_repeat('a', 64),
        ];

        yield 'trailing-whitespace' => [
            \str_repeat('a', 64) . ' ',
        ];
    }
}
