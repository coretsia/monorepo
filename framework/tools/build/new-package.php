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

final class NewPackage
{
    public const string CODE_FAILED = 'CORETSIA_NEW_PACKAGE_FAILED';

    public static function main(array $argv): int
    {
        $repoRoot = self::resolveRepoRoot($argv);
        $opts = self::parseArgs($argv);

        $layer = self::need($opts, 'layer');
        $slug = self::need($opts, 'slug');
        $kind = self::need($opts, 'kind');

        $allowedLayers = ['core', 'platform', 'integrations', 'devtools', 'enterprise', 'presets'];
        if (!\in_array($layer, $allowedLayers, true)) {
            throw new \RuntimeException('Invalid --layer (allowed: core|platform|integrations|devtools|enterprise|presets)');
        }

        if (\preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug) !== 1) {
            throw new \RuntimeException('slug must be kebab-case (a-z0-9 and "-")');
        }

        if (!\in_array($kind, ['library', 'runtime'], true)) {
            throw new \RuntimeException('kind must be "library" or "runtime"');
        }

        if (\in_array($slug, ['app', 'modules', 'shared'], true)) {
            throw new \RuntimeException('Forbidden slug: ' . $slug);
        }

        if ($layer === 'core' && \in_array($slug, ['core', 'platform', 'integrations', 'enterprise', 'devtools', 'presets'], true)) {
            throw new \RuntimeException('Reserved core namespace collision slug: ' . $slug);
        }

        if ($slug === 'kernel' && $layer !== 'core') {
            throw new \RuntimeException('Reserved slug: ' . $slug);
        }

        if ($slug === 'observability') {
            throw new \RuntimeException('Reserved slug: ' . $slug);
        }

        $packageDir = $repoRoot . '/framework/packages/' . $layer . '/' . $slug;
        if (\is_dir($packageDir)) {
            throw new \RuntimeException('Package directory already exists: ' . self::rel($repoRoot, $packageDir));
        }

        $composerName = 'coretsia/' . $layer . '-' . $slug;
        $namespaceRoot = self::packageRootNamespace($layer, $slug);

        self::mkdir($packageDir);
        self::mkdir($packageDir . '/src');

        self::writeTextLf(
            $packageDir . '/composer.json',
            self::composerJson($composerName, $namespaceRoot, $layer, $slug, $kind),
        );

        self::runPackageScaffoldSync($repoRoot, $packageDir);

        \fwrite(STDOUT, "OK\n");
        \fwrite(STDOUT, "framework/packages/$layer/$slug\n");

