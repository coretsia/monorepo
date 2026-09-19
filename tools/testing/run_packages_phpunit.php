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

use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';

(static function (array $argv): void {
    try {
        $repository = RepositoryContext::discoverFrom(__DIR__);
    } catch (Throwable) {
        self_testRunnerFail('repository-root-invalid');
    }

    $repoRoot = $repository->repoRoot();

    try {
        $phpunitBin = $repository->resolveExistingFile('vendor/bin/phpunit');
    } catch (Throwable) {
        self_testRunnerFail('missing vendor/bin/phpunit');
    }

    if (!is_readable($phpunitBin)) {
        self_testRunnerFail('missing vendor/bin/phpunit');
    }

    try {
        $baseConfigAbs = $repository->resolveExistingFile('tools/testing/phpunit.xml');
    } catch (Throwable) {
        self_testRunnerFail('missing tools/testing/phpunit.xml');
    }

    if (!is_readable($baseConfigAbs)) {
        self_testRunnerFail('missing tools/testing/phpunit.xml');
    }

    try {
        $bootstrapAbs = $repository->resolveExistingFile('tools/testing/bootstrap.php');
    } catch (Throwable) {
        self_testRunnerFail('missing tools/testing/bootstrap.php');
    }

    if (!is_readable($bootstrapAbs)) {
        self_testRunnerFail('missing tools/testing/bootstrap.php');
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

    try {
        $products = WorkspacePackageCatalog::discover($repository)->all();
    } catch (Throwable) {
        self_testRunnerFail('cannot discover workspace packages');
    }

    /** @var list<array{composerName:string,pkg:string,testsRel:string}> $pkgEntries */
    $pkgEntries = [];

    /** @var list<string> $testsDirsRel */
    $testsDirsRel = [];

    foreach ($products as $product) {
        $testsCandidate = $product['absolutePath'] . '/tests';

        if (!is_dir($testsCandidate)) {
            continue;
        }

        try {
            $testsDir = $repository->resolveExistingDirectory($testsCandidate);
        } catch (Throwable) {
            self_testRunnerFail('package tests path invalid');
        }

        $pkgRel = $product['relativePath'];
        $testsRel = $pkgRel . '/tests';

        if ($repository->relativeToRepo($testsDir) !== $testsRel) {
            self_testRunnerFail('package tests path invalid');
        }

        if (!self_hasNonEmptyTestsTree($testsDir)) {
            continue;
        }

        $pkgEntries[] = [
            'composerName' => $product['composerName'],
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
        static fn (array $a, array $b): int => strcmp($a['composerName'], $b['composerName']),
    );

    if ($listPackages) {
        foreach ($pkgEntries as $e) {
            ConsoleOutput::line("package: {$e['pkg']}/tests", false);
        }
    }

    /** @var list<string> $selectionArgs */
    $selectionArgs = [];

    try {
        if ($fileSelector !== null) {
            $selectionArgs[] = self_resolveTestFile(
                $fileSelector,
                $repository,
                $pkgEntries,
            );
        } elseif ($packageSelector !== null) {
            $selectionArgs = self_resolvePackageTests($packageSelector, $pkgEntries);
        }
    } catch (RuntimeException $exception) {
        self_testRunnerFail($exception->getMessage());
    }

    $varRoot = $repository->resolve('var');

    if (is_link($varRoot)) {
        self_testRunnerFail('repository var path invalid');
    }

    if (is_dir($varRoot)) {
        try {
            $varRoot = $repository->resolveExistingDirectory($varRoot);
        } catch (Throwable) {
            self_testRunnerFail('repository var path invalid');
        }
    }

    $generatedDir = $varRoot . '/phpunit';

    if (is_link($generatedDir)) {
        self_testRunnerFail('phpunit state path invalid');
    }

    if (is_dir($generatedDir)) {
        try {
            $generatedDir = $repository->resolveExistingDirectory($generatedDir);
        } catch (Throwable) {
            self_testRunnerFail('phpunit state path invalid');
        }
    }

    $generatedConfigAbs = $generatedDir . '/phpunit.discovered.xml';

    try {
        $xml = self_renderGeneratedPhpunitXmlFromBase(
            $baseConfigAbs,
            $generatedConfigAbs,
            $repository,
            $testsDirsRel,
        );
    } catch (Throwable) {
        self_testRunnerFail('cannot render generated phpunit config');
    }

    try {
        DeterministicFile::writeTextLf($generatedConfigAbs, $xml);
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
        exit(self_runPhpUnit($cmd, $repoRoot));
    }

    /** @var list<int> $failedRuns */
    $failedRuns = [];
    $finalExitCode = 0;

    for ($run = 1; $run <= $repeat; $run++) {
        ConsoleOutput::line(
            "repeat: {$run}/{$repeat}",
            false,
        );

        $code = self_runPhpUnit($cmd, $repoRoot);

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

    ConsoleOutput::line(
        $summary,
        false,
    );

    exit($finalExitCode);
})(
    $argv,
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
        $arg = (string) $args[$i];

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

    $value = (string) $args[$next];

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
    $repeat = (int) $value;

    return $repeat;
}

/**
 * @param list<array{composerName:string,pkg:string,testsRel:string}> $pkgEntries
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
            $pathSelector = str_starts_with($entry['pkg'], 'packages/')
                ? substr($entry['pkg'], strlen('packages/'))
                : $entry['pkg'];

            if (
                $entry['composerName'] === $selector
                || $pathSelector === $selector
            ) {
                return [$entry['testsRel']];
            }
        }

        throw new RuntimeException('package selector not found: ' . $selector);
    }

    /** @var list<string> $familyMatches */
    $familyMatches = [];

    foreach ($pkgEntries as $entry) {
        $pathSelector = str_starts_with($entry['pkg'], 'packages/')
            ? substr($entry['pkg'], strlen('packages/'))
            : $entry['pkg'];

        if (str_starts_with($pathSelector, $selector . '/')) {
            $familyMatches[] = $entry['testsRel'];
        }
    }

    if ($familyMatches !== []) {
        sort($familyMatches, SORT_STRING);

        return $familyMatches;
    }

    /** @var list<array{composerName:string,pkg:string,testsRel:string}> $basenameMatches */
    $basenameMatches = [];

    foreach ($pkgEntries as $entry) {
        $composerParts = explode('/', $entry['composerName']);
        $pathParts = explode('/', $entry['pkg']);

        $composerBasename = $composerParts[count($composerParts) - 1] ?? '';
        $pathBasename = $pathParts[count($pathParts) - 1] ?? '';

        if ($composerBasename === $selector || $pathBasename === $selector) {
            $basenameMatches[] = $entry;
        }
    }

    if (count($basenameMatches) === 1) {
        return [$basenameMatches[0]['testsRel']];
    }

    if (count($basenameMatches) > 1) {
        $matches = array_map(
            static fn (array $entry): string => $entry['composerName'],
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

/**
 * @param list<array{composerName:string,pkg:string,testsRel:string}> $pkgEntries
 */
function self_resolveTestFile(
    string $selector,
    RepositoryContext $repository,
    array $pkgEntries,
): string {
    if (
        trim($selector) !== $selector
        || $selector === ''
        || str_contains($selector, "\0")
        || RepositoryContext::isAbsolutePath($selector)
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

    try {
        $real = $repository->resolveExistingFile($relative);
    } catch (Throwable) {
        throw new RuntimeException('test file not found: ' . $relative);
    }

    if (!is_readable($real)) {
        throw new RuntimeException('test file not found: ' . $relative);
    }

    $resolvedRelative = $repository->relativeToRepo($real);
    $packageTest = false;

    foreach ($pkgEntries as $entry) {
        if (str_starts_with($resolvedRelative, $entry['testsRel'] . '/')) {
            $packageTest = true;
            break;
        }
    }

    $toolsTest = str_starts_with($resolvedRelative, 'tools/tests/');

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
    string $repoRoot,
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
        $repoRoot,
    );

    if (!is_resource($proc)) {
        self_testRunnerFail('cannot start phpunit process');
    }

    return proc_close($proc);
}

function self_testRunnerFail(string $reason): never
{
    ConsoleOutput::codeWithDiagnostics(
        'CORETSIA_TEST_RUNNER_FAILED',
        [$reason],
    );

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
        RecursiveIteratorIterator::SELF_FIRST,
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

/**
 * @param list<string> $discoveredTestsDirsRel
 */
function self_renderGeneratedPhpunitXmlFromBase(
    string $baseConfigAbs,
    string $generatedConfigAbs,
    RepositoryContext $repository,
    array $discoveredTestsDirsRel,
): string {
    $baseDir = RepositoryContext::normalizePath(dirname($baseConfigAbs));
    $baseDirRel = $repository->relativeToRepo($baseDir);
    $generatedDir = RepositoryContext::normalizePath(
        dirname($generatedConfigAbs),
    );

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
                if ($value === '' || RepositoryContext::isAbsolutePath($value)) {
                    continue;
                }

                $abs = $repository->resolve($baseDirRel . '/' . $value);

                if (is_dir($abs)) {
                    $abs = $repository->resolveExistingDirectory($abs);
                }

                $attr->value = self_makeRelativePath($generatedDir, $abs);
            }
        }

        /** @var DOMElement $directory */
        foreach ($xpath->query('//directory') ?: [] as $directory) {
            $value = trim($directory->textContent);
            if ($value === '' || RepositoryContext::isAbsolutePath($value)) {
                continue;
            }

            $abs = $repository->resolve($baseDirRel . '/' . $value);

            if (is_dir($abs)) {
                $abs = $repository->resolveExistingDirectory($abs);
            }

            while ($directory->firstChild !== null) {
                $directory->removeChild($directory->firstChild);
            }

            $directory->appendChild(
                $dom->createTextNode(self_makeRelativePath($generatedDir, $abs)),
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
                    $discoveredTestsDirsRel,
                ),
            ),
        );
        sort($discoveredTestsDirsRel, SORT_STRING);

        foreach ($discoveredTestsDirsRel as $rel) {
            if ($rel === '') {
                continue;
            }

            $abs = $repository->resolveExistingDirectory($rel);

            $directory = $dom->createElement('directory');
            $directory->appendChild(
                $dom->createTextNode(self_makeRelativePath($generatedDir, $abs)),
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
            . "  ",
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

function self_makeRelativePath(string $fromDir, string $toPath): string
{
    $fromDir = RepositoryContext::normalizePath($fromDir);
    $toPath = RepositoryContext::normalizePath($toPath);

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
