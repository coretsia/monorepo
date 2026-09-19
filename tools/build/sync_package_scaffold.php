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
        $options = coretsia_sync_package_scaffold_resolve_options($argv);

        $repository = $options['repo_root'] === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($options['repo_root']);

        $canonicalPackageFiles = [
            'LICENSE' => DeterministicFile::readBytesExact(
                $repository->resolveExistingFile('LICENSE'),
            ),
            'NOTICE' => DeterministicFile::readBytesExact(
                $repository->resolveExistingFile('NOTICE'),
            ),
            'SECURITY.md' => DeterministicFile::readBytesExact(
                $repository->resolveExistingFile('SECURITY.md'),
            ),
        ];

        $products = coretsia_sync_package_scaffold_products(
            $repository,
            $options['path'],
        );

        $diagnostics = coretsia_sync_package_scaffold_run(
            $products,
            $canonicalPackageFiles,
            $options['check'],
        );

        if ($options['check'] && $diagnostics !== []) {
            ConsoleOutput::codeWithDiagnostics(
                ErrorCodes::CORETSIA_PACKAGE_SCAFFOLD_OUT_OF_SYNC,
                $diagnostics,
            );

            exit(1);
        }

        exit(0);
    } catch (Throwable) {
        ConsoleOutput::codeWithDiagnostics(
            ErrorCodes::CORETSIA_PACKAGE_SCAFFOLD_SYNC_FAILED,
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
 *     check: bool,
 *     repo_root: string|null,
 *     path: string|null
 * }
 */
function coretsia_sync_package_scaffold_resolve_options(array $argv): array
{
    $values = [
        'check' => false,
        'repo_root' => null,
        'path' => null,
    ];

    $count = count($argv);

    for ($i = 1; $i < $count; $i++) {
        $arg = trim((string) $argv[$i]);

        if ($arg === '') {
            continue;
        }

        if ($arg === '--check') {
            $values['check'] = true;
            continue;
        }

        foreach (
            [
                'repo-root' => 'repo_root',
                'path' => 'path',
            ] as $name => $key
        ) {
            if (str_starts_with($arg, '--' . $name . '=')) {
                $value = trim(substr($arg, strlen('--' . $name . '=')));

                if ($value === '' || $values[$key] !== null) {
                    throw new RuntimeException('package-scaffold-argument-invalid');
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
                    throw new RuntimeException('package-scaffold-argument-invalid');
                }

                $values[$key] = $value;
                $i++;

                continue 2;
            }
        }

        if (str_starts_with($arg, '--')) {
            throw new RuntimeException('package-scaffold-unknown-option');
        }

        if ($values['path'] !== null) {
            throw new RuntimeException('package-scaffold-path-duplicate');
        }

        // Preserve the old positional package-path form.
        $values['path'] = $arg;
    }

    return $values;
}

/**
 * @param list<array{
 *     relativePath:string,
 *     absolutePath:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }> $products
 * @param array<string,string> $canonicalPackageFiles
 *
 * @return list<string>
 */
function coretsia_sync_package_scaffold_run(
    array $products,
    array $canonicalPackageFiles,
    bool $check,
): array {
    $diagnostics = [];

    foreach ($products as $product) {
        foreach (
            coretsia_sync_package_scaffold_sync_package(
                $product,
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
 * @param array{
 *     relativePath:string,
 *     absolutePath:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * } $product
 * @param array<string,string> $canonicalPackageFiles
 *
 * @return list<string>
 */
function coretsia_sync_package_scaffold_sync_package(
    array $product,
    array $canonicalPackageFiles,
    bool $check,
): array {
    $packageRoot = $product['absolutePath'];
    $relativeRoot = $product['relativePath'];
    $layer = $product['layer'];
    $slug = $product['slug'];

    $diagnostics = [];

    coretsia_sync_package_scaffold_assert_safe_path(
        $packageRoot,
        'composer.json',
    );

    $kind = coretsia_sync_package_scaffold_package_kind($packageRoot);

    $safePaths = [
        'LICENSE',
        'NOTICE',
        'SECURITY.md',
        'README.md',
        'src',
        'tests/Contract/CrossCuttingNoopDoesNotThrowTest.php',
    ];

    if ($kind === 'runtime') {
        $studlySlug = coretsia_sync_package_scaffold_studly($slug);

        $safePaths = [
            ...$safePaths,
            'src/Module',
            'src/Provider',
            'config',
            'src/Module/' . $studlySlug . 'Module.php',
            'src/Provider/' . $studlySlug . 'ServiceProvider.php',
            'config/' . $slug . '.php',
            'config/rules.php',
        ];
    }

    foreach ($safePaths as $relativePath) {
        coretsia_sync_package_scaffold_assert_safe_path(
            $packageRoot,
            $relativePath,
        );
    }

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

    if ($kind === 'runtime') {
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
 * @return list<array{
 *     relativePath:string,
 *     absolutePath:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }>
 */
function coretsia_sync_package_scaffold_products(
    RepositoryContext $repository,
    ?string $path,
): array {
    $catalog = WorkspacePackageCatalog::discover($repository);
    $products = $catalog->layeredPackages();

    if ($path === null) {
        return array_map(
            static fn (array $product): array => [
                'relativePath' => $product['relativePath'],
                'absolutePath' => $product['absolutePath'],
                'layer' => $product['layer'],
                'slug' => $product['slug'],
                'packageId' => $product['packageId'],
            ],
            $products,
        );
    }

    $scope = $repository->resolveExistingDirectory($path);

    foreach ($products as $product) {
        if ($product['absolutePath'] !== $scope) {
            continue;
        }

        return [
            [
                'relativePath' => $product['relativePath'],
                'absolutePath' => $product['absolutePath'],
                'layer' => $product['layer'],
                'slug' => $product['slug'],
                'packageId' => $product['packageId'],
            ]
        ];
    }

    $selected = [];

    foreach ($products as $product) {
        if (!RepositoryContext::containsPath($scope, $product['absolutePath'])) {
            continue;
        }

        $selected[] = [
            'relativePath' => $product['relativePath'],
            'absolutePath' => $product['absolutePath'],
            'layer' => $product['layer'],
            'slug' => $product['slug'],
            'packageId' => $product['packageId'],
        ];
    }

    if ($selected !== []) {
        return $selected;
    }

    /*
     * new-package stages a package below var/tmp/.../packages/<layer>/<slug>.
     * It is intentionally not yet part of WorkspacePackageCatalog.
     */
    if (is_file($scope . '/composer.json')) {
        return [
            coretsia_sync_package_scaffold_explicit_candidate(
                $repository,
                $scope,
            ),
        ];
    }

    return [];
}

/**
 * @return array{
 *     relativePath:string,
 *     absolutePath:string,
 *     layer:string,
 *     slug:string,
 *     packageId:string
 * }
 */
function coretsia_sync_package_scaffold_explicit_candidate(
    RepositoryContext $repository,
    string $packageRoot,
): array {
    $packageRoot = RepositoryContext::normalizePath($packageRoot);
    $relativePath = $repository->relativeToRepo($packageRoot);

    if (
        preg_match(
            '~\Avar/tmp/new-package-[a-f0-9]{16}/packages/([a-z0-9]+(?:[._-][a-z0-9]+)*)/([a-z0-9]+(?:[._-][a-z0-9]+)*)\z~',
            $relativePath,
            $matches,
        ) !== 1
    ) {
        throw new RuntimeException('package-scaffold-candidate-path-invalid');
    }

    $layer = $matches[1];
    $slug = $matches[2];

    if (!WorkspacePackageCatalog::isLayeredRoot($layer)) {
        throw new RuntimeException('package-scaffold-candidate-not-layered');
    }

    coretsia_sync_package_scaffold_assert_safe_path(
        $packageRoot,
        'composer.json',
    );

    $manifest = ComposerJson::readObject($packageRoot . '/composer.json');
    $composerName = $manifest['name'] ?? null;

    if (
        !is_string($composerName)
        || preg_match(
            '~\Acoretsia/[a-z0-9]+(?:[._-][a-z0-9]+)*\z~',
            $composerName,
        ) !== 1
    ) {
        throw new RuntimeException('package-scaffold-candidate-composer-name-invalid');
    }

    return [
        'relativePath' => 'packages/' . $layer . '/' . $slug,
        'absolutePath' => $packageRoot,
        'layer' => $layer,
        'slug' => $slug,
        'packageId' => $layer . '/' . $slug,
    ];
}

/**
 * @param array<string,string> $canonicalPackageFiles
 *
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

    if (!\is_dir($packageRoot . '/src')) {
        if ($check) {
            $diagnostics[] = $relativeRoot . '/src: missing-scaffold-directory';
        } else {
            coretsia_sync_package_scaffold_ensure_dir($packageRoot . '/src');
        }
    }

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
    $composer = ComposerJson::readObject($packageRoot . '/composer.json');
    $kind = $composer['extra']['coretsia']['kind'] ?? null;

    return is_string($kind) ? $kind : null;
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

function coretsia_sync_package_scaffold_assert_safe_path(
    string $packageRoot,
    string $relativePath,
): void {
    $packageRoot = rtrim(
        RepositoryContext::normalizePath($packageRoot),
        '/',
    );
    $relativePath = trim(
        str_replace('\\', '/', $relativePath),
        '/',
    );

    if ($relativePath === '') {
        throw new RuntimeException('package-scaffold-path-invalid');
    }

    $current = $packageRoot;

    foreach (explode('/', $relativePath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            throw new RuntimeException('package-scaffold-path-invalid');
        }

        $current .= '/' . $segment;

        if (is_link($current)) {
            throw new RuntimeException('package-scaffold-symlink-not-allowed');
        }

        if (!file_exists($current)) {
            return;
        }
    }
}

function coretsia_sync_package_scaffold_read_file(string $path): string
{
    return DeterministicFile::readBytesExact($path);
}

function coretsia_sync_package_scaffold_write_file_exact(
    string $path,
    string $contents,
): void {
    DeterministicFile::writeBytesExact($path, $contents);
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

/**
 * @param list<string> $values
 *
 * @return list<string>
 */
function coretsia_sync_package_scaffold_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}
