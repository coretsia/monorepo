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

final class KernelArtifactsObservabilityPolicyContractTest extends TestCase
{
    public function testKernelArtifactFingerprintContainerCompileAndCacheObservabilityNamesAreExact(): void
    {
        $names = [];

        foreach (self::observabilityRuntimeSources() as $source) {
            \preg_match_all(
                '/private const string (?:SPAN|METRIC)_[A-Z0-9_]+ = \'([^\']+)\';/',
                $source,
                $matches,
            );

            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
        }

        $actual = \array_keys($names);
        \sort($actual, \SORT_STRING);

        self::assertSame(
            [
                'kernel.artifacts_write',
                'kernel.artifacts_write_duration_ms',
                'kernel.artifacts_write_total',
                'kernel.cache_verify',
                'kernel.cache_verify_duration_ms',
                'kernel.cache_verify_total',
                'kernel.container_compile',
                'kernel.container_compile_duration_ms',
                'kernel.container_compile_total',
                'kernel.fingerprint_calculate',
                'kernel.fingerprint_calculate_duration_ms',
                'kernel.fingerprint_calculate_total',
            ],
            $actual,
        );
    }

    public function testMetricLabelsUseOnlyOutcome(): void
    {
        foreach (self::observabilityRuntimeSourcesWithoutComments() as $relativePath => $source) {
            \preg_match_all('/\$labels\s*=\s*\[(.*?)];/s', $source, $matches);

            self::assertNotSame([], $matches[1], $relativePath . ' must build explicit metric labels.');

            foreach ($matches[1] as $labelBlock) {
                self::assertMatchesRegularExpression(
                    '/\A\s*\'outcome\'\s*=>\s*self::safeOutcome\(\$outcome\),?\s*\z/',
                    $labelBlock,
                    $relativePath . ' metric labels must contain only outcome.',
                );

                self::assertDoesNotMatchRegularExpression(
                    '/[\'"](path|artifact|app|env|preset|fingerprint|reason|exception)[\'"]\s*=>/',
                    $labelBlock,
                    $relativePath . ' must not use unsafe metric labels.',
                );
            }
        }
    }

    public function testNoMetricCallUsesInlineUnsafeLabels(): void
    {
        foreach (self::observabilityRuntimeSourcesWithoutComments() as $relativePath => $source) {
            foreach (
                [
                    'path',
                    'artifact',
                    'app',
                    'env',
                    'preset',
                    'fingerprint',
                    'reason',
                    'exception',
                ] as $forbiddenLabel
            ) {
                self::assertDoesNotMatchRegularExpression(
                    '/->(?:increment|observe)\([^;]*[\'"]' . \preg_quote($forbiddenLabel, '/') . '[\'"]\s*=>/s',
                    $source,
                    $relativePath . ' must not pass unsafe metric label `' . $forbiddenLabel . '`.',
                );
            }
        }
    }

    public function testObservabilityLogsDoNotUseUnsafeContextKeysOrExceptionRecording(): void
    {
        foreach (self::observabilityRuntimeSourcesWithoutComments() as $relativePath => $source) {
            self::assertStringNotContainsString(
                'recordException(',
                $source,
                $relativePath . ' must not record throwable objects into observability.',
            );

            foreach (
                [
                    'absolute_path',
                    'raw_path',
                    'raw_payload',
                    'payload',
                    'raw_config',
                    'config_value',
                    'raw_env',
                    'env_value',
                    'fingerprint',
                    'php_warning',
                    'warning_text',
                    'stack_trace',
                    'trace',
                    'previous',
                    'throwable',
                    'exception',
                ] as $forbiddenContextKey
            ) {
                self::assertDoesNotMatchRegularExpression(
                    '/[\'"]' . \preg_quote($forbiddenContextKey, '/') . '[\'"]\s*=>/',
                    $source,
                    $relativePath . ' must not log unsafe context key `' . $forbiddenContextKey . '`.',
                );
            }
        }
    }

    public function testOnlyExpectedKernelSourcesEmitTheseObservabilityOperations(): void
    {
        $sourceFiles = self::phpFiles(self::packageRoot() . '/src');
        $emitters = [];

        foreach ($sourceFiles as $path) {
            $source = self::stripPhpComments(self::readFile($path));

            if (
                \str_contains($source, 'kernel.artifacts_write')
                || \str_contains($source, 'kernel.fingerprint_calculate')
                || \str_contains($source, 'kernel.container_compile')
                || \str_contains($source, 'kernel.cache_verify')
            ) {
                $emitters[] = self::relativeToPackage($path);
            }
        }

        \sort($emitters, \SORT_STRING);

        self::assertSame(
            [
                'src/Artifacts/ArtifactWriter.php',
                'src/Artifacts/Fingerprint/FingerprintCalculator.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
                'src/Container/ContainerCompiler.php',
            ],
            $emitters,
        );
    }

    /**
     * @return list<string>
     */
    private static function observabilityRuntimeSources(): array
    {
        return \array_values(self::observabilityRuntimeSourcesByPath());
    }

    /**
     * @return array<string,string>
     */
    private static function observabilityRuntimeSourcesWithoutComments(): array
    {
        $withoutComments = [];

        foreach (self::observabilityRuntimeSourcesByPath() as $relativePath => $source) {
            $withoutComments[$relativePath] = self::stripPhpComments($source);
        }

        return $withoutComments;
    }

    /**
     * @return array<string,string>
     */
    private static function observabilityRuntimeSourcesByPath(): array
    {
        $sources = [];

        foreach (
            [
                'src/Artifacts/ArtifactWriter.php',
                'src/Artifacts/Fingerprint/FingerprintCalculator.php',
                'src/Artifacts/Verifier/CacheVerifier.php',
                'src/Container/ContainerCompiler.php',
            ] as $relativePath
        ) {
            $sources[$relativePath] = self::sourceFile($relativePath);
        }

        return $sources;
    }

    private static function stripPhpComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function sourceFile(string $relativePath): string
    {
        $path = self::packageRoot() . '/' . $relativePath;

        self::assertFileExists($path);

        return self::readFile($path);
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $directory,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        $files = [];

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private static function readFile(string $path): string
    {
        $source = \file_get_contents($path);

        self::assertIsString($source);

        return $source;
    }

    private static function relativeToPackage(string $path): string
    {
        return \str_replace('\\', '/', \substr($path, \strlen(self::packageRoot()) + 1));
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