        return 0;
    }

    /**
     * Parse:
     * - --k=v
     * - --k v
     *
     * @return array<string,string>
     */
    private static function parseArgs(array $argv): array
    {
        $out = [];

        $n = \count($argv);
        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];

            if (!\str_starts_with($a, '--')) {
                continue;
            }

            $eq = \strpos($a, '=');
            if ($eq !== false) {
                $k = \substr($a, 2, $eq - 2);
                $v = \substr($a, $eq + 1);

                if ($k !== '' && $v !== '') {
                    $out[$k] = $v;
                }

                continue;
            }

            $k = \substr($a, 2);
            if ($k === '' || ($i + 1) >= $n) {
                continue;
            }

            $v = \trim((string)$argv[$i + 1]);
            if ($v === '' || \str_starts_with($v, '--')) {
                continue;
            }

            $out[$k] = $v;
            $i++;
        }

        return $out;
    }

    /**
     * @param array<string,string> $opts
     */
    private static function need(array $opts, string $key): string
    {
        $v = $opts[$key] ?? null;

        if (!\is_string($v) || \trim($v) === '') {
            throw new \RuntimeException('Missing required arg: --' . $key);
        }

        return $v;
    }

    private static function packageRootNamespace(string $layer, string $slug): string
    {
        $studlySlug = self::studly($slug);

        if ($layer === 'core') {
            return 'Coretsia\\' . $studlySlug . '\\';
        }

        return 'Coretsia\\' . self::studly($layer) . '\\' . $studlySlug . '\\';
    }

    private static function composerJson(
        string $name,
        string $namespaceRoot,
        string $layer,
        string $slug,
        string $kind,
    ): string {
        $studlySlug = self::studly($slug);

        $coretsiaExtra = [
            'kind' => $kind,
        ];

        if ($kind === 'runtime') {
            $coretsiaExtra['moduleId'] = $layer . '.' . $slug;
            $coretsiaExtra['moduleClass'] = $namespaceRoot . 'Module\\' . $studlySlug . 'Module';
            $coretsiaExtra['providers'] = [
                $namespaceRoot . 'Provider\\' . $studlySlug . 'ServiceProvider',
            ];
            $coretsiaExtra['defaultsConfigPath'] = 'config/' . $slug . '.php';
        }

        $json = [
            'name' => $name,
            'type' => 'library',
            'description' => 'Coretsia package: ' . $name,
            'license' => 'Apache-2.0',
            'require' => [
                'php' => '^8.4',
            ],
            'autoload' => [
                'psr-4' => [
                    $namespaceRoot => 'src/',
                ],
            ],
            'autoload-dev' => [
                'psr-4' => [
                    $namespaceRoot . 'Tests\\' => 'tests/',
                ],
            ],
            'config' => [
                'sort-packages' => true,
            ],
            'extra' => [
                'coretsia' => $coretsiaExtra,
            ],
        ];

        return self::encodeComposerJsonCanonical($json);
    }

    private static function runPackageScaffoldSync(string $repoRoot, string $packageDir): void
    {
        $frameworkRoot = $repoRoot . '/framework';
        $syncTool = $frameworkRoot . '/tools/build/sync_package_scaffold.php';

        if (!\is_file($syncTool) || !\is_readable($syncTool)) {
            throw new \RuntimeException('sync_package_scaffold.php missing');
        }

        /** @var array<int, resource> $pipes */
        $pipes = [];

        $process = \proc_open(
            [PHP_BINARY, $syncTool, $packageDir],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $frameworkRoot,
        );

        if (!\is_resource($process)) {
            throw new \RuntimeException('package-scaffold-sync-start-failed');
        }

        if (isset($pipes[0]) && \is_resource($pipes[0])) {
            \fclose($pipes[0]);
        }

        $stdout = '';
        if (isset($pipes[1]) && \is_resource($pipes[1])) {
            $stdout = (string)\stream_get_contents($pipes[1]);
            \fclose($pipes[1]);
        }

        $stderr = '';
        if (isset($pipes[2]) && \is_resource($pipes[2])) {
            $stderr = (string)\stream_get_contents($pipes[2]);
            \fclose($pipes[2]);
        }

        $exitCode = \proc_close($process);

        if ($exitCode !== 0) {
            $summary = self::firstNonEmptyLine($stdout) ?? self::firstNonEmptyLine($stderr);

            if ($summary !== null) {
                throw new \RuntimeException('package-scaffold-sync-failed: ' . $summary);
            }

            throw new \RuntimeException('package-scaffold-sync-failed: exit-code=' . $exitCode);
        }
    }

    private static function firstNonEmptyLine(string $value): ?string
    {
        $value = self::normalizeEol($value);
        $lines = \explode("\n", $value);

        foreach ($lines as $line) {
            $line = \trim($line);

            if ($line !== '') {
                return $line;
            }
        }

        return null;
    }

    private static function encodeComposerJsonCanonical(array $data): string
    {
        $pretty4 = self::encodeJsonValue($data, 0, 4);
        $pretty4 = self::normalizeEol($pretty4);

        $two = self::reindentLeadingSpaces($pretty4, 4, 2);

        return self::normalizeToLfFinalNewline($two);
    }

    private static function encodeJsonValue(mixed $value, int $level, int $indentSize): string
    {
        if ($value === null) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_int($value)) {
            return (string)$value;
        }

        if (\is_float($value)) {
            throw new \RuntimeException('JSON float forbidden in tooling encoder');
        }

        if (\is_string($value)) {
            return '"' . self::escapeJsonString($value) . '"';
        }

        if (!\is_array($value)) {
            throw new \RuntimeException('Unsupported JSON type: ' . \gettype($value));
        }

        $indent = \str_repeat(' ', $level * $indentSize);
        $childIndent = \str_repeat(' ', ($level + 1) * $indentSize);

        if (\array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];
            foreach ($value as $v) {
                $parts[] = $childIndent . self::encodeJsonValue($v, $level + 1, $indentSize);
            }

            return "[\n" . \implode(",\n", $parts) . "\n" . $indent . ']';
        }

        $parts = [];
        foreach ($value as $k => $v) {
            if (!\is_string($k)) {
                throw new \RuntimeException('JSON object keys must be strings');
            }

            $parts[] = $childIndent
                . '"'
                . self::escapeJsonString($k)
                . '": '
                . self::encodeJsonValue($v, $level + 1, $indentSize);
        }

        return "{\n" . \implode(",\n", $parts) . "\n" . $indent . '}';
    }

    private static function escapeJsonString(string $s): string
    {
        $out = '';
        $len = \strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            $o = \ord($c);

            if ($c === '"') {
                $out .= '\\"';
                continue;
            }

            if ($c === '\\') {
                $out .= '\\\\';
                continue;
            }

            if ($o === 8) {
                $out .= '\\b';
                continue;
            }

            if ($o === 12) {
                $out .= '\\f';
                continue;
            }

            if ($o === 10) {
                $out .= '\\n';
                continue;
            }

            if ($o === 13) {
                $out .= '\\r';
                continue;
            }

            if ($o === 9) {
                $out .= '\\t';
                continue;
            }

            if ($o < 0x20) {
                $out .= \sprintf('\\u%04x', $o);
                continue;
            }

            $out .= $c;
        }

        $out = \str_replace("\u{2028}", '\\u2028', $out);
        $out = \str_replace("\u{2029}", '\\u2029', $out);

        return $out;
    }

    private static function reindentLeadingSpaces(string $json, int $from, int $to): string
    {
        $out = \preg_replace_callback(
            '~^( +)~m',
            static function (array $m) use ($from, $to): string {
                $n = \strlen($m[1]);

                if ($n % $from !== 0) {
                    return $m[1];
                }

                $levels = \intdiv($n, $from);

                return \str_repeat(' ', $levels * $to);
            },
            $json,
        );

        if (!\is_string($out)) {
            throw new \RuntimeException('indent rewrite failed');
        }

        return $out;
    }

    private static function normalizeEol(string $s): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function normalizeToLfFinalNewline(string $s): string
    {
        $s = self::normalizeEol($s);

        if (!\str_ends_with($s, "\n")) {
            $s .= "\n";
        }

        return $s;
    }

    private static function writeTextLf(string $path, string $content): void
    {
        $content = self::normalizeToLfFinalNewline($content);

        $result = \file_put_contents($path, $content, LOCK_EX);

        if ($result === false) {
            throw new \RuntimeException('Cannot write file: ' . $path);
        }
    }

    private static function mkdir(string $path): void
    {
        if (\is_dir($path)) {
            return;
        }

        if (!@\mkdir($path, 0777, true) && !\is_dir($path)) {
            throw new \RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private static function studly(string $kebab): string
    {
        $parts = \preg_split('/-+/', $kebab) ?: [$kebab];
        $out = '';

        foreach ($parts as $p) {
            if ($p === '') {
                continue;
            }

            $out .= \strtoupper($p[0]) . \strtolower(\substr($p, 1));
        }

        return $out;
    }

    private static function argRepoRoot(array $argv): ?string
    {
        $n = \count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];

            if (\str_starts_with($a, '--repo-root=')) {
                $v = \trim(\substr($a, \strlen('--repo-root=')));

                return $v !== '' ? $v : null;
            }

            if ($a === '--repo-root') {
                $next = ($i + 1 < $n) ? \trim((string)$argv[$i + 1]) : '';

                return $next !== '' ? $next : null;
            }
        }

        return null;
    }

    private static function resolveRepoRoot(array $argv): string
    {
        $arg = self::argRepoRoot($argv);

        if ($arg === null) {
            return self::repoRootUnsafe();
        }

        $candidate = \str_replace('\\', '/', \trim($arg));

        if (!self::isAbsolutePath($candidate)) {
            $cwd = \getcwd();
            if ($cwd === false) {
                throw new \RuntimeException('Cannot resolve cwd');
            }

            $cwd = \rtrim(\str_replace('\\', '/', $cwd), '/');
            $candidate = $cwd . '/' . \ltrim($candidate, '/');
        }

        $candidate = \rtrim($candidate, '/');

        $real = \realpath($candidate);
        if ($real !== false) {
            $candidate = \rtrim(\str_replace('\\', '/', $real), '/');
        }

        if (
            !\is_dir($candidate . '/framework')
            || !\is_dir($candidate . '/skeleton')
            || !\is_file($candidate . '/composer.json')
        ) {
            throw new \RuntimeException('Invalid --repo-root: missing framework/skeleton/composer.json markers');
        }

        return $candidate;
    }

    private static function isAbsolutePath(string $path): bool
    {
        $path = \trim($path);

        if ($path === '') {
            return false;
        }

        if (\str_starts_with($path, '/') || \str_starts_with($path, '\\')) {
            return true;
        }

        return \preg_match('~^[A-Za-z]:[\\\\/]~', $path) === 1;
    }

    private static function rel(string $repoRoot, string $abs): string
    {
        $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/') . '/';
        $abs = \str_replace('\\', '/', $abs);

        return \str_starts_with($abs, $repoRoot) ? \substr($abs, \strlen($repoRoot)) : $abs;
    }

    private static function repoRootUnsafe(): string
    {
        $dir = \getcwd();
        if ($dir === false) {
            throw new \RuntimeException('Cannot resolve cwd');
        }

        $dir = \rtrim(\str_replace('\\', '/', $dir), '/');

        for ($i = 0; $i < 30; $i++) {
            if (\is_dir($dir . '/framework') && \is_dir($dir . '/skeleton') && \is_file($dir . '/composer.json')) {
                return $dir;
            }

            $parent = \dirname($dir);
            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        throw new \RuntimeException('Repo root not found');
    }
}

try {
    exit(NewPackage::main($argv));
} catch (Throwable $e) {
    $msg = \str_replace(["\r\n", "\r"], "\n", $e->getMessage());
    \fwrite(STDERR, NewPackage::CODE_FAILED . ": {$msg}\n");
    exit(1);
}
