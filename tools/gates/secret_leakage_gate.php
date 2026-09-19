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

use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/GateRuntime.php';

$argv = isset($_SERVER['argv']) && \is_array($_SERVER['argv'])
    ? $_SERVER['argv']
    : [];

exit(GateRuntime::execute(
    'CORETSIA_SECRET_LEAK_DETECTED',
    'CORETSIA_SECRET_GATE_SCAN_FAILED',
    static function (RepositoryContext $repository) use ($argv): array {
        $options = coretsia_secret_leakage_gate_parse_options(
            $repository,
            $argv,
        );

        $result = $options['gitleaks_json'] === null
            ? coretsia_secret_leakage_gate_run_gitleaks($options['source_root'], $options['config_path'])
            : coretsia_secret_leakage_gate_mocked_gitleaks_result(
                $options['gitleaks_json'],
                $options['gitleaks_exit_code'],
            );

        $findings = coretsia_secret_leakage_gate_parse_findings_json($result['report']);
        $diagnostics = coretsia_secret_leakage_gate_collect_diagnostics(
            $findings,
            $options['source_root'],
        );

        if ($diagnostics === []) {
            if ($result['exitKnown'] && $result['exit'] === 0) {
                return [];
            }

            throw new \RuntimeException('gitleaks-nonzero-without-findings');
        }

        return $diagnostics;
    },
));

/**
 * @param list<string> $argv
 *
 * @return array{
 *     'source_root': string,
 *     'config_path': string,
 *     'gitleaks_json': string|null,
 *     'gitleaks_exit_code': int
 * }
 */
function coretsia_secret_leakage_gate_parse_options(
    RepositoryContext $repository,
    array $argv,
): array {
    $path = null;
    $config = null;
    $gitleaksJson = null;
    $gitleaksExitCode = 0;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '' || $arg === '--') {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $path = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '--config=')) {
            $config = \substr($arg, \strlen('--config='));
            continue;
        }

        /*
         * Test-only: bypass live Gitleaks and feed captured JSON fixture into
         * the same parser/output path. This keeps integration tests independent
         * from local machine / CI Gitleaks availability.
         */
        if (\str_starts_with($arg, '--gitleaks-json=')) {
            $gitleaksJson = \substr($arg, \strlen('--gitleaks-json='));
            continue;
        }

        if (\str_starts_with($arg, '--gitleaks-exit-code=')) {
            $raw = \substr($arg, \strlen('--gitleaks-exit-code='));
            if (\preg_match('/\A[0-9]{1,3}\z/', $raw) !== 1) {
                throw new \RuntimeException('gitleaks-exit-code-invalid');
            }

            $gitleaksExitCode = (int) $raw;
            if ($gitleaksExitCode < 0 || $gitleaksExitCode > 255) {
                throw new \RuntimeException('gitleaks-exit-code-invalid');
            }
        }
    }

    $sourceRoot = $path === null
        ? $repository->repoRoot()
        : $repository->resolveExistingDirectory($path);

    $configPath = coretsia_secret_leakage_gate_resolve_source_file(
        $repository,
        $config ?? '.gitleaks.toml',
        $sourceRoot,
    );

    $resolvedGitleaksJson = $gitleaksJson === null
        ? null
        : coretsia_secret_leakage_gate_resolve_source_file(
            $repository,
            $gitleaksJson,
            $sourceRoot,
        );

    return [
        'source_root' => $sourceRoot,
        'config_path' => \rtrim(\str_replace('\\', '/', $configPath), '/'),
        'gitleaks_json' => $resolvedGitleaksJson,
        'gitleaks_exit_code' => $gitleaksExitCode,
    ];
}

function coretsia_secret_leakage_gate_resolve_source_file(
    RepositoryContext $repository,
    string $path,
    string $sourceRoot,
): string {
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        throw new \RuntimeException('file-path-empty');
    }

    $candidate = RepositoryContext::isAbsolutePath($path)
        ? $path
        : \rtrim($sourceRoot, '/') . '/' . \ltrim($path, '/');

    $resolved = $repository->resolveExistingFile($candidate);

    if (!RepositoryContext::containsPath($sourceRoot, $resolved)) {
        throw new \RuntimeException('file-path-outside-source-root');
    }

    return $resolved;
}

