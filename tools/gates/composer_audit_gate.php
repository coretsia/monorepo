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
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/RepositoryContext.php';

(static function (array $argv): void {
    try {
        $options = coretsia_composer_audit_gate_resolve_options(
            $argv,
        );

        $repository = $options['repo_root'] === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot(
                $options['repo_root'],
            );

        coretsia_composer_audit_gate_assert_root_installation($repository);

        $composerCommand = coretsia_composer_audit_gate_resolve_composer_command(
            $options['composer'],
            $repository,
        );

        $result = coretsia_composer_audit_gate_run_composer_audit(
            $composerCommand,
            $repository->repoRoot(),
        );

        if (coretsia_composer_audit_gate_is_network_failure($result)) {
            ConsoleOutput::codeWithDiagnostics(
                ErrorCodes::CORETSIA_TOOLS_NETWORK_FAILED,
            );

            exit(1);
        }

        $payload = coretsia_composer_audit_gate_parse_audit_json($result);
        $diagnostics = coretsia_composer_audit_gate_collect_diagnostics($payload);

        if (
            $diagnostics === []
            && $result['exitKnown']
            && $result['exit'] !== 0
        ) {
            throw new RuntimeException('composer-audit-process-failed');
        }

        if ($diagnostics === []) {
            exit(0);
        }

        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_COMPOSER_AUDIT_FAILED,
            $diagnostics,
        );

        exit(1);
    } catch (Throwable) {
        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_COMPOSER_AUDIT_SCAN_FAILED,
        );

        exit(1);
    }
})(
    isset($_SERVER['argv']) && is_array($_SERVER['argv'])
        ? $_SERVER['argv']
        : [],
);

/**
 * @param list<string> $argv
 *
 * @return array{
 *     repo_root:string|null,
 *     composer:string|null
 * }
 */
function coretsia_composer_audit_gate_resolve_options(
    array $argv,
): array {
    $repoRoot = null;
    $composer = null;
    $count = count($argv);

    for ($i = 1; $i < $count; $i++) {
        $arg = trim((string) $argv[$i]);

        if ($arg === '') {
            throw new RuntimeException('composer-audit-argument-invalid');
        }

        foreach (
            [
                'repo-root' => 'repo_root',
                'path' => 'repo_root',
                'composer' => 'composer',
            ] as $name => $key
        ) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                $value = trim(
                    substr(
                        $arg,
                        strlen('--' . $name . '='),
                    ),
                );

                if ($value === '') {
                    throw new RuntimeException('composer-audit-argument-invalid');
                }

                if ($key === 'repo_root') {
                    if ($repoRoot !== null) {
                        throw new RuntimeException('composer-audit-argument-duplicate');
                    }

                    $repoRoot = $value;
                } else {
                    if ($composer !== null) {
                        throw new RuntimeException('composer-audit-argument-duplicate');
                    }

                    $composer = $value;
                }

                continue 2;
            }

            if ($arg === '--' . $name) {
                $value = $i + 1 < $count
                    ? trim((string) $argv[$i + 1])
                    : '';

                if (
                    $value === ''
                    || str_starts_with($value, '--')
                ) {
                    throw new RuntimeException('composer-audit-argument-invalid');
                }

                if ($key === 'repo_root') {
                    if ($repoRoot !== null) {
                        throw new RuntimeException('composer-audit-argument-duplicate');
                    }

                    $repoRoot = $value;
                } else {
                    if ($composer !== null) {
                        throw new RuntimeException('composer-audit-argument-duplicate');
                    }

                    $composer = $value;
                }

                $i++;

                continue 2;
            }
        }

        if (str_starts_with($arg, '--')) {
            throw new RuntimeException('composer-audit-unknown-option');
        }

        if ($repoRoot !== null) {
            throw new RuntimeException('composer-audit-argument-duplicate');
        }

        // Preserve the previous positional repository-root form.
        $repoRoot = $arg;
    }

    return [
        'repo_root' => $repoRoot,
        'composer' => $composer,
    ];
}

