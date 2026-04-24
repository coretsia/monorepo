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

(static function (): void {
    /**
     * Execute callable with warnings/notices suppressed (no output pollution).
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    $withSuppressedErrors = static function (callable $fn) {
        \set_error_handler(static function (): bool {
            return true;
        });

        try {
            return $fn();
        } finally {
            \restore_error_handler();
        }
    };

    $toolsRootRuntime = $withSuppressedErrors(static function (): ?string {
        $p = \realpath(__DIR__ . '/..');
        return \is_string($p) ? $p : null;
    });

    if ($toolsRootRuntime === null) {
        $fallbackConsole = __DIR__ . '/../spikes/_support/ConsoleOutput.php';
        if (\is_file($fallbackConsole) && \is_readable($fallbackConsole)) {
            require_once $fallbackConsole;

            \Coretsia\Tools\Spikes\_support\ConsoleOutput::codeWithDiagnostics(
                'CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED',
                [],
            );
        }

        exit(1);
    }

    $bootstrap = $toolsRootRuntime . '/spikes/_support/bootstrap.php';
    $consoleFile = $toolsRootRuntime . '/spikes/_support/ConsoleOutput.php';
    $errorCodesFile = $toolsRootRuntime . '/spikes/_support/ErrorCodes.php';

    /** @var class-string $ConsoleOutput */
    $ConsoleOutput = 'Coretsia\\Tools\\Spikes\\_support\\ConsoleOutput';

    /** @var class-string $ErrorCodes */
    $ErrorCodes = 'Coretsia\\Tools\\Spikes\\_support\\ErrorCodes';

    $fallbackViolation = 'CORETSIA_OBSERVABILITY_NAMING_DRIFT';
    $fallbackScanFailed = 'CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED';
                if (\defined($name)) {
                    /** @var string $v */
                    $v = \constant($name);
                    $code = $v;
                }
            }

            $ConsoleOutput::codeWithDiagnostics($code, []);
        }

        exit(1);
    }

    require_once $bootstrap;

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }
    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_NAMING_DRIFT';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_OBSERVABILITY_NAMING_GATE_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackScanFailed;
    })();

    try {
        $repoRoot = $withSuppressedErrors(static function (): ?string {
            $p = \realpath(__DIR__ . '/..' . '/..' . '/..');
            return \is_string($p) ? $p : null;
        });

        if ($repoRoot === null || $repoRoot === '') {
            throw new \RuntimeException('repo-root-invalid');
        }

        $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');
        $frameworkRoot = $repoRoot . '/framework';
        $packagesRoot = $frameworkRoot . '/packages';
        $observabilityFile = $repoRoot . '/docs/ssot/observability.md';

        if (!\is_file($observabilityFile) || !\is_readable($observabilityFile)) {
            throw new \RuntimeException('observability-ssot-missing');
        }

        /** @var array{allowed:array<string, true>, forbidden:array<string, true>} $policy */
        $policy = coretsia_observability_naming_gate_parse_observability_policy($observabilityFile);

        if ($policy['allowed'] === [] || $policy['forbidden'] === []) {
            throw new \RuntimeException('observability-policy-empty');
        }

        if (!\is_dir($packagesRoot)) {
            exit(0);
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach (coretsia_observability_naming_gate_find_php_sources($packagesRoot) as $absPath) {
            foreach (
                coretsia_observability_naming_gate_scan_php_file(
                    $absPath,
                    $frameworkRoot,
                    $policy['allowed'],
                    $policy['forbidden'],
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }

        $violations = \array_values(\array_unique($violations));
        \sort($violations, \SORT_STRING);

        if ($violations === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $violations);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return array{allowed:array<string, true>, forbidden:array<string, true>}
 */
function coretsia_observability_naming_gate_parse_observability_policy(string $observabilityFile): array
{
    $content = coretsia_observability_naming_gate_read_file($observabilityFile);
    $lines = \preg_split('/\R/u', $content);
    if (!\is_array($lines)) {
        throw new \RuntimeException('observability-lines-invalid');
    }

    /** @var array<string, true> $allowed */
    $allowed = [];

    /** @var array<string, true> $forbidden */
    $forbidden = [];

    $section = null;

    foreach ($lines as $line) {
        $trimmed = \trim($line);

        if ($trimmed === '## Label Allowlist (MUST)') {
            $section = 'allowed';
            continue;
        }

        if ($trimmed === '## Forbidden Label Keys (MUST)') {
            $section = 'forbidden';
            continue;
        }

        if ($section !== null && \str_starts_with($trimmed, '## ')) {
            $section = null;
            continue;
        }

        if ($section === null) {
            continue;
        }

        if (!\preg_match('/^-\s+`([^`]+)`$/u', $trimmed, $m)) {
            continue;
        }

        $label = $m[1];

        if (!\preg_match('/^[a-z][a-z0-9_]*$/', $label)) {
            throw new \RuntimeException('observability-label-invalid');
        }

        if ($section === 'allowed') {
            $allowed[$label] = true;
            continue;
        }

        if ($section === 'forbidden') {
            $forbidden[$label] = true;
        }
    }

    \ksort($allowed, \SORT_STRING);
    \ksort($forbidden, \SORT_STRING);

    return [
        'allowed' => $allowed,
        'forbidden' => $forbidden,
    ];
}

/**
 * @return list<string>
 */
function coretsia_observability_naming_gate_find_php_sources(string $packagesRoot): array
{
    $packagesRoot = \rtrim(\str_replace('\\', '/', $packagesRoot), '/');

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

    /** @var list<string> $phpFiles */
    $phpFiles = [];

    foreach ($layers as $layer) {
        if ($layer === '.' || $layer === '..') {
            continue;
        }

        $layerDir = $packagesRoot . '/' . $layer;
        if (!\is_dir($layerDir)) {
            continue;
        }

        $slugs = \scandir($layerDir);
        if ($slugs === false) {
            throw new \RuntimeException('package-layer-scan-failed');
        }

        foreach ($slugs as $slug) {
            if ($slug === '.' || $slug === '..') {
                continue;
            }

            $packageRoot = $layerDir . '/' . $slug;
            if (!\is_dir($packageRoot)) {
                continue;
            }

            $srcRoot = $packageRoot . '/src';
            if (!\is_dir($srcRoot)) {
                continue;
            }

            try {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator(
                        $srcRoot,
                        \FilesystemIterator::SKIP_DOTS,
                    ),
                    \RecursiveIteratorIterator::LEAVES_ONLY,
                );
            } catch (\Throwable) {
                throw new \RuntimeException('src-root-iterator-failed');
            }

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo instanceof \SplFileInfo) {
                    continue;
                }

                if (!$fileInfo->isFile()) {
                    continue;
                }

                $absPath = \rtrim(\str_replace('\\', '/', $fileInfo->getPathname()), '/');
                if (!\str_ends_with($absPath, '.php')) {
                    continue;
                }

                if (
                    \str_contains($absPath, '/tests/')
                    || \str_contains($absPath, '/fixtures/')
                    || \str_contains($absPath, '/vendor/')
                ) {
                    continue;
                }

                $phpFiles[] = $absPath;
            }
        }
    }

    $phpFiles = \array_values(\array_unique($phpFiles));
    \sort($phpFiles, \SORT_STRING);

    return $phpFiles;
}

/**
 * @param array<string, true> $allowedLabels
 * @param array<string, true> $forbiddenLabels
 * @return list<string>
 */
function coretsia_observability_naming_gate_scan_php_file(
    string $absPath,
    string $frameworkRoot,
    array $allowedLabels,
    array $forbiddenLabels,
): array {
    $source = coretsia_observability_naming_gate_read_file($absPath);

    try {
        $tokens = \token_get_all($source);
    } catch (\Throwable) {
        throw new \RuntimeException('php-tokenize-failed');
    }

    /** @var list<string> $violations */
    $violations = [];

    $frameworkRelPath = coretsia_observability_naming_gate_rel_from_framework($absPath, $frameworkRoot);
    $count = \count($tokens);

    for ($i = 0; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
            continue;
        }

        $value = coretsia_observability_naming_gate_decode_php_string_literal($token[1]);

        if ($value === '') {
            continue;
        }

        if (
            coretsia_observability_naming_gate_is_metric_context($tokens, $i, $absPath)
            && coretsia_observability_naming_gate_is_metric_name_candidate($value)
            && !coretsia_observability_naming_gate_is_valid_metric_name($value)
        ) {
            $violations[] = $frameworkRelPath . ': metric-name-invalid';
        }

        $isArrayKey = coretsia_observability_naming_gate_is_array_key_token($tokens, $i);

        if (!$isArrayKey) {
            if (
                isset($forbiddenLabels[$value])
                && coretsia_observability_naming_gate_is_observability_scalar_label_key_context($tokens, $i, $absPath)
            ) {
                $violations[] = $frameworkRelPath . ': forbidden-label-key';
            }

            if (
                !isset($forbiddenLabels[$value])
                && !isset($allowedLabels[$value])
                && coretsia_observability_naming_gate_is_observability_scalar_label_key_context($tokens, $i, $absPath)
            ) {
                $violations[] = $frameworkRelPath . ': label-key-not-allowlisted';
            }

            continue;
        }

        if (coretsia_observability_naming_gate_is_observability_structural_key($value)) {
            continue;
        }

        if (!coretsia_observability_naming_gate_is_explicit_observability_label_array_key($tokens, $i, $absPath)) {
            continue;
        }

        if (isset($forbiddenLabels[$value])) {
            $violations[] = $frameworkRelPath . ': forbidden-label-key';
            continue;
        }

        if (!isset($allowedLabels[$value])) {
            $violations[] = $frameworkRelPath . ': label-key-not-allowlisted';
        }
    }

    $violations = \array_values(\array_unique($violations));
    \sort($violations, \SORT_STRING);

    return $violations;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_array_key_token(array $tokens, int $index): bool
{
    $nextIndex = coretsia_observability_naming_gate_next_meaningful_token_index($tokens, $index + 1);

    if ($nextIndex === null) {
        return false;
    }

    $next = $tokens[$nextIndex];

    return \is_array($next) && $next[0] === T_DOUBLE_ARROW;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_explicit_observability_label_array_key(
    array $tokens,
    int $index,
    string $absPath,
): bool {
    $containerKey = coretsia_observability_naming_gate_observability_container_key_for_array_key($tokens, $index);
    if ($containerKey !== null) {
        return true;
    }

    $callName = coretsia_observability_naming_gate_call_name_for_array_argument($tokens, $index);
    if ($callName === null) {
        return false;
    }

    return coretsia_observability_naming_gate_is_labelish_callable_name($callName)
        || (
            coretsia_observability_naming_gate_is_metric_context($tokens, $index, $absPath)
            && coretsia_observability_naming_gate_is_observability_callable_name($callName)
        );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_observability_container_key_for_array_key(array $tokens, int $index): ?string
{
    $openIndex = coretsia_observability_naming_gate_current_short_array_open_index($tokens, $index);
    if ($openIndex === null) {
        return null;
    }

    $containerKey = coretsia_observability_naming_gate_array_value_key_before_open($tokens, $openIndex);
    if ($containerKey === null) {
        return null;
    }

    if (isset([
            'label' => true,
            'labels' => true,
            'attribute' => true,
            'attributes' => true,
            'dimension' => true,
            'dimensions' => true,
            'tag' => true,
            'tags' => true,
            'span_attribute' => true,
            'span_attributes' => true,
            'metric_label' => true,
            'metric_labels' => true,
        ][$containerKey])) {
        return $containerKey;
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_current_short_array_open_index(array $tokens, int $index): ?int
{
    $depth = 0;

    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($token === ']') {
            $depth++;
            continue;
        }

        if ($token === '[') {
            if ($depth === 0) {
                return $i;
            }

            $depth--;
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_array_value_key_before_open(array $tokens, int $openIndex): ?string
{
    $arrowIndex = coretsia_observability_naming_gate_previous_meaningful_token_index($tokens, $openIndex - 1);
    if ($arrowIndex === null) {
        return null;
    }

    $arrow = $tokens[$arrowIndex];
    if (!\is_array($arrow) || $arrow[0] !== T_DOUBLE_ARROW) {
        return null;
    }

    $keyIndex = coretsia_observability_naming_gate_previous_meaningful_token_index($tokens, $arrowIndex - 1);
    if ($keyIndex === null) {
        return null;
    }

    $key = $tokens[$keyIndex];

    if (\is_array($key) && $key[0] === T_CONSTANT_ENCAPSED_STRING) {
        return coretsia_observability_naming_gate_normalize_identifier(
            coretsia_observability_naming_gate_decode_php_string_literal($key[1]),
        );
    }

    if (\is_array($key) && $key[0] === T_STRING) {
        return coretsia_observability_naming_gate_normalize_identifier($key[1]);
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_call_name_for_array_argument(array $tokens, int $index): ?string
{
    $openIndex = coretsia_observability_naming_gate_current_short_array_open_index($tokens, $index);
    if ($openIndex === null) {
        return null;
    }

    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $openIndex);
    if ($parenIndex === null) {
        return null;
    }

    $nameIndex = coretsia_observability_naming_gate_previous_meaningful_token_index($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return null;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return null;
    }

    return coretsia_observability_naming_gate_normalize_identifier($name[1]);
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_find_enclosing_call_open_paren_index(array $tokens, int $index): ?int
{
    $depth = 0;

    for ($i = $index; $i >= 0; $i--) {
        $token = $tokens[$i];

        if ($token === ')') {
            $depth++;
            continue;
        }

        if ($token === '(') {
            if ($depth === 0) {
                $nameIndex = coretsia_observability_naming_gate_previous_meaningful_token_index($tokens, $i - 1);
                if ($nameIndex === null) {
                    return null;
                }

                $name = $tokens[$nameIndex];

                return \is_array($name) && $name[0] === T_STRING ? $i : null;
            }

            $depth--;
        }
    }

    return null;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_observability_scalar_label_key_context(
    array $tokens,
    int $index,
    string $absPath,
): bool {
    $callName = coretsia_observability_naming_gate_call_name_for_scalar_argument($tokens, $index);
    if ($callName === null) {
        return false;
    }

    return coretsia_observability_naming_gate_is_labelish_callable_name($callName)
        || (
            coretsia_observability_naming_gate_is_metric_context($tokens, $index, $absPath)
            && coretsia_observability_naming_gate_is_observability_callable_name($callName)
        );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_call_name_for_scalar_argument(array $tokens, int $index): ?string
{
    $parenIndex = coretsia_observability_naming_gate_find_enclosing_call_open_paren_index($tokens, $index);
    if ($parenIndex === null) {
        return null;
    }

    $nameIndex = coretsia_observability_naming_gate_previous_meaningful_token_index($tokens, $parenIndex - 1);
    if ($nameIndex === null) {
        return null;
    }

    $name = $tokens[$nameIndex];
    if (!\is_array($name) || $name[0] !== T_STRING) {
        return null;
    }

    return coretsia_observability_naming_gate_normalize_identifier($name[1]);
}

function coretsia_observability_naming_gate_is_labelish_callable_name(string $name): bool
{
    return \str_contains($name, 'label')
        || \str_contains($name, 'attribute')
        || \str_contains($name, 'dimension')
        || \str_contains($name, 'tag');
}

function coretsia_observability_naming_gate_is_observability_callable_name(string $name): bool
{
    return coretsia_observability_naming_gate_is_labelish_callable_name($name)
        || \str_contains($name, 'metric')
        || \str_contains($name, 'counter')
        || \str_contains($name, 'histogram')
        || \str_contains($name, 'gauge')
        || \str_contains($name, 'timer')
        || \str_contains($name, 'observe')
        || \str_contains($name, 'span')
        || \str_contains($name, 'trace');
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_previous_meaningful_token_index(array $tokens, int $start): ?int
{
    for ($i = $start; $i >= 0; $i--) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            return $i;
        }

        if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            return $i;
        }
    }

    return null;
}

function coretsia_observability_naming_gate_normalize_identifier(string $value): string
{
    return \strtolower(\str_replace('-', '_', $value));
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_next_meaningful_token_index(array $tokens, int $start): ?int
{
    $count = \count($tokens);

    for ($i = $start; $i < $count; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            return $i;
        }

        if ($token[0] !== T_WHITESPACE && $token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
            return $i;
        }
    }

    return null;
}

function coretsia_observability_naming_gate_is_metric_name_candidate(string $value): bool
{
    if (\str_contains($value, "\n") || \str_contains($value, "\r") || \str_contains($value, ' ')) {
        return false;
    }

    if (\str_contains($value, '/') || \str_contains($value, ':') || \str_contains($value, '\\')) {
        return false;
    }

    if (\str_contains($value, '.')) {
        return true;
    }

    return (bool)\preg_match('/_[a-z][a-z0-9]*(?:_[a-z][a-z0-9]*)*$/', $value);
}

function coretsia_observability_naming_gate_is_valid_metric_name(string $value): bool
{
    return (bool)\preg_match(
        '/^[a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+_[a-z][a-z0-9]*(?:_[a-z][a-z0-9]*)*$/',
        $value,
    );
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_metric_context(array $tokens, int $index, string $absPath): bool
{
    $path = \strtolower(\str_replace('\\', '/', $absPath));
    if (
        \str_contains($path, '/metric/')
        || \str_contains($path, '/metrics/')
        || \str_contains($path, '/observability/')
    ) {
        return true;
    }

    $context = coretsia_observability_naming_gate_context_words($tokens, $index, 56);

    foreach ([
                 'metric',
                 'metrics',
                 'counter',
                 'histogram',
                 'gauge',
                 'timer',
                 'observe',
                 'measurement',
                 'measure',
                 'increment',
                 'decrement',
             ] as $needle) {
        if (\str_contains($context, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_labelish_context(array $tokens, int $index, string $absPath): bool
{
    if (coretsia_observability_naming_gate_is_metric_context($tokens, $index, $absPath)) {
        return true;
    }

    $path = \strtolower(\str_replace('\\', '/', $absPath));
    if (
        \str_contains($path, '/tracing/')
        || \str_contains($path, '/trace/')
        || \str_contains($path, '/span/')
        || \str_contains($path, '/spans/')
        || \str_contains($path, '/observability/')
    ) {
        return true;
    }

    $context = coretsia_observability_naming_gate_context_words($tokens, $index, 56);

    foreach ([
                 'label',
                 'labels',
                 'attribute',
                 'attributes',
                 'dimension',
                 'dimensions',
                 'tag',
                 'tags',
                 'span',
                 'spans',
                 'trace',
                 'tracing',
             ] as $needle) {
        if (\str_contains($context, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_is_observability_context(array $tokens, int $index, string $absPath): bool
{
    if (coretsia_observability_naming_gate_is_labelish_context($tokens, $index, $absPath)) {
        return true;
    }

    $path = \strtolower(\str_replace('\\', '/', $absPath));
    if (
        \str_contains($path, '/logging/')
        || \str_contains($path, '/logger/')
        || \str_contains($path, '/log/')
    ) {
        return true;
    }

    $context = coretsia_observability_naming_gate_context_words($tokens, $index, 56);

    foreach ([
                 'log',
                 'logger',
                 'logging',
                 'event',
                 'events',
                 'context',
                 'redact',
                 'redaction',
                 'telemetry',
                 'observability',
             ] as $needle) {
        if (\str_contains($context, $needle)) {
            return true;
        }
    }

    return false;
}

function coretsia_observability_naming_gate_is_observability_structural_key(string $value): bool
{
    return isset([
            'attribute' => true,
            'attributes' => true,
            'bucket' => true,
            'buckets' => true,
            'description' => true,
            'dimension' => true,
            'dimensions' => true,
            'label' => true,
            'labels' => true,
            'metric' => true,
            'metrics' => true,
            'name' => true,
            'span' => true,
            'spans' => true,
            'tag' => true,
            'tags' => true,
            'type' => true,
            'unit' => true,
            'value' => true,
        ][$value]);
}

/**
 * @param list<array{0:int, 1:string, 2:int}|string> $tokens
 */
function coretsia_observability_naming_gate_context_words(array $tokens, int $index, int $radius): string
{
    /** @var list<string> $words */
    $words = [];

    $start = \max(0, $index - $radius);
    $end = \min(\count($tokens) - 1, $index + $radius);

    for ($i = $start; $i <= $end; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            continue;
        }

        if (
            $token[0] !== T_STRING
            && $token[0] !== T_VARIABLE
            && $token[0] !== T_NAME_FULLY_QUALIFIED
            && $token[0] !== T_NAME_QUALIFIED
            && $token[0] !== T_CONSTANT_ENCAPSED_STRING
        ) {
            continue;
        }

        $text = $token[1];

        if ($token[0] === T_VARIABLE) {
            $text = \ltrim($text, '$');
        }

        if ($token[0] === T_CONSTANT_ENCAPSED_STRING) {
            try {
                $text = coretsia_observability_naming_gate_decode_php_string_literal($text);
            } catch (\Throwable) {
                continue;
            }
        }

        $text = \strtolower($text);
        $text = \preg_replace('/[^a-z0-9_]+/', ' ', $text);
        if (!\is_string($text) || $text === '') {
            continue;
        }

        $words[] = $text;
    }

    return ' ' . \implode(' ', $words) . ' ';
}

function coretsia_observability_naming_gate_decode_php_string_literal(string $literal): string
{
    if (\strlen($literal) < 2) {
        throw new \RuntimeException('php-string-literal-invalid');
    }

    $quote = $literal[0];
    $inner = \substr($literal, 1, -1);

    if ($quote === "'") {
        return \str_replace(
            ["\\\\", "\\'"],
            ["\\", "'"],
            $inner,
        );
    }

    if ($quote === '"') {
        return \stripcslashes($inner);
    }

    throw new \RuntimeException('php-string-literal-quote-invalid');
}

function coretsia_observability_naming_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $content = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($content)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $content;
}

function coretsia_observability_naming_gate_rel_from_framework(string $absPath, string $frameworkRoot): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $frameworkRoot = \rtrim(\str_replace('\\', '/', $frameworkRoot), '/');

    if ($absPath === $frameworkRoot) {
        return '.';
    }

    if (!\str_starts_with($absPath, $frameworkRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($frameworkRoot) + 1);
}
