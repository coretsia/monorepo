#!/usr/bin/env php
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

/**
 * @return string absolute path to repo root
 */
function detectRepoRoot(string $startDir): string
{
    $dir = realpath($startDir);
    if ($dir === false) {
        throw new RuntimeException('Cannot resolve startDir: ' . $startDir);
    }

    // Walk up until we find a "repo root" marker set.
    // We intentionally do NOT rely on .git, because it might be absent in archives.
    for ($i = 0; $i < 20; $i++) {
        $hasFramework = is_dir($dir . DIRECTORY_SEPARATOR . 'framework');
        $hasDocs = is_dir($dir . DIRECTORY_SEPARATOR . 'docs');
        $hasLicense = is_file($dir . DIRECTORY_SEPARATOR . 'LICENSE');
        $hasReadme = is_file($dir . DIRECTORY_SEPARATOR . 'README.md');

        if ($hasFramework && $hasDocs && $hasLicense && $hasReadme) {
            return $dir;
        }

        $parent = dirname($dir);
        if ($parent === $dir) {
            break;
        }
        $dir = $parent;
    }

    throw new RuntimeException(
        'Repo root not found (expected framework/docs/LICENSE/README.md markers). Start: ' . $startDir
    );
}

/**
 * @param mixed $value
 * @param string $label
 * @return list<string>
 */
function normalizeStringList(mixed $value, string $label): array
{
    if (!is_array($value)) {
        throw new RuntimeException($label . ' must be an array');
    }

    $out = [];
    $seen = [];

    foreach ($value as $v) {
        if (!is_string($v)) {
            throw new RuntimeException($label . ' must contain only strings');
        }

        $v = trim($v);
        if ($v === '') {
            continue;
        }

        if (isset($seen[$v])) {
            continue;
        }

        $seen[$v] = true;
        $out[] = $v;
    }

    return $out;
}

/**
 * @return array{ignoreDirs:list<string>, ignoreFiles:list<string>}
 */
function loadIgnoreConfig(string $buildDir): array
{
    $path = $buildDir . DIRECTORY_SEPARATOR . 'structure.ignore.php';
    if (!is_file($path)) {
        throw new RuntimeException('Ignore config not found: ' . $path);
    }

    $cfg = require $path;

    if (!is_array($cfg)) {
        throw new RuntimeException('Ignore config must return an array: ' . $path);
    }

    if (!array_key_exists('ignoreDirs', $cfg) || !array_key_exists('ignoreFiles', $cfg)) {
        throw new RuntimeException('Ignore config must return keys {ignoreDirs, ignoreFiles}: ' . $path);
    }

    $ignoreDirs = normalizeStringList($cfg['ignoreDirs'], 'ignoreDirs');
    $ignoreFiles = normalizeStringList($cfg['ignoreFiles'], 'ignoreFiles');

    return [
        'ignoreDirs' => $ignoreDirs,
        'ignoreFiles' => $ignoreFiles,
    ];
}

/**
 * @param list<string> $ignoreDirs
 * @param list<string> $ignoreFiles
 */
function generateProjectTree(
    string $dir,
    string $prefix = '',
    array  $ignoreDirs = [],
    array  $ignoreFiles = [],
    bool   $includePhpSymbols = true
): string
{
    $scan = scandir($dir);
    if ($scan === false) {
        return '';
    }

    /** @var list<string> $all */
    $all = array_values(array_diff($scan, ['.', '..']));

    // Pre-filter ignored entries first (otherwise "└──" gets wrong when entries are skipped).
    /** @var list<string> $entries */
    $entries = [];
    foreach ($all as $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;

        // Avoid symlink recursion / surprises.
        if (is_link($path)) {
            continue;
        }

        if (is_dir($path) && in_array($name, $ignoreDirs, true)) {
            continue;
        }

        if (is_file($path) && in_array($name, $ignoreFiles, true)) {
            continue;
        }

        $entries[] = $name;
    }

    // Sort: dirs first, then files (stable by name).
    usort($entries, static function (string $a, string $b) use ($dir): int {
        $aPath = $dir . DIRECTORY_SEPARATOR . $a;
        $bPath = $dir . DIRECTORY_SEPARATOR . $b;

        $aIsDir = is_dir($aPath);
        $bIsDir = is_dir($bPath);

        if ($aIsDir && !$bIsDir) {
            return -1;
        }
        if (!$aIsDir && $bIsDir) {
            return 1;
        }

        return strcmp($a, $b);
    });

    $lastIndex = count($entries) - 1;
    $tree = '';

    foreach ($entries as $idx => $name) {
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        $isLast = ($idx === $lastIndex);

        $tree .= $prefix . ($isLast ? '└── ' : '├── ') . $name;

        // For PHP files optionally add class/method info.
        if (
            $includePhpSymbols
            && is_file($path)
            && strtolower((string)pathinfo($path, PATHINFO_EXTENSION)) === 'php'
        ) {
            $phpInfo = getPhpFileInfo($path);
            if ($phpInfo !== '') {
                $tree .= $phpInfo;
            }
        }

        if (is_dir($path)) {
            $tree .= "/\n";
            $tree .= generateProjectTree(
                $path,
                $prefix . ($isLast ? '    ' : '│   '),
                $ignoreDirs,
                $ignoreFiles,
                $includePhpSymbols
            );
        } else {
            $tree .= "\n";
        }
    }

    return $tree;
}