function coretsia_composer_audit_gate_assert_root_installation(
    RepositoryContext $repository,
): void {
    $repository->resolveExistingFile('composer.json');
    $repository->resolveExistingFile('composer.lock');
    $repository->resolveExistingDirectory('vendor');
}

/**
 * @return non-empty-list<string>
 */
function coretsia_composer_audit_gate_resolve_composer_command(
    ?string $composer,
    RepositoryContext $repository,
): array {
    $composer = trim($composer ?? 'composer');

    if ($composer === '') {
        throw new RuntimeException('composer-command-invalid');
    }

    if ($composer === 'composer') {
        return ['composer'];
    }

    if (RepositoryContext::isAbsolutePath($composer)) {
        $real = @realpath($composer);

        if (
            !is_string($real)
            || !is_file($real)
            || !is_readable($real)
        ) {
            throw new RuntimeException('composer-command-invalid');
        }

        $real = RepositoryContext::normalizePath($real);
    } else {
        $real = $repository->resolveExistingFile($composer);
    }

    if (str_ends_with(strtolower($real), '.php')) {
        return [PHP_BINARY, $real];
    }

    return [$real];
}

/**
 * @param non-empty-list<string> $composerCommand
 *
 * @return array{exit:int,exitKnown:bool,stdout:string,stderr:string}
 */
function coretsia_composer_audit_gate_run_composer_audit(array $composerCommand, string $cwd): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    foreach (coretsia_composer_audit_gate_command_candidates($composerCommand) as $cmd) {
        $pipes = [];

        \set_error_handler(static function (): bool {
            return true;
        });

        try {
            $process = \proc_open(
                $cmd,
                $descriptors,
                $pipes,
                $cwd,
                null,
                coretsia_composer_audit_gate_proc_options($cmd),
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
                        throw new \RuntimeException('composer-stream-select-failed');
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

                throw new \RuntimeException('composer-output-too-large');
            }

            if (\microtime(true) > $deadline) {
                \proc_terminate($process);

                throw new \RuntimeException('composer-process-timeout');
            }
        }

        $stdout .= (string) \stream_get_contents($pipes[1]);
        $stderr .= (string) \stream_get_contents($pipes[2]);

        \fclose($pipes[1]);
        \fclose($pipes[2]);

        $closeExit = \proc_close($process);
        $exitKnown = $closeExit >= 0;
        $exit = $exitKnown ? $closeExit : 1;

        if (coretsia_composer_audit_gate_should_try_next_command($cmd, $exit, $stdout, $stderr)) {
            continue;
        }

        return [
            'exit' => $exit,
            'exitKnown' => $exitKnown,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }

    throw new \RuntimeException('composer-process-start-failed');
}

/**
 * @param non-empty-list<string> $argv
 */
function coretsia_composer_audit_gate_shell_command(array $argv): string
{
    $parts = [];

    foreach ($argv as $arg) {
        if ($arg === '') {
            throw new \RuntimeException('composer-command-argument-invalid');
        }

        $parts[] = \escapeshellarg($arg);
    }

    return \implode(' ', $parts);
}

/**
 * @param non-empty-list<string> $composerCommand
 *
 * @return list<string|array<int,string>>
 */
function coretsia_composer_audit_gate_command_candidates(array $composerCommand): array
{
    if (\count($composerCommand) === 1 && $composerCommand[0] === 'composer') {
        $command = 'composer audit --format=json --abandoned=ignore';

        if (\PHP_OS_FAMILY !== 'Windows') {
            return [$command];
        }

        if (coretsia_composer_audit_gate_prefer_bash_on_windows()) {
            return [
                coretsia_composer_audit_gate_bash_candidate($command),
                coretsia_composer_audit_gate_cmd_candidate($command),
                $command,
            ];
        }

        return [
            coretsia_composer_audit_gate_cmd_candidate($command),
            coretsia_composer_audit_gate_bash_candidate($command),
            $command,
        ];
    }

    $argv = \array_merge($composerCommand, ['audit', '--format=json', '--abandoned=ignore']);

    if (\PHP_OS_FAMILY === 'Windows') {
        return [
            $argv,
            coretsia_composer_audit_gate_shell_command($argv),
        ];
    }

    return [
        coretsia_composer_audit_gate_shell_command($argv),
    ];
}

