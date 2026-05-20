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

(static function (): void {
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
                'CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED',
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

    $fallbackViolation = 'CORETSIA_PACKAGE_PUBLISH_SAFETY_VIOLATION';
    $fallbackScanFailed = 'CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED';

    if (!\is_file($bootstrap) || !\is_readable($bootstrap)) {
        if (\is_file($consoleFile) && \is_readable($consoleFile)) {
            require_once $consoleFile;

            $code = $fallbackScanFailed;
            if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
                require_once $errorCodesFile;

                $name = $ErrorCodes . '::CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED';
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

    // NOTE (cemented): if bootstrap exists but terminates the process, its output is authoritative.
    require_once $bootstrap;

    if (\is_file($consoleFile) && \is_readable($consoleFile)) {
        require_once $consoleFile;
    }
    if (\is_file($errorCodesFile) && \is_readable($errorCodesFile)) {
        require_once $errorCodesFile;
    }

    $codeViolation = (static function () use ($ErrorCodes, $fallbackViolation): string {
        $name = $ErrorCodes . '::CORETSIA_PACKAGE_PUBLISH_SAFETY_VIOLATION';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackViolation;
    })();

    $codeScanFailed = (static function () use ($ErrorCodes, $fallbackScanFailed): string {
        $name = $ErrorCodes . '::CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED';
        if (\defined($name)) {
            /** @var string $v */
            $v = \constant($name);
            return $v;
        }

        return $fallbackScanFailed;
    })();

    try {
        $repoRoot = $withSuppressedErrors(static function (): ?string {
            $p = \realpath(__DIR__ . '/..' . '/..' . '/..');
            return \is_string($p) ? $p : null;
        });

        if ($repoRoot === null || $repoRoot === '') {
            throw new \RuntimeException('repo-root-invalid');
        }

        $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

        $releaseLinePath = $repoRoot . '/framework/tools/release/release-line.json';
        $splitPublishPath = $repoRoot . '/.github/split-publish-packages.json';
        $packagesRoot = $repoRoot . '/framework/packages';

        $releaseLine = coretsia_package_publish_safety_gate_load_release_line($releaseLinePath, $repoRoot);
        $allowlistedPackageIds = coretsia_package_publish_safety_gate_load_split_publish_allowlist(
            $splitPublishPath,
            $repoRoot,
        );

        $workspacePackagesByName = coretsia_package_publish_safety_gate_discover_workspace_packages_by_name(
            $packagesRoot,
            $repoRoot,
        );

        /** @var array<string, true> $allowlistedLookup */
        $allowlistedLookup = [];
        foreach ($allowlistedPackageIds as $packageId) {
            $allowlistedLookup[$packageId] = true;
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach ($allowlistedPackageIds as $packageId) {
            foreach (
                coretsia_package_publish_safety_gate_validate_allowlisted_package(
                    $packageId,
                    $repoRoot,
                    $releaseLine['publicConstraint'],
                    $allowlistedLookup,
                    $workspacePackagesByName,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }

        $violations = coretsia_package_publish_safety_gate_unique_sorted($violations);

        if ($violations === []) {
            exit(0);
        }

        $ConsoleOutput::codeWithDiagnostics($codeViolation, $violations);
        exit(1);
    } catch (\Throwable) {
        if (\class_exists($ConsoleOutput)) {
            $ConsoleOutput::codeWithDiagnostics($codeScanFailed, []);
        }

        exit(1);
    }
})();

/**
 * @return array{currentMinor:string,devVersion:string,publicConstraint:string}
 */
function coretsia_package_publish_safety_gate_load_release_line(string $path, string $repoRoot): array
{
    $data = coretsia_package_publish_safety_gate_read_json_object($path, $repoRoot);

    $schemaVersion = $data['schemaVersion'] ?? null;
    $currentMinor = $data['currentMinor'] ?? null;
    $devVersion = $data['devVersion'] ?? null;
    $publicConstraint = $data['publicConstraint'] ?? null;

    if ($schemaVersion !== 'coretsia.releaseLine.v1') {
        throw new \RuntimeException('release-line-schema-version-invalid');
    }

    if (!\is_string($currentMinor) || \preg_match('~\A[0-9]+\.[0-9]+\z~', $currentMinor) !== 1) {
        throw new \RuntimeException('release-line-current-minor-invalid');
    }

    if (!\is_string($devVersion) || $devVersion !== $currentMinor . '.x-dev') {
        throw new \RuntimeException('release-line-dev-version-invalid');
    }

    if (!\is_string($publicConstraint) || $publicConstraint !== '^' . $currentMinor . '.0') {
        throw new \RuntimeException('release-line-public-constraint-invalid');
    }

    return [
        'currentMinor' => $currentMinor,
        'devVersion' => $devVersion,
        'publicConstraint' => $publicConstraint,
    ];
}

/**
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_load_split_publish_allowlist(string $path, string $repoRoot): array
{
    $data = coretsia_package_publish_safety_gate_read_json_object($path, $repoRoot);

    $schemaVersion = $data['schemaVersion'] ?? null;
    if ($schemaVersion !== 'coretsia.splitPublishPackages.v1') {
        throw new \RuntimeException('split-publish-schema-version-invalid');
    }

    $packages = $data['packages'] ?? null;
    if (!\is_array($packages) || !\array_is_list($packages)) {
        throw new \RuntimeException('split-publish-packages-invalid');
    }

    /** @var list<string> $packageIds */
    $packageIds = [];

    foreach ($packages as $row) {
        if (!\is_array($row) || \array_is_list($row)) {
            throw new \RuntimeException('split-publish-package-row-invalid');
        }

        $packageId = $row['package_id'] ?? null;
        if (!\is_string($packageId) || !coretsia_package_publish_safety_gate_is_valid_package_id($packageId)) {
            throw new \RuntimeException('split-publish-package-id-invalid');
        }

        $packageIds[] = $packageId;
    }

    $unique = \array_values(\array_unique($packageIds));
    if (\count($unique) !== \count($packageIds)) {
        throw new \RuntimeException('split-publish-package-id-duplicate');
    }

    \sort($packageIds, \SORT_STRING);

    return $packageIds;
}

/**
 * @return array<string, string>
 */
function coretsia_package_publish_safety_gate_discover_workspace_packages_by_name(
    string $packagesRoot,
    string $repoRoot,
): array {
    if (!\is_dir($packagesRoot)) {
        throw new \RuntimeException('packages-root-missing');
    }

    $packagesRootReal = \realpath($packagesRoot);
    if ($packagesRootReal === false) {
        throw new \RuntimeException('packages-root-unresolvable');
    }

    $packagesRootReal = \rtrim(\str_replace('\\', '/', $packagesRootReal), '/');

    $hits = \glob($packagesRootReal . '/*/*/composer.json', \GLOB_NOSORT);
    if ($hits === false) {
        $hits = [];
    }

    $hits = \array_values(
        \array_map(
            static fn (string $path): string => \str_replace('\\', '/', $path),
            $hits,
        ),
    );
    \sort($hits, \SORT_STRING);

    /** @var array<string, string> $byName */
    $byName = [];

    foreach ($hits as $composerJsonPath) {
        if (!\is_file($composerJsonPath)) {
            continue;
        }

        $relative = coretsia_package_publish_safety_gate_rel_from_root($composerJsonPath, $packagesRootReal);
        $parts = \explode('/', $relative);

        if (\count($parts) !== 3 || $parts[2] !== 'composer.json') {
            throw new \RuntimeException('workspace-package-composer-path-invalid');
        }

        [$layer, $slug] = [$parts[0], $parts[1]];

        if (!coretsia_package_publish_safety_gate_is_valid_layer($layer)) {
            throw new \RuntimeException('workspace-package-layer-invalid');
        }

        if (!coretsia_package_publish_safety_gate_is_valid_slug($slug)) {
            throw new \RuntimeException('workspace-package-slug-invalid');
        }

        $packageId = $layer . '/' . $slug;
        $expectedName = 'coretsia/' . $layer . '-' . $slug;

        $composer = coretsia_package_publish_safety_gate_read_json_object($composerJsonPath, $repoRoot);
        $actualName = $composer['name'] ?? null;

        if ($actualName === $expectedName) {
            $byName[$expectedName] = $packageId;
        }
    }

    \ksort($byName, \SORT_STRING);

    return $byName;
}

/**
 * @param array<string, true> $allowlistedLookup
 * @param array<string, string> $workspacePackagesByName
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_validate_allowlisted_package(
    string $packageId,
    string $repoRoot,
    string $publicConstraint,
    array $allowlistedLookup,
    array $workspacePackagesByName,
): array {
    [$layer, $slug] = \explode('/', $packageId, 2);

    $composerJsonPath = $repoRoot . '/framework/packages/' . $layer . '/' . $slug . '/composer.json';
    $repoRelPath = 'framework/packages/' . $layer . '/' . $slug . '/composer.json';

    /** @var list<string> $violations */
    $violations = [];

    if (!\is_file($composerJsonPath) || !\is_readable($composerJsonPath)) {
        return [$repoRelPath . ': package-composer-missing'];
    }

    $data = coretsia_package_publish_safety_gate_read_json_object($composerJsonPath, $repoRoot);

    $expectedName = 'coretsia/' . $layer . '-' . $slug;
    $actualName = $data['name'] ?? null;

    if ($actualName !== $expectedName) {
        $violations[] = $repoRelPath . ': package-name-mismatch';
    }

    $type = $data['type'] ?? null;
    if ($type !== 'library') {
        $violations[] = $repoRelPath . ': package-type-not-library';
    }

    if (\array_key_exists('version', $data)) {
        $violations[] = $repoRelPath . ': package-version-field-forbidden';
    }

    foreach (['require', 'require-dev'] as $sectionName) {
        if (!\array_key_exists($sectionName, $data)) {
            continue;
        }

        $section = $data[$sectionName];

        if (!\is_array($section) || \array_is_list($section)) {
            $violations[] = $repoRelPath . ': ' . $sectionName . '-section-invalid';
            continue;
        }

        foreach ($section as $dependencyName => $constraint) {
            if (!\is_string($dependencyName) || $dependencyName === '') {
                $violations[] = $repoRelPath . ': ' . $sectionName . '-dependency-name-invalid';
                continue;
            }

            if (!\is_string($constraint) || $constraint === '') {
                $violations[] = $repoRelPath . ': ' . $sectionName . '.' . $dependencyName . ' dependency-constraint-invalid';
                continue;
            }

            if (!\str_starts_with($dependencyName, 'coretsia/')) {
                continue;
            }

            foreach (
                coretsia_package_publish_safety_gate_validate_internal_constraint(
                    $repoRelPath,
                    $sectionName,
                    $dependencyName,
                    $constraint,
                    $publicConstraint,
                    $allowlistedLookup,
                    $workspacePackagesByName,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }
    }

    return coretsia_package_publish_safety_gate_unique_sorted($violations);
}

/**
 * @param array<string, true> $allowlistedLookup
 * @param array<string, string> $workspacePackagesByName
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_validate_internal_constraint(
    string $repoRelPath,
    string $sectionName,
    string $dependencyName,
    string $constraint,
    string $publicConstraint,
    array $allowlistedLookup,
    array $workspacePackagesByName,
): array {
    $prefix = $repoRelPath . ': ' . $sectionName . '.' . $dependencyName;
    $normalizedConstraint = \trim($constraint);
    $lowerConstraint = \strtolower($normalizedConstraint);

    /** @var list<string> $violations */
    $violations = [];

    if ($normalizedConstraint === 'dev-main') {
        $violations[] = $prefix . ' internal-dev-main-forbidden';
    }

    if ($normalizedConstraint === '*') {
        $violations[] = $prefix . ' internal-wildcard-forbidden';
    }

    if (\str_contains($lowerConstraint, '@dev')) {
        $violations[] = $prefix . ' internal-dev-stability-forbidden';
    }

    if (coretsia_package_publish_safety_gate_is_exact_semver_pin($normalizedConstraint)) {
        $violations[] = $prefix . ' internal-exact-semver-pin-forbidden';
    }

    if ($normalizedConstraint !== $publicConstraint) {
        $violations[] = $prefix . ' internal-constraint-not-release-line-public-constraint';
    }

    $dependencyPackageId = $workspacePackagesByName[$dependencyName] ?? null;
    if (!\is_string($dependencyPackageId) || !isset($allowlistedLookup[$dependencyPackageId])) {
        $violations[] = $prefix . ' internal-dependency-not-split-publish-allowlisted';
    }

    return coretsia_package_publish_safety_gate_unique_sorted($violations);
}

function coretsia_package_publish_safety_gate_is_exact_semver_pin(string $constraint): bool
{
    return \preg_match(
        '~\A={0,2}\s*v?[0-9]+\.[0-9]+\.[0-9]+(?:-[A-Za-z0-9_.-]+)?(?:\+[A-Za-z0-9_.-]+)?\z~',
        $constraint,
    ) === 1;
}

function coretsia_package_publish_safety_gate_is_valid_package_id(string $packageId): bool
{
    if (\preg_match('~\A([a-z][a-z0-9-]*)/([a-z0-9][a-z0-9-]*)\z~', $packageId, $m) !== 1) {
        return false;
    }

    return coretsia_package_publish_safety_gate_is_valid_layer($m[1])
        && coretsia_package_publish_safety_gate_is_valid_slug($m[2]);
}

function coretsia_package_publish_safety_gate_is_valid_layer(string $layer): bool
{
    return \preg_match('~\A[a-z][a-z0-9-]*\z~', $layer) === 1;
}

function coretsia_package_publish_safety_gate_is_valid_slug(string $slug): bool
{
    return \preg_match('~\A[a-z0-9][a-z0-9-]*\z~', $slug) === 1;
}

/**
 * @return array<string, mixed>
 */
function coretsia_package_publish_safety_gate_read_json_object(string $path, string $repoRoot): array
{
    $raw = coretsia_package_publish_safety_gate_read_file($path);

    if (\str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = \substr($raw, 3);
    }

    $data = \json_decode(coretsia_package_publish_safety_gate_normalize_eol($raw), true);

    if (!\is_array($data) || \array_is_list($data)) {
        throw new \RuntimeException(
            'json-object-invalid: ' . coretsia_package_publish_safety_gate_rel_from_repo($path, $repoRoot),
        );
    }

    return $data;
}

function coretsia_package_publish_safety_gate_read_file(string $path): string
{
    \set_error_handler(static function (): bool {
        return true;
    });

    try {
        $content = \file_get_contents($path);
    } finally {
        \restore_error_handler();
    }

    if (!\is_string($content)) {
        throw new \RuntimeException('file-read-failed');
    }

    return $content;
}

function coretsia_package_publish_safety_gate_normalize_eol(string $s): string
{
    return \str_replace(["\r\n", "\r"], "\n", $s);
}

/**
 * @param list<string> $values
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}

function coretsia_package_publish_safety_gate_rel_from_repo(string $absPath, string $repoRoot): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $repoRoot = \rtrim(\str_replace('\\', '/', $repoRoot), '/');

    if ($absPath === $repoRoot) {
        return '.';
    }

    if (!\str_starts_with($absPath, $repoRoot . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($repoRoot) + 1);
}

function coretsia_package_publish_safety_gate_rel_from_root(string $absPath, string $root): string
{
    $absPath = \rtrim(\str_replace('\\', '/', $absPath), '/');
    $root = \rtrim(\str_replace('\\', '/', $root), '/');

    if ($absPath === $root) {
        return '.';
    }

    if (!\str_starts_with($absPath, $root . '/')) {
        return 'UNKNOWN_PATH';
    }

    return \substr($absPath, \strlen($root) + 1);
}