function getPhpFileInfo(string $filePath): string
{
    $content = @file_get_contents($filePath);
    if ($content === false || $content === '') {
        return '';
    }

    $tokens = @token_get_all($content);
    if (!is_array($tokens) || $tokens === []) {
        return '';
    }

    /** @var list<array{name:string,type:string,methods:list<string>}> $classes */
    $classes = [];

    /** @var array{name:string,type:string,methods:list<string>}|null $currentClass */
    $currentClass = null;

    $currentBraceLevel = 0;

    // Class body "level" (brace count after opening "{")
    $classBodyLevel = 0;
    $inClass = false;

    foreach ($tokens as $i => $token) {
        if (!is_array($token)) {
            if ($token === '{') {
                $currentBraceLevel++;
            } elseif ($token === '}') {
                $currentBraceLevel--;

                // Exit class scope when we closed the class body.
                if ($inClass && $currentBraceLevel < $classBodyLevel) {
                    if (is_array($currentClass)) {
                        $classes[] = $currentClass;
                    }
                    $currentClass = null;
                    $inClass = false;
                    $classBodyLevel = 0;
                }
            }

            continue;
        }

        $tokenType = $token[0];

        if (($tokenType === T_CLASS || $tokenType === T_INTERFACE || $tokenType === T_TRAIT) && !$inClass) {
            // Detect real declaration (avoid Foo::class, new class, class_exists, etc.)
            $isDeclaration = true;

            $j = $i - 1;
            while ($j >= 0) {
                $prev = $tokens[$j];

                if (is_array($prev)) {
                    $prevType = $prev[0];

                    if ($prevType === T_WHITESPACE || $prevType === T_COMMENT || $prevType === T_DOC_COMMENT) {
                        $j--;
                        continue;
                    }

                    if (in_array($prevType, [T_NEW, T_EXTENDS, T_IMPLEMENTS, T_INSTANCEOF], true)) {
                        $isDeclaration = false;
                    }

                    // If it's "Foo::class" (T_CLASS used as const), prev is usually T_DOUBLE_COLON.
                    if ($prevType === T_DOUBLE_COLON) {
                        $isDeclaration = false;
                    }
                }

                break;
            }

            if (!$isDeclaration) {
                continue;
            }

            // Find class name.
            $name = '';
            for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
                $t = $tokens[$j];

                if (is_array($t) && $t[0] === T_STRING) {
                    $name = $t[1];
                    break;
                }

                // Anonymous class or weird token stream — abort.
                if ($t === '{' || $t === ';') {
                    break;
                }
            }

            if ($name !== '') {
                $type = $tokenType === T_CLASS ? 'class' : ($tokenType === T_INTERFACE ? 'interface' : 'trait');

                $currentClass = [
                    'name' => $name,
                    'type' => $type,
                    'methods' => [],
                ];

                $inClass = true;

                // We haven't consumed "{" yet; currentBraceLevel will increase when it appears.
                // Class body level is "after opening brace", so +1 here.
                $classBodyLevel = $currentBraceLevel + 1;
            }

            continue;
        }

        if ($inClass && $tokenType === T_FUNCTION) {
            if (!is_array($currentClass)) {
                continue;
            }

            $methodName = '';

            // Find method name (skip anonymous functions).
            for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
                $t = $tokens[$j];

                if (is_array($t) && $t[0] === T_STRING) {
                    $methodName = $t[1];
                    break;
                }

                if ($t === '(') {
                    // Anonymous function.
                    break;
                }
            }

            if ($methodName === '') {
                continue;
            }

            if (in_array($methodName, [
                '__construct',
                '__destruct',
                '__get',
                '__set',
                '__call',
                '__callStatic',
                '__isset',
                '__unset',
                '__sleep',
                '__wakeup',
                '__toString',
                '__invoke',
                '__set_state',
                '__clone',
                '__debugInfo',
            ], true)) {
                continue;
            }

            $currentClass['methods'][] = $methodName . '()';
        }
    }

    // Add trailing class (EOF inside class).
    if ($inClass && is_array($currentClass)) {
        $classes[] = $currentClass;
    }

    if ($classes === []) {
        return '';
    }

    $parts = [];
    foreach ($classes as $class) {
        $label = $class['name'];

        if ($class['type'] !== 'class') {
            $label .= ' [' . $class['type'] . ']';
        }

        if ($class['methods'] !== []) {
            $label .= ' - ' . implode('/', $class['methods']);
        }

        $parts[] = $label;
    }

    return ' (' . implode('; ', $parts) . ')';
}

