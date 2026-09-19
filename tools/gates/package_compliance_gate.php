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

(static function (array $argv): void {
    try {
        $options = coretsia_package_compliance_gate_resolve_options($argv);

        $repository = $options['repo_root'] === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($options['repo_root']);

        $catalog = WorkspacePackageCatalog::discover($repository);
        $layeredProducts = $catalog->layeredPackages();

        $products = coretsia_package_compliance_gate_select_products(
            $repository,
            $layeredProducts,
            $options['path'],
        );

        $allowlistPath = coretsia_package_compliance_gate_allowlist_path(
            $repository,
            $options['allowlist'],
        );

        $allowlist = coretsia_package_compliance_gate_load_allowlist($allowlistPath);

        coretsia_package_compliance_gate_assert_allowlist_products(
            $allowlist,
            $layeredProducts,
        );

        $diagnostics = coretsia_package_compliance_gate_scan(
            $products,
            $repository,
            $allowlist,
        );

        $specialScope = $options['path'] === null
            ? null
            : $repository->resolveExistingDirectory($options['path']);

        foreach ($catalog->specialDistributions() as $product) {
            if (
                $specialScope !== null
                && $specialScope !== $product['absolutePath']
                && !RepositoryContext::containsPath(
                    $specialScope,
                    $product['absolutePath'],
                )
            ) {
                continue;
            }

            foreach (
                coretsia_package_compliance_gate_validate_special_distribution(
                    $product,
                    $repository,
                ) as $diagnostic
            ) {
                $diagnostics[] = $diagnostic;
            }
        }

        $diagnostics = coretsia_package_compliance_gate_unique_sorted($diagnostics);

        if ($diagnostics === []) {
            exit(0);
        }

        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_PACKAGE_COMPLIANCE_VIOLATION,
            $diagnostics,
        );

        exit(1);
    } catch (Throwable) {
        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_PACKAGE_COMPLIANCE_GATE_FAILED,
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
 *     repo_root: string|null,
 *     path: string|null,
 *     allowlist: string|null
 * }
 */
function coretsia_package_compliance_gate_resolve_options(array $argv): array
{
    $values = [
        'repo_root' => null,
        'path' => null,
        'allowlist' => null,
    ];

    $count = count($argv);

    for ($i = 1; $i < $count; $i++) {
        $arg = trim((string) $argv[$i]);

        if ($arg === '') {
            continue;
        }

        foreach (
            [
                'repo-root' => 'repo_root',
                'path' => 'path',
                'allowlist' => 'allowlist',
            ] as $name => $key
        ) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                $value = trim(substr($arg, strlen('--' . $name . '=')));

                if ($value === '' || $values[$key] !== null) {
                    throw new RuntimeException('package-compliance-argument-invalid');
                }

                $values[$key] = $value;

                continue 2;
            }

            if ($arg === '--' . $name) {
                $value = $i + 1 < $count
                    ? trim((string) $argv[$i + 1])
                    : '';

                if (
                    $value === ''
                    || str_starts_with($value, '--')
                    || $values[$key] !== null
                ) {
                    throw new RuntimeException('package-compliance-argument-invalid');
                }

                $values[$key] = $value;
                $i++;

                continue 2;
            }
        }

        if (str_starts_with($arg, '--')) {
            throw new RuntimeException('package-compliance-unknown-option');
        }

        if ($values['path'] !== null) {
            throw new RuntimeException('package-compliance-path-duplicate');
        }

        // Preserve the previous positional scan-path form.
        $values['path'] = $arg;
    }

    return $values;
}

/**
 * @param list<array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }> $layeredProducts
 *
 * @return list<array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }>
 */
function coretsia_package_compliance_gate_select_products(
    RepositoryContext $repository,
    array $layeredProducts,
    ?string $path,
): array {
    if ($path === null) {
        return $layeredProducts;
    }

    $scope = $repository->resolveExistingDirectory($path);

    $selected = [];

    foreach ($layeredProducts as $product) {
        if (
            $scope !== $product['absolutePath']
            && !RepositoryContext::containsPath(
                $scope,
                $product['absolutePath'],
            )
        ) {
            continue;
        }

        $selected[] = $product;
    }

    return $selected;
}

