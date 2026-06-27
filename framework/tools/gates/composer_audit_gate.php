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
                'CORETSIA_COMPOSER_AUDIT_SCAN_FAILED',
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

    $fallbackAuditFailed = 'CORETSIA_COMPOSER_AUDIT_FAILED';
    $fallbackScanFailed = 'CORETSIA_COMPOSER_AUDIT_SCAN_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_COMPOSER_AUDIT_SCAN_FAILED';
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

    $codeAuditFailed = coretsia_composer_audit_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_COMPOSER_AUDIT_FAILED',
        $fallbackAuditFailed,
    );

    $codeScanFailed = coretsia_composer_audit_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_COMPOSER_AUDIT_SCAN_FAILED',
        $fallbackScanFailed,
    );

    try {
        $frameworkRoot = $withSuppressedErrors(static function () use ($toolsRootRuntime): ?string {
            $p = \realpath($toolsRootRuntime . '/..');

            return \is_string($p) ? \rtrim(\str_replace('\\', '/', $p), '/') : null;
        });

        if ($frameworkRoot === null || $frameworkRoot === '') {
            throw new \RuntimeException('framework-root-invalid');
        }

        $defaultRepoRoot = $withSuppressedErrors(static function () use ($frameworkRoot): ?string {
            $p = \realpath($frameworkRoot . '/..');

            return \is_string($p) ? \rtrim(\str_replace('\\', '/', $p), '/') : null;
        });

        if ($defaultRepoRoot === null || $defaultRepoRoot === '') {
            throw new \RuntimeException('repo-root-invalid');
        }

        $repoRoot = coretsia_composer_audit_gate_resolve_repo_root($argv, $defaultRepoRoot);
        $composerCommand = coretsia_composer_audit_gate_resolve_composer_command($argv, $frameworkRoot);

        /** @var list<string> $diagnostics */
        $diagnostics = [];

        foreach (coretsia_composer_audit_gate_install_roots($repoRoot) as $installRoot) {
            $result = coretsia_composer_audit_gate_run_composer_audit($composerCommand, $installRoot['path']);
            $payload = coretsia_composer_audit_gate_parse_audit_json($result);

            $rootDiagnostics = coretsia_composer_audit_gate_collect_diagnostics($payload, $installRoot['label']);

            if ($rootDiagnostics === [] && $result['exitKnown'] && $result['exit'] !== 0) {
                throw new \RuntimeException('composer-audit-process-failed');
            }

            foreach ($rootDiagnostics as $diagnostic) {
                $diagnostics[] = $diagnostic;
            }
        }

        $diagnostics = \array_values(\array_unique($diagnostics));
        \usort($diagnostics, static fn (string $a, string $b): int => \strcmp($a, $b));

        if ($diagnostics === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeAuditFailed, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @param list<string> $argv
 */
function coretsia_composer_audit_gate_resolve_repo_root(array $argv, string $defaultRepoRoot): string
{
    $repoRoot = $defaultRepoRoot;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '') {
            continue;
        }

        if ($arg === '--') {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $repoRoot = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '--composer=')) {
            continue;
        }

        if (\str_starts_with($arg, '-')) {
            continue;
        }

        $repoRoot = $arg;
    }

    return coretsia_composer_audit_gate_resolve_existing_dir($repoRoot, $defaultRepoRoot);
}

function coretsia_composer_audit_gate_resolve_existing_dir(string $path, string $defaultRepoRoot): string
{
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        throw new \RuntimeException('repo-root-invalid');
    }

    /** @var list<string> $candidates */
    $candidates = [];

    if (coretsia_composer_audit_gate_is_absolute_path($path)) {
        $candidates[] = $path;
    } else {
        $cwd = \getcwd();
        if (\is_string($cwd)) {
            $candidates[] = \rtrim(\str_replace('\\', '/', $cwd), '/') . '/' . \ltrim($path, '/');
        }

        $candidates[] = \rtrim($defaultRepoRoot, '/') . '/' . \ltrim($path, '/');
    }

    foreach (\array_values(\array_unique($candidates)) as $candidate) {
        $real = \realpath($candidate);

        if (\is_string($real) && \is_dir($real) && \is_readable($real)) {
            return \rtrim(\str_replace('\\', '/', $real), '/');
        }
    }

    throw new \RuntimeException('repo-root-invalid');
}

/**
 * @param list<string> $argv
 * @return non-empty-list<string>
 */
function coretsia_composer_audit_gate_resolve_composer_command(array $argv, string $frameworkRoot): array
{
    $composer = 'composer';

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg)) {
            continue;
        }

        if (\str_starts_with($arg, '--composer=')) {
            $composer = \substr($arg, \strlen('--composer='));
            break;
        }
    }

    $composer = \str_replace('\\', '/', \trim($composer));

    if ($composer === '') {
        throw new \RuntimeException('composer-command-invalid');
    }

    if ($composer === 'composer') {
        return ['composer'];
    }

    if (!coretsia_composer_audit_gate_is_absolute_path($composer)) {
        $composer = \rtrim($frameworkRoot, '/') . '/' . \ltrim($composer, '/');
    }

    $real = \realpath($composer);

    if (!\is_string($real) || !\is_file($real) || !\is_readable($real)) {
        throw new \RuntimeException('composer-command-invalid');
    }

    $real = \str_replace('\\', '/', $real);

    if (\str_ends_with(\strtolower($real), '.php')) {
        return [\PHP_BINARY, $real];
    }

    return [$real];
}

