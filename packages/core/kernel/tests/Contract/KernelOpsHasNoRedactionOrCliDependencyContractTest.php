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

final class KernelOpsHasNoRedactionOrCliDependencyContractTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private const array FORBIDDEN_CODE_PATTERNS = [
        'SensitiveDataRedactorInterface' => '/\bSensitiveDataRedactorInterface\b/',
        'redactor-or-redaction-service' => '/\b[A-Za-z_][A-Za-z0-9_]*(?:Redactor|Redaction)[A-Za-z0-9_]*\b/',
        'platform-namespace' => '/\bCoretsia\\\\Platform\\\\/',
        'contracts-cli-namespace' => '/\bCoretsia\\\\Contracts\\\\Cli\\\\/',
        'symfony-console-namespace' => '/\bSymfony\\\\Component\\\\Console\\\\/',
        'cli-output-interface' => '/\bOutputInterface\b/',
        'cli-input-interface' => '/\bInputInterface\b/',
        'cli-formatter' => '/\b[A-Za-z_][A-Za-z0-9_]*Formatter[A-Za-z0-9_]*\b/',
        'ansi-or-terminal' => '/\b(?:Ansi|Terminal)[A-Za-z0-9_]*\b/',
    ];

    /**
     * @var array<string, string>
     */
    private const array FORBIDDEN_SOURCE_PATTERNS = [
        'platform-redaction-package-path' => '~(?:^|[^A-Za-z0-9_])platform/redaction(?:[^A-Za-z0-9_]|$)~i',
        'platform-redaction-composer-package' => '~\bcoretsia/platform-redaction\b~i',
        'platform-redaction-namespace' => '~\bCoretsia\\\\Platform\\\\Redaction(?:\\\\|\b)~',
        'platform-namespace' => '~\bCoretsia\\\\Platform\\\\~',
        'contracts-cli-namespace' => '~\bCoretsia\\\\Contracts\\\\Cli\\\\~',
        'symfony-console-namespace' => '~\bSymfony\\\\Component\\\\Console\\\\~',
        'cli-output-or-input' => '~\b(?:OutputInterface|InputInterface)\b~',
        'cli-formatter' => '~\b[A-Za-z_][A-Za-z0-9_]*Formatter[A-Za-z0-9_]*\b~',
        'ansi-or-terminal' => '~\b(?:Ansi|Terminal)[A-Za-z0-9_]*\b~',
    ];

    public function testKernelOpsHasNoRedactionOrCliDependency(): void
    {
        $violations = [];

        foreach (self::kernelOpsSourceFiles() as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            $withoutComments = self::phpWithoutComments($source);
            $code = self::phpCodeWithoutCommentsAndStrings($source);
            $relativePath = self::relativeToKernelRoot($file);

            foreach (self::FORBIDDEN_CODE_PATTERNS as $label => $pattern) {
                if (\preg_match($pattern, $code) !== 1) {
                    continue;
                }

                $violations[] = $relativePath . ': forbidden Kernel Ops dependency: ' . $label;
            }

            foreach (self::FORBIDDEN_SOURCE_PATTERNS as $label => $pattern) {
                if (\preg_match($pattern, $withoutComments) !== 1) {
                    continue;
                }

                $violations[] = $relativePath . ': forbidden Kernel Ops dependency: ' . $label;
            }
        }

        \sort($violations, \SORT_STRING);

        self::assertSame(
            [],
            $violations,
            "Kernel Ops must remain transport-neutral and safe by construction without CLI or redaction dependencies.\n"
            . \implode("\n", $violations),
        );
    }

    public function testKernelPackageDoesNotRequirePlatformOrRedactionPackage(): void
    {
        $composerPath = self::kernelRoot() . '/composer.json';

        self::assertFileExists($composerPath);

        $contents = \file_get_contents($composerPath);

        self::assertIsString($contents);

        $composer = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($composer);

        foreach (['require', 'require-dev'] as $section) {
            $requirements = $composer[$section] ?? [];

            self::assertIsArray($requirements);

            foreach (\array_keys($requirements) as $package) {
                self::assertIsString($package);
                self::assertFalse(
                    \str_starts_with($package, 'coretsia/platform-'),
                    $section . ' must not introduce platform package dependency "' . $package . '" into core/kernel.',
                );
                self::assertNotSame('coretsia/platform-redaction', $package);
            }
        }

        self::assertStringNotContainsString('platform/redaction', $contents);
    }

    public function testKernelOpsDoesNotResolveAnyRedactionService(): void
    {
        foreach (self::kernelOpsSourceFiles() as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            $code = self::phpWithoutComments($source);

            self::assertDoesNotMatchRegularExpression(
                '/(?:->get|::service|::[A-Za-z_][A-Za-z0-9_]*Service)\s*\([^;]*(?:redactor|redaction)/is',
                $code,
                self::relativeToKernelRoot($file) . ' must not resolve a redaction service.',
            );
        }
    }

    /**
     * @return list<string>
     */
    private static function kernelOpsSourceFiles(): array
    {
        $opsRoot = self::kernelRoot() . '/src/Ops';

        self::assertDirectoryExists($opsRoot);

        $files = self::phpFiles($opsRoot);
        $files[] = self::kernelRoot() . '/src/Provider/KernelServiceFactory.php';
        $files = \array_values(\array_unique($files));

        \sort($files, \SORT_STRING);

        return $files;
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $files[] = \str_replace('\\', '/', $fileInfo->getPathname());
        }

        \sort($files, \SORT_STRING);

        return $files;
    }

    private static function phpWithoutComments(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if ($token[0] === \T_COMMENT || $token[0] === \T_DOC_COMMENT) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function phpCodeWithoutCommentsAndStrings(string $source): string
    {
        $tokens = \token_get_all($source);
        $out = '';

        foreach ($tokens as $token) {
            if (\is_string($token)) {
                $out .= $token;

                continue;
            }

            if (
                $token[0] === \T_COMMENT
                || $token[0] === \T_DOC_COMMENT
                || $token[0] === \T_CONSTANT_ENCAPSED_STRING
                || $token[0] === \T_ENCAPSED_AND_WHITESPACE
            ) {
                $out .= ' ';

                continue;
            }

            $out .= $token[1];
        }

        return $out;
    }

    private static function kernelRoot(): string
    {
        $root = \realpath(__DIR__ . '/../..');

        self::assertIsString($root);

        return \str_replace('\\', '/', $root);
    }

    private static function relativeToKernelRoot(string $file): string
    {
        $root = self::kernelRoot();
        $file = \str_replace('\\', '/', $file);

        if (\str_starts_with($file, $root . '/')) {
            return \substr($file, \strlen($root) + 1);
        }

        return $file;
    }
}
