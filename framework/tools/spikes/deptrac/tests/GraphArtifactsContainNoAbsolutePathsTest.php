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

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;
use Coretsia\Tools\Spikes\deptrac\GraphArtifactBuilder;
use PHPUnit\Framework\TestCase;

final class GraphArtifactsContainNoAbsolutePathsTest extends TestCase
{
    public function testGeneratedDotSvgHtmlContainNoAbsolutePathsAndAreLfOnly(): void
    {
        $dir = self::createTempDir('coretsia_spikes_deptrac_graph_');
        try {
            GraphArtifactBuilder::buildToDirFromFixture('deptrac_min/package_index_ok.php', $dir);

            // MUST: use spikes deterministic IO wrapper (spikes io policy gate).
            $dot = DeterministicFile::readBytesExact($dir . '/deptrac_graph.dot');
            $svg = DeterministicFile::readBytesExact($dir . '/deptrac_graph.svg');
            $html = DeterministicFile::readBytesExact($dir . '/deptrac_graph.html');

            foreach (['dot' => $dot, 'svg' => $svg, 'html' => $html] as $kind => $content) {
                $this->assertLfOnlyAndFinalNewline($content, $kind);
                $this->assertNoAbsolutePathPatterns($content, $kind);
            }
        } finally {
            self::rmDir($dir);
        }
    }

    public function testBuilderRejectsAbsoluteIdentifiersDefensively(): void
    {
        $index = [
            'schema_version' => 1,
            'repo_root' => 'repo',
            'packages' => [
                [
                    'package_id' => '/home/user/leak',
                    'deps' => [],
                ],
            ],
        ];

        try {
            GraphArtifactBuilder::buildDotFromIndex($index, 'deptrac_min/custom.php');
            self::fail('Expected DeterministicException for absolute identifier');
        } catch (DeterministicException $e) {
            $this->assertDeterministicErrorCodeLike($e, ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID);
        }
    }

    private function assertNoAbsolutePathPatterns(string $content, string $kind): void
    {
        // POSIX absolute patterns mentioned in epic (segment-based to avoid false positives on normal ids).
        self::assertDoesNotMatchRegularExpression('~/(?:home|users)/~i', $content, $kind . ': contains POSIX home absolute pattern');

        // Windows drive-letter absolute: (?i)\b[A-Z]:(\\|/)  -> use char class [\\/]
        self::assertDoesNotMatchRegularExpression('~(?i)\b[A-Z]:[\\\\/]~', $content, $kind . ': contains Windows drive absolute pattern');

        // Windows UNC absolute: \\server\share\...
        self::assertDoesNotMatchRegularExpression('~\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+~', $content, $kind . ': contains UNC absolute pattern');
    }

    private function assertLfOnlyAndFinalNewline(string $text, string $kind): void
    {
        self::assertFalse(str_contains($text, "\r"), $kind . ': must be LF-only (no CR)');
        self::assertNotSame('', $text, $kind . ': must not be empty');
        self::assertSame("\n", substr($text, -1), $kind . ': must end with final newline');
    }

    private function assertDeterministicErrorCodeLike(\Throwable $e, string $expectedCode): void
    {
        // We don’t assume internal API of DeterministicException; still enforce that the code is observable.
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

    private static function createTempDir(string $prefix): string
    {
        $base = rtrim(sys_get_temp_dir(), '/\\');
        $dir = $base . '/' . $prefix . bin2hex(random_bytes(6));

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new \RuntimeException('failed_to_create_temp_dir');
        }

        return $dir;
    }

    private static function rmDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir . '/' . $name;

            if (is_dir($path)) {
                self::rmDir($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
