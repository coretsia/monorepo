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
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\ReleaseLine;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';
require_once __DIR__ . '/../support/ComposerJson.php';
require_once __DIR__ . '/../support/ReleaseLine.php';
require_once __DIR__ . '/../support/WorkspacePackageCatalog.php';
require_once __DIR__ . '/../support/GateRuntime.php';

exit(GateRuntime::execute(
    ErrorCodes::CORETSIA_PACKAGE_PUBLISH_SAFETY_VIOLATION,
    ErrorCodes::CORETSIA_PACKAGE_PUBLISH_SAFETY_GATE_FAILED,
    static function (RepositoryContext $repository): array {
        $releaseLine = ReleaseLine::load($repository);
        $catalog = WorkspacePackageCatalog::discover($repository);

        $publishIds = coretsia_package_publish_safety_gate_load_split_publish_allowlist(
            $repository->resolveExistingFile('.github/split-publish-packages.json'),
        );

        $productsByPublishId = coretsia_package_publish_safety_gate_products_by_publish_id($catalog);

        /** @var list<array{
         *     kind:string,
         *     relativePath:string,
         *     absolutePath:string,
         *     composerJsonPath:string,
         *     composerName:string,
         *     layer:string|null,
         *     slug:string|null,
         *     packageId:string|null
         * }> $allowlistedProducts
         */
        $allowlistedProducts = [];

        /** @var array<string,true> $allowlistedComposerNames */
        $allowlistedComposerNames = [];

        foreach ($publishIds as $publishId) {
            $product = $productsByPublishId[$publishId] ?? null;

            if (!is_array($product)) {
                throw new RuntimeException('split-publish-package-not-in-catalog');
            }

            $allowlistedProducts[] = $product;
            $allowlistedComposerNames[$product['composerName']] = true;
        }

        /** @var list<string> $violations */
        $violations = [];

        foreach ($allowlistedProducts as $product) {
            foreach (
                coretsia_package_publish_safety_gate_validate_allowlisted_product(
                    $product,
                    $releaseLine->publicConstraint(),
                    $allowlistedComposerNames,
                    $catalog,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }

        return coretsia_package_publish_safety_gate_unique_sorted($violations);
    },
));

/**
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_load_split_publish_allowlist(
    string $path,
): array {
    $raw = DeterministicFile::readTextNormalizedEol($path);

    if (str_starts_with($raw, "\xEF\xBB\xBF")) {
        $raw = substr($raw, 3);
    }

    try {
        $data = json_decode(
            $raw,
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    } catch (\JsonException) {
        throw new RuntimeException('split-publish-json-invalid');
    }

    if (!is_array($data) || array_is_list($data)) {
        throw new RuntimeException('split-publish-json-object-invalid');
    }

    if (
        ($data['schemaVersion'] ?? null)
        !== 'coretsia.splitPublishPackages.v1'
    ) {
        throw new RuntimeException('split-publish-schema-version-invalid');
    }

    $packages = $data['packages'] ?? null;

    if (!is_array($packages) || !array_is_list($packages)) {
        throw new RuntimeException('split-publish-packages-invalid');
    }

    /** @var list<string> $publishIds */
    $publishIds = [];

    foreach ($packages as $row) {
        if (!is_array($row) || array_is_list($row)) {
            throw new RuntimeException('split-publish-package-row-invalid');
        }

        $publishId = $row['package_id'] ?? null;

        if (!is_string($publishId) || $publishId === '') {
            throw new RuntimeException('split-publish-package-id-invalid');
        }

        $publishIds[] = $publishId;
    }

    if (
        count(array_unique($publishIds))
        !== count($publishIds)
    ) {
        throw new RuntimeException('split-publish-package-id-duplicate');
    }

    sort($publishIds, SORT_STRING);

    return $publishIds;
}

/**
 * @return array<string,array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string|null,
 *     slug:string|null,
 *     packageId:string|null
 * }>
 */
function coretsia_package_publish_safety_gate_products_by_publish_id(
    WorkspacePackageCatalog $catalog,
): array {
    $products = [];

    foreach ($catalog->all() as $product) {
        $publishId = coretsia_package_publish_safety_gate_publish_id($product);

        if (isset($products[$publishId])) {
            throw new RuntimeException('split-publish-package-id-collision');
        }

        $products[$publishId] = $product;
    }

    ksort($products, SORT_STRING);

    return $products;
}

/**
 * @param array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string|null,
 *     slug:string|null,
 *     packageId:string|null
 * } $product
 */
function coretsia_package_publish_safety_gate_publish_id(
    array $product,
): string {
    if ($product['packageId'] !== null) {
        return $product['packageId'];
    }

    if (
        $product['kind']
        !== WorkspacePackageCatalog::KIND_SPECIAL_DISTRIBUTION
    ) {
        throw new RuntimeException('split-publish-product-kind-invalid');
    }

    return $product['composerName'];
}

/**
 * @param array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string|null,
 *     slug:string|null,
 *     packageId:string|null
 * } $product
 * @param array<string,true> $allowlistedComposerNames
 *
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_validate_allowlisted_product(
    array $product,
    string $publicConstraint,
    array $allowlistedComposerNames,
    WorkspacePackageCatalog $catalog,
): array {
    $repoRelPath = $product['relativePath'] . '/composer.json';
    $data = ComposerJson::readObject(
        $product['composerJsonPath'],
    );

    /** @var list<string> $violations */
    $violations = [];

    $expectedType = coretsia_package_publish_safety_gate_expected_type($product);

    if (($data['type'] ?? null) !== $expectedType) {
        $violations[] = $repoRelPath . ': package-type-not-' . $expectedType;
    }

    if (array_key_exists('version', $data)) {
        $violations[] = $repoRelPath . ': package-version-field-forbidden';
    }

    foreach (['require', 'require-dev'] as $sectionName) {
        if (!array_key_exists($sectionName, $data)) {
            continue;
        }

        $section = $data[$sectionName];

        if ($section instanceof \stdClass) {
            $section = get_object_vars($section);
        }

        if (!is_array($section) || array_is_list($section)) {
            $violations[] = $repoRelPath . ': ' . $sectionName . '-section-invalid';

            continue;
        }

        foreach ($section as $dependencyName => $constraint) {
            if (
                !is_string($dependencyName)
                || $dependencyName === ''
            ) {
                $violations[] = $repoRelPath . ': ' . $sectionName . '-dependency-name-invalid';

                continue;
            }

            if (!is_string($constraint) || $constraint === '') {
                $violations[] = $repoRelPath
                    . ': '
                    . $sectionName
                    . '.'
                    . $dependencyName
                    . ' dependency-constraint-invalid';

                continue;
            }

            if (!str_starts_with($dependencyName, 'coretsia/')) {
                continue;
            }

            foreach (
                coretsia_package_publish_safety_gate_validate_internal_constraint(
                    $repoRelPath,
                    $sectionName,
                    $dependencyName,
                    $constraint,
                    $publicConstraint,
                    $allowlistedComposerNames,
                    $catalog,
                ) as $violation
            ) {
                $violations[] = $violation;
            }
        }
    }

    return coretsia_package_publish_safety_gate_unique_sorted($violations);
}