/**
 * @param string|array<int,string> $cmd
 *
 * @return array<string,mixed>
 */
function coretsia_composer_audit_gate_proc_options(string|array $cmd): array
{
    if (\PHP_OS_FAMILY === 'Windows' && \is_array($cmd)) {
        return ['bypass_shell' => true];
    }

    return [];
}

function coretsia_composer_audit_gate_prefer_bash_on_windows(): bool
{
    if (\PHP_OS_FAMILY !== 'Windows') {
        return false;
    }

    $msystem = \getenv('MSYSTEM');
    if (\is_string($msystem) && $msystem !== '') {
        return true;
    }

    $msys = \getenv('MSYS');
    if (\is_string($msys) && $msys !== '') {
        return true;
    }

    $shell = \getenv('SHELL');
    if (\is_string($shell) && $shell !== '' && \stripos($shell, 'bash') !== false) {
        return true;
    }

    return false;
}

/**
 * @return array<int,string>
 */
function coretsia_composer_audit_gate_bash_candidate(string $command): array
{
    $exe = 'bash';

    if (\PHP_OS_FAMILY === 'Windows') {
        $resolved = coretsia_composer_audit_gate_resolve_bash_exe();
        if ($resolved !== null) {
            $exe = $resolved;
        }
    }

    return [$exe, '--noprofile', '--norc', '-e', '-o', 'pipefail', '-c', $command];
}

/**
 * @return array<int,string>
 */
function coretsia_composer_audit_gate_cmd_candidate(string $command): array
{
    return ['cmd.exe', '/d', '/s', '/c', $command];
}

function coretsia_composer_audit_gate_resolve_bash_exe(): ?string
{
    static $resolved = false;
    static $bashExe = null;

    if ($resolved) {
        return \is_string($bashExe) ? $bashExe : null;
    }

    $resolved = true;

    if (\PHP_OS_FAMILY !== 'Windows') {
        $bashExe = null;
        return null;
    }

    /** @var list<string> $candidates */
    $candidates = [];

    $programFiles = \getenv('ProgramFiles');
    if (\is_string($programFiles) && $programFiles !== '') {
        $candidates[] = $programFiles . '\\Git\\bin\\bash.exe';
        $candidates[] = $programFiles . '\\Git\\usr\\bin\\bash.exe';
    }

    $programFilesX86 = \getenv('ProgramFiles(x86)');
    if (\is_string($programFilesX86) && $programFilesX86 !== '') {
        $candidates[] = $programFilesX86 . '\\Git\\bin\\bash.exe';
        $candidates[] = $programFilesX86 . '\\Git\\usr\\bin\\bash.exe';
    }

    foreach ($candidates as $candidate) {
        if (\is_file($candidate) && \is_readable($candidate)) {
            $bashExe = $candidate;
            return $bashExe;
        }
    }

    $bashExe = null;
    return null;
}

/**
 * @param string|array<int,string> $cmd
 */
function coretsia_composer_audit_gate_should_try_next_command(
    string|array $cmd,
    int $exitCode,
    string $stdout,
    string $stderr,
): bool {
    if (\PHP_OS_FAMILY !== 'Windows') {
        return false;
    }

    if ($exitCode === 0) {
        return false;
    }

    if (!\is_array($cmd) || $cmd === []) {
        return false;
    }

    $base = coretsia_composer_audit_gate_exe_base($cmd);

    if (($base === 'bash' || $base === 'bash.exe') && $exitCode === 127) {
        return true;
    }

    if (($base === 'cmd' || $base === 'cmd.exe') && $exitCode === 9009) {
        return true;
    }

    $payload = \strtolower($stdout . "\n" . $stderr);

    if ($payload !== '') {
        if ($base === 'cmd' || $base === 'cmd.exe') {
            return \str_contains($payload, 'is not recognized as an internal or external command')
                || \str_contains($payload, 'the system cannot find the path specified')
                || \str_contains($payload, 'the system cannot find the file specified');
        }

        if ($base === 'bash' || $base === 'bash.exe') {
            return \str_contains($payload, 'command not found')
                || \str_contains($payload, 'no such file or directory')
                || \str_contains($payload, 'windows subsystem for linux has no installed distributions');
        }

        return false;
    }

    if ($base === 'bash' || $base === 'bash.exe') {
        return \trim($stdout . $stderr) === '';
    }

    return false;
}

