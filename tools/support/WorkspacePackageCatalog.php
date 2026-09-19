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

namespace Coretsia\Tools\Support;

/**
 * Tooling-only catalog of publishable Coretsia products under `packages/`.
 *
 * Composer identity always comes from composer.json. Special distributions
 * are classified explicitly and never receive fabricated layered package ids.
 */
final class WorkspacePackageCatalog
{
    public const string KIND_LAYERED_PACKAGE = 'layered-package';
    public const string KIND_SPECIAL_DISTRIBUTION = 'special-distribution';

    /**
     * @var list<string>
     */
    private const array LAYERED_ROOTS = [
        'core',
        'platform',
        'integrations',
        'enterprise',
        'devtools',
        'presets',
    ];

    /**
     * @var array<string,string>
     */
    private const array SPECIAL_DISTRIBUTIONS = [
        'packages/framework' => 'coretsia/framework',
        'packages/applications/skeleton' => 'coretsia/skeleton',
    ];

    /**
     * @var list<array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string|null,
     *     slug: string|null,
     *     packageId: string|null
     * }>
     */
    private array $products;

    /**
     * @var array<string,int>
     */
    private array $indexByComposerName;

    /**
     * @param list<array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string|null,
     *     slug: string|null,
     *     packageId: string|null
     * }> $products
     */
    private function __construct(array $products)
    {
        $this->products = $products;
        $this->indexByComposerName = [];

        foreach ($products as $index => $product) {
            $name = $product['composerName'];

            if (isset($this->indexByComposerName[$name])) {
                throw new \RuntimeException('workspace-package-composer-name-duplicate');
            }

            $this->indexByComposerName[$name] = $index;
        }
    }

    public static function discover(RepositoryContext $repository): self
    {
        $composerFiles = self::discoverComposerFiles($repository);
        $products = [];

        foreach ($composerFiles as $composerJsonPath) {
            $composerRelativePath = $repository->relativeToRepo($composerJsonPath);
            $relativePath = substr($composerRelativePath, 0, -strlen('/composer.json'));

            if ($relativePath === '' || $relativePath === $composerRelativePath) {
                throw new \RuntimeException('workspace-package-path-invalid');
            }

            $manifest = ComposerJson::readObject($composerJsonPath);
            $composerName = self::composerName($manifest);

            $classification = self::classify($relativePath, $composerName);

            $products[] = [
                'kind' => $classification['kind'],
                'relativePath' => $relativePath,
                'absolutePath' => $repository->resolveExistingDirectory($relativePath),
                'composerJsonPath' => $composerJsonPath,
                'composerName' => $composerName,
                'layer' => $classification['layer'],
                'slug' => $classification['slug'],
                'packageId' => $classification['packageId'],
            ];
        }

        usort(
            $products,
            static fn (array $left, array $right): int => strcmp($left['relativePath'], $right['relativePath']),
        );

        return new self($products);
    }

    /**
     * @return list<array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string|null,
     *     slug: string|null,
     *     packageId: string|null
     * }>
     */
    public function all(): array
    {
        return $this->products;
    }

    /**
     * @return list<string>
     */
    public static function layeredRoots(): array
    {
        return self::LAYERED_ROOTS;
    }

    public static function isLayeredRoot(string $layer): bool
    {
        return in_array($layer, self::LAYERED_ROOTS, true);
    }

    /**
     * @return list<array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string,
     *     slug: string,
     *     packageId: string
     * }>
     */
    public function layeredPackages(): array
    {
        $out = [];

        foreach ($this->products as $product) {
            if ($product['kind'] !== self::KIND_LAYERED_PACKAGE) {
                continue;
            }

            if (
                $product['layer'] === null
                || $product['slug'] === null
                || $product['packageId'] === null
            ) {
                throw new \LogicException('workspace-package-layered-classification-invalid');
            }

            /** @var array{
             *     kind: string,
             *     relativePath: string,
             *     absolutePath: string,
             *     composerJsonPath: string,
             *     composerName: string,
             *     layer: string,
             *     slug: string,
             *     packageId: string
             * } $product
             */
            $out[] = $product;
        }

        return $out;
    }

