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

use Coretsia\Kernel\Config\ConfigMerger;
use Coretsia\Kernel\Config\DirectiveProcessor;
use Coretsia\Kernel\Config\Validation\ConfigNamespaceGuard;
use PHPUnit\Framework\TestCase;

final class ConfigMergerReplacementSemanticsTest extends TestCase
{
    public function testPlainListPatchReplacesExistingListWithoutImplicitMerge(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        self::assertSame(
            [
                'values' => [
                    'replacement',
                ],
            ],
            $merger->merge(
                [
                    'values' => [
                        'base-a',
                        'base-b',
                    ],
                ],
                [
                    'values' => [
                        'replacement',
                    ],
                ],
            ),
        );
    }

    public function testReplaceDirectiveReplacesExistingScalarValue(): void
    {
        $processor = self::processor();
        $merger = self::merger($processor);

        $patch = $processor->processRootSubtree(
            root: 'kernel',
            subtree: [
                'enabled' => [
                    '@replace' => false,
                ],
            ],
        );

        self::assertSame(
            [
                'enabled' => false,
            ],
            $merger->merge(
                [
                    'enabled' => true,
                ],
                $patch,
            ),
        );
    }

    private static function processor(): DirectiveProcessor
    {
        return new DirectiveProcessor(
            namespaceGuard: new ConfigNamespaceGuard([
                'coretsia',
                '_internal',
            ]),
        );
    }

    private static function merger(DirectiveProcessor $processor): ConfigMerger
    {
        return new ConfigMerger(
            directiveProcessor: $processor,
        );
    }
}