/**
 * @return list<array{label:string,path:string}>
 */
function coretsia_composer_audit_gate_install_roots(string $repoRoot): array
{
    $roots = [
        ['label' => 'root', 'path' => $repoRoot],
        ['label' => 'framework', 'path' => $repoRoot . '/framework'],
        ['label' => 'skeleton', 'path' => $repoRoot . '/skeleton'],
    ];

    /** @var list<array{label:string,path:string}> $auditRoots */
    $auditRoots = [];

    foreach ($roots as $root) {
        $composerJson = $root['path'] . '/composer.json';
        $composerLock = $root['path'] . '/composer.lock';

        if (
            !\is_dir($root['path'])
            || !\is_file($composerJson)
            || !\is_readable($composerJson)
            || !\is_file($composerLock)
            || !\is_readable($composerLock)
        ) {
            throw new \RuntimeException('install-root-invalid');
        }

        if (!coretsia_composer_audit_gate_lock_has_packages($composerLock)) {
            continue;
        }

        $auditRoots[] = $root;
    }

    return $auditRoots;
}

function coretsia_composer_audit_gate_lock_has_packages(string $composerLock): bool
{
    $contents = \file_get_contents($composerLock);
    if (!\is_string($contents)) {
        throw new \RuntimeException('composer-lock-read-failed');
    }

    $decoded = \json_decode($contents, true);
    if (\json_last_error() !== \JSON_ERROR_NONE || !\is_array($decoded) || \array_is_list($decoded)) {
        throw new \RuntimeException('composer-lock-json-invalid');
    }

    $packages = $decoded['packages'] ?? null;
    $packagesDev = $decoded['packages-dev'] ?? null;

    if (!\is_array($packages) || !\is_array($packagesDev)) {
        throw new \RuntimeException('composer-lock-packages-invalid');
    }

    return $packages !== [] || $packagesDev !== [];
}

/**
 * @param non-empty-list<string> $composerCommand
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
                    $ready = \stream_select($read, $write, $except, 0, 10_000);
                } finally {
                    \restore_error_handler();
                }

                if ($ready === false) {
                    throw new \RuntimeException('composer-stream-select-failed');
                }

                foreach ($read as $stream) {
                    if ($stream === $pipes[1]) {
                        $stdout .= (string)\stream_get_contents($pipes[1]);
                        continue;
                    }

                    if ($stream === $pipes[2]) {
                        $stderr .= (string)\stream_get_contents($pipes[2]);
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

        $stdout .= (string)\stream_get_contents($pipes[1]);
        $stderr .= (string)\stream_get_contents($pipes[2]);

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
    $exe = \strtolower((string)($cmd[0] ?? ''));
    if ($exe === '') {
        return '';
    }

    $exe = \str_replace('\\', '/', $exe);

    return \strtolower(\basename($exe));
}

/**
 * @param array{exit:int,exitKnown:bool,stdout:string,stderr:string} $result
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
 * @return list<string>
 */
function coretsia_composer_audit_gate_collect_diagnostics(array $payload, string $rootLabel): array
{
    if (!\in_array($rootLabel, ['root', 'framework', 'skeleton'], true)) {
        throw new \RuntimeException('root-label-invalid');
    }

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

            $diagnostics[] = $rootLabel . ':' . $package . ':' . $advisoryId;
        }

        return coretsia_composer_audit_gate_sorted_unique($diagnostics);
    }

    foreach ($advisories as $package => $entries) {
        if (!\is_string($package) || !coretsia_composer_audit_gate_is_valid_package_name($package)) {
            throw new \RuntimeException('composer-audit-package-invalid');
        }

        foreach (coretsia_composer_audit_gate_advisory_entries($entries) as $advisory) {
            $advisoryId = coretsia_composer_audit_gate_advisory_id($advisory);

            $diagnostics[] = $rootLabel . ':' . $package . ':' . $advisoryId;
        }
    }

    return coretsia_composer_audit_gate_sorted_unique($diagnostics);
}

/**
 * @param mixed $entries
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
 * @return list<string>
 */
function coretsia_composer_audit_gate_sorted_unique(array $values): array
{
    $values = \array_values(\array_unique($values));
    \usort($values, static fn (string $a, string $b): int => \strcmp($a, $b));

    return $values;
}

function coretsia_composer_audit_gate_is_absolute_path(string $path): bool
{
    $path = \trim($path);

    if ($path === '') {
        return false;
    }

    if (\str_starts_with($path, '/') || \str_starts_with($path, '\\')) {
        return true;
    }

    return \preg_match('/\A[A-Za-z]:[\/\\\\]/', $path) === 1;
}

function coretsia_composer_audit_gate_error_code_or_fallback(
    string $errorCodesFqcn,
    string $constantName,
    string $fallback,
): string {
    $name = $errorCodesFqcn . '::' . $constantName;

    if (\defined($name)) {
        /** @var string $code */
        $code = \constant($name);

        return $code;
    }

    return $fallback;
}
