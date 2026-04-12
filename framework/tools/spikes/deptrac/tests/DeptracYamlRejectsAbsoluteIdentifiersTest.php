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

namespace Coretsia\Tools\Spikes\deptrac\tests;

use Coretsia\Tools\Spikes\deptrac\DeptracGenerate;
use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\TestCase;

final class DeptracYamlRejectsAbsoluteIdentifiersTest extends TestCase
{
    public function testYamlRejectsPackageIdWithBackslash(): void
    {
        $index = [
            'schema_version' => 1,
            'repo_root' => 'repo',
            'packages' => [
                [
                    'package_id' => 'demo\\pkg-a',
                    'composer' => 'coretsia/demo-pkg-a',
                    'path' => 'packages/demo/pkg-a',
                    'module_id' => 'demo.pkg_a',
                    'deps' => [],
                    'allowlist' => ['tests/**'],
                ],
            ],
        ];

        try {
            DeptracGenerate::generateYamlFromIndex($index, 'deptrac_min/custom_index.php');
            self::fail('Expected DeterministicException for backslash in package_id');
        } catch (DeterministicException $e) {
            $this->assertDeterministicErrorCodeLike($e, ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID);
        }
    }

    public function testYamlRejectsDepContainingPosixHomePattern(): void
    {
        $index = [
            'schema_version' => 1,
            'repo_root' => 'repo',
            'packages' => [
                [
                    'package_id' => 'demo/pkg-a',
                    'composer' => 'coretsia/demo-pkg-a',
                    'path' => 'packages/demo/pkg-a',
                    'module_id' => 'demo.pkg_a',
                    'deps' => ['demo/home/user'], // contains "/home/" => forbidden
                    'allowlist' => ['tests/**'],
                ],
            ],
        ];

        try {
            DeptracGenerate::generateYamlFromIndex($index, 'deptrac_min/custom_index.php');
            self::fail('Expected DeterministicException for absolute-like dep pattern');
        } catch (DeterministicException $e) {
            $this->assertDeterministicErrorCodeLike($e, ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID);
        }
    }

    public function testYamlRejectsWindowsDriveLikeDep(): void
    {
        $index = [
            'schema_version' => 1,
            'repo_root' => 'repo',
            'packages' => [
                [
                    'package_id' => 'demo/pkg-a',
                    'composer' => 'coretsia/demo-pkg-a',
                    'path' => 'packages/demo/pkg-a',
                    'module_id' => 'demo.pkg_a',
                    'deps' => ['C:/tmp/secret'], // drive absolute anywhere => forbidden
                    'allowlist' => ['tests/**'],
                ],
            ],
        ];

        try {
            DeptracGenerate::generateYamlFromIndex($index, 'deptrac_min/custom_index.php');
            self::fail('Expected DeterministicException for windows-drive dep pattern');
        } catch (DeterministicException $e) {
            $this->assertDeterministicErrorCodeLike($e, ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID);
        }
    }

    private function assertDeterministicErrorCodeLike(\Throwable $e, string $expectedCode): void
    {
        $msg = $e->getMessage();

        if (method_exists($e, 'code')) {
            /** @var mixed $code */
            $code = $e->code();
            self::assertSame($expectedCode, $code);
            return;
        }

        if (method_exists($e, 'getErrorCode')) {
            /** @var mixed $code */
            $code = $e->getErrorCode();
            self::assertSame($expectedCode, $code);
            return;
        }

        self::assertStringContainsString($expectedCode, $msg, 'Deterministic error code must be visible');
    }
}