function coretsia_package_compliance_gate_allowlist_path(
    RepositoryContext $repository,
    ?string $path,
): string {
    return $repository->resolveExistingFile($path ?? 'tools/config/package_compliance_allowlist.php');
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

        if (
            preg_match(
                '/\A[a-z0-9]+(?:[._-][a-z0-9]+)*\/[a-z0-9]+(?:[._-][a-z0-9]+)*\z/u',
                $item,
            ) !== 1
        ) {
            throw new RuntimeException('package-compliance-allowlist-entry-invalid');
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
 * @param list<array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }> $layeredProducts
 */
function coretsia_package_compliance_gate_assert_allowlist_products(
    array $allowlist,
    array $layeredProducts,
): void {
    /** @var array<string,true> $known */
    $known = [];

    foreach ($layeredProducts as $product) {
        $known[$product['packageId']] = true;
    }

    foreach (array_keys($allowlist) as $packageId) {
        if (isset($known[$packageId])) {
            continue;
        }

        throw new RuntimeException('package-compliance-allowlist-product-invalid');
    }
}

/**
 * @param list<array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }> $products
 * @param array<string,true> $allowlist
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_scan(
    array $products,
    RepositoryContext $repository,
    array $allowlist,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($products as $product) {
        foreach (
            coretsia_package_compliance_gate_validate_package(
                $product,
                $repository,
                $allowlist,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return coretsia_package_compliance_gate_unique_sorted($diagnostics);
}

/**
 * @param array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * } $product
 * @param array<string,true> $allowlist
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_package(
    array $product,
    RepositoryContext $repository,
    array $allowlist,
): array {
    $packageRoot = $product['absolutePath'];
    $relativeRoot = $product['relativePath'];
    $layer = $product['layer'];
    $slug = $product['slug'];
    $packageId = $product['packageId'];

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (
        coretsia_package_compliance_gate_validate_package_identity(
            $relativeRoot,
            $layer,
            $slug,
            $packageId,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    if (isset($allowlist[$packageId])) {
        return coretsia_package_compliance_gate_unique_sorted($diagnostics);
    }

    $symlinkDiagnostics = coretsia_package_compliance_gate_validate_symlink_paths(
        $packageRoot,
        $relativeRoot,
        [
            'composer.json',
            'README.md',
            'LICENSE',
            'NOTICE',
            'SECURITY.md',
            'src',
            'tests/Contract',
            'tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
        ],
    );

    if ($symlinkDiagnostics !== []) {
        return coretsia_package_compliance_gate_unique_sorted([
            ...$diagnostics,
            ...$symlinkDiagnostics,
        ]);
    }

    foreach (
        coretsia_package_compliance_gate_validate_required_scaffold(
            $packageRoot,
            $relativeRoot,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    foreach (
        coretsia_package_compliance_gate_validate_canonical_package_files(
            $packageRoot,
            $relativeRoot,
            $repository,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    $composer = ComposerJson::readObject($product['composerJsonPath']);

    $namespaceRoot = coretsia_package_compliance_gate_namespace_root(
        $layer,
        $slug,
    );
    $sourcePathRoot = coretsia_package_compliance_gate_source_path_root(
        $layer,
        $slug,
    );
    $testNamespaceRoot = $namespaceRoot . 'Tests\\';

    foreach (
        coretsia_package_compliance_gate_validate_composer_json(
            $composer,
            $relativeRoot,
            $namespaceRoot,
            $sourcePathRoot,
            $testNamespaceRoot,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    $kind = coretsia_package_compliance_gate_composer_coretsia_kind($composer);

    if ($kind === 'runtime') {
        $studlySlug = coretsia_package_compliance_gate_studly($slug);

        $runtimeSymlinkDiagnostics = coretsia_package_compliance_gate_validate_symlink_paths(
            $packageRoot,
            $relativeRoot,
            [
                'src/Module',
                'src/Provider',
                'config',
                'src/Module/' . $studlySlug . 'Module.php',
                'src/Provider/' . $studlySlug . 'ServiceProvider.php',
                'config/' . $slug . '.php',
                'config/rules.php',
            ],
        );

        if ($runtimeSymlinkDiagnostics !== []) {
            return coretsia_package_compliance_gate_unique_sorted([
                ...$diagnostics,
                ...$runtimeSymlinkDiagnostics,
            ]);
        }

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

    if (is_file($packageRoot . '/README.md')) {
        foreach (
            coretsia_package_compliance_gate_validate_readme(
                $packageRoot . '/README.md',
                $relativeRoot . '/README.md',
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return coretsia_package_compliance_gate_unique_sorted($diagnostics);
}

/**
 * @param array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:null,
 *     slug:null,
 *     packageId:null
 * } $product
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_special_distribution(
    array $product,
    RepositoryContext $repository,
): array {
    $packageRoot = $product['absolutePath'];
    $relativeRoot = $product['relativePath'];

    $requiredFiles = [
        'composer.json',
        'README.md',
        'LICENSE',
        'NOTICE',
        'SECURITY.md',
    ];

    $diagnostics = coretsia_package_compliance_gate_validate_symlink_paths(
        $packageRoot,
        $relativeRoot,
        $requiredFiles,
    );

    if ($diagnostics !== []) {
        return $diagnostics;
    }

    foreach ($requiredFiles as $file) {
        if (!is_file($packageRoot . '/' . $file)) {
            $diagnostics[] = $relativeRoot
                . '/'
                . $file
                . ': missing-required-file';
        }
    }

    foreach (
        coretsia_package_compliance_gate_validate_canonical_package_files(
            $packageRoot,
            $relativeRoot,
            $repository,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    return coretsia_package_compliance_gate_unique_sorted($diagnostics);
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

    if (!WorkspacePackageCatalog::isLayeredRoot($layer)) {
        $diagnostics[] = $relativeRoot . ': invalid-package-layer';
    }

    if (\preg_match('/\A[a-z0-9][a-z0-9-]*\z/', $slug) !== 1) {
        $diagnostics[] = $relativeRoot . ': invalid-package-slug';
    }

    if (\in_array($slug, ['app', 'modules', 'shared'], true)) {
        $diagnostics[] = $relativeRoot . ': forbidden-slug';
    }

    if (
        $layer === 'core'
        && in_array(
            $slug,
            WorkspacePackageCatalog::layeredRoots(),
            true,
        )
    ) {
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
            'SECURITY.md',
            'tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
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
function coretsia_package_compliance_gate_validate_canonical_package_files(
    string $packageRoot,
    string $relativeRoot,
    RepositoryContext $repository,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (['LICENSE', 'NOTICE', 'SECURITY.md'] as $file) {
        $packageFile = $packageRoot . '/' . $file;

        if (!is_file($packageFile)) {
            continue;
        }

        $canonicalFile = $repository->resolveExistingFile($file);

        if (
            coretsia_package_compliance_gate_read_file($packageFile)
            === DeterministicFile::readBytesExact($canonicalFile)
        ) {
            continue;
        }

        $reason = in_array($file, ['LICENSE', 'NOTICE'], true)
            ? 'legal-file-drift'
            : 'canonical-package-file-drift';

        $diagnostics[] = $relativeRoot
            . '/'
            . $file
            . ': '
            . $reason;
    }

    return $diagnostics;
}

/**
 * @param array<string,mixed> $composer
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_composer_json(
    array $composer,
    string $relativeRoot,
    string $namespaceRoot,
    string $sourcePathRoot,
    string $testNamespaceRoot,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

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
 *
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
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}

/**
 * @param list<string> $relativePaths
 *
 * @return list<string>
 */
function coretsia_package_compliance_gate_validate_symlink_paths(
    string $packageRoot,
    string $relativeRoot,
    array $relativePaths,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($relativePaths as $relativePath) {
        $symlinkPath = coretsia_package_compliance_gate_symlink_component(
            $packageRoot,
            $relativePath,
        );

        if ($symlinkPath === null) {
            continue;
        }

        $diagnostics[] = $relativeRoot
            . '/'
            . $symlinkPath
            . ': symlink-not-allowed';
    }

    return coretsia_package_compliance_gate_unique_sorted(
        $diagnostics,
    );
}

function coretsia_package_compliance_gate_symlink_component(
    string $packageRoot,
    string $relativePath,
): ?string {
    $packageRoot = rtrim(
        RepositoryContext::normalizePath($packageRoot),
        '/',
    );
    $relativePath = trim(
        str_replace('\\', '/', $relativePath),
        '/',
    );

    if ($relativePath === '') {
        return null;
    }

    $current = $packageRoot;
    $segments = [];

    foreach (explode('/', $relativePath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }

        $segments[] = $segment;
        $current .= '/' . $segment;

        if (is_link($current)) {
            return implode('/', $segments);
        }

        if (!file_exists($current)) {
            return null;
        }
    }

    return null;
}

function coretsia_package_compliance_gate_read_file(string $path): string
{
    return DeterministicFile::readBytesExact($path);
}
