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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Header\ArtifactHeader;
use PHPUnit\Framework\TestCase;

final class ArtifactsHeaderShapeContractTest extends TestCase
{
    public function testHeaderExportsCanonicalRequiredShapeWithoutTimestampsOrHostData(): void
    {
        $header = ArtifactHeader::create(
            name: 'config',
            schemaVersion: 1,
            fingerprint: \str_repeat('a', 64),
            generator: 'core/kernel/artifacts',
        );

        self::assertSame(
            [
                'name' => 'config',
                'schemaVersion' => 1,
                'fingerprint' => \str_repeat('a', 64),
                'generator' => 'core/kernel/artifacts',
            ],
            $header->toArray(),
        );

        self::assertSame(
            [
                'name',
                'schemaVersion',
                'fingerprint',
                'generator',
            ],
            \array_keys($header->toArray()),
        );

        self::assertArrayNotHasKey('createdAt', $header->toArray());
        self::assertArrayNotHasKey('timestamp', $header->toArray());
        self::assertArrayNotHasKey('generatedAt', $header->toArray());
        self::assertArrayNotHasKey('hostname', $header->toArray());
        self::assertArrayNotHasKey('absolutePath', $header->toArray());
    }

    public function testHeaderMayExportOptionalRequiresMapOnlyWhenProvided(): void
    {
        $header = ArtifactHeader::create(
            name: 'container',
            schemaVersion: 1,
            fingerprint: \str_repeat('b', 64),
            generator: 'core/kernel/artifacts',
            requires: [
                'config@1' => true,
                'module-manifest@1' => true,
            ],
        );

        self::assertSame(
            [
                'name' => 'container',
                'schemaVersion' => 1,
                'fingerprint' => \str_repeat('b', 64),
                'generator' => 'core/kernel/artifacts',
                'requires' => [
                    'config@1' => true,
                    'module-manifest@1' => true,
                ],
            ],
            $header->toArray(),
        );
    }

    public function testHeaderRejectsUnsafeNamesFingerprintsAndGeneratorsWithoutLeakingRawValues(): void
    {
        foreach (
            [
                static fn (): ArtifactHeader => ArtifactHeader::create(
                    name: 'routes.php',
                    schemaVersion: 1,
                    fingerprint: \str_repeat('a', 64),
                    generator: 'core/kernel/artifacts',
                ),
                static fn (): ArtifactHeader => ArtifactHeader::create(
                    name: 'config',
                    schemaVersion: 0,
                    fingerprint: \str_repeat('a', 64),
                    generator: 'core/kernel/artifacts',
                ),
                static fn (): ArtifactHeader => ArtifactHeader::create(
                    name: 'config',
                    schemaVersion: 1,
                    fingerprint: '/private/fingerprint',
                    generator: 'core/kernel/artifacts',
                ),
                static fn (): ArtifactHeader => ArtifactHeader::create(
                    name: 'config',
                    schemaVersion: 1,
                    fingerprint: \str_repeat('a', 64),
                    generator: '/private/generator',
                ),
            ] as $operation
        ) {
            try {
                $operation();
                self::fail('Expected InvalidArgumentException was not thrown.');
            } catch (\InvalidArgumentException $exception) {
                self::assertStringNotContainsString('routes.php', $exception->getMessage());
                self::assertStringNotContainsString('/private/fingerprint', $exception->getMessage());
                self::assertStringNotContainsString('/private/generator', $exception->getMessage());
            }
        }
    }
}