/**
 * @return array{
 *     'exit': int,
 *     'exitKnown': bool,
 *     'stdout': string,
 *     'stderr': string,
 *     'report': string
 * }
 */
function coretsia_secret_leakage_gate_mocked_gitleaks_result(string $jsonPath, int $exitCode): array
{
    return [
        'exit' => $exitCode,
        'exitKnown' => true,
        'stdout' => '',
        'stderr' => '',
        'report' => DeterministicFile::readBytesExact($jsonPath),
    ];
}

/**
 * @return array{
 *     'exit': int,
 *     'exitKnown': bool,
 *     'stdout': string,
 *     'stderr': string,
 *     'report': string
 * }
 */
function coretsia_secret_leakage_gate_run_gitleaks(string $sourceRoot, string $configPath): array
{
    $reportPath = coretsia_secret_leakage_gate_temp_report_path();

    /** @var array{
     *     'exit': int,
     *     'exitKnown': bool,
     *     'stdout': string,
     *     'stderr': string,
     *     'report': string
     * }|null $result
     */
    $result = null;

    try {
        $argv = [
            'gitleaks',
            'dir',
            $sourceRoot,
            '--config=' . $configPath,
            '--report-format=json',
            '--report-path=' . $reportPath,
            '--redact',
            '--no-banner',
            '--no-color',
            '--max-archive-depth=0',
            '--max-decode-depth=0',
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        foreach (coretsia_secret_leakage_gate_command_candidates($argv) as $cmd) {
            $pipes = [];

            \set_error_handler(static function (): bool {
                return true;
            });

            try {
                $process = \proc_open(
                    $cmd,
                    $descriptors,
                    $pipes,
                    $sourceRoot,
                    null,
                    coretsia_secret_leakage_gate_proc_options($cmd),
                );
            } finally {
                \restore_error_handler();
            }

            if (!\is_resource($process)) {
                continue;
            }

            \fclose($pipes[0]);

            \stream_set_blocking($pipes[1], false);
            \stream_set_blocking($pipes[2], false);

            $stdout = '';
            $stderr = '';
            $deadline = \microtime(true) + 60.0;

            while (!\feof($pipes[1]) || !\feof($pipes[2])) {
                if (\PHP_OS_FAMILY === 'Windows') {
                    if (!\feof($pipes[1])) {
                        $stdout .= (string) \stream_get_contents($pipes[1]);
                    }

                    if (!\feof($pipes[2])) {
                        $stderr .= (string) \stream_get_contents($pipes[2]);
                    }

                    \usleep(10_000);
                } else {
                    $read = [];

                    if (!\feof($pipes[1])) {
                        $read[] = $pipes[1];
                    }

                    if (!\feof($pipes[2])) {
                        $read[] = $pipes[2];
                    }

                    if ($read !== []) {
                        $write = null;
                        $except = null;

                        \set_error_handler(static function (): bool {
                            return true;
                        });

                        try {
                            $ready = \stream_select(
                                $read,
                                $write,
                                $except,
                                0,
                                10_000,
                            );
                        } finally {
                            \restore_error_handler();
                        }

                        if ($ready === false) {
                            throw new \RuntimeException('gitleaks-stream-select-failed');
                        }

                        foreach ($read as $stream) {
                            if ($stream === $pipes[1]) {
                                $stdout .= (string) \stream_get_contents($pipes[1]);

                                continue;
                            }

                            if ($stream === $pipes[2]) {
                                $stderr .= (string) \stream_get_contents($pipes[2]);
                            }
                        }
                    }
                }

                if (\strlen($stdout) + \strlen($stderr) > 2_000_000) {
                    \proc_terminate($process);

                    throw new \RuntimeException('gitleaks-output-too-large');
                }

                if (\microtime(true) > $deadline) {
                    \proc_terminate($process);

                    throw new \RuntimeException('gitleaks-process-timeout');
                }
            }

            $stdout .= (string) \stream_get_contents($pipes[1]);
            $stderr .= (string) \stream_get_contents($pipes[2]);

            \fclose($pipes[1]);
            \fclose($pipes[2]);

            $closeExit = \proc_close($process);
            $exitKnown = $closeExit >= 0;
            $exit = $exitKnown ? $closeExit : 1;

            $result = [
                'exit' => $exit,
                'exitKnown' => $exitKnown,
                'stdout' => $stdout,
                'stderr' => $stderr,
                'report' => DeterministicFile::readBytesExact($reportPath),
            ];

            break;
        }

        if ($result === null) {
            throw new \RuntimeException('gitleaks-process-start-failed');
        }
    } finally {
        coretsia_secret_leakage_gate_remove_file($reportPath);
    }

    return $result;
}

function coretsia_secret_leakage_gate_temp_report_path(): string
{
    $base = \rtrim(\str_replace('\\', '/', \sys_get_temp_dir()), '/');

    if ($base === '') {
        throw new \RuntimeException('temp-dir-invalid');
    }

    $path = \tempnam($base, 'coretsia-gitleaks-report-');

    if ($path === false) {
        throw new \RuntimeException('temp-report-create-failed');
    }

    return \str_replace('\\', '/', $path);
}

function coretsia_secret_leakage_gate_remove_file(string $path): void
{
    if ($path === '') {
        return;
    }

    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        if (\is_file($path)) {
            \unlink($path);
        }
    } finally {
        \restore_error_handler();
    }
}

