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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Ops\KernelOpsFacade;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsCacheVerifyReportsAllFourGenerationFilesTest extends TestCase
{
    public function testVerifyProjectionContainsExactlySafeFourArtifactEntriesInVerifierOrder(): void
    {
        $input = [
            'expectedGenerationId' => 'expected-001',
            'currentGenerationId' => 'current-001',
            'outcome' => 'dirty',
            'artifacts' => [
                self::artifact('config', 'config.php', 100, 99),
                self::artifact('module-manifest', 'module-manifest.php', 80, 80),
                self::artifact('artifact-generation', 'generation-manifest.php', 70, null),
                self::artifact('container', 'container.php', 120, 120),
            ],
        ];

        $method = new ReflectionMethod(KernelOpsFacade::class, 'verifyData');
        $data = $method->invoke(null, $input);

        self::assertSame(
            ['artifacts', 'current_generation_id', 'expected_generation_id', 'state'],
            \array_keys($data),
        );
        self::assertSame(
            ['config', 'module-manifest', 'artifact-generation', 'container'],
            \array_column($data['artifacts'], 'name'),
        );

        foreach ($data['artifacts'] as $artifact) {
            self::assertSame(
                ['basename', 'existing_byte_count', 'expected_byte_count', 'name', 'reason', 'status'],
                \array_keys($artifact),
            );
            self::assertArrayNotHasKey('path', $artifact);
        }
    }

    private static function artifact(
        string $name,
        string $basename,
        int $expected,
        ?int $existing,
    ): array {
        return [
            'name' => $name,
            'basename' => $basename,
            'status' => $existing === null ? 'dirty' : 'clean',
            'reason' => $existing === null ? 'missing' : 'ok',
            'expectedBytes' => $expected,
            'existingBytes' => $existing,
            'path' => '/private/must-not-leak/' . $basename,
        ];
    }
}
