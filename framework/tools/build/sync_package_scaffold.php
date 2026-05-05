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
                'CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED',
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

    $fallbackOutOfSync = 'CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC';
    $fallbackSyncFailed = 'CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackSyncFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $code = coretsia_sync_package_scaffold_error_code_or_fallback(
                    $ErrorCodes,
                    'CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED',
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

    $codeOutOfSync = coretsia_sync_package_scaffold_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC',
        $fallbackOutOfSync,
    );

    $codeSyncFailed = coretsia_sync_package_scaffold_error_code_or_fallback(
        $ErrorCodes,
        'CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED',
        $fallbackSyncFailed,
    );

    try {
        $repoRoot = coretsia_sync_package_scaffold_resolve_repo_root($toolsRootRuntime);
        $options = coretsia_sync_package_scaffold_resolve_options($argv, $toolsRootRuntime . '/..', $repoRoot);

        $canonicalPackageFiles = [
            'LICENSE' => coretsia_sync_package_scaffold_read_file($repoRoot . '/LICENSE'),
            'NOTICE' => coretsia_sync_package_scaffold_read_file($repoRoot . '/NOTICE'),
            'SECURITY.md' => coretsia_sync_package_scaffold_read_file($repoRoot . '/SECURITY.md'),
        ];

        $diagnostics = coretsia_sync_package_scaffold_run(
            $options['scan_root'],
            $canonicalPackageFiles,
            $options['check'],
        );

        $diagnostics = \array_values(\array_unique($diagnostics));
        \sort($diagnostics, \SORT_STRING);

        if ($options['check'] && $diagnostics !== []) {
            $ConsoleOutput::codeWithDiagnostics($codeOutOfSync, $diagnostics);
            exit(1);
        }

        exit(0);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeSyncFailed, []);
        }

        exit(1);
    }
})(
    isset($_SERVER['argv']) && \is_array($_SERVER['argv']) ? $_SERVER['argv'] : []
);

/**
 * @param list<string> $argv
 * @return array{check:bool,scan_root:string}
 */
function coretsia_sync_package_scaffold_resolve_options(array $argv, string $defaultScanRoot, string $repoRoot): array
{
    $check = false;
    $scanRoot = $defaultScanRoot;

    foreach (\array_slice($argv, 1) as $arg) {
        if (!\is_string($arg) || $arg === '') {
            continue;
        }

        if ($arg === '--') {
            continue;
        }

        if ($arg === '--check') {
            $check = true;
            continue;
        }

        if (\str_starts_with($arg, '--path=')) {
            $scanRoot = \substr($arg, \strlen('--path='));
            continue;
        }

        if (\str_starts_with($arg, '-')) {
            throw new \RuntimeException('package-scaffold-unknown-option');
        }

        $scanRoot = $arg;
    }

    return [
        'check' => $check,
        'scan_root' => coretsia_sync_package_scaffold_resolve_existing_dir(
            $scanRoot,
            $defaultScanRoot,
            $repoRoot,
        ),
    ];
}