    /**
     * @return list<array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: null,
     *     slug: null,
     *     packageId: null
     * }>
     */
    public function specialDistributions(): array
    {
        $out = [];

        foreach ($this->products as $product) {
            if ($product['kind'] !== self::KIND_SPECIAL_DISTRIBUTION) {
                continue;
            }

            if ($product['layer'] !== null || $product['slug'] !== null || $product['packageId'] !== null) {
                throw new \LogicException('workspace-package-special-classification-invalid');
            }

            /** @var array{
             *     kind: string,
             *     relativePath: string,
             *     absolutePath: string,
             *     composerJsonPath: string,
             *     composerName: string,
             *     layer: null,
             *     slug: null,
             *     packageId: null
             * } $product
             */
            $out[] = $product;
        }

        return $out;
    }

    /**
     * @return array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string|null,
     *     slug: string|null,
     *     packageId: string|null
     * }|null
     */
    public function byComposerName(string $composerName): ?array
    {
        $index = $this->indexByComposerName[$composerName] ?? null;

        return $index === null ? null : $this->products[$index];
    }

    /**
     * @return array{
     *     kind: string,
     *     relativePath: string,
     *     absolutePath: string,
     *     composerJsonPath: string,
     *     composerName: string,
     *     layer: string,
     *     slug: string,
     *     packageId: string
     * }|null
     */
    public function byPackageId(string $packageId): ?array
    {
        foreach ($this->layeredPackages() as $product) {
            if ($product['packageId'] === $packageId) {
                return $product;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function discoverComposerFiles(RepositoryContext $repository): array
    {
        $files = [];

        foreach (array_keys(self::SPECIAL_DISTRIBUTIONS) as $relativePath) {
            $composerRelativePath = $relativePath . '/composer.json';
            $candidate = $repository->resolve($composerRelativePath);

            if (!is_file($candidate)) {
                continue;
            }

            $files[] = $repository->resolveExistingFile($composerRelativePath);
        }

        try {
            $layers = new \FilesystemIterator(
                $repository->packagesRoot(),
                \FilesystemIterator::SKIP_DOTS,
            );

            foreach ($layers as $layer) {
                if (!$layer instanceof \SplFileInfo || !$layer->isDir()) {
                    continue;
                }

                $products = new \FilesystemIterator(
                    $layer->getPathname(),
                    \FilesystemIterator::SKIP_DOTS,
                );

                foreach ($products as $product) {
                    if (!$product instanceof \SplFileInfo || !$product->isDir()) {
                        continue;
                    }

                    $composerJsonPath = $product->getPathname() . '/composer.json';
                    if (!is_file($composerJsonPath)) {
                        continue;
                    }

                    $relativePath = $repository->relativeToRepo($composerJsonPath);
                    $files[] = $repository->resolveExistingFile($relativePath);
                }
            }
        } catch (\UnexpectedValueException) {
            throw new \RuntimeException('workspace-package-discovery-failed');
        }

        $files = array_values(array_unique($files));
        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * @param array<string,mixed> $manifest
     */
    private static function composerName(array $manifest): string
    {
        $name = $manifest['name'] ?? null;

        if (
            !is_string($name)
            || preg_match('~\Acoretsia/[a-z0-9]+(?:[._-][a-z0-9]+)*\z~', $name) !== 1
        ) {
            throw new \RuntimeException('workspace-package-composer-name-invalid');
        }

        return $name;
    }

    /**
     * @return array{
     *     kind: string,
     *     layer: string|null,
     *     slug: string|null,
     *     packageId: string|null
     * }
     */
    private static function classify(string $relativePath, string $composerName): array
    {
        if (isset(self::SPECIAL_DISTRIBUTIONS[$relativePath])) {
            if ($composerName !== self::SPECIAL_DISTRIBUTIONS[$relativePath]) {
                throw new \RuntimeException('workspace-special-distribution-composer-name-invalid');
            }

            return [
                'kind' => self::KIND_SPECIAL_DISTRIBUTION,
                'layer' => null,
                'slug' => null,
                'packageId' => null,
            ];
        }

        if (
            preg_match(
                '~\Apackages/([a-z0-9]+(?:[._-][a-z0-9]+)*)/([a-z0-9]+(?:[._-][a-z0-9]+)*)\z~',
                $relativePath,
                $matches,
            ) !== 1
        ) {
            throw new \RuntimeException('workspace-package-path-unrecognized');
        }

        $layer = $matches[1];
        $slug = $matches[2];

        if (!self::isLayeredRoot($layer)) {
            throw new \RuntimeException('workspace-package-layer-invalid');
        }

        return [
            'kind' => self::KIND_LAYERED_PACKAGE,
            'layer' => $layer,
            'slug' => $slug,
            'packageId' => $layer . '/' . $slug,
        ];
    }
}
