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

namespace Coretsia\Tools\Spikes\workspace\tests;

use Coretsia\Tools\Spikes\workspace\ComposerJsonCanonicalizer;
use Coretsia\Tools\Spikes\workspace\WorkspacePolicy;
use PHPUnit\Framework\TestCase;

final class ComposerJsonCanonicalFormatTest extends TestCase
{
    public function testCanonicalOutputIsLfOnlyEndsWithFinalNewlineAndUsesTwoSpaceIndent(): void
    {
        $composer = [];
        $composer['name'] = 'acme/example';
        $composer['require'] = ['php' => '^8.4'];
        $composer['autoload'] = ['psr-4' => ['Acme\\Example\\' => 'src/']];

        $out = ComposerJsonCanonicalizer::encodeCanonical($composer);

        // LF-only + final newline
        self::assertFalse(\str_contains($out, "\r"));
        self::assertTrue(\str_ends_with($out, "\n"));

        // Pretty, 2 spaces: spot-check.
        $lines = \explode("\n", $out);

        $nameLine = self::firstLineContaining($lines, '"name"');
        self::assertNotNull($nameLine);
        self::assertTrue(\str_starts_with((string)$nameLine, '  "name"'));

        $phpLine = self::firstLineContaining($lines, '"php"');
        self::assertNotNull($phpLine);
        self::assertTrue(\str_starts_with((string)$phpLine, '    "php"'));

        // Global indentation check:
        // - no tabs anywhere
        // - for each non-empty line: leading spaces count is divisible by 2
        //   (canonical is 2-spaces per nesting level)
        self::assertCanonicalIndentationIsTwoSpacesGlobally($out);
    }

    public function testCanonicalOutputPreservesNonManagedKeyOrder(): void
    {
        // Intentionally weird top-level order:
        $composer = [];
        $composer['config'] = ['sort-packages' => true];
        $composer['description'] = 'x';
        $composer['name'] = 'acme/example';
        $composer['type'] = 'project';

        // Also preserve user-owned repo map key order (url then type).
        $composer['repositories'] = [
            [
                'url' => 'https://example.com/acme/custom.git',
                'type' => 'vcs',
            ],
        ];

        $out = ComposerJsonCanonicalizer::encodeCanonical($composer);

        $pConfig = self::pos($out, '"config"');
        $pDesc = self::pos($out, '"description"');
        $pName = self::pos($out, '"name"');
        $pType = self::pos($out, '"type"');

        self::assertTrue($pConfig < $pDesc);
        self::assertTrue($pDesc < $pName);
        self::assertTrue($pName < $pType);

        // User-owned map keys order preserved: "url" appears before "type" within that object.
        $pUrl = self::pos($out, '"url": "https://example.com/acme/custom.git"');
        $pRepoType = self::pos($out, '"type": "vcs"');

        self::assertTrue($pUrl < $pRepoType);
    }

    public function testManagedRepositoriesOrderingMatchesWorkspacePolicy(): void
    {
        $composer = [];
        $composer['name'] = 'acme/example';
        $composer['repositories'] = [
            // user-owned prefix (must be preserved as-is)
            [
                'type' => 'vcs',
                'url' => 'https://example.com/acme/user-owned.git',
            ],
            // managed (messy order + keys)
            [
                'url' => '..\\framework\\packages\\*\\*',
                'coretsia_managed' => true,
                'type' => 'path',
                'options' => ['symlink' => true],
            ],
            [
                'coretsia_managed' => true,
                'options' => ['symlink' => true],
                'url' => '../framework',
                'type' => 'path',
            ],
        ];

        $rebuilt = WorkspacePolicy::rebuildManagedRepositoriesBlockIfPresent($composer);
        $out = ComposerJsonCanonicalizer::encodeCanonical($rebuilt);

        $decoded = \json_decode($out, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('repositories', $decoded);

        /** @var list<array> $repos */
        $repos = $decoded['repositories'];

        // User-owned prefix preserved (values + key order).
        self::assertSame(['type', 'url'], \array_keys($repos[0]));
        self::assertSame('vcs', $repos[0]['type']);
        self::assertSame('https://example.com/acme/user-owned.git', $repos[0]['url']);

        // Managed entries contiguity proof (explicit):
        // expected layout after policy rebuild:
        //   repos[0] = user-owned
        //   repos[1] = managed
        //   repos[2] = managed
        self::assertCount(3, $repos);
        self::assertFalse(WorkspacePolicy::isManagedRepositoryEntry($repos[0]));
        self::assertTrue(WorkspacePolicy::isManagedRepositoryEntry($repos[1]));
        self::assertTrue(WorkspacePolicy::isManagedRepositoryEntry($repos[2]));

        // Also assert: once managed block starts, we never see user-owned until it ends.
        $firstManaged = null;
        $lastManaged = null;
        $n = \count($repos);

        for ($i = 0; $i < $n; $i++) {
            if (!WorkspacePolicy::isManagedRepositoryEntry($repos[$i])) {
                continue;
            }

            $firstManaged ??= $i;
            $lastManaged = $i;
        }

        self::assertNotNull($firstManaged);
        self::assertNotNull($lastManaged);

        for ($i = (int)$firstManaged; $i <= (int)$lastManaged; $i++) {
            self::assertTrue(WorkspacePolicy::isManagedRepositoryEntry($repos[$i]));
        }

        /** @var list<array> $managed */
        $managed = [$repos[1], $repos[2]];
        self::assertCount(2, $managed);

        // Managed entries are sorted by normalized URL (strcmp).
        $urls = [
            WorkspacePolicy::normalizeUrlForSort((string)$managed[0]['url']),
            WorkspacePolicy::normalizeUrlForSort((string)$managed[1]['url']),
        ];
        $sorted = $urls;
        \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));
        self::assertSame($sorted, $urls);

        // Managed map key insertion order is canonical.
        foreach ($managed as $m) {
            $keys = \array_keys($m);
            self::assertSame(['type', 'url', 'options', 'coretsia_managed'], $keys);

            self::assertSame('path', $m['type']);
            self::assertIsString($m['url']);
            self::assertSame(true, $m['coretsia_managed']);
        }
    }

    private static function assertCanonicalIndentationIsTwoSpacesGlobally(string $out): void
    {
        self::assertFalse(\str_contains($out, "\t"), 'Canonical JSON MUST NOT contain tabs.');

        $lines = \explode("\n", $out);
        $lineNo = 0;

        foreach ($lines as $line) {
            $lineNo++;

            // Last explode element is "" because output ends with "\n" (final newline).
            if ($line === '') {
                continue;
            }

            $len = \strlen($line);
            $leadingSpaces = 0;

            for ($i = 0; $i < $len; $i++) {
                $ch = $line[$i];
                if ($ch !== ' ') {
                    break;
                }
                $leadingSpaces++;
            }

            self::assertSame(
                0,
                $leadingSpaces % 2,
                'Indentation MUST be 2-space aligned; got ' . $leadingSpaces . ' leading spaces on line ' . $lineNo . '.'
            );
        }
    }

    /**
     * @param list<string> $lines
     */
    private static function firstLineContaining(array $lines, string $needle): ?string
    {
        foreach ($lines as $line) {
            if (\str_contains($line, $needle)) {
                return $line;
            }
        }

        return null;
    }

    private static function pos(string $haystack, string $needle): int
    {
        $p = \strpos($haystack, $needle);
        self::assertNotFalse($p);

        return (int)$p;
    }
}
