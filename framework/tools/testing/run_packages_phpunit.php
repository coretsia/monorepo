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

(static function (array $argv): void {
    $frameworkRoot = dirname(__DIR__, 2);
    $frameworkRoot = rtrim(str_replace('\\', '/', $frameworkRoot), '/');

    $phpunitBin = $frameworkRoot . '/vendor/bin/phpunit';
    if (!is_file($phpunitBin)) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: missing framework/vendor/bin/phpunit\n");
        exit(1);
    }

    $baseConfigAbs = $frameworkRoot . '/tools/testing/phpunit.xml';
    if (!is_file($baseConfigAbs)) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: missing framework/tools/testing/phpunit.xml\n");
        exit(1);
    }

    $bootstrap = $frameworkRoot . '/tools/testing/bootstrap.php';
    if (!is_file($bootstrap)) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: missing framework/tools/testing/bootstrap.php\n");
        exit(1);
    }

    $strict = false;

    /** @var list<string> $forwardArgs */
    $forwardArgs = [];

    $args = array_slice($argv, 1);
    $stopParsingFlags = false;

    foreach ($args as $a) {
        $a = (string)$a;

        if ($a === '') {
            continue;
        }

        if ($a === '--') {
            $stopParsingFlags = true;
            continue;
        }

        if (!$stopParsingFlags && $a === '--strict') {
            $strict = true;
            continue;
        }

        $forwardArgs[] = $a;
    }

    $packageDirs = glob($frameworkRoot . '/packages/*/*', GLOB_ONLYDIR);
    if ($packageDirs === false) {
        $packageDirs = [];
    }

    $packageDirs = array_values(array_filter($packageDirs, static fn (string $p): bool => $p !== ''));
    $packageDirs = array_map(static fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/'), $packageDirs);
    sort($packageDirs, SORT_STRING);

    /** @var list<array{pkg:string,testsRel:string}> $pkgEntries */
    $pkgEntries = [];

    /** @var list<string> $testsDirsRel */
    $testsDirsRel = [];

    foreach ($packageDirs as $pkgDir) {
        $testsDir = $pkgDir . '/tests';
        if (!self_hasNonEmptyTestsTree($testsDir)) {
            continue;
        }

        $pkgRel = self_relFromFramework($pkgDir, $frameworkRoot);
        $testsRel = $pkgRel . '/tests';

        $pkgEntries[] = [
            'pkg' => $pkgRel,
            'testsRel' => $testsRel,
        ];

        $testsDirsRel[] = $testsRel;
    }

    if ($strict) {
        sort($testsDirsRel, SORT_STRING);
    }

    usort(
        $pkgEntries,
        static fn (array $a, array $b): int => strcmp($a['pkg'], $b['pkg'])
    );

    foreach ($pkgEntries as $e) {
        fwrite(STDOUT, "==> package: {$e['pkg']}/tests\n");
    }

    $genDir = $frameworkRoot . '/var/phpunit';
    $genDir = rtrim(str_replace('\\', '/', $genDir), '/');

    if (!is_dir($genDir)) {
        @mkdir($genDir, 0777, true);
    }
    if (!is_dir($genDir)) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: cannot create framework/var/phpunit\n");
        exit(1);
    }

    $generatedConfigAbs = $genDir . '/phpunit.discovered.xml';

    try {
        $xml = self_renderGeneratedPhpunitXmlFromBase(
            $baseConfigAbs,
            $generatedConfigAbs,
            $frameworkRoot,
            $testsDirsRel,
        );
    } catch (Throwable) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: cannot render generated phpunit config\n");
        exit(1);
    }

    $ok = @file_put_contents($generatedConfigAbs, $xml);
    if (!is_int($ok) || $ok <= 0) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: cannot write var/phpunit/phpunit.discovered.xml\n");
        exit(1);
    }

    $cmd = array_merge(
        [
            PHP_BINARY,
            $phpunitBin,
            '-c',
            $generatedConfigAbs,
            '--do-not-cache-result',
        ],
        $forwardArgs
    );

    $descriptors = [
        0 => ['file', 'php://stdin', 'r'],
        1 => ['file', 'php://stdout', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ];

    $proc = proc_open($cmd, $descriptors, $pipes, $frameworkRoot);

    if (!is_resource($proc)) {
        fwrite(STDERR, "CORETSIA_TEST_RUNNER_FAILED: cannot start phpunit process\n");
        exit(1);
    }

    $code = proc_close($proc);
    exit($code);
})($argv);

