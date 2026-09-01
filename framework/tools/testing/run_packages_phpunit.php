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

    require_once $frameworkRoot . '/tools/support/ConsoleOutput.php';
    require_once $frameworkRoot . '/tools/support/ErrorCodes.php';
    require_once $frameworkRoot . '/tools/support/DeterministicException.php';
    require_once $frameworkRoot . '/tools/support/DeterministicFile.php';

    $phpunitBin = $frameworkRoot . '/vendor/bin/phpunit';
    if (!is_file($phpunitBin)) {
        self_testRunnerFail('missing framework/vendor/bin/phpunit');
    }

    $baseConfigAbs = $frameworkRoot . '/tools/testing/phpunit.xml';
    if (!is_file($baseConfigAbs)) {
        self_testRunnerFail('missing framework/tools/testing/phpunit.xml');
    }

    $bootstrap = $frameworkRoot . '/tools/testing/bootstrap.php';
    if (!is_file($bootstrap)) {
        self_testRunnerFail('missing framework/tools/testing/bootstrap.php');
    }

    try {
        $options = self_parseRunnerOptions(array_slice($argv, 1));
    } catch (RuntimeException $exception) {
        self_testRunnerFail($exception->getMessage());
    }

    $strict = $options['strict'];
    $listPackages = $options['listPackages'];
    $repeat = $options['repeat'];
    $fileSelector = $options['file'];
    $packageSelector = $options['package'];
    $forwardArgs = $options['forwardArgs'];

    $packageDirs = glob($frameworkRoot . '/packages/*/*', GLOB_ONLYDIR);
    if ($packageDirs === false) {
        $packageDirs = [];
    }

    $packageDirs = array_values(array_filter($packageDirs, static fn (string $p): bool => $p !== ''));
    $packageDirs = array_map(static fn (string $p): string => rtrim(str_replace('\\', '/', $p), '/'), $packageDirs);
    sort($packageDirs, SORT_STRING);

    /** @var list<array{id:string,pkg:string,testsRel:string}> $pkgEntries */
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

        if (!str_starts_with($pkgRel, 'packages/')) {
            self_testRunnerFail('discovered package path is outside framework/packages');
        }

        $packageId = substr($pkgRel, strlen('packages/'));

        if (
            preg_match('/\A[A-Za-z0-9._-]+\/[A-Za-z0-9._-]+\z/D', $packageId) !== 1
        ) {
            self_testRunnerFail('discovered package id is invalid: ' . $packageId);
        }

        $pkgEntries[] = [
            'id' => $packageId,
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
        static fn (array $a, array $b): int => strcmp($a['id'], $b['id'])
    );

    if ($listPackages) {
        foreach ($pkgEntries as $e) {
            \Coretsia\Tools\Support\ConsoleOutput::line("package: {$e['pkg']}/tests", false);
        }
    }

    /** @var list<string> $selectionArgs */
    $selectionArgs = [];

    try {
        if ($fileSelector !== null) {
            $selectionArgs[] = self_resolveTestFile($fileSelector, $frameworkRoot);
        } elseif ($packageSelector !== null) {
            $selectionArgs = self_resolvePackageTests($packageSelector, $pkgEntries);
        }
    } catch (RuntimeException $exception) {
        self_testRunnerFail($exception->getMessage());
    }

    $genDir = $frameworkRoot . '/var/phpunit';
    $genDir = rtrim(str_replace('\\', '/', $genDir), '/');

    if (!is_dir($genDir)) {
        @mkdir($genDir, 0777, true);
    }
    if (!is_dir($genDir)) {
        self_testRunnerFail('cannot create framework/var/phpunit');
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
        self_testRunnerFail('cannot render generated phpunit config');
    }

    try {
        \Coretsia\Tools\Support\DeterministicFile::writeTextLf($generatedConfigAbs, $xml);
    } catch (Throwable) {
        self_testRunnerFail('cannot write var/phpunit/phpunit.discovered.xml');
    }

    $cmd = array_merge(
        [
            PHP_BINARY,
            $phpunitBin,
            '-c',
            $generatedConfigAbs,
            '--do-not-cache-result',
        ],
        $forwardArgs,
        $selectionArgs,
    );

    if ($repeat === 1) {
        exit(self_runPhpUnit($cmd, $frameworkRoot));
    }

    /** @var list<int> $failedRuns */
    $failedRuns = [];
    $finalExitCode = 0;

    for ($run = 1; $run <= $repeat; $run++) {
        \Coretsia\Tools\Support\ConsoleOutput::line(
            "repeat: {$run}/{$repeat}",
            false,
        );

        $code = self_runPhpUnit($cmd, $frameworkRoot);

        if ($code !== 0) {
            $failedRuns[] = $run;

            if ($finalExitCode === 0) {
                $finalExitCode = $code >= 1 && $code <= 255
                    ? $code
                    : 1;
            }
        }
    }

    $summary = 'repeat-summary: runs:'
        . $repeat
        . ' failures:'
        . count($failedRuns);

    if ($failedRuns !== []) {
        $summary .= ' failed-runs:' . implode(',', $failedRuns);
    }

    \Coretsia\Tools\Support\ConsoleOutput::line(
        $summary,
        false,
    );

    exit($finalExitCode);
})(
    $argv
);