function coretsia_sync_package_scaffold_resolve_existing_dir(
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

    if (coretsia_sync_package_scaffold_is_absolute_path($path)) {
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

function coretsia_sync_package_scaffold_is_absolute_path(string $path): bool
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

function coretsia_sync_package_scaffold_resolve_repo_root(string $toolsRootRuntime): string
{
    $repoRoot = \realpath($toolsRootRuntime . '/..' . '/..');

    if (!\is_string($repoRoot) || !\is_dir($repoRoot) || !\is_readable($repoRoot)) {
        throw new \RuntimeException('repo-root-invalid');
    }

    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    foreach (['LICENSE', 'NOTICE', 'SECURITY.md'] as $file) {
        $path = $repoRoot . '/' . $file;

        if (!\is_file($path) || !\is_readable($path)) {
            throw new \RuntimeException('repo-canonical-package-file-missing');
        }
    }

    return $repoRoot;
}

/**
 * @param array<string,string> $canonicalPackageFiles
 * @return list<string>
 */
function coretsia_sync_package_scaffold_run(
    string $scanRoot,
    array $canonicalPackageFiles,
    bool $check,
): array {
    $packageRoots = coretsia_sync_package_scaffold_collect_package_roots($scanRoot);

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($packageRoots as $packageRoot) {
        foreach (
            coretsia_sync_package_scaffold_sync_package(
                $scanRoot,
                $packageRoot,
                $canonicalPackageFiles,
                $check,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return coretsia_sync_package_scaffold_unique_sorted($diagnostics);
}

/**
 * @return list<string>
 */
function coretsia_sync_package_scaffold_collect_package_roots(string $scanRoot): array
{
    $scanRoot = \rtrim(\str_replace('\\', '/', $scanRoot), '/');

    if (
        \is_file($scanRoot . '/composer.json')
        && \preg_match('~/packages/[a-z][a-z0-9-]*/[a-z0-9][a-z0-9-]*\z~', $scanRoot) === 1
    ) {
        return [$scanRoot];
    }

    $packagesRoot = $scanRoot . '/packages';

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

    return coretsia_sync_package_scaffold_unique_sorted($packageRoots);
}

/**
 * @param array<string,string> $canonicalPackageFiles
 * @return list<string>
 */
function coretsia_sync_package_scaffold_sync_package(
    string $scanRoot,
    string $packageRoot,
    array $canonicalPackageFiles,
    bool $check,
): array {
    $identity = coretsia_sync_package_scaffold_package_identity($scanRoot, $packageRoot);

    $relativeRoot = $identity['relative_root'];
    $layer = $identity['layer'];
    $slug = $identity['slug'];

    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach (
        coretsia_sync_package_scaffold_sync_canonical_package_files(
            $packageRoot,
            $relativeRoot,
            $canonicalPackageFiles,
            $check,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    foreach (
        coretsia_sync_package_scaffold_sync_baseline_files(
            $packageRoot,
            $relativeRoot,
            $layer,
            $slug,
            $check,
        ) as $diagnostic
    ) {
        $diagnostics[] = $diagnostic;
    }

    if (coretsia_sync_package_scaffold_package_kind($packageRoot) === 'runtime') {
        foreach (
            coretsia_sync_package_scaffold_sync_runtime_files(
                $packageRoot,
                $relativeRoot,
                $layer,
                $slug,
                $check,
            ) as $diagnostic
        ) {
            $diagnostics[] = $diagnostic;
        }
    }

    return coretsia_sync_package_scaffold_unique_sorted($diagnostics);
}

/**
 * @return array{relative_root:string,layer:string,slug:string,package_id:string}
 */
function coretsia_sync_package_scaffold_package_identity(string $scanRoot, string $packageRoot): array
{
    $relativeRoot = coretsia_sync_package_scaffold_normalize_relative_path($scanRoot, $packageRoot);
    $parts = \explode('/', $relativeRoot);

    if (\count($parts) === 3 && $parts[0] === 'packages') {
        $layer = $parts[1];
        $slug = $parts[2];

        return [
            'relative_root' => $relativeRoot,
            'layer' => $layer,
            'slug' => $slug,
            'package_id' => $layer . '/' . $slug,
        ];
    }

    $normalizedPackageRoot = \rtrim(\str_replace('\\', '/', $packageRoot), '/');

    if (
        \preg_match(
            '~/packages/([a-z][a-z0-9-]*)/([a-z0-9][a-z0-9-]*)\z~',
            $normalizedPackageRoot,
            $matches,
        ) !== 1
    ) {
        throw new \RuntimeException('package-path-invalid');
    }

    $layer = $matches[1];
    $slug = $matches[2];
    $relativeRoot = 'packages/' . $layer . '/' . $slug;

    return [
        'relative_root' => $relativeRoot,
        'layer' => $layer,
        'slug' => $slug,
        'package_id' => $layer . '/' . $slug,
    ];
}

/**
 * @param array<string,string> $canonicalPackageFiles
 * @return list<string>
 */
function coretsia_sync_package_scaffold_sync_canonical_package_files(
    string $packageRoot,
    string $relativeRoot,
    array $canonicalPackageFiles,
    bool $check,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    foreach ($canonicalPackageFiles as $file => $canonicalContent) {
        $path = $packageRoot . '/' . $file;
        $relativePath = $relativeRoot . '/' . $file;

        if (!\is_file($path)) {
            if ($check) {
                $diagnostics[] = $relativePath . ': missing-canonical-package-file';
                continue;
            }

            coretsia_sync_package_scaffold_write_file_exact($path, $canonicalContent);
            continue;
        }

        if (coretsia_sync_package_scaffold_read_file($path) !== $canonicalContent) {
            if ($check) {
                $diagnostics[] = $relativePath . ': canonical-package-file-drift';
                continue;
            }

            coretsia_sync_package_scaffold_write_file_exact($path, $canonicalContent);
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_sync_package_scaffold_sync_baseline_files(
    string $packageRoot,
    string $relativeRoot,
    string $layer,
    string $slug,
    bool $check,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    $readmePath = $packageRoot . '/README.md';
    if (!\is_file($readmePath)) {
        if ($check) {
            $diagnostics[] = $relativeRoot . '/README.md: missing-scaffold-file';
        } else {
            coretsia_sync_package_scaffold_write_file_exact(
                $readmePath,
                coretsia_sync_package_scaffold_readme_template($layer, $slug),
            );
        }
    }

    $contractTestPath = $packageRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php';
    if (!\is_file($contractTestPath)) {
        if ($check) {
            $diagnostics[] = $relativeRoot . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php: missing-scaffold-file';
        } else {
            coretsia_sync_package_scaffold_write_file_exact(
                $contractTestPath,
                coretsia_sync_package_scaffold_contract_test_template($layer, $slug),
            );
        }
    }

    return $diagnostics;
}

/**
 * @return list<string>
 */
function coretsia_sync_package_scaffold_sync_runtime_files(
    string $packageRoot,
    string $relativeRoot,
    string $layer,
    string $slug,
    bool $check,
): array {
    /** @var list<string> $diagnostics */
    $diagnostics = [];

    $studlySlug = coretsia_sync_package_scaffold_studly($slug);

    $requiredDirs = [
        'src/Module',
        'src/Provider',
        'config',
    ];

    foreach ($requiredDirs as $dir) {
        $path = $packageRoot . '/' . $dir;

        if (\is_dir($path)) {
            continue;
        }

        if ($check) {
            $diagnostics[] = $relativeRoot . '/' . $dir . ': missing-runtime-directory';
            continue;
        }

        coretsia_sync_package_scaffold_ensure_dir($path);
    }

    $runtimeFiles = [
        'src/Module/' . $studlySlug . 'Module.php' => coretsia_sync_package_scaffold_runtime_module_template(
            $layer,
            $slug
        ),
        'src/Provider/' . $studlySlug . 'ServiceProvider.php' => coretsia_sync_package_scaffold_runtime_provider_template(
            $layer,
            $slug
        ),
        'config/' . $slug . '.php' => coretsia_sync_package_scaffold_runtime_defaults_config_template(),
        'config/rules.php' => coretsia_sync_package_scaffold_runtime_rules_config_template(),
    ];

    foreach ($runtimeFiles as $file => $content) {
        $path = $packageRoot . '/' . $file;

        if (\is_file($path)) {
            continue;
        }

        if ($check) {
            $diagnostics[] = $relativeRoot . '/' . $file . ': missing-runtime-file';
            continue;
        }

        coretsia_sync_package_scaffold_write_file_exact($path, $content);
    }

    return $diagnostics;
}

function coretsia_sync_package_scaffold_package_kind(string $packageRoot): ?string
{
    $composerPath = $packageRoot . '/composer.json';

    if (!\is_file($composerPath) || !\is_readable($composerPath)) {
        return null;
    }

    $decoded = \json_decode(coretsia_sync_package_scaffold_read_file($composerPath), true);

    if (!\is_array($decoded) || \array_is_list($decoded)) {
        return null;
    }

    $kind = $decoded['extra']['coretsia']['kind'] ?? null;

    return \is_string($kind) ? $kind : null;
}

function coretsia_sync_package_scaffold_readme_template(string $layer, string $slug): string
{
    $title = 'Coretsia ' . coretsia_sync_package_scaffold_readable_title($layer, $slug);

    return '<!--' . "\n"
        . '  Coretsia Framework (Monorepo)' . "\n\n"
        . '  Project: Coretsia Framework (Monorepo)' . "\n"
        . '  Authors: Vladyslav Mudrichenko and contributors' . "\n"
        . '  Copyright (c) 2026 Vladyslav Mudrichenko' . "\n\n"
        . '  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko' . "\n"
        . '  SPDX-License-Identifier: Apache-2.0' . "\n\n"
        . '  For contributors list, see git history.' . "\n"
        . '  See LICENSE and NOTICE in the project root for full license information.' . "\n"
        . '-->' . "\n\n"
        . '# ' . $title . "\n\n"
        . 'Package scaffold placeholder. Replace this README with package-specific documentation.' . "\n\n"
        . '## Observability' . "\n\n"
        . 'This package does not document additional observability behavior yet.' . "\n\n"
        . '## Errors' . "\n\n"
        . 'This package does not document additional error behavior yet.' . "\n\n"
        . '## Security / Redaction' . "\n\n"
        . 'This package does not document additional security or redaction behavior yet.' . "\n";
}

function coretsia_sync_package_scaffold_contract_test_template(string $layer, string $slug): string
{
    $namespace = \rtrim(coretsia_sync_package_scaffold_namespace_root($layer, $slug), '\\') . '\\Tests\\Contract';

    return "<?php\n\n"
        . "declare(strict_types=1);\n\n"
        . "/*\n"
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
        . " */\n\n"
        . 'namespace ' . $namespace . ";\n\n"
        . "use PHPUnit\\Framework\\TestCase;\n\n"
        . "final class CrossCuttingNoopDoesNotThrowTest extends TestCase\n"
        . "{\n"
        . "    public function testNoopDoesNotThrow(): void\n"
        . "    {\n"
        . "        self::assertTrue(true);\n"
        . "    }\n"
        . "}\n";
}

function coretsia_sync_package_scaffold_runtime_module_template(string $layer, string $slug): string
{
    $namespace = \rtrim(coretsia_sync_package_scaffold_namespace_root($layer, $slug), '\\') . '\\Module';
    $className = coretsia_sync_package_scaffold_studly($slug) . 'Module';

    return coretsia_sync_package_scaffold_php_class_template($namespace, $className);
}

function coretsia_sync_package_scaffold_runtime_provider_template(string $layer, string $slug): string
{
    $namespace = \rtrim(coretsia_sync_package_scaffold_namespace_root($layer, $slug), '\\') . '\\Provider';
    $className = coretsia_sync_package_scaffold_studly($slug) . 'ServiceProvider';

    return coretsia_sync_package_scaffold_php_class_template($namespace, $className);
}

function coretsia_sync_package_scaffold_php_class_template(string $namespace, string $className): string
{
    return "<?php\n\n"
        . "declare(strict_types=1);\n\n"
        . "/*\n"
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
        . " */\n\n"
        . 'namespace ' . $namespace . ";\n\n"
        . 'final class ' . $className . "\n"
        . "{\n"
        . "}\n";
}

function coretsia_sync_package_scaffold_runtime_defaults_config_template(): string
{
    return "<?php\n\n"
        . "declare(strict_types=1);\n\n"
        . "/*\n"
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
        . " */\n\n"
        . "return [\n"
        . "];\n";
}

function coretsia_sync_package_scaffold_runtime_rules_config_template(): string
{
    return "<?php\n\n"
        . "declare(strict_types=1);\n\n"
        . "/*\n"
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
        . " */\n\n"
        . "return [\n"
        . "];\n";
}

function coretsia_sync_package_scaffold_readable_title(string $layer, string $slug): string
{
    if ($layer === 'core') {
        return coretsia_sync_package_scaffold_words_title($slug);
    }

    return coretsia_sync_package_scaffold_words_title($layer)
        . ' '
        . coretsia_sync_package_scaffold_words_title($slug);
}

function coretsia_sync_package_scaffold_words_title(string $value): string
{
    $words = [];

    foreach (\explode('-', $value) as $part) {
        if ($part === '') {
            continue;
        }

        $words[] = \strtoupper($part[0]) . \strtolower(\substr($part, 1));
    }

    return \implode(' ', $words);
}

function coretsia_sync_package_scaffold_namespace_root(string $layer, string $slug): string
{
    $override = coretsia_sync_package_scaffold_namespace_root_override($layer . '/' . $slug);

    if ($override !== null) {
        return $override;
    }

    $studlySlug = coretsia_sync_package_scaffold_studly($slug);

    if ($layer === 'core') {
        return 'Coretsia\\' . $studlySlug . '\\';
    }

    return 'Coretsia\\' . coretsia_sync_package_scaffold_studly($layer) . '\\' . $studlySlug . '\\';
}

function coretsia_sync_package_scaffold_namespace_root_override(string $packageId): ?string
{
    return match ($packageId) {
        'core/dto-attribute' => 'Coretsia\\Dto\\Attribute\\',
        default => null,
    };
}

function coretsia_sync_package_scaffold_studly(string $value): string
{
    $studly = '';

    foreach (\explode('-', $value) as $part) {
        if ($part === '') {
            continue;
        }

        $studly .= \strtoupper($part[0]) . \strtolower(\substr($part, 1));
    }

    return $studly;
}

function coretsia_sync_package_scaffold_read_file(string $path): string
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

function coretsia_sync_package_scaffold_write_file_exact(string $path, string $contents): void
{
    coretsia_sync_package_scaffold_ensure_dir(\dirname($path));

    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $result = \file_put_contents($path, $contents);
    } finally {
        \restore_error_handler();
    }

    if ($result === false) {
        throw new \RuntimeException('file-write-failed');
    }
}

function coretsia_sync_package_scaffold_ensure_dir(string $dir): void
{
    if (\is_dir($dir)) {
        return;
    }

    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $created = \mkdir($dir, 0777, true);
    } finally {
        \restore_error_handler();
    }

    if (!$created && !\is_dir($dir)) {
        throw new \RuntimeException('directory-create-failed');
    }
}

function coretsia_sync_package_scaffold_normalize_relative_path(string $root, string $path): string
{
    $root = \rtrim(\str_replace('\\', '/', $root), '/');
    $path = \rtrim(\str_replace('\\', '/', $path), '/');

    if (\str_starts_with($path, $root . '/')) {
        return \substr($path, \strlen($root) + 1);
    }

    return \ltrim($path, '/');
}

/**
 * @param list<string> $values
 * @return list<string>
 */
function coretsia_sync_package_scaffold_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}

function coretsia_sync_package_scaffold_error_code_or_fallback(
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