/**
 * @param non-empty-list<string> $argv
 *
 * @return list<string|array<int,string>>
 */
function coretsia_secret_leakage_gate_command_candidates(array $argv): array
{
    if (\PHP_OS_FAMILY === 'Windows') {
        return [
            $argv,
            coretsia_secret_leakage_gate_shell_command($argv),
        ];
    }

    return [
        coretsia_secret_leakage_gate_shell_command($argv),
    ];
}

/**
 * @param non-empty-list<string> $argv
 */
function coretsia_secret_leakage_gate_shell_command(array $argv): string
{
    $parts = [];

    foreach ($argv as $arg) {
        if ($arg === '') {
            throw new \RuntimeException('gitleaks-command-argument-invalid');
        }

        $parts[] = \escapeshellarg($arg);
    }

    return \implode(' ', $parts);
}

/**
 * @param string|array<int,string> $cmd
 *
 * @return array<string,mixed>
 */
function coretsia_secret_leakage_gate_proc_options(string|array $cmd): array
{
    if (\PHP_OS_FAMILY === 'Windows' && \is_array($cmd)) {
        return ['bypass_shell' => true];
    }

    return [];
}

/**
 * @return list<array<string,mixed>>
 */
function coretsia_secret_leakage_gate_parse_findings_json(string $report): array
{
    $payload = coretsia_secret_leakage_gate_decode_json_payload($report);

    if ($payload !== null) {
        return $payload;
    }

    throw new \RuntimeException('gitleaks-json-invalid');
}

/**
 * @return list<array<string,mixed>>|null
 */
function coretsia_secret_leakage_gate_decode_json_payload(string $output): ?array
{
    $output = \trim(\str_replace(["\r\n", "\r"], "\n", $output));

    if ($output === '') {
        return null;
    }

    $decoded = \json_decode($output, true);
    if (\json_last_error() === \JSON_ERROR_NONE && \is_array($decoded) && \array_is_list($decoded)) {
        return coretsia_secret_leakage_gate_normalize_findings_list($decoded);
    }

    $first = \strpos($output, '[');
    $last = \strrpos($output, ']');

    if ($first === false || $last === false || $last < $first) {
        return null;
    }

    $candidate = \substr($output, $first, ($last - $first) + 1);
    $decoded = \json_decode($candidate, true);

    if (\json_last_error() !== \JSON_ERROR_NONE || !\is_array($decoded) || !\array_is_list($decoded)) {
        return null;
    }

    return coretsia_secret_leakage_gate_normalize_findings_list($decoded);
}

/**
 * @param list<mixed> $decoded
 *
 * @return list<array<string,mixed>>
 */
