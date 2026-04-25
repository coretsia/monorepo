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

        $layer = self::need($opts, 'layer'); // core|platform|integrations|devtools|enterprise|presets
        $slug = self::need($opts, 'slug');   // kebab-case
        $kind = self::need($opts, 'kind');   // library|runtime

        $allowedLayers = ['core', 'platform', 'integrations', 'devtools', 'enterprise', 'presets'];
        if (!in_array($layer, $allowedLayers, true)) {
            throw new RuntimeException('Invalid --layer (allowed: core|platform|integrations|devtools|enterprise|presets)');
        }

        if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
            throw new RuntimeException('slug must be kebab-case (a-z0-9 and "-")');
        }

        if (!in_array($kind, ['library', 'runtime'], true)) {
            throw new RuntimeException('kind must be "library" or "runtime"');
        }

        // Minimal safety (future package-compliance gates may extend this).
        $forbiddenSlugs = ['app', 'apps', 'modules', 'shared'];
        if (in_array($slug, $forbiddenSlugs, true)) {
            throw new RuntimeException('Forbidden slug: ' . $slug);
        }

        $packageDir = $repoRoot . '/framework/packages/' . $layer . '/' . $slug;
        if (is_dir($packageDir)) {
            throw new RuntimeException('Package directory already exists: ' . self::rel($repoRoot, $packageDir));
        }

        $composerName = 'coretsia/' . $layer . '-' . $slug;

        $ns = self::packageRootNamespace($layer, $slug); // ends with "\"
        $studlySlug = self::studly($slug);
        $moduleId = $layer . '.' . $slug; // derived only from {layer, slug}

        self::mkdir($packageDir . '/src');
        self::mkdir($packageDir . '/tests/Contract');

        self::writeTextLf($packageDir . '/README.md', self::readme($composerName));
        self::writeTextLf(
            $packageDir . '/composer.json',
            self::composerJson($composerName, $ns, $kind, $moduleId, $studlySlug)
        );

        if ($kind === 'runtime') {
            self::mkdir($packageDir . '/src/Module');
            self::mkdir($packageDir . '/src/Provider');
            self::mkdir($packageDir . '/config');

            $moduleClass = rtrim($ns, '\\') . '\\Module\\' . $studlySlug . 'Module';
            $providerClass = rtrim($ns, '\\') . '\\Provider\\' . $studlySlug . 'ServiceProvider';

            self::writeTextLf(
                $packageDir . '/src/Module/' . $studlySlug . 'Module.php',
                self::runtimeModulePhp($moduleClass)
            );
            self::writeTextLf(
                $packageDir . '/src/Provider/' . $studlySlug . 'ServiceProvider.php',
                self::runtimeProviderPhp($providerClass)
            );

            // Config defaults file name is kebab-case (canonical): config/<slug>.php
            self::writeTextLf(
                $packageDir . '/config/' . $slug . '.php',
                self::defaultsConfigPhp()
            );

            self::writeTextLf(
                $packageDir . '/config/rules.php',
                self::rulesConfigPhp()
            );
        }

        self::writeTextLf(
            $packageDir . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
            self::noopContractTestPhp($ns)
        );

        fwrite(STDOUT, "OK\n");
        fwrite(STDOUT, "framework/packages/$layer/$slug\n");
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

        $n = count($argv);
        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];
            if (!str_starts_with($a, '--')) {
                continue;
            }

            $eq = strpos($a, '=');
            if ($eq !== false) {
                $k = substr($a, 2, $eq - 2);
                $v = substr($a, $eq + 1);
                if ($k !== '' && $v !== '') {
                    $out[$k] = $v;
                }
                continue;
            }

            $k = substr($a, 2);
            if ($k === '' || ($i + 1) >= $n) {
                continue;
            }

            $v = trim((string)$argv[$i + 1]);
            if ($v === '' || str_starts_with($v, '--')) {
                continue;
            }

            $out[$k] = $v;
            $i++;
        }

        return $out;
    }

    private static function need(array $opts, string $key): string
    {
        $v = $opts[$key] ?? null;
        if (!is_string($v) || trim($v) === '') {
            throw new RuntimeException('Missing required arg: --' . $key);
        }
        return $v;
    }

    private static function packageRootNamespace(string $layer, string $slug): string
    {
        $studlySlug = self::studly($slug);

        // Core exception:
        if ($layer === 'core') {
            return 'Coretsia\\' . $studlySlug . '\\';
        }

        $studlyLayer = self::studly($layer);
        return 'Coretsia\\' . $studlyLayer . '\\' . $studlySlug . '\\';
    }

    private static function composerJson(string $name, string $psr4, string $kind, string $moduleId, string $studlySlug): string
    {
        $extra = [
            'coretsia' => [
                'kind' => $kind,
            ],
        ];

        if ($kind === 'runtime') {
            $extra['coretsia']['moduleId'] = $moduleId;
            $extra['coretsia']['moduleClass'] = rtrim($psr4, '\\') . '\\Module\\' . $studlySlug . 'Module';
            $extra['coretsia']['providers'] = [
                rtrim($psr4, '\\') . '\\Provider\\' . $studlySlug . 'ServiceProvider',
            ];
            // Canonical defaults config path is config/<slug>.php (kebab):
            $extra['coretsia']['defaultsConfigPath'] = 'config/' . self::kebabFromStudly($studlySlug) . '.php';
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
                    $psr4 => 'src/',
                ],
            ],
            'config' => [
                'sort-packages' => true,
            ],
            'extra' => $extra,
        ];

        return self::encodeComposerJsonCanonical($json);
    }

    private static function readme(string $name): string
    {
        return "# $name\n\n"
            . "## Observability\n\n"
            . "- Noop-safe ports only; no secrets/PII.\n\n"
            . "## Errors\n\n"
            . "- Deterministic codes; no payload leakage.\n\n"
            . "## Security / Redaction\n\n"
            . "- Never log Authorization/Cookie/session id/tokens/raw payloads.\n";
    }

    private static function runtimeModulePhp(string $fqcn): string
    {
        [$ns, $cls] = self::splitFqcn($fqcn);

        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace $ns;\n\n"
            . "final class $cls\n"
            . "{\n"
            . "}\n";
    }

    private static function runtimeProviderPhp(string $fqcn): string
    {
        [$ns, $cls] = self::splitFqcn($fqcn);

        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace $ns;\n\n"
            . "final class $cls\n"
            . "{\n"
            . "}\n";
    }

    private static function defaultsConfigPhp(): string
    {
        // Subtree only (no wrapper root).
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'enabled' => true,\n"
            . "];\n";
    }

    private static function rulesConfigPhp(): string
    {
        // Stub rules file (shape TBD by owner epic).
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    // rules go here\n"
            . "];\n";
    }

    private static function noopContractTestPhp(string $ns): string
    {
        return "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace " . rtrim($ns, '\\') . "\\Tests\\Contract;\n\n"
            . "use PHPUnit\\Framework\\TestCase;\n\n"
            . "final class CrossCuttingNoopDoesNotThrowTest extends TestCase\n"
            . "{\n"
            . "    public function testNoopSafeSurface(): void\n"
            . "    {\n"
            . "        self::assertTrue(true);\n"
            . "    }\n"
            . "}\n";
    }

    /**.
     */
    private static function encodeComposerJsonCanonical(array $data): string
    {
        $pretty4 = self::encodeJsonValue($data, 0, 4);
        $pretty4 = self::normalizeEol($pretty4);

        $two = self::reindentLeadingSpaces($pretty4, 4, 2);
        $two = self::normalizeToLfFinalNewline($two);

        return $two;
    }

    private static function encodeJsonValue(mixed $value, int $level, int $indentSize): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return (string)$value;
        }
        if (is_float($value)) {
            throw new RuntimeException('JSON float forbidden in tooling encoder');
        }
        if (is_string($value)) {
            return '"' . self::escapeJsonString($value) . '"';
        }
        if (!is_array($value)) {
            throw new RuntimeException('Unsupported JSON type: ' . gettype($value));
        }

        $indent = str_repeat(' ', $level * $indentSize);
        $childIndent = str_repeat(' ', ($level + 1) * $indentSize);

        if (array_is_list($value)) {
            if ($value === []) {
                return '[]';
            }

            $parts = [];
            foreach ($value as $v) {
                $parts[] = $childIndent . self::encodeJsonValue($v, $level + 1, $indentSize);
            }

            return "[\n" . implode(",\n", $parts) . "\n" . $indent . "]";
        }

        $parts = [];
        foreach ($value as $k => $v) {
            if (!is_string($k)) {
                throw new RuntimeException('JSON object keys must be strings');
            }

            $parts[] = $childIndent
                . '"'
                . self::escapeJsonString($k)
                . '": '
                . self::encodeJsonValue($v, $level + 1, $indentSize);
        }

        return "{\n" . implode(",\n", $parts) . "\n" . $indent . "}";
    }

    private static function escapeJsonString(string $s): string
    {
        $out = '';
        $len = strlen($s);

        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            $o = ord($c);

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
                $out .= sprintf('\\u%04x', $o);
                continue;
            }

            $out .= $c;
        }

        $out = str_replace("\u{2028}", '\\u2028', $out);
        $out = str_replace("\u{2029}", '\\u2029', $out);

        return $out;
    }

    private static function reindentLeadingSpaces(string $json, int $from, int $to): string
    {
        $out = preg_replace_callback('~^( +)~m', static function (array $m) use ($from, $to): string {
            $n = strlen($m[1]);
            if ($n % $from !== 0) {
                return $m[1];
            }
            $levels = intdiv($n, $from);
            return str_repeat(' ', $levels * $to);
        }, $json);

        if (!is_string($out)) {
            throw new RuntimeException('indent rewrite failed');
        }

        return $out;
    }

    private static function normalizeEol(string $s): string
    {
        return str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function normalizeToLfFinalNewline(string $s): string
    {
        $s = self::normalizeEol($s);
        if (!str_ends_with($s, "\n")) {
            $s .= "\n";
        }
        return $s;
    }

    private static function writeTextLf(string $path, string $content): void
    {
        $content = self::normalizeToLfFinalNewline($content);
        file_put_contents($path, $content, LOCK_EX);
    }

    private static function mkdir(string $path): void
    {
        if (!is_dir($path) && !@mkdir($path, 0777, true)) {
            throw new RuntimeException('Cannot create directory: ' . $path);
        }
    }

    private static function studly(string $kebab): string
    {
        $parts = preg_split('/-+/', $kebab) ?: [$kebab];
        $out = '';
        foreach ($parts as $p) {
            $out .= ucfirst($p);
        }
        return $out;
    }

    private static function kebabFromStudly(string $studly): string
    {
        // Minimal Studly->kebab (ASCII only): FooBar -> foo-bar
        $out = preg_replace('~(?<!^)[A-Z]~', '-$0', $studly);
        if (!is_string($out)) {
            $out = $studly;
        }
        return strtolower($out);
    }

    /**
     * @return array{0:string,1:string}
     */
    private static function splitFqcn(string $fqcn): array
    {
        $pos = strrpos($fqcn, '\\');
        if ($pos === false) {
            throw new RuntimeException('Invalid FQCN: ' . $fqcn);
        }
        return [substr($fqcn, 0, $pos), substr($fqcn, $pos + 1)];
    }

    private static function argRepoRoot(array $argv): ?string
    {
        $n = count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string)$argv[$i];

            if (str_starts_with($a, '--repo-root=')) {
                $v = trim(substr($a, strlen('--repo-root=')));
                return $v !== '' ? $v : null;
            }

            if ($a === '--repo-root') {
                $next = ($i + 1 < $n) ? trim((string)$argv[$i + 1]) : '';
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

        $candidate = str_replace('\\', '/', trim($arg));

        if (!self::isAbsolutePath($candidate)) {
            $cwd = getcwd();
            if ($cwd === false) {
                throw new RuntimeException('Cannot resolve cwd');
            }
            $cwd = rtrim(str_replace('\\', '/', $cwd), '/');
            $candidate = $cwd . '/' . ltrim($candidate, '/');
        }

        $candidate = rtrim($candidate, '/');

        $real = realpath($candidate);
        if ($real !== false) {
            $candidate = rtrim(str_replace('\\', '/', $real), '/');
        }

        if (!is_dir($candidate . '/framework') || !is_dir($candidate . '/skeleton') || !is_file($candidate . '/composer.json')) {
            throw new RuntimeException('Invalid --repo-root: missing framework/skeleton/composer.json markers');
        }

        return $candidate;
    }

    private static function isAbsolutePath(string $path): bool
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

    private static function rel(string $repoRoot, string $abs): string
    {
        $repoRoot = rtrim(str_replace('\\', '/', $repoRoot), '/') . '/';
        $abs = str_replace('\\', '/', $abs);
        return str_starts_with($abs, $repoRoot) ? substr($abs, strlen($repoRoot)) : $abs;
    }

    private static function repoRootUnsafe(): string
    {
        $dir = getcwd();
        if ($dir === false) {
            throw new RuntimeException('Cannot resolve cwd');
        }

        $dir = rtrim(str_replace('\\', '/', $dir), '/');

        for ($i = 0; $i < 30; $i++) {
            if (is_dir($dir . '/framework') && is_dir($dir . '/skeleton') && is_file($dir . '/composer.json')) {
                return $dir;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        throw new RuntimeException('Repo root not found');
    }
}

try {
    exit(NewPackage::main($argv));
} catch (Throwable $e) {
    $msg = str_replace(["\r\n", "\r"], "\n", $e->getMessage());
    fwrite(STDERR, NewPackage::CODE_FAILED . ": {$msg}\n");
    exit(1);
}
