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

namespace Coretsia\Tools\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class ReservedTagsRegistryGateTest extends TestCase
{
    /**
     * @var list<string>
     */
    private array $tmpRoots = [];

    protected function tearDown(): void
    {
        foreach (\array_reverse($this->tmpRoots) as $tmpRoot) {
            $this->removeDirectory($tmpRoot);
        }

        $this->tmpRoots = [];

        parent::tearDown();
    }

    public function testPassesWhenDocsRegistryAndReservedTagsMatch(): void
    {
        $root = $this->createFixtureRoot();

        $result = $this->runGate($root);

        self::assertSame(0, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame('', $result['stderr']);
    }

    public function testFailsWhenReservedTagsMissesTagFromDocs(): void
    {
        $root = $this->createFixtureRoot(
            reservedTagsConstants: [
                'KERNEL_RESET' => 'kernel.reset',
            ],
        );

        $result = $this->runGate($root);

        $this->assertGateFailure(
            $result,
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/core/foundation/src/Tag/ReservedTags.php: reserved-tag-constant-missing:CLI_COMMAND',
            ],
        );
    }

    public function testFailsWhenReservedTagsConstValueDiffers(): void
    {
        $root = $this->createFixtureRoot(
            reservedTagsConstants: [
                'CLI_COMMAND' => 'cli.command.wrong',
                'KERNEL_RESET' => 'kernel.reset',
            ],
        );

        $result = $this->runGate($root);

        $this->assertGateFailure(
            $result,
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/core/foundation/src/Tag/ReservedTags.php: reserved-tag-constant-value-mismatch:CLI_COMMAND',
                'framework/packages/core/foundation/src/Tag/ReservedTags.php: reserved-tag-extra-public-constant:CLI_COMMAND',
            ],
        );
    }

    public function testFailsWhenAnyPackageHasProviderTagsFile(): void
    {
        $root = $this->createFixtureRoot();

        $this->writeFile(
            $root . '/framework/packages/platform/example/src/Provider/Tags.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Example\Provider;

final class Tags
{
}

PHP,
        );

        $result = $this->runGate($root);

        $this->assertGateFailure(
            $result,
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/platform/example/src/Provider/Tags.php: provider-tags-file-forbidden',
            ],
        );
    }

    public function testFailsWhenAnotherPackageDefinesReservedTagLiteralConstant(): void
    {
        $root = $this->createFixtureRoot();

        $this->writeFile(
            $root . '/framework/packages/platform/example/src/Internal/LocalTags.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Example\Internal;

final class LocalTags
{
    public const string CLI_COMMAND = 'cli.command';
}

PHP,
        );

        $result = $this->runGate($root);

        $this->assertGateFailure(
            $result,
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/platform/example/src/Internal/LocalTags.php: reserved-tag-local-constant-forbidden:CLI_COMMAND',
            ],
        );
    }

    public function testFailsWhenAnotherPackageDefinesReservedTagsAliasConstant(): void
    {
        $root = $this->createFixtureRoot();

        $this->writeFile(
            $root . '/framework/packages/platform/example/src/Internal/LocalTags.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Example\Internal;

use Coretsia\Foundation\Tag\ReservedTags;

final class LocalTags
{
    public const string CLI_COMMAND = ReservedTags::CLI_COMMAND;
}

PHP,
        );

        $result = $this->runGate($root);

        $this->assertGateFailure(
            $result,
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/platform/example/src/Internal/LocalTags.php: reserved-tag-local-constant-forbidden:CLI_COMMAND',
            ],
        );
    }

    public function testFailureOutputIsDeterministicRepoRelativeSortedAndDoesNotContainAbsolutePaths(): void
    {
        $root = $this->createFixtureRoot(
            reservedTagsConstants: [
                'KERNEL_RESET' => 'kernel.reset',
            ],
        );

        $this->writeFile(
            $root . '/framework/packages/platform/zeta/src/Provider/Tags.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Zeta\Provider;

final class Tags
{
}

PHP,
        );

        $this->writeFile(
            $root . '/framework/packages/platform/alpha/src/Internal/LocalTags.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Alpha\Internal;

final class LocalTags
{
    public const string CLI_COMMAND = 'cli.command';
}

PHP,
        );

        $first = $this->runGate($root);
        $second = $this->runGate($root);

        self::assertSame(1, $first['exitCode']);
        self::assertSame(1, $second['exitCode']);

        self::assertSame('', $first['stdout']);
        self::assertSame('', $second['stdout']);
        self::assertSame($first['stderr'], $second['stderr']);

        $lines = $this->outputLines($first['stderr']);

        self::assertSame(
            [
                'CORETSIA_RESERVED_TAGS_REGISTRY_DRIFT',
                'framework/packages/core/foundation/src/Tag/ReservedTags.php: reserved-tag-constant-missing:CLI_COMMAND',
                'framework/packages/platform/alpha/src/Internal/LocalTags.php: reserved-tag-local-constant-forbidden:CLI_COMMAND',
                'framework/packages/platform/zeta/src/Provider/Tags.php: provider-tags-file-forbidden',
            ],
            $lines,
        );

        $diagnostics = \array_slice($lines, 1);
        $sortedDiagnostics = $diagnostics;
        \sort($sortedDiagnostics, \SORT_STRING);

        self::assertSame($sortedDiagnostics, $diagnostics);

        foreach ($lines as $line) {
            self::assertStringNotContainsString('\\', $line);
            self::assertStringNotContainsString($this->normalizePath($root), $line);
            self::assertFalse(\str_starts_with($line, '/'), 'Output must not contain absolute Unix paths.');
            self::assertMatchesRegularExpression('/\A(?:CORETSIA_[A-Z0-9_]+|framework\/.+)\z/', $line);
        }

        self::assertNotSame('', $first['stderr']);
    }

    /**
     * @param array{exitCode:int, stdout:string, stderr:string} $result
     * @param list<string> $expectedLines
     */
    private function assertGateFailure(array $result, array $expectedLines): void
    {
        self::assertSame(1, $result['exitCode']);

        self::assertSame('', $result['stdout']);

        self::assertSame(
            $expectedLines,
            $this->outputLines($result['stderr']),
        );
    }

    /**
     * @param array<string, string> $reservedTagsConstants
     */
    private function createFixtureRoot(
        array $reservedTagsConstants = [
            'CLI_COMMAND' => 'cli.command',
            'KERNEL_RESET' => 'kernel.reset',
        ],
    ): string {
        $root = $this->newTemporaryDirectory();

        $this->writeFile(
            $root . '/docs/ssot/tags.md',
            <<<'MD'
# Tags SSoT

## Reserved Tag Registry (MUST)

| tag | semantic owner |
|---|---|
| `cli.command` | `platform/cli` |
| `kernel.reset` | `core/foundation` |

MD,
        );

        $this->writeReservedTags($root, $reservedTagsConstants);

        $this->writeFile(
            $root . '/framework/packages/platform/example/src/ExampleService.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Example;

final class ExampleService
{
}

PHP,
        );

        return $root;
    }

    /**
     * @param array<string, string> $constants
     */
    private function writeReservedTags(string $root, array $constants): void
    {
        $constantLines = [];

        foreach ($constants as $name => $value) {
            $constantLines[] = \sprintf(
                '    public const string %s = %s;',
                $name,
                \var_export($value, true),
            );
        }

        $constantsCode = \implode("\n", $constantLines);

        $this->writeFile(
            $root . '/framework/packages/core/foundation/src/Tag/ReservedTags.php',
            <<<PHP
<?php

declare(strict_types=1);

namespace Coretsia\Foundation\Tag;

final class ReservedTags
{
{$constantsCode}
}

PHP,
        );
    }

    /**
     * @return array{exitCode:int, stdout:string, stderr:string}
     */
    private function runGate(string $root): array
    {
        $command = [
            \PHP_BINARY,
            $this->frameworkRoot() . '/tools/gates/reserved_tags_registry_gate.php',
            '--root=' . $root,
        ];

        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($command, $descriptorSpec, $pipes, $this->repoRoot());

        if (!\is_resource($process)) {
            self::fail('Failed to start reserved tags registry gate process.');
        }

        \fclose($pipes[0]);

        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $exitCode = \proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [
            'exitCode' => $exitCode,
            'stdout' => $this->normalizeNewlines($stdout),
            'stderr' => $this->normalizeNewlines($stderr),
        ];
    }

    /**
     * @return list<string>
     */
    private function outputLines(string $output): array
    {
        $output = \trim($output);

        if ($output === '') {
            return [];
        }

        /** @var list<string> $lines */
        $lines = \preg_split('/\n/', $output) ?: [];

        return $lines;
    }

    private function frameworkRoot(): string
    {
        return $this->normalizePath(\dirname(__DIR__, 3));
    }

    private function repoRoot(): string
    {
        return $this->normalizePath(\dirname($this->frameworkRoot()));
    }

    private function newTemporaryDirectory(): string
    {
        $base = \rtrim(\sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'coretsia-reserved-tags-gate-'
            . \bin2hex(\random_bytes(8));

        if (!\mkdir($base, 0777, true) && !\is_dir($base)) {
            self::fail('Failed to create temporary directory.');
        }

        $path = $this->normalizePath($base);
        $this->tmpRoots[] = $path;

        return $path;
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = \dirname($path);

        if (!\is_dir($dir) && !\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('Failed to create fixture directory: ' . $dir);
        }

        if (\file_put_contents($path, $contents) === false) {
            self::fail('Failed to write fixture file: ' . $path);
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }

            if ($fileInfo->isDir() && !$fileInfo->isLink()) {
                \rmdir($fileInfo->getPathname());
                continue;
            }

            \unlink($fileInfo->getPathname());
        }

        \rmdir($path);
    }

    private function normalizePath(string $path): string
    {
        return \rtrim(\str_replace('\\', '/', $path), '/');
    }

    private function normalizeNewlines(string $value): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $value);
    }
}
