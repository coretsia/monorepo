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
                'CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_PACKAGE_COMPLIANCE_VIOLATION';
    $fallbackGateFailed = 'CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackGateFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_package_compliance_gate_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED',
                    $code,
                );
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

    $codeViolation = coretsia_package_compliance_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_PACKAGE_COMPLIANCE_VIOLATION',
        $fallbackViolation,
    );

    $codeGateFailed = coretsia_package_compliance_gate_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED',
        $fallbackGateFailed,
    );

    try {
        $defaultScanRoot = $toolsRootRuntime . '/..';
        $repoRoot = coretsia_package_compliance_gate_resolve_repo_root($toolsRootRuntime);
        $scanRoot = coretsia_package_compliance_gate_resolve_scan_root($argv, $defaultScanRoot, $repoRoot);
        $allowlistPath = coretsia_package_compliance_gate_resolve_allowlist_path(
            $argv,
            $toolsRootRuntime . '/gates/package_compliance_allowlist.php',
        );

        $allowlist = coretsia_package_compliance_gate_load_allowlist($allowlistPath);

        $diagnostics = coretsia_package_compliance_gate_scan($scanRoot, $repoRoot, $allowlist);
        $diagnostics = \array_values(\array_unique($diagnostics));
        \sort($diagnostics, \SORT_STRING);

        if ($diagnostics === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $diagnostics);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeGateFailed, []);
        }

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @param list<string> $argv
 */
function coretsia_package_compliance_gate_resolve_scan_root(
    array $argv,
    string $defaultScanRoot,
    string $repoRoot,
): string {
    $scanRoot = $defaultScanRoot;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '') {
            continue;
        }

        if ($arg === '--') {
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $scanRoot = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '--allowlist=')) {
            continue;
        }

        if (\str_starts_with($arg, '-')) {
            continue;
        }

        $scanRoot = $arg;
    }

    return coretsia_package_compliance_gate_resolve_existing_dir(
        $scanRoot,
        $defaultScanRoot,
        $repoRoot,
    );
}

function coretsia_package_compliance_gate_resolve_existing_dir(
    string $path,
    string $defaultScanRoot,
    string $repoRoot,
): string {
    $path = \str_replace('\\', '/', \trim($path));

    if ($path === '') {
        $path = $defaultScanRoot;
    }

    /** @var list<string> $candidates */
    $candidates = [];

    if (coretsia_package_compliance_gate_is_absolute_path($path)) {
        $candidates[] = $path;
    } else {
        $cwd = \getcwd();
        if (\is_string($cwd)) {
            $candidates[] = \rtrim(\str_replace('\\', '/', $cwd), '/') . '/' . \ltrim($path, '/');
        }

        $candidates[] = \rtrim($repoRoot, '/') . '/' . \ltrim($path, '/');
        $candidates[] = \rtrim($defaultScanRoot, '/') . '/' . \ltrim($path, '/');
    }

    foreach (\array_values(\array_unique($candidates)) as $candidate) {
        $real = \realpath($candidate);

        if (\is_string($real) && \is_dir($real) && \is_readable($real)) {
            return \rtrim(\str_replace('\\', '/', $real), '/');
        }
    }

    throw new \RuntimeException('scan-root-invalid');
}

function coretsia_package_compliance_gate_is_absolute_path(string $path): bool
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

/**
 * @param list<string> $argv
 */
function coretsia_package_compliance_gate_resolve_allowlist_path(array $argv, string $defaultAllowlistPath): string
{
    $allowlistPath = $defaultAllowlistPath;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg)) {
            continue;
        }

        if (\str_starts_with($arg, '--allowlist=')) {
            $allowlistPath = \substr($arg, \strlen('--allowlist='));
            break;
        }
    }

    $realAllowlistPath = \realpath($allowlistPath);

    if (!\is_string($realAllowlistPath) || !\is_file($realAllowlistPath) || !\is_readable($realAllowlistPath)) {
        throw new \RuntimeException('package-compliance-allowlist-path-invalid');
    }

    return \str_replace('\\', '/', $realAllowlistPath);
}