/**
 * @param array<int,string> $cmd
 */
function coretsia_composer_audit_gate_exe_base(array $cmd): string
{
    $exe = \strtolower((string) ($cmd[0] ?? ''));
    if ($exe === '') {
        return '';
    }

    $exe = \str_replace('\\', '/', $exe);

    return \strtolower(\basename($exe));
}

/**
 * @param array{
 *     exit:int,
 *     exitKnown:bool,
 *     stdout:string,
 *     stderr:string
 * } $result
 */
function coretsia_composer_audit_gate_is_network_failure(array $result): bool
{
    if (!$result['exitKnown'] || $result['exit'] === 0) {
        return false;
    }

    $output = \strtolower(
        $result['stdout'] . "\n" . $result['stderr'],
    );

    if ($output === '') {
        return false;
    }

    /*
     * Composer CurlDownloader transport failures that mean the remote
     * advisory source could not be reached or read reliably.
     *
     * 5  - could not resolve proxy
     * 6  - could not resolve host
     * 7  - failed to connect
     * 28 - operation timeout
     * 35 - TLS/SSL connect failure
     * 52 - empty reply
     * 55 - send failure
     * 56 - receive failure
     */
    return \preg_match(
        '/\bcurl error (?:5|6|7|28|35|52|55|56)\b/',
        $output,
    ) === 1;
}

/**
 * @param array{exit:int,exitKnown:bool,stdout:string,stderr:string} $result
 *
 * @return array<string,mixed>
 */
function coretsia_composer_audit_gate_parse_audit_json(array $result): array
{
    foreach ([$result['stdout'], $result['stderr']] as $output) {
        $payload = coretsia_composer_audit_gate_decode_json_payload($output);
        if ($payload !== null) {
            return $payload;
        }
    }

    throw new \RuntimeException('composer-audit-json-invalid');
}

/**
 * @return array<string,mixed>|null
 */
function coretsia_composer_audit_gate_decode_json_payload(string $output): ?array
{
    $output = \trim(\str_replace(["\r\n", "\r"], "\n", $output));

    if ($output === '') {
        return null;
    }

    $decoded = \json_decode($output, true);
    if (\json_last_error() === \JSON_ERROR_NONE && \is_array($decoded) && !\array_is_list($decoded)) {
        /** @var array<string,mixed> $decoded */
        return $decoded;
    }

    $first = \strpos($output, '{');
    $last = \strrpos($output, '}');

    if ($first === false || $last === false || $last <= $first) {
        return null;
    }

    $candidate = \substr($output, $first, ($last - $first) + 1);
    $decoded = \json_decode($candidate, true);

    if (\json_last_error() !== \JSON_ERROR_NONE || !\is_array($decoded) || \array_is_list($decoded)) {
        return null;
    }

    /** @var array<string,mixed> $decoded */
    return $decoded;
}

/**
 * @param array<string,mixed> $payload
 *
 * @return list<string>
 */