function writeFileAtomic(string $filePath, string $content): void
{
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory: ' . $dir);
        }
    }

    $tmp = $filePath . '.tmp';
    $bytes = file_put_contents($tmp, $content, LOCK_EX);
    if ($bytes === false) {
        throw new RuntimeException('Failed to write temp file: ' . $tmp);
    }

    // rename() is atomic on most filesystems when target is on the same volume.
    if (!rename($tmp, $filePath)) {
        throw new RuntimeException('Failed to move temp file into place: ' . $filePath);
    }
}

/**
 * @return array{withTimestamp:bool, mode:string}
 */
function parseArgs(array $argv): array
{
    $withTimestamp = in_array('--timestamp', $argv, true);

    $hasStructure = in_array('--structure', $argv, true);
    $hasFull = in_array('--full', $argv, true);

    // Default: both.
    $mode = 'both';
    if ($hasStructure && !$hasFull) {
        $mode = 'structure';
    } elseif ($hasFull && !$hasStructure) {
        $mode = 'full';
    }

    return [
        'withTimestamp' => $withTimestamp,
        'mode' => $mode,
    ];
}

function buildMarkdown(
    string $title,
    string $generatorCommand,
    string $repoRoot,
    array  $ignoreDirs,
    array  $ignoreFiles,
    bool   $includePhpSymbols,
    bool   $withTimestamp
): string
{
    $structure = "<!-- GENERATED FILE. DO NOT EDIT. -->\n";
    $structure .= "<!-- Generated by: $generatorCommand -->\n";
    $structure .= "<!-- Ignore lists SSoT: framework/tools/build/structure.ignore.php -->\n";
    $structure .= "<!-- Modes: default writes 2 files; --structure or --full writes one -->\n";
    $structure .= "<!-- Timestamp: add --timestamp (non-deterministic) -->\n\n";

    $structure .= '# ' . $title . "\n\n";
    $structure .= "```\n";
    $structure .= "Coretsia/\n";
    $structure .= generateProjectTree($repoRoot, '', $ignoreDirs, $ignoreFiles, $includePhpSymbols);
    $structure .= "```\n\n";

    $structure .= "Generator ignores entries for documentation output; this is not the same as .gitignore.\n";
    $structure .= "Ignore lists SSoT: framework/tools/build/structure.ignore.php\n";
    $structure .= "Ignored directories: " . implode(', ', $ignoreDirs) . "\n";
    $structure .= "Ignored files: " . implode(', ', $ignoreFiles) . "\n";

    if ($withTimestamp) {
        $structure .= "Generated at: " . date('Y-m-d H:i:s') . "\n";
    }

    // Ensure trailing newline at EOF.
    if (!str_ends_with($structure, "\n")) {
        $structure .= "\n";
    }

    return $structure;
}

// Args
$args = parseArgs($argv);
$withTimestamp = $args['withTimestamp'];
$mode = $args['mode'];

// Resolve repo root and output paths (root-based, deterministic).
$repoRoot = detectRepoRoot(__DIR__);

$docsGeneratedDir = $repoRoot . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . 'generated';
$outputFileFull = $docsGeneratedDir . DIRECTORY_SEPARATOR . 'GENERATED_STRUCTURE.md';
$outputFileTree = $docsGeneratedDir . DIRECTORY_SEPARATOR . 'GENERATED_STRUCTURE_TREE.md';

// Load ignore config from SSoT file.
$ignoreConfig = loadIgnoreConfig(__DIR__);
$ignoreDirs = $ignoreConfig['ignoreDirs'];
$ignoreFiles = $ignoreConfig['ignoreFiles'];

$written = [];

/**
 * Full (as before): includes class/method info.
 * Keep legacy path GENERATED_STRUCTURE.md for compatibility.
 */
if ($mode === 'both' || $mode === 'full') {
    $md = buildMarkdown(
        '🏗️ Структура монорепозиторію (повна, з PHP symbols)',
        'composer docs:structure:full',
        $repoRoot,
        $ignoreDirs,
        $ignoreFiles,
        true,
        $withTimestamp
    );

    writeFileAtomic($outputFileFull, $md);
    $written[] = 'docs/generated/GENERATED_STRUCTURE.md';
}

/**
 * Tree-only: no class/method info.
 */
if ($mode === 'both' || $mode === 'structure') {
    $md = buildMarkdown(
        '🏗️ Структура монорепозиторію (тільки дерево)',
        'composer docs:structure:tree',
        $repoRoot,
        $ignoreDirs,
        $ignoreFiles,
        false,
        $withTimestamp
    );

    writeFileAtomic($outputFileTree, $md);
    $written[] = 'docs/generated/GENERATED_STRUCTURE_TREE.md';
}

if ($written === []) {
    // Should be unreachable, but keep deterministic behavior.
    throw new RuntimeException('Nothing to write (invalid mode).');
}

echo "Project structure generated:\n";
foreach ($written as $p) {
    echo "- " . $p . "\n";
}