function coretsia_package_compliance_gate_resolve_repo_root(string $toolsRootRuntime): string
{
    $repoRoot = \realpath($toolsRootRuntime . '/..' . '/..');

    if (!\is_string($repoRoot) || !\is_dir($repoRoot) || !\is_readable($repoRoot)) {
        throw new \RuntimeException('repo-root-invalid');
    }

    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    foreach (['LICENSE', 'NOTICE'] as $file) {
        $path = $repoRoot . '/' . $file;
        if (!\is_file($path) || !\is_readable($path)) {
            throw new \RuntimeException('repo-legal-file-missing');
        }
    }

    return $repoRoot;
}

/**
 * @return array<string,true>
 */
function coretsia_package_compliance_gate_load_allowlist(string $allowlistPath): array
{
    $allowlistPath = \str_replace('\\', '/', $allowlistPath);

    if (!\is_file($allowlistPath) || !\is_readable($allowlistPath)) {
        throw new \RuntimeException('package-compliance-allowlist-missing');
    }

    $value = require $allowlistPath;

    if (!\is_array($value) || !\array_is_list($value)) {
        throw new \RuntimeException('package-compliance-allowlist-invalid');
    }

    /** @var list<string> $items */
    $items = [];

    foreach ($value as $item) {
        if (!\is_string($item)) {
            throw new \RuntimeException('package-compliance-allowlist-entry-invalid');
        }

        if (\preg_match('/\A[a-z][a-z0-9-]*\/[a-z0-9][a-z0-9-]*\z/', $item) !== 1) {
            throw new \RuntimeException('package-compliance-allowlist-entry-invalid');
        }

        $items[] = $item;
    }

    $sorted = $items;
    \usort($sorted, static fn (string $a, string $b): int => \strcmp($a, $b));

    if ($items !== $sorted) {
        throw new \RuntimeException('package-compliance-allowlist-not-sorted');
    }

    if (\count(\array_unique($items)) !== \count($items)) {
        throw new \RuntimeException('package-compliance-allowlist-duplicate');
    }

    /** @var array<string,true> $lookup */
    $lookup = [];
    foreach ($items as $item) {
        $lookup[$item] = true;
    }

    \ksort($lookup, \SORT_STRING);

    return $lookup;
}

/**
 * @param array<string,true> $allowlist
 * @return list<string>
 */