function coretsia_secret_leakage_gate_normalize_findings_list(array $decoded): array
{
    /** @var list<array<string,mixed>> $findings */
    $findings = [];

    foreach ($decoded as $item) {
        if (!\is_array($item) || \array_is_list($item)) {
            throw new \RuntimeException('gitleaks-finding-invalid');
        }

        /** @var array<string,mixed> $item */
        $findings[] = $item;
    }

    return $findings;
}

/**
 * @param list<array<string,mixed>> $findings
 *
 * @return list<string>
 */
function coretsia_secret_leakage_gate_collect_diagnostics(array $findings, string $sourceRoot): array
{
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($findings as $finding) {
        $file = coretsia_secret_leakage_gate_finding_file($finding, $sourceRoot);
        $rule = coretsia_secret_leakage_gate_finding_rule($finding);
        $line = coretsia_secret_leakage_gate_finding_line($finding);

        $diagnostics[] = $line === null
            ? $file . ': ' . $rule
            : $file . ':' . $line . ': ' . $rule;
    }

    return coretsia_secret_leakage_gate_sorted_unique($diagnostics);
}

/**
 * @param array<string,mixed> $finding
 */
function coretsia_secret_leakage_gate_finding_file(array $finding, string $sourceRoot): string
{
    foreach (['File', 'file'] as $key) {
        $value = $finding[$key] ?? null;

        if (\is_string($value) && $value !== '') {
            return coretsia_secret_leakage_gate_safe_relative_path($value, $sourceRoot);
        }
    }

    return 'unknown-path';
}

/**
 * @param array<string,mixed> $finding
 */
function coretsia_secret_leakage_gate_finding_rule(array $finding): string
{
    foreach (['RuleID', 'ruleID', 'rule_id', 'Rule', 'rule'] as $key) {
        $value = $finding[$key] ?? null;

        if (\is_string($value) && \preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) === 1) {
            return $value;
        }
    }

    return 'unknown-rule';
}

/**
 * @param array<string,mixed> $finding
 */
function coretsia_secret_leakage_gate_finding_line(array $finding): ?int
{
    foreach (['StartLine', 'startLine', 'start_line', 'Line', 'line'] as $key) {
        $value = $finding[$key] ?? null;

        if (\is_int($value) && $value > 0) {
            return $value;
        }

        if (\is_string($value) && \preg_match('/\A[1-9][0-9]*\z/', $value) === 1) {
            return (int) $value;
        }
    }

    return null;
}

function coretsia_secret_leakage_gate_safe_relative_path(string $path, string $sourceRoot): string
{
    $path = \trim(\str_replace('\\', '/', $path));

    if ($path === '') {
        return 'unknown-path';
    }

    $sourceRoot = \rtrim(\str_replace('\\', '/', $sourceRoot), '/');

    if (RepositoryContext::isAbsolutePath($path)) {
        $normalized = \rtrim($path, '/');

        if ($normalized === $sourceRoot) {
            return 'unknown-path';
        }

        $prefix = $sourceRoot . '/';
        if (!\str_starts_with($normalized, $prefix)) {
            return 'unknown-path';
        }

        $path = \substr($normalized, \strlen($prefix));
    }

    $path = \ltrim($path, '/');

    if ($path === '') {
        return 'unknown-path';
    }

    $parts = \explode('/', $path);
    $safeParts = [];

    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            return 'unknown-path';
        }

        if (\preg_match('/\A[A-Za-z0-9._@+-]+\z/', $part) !== 1) {
            return 'unknown-path';
        }

        $safeParts[] = $part;
    }

    $relative = \implode('/', $safeParts);

    if (
        $relative === ''
        || \str_contains($relative, '://')
        || \str_contains($relative, '=')
    ) {
        return 'unknown-path';
    }

    return $relative;
}

/**
 * @param list<string> $values
 *
 * @return list<string>
 */
function coretsia_secret_leakage_gate_sorted_unique(array $values): array
{
    $values = \array_values(\array_unique($values));
    \usort($values, static fn (string $a, string $b): int => \strcmp($a, $b));

    return $values;
}