function coretsia_composer_audit_gate_collect_diagnostics(
    array $payload,
): array {
    if (!\array_key_exists('advisories', $payload) || !\is_array($payload['advisories'])) {
        throw new \RuntimeException('composer-audit-advisories-missing');
    }

    /** @var array<mixed> $advisories */
    $advisories = $payload['advisories'];

    if ($advisories === []) {
        return [];
    }

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    if (\array_is_list($advisories)) {
        foreach ($advisories as $advisory) {
            if (!\is_array($advisory) || \array_is_list($advisory)) {
                throw new \RuntimeException('composer-audit-advisory-invalid');
            }

            $package = coretsia_composer_audit_gate_advisory_package($advisory);
            $advisoryId = coretsia_composer_audit_gate_advisory_id($advisory);

            $diagnostics[] = 'root:' . $package . ':' . $advisoryId;
        }

        return coretsia_composer_audit_gate_sorted_unique($diagnostics);
    }

    foreach ($advisories as $package => $entries) {
        if (!\is_string($package) || !coretsia_composer_audit_gate_is_valid_package_name($package)) {
            throw new \RuntimeException('composer-audit-package-invalid');
        }

        foreach (coretsia_composer_audit_gate_advisory_entries($entries) as $advisory) {
            $advisoryId = coretsia_composer_audit_gate_advisory_id($advisory);

            $diagnostics[] = 'root:' . $package . ':' . $advisoryId;
        }
    }

    return coretsia_composer_audit_gate_sorted_unique($diagnostics);
}

/**
 * @param mixed $entries
 *
 * @return list<array<string,mixed>>
 */
function coretsia_composer_audit_gate_advisory_entries(mixed $entries): array
{
    if (!\is_array($entries)) {
        throw new \RuntimeException('composer-audit-advisory-list-invalid');
    }

    if ($entries === []) {
        return [];
    }

    if (\array_is_list($entries)) {
        /** @var list<array<string,mixed>> $out */
        $out = [];

        foreach ($entries as $entry) {
            if (!\is_array($entry) || \array_is_list($entry)) {
                throw new \RuntimeException('composer-audit-advisory-invalid');
            }

            /** @var array<string,mixed> $entry */
            $out[] = $entry;
        }

        return $out;
    }

    /** @var array<string,mixed> $entries */
    return [$entries];
}

/**
 * @param array<string,mixed> $advisory
 */
function coretsia_composer_audit_gate_advisory_package(array $advisory): string
{
    foreach (['packageName', 'package_name', 'package', 'name'] as $key) {
        $value = $advisory[$key] ?? null;

        if (\is_string($value) && coretsia_composer_audit_gate_is_valid_package_name($value)) {
            return $value;
        }
    }

    throw new \RuntimeException('composer-audit-package-missing');
}

/**
 * @param array<string,mixed> $advisory
 */
function coretsia_composer_audit_gate_advisory_id(array $advisory): string
{
    foreach (['advisoryId', 'advisory_id', 'id', 'cve'] as $key) {
        $value = $advisory[$key] ?? null;

        if (\is_string($value) && coretsia_composer_audit_gate_is_valid_advisory_id($value)) {
            return $value;
        }

        if (\is_array($value)) {
            /** @var list<string> $ids */
            $ids = [];

            foreach ($value as $candidate) {
                if (\is_string($candidate) && coretsia_composer_audit_gate_is_valid_advisory_id($candidate)) {
                    $ids[] = $candidate;
                }
            }

            if ($ids !== []) {
                \usort($ids, static fn (string $a, string $b): int => \strcmp($a, $b));

                return $ids[0];
            }
        }
    }

    throw new \RuntimeException('composer-audit-advisory-id-missing');
}

function coretsia_composer_audit_gate_is_valid_package_name(string $package): bool
{
    return \preg_match('/\A[a-z0-9][a-z0-9_.-]*\/[a-z0-9][a-z0-9_.-]*\z/', $package) === 1;
}

function coretsia_composer_audit_gate_is_valid_advisory_id(string $id): bool
{
    return \preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $id) === 1;
}

/**
 * @param list<string> $values
 *
 * @return list<string>
 */
function coretsia_composer_audit_gate_sorted_unique(array $values): array
{
    $values = \array_values(\array_unique($values));
    \usort($values, static fn (string $a, string $b): int => \strcmp($a, $b));

    return $values;
}
