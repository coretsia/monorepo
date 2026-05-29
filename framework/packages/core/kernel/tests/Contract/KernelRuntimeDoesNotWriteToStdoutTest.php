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

use Coretsia\Kernel\Runtime\KernelRuntime;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class KernelRuntimeDoesNotWriteToStdoutTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_FUNCTIONS = [
        'var_dump',
        'print_r',
        'printf',
        'error_log',
    ];

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_CONSTANTS = [
        'STDOUT',
        'STDERR',
    ];

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_STREAMS = [
        'php://stdout',
        'php://stderr',
        'php://output',
    ];

    public function testKernelRuntimeAndProviderSourceDoNotWriteToStdoutOrStderr(): void
    {
        $violations = [];

        foreach ($this->sourceFiles() as $file) {
            $source = \file_get_contents($file);

            self::assertIsString($source);

            $violations = [
                ...$violations,
                ...self::sourceViolations($source, $file),
            ];
        }

        self::assertSame(
            [],
            $violations,
            "Kernel runtime/provider diagnostics must go through deterministic exceptions/results or logging ports, not stdout/stderr.\n"
            . self::formatViolations($violations),
        );
    }

    public function testScannedFilesAreLimitedToRuntimeAndProviderSourceOnly(): void
    {
        $files = $this->sourceFiles();

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $normalized = self::normalizePath($file);

            self::assertStringContainsString('/src/', $normalized);

            self::assertTrue(
                \str_contains($normalized, '/src/Runtime/')
                || \str_contains($normalized, '/src/Provider/'),
                \sprintf(
                    'Unexpected scanned file "%s". Only src/Runtime/** and src/Provider/** should be scanned.',
                    $file,
                ),
            );

            self::assertStringNotContainsString('/tests/', $normalized);
            self::assertStringNotContainsString('/fixtures/', $normalized);
            self::assertStringNotContainsString('/Fixtures/', $normalized);
        }
    }

    public function testGuardAllowsLoggerPortUsage(): void
    {
        $source = <<<'PHP'
<?php

final class Example
{
    public function run(): void
    {
        $this->logger->info('kernel.uow', [
            'operation' => 'http',
            'outcome' => 'success',
        ]);
    }
}
PHP;

        self::assertSame(
            [],
            self::sourceViolations($source, 'synthetic/logger-port.php'),
        );
    }

    #[DataProvider('forbiddenOutputSamples')]
    public function testGuardDetectsForbiddenOutputPrimitive(
        string $source,
        string $expectedNeedle,
    ): void {
        $violations = self::sourceViolations($source, 'synthetic/forbidden.php');

        self::assertNotSame([], $violations);

        $formatted = self::formatViolations($violations);

        self::assertStringContainsString($expectedNeedle, $formatted);
    }

    /**
     * @return iterable<string, array{0:string,1:string}>
     */
    public static function forbiddenOutputSamples(): iterable
    {
        yield 'echo' => [
            <<<'PHP'
<?php

echo 'debug';
PHP,
            'echo',
        ];

        yield 'print' => [
            <<<'PHP'
<?php

print 'debug';
PHP,
            'print',
        ];

        yield 'var_dump' => [
            <<<'PHP'
<?php

var_dump(['debug' => true]);
PHP,
            'var_dump',
        ];

        yield 'print_r' => [
            <<<'PHP'
<?php

print_r(['debug' => true]);
PHP,
            'print_r',
        ];

        yield 'printf' => [
            <<<'PHP'
<?php

printf('debug');
PHP,
            'printf',
        ];

        yield 'error_log' => [
            <<<'PHP'
<?php

error_log('debug');
PHP,
            'error_log',
        ];

        yield 'STDOUT' => [
            <<<'PHP'
<?php

fwrite(STDOUT, 'debug');
PHP,
            'STDOUT',
        ];

        yield 'STDERR' => [
            <<<'PHP'
<?php

fwrite(STDERR, 'debug');
PHP,
            'STDERR',
        ];

        yield 'php://stdout' => [
            <<<'PHP'
<?php

$stream = fopen('php://stdout', 'wb');
PHP,
            'php://stdout',
        ];

        yield 'php://stderr' => [
            <<<'PHP'
<?php

$stream = fopen('php://stderr', 'wb');
PHP,
            'php://stderr',
        ];

        yield 'php://output' => [
            <<<'PHP'
<?php

$stream = fopen('php://output', 'wb');
PHP,
            'php://output',
        ];
    }

    /**
     * @return list<non-empty-string>
     */
    private function sourceFiles(): array
    {
        $packageRoot = $this->kernelPackageRoot();

        $roots = [
            $packageRoot . '/src/Runtime',
            $packageRoot . '/src/Provider',
        ];

        $files = [];

        foreach ($roots as $root) {
            self::assertDirectoryExists($root);

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $root,
                    \FilesystemIterator::SKIP_DOTS,
                ),
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                    continue;
                }

                if ($fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $path = $fileInfo->getPathname();

                if ($path === '') {
                    continue;
                }

                $normalized = self::normalizePath($path);

                if (
                    \str_contains($normalized, '/tests/')
                    || \str_contains($normalized, '/fixtures/')
                    || \str_contains($normalized, '/Fixtures/')
                ) {
                    continue;
                }

                $files[] = $path;
            }
        }

        \usort(
            $files,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertNotSame([], $files);

        /** @var list<non-empty-string> $files */
        return $files;
    }

    /**
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function sourceViolations(string $source, string $file): array
    {
        $tokens = \token_get_all($source);
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if ($tokenId === \T_ECHO) {
                $violations[] = self::violation(
                    file: $file,
                    line: $line,
                    token: 'echo',
                    message: 'Kernel runtime/provider source must not use echo.',
                );

                continue;
            }

            if ($tokenId === \T_PRINT) {
                $violations[] = self::violation(
                    file: $file,
                    line: $line,
                    token: 'print',
                    message: 'Kernel runtime/provider source must not use print.',
                );

                continue;
            }

            if ($tokenId === \T_STRING) {
                $normalized = \strtolower($text);

                if (\in_array($normalized, self::FORBIDDEN_FUNCTIONS, true)
                    && self::looksLikeFunctionCall($tokens, $index)
                ) {
                    $violations[] = self::violation(
                        file: $file,
                        line: $line,
                        token: $text,
                        message: \sprintf(
                            'Kernel runtime/provider source must not call %s().',
                            $text,
                        ),
                    );

                    continue;
                }

                if (\in_array($text, self::FORBIDDEN_CONSTANTS, true)) {
                    $violations[] = self::violation(
                        file: $file,
                        line: $line,
                        token: $text,
                        message: \sprintf(
                            'Kernel runtime/provider source must not reference %s.',
                            $text,
                        ),
                    );
                }

                continue;
            }

            if ($tokenId === \T_CONSTANT_ENCAPSED_STRING) {
                foreach (self::FORBIDDEN_STREAMS as $stream) {
                    if (\str_contains($text, $stream)) {
                        $violations[] = self::violation(
                            file: $file,
                            line: $line,
                            token: $stream,
                            message: \sprintf(
                                'Kernel runtime/provider source must not reference %s.',
                                $stream,
                            ),
                        );
                    }
                }
            }
        }

        return $violations;
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     */
    private static function looksLikeFunctionCall(array $tokens, int $index): bool
    {
        $next = self::nextMeaningfulToken($tokens, $index + 1);

        if ($next !== '(') {
            return false;
        }

        $previous = self::previousMeaningfulToken($tokens, $index - 1);

        if ($previous === null) {
            return true;
        }

        if (\is_array($previous)) {
            return !\in_array(
                $previous[0],
                [
                    \T_OBJECT_OPERATOR,
                    \T_DOUBLE_COLON,
                    \T_FUNCTION,
                ],
                true,
            );
        }

        return $previous !== '?->';
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     *
     * @return string|array{0:int,1:string,2:int}|null
     */
    private static function nextMeaningfulToken(array $tokens, int $index): string|array|null
    {
        $count = \count($tokens);

        for ($i = $index; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (self::isIgnorableToken($token)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     *
     * @return string|array{0:int,1:string,2:int}|null
     */
    private static function previousMeaningfulToken(array $tokens, int $index): string|array|null
    {
        for ($i = $index; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (self::isIgnorableToken($token)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private static function isIgnorableToken(mixed $token): bool
    {
        return \is_array($token)
            && \in_array(
                $token[0],
                [
                    \T_WHITESPACE,
                    \T_COMMENT,
                    \T_DOC_COMMENT,
                ],
                true,
            );
    }

    /**
     * @return array{file:string,line:int,token:string,message:string}
     */
    private static function violation(
        string $file,
        int $line,
        string $token,
        string $message,
    ): array {
        return [
            'file' => self::normalizePath($file),
            'line' => $line,
            'token' => $token,
            'message' => $message,
        ];
    }

    /**
     * @param list<array{file:string,line:int,token:string,message:string}> $violations
     */
    private static function formatViolations(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        $lines = [];

        foreach ($violations as $violation) {
            $lines[] = \sprintf(
                '- %s:%d [%s] %s',
                $violation['file'],
                $violation['line'],
                $violation['token'],
                $violation['message'],
            );
        }

        return \implode("\n", $lines);
    }

    private function kernelPackageRoot(): string
    {
        $runtimeFile = new ReflectionClass(KernelRuntime::class)->getFileName();

        self::assertIsString($runtimeFile);

        return \dirname($runtimeFile, 3);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