function coretsia_package_compliance_gate_scan(string $scanRoot, string $repoRoot, array $allowlist): array
{
    $packageRoots = coretsia_package_compliance_gate_collect_package_roots($scanRoot);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($packageRoots as $packageRoot) {
        foreach (
            coretsia_package_compliance_gate_validate_package(
                $scanRoot,
                $repoRoot,
                $packageRoot,
                $allowlist
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_collect_package_roots(string $scanRoot): array
{
    $packagesRoot = \rtrim($scanRoot, '/') . '/packages';

    if (!\is_dir($packagesRoot)) {
        return [];
    }

    $layers = \scandir($packagesRoot);
    if ($layers === false) {
        throw new \RuntimeException('packages-root-scan-failed');
    }

    /** @var list<string> $packageRoots */
    $packageRoots = [];

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

            $packageRoots[] = \rtrim(\str_replace('\\', '/', $packageRoot), '/');
        }
    }

    $packageRoots = \array_values(\array_unique($packageRoots));
    \sort($packageRoots, \SORT_STRING);

    return $packageRoots;
}

/**
 * @param array<string,true> $allowlist
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_package(
    string $scanRoot,
    string $repoRoot,
    string $packageRoot,
    array $allowlist,
): array {
    $identity = coretsia_package_compliance_gate_package_identity($scanRoot, $packageRoot);
    $relativeRoot = $identity['relative_root'];
    $layer = $identity['layer'];
    $slug = $identity['slug'];
    $packageId = $identity['package_id'];

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (
        coretsia_package_compliance_gate_validate_package_identity(
            $relativeRoot,
            $layer,
            $slug,
            $packageId
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    if (isset($allowlist[$packageId])) {
        return coretsia_package_compliance_gate_unique_sorted($diagnostics);
    }

    foreach (coretsia_package_compliance_gate_validate_required_scaffold($packageRoot, $relativeRoot) as $diagnostic) {
        $diagnostics[] = $diagnostic;
    }

    foreach (
        coretsia_package_compliance_gate_validate_legal_files(
            $packageRoot,
            $relativeRoot,
            $repoRoot
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    $composer = coretsia_package_compliance_gate_decode_composer_json($packageRoot . '/composer.json');

    if ($composer === null) {
        $diagnostics[] = $relativeRoot . '/composer.json: composer-json-invalid';

        return coretsia_package_compliance_gate_unique_sorted($diagnostics);
    }

    $namespaceRoot = coretsia_package_compliance_gate_namespace_root($layer, $slug);
    $sourcePathRoot = coretsia_package_compliance_gate_source_path_root($layer, $slug);
    $testNamespaceRoot = $namespaceRoot . 'Tests\\';

    foreach (
        coretsia_package_compliance_gate_validate_composer_json(
            $composer,
            $relativeRoot,
            $layer,
            $slug,
            $namespaceRoot,
            $sourcePathRoot,
            $testNamespaceRoot,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    $kind = coretsia_package_compliance_gate_composer_coretsia_kind($composer);

    if ($kind === 'runtime') {
        foreach (
            coretsia_package_compliance_gate_validate_runtime_package(
                $packageRoot,
                $relativeRoot,
                $layer,
                $slug,
                $namespaceRoot,
                $composer,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    if (\is_file($packageRoot . '/README.md')) {
        foreach (
            coretsia_package_compliance_gate_validate_readme(
                $packageRoot . '/README.md',
                $relativeRoot . '/README.md'
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return coretsia_package_compliance_gate_unique_sorted($diagnostics);
}

/**
 * @return array{relative_root:string,layer:string,slug:string,package_id:string}
 */
function coretsia_package_compliance_gate_package_identity(string $scanRoot, string $packageRoot): array
{
    $relativeRoot = coretsia_package_compliance_gate_normalize_relative_path($scanRoot, $packageRoot);
    $parts = \explode('/', $relativeRoot);

    if (\count($parts) !== 3 || $parts[0] !== 'packages') {
        throw new \RuntimeException('package-path-invalid');
    }

    $layer = $parts[1];
    $slug = $parts[2];

    return [
        'relative_root' => $relativeRoot,
        'layer' => $layer,
        'slug' => $slug,
        'package_id' => $layer . '/' . $slug,
    ];
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_package_identity(
    string $relativeRoot,
    string $layer,
    string $slug,
    string $packageId,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    if (!\in_array($layer, ['core', 'platform', 'integrations', 'enterprise', 'devtools', 'presets'], true)) {
        $diagnostics[] = $relativeRoot . ': invalid-package-layer';
    }

    if (\preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug) !== 1) {
        $diagnostics[] = $relativeRoot . ': invalid-package-slug';
    }

    if (\in_array($slug, ['app', 'modules', 'shared'], true)) {
        $diagnostics[] = $relativeRoot . ': forbidden-slug';
    }

    if ($layer === 'core' && \in_array(
        $slug,
        ['core', 'platform', 'integrations', 'enterprise', 'devtools', 'presets'],
        true
    )) {
        $diagnostics[] = $relativeRoot . ': core-namespace-collision-slug';
    }

    if ($slug === 'kernel' && $packageId !== 'core/kernel') {
        $diagnostics[] = $relativeRoot . ': reserved-slug';
    }

    if ($slug === 'observability') {
        $diagnostics[] = $relativeRoot . ': reserved-slug';
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_required_scaffold(string $packageRoot, string $relativeRoot): array
{
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (
        [
            'composer.json',
            'README.md',
            'LICENSE',
            'NOTICE',
            'tests/Contract/CrossCuttingNoopDoesNotThrowTest.php'
        ] as $file
    ) {
        if (!\is_file($packageRoot . '/' . $file)) {
            $diagnostics[] = $relativeRoot . '/' . $file . ': missing-required-file';
        }
    }

    foreach (['src', 'tests/Contract'] as $dir) {
        if (!\is_dir($packageRoot . '/' . $dir)) {
            $diagnostics[] = $relativeRoot . '/' . $dir . ': missing-required-directory';
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_legal_files(
    string $packageRoot,
    string $relativeRoot,
    string $repoRoot
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (['LICENSE', 'NOTICE'] as $file) {
        $packageFile = $packageRoot . '/' . $file;

        if (!\is_file($packageFile)) {
            continue;
        }

        if (coretsia_package_compliance_gate_read_file($packageFile) !== coretsia_package_compliance_gate_read_file(
            $repoRoot . '/' . $file
        )) {
            $diagnostics[] = $relativeRoot . '/' . $file . ': legal-file-drift';
        }
    }

    return $diagnostics;
}

/**
 * @return array<string,mixed>|null
 */
function coretsia_package_compliance_gate_decode_composer_json(string $composerPath): ?array
{
    if (!\is_file($composerPath) || !\is_readable($composerPath)) {
        return null;
    }

    $contents = coretsia_package_compliance_gate_read_file($composerPath);
    $decoded = \json_decode($contents, true);

    if (!\is_array($decoded) || \array_is_list($decoded)) {
        return null;
    }

    return $decoded;
}

/**
 * @param array<string,mixed> $composer
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_composer_json(
    array $composer,
    string $relativeRoot,
    string $layer,
    string $slug,
    string $namespaceRoot,
    string $sourcePathRoot,
    string $testNamespaceRoot,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    if (($composer['name'] ?? null) !== 'coretsia/' . $layer . '-' . $slug) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-composer-name';
    }

    if (($composer['type'] ?? null) !== 'library') {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-composer-type';
    }

    if (($composer['license'] ?? null) !== 'Apache-2.0') {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-composer-license';
    }

    $autoloadPsr4 = $composer['autoload']['psr-4'] ?? null;
    if (!\is_array($autoloadPsr4) || (($autoloadPsr4[$namespaceRoot] ?? null) !== $sourcePathRoot)) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-psr4-autoload';
    }

    if (isset($composer['autoload-dev'])) {
        $autoloadDevPsr4 = $composer['autoload-dev']['psr-4'] ?? null;
        if (!\is_array($autoloadDevPsr4) || (($autoloadDevPsr4[$testNamespaceRoot] ?? null) !== 'tests/')) {
            $diagnostics[] = $relativeRoot . '/composer.json: invalid-psr4-autoload-dev';
        }
    }

    $kind = coretsia_package_compliance_gate_composer_coretsia_kind($composer);
    if ($kind === null) {
        $diagnostics[] = $relativeRoot . '/composer.json: missing-package-kind';
    } elseif ($kind !== 'library' && $kind !== 'runtime') {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-package-kind';
    }

    return $diagnostics;
}

/**
 * @param array<string,mixed> $composer
 */
function coretsia_package_compliance_gate_composer_coretsia_kind(array $composer): ?string
{
    $kind = $composer['extra']['coretsia']['kind'] ?? null;

    return \is_string($kind) ? $kind : null;
}

/**
 * @param array<string,mixed> $composer
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_runtime_package(
    string $packageRoot,
    string $relativeRoot,
    string $layer,
    string $slug,
    string $namespaceRoot,
    array $composer,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    $studlySlug = coretsia_package_compliance_gate_studly($slug);
    $moduleFile = 'src/Module/' . $studlySlug . 'Module.php';
    $providerFile = 'src/Provider/' . $studlySlug . 'ServiceProvider.php';
    $defaultsConfigFile = 'config/' . $slug . '.php';

    foreach (['src/Module', 'src/Provider', 'config'] as $dir) {
        if (!\is_dir($packageRoot . '/' . $dir)) {
            $diagnostics[] = $relativeRoot . '/' . $dir . ': missing-runtime-directory';
        }
    }

    foreach ([$moduleFile, $providerFile, $defaultsConfigFile, 'config/rules.php'] as $file) {
        if (!\is_file($packageRoot . '/' . $file)) {
            $diagnostics[] = $relativeRoot . '/' . $file . ': missing-runtime-file';
        }
    }

    $moduleFqcn = $namespaceRoot . 'Module\\' . $studlySlug . 'Module';
    $providerFqcn = $namespaceRoot . 'Provider\\' . $studlySlug . 'ServiceProvider';

    $extra = $composer['extra']['coretsia'] ?? null;
    if (!\is_array($extra)) {
        $extra = [];
    }

    if (!\array_key_exists('moduleId', $extra)) {
        $diagnostics[] = $relativeRoot . '/composer.json: missing-runtime-metadata-moduleId';
    } elseif ($extra['moduleId'] !== $layer . '.' . $slug) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-runtime-metadata-moduleId';
    }

    if (!\array_key_exists('moduleClass', $extra)) {
        $diagnostics[] = $relativeRoot . '/composer.json: missing-runtime-metadata-moduleClass';
    } elseif ($extra['moduleClass'] !== $moduleFqcn) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-runtime-metadata-moduleClass';
    }

    if (!\array_key_exists('providers', $extra)) {
        $diagnostics[] = $relativeRoot . '/composer.json: missing-runtime-metadata-providers';
    } elseif (!\is_array($extra['providers']) || !\in_array($providerFqcn, $extra['providers'], true)) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-runtime-metadata-providers';
    }

    if (!\array_key_exists('defaultsConfigPath', $extra)) {
        $diagnostics[] = $relativeRoot . '/composer.json: missing-runtime-metadata-defaultsConfigPath';
    } elseif ($extra['defaultsConfigPath'] !== $defaultsConfigFile) {
        $diagnostics[] = $relativeRoot . '/composer.json: invalid-runtime-metadata-defaultsConfigPath';
    }

    if (\is_file($packageRoot . '/' . $defaultsConfigFile)) {
        foreach (
            coretsia_package_compliance_gate_validate_runtime_defaults_config(
                $packageRoot . '/' . $defaultsConfigFile,
                $relativeRoot . '/' . $defaultsConfigFile,
                $slug,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    if (\is_file($packageRoot . '/config/rules.php')) {
        foreach (
            coretsia_package_compliance_gate_validate_runtime_rules_config(
                $packageRoot . '/config/rules.php',
                $relativeRoot . '/config/rules.php',
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_runtime_defaults_config(
    string $path,
    string $relativePath,
    string $slug,
): array {
    $contents = coretsia_package_compliance_gate_read_file($path);

    if (!coretsia_package_compliance_gate_php_source_has_return_array($contents)) {
        return [$relativePath . ': config-defaults-not-array'];
    }

    $arrayBlock = coretsia_package_compliance_gate_extract_php_return_array_block($contents);
    if ($arrayBlock === null) {
        return [$relativePath . ': config-defaults-not-array'];
    }

    $topLevelKeys = coretsia_package_compliance_gate_extract_php_array_string_keys($arrayBlock);
    $wrapperCandidates = \array_values(\array_unique([$slug, \str_replace('-', '_', $slug)]));

    foreach ($wrapperCandidates as $candidate) {
        if (isset($topLevelKeys[$candidate])) {
            return [$relativePath . ': config-defaults-wrapper-root'];
        }
    }

    return [];
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_runtime_rules_config(string $path, string $relativePath): array
{
    $contents = coretsia_package_compliance_gate_read_file($path);

    if (!coretsia_package_compliance_gate_php_source_has_return_array($contents)) {
        return [$relativePath . ': config-rules-not-array'];
    }

    return [];
}

/**
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_readme(string $readmePath, string $relativePath): array
{
    $contents = coretsia_package_compliance_gate_read_file($readmePath);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    if (\preg_match('/^## Observability\s*$/m', $contents) !== 1) {
        $diagnostics[] = $relativePath . ': readme-missing-observability-section';
    }

    if (\preg_match('/^## Errors\s*$/m', $contents) !== 1) {
        $diagnostics[] = $relativePath . ': readme-missing-errors-section';
    }

    if (\preg_match('/^## Security \/ Redaction\s*$/m', $contents) !== 1) {
        $diagnostics[] = $relativePath . ': readme-missing-security-redaction-section';
    }

    return $diagnostics;
}

function coretsia_package_compliance_gate_namespace_root(string $layer, string $slug): string
{
    $override = coretsia_package_compliance_gate_namespace_root_override($layer . '/' . $slug);

    if ($override !== null) {
        return $override;
    }

    $studlySlug = coretsia_package_compliance_gate_studly($slug);

    if ($layer === 'core') {
        return 'Coretsia\\' . $studlySlug . '\\';
    }

    return 'Coretsia\\' . coretsia_package_compliance_gate_studly($layer) . '\\' . $studlySlug . '\\';
}

function coretsia_package_compliance_gate_source_path_root(string $layer, string $slug): string
{
    $override = coretsia_package_compliance_gate_source_path_root_override($layer . '/' . $slug);

    if ($override !== null) {
        return $override;
    }

    return 'src/';
}

function coretsia_package_compliance_gate_source_path_root_override(string $packageId): ?string
{
    return match ($packageId) {
        'core/dto-attribute' => 'src/Attribute/',
        default => null,
    };
}

function coretsia_package_compliance_gate_namespace_root_override(string $packageId): ?string
{
    return match ($packageId) {
        'core/dto-attribute' => 'Coretsia\\Dto\\Attribute\\',
        default => null,
    };
}

function coretsia_package_compliance_gate_studly(string $value): string
{
    $parts = \explode('-', $value);
    $studly = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $studly .= \strtoupper($part[0]) . \strtolower(\substr($part, 1));
    }

    return $studly;
}

function coretsia_package_compliance_gate_php_source_has_return_array(string $contents): bool
{
    return coretsia_package_compliance_gate_find_php_return_array_open_offset($contents) !== null;
}

function coretsia_package_compliance_gate_extract_php_return_array_block(string $contents): ?string
{
    $openPos = coretsia_package_compliance_gate_find_php_return_array_open_offset($contents);

    if ($openPos === null) {
        return null;
    }

    $open = $contents[$openPos] ?? null;

    if ($open !== '[' && $open !== '(') {
        return null;
    }

    $close = $open === '[' ? ']' : ')';

    return coretsia_package_compliance_gate_extract_balanced_block($contents, $openPos, $open, $close);
}

function coretsia_package_compliance_gate_find_php_return_array_open_offset(string $contents): ?int
{
    $tokens = \token_get_all($contents);
    $tokenCount = \count($tokens);

    /** @var list<int> $offsets */
    $offsets = [];

    $offset = 0;

    for ($i = 0; $i < $tokenCount; $i++) {
        $token = $tokens[$i];
        $offsets[$i] = $offset;

        $text = \is_array($token) ? $token[1] : $token;
        $offset += \strlen($text);
    }

    for ($i = 0; $i < $tokenCount; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token) || $token[0] !== T_RETURN) {
            continue;
        }

        $nextIndex = coretsia_package_compliance_gate_next_meaningful_token_index($tokens, $i + 1);

        if ($nextIndex === null) {
            continue;
        }

        $next = $tokens[$nextIndex];

        if ($next === '[') {
            return $offsets[$nextIndex];
        }

        if (!\is_array($next) || $next[0] !== T_ARRAY) {
            continue;
        }

        $openIndex = coretsia_package_compliance_gate_next_meaningful_token_index($tokens, $nextIndex + 1);

        if ($openIndex !== null && ($tokens[$openIndex] ?? null) === '(') {
            return $offsets[$openIndex];
        }
    }

    return null;
}

/**
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens
 */
function coretsia_package_compliance_gate_next_meaningful_token_index(array $tokens, int $start): ?int
{
    $tokenCount = \count($tokens);

    for ($i = $start; $i < $tokenCount; $i++) {
        $token = $tokens[$i];

        if (!\is_array($token)) {
            return $i;
        }

        if (
            $token[0] === T_WHITESPACE
            || $token[0] === T_COMMENT
            || $token[0] === T_DOC_COMMENT
        ) {
            continue;
        }

        return $i;
    }

    return null;
}

function coretsia_package_compliance_gate_extract_balanced_block(
    string $source,
    int $openPos,
    string $open,
    string $close,
): ?string {
    $length = \strlen($source);
    $depth = 0;

    for ($i = $openPos; $i < $length; $i++) {
        $char = $source[$i];

        if ($char === "'" || $char === '"') {
            $i = coretsia_package_compliance_gate_skip_php_string($source, $i);
            continue;
        }

        if ($char === '/' && ($source[$i + 1] ?? '') === '/') {
            $next = \strpos($source, "\n", $i + 2);
            if ($next === false) {
                return null;
            }

            $i = $next;
            continue;
        }

        if ($char === '#') {
            $next = \strpos($source, "\n", $i + 1);
            if ($next === false) {
                return null;
            }

            $i = $next;
            continue;
        }

        if ($char === '/' && ($source[$i + 1] ?? '') === '*') {
            $next = \strpos($source, '*/', $i + 2);
            if ($next === false) {
                return null;
            }

            $i = $next + 1;
            continue;
        }

        if ($char === $open) {
            $depth++;
            continue;
        }

        if ($char === $close) {
            $depth--;

            if ($depth === 0) {
                return \substr($source, $openPos, $i - $openPos + 1);
            }
        }
    }

    return null;
}

function coretsia_package_compliance_gate_skip_php_string(string $source, int $start): int
{
    $quote = $source[$start];
    $length = \strlen($source);

    for ($i = $start + 1; $i < $length; $i++) {
        if ($source[$i] === '\\') {
            $i++;
            continue;
        }

        if ($source[$i] === $quote) {
            return $i;
        }
    }

    return $length - 1;
}

/**
 * @return array<string,true>
 */
function coretsia_package_compliance_gate_extract_php_array_string_keys(string $arrayBlock): array
{
    /** @var array<string,true> $keys */
    $keys = [];
    $length = \strlen($arrayBlock);
    $depth = 0;

    for ($i = 0; $i < $length; $i++) {
        $char = $arrayBlock[$i];

        if ($char === "'" || $char === '"') {
            $end = coretsia_package_compliance_gate_skip_php_string($arrayBlock, $i);
            $literal = \substr($arrayBlock, $i, $end - $i + 1);

            if ($depth === 1) {
                $after = coretsia_package_compliance_gate_next_non_ws_offset($arrayBlock, $end + 1);
                if ($after !== null && \substr($arrayBlock, $after, 2) === '=>') {
                    $keys[coretsia_package_compliance_gate_decode_php_string_literal($literal)] = true;
                }
            }

            $i = $end;
            continue;
        }

        if ($char === '[' || $char === '(') {
            $depth++;
            continue;
        }

        if ($char === ']' || $char === ')') {
            $depth--;
        }
    }

    \ksort($keys, \SORT_STRING);

    return $keys;
}

function coretsia_package_compliance_gate_next_non_ws_offset(string $source, int $start): ?int
{
    $length = \strlen($source);

    for ($i = $start; $i < $length; $i++) {
        if (!\ctype_space($source[$i])) {
            return $i;
        }
    }

    return null;
}

function coretsia_package_compliance_gate_decode_php_string_literal(string $literal): string
{
    if (\strlen($literal) < 2) {
        throw new \RuntimeException('php-string-literal-invalid');
    }

    $quote = $literal[0];
    $inner = \substr($literal, 1, -1);

    if ($quote === "'") {
        return \str_replace(["\\\\", "\\'"], ['\\', "'"], $inner);
    }

    if ($quote === '"') {
        return \stripcslashes($inner);
    }

    throw new \RuntimeException('php-string-literal-quote-invalid');
}

/**
 * @param list<string> $values
 * @return list<string>
 */
function coretsia_package_compliance_gate_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}

function coretsia_package_compliance_gate_normalize_relative_path(string $root, string $path): string
{
    $root = \rtrim(\str_replace('\\', '/', $root), '/');
    $path = \rtrim(\str_replace('\\', '/', $path), '/');

    if (\str_starts_with($path, $root . '/')) {
        return \substr($path, \strlen($root) + 1);
    }

    return \ltrim($path, '/');
}

function coretsia_package_compliance_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $contents = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($contents)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $contents;
}

function coretsia_package_compliance_gate_error_code_or_fallback(
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