/**
 * @param array<int, mixed> $args
 * @return array{
 *     strict: bool,
 *     listPackages: bool,
 *     repeat: int<1, 1000>,
 *     file: non-empty-string|null,
 *     package: non-empty-string|null,
 *     forwardArgs: list<string>
 * }
 */
function self_parseRunnerOptions(array $args): array
{
    $strict = false;
    $listPackages = false;
    $repeat = 1;
    $repeatSeen = false;
    $file = null;
    $package = null;

    /** @var list<string> $forwardArgs */
    $forwardArgs = [];

    $stopParsingFlags = false;
    $count = count($args);

    for ($i = 0; $i < $count; $i++) {
        $arg = (string)$args[$i];

        if ($arg === '') {
            continue;
        }

        if ($stopParsingFlags) {
            $forwardArgs[] = $arg;
            continue;
        }

        if ($arg === '--') {
            $stopParsingFlags = true;
            continue;
        }

        if ($arg === '--strict') {
            $strict = true;
            continue;
        }

        if ($arg === '--list-packages') {
            $listPackages = true;
            continue;
        }

        if ($arg === '--repeat' || str_starts_with($arg, '--repeat=')) {
            if ($repeatSeen) {
                throw new RuntimeException('duplicate --repeat option');
            }

            $value = self_runnerOptionValue(
                $args,
                $i,
                $arg,
                '--repeat',
            );

            $repeat = self_parseRepeat($value);
            $repeatSeen = true;
            continue;
        }

        if ($arg === '--file' || str_starts_with($arg, '--file=')) {
            if ($file !== null) {
                throw new RuntimeException('duplicate --file option');
            }

            $file = self_nonEmptyRunnerOptionValue(
                self_runnerOptionValue(
                    $args,
                    $i,
                    $arg,
                    '--file',
                ),
                '--file',
            );
            continue;
        }

        if ($arg === '--package' || str_starts_with($arg, '--package=')) {
            if ($package !== null) {
                throw new RuntimeException('duplicate --package option');
            }

            $package = self_nonEmptyRunnerOptionValue(
                self_runnerOptionValue(
                    $args,
                    $i,
                    $arg,
                    '--package',
                ),
                '--package',
            );
            continue;
        }

        $forwardArgs[] = $arg;
    }

    if ($file !== null && $package !== null) {
        throw new RuntimeException('--file and --package are mutually exclusive');
    }

    /** @var int<1, 1000> $repeat */
    return [
        'strict' => $strict,
        'listPackages' => $listPackages,
        'repeat' => $repeat,
        'file' => $file,
        'package' => $package,
        'forwardArgs' => $forwardArgs,
    ];
}

/**
 * @param array<int, mixed> $args
 */
function self_runnerOptionValue(
    array $args,
    int &$index,
    string $arg,
    string $name,
): string {
    $prefix = $name . '=';

    if (str_starts_with($arg, $prefix)) {
        return substr($arg, strlen($prefix));
    }

    $next = $index + 1;

    if (!isset($args[$next])) {
        throw new RuntimeException("{$name} requires a value");
    }

    $value = (string)$args[$next];

    if ($value === '' || $value === '--') {
        throw new RuntimeException("{$name} requires a value");
    }

    $index = $next;

    return $value;
}

/** @return non-empty-string */
function self_nonEmptyRunnerOptionValue(
    string $value,
    string $name,
): string {
    if (
        $value === ''
        || trim($value) !== $value
        || str_contains($value, "\0")
    ) {
        throw new RuntimeException("invalid {$name} value");
    }

    return $value;
}

/** @return int<1, 1000> */
function self_parseRepeat(string $value): int
{
    if (
        preg_match('/\A(?:[1-9][0-9]{0,2}|1000)\z/D', $value) !== 1
    ) {
        throw new RuntimeException('--repeat must be an integer from 1 to 1000');
    }

    /** @var int<1, 1000> $repeat */
    $repeat = (int)$value;

    return $repeat;
}

/**
 * @param list<array{id:string,pkg:string,testsRel:string}> $pkgEntries
 * @return list<string>
 */
