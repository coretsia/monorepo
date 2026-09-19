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

use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';

final class PackageIndexTool
{
    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);

        $check = self::argFlag($argv, '--check');
        $apply = self::argFlag($argv, '--apply') || !$check; // default apply unless --check

        $outPath = $repository->resolve(
            self::argValue($argv, '--out') ?? 'tools/testing/package-index.php',
        );

        $catalog = WorkspacePackageCatalog::discover($repository);
        $packages = self::buildIndex($catalog);

        $payload = [
            'schemaVersion' => 1,
            'generatedBy' => 'tools/build/package_index.php',
            'packages' => $packages,
        ];

        $php = self::renderPhpReturnFile($payload);

        $changed = self::isDifferentFile($outPath, $php);
        $relativePath = $repository->relativeToRepo($outPath);

        if ($check) {
            if ($changed) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_PACKAGE_INDEX_OUT_OF_DATE,
                    [$relativePath],
                );
                return 1;
            }

            return 0;
        }

        if ($apply && $changed) {
            DeterministicFile::writeTextLf($outPath, $php);
        }

        ConsoleOutput::line('OK', false);

        if ($changed) {
            ConsoleOutput::line($relativePath, false);
        }

        return 0;
    }

    /**
     * Runtime/tooling package index contains layered packages only.
     * Special distributions are intentionally excluded.
     *
     * @return list<array<string,mixed>>
     */
    private static function buildIndex(WorkspacePackageCatalog $catalog): array
    {
        $items = [];

        foreach ($catalog->layeredPackages() as $product) {
            $data = ComposerJson::readObject($product['composerJsonPath']);

            $psr4 = self::extractPsr4(
                $data,
                is_dir($product['absolutePath'] . '/src'),
                $product['packageId'],
            );
            $type = (string) ($data['type'] ?? 'library');

            $extra = $data['extra'] ?? [];
            $coretsia = (is_array($extra) && isset($extra['coretsia']) && is_array($extra['coretsia']))
                ? $extra['coretsia']
                : [];

            $kind = (string) ($coretsia['kind'] ?? $type);
            $moduleId = $coretsia['moduleId'] ?? null;
            $moduleClass = $coretsia['moduleClass'] ?? null;
            $providers = $coretsia['providers'] ?? [];
            $defaultsConfigPath = $coretsia['defaultsConfigPath'] ?? null;

            if (!is_string($moduleId) || trim($moduleId) === '') {
                $moduleId = null;
            }

            if (!is_string($moduleClass) || trim($moduleClass) === '') {
                $moduleClass = null;
            }

            if (!is_string($defaultsConfigPath) || trim($defaultsConfigPath) === '') {
                $defaultsConfigPath = null;
            }

            if (!is_array($providers)) {
                $providers = [];
            }

            $providers = array_values(array_map('strval', $providers));
            sort($providers, SORT_STRING);

            $items[] = [
                'id' => $product['layer'] . '.' . $product['slug'],
                'layer' => $product['layer'],
                'slug' => $product['slug'],
                'path' => $product['relativePath'],
                'composerName' => $product['composerName'],
                'psr4' => $psr4,
                'kind' => $kind,
                'moduleId' => $moduleId,
                'moduleClass' => $moduleClass,
                'providers' => $providers,
                'defaultsConfigPath' => $defaultsConfigPath,
            ];
        }

        usort(
            $items,
            static fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']),
        );

        return $items;
    }

    private static function licenseHeaderPhp(): string
    {
        return "/*\n"
            . " * Coretsia Framework (Monorepo)\n"
            . " *\n"
            . " * Project: Coretsia Framework (Monorepo)\n"
            . " * Authors: Vladyslav Mudrichenko and contributors\n"
            . " * Copyright (c) 2026 Vladyslav Mudrichenko\n"
            . " *\n"
            . " * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko\n"
            . " * SPDX-License-Identifier: Apache-2.0\n"
            . " *\n"
            . " * For contributors list, see git history.\n"
            . " * See LICENSE and NOTICE in the project root for full license information.\n"
            . " */\n\n";
    }

    private static function renderPhpReturnFile(array $payload): string
    {
        $payload = self::normalizePayload($payload);

        $export = self::renderPhpValue($payload, 0);
        $export = self::normalizeEol($export);

        $out = "<?php\n\n";
        $out .= "declare(strict_types=1);\n\n";
        $out .= self::licenseHeaderPhp();
        $out .= "/*\n";
        $out .= " * GENERATED FILE (tooling-only).\n";
        $out .= " * MUST NOT be used by runtime.\n";
        $out .= " * Regenerate: composer arch:package-index:generate\n";
        $out .= " */\n\n";
        $out .= "return " . $export . ";\n";

        return $out;
    }

    private static function renderPhpValue(mixed $value, int $indent): string
    {
        if (is_array($value)) {
            return self::renderPhpArray($value, $indent);
        }

        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value) || is_string($value)) {
            return var_export($value, true);
        }

        throw new RuntimeException('Unsupported package-index payload value');
    }

    /**
     * @param array<int|string,mixed> $value
     */
    private static function renderPhpArray(array $value, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        $childPad = str_repeat(' ', $indent + 2);

        $lines = [];
        $lines[] = $pad . 'array(';

        foreach ($value as $key => $item) {
            $keyOut = self::renderPhpArrayKey($key);

            if (is_array($item)) {
                $lines[] = $childPad . $keyOut . ' =>';

                $nestedLines = explode("\n", self::renderPhpArray($item, $indent + 2));
                $last = count($nestedLines) - 1;

                foreach ($nestedLines as $i => $line) {
                    $lines[] = $line . ($i === $last ? ',' : '');
                }

                continue;
            }

            $lines[] = $childPad . $keyOut . ' => ' . self::renderPhpValue($item, $indent + 2) . ',';
        }

        $lines[] = $pad . ')';

        return implode("\n", $lines);
    }

    private static function renderPhpArrayKey(int|string $key): string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return var_export($key, true);
    }

    private static function normalizePayload(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            $out = [];
            foreach ($value as $v) {
                $out[] = self::normalizePayload($v);
            }
            return $out;
        }

        $keys = array_keys($value);
        usort($keys, static fn ($a, $b) => strcmp((string) $a, (string) $b));

        $out = [];
        foreach ($keys as $k) {
            $out[(string) $k] = self::normalizePayload($value[$k]);
        }
        return $out;
    }

    private static function isDifferentFile(string $path, string $newContent): bool
    {
        if (!is_file($path)) {
            return true;
        }

        return DeterministicFile::readBytesExact($path) !== $newContent;
    }

    private static function extractPsr4(
        array $composer,
        bool $required,
        string $packageId,
    ): string {
        $autoload = $composer['autoload'] ?? null;

        if ($autoload instanceof \stdClass) {
            $autoload = get_object_vars($autoload);
        }

        $psr4 = is_array($autoload) && !array_is_list($autoload)
            ? ($autoload['psr-4'] ?? null)
            : null;

        if ($psr4 instanceof \stdClass) {
            $psr4 = get_object_vars($psr4);
        }

        if (!is_array($psr4) || array_is_list($psr4)) {
            if ($required) {
                throw new RuntimeException(
                    'source-owning layered package must declare exactly one PSR-4 root ' . $packageId,
                );
            }

            return '';
        }

        $keys = array_keys($psr4);

        foreach ($keys as $key) {
            if (!is_string($key) || trim($key) === '') {
                throw new RuntimeException(
                    'layered package declares invalid PSR-4 root ' . $packageId,
                );
            }
        }

        sort($keys, SORT_STRING);

        if ($required && count($keys) !== 1) {
            throw new RuntimeException(
                'source-owning layered package must declare exactly one PSR-4 root ' . $packageId,
            );
        }

        return $keys[0] ?? '';
    }

    private static function normalizeEol(string $s): string
    {
        return str_replace(["\r\n", "\r"], "\n", $s);
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    private static function argValue(array $argv, string $name): ?string
    {
        $n = count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string) $argv[$i];

            if (str_starts_with($a, $name . '=')) {
                $v = trim(substr($a, strlen($name . '=')));

                if ($v === '') {
                    throw new RuntimeException('argument-value-invalid');
                }

                return $v;
            }

            if ($a === $name) {
                $next = ($i + 1 < $n) ? trim((string) $argv[$i + 1]) : '';

                if ($next === '' || str_starts_with($next, '--')) {
                    throw new RuntimeException('argument-value-invalid');
                }

                return $next;
            }
        }

        return null;
    }

    private static function resolveRepository(array $argv): RepositoryContext
    {
        $repoRoot = self::argValue($argv, '--repo-root');

        return $repoRoot === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($repoRoot);
    }
}

try {
    exit(PackageIndexTool::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_PACKAGE_INDEX_FAILED,
    );

    exit(1);
}