/**
 * @param array{
 *     kind:string,
 *     relativePath:string,
 *     absolutePath:string,
 *     composerJsonPath:string,
 *     composerName:string,
 *     layer:string|null,
 *     slug:string|null,
 *     packageId:string|null
 * } $product
 */
function coretsia_package_publish_safety_gate_expected_type(
    array $product,
): string {
    if (
        $product['kind']
        === WorkspacePackageCatalog::KIND_LAYERED_PACKAGE
    ) {
        return 'library';
    }

    return match ($product['composerName']) {
        'coretsia/framework' => 'metapackage',
        'coretsia/skeleton' => 'project',
        default => throw new RuntimeException('special-distribution-type-policy-missing'),
    };
}

/**
 * @param array<string,true> $allowlistedComposerNames
 *
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_validate_internal_constraint(
    string $repoRelPath,
    string $sectionName,
    string $dependencyName,
    string $constraint,
    string $publicConstraint,
    array $allowlistedComposerNames,
    WorkspacePackageCatalog $catalog,
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

    if (
        $catalog->byComposerName($dependencyName) === null
        || !isset($allowlistedComposerNames[$dependencyName])
    ) {
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

/**
 * @param list<string> $values
 *
 * @return list<string>
 */
function coretsia_package_publish_safety_gate_unique_sorted(array $values): array
{
    $values = \array_values(\array_unique($values));
    \sort($values, \SORT_STRING);

    return $values;
}