function self_resolvePackageTests(
    string $selector,
    array $pkgEntries,
): array {
    $selector = str_replace('\\', '/', $selector);

    if (
        trim($selector) !== $selector
        || preg_match('/\A[A-Za-z0-9._-]+(?:\/[A-Za-z0-9._-]+)?\z/D', $selector) !== 1
    ) {
        throw new RuntimeException('invalid package selector: ' . $selector);
    }

    if (str_contains($selector, '/')) {
        foreach ($pkgEntries as $entry) {
            if ($entry['id'] === $selector) {
                return [$entry['testsRel']];
            }
        }

        throw new RuntimeException('package selector not found: ' . $selector);
    }

    /** @var list<string> $familyMatches */
    $familyMatches = [];

    foreach ($pkgEntries as $entry) {
        if (str_starts_with($entry['id'], $selector . '/')) {
            $familyMatches[] = $entry['testsRel'];
        }
    }

    if ($familyMatches !== []) {
        sort($familyMatches, SORT_STRING);

        return $familyMatches;
    }

    /** @var list<array{id:string,pkg:string,testsRel:string}> $basenameMatches */
    $basenameMatches = [];

    foreach ($pkgEntries as $entry) {
        $parts = explode('/', $entry['id']);

        if (count($parts) === 2 && $parts[1] === $selector) {
            $basenameMatches[] = $entry;
        }
    }

    if (count($basenameMatches) === 1) {
        return [$basenameMatches[0]['testsRel']];
    }

    if (count($basenameMatches) > 1) {
        $matches = array_map(
            static fn (array $entry): string => $entry['id'],
            $basenameMatches,
        );
        sort($matches, SORT_STRING);

        throw new RuntimeException(
            'ambiguous package selector: '
            . $selector
            . ' matches='
            . implode(',', $matches),
        );
    }

    throw new RuntimeException('package selector not found: ' . $selector);
}

function self_resolveTestFile(
    string $selector,
    string $frameworkRoot,
): string {
    if (
        trim($selector) !== $selector
        || $selector === ''
        || str_contains($selector, "\0")
        || self_isAbsolutePath($selector)
    ) {
        throw new RuntimeException('invalid test file selector');
    }

    $relative = str_replace('\\', '/', $selector);
    $parts = explode('/', $relative);

    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            throw new RuntimeException('invalid test file selector');
        }
    }

    if (!str_ends_with($relative, '.php')) {
        throw new RuntimeException('test file selector must reference a .php file');
    }

    $candidate = $frameworkRoot . '/' . $relative;
    $real = realpath($candidate);

    if (!is_string($real) || !is_file($real) || !is_readable($real)) {
        throw new RuntimeException('test file not found: ' . $relative);
    }

    $real = str_replace('\\', '/', $real);
    $frameworkRoot = rtrim(str_replace('\\', '/', $frameworkRoot), '/');

    if (!str_starts_with($real, $frameworkRoot . '/')) {
        throw new RuntimeException('test file resolves outside framework root');
    }

    $resolvedRelative = self_relFromFramework($real, $frameworkRoot);

    $packageTest = preg_match(
        '~\Apackages/[^/]+/[^/]+/tests(?:/[^/]+)+\.php\z~D',
        $resolvedRelative,
    ) === 1;

    $toolsTest = preg_match(
        '~\Atools/tests(?:/[^/]+)+\.php\z~D',
        $resolvedRelative,
    ) === 1;

    if (!$packageTest && !$toolsTest) {
        throw new RuntimeException(
            'test file is outside canonical package/tool test roots: '
            . $resolvedRelative,
        );
    }

    return $resolvedRelative;
}

/**
 * @param non-empty-list<string> $cmd
 */
function self_runPhpUnit(
    array $cmd,
    string $frameworkRoot,
): int {
    $descriptors = [
        0 => ['file', 'php://stdin', 'r'],
        1 => ['file', 'php://stdout', 'w'],
        2 => ['file', 'php://stderr', 'w'],
    ];

    $pipes = [];

    $proc = proc_open(
        $cmd,
        $descriptors,
        $pipes,
        $frameworkRoot,
    );

    if (!is_resource($proc)) {
        self_testRunnerFail('cannot start phpunit process');
    }

    return proc_close($proc);
}

function self_testRunnerFail(string $reason): never
{
    \Coretsia\Tools\Support\ConsoleOutput::line('CORETSIA_TEST_RUNNER_FAILED: ' . $reason);

    exit(1);
}

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
    array $discoveredTestsDirsRel
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

        $discoveredTestsDirsRel = array_values(
            array_unique(
                array_map(
                    static fn (string $p): string => trim(str_replace('\\', '/', $p), '/'),
                    $discoveredTestsDirsRel
                )
            )
        );
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
