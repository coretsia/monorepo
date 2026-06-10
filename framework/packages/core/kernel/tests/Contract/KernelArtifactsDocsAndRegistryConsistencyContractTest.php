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

use PHPUnit\Framework\TestCase;

final class KernelArtifactsDocsAndRegistryConsistencyContractTest extends TestCase
{
    public function testArtifactsAndFingerprintSsotDoesNotRedefineGlobalEnvelopeLaw(): void
    {
        $source = self::repoFile('docs/ssot/artifacts-and-fingerprint.md');
        $plain = self::markdownPlainText($source);

        self::assertStringContainsString(
            'MUST NOT redefine',
            $plain,
        );

        self::assertStringContainsString(
            'global artifact envelope',
            $plain,
        );

        self::assertStringContainsString(
            'canonical artifact envelope shape',
            $plain,
        );

        self::assertStringContainsString(
            'docs/ssot/artifacts.md',
            $plain,
        );

        self::assertStringNotContainsString(
            'The canonical artifact envelope is defined as:',
            $source,
        );

        self::assertStringNotContainsString(
            '| name | owner | schema',
            \strtolower($source),
        );
    }

    public function testCacheVerifySsotDoesNotRedefineArtifactRegistryRows(): void
    {
        $source = self::repoFile('docs/ssot/cache-verify.md');
        $plain = self::markdownPlainText($source);

        self::assertStringContainsString(
            'MUST NOT redefine',
            $plain,
        );

        self::assertStringContainsString(
            'artifact registry',
            $plain,
        );

        self::assertStringContainsString(
            'docs/ssot/artifacts.md',
            $plain,
        );

        self::assertStringNotContainsString(
            '| module-manifest@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| config@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| container@1 |',
            $source,
        );

        self::assertStringNotContainsString(
            '| routes@1 |',
            $source,
        );
    }

    public function testObservabilitySsotContainsRegisteredArtifactFingerprintAndCacheVerifyMetricNames(): void
    {
        $source = self::repoFile('docs/ssot/observability.md');

        foreach (
            [
                'kernel.artifacts_write_total',
                'kernel.artifacts_write_duration_ms',
                'kernel.fingerprint_calculate_total',
                'kernel.fingerprint_calculate_duration_ms',
                'kernel.cache_verify_total',
                'kernel.cache_verify_duration_ms',
            ] as $metricName
        ) {
            self::assertStringContainsString($metricName, $source);
        }

        foreach (
            [
                'kernel.artifacts_write',
                'kernel.fingerprint_calculate',
                'kernel.cache_verify',
            ] as $spanName
        ) {
            self::assertStringContainsString($spanName, $source);
        }

        self::assertStringContainsString('outcome', $source);
        self::assertStringNotContainsString('fingerprint` label', $source);
    }

    public function testKernelReadmeNoLongerListsConfigArtifactWritingAsOutOfScope(): void
    {
        $source = self::repoFile('framework/packages/core/kernel/README.md');

        $outOfScope = self::section($source, '**Out of scope:**', '## Runtime responsibilities');

        self::assertStringNotContainsString(
            'config artifact writing',
            \strtolower($outOfScope),
        );

        self::assertStringContainsString(
            'Kernel-owned artifact production',
            $source,
        );

        self::assertStringContainsString(
            'Kernel-owned cache verification',
            $source,
        );

        self::assertStringContainsString(
            'artifact/fingerprint/cache services are registered by `KernelServiceProvider` as factories only',
            $source,
        );
    }

    private static function section(string $source, string $startNeedle, string $endNeedle): string
    {
        $start = \strpos($source, $startNeedle);

        self::assertIsInt($start);

        $end = \strpos($source, $endNeedle, $start);

        self::assertIsInt($end);

        return \substr($source, $start, $end - $start);
    }

    private static function repoFile(string $relativePath): string
    {
        $path = self::repoRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function repoRoot(): string
    {
        return \dirname(__DIR__, 6);
    }

    private static function markdownPlainText(string $source): string
    {
        return \str_replace(
            [
                '**',
                '`',
            ],
            '',
            $source,
        );
    }
}