/** exists AND has any meaningful contents (any .php file OR any non-dot directory). */
function self_hasNonEmptyTestsTree(string $testsDir): bool
{
    $testsDir = rtrim(str_replace('\\', '/', $testsDir), '/');
    if ($testsDir === '' || !is_dir($testsDir)) {
        return false;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($testsDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($it as $node) {
        $path = $node->getPathname();

        if ($path === '') {
            continue;
        }

        if ($node->isDir()) {
            return true;
        }

        if ($node->isFile()) {
            $p = str_replace('\\', '/', $path);
            if (str_ends_with($p, '.php')) {
                return true;
            }
        }
    }

    return false;
}

function self_relFromFramework(string $absPath, string $frameworkRoot): string
{
    $absPath = rtrim(str_replace('\\', '/', $absPath), '/');
    $frameworkRoot = rtrim(str_replace('\\', '/', $frameworkRoot), '/');

    if ($absPath === $frameworkRoot) {
        return '.';
    }

    if (!str_starts_with($absPath, $frameworkRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return substr($absPath, strlen($frameworkRoot) + 1);
}

/**
 * @param list<string> $discoveredTestsDirsRel
 */
function self_renderGeneratedPhpunitXmlFromBase(
    string $baseConfigAbs,
    string $generatedConfigAbs,
    string $frameworkRoot,
    array  $discoveredTestsDirsRel
): string {
    $baseDir = rtrim(str_replace('\\', '/', dirname($baseConfigAbs)), '/');
    $generatedDir = rtrim(str_replace('\\', '/', dirname($generatedConfigAbs)), '/');
    $frameworkRoot = rtrim(str_replace('\\', '/', $frameworkRoot), '/');

    $previous = libxml_use_internal_errors(true);

    try {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        $loaded = $dom->load($baseConfigAbs);
        if ($loaded !== true) {
            throw new RuntimeException('base-phpunit-load-failed');
        }

        $xpath = new DOMXPath($dom);

        /** @var DOMElement|null $root */
        $root = $dom->documentElement;
        if (!$root instanceof DOMElement || $root->tagName !== 'phpunit') {
            throw new RuntimeException('phpunit-root-missing');
        }

        foreach (['bootstrap', 'cacheDirectory'] as $attrName) {
            /** @var DOMAttr $attr */
            foreach ($xpath->query('//@' . $attrName) ?: [] as $attr) {
                $value = trim($attr->value);
                if ($value === '' || self_isAbsolutePath($value)) {
                    continue;
                }

                $abs = self_normalizePath($baseDir . '/' . $value);
                $attr->value = self_makeRelativePath($generatedDir, $abs);
            }
        }

        /** @var DOMElement $directory */
        foreach ($xpath->query('//directory') ?: [] as $directory) {
            $value = trim($directory->textContent);
            if ($value === '' || self_isAbsolutePath($value)) {
                continue;
            }

            $abs = self_normalizePath($baseDir . '/' . $value);

            while ($directory->firstChild !== null) {
                $directory->removeChild($directory->firstChild);
            }

            $directory->appendChild(
                $dom->createTextNode(self_makeRelativePath($generatedDir, $abs))
            );
        }

        /** @var DOMElement|null $allSuite */
        $allSuite = null;
        /** @var DOMElement $suite */
        foreach ($xpath->query('//testsuite[@name="all"]') ?: [] as $suite) {
            $allSuite = $suite;
            break;
        }

        if (!$allSuite instanceof DOMElement) {
            throw new RuntimeException('all-testsuite-missing');
        }

        $marker = null;
        /** @var DOMComment $comment */
        foreach ($xpath->query('./comment()', $allSuite) ?: [] as $comment) {
            if (str_contains($comment->data, 'CORETSIA_DISCOVERED_TEST_DIRECTORIES')) {
                $marker = $comment;
                break;
            }
        }

        if (!$marker instanceof DOMComment) {
            throw new RuntimeException('discovered-tests-marker-missing');
        }

        $discoveredTestsDirsRel = array_values(array_unique(array_map(
            static fn (string $p): string => trim(str_replace('\\', '/', $p), '/'),
            $discoveredTestsDirsRel
        )));
        sort($discoveredTestsDirsRel, SORT_STRING);

        foreach ($discoveredTestsDirsRel as $rel) {
            if ($rel === '') {
                continue;
            }

            $abs = self_normalizePath($frameworkRoot . '/' . $rel);
            if (!is_dir($abs)) {
                continue;
            }

            $directory = $dom->createElement('directory');
            $directory->appendChild(
                $dom->createTextNode(self_makeRelativePath($generatedDir, $abs))
            );

            $allSuite->insertBefore($directory, $marker);
        }

        $allSuite->removeChild($marker);

        // Remove ALL comments from the document:
        // - document-level XML comments before <phpunit>
        // - explanatory comments under <phpunit>
        // - nested comments inside nodes like <php>
        $allComments = [];
        /** @var DOMComment $comment */
        foreach ($xpath->query('//comment()') ?: [] as $comment) {
            $allComments[] = $comment;
        }

        foreach ($dom->childNodes as $child) {
            if ($child instanceof DOMComment) {
                $allComments[] = $child;
            }
        }

        foreach ($allComments as $commentNode) {
            if ($commentNode->parentNode instanceof DOMNode) {
                $commentNode->parentNode->removeChild($commentNode);
            }
        }

        $generatedComment = $dom->createComment(
            "\n"
            . "    GENERATED. Do not edit.\n"
            . "    Generated from tools/testing/phpunit.xml by tools/testing/run_packages_phpunit.php\n"
            . "    Contains only discovered existing package test directories materialized into the canonical base harness.\n"
            . "  "
        );

        $insertBefore = null;
        foreach ($root->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $insertBefore = $child;
                break;
            }
        }

        if ($insertBefore instanceof DOMNode) {
            $root->insertBefore($generatedComment, $insertBefore);
        } else {
            $root->appendChild($generatedComment);
        }

        $xml = $dom->saveXML();
        if (!is_string($xml) || $xml === '') {
            throw new RuntimeException('generated-phpunit-save-failed');
        }

        return str_replace("\r\n", "\n", str_replace("\r", "\n", $xml));
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
}

function self_isAbsolutePath(string $path): bool
{
    $path = trim($path);
    if ($path === '') {
        return false;
    }

    if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        return true;
    }

    return preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
}

function self_normalizePath(string $path): string
{
    $path = str_replace('\\', '/', $path);
    $prefix = '';

    if (preg_match('~^[A-Za-z]:~', $path) === 1) {
        $prefix = substr($path, 0, 2);
        $path = substr($path, 2);
    }

    $isAbsolute = str_starts_with($path, '/');
    $parts = explode('/', $path);
    $out = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            if ($out !== [] && end($out) !== '..') {
                array_pop($out);
                continue;
            }

            if (!$isAbsolute) {
                $out[] = '..';
            }

            continue;
        }

        $out[] = $part;
    }

    $normalized = implode('/', $out);

    if ($isAbsolute) {
        $normalized = '/' . $normalized;
    }

    if ($prefix !== '') {
        $normalized = $prefix . $normalized;
    }

    return $normalized === '' ? ($isAbsolute ? '/' : '.') : $normalized;
}

function self_makeRelativePath(string $fromDir, string $toPath): string
{
    $fromDir = self_normalizePath($fromDir);
    $toPath = self_normalizePath($toPath);

    $fromParts = self_splitPathParts($fromDir);
    $toParts = self_splitPathParts($toPath);

    $common = 0;
    $max = min(count($fromParts), count($toParts));

    while ($common < $max && $fromParts[$common] === $toParts[$common]) {
        $common++;
    }

    $up = array_fill(0, count($fromParts) - $common, '..');
    $down = array_slice($toParts, $common);

    $parts = array_merge($up, $down);

    return $parts === [] ? '.' : implode('/', $parts);
}

/**
 * @return list<string>
 */
function self_splitPathParts(string $path): array
{
    $path = str_replace('\\', '/', $path);

    if (preg_match('~^[A-Za-z]:~', $path) === 1) {
        $path = substr($path, 2);
    }

    $path = trim($path, '/');

    if ($path === '') {
        return [];
    }

    /** @var list<string> $parts */
    $parts = array_values(array_filter(explode('/', $path), static fn (string $p): bool => $p !== ''));

    return $parts;
}
