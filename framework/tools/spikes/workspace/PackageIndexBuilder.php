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

namespace Coretsia\Tools\Spikes\workspace;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\DeterministicFile;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

final class PackageIndexBuilder
{
    private function __construct()
    {
    }

    /**
     * Build deterministic package-index from a repo root by scanning:
     * - framework/packages/*\/*\/composer.json
     *
     * Output entry shape (cemented key insertion order):
     * - slug, layer, path, composerName, psr4, kind, moduleClass (if present)
     *
     * Stable ordering (single-choice):
     * - entries sorted by normalized path ascending using strcmp
     *
     * @param string $repoRoot
     * @return list<array{
     *   slug:string,
     *   layer:string,
     *   path:string,
     *   composerName:string,
     *   psr4:array<string,string>,
     *   kind:string,
     *   moduleClass?:string
     * }>
     *
     * @throws DeterministicException
     */
    public static function build(string $repoRoot): array
    {
        $repoRoot = self::normalizePath($repoRoot);
        $packagesRoot = self::joinPath($repoRoot, 'framework/packages');

        if (!\is_dir($packagesRoot)) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID);
        }

        $entries = [];

        $layers = self::listChildDirectoriesSorted($packagesRoot);
        foreach ($layers as $layer) {
            $layerPath = self::joinPath($packagesRoot, $layer);

            $slugs = self::listChildDirectoriesSorted($layerPath);
            foreach ($slugs as $slug) {
                $packageDir = self::joinPath($layerPath, $slug);
                $composerJsonPath = self::joinPath($packageDir, 'composer.json');

                // Scan pattern is framework/packages/*/*/composer.json => only include where the file exists.
                if (!\is_file($composerJsonPath)) {
                    continue;
                }

                $composerJson = self::readAndDecodeComposerJson($composerJsonPath);

                $composerName = self::requireStringSchema($composerJson, 'name');
                $psr4 = self::extractPsr4Schema($composerJson);
                [$kind, $moduleClass] = self::extractCoretsiaExtraSchema($composerJson);

                // Emit path relative to framework root (no leading "framework/"), normalized to forward slashes.
                $relativePath = 'packages/' . $layer . '/' . $slug;

                // Entry key insertion order is cemented (single-choice).
                $entry = [];
                $entry['slug'] = $slug;
                $entry['layer'] = $layer;
                $entry['path'] = $relativePath;
                $entry['composerName'] = $composerName;
                $entry['psr4'] = $psr4;
                $entry['kind'] = $kind;
                if ($moduleClass !== null) {
                    $entry['moduleClass'] = $moduleClass;
                }

                $entries[] = $entry;
            }
        }

        \usort(
            $entries,
            static function (array $a, array $b): int {
                $pa = self::normalizePath((string) $a['path']);
                $pb = self::normalizePath((string) $b['path']);

                $c = \strcmp($pa, $pb);
                if ($c !== 0) {
                    return $c;
                }

                // Defensive deterministic tie-breakers (should never trigger for unique paths).
                $c = \strcmp((string) $a['layer'], (string) $b['layer']);
                if ($c !== 0) {
                    return $c;
                }

                $c = \strcmp((string) $a['slug'], (string) $b['slug']);
                if ($c !== 0) {
                    return $c;
                }

                return \strcmp((string) $a['composerName'], (string) $b['composerName']);
            }
        );

        /** @var list<array{slug:string,layer:string,path:string,composerName:string,psr4:array<string,string>,kind:string,moduleClass?:string}> $entries */
        return \array_values($entries);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws DeterministicException
     */
    private static function readAndDecodeComposerJson(string $composerJsonPath): array
    {
        try {
            $bytes = DeterministicFile::readBytesExact($composerJsonPath);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_IO_READ_FAILED, $e);
        }

        try {
            $decoded = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED, $e);
        }

        // composer.json MUST be an object; reject scalars and top-level lists deterministically.
        if (!\is_array($decoded) || \array_is_list($decoded)) {
            self::fail(ErrorCodes::CORETSIA_WORKSPACE_COMPOSER_JSON_PARSE_FAILED);
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<string, string>
     *
     * @throws DeterministicException
     */
    private static function extractPsr4Schema(array $composerJson): array
    {
        if (!\array_key_exists('autoload', $composerJson) || !\is_array($composerJson['autoload'])) {
            self::failPackageComposerSchemaInvalid();
        }

        /** @var array<string, mixed> $autoload */
        $autoload = $composerJson['autoload'];

        if (
            !\array_key_exists('psr-4', $autoload)
            || !\is_array($autoload['psr-4'])
            || \array_is_list($autoload['psr-4'])
        ) {
            self::failPackageComposerSchemaInvalid();
        }

        /** @var array<string, mixed> $psr4Raw */
        $psr4Raw = $autoload['psr-4'];

        $psr4 = [];
        foreach ($psr4Raw as $k => $v) {
            if (!\is_string($k) || !\is_string($v)) {
                self::failPackageComposerSchemaInvalid();
            }
            $psr4[$k] = $v;
        }

        return $psr4;
    }

    /**
     * @return array{0:string,1:string|null}
     *
     * @throws DeterministicException
     */
    private static function extractCoretsiaExtraSchema(array $composerJson): array
    {
        if (!\array_key_exists('extra', $composerJson) || !\is_array($composerJson['extra'])) {
            self::failPackageComposerSchemaInvalid();
        }

        /** @var array<string, mixed> $extra */
        $extra = $composerJson['extra'];

        if (!\array_key_exists('coretsia', $extra) || !\is_array($extra['coretsia'])) {
            self::failPackageComposerSchemaInvalid();
        }

        /** @var array<string, mixed> $coretsia */
        $coretsia = $extra['coretsia'];

        if (!\array_key_exists('kind', $coretsia) || !\is_string($coretsia['kind']) || $coretsia['kind'] === '') {
            self::failPackageComposerSchemaInvalid();
        }

        $kind = (string) $coretsia['kind'];
        $moduleClass = null;

        if (\array_key_exists('moduleClass', $coretsia)) {
            if (!\is_string($coretsia['moduleClass']) || $coretsia['moduleClass'] === '') {
                self::failPackageComposerSchemaInvalid();
            }
            $moduleClass = (string) $coretsia['moduleClass'];
        }

        return [$kind, $moduleClass];
    }

    /**
     * @throws DeterministicException
     */
    private static function requireStringSchema(array $map, string $key): string
    {
        if (!\array_key_exists($key, $map) || !\is_string($map[$key]) || $map[$key] === '') {
            self::failPackageComposerSchemaInvalid();
        }

        return (string) $map[$key];
    }

    /**
     * @return list<string>
     *
     * @throws DeterministicException
     */
    private static function listChildDirectoriesSorted(string $path): array
    {
        $out = [];

        try {
            $it = new \DirectoryIterator($path);
        } catch (\Throwable $e) {
            self::fail(ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID, $e);
        }

        foreach ($it as $fi) {
            if ($fi->isDot()) {
                continue;
            }
            if (!$fi->isDir()) {
                continue;
            }

            $name = $fi->getFilename();
            if ($name === '' || $name === '.' || $name === '..') {
                continue;
            }

            $out[] = $name;
        }

        \usort($out, static fn(string $a, string $b): int => \strcmp($a, $b));

        return \array_values($out);
    }

    private static function joinPath(string $base, string $rel): string
    {
        $b = \rtrim(self::normalizePath($base), '/');
        $r = \ltrim(self::normalizePath($rel), '/');

        return $r === '' ? $b : ($b . '/' . $r);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }

    /**
     * @throws DeterministicException
     */
    private static function failPackageComposerSchemaInvalid(): never
    {
        self::fail(ErrorCodes::CORETSIA_WORKSPACE_PACKAGE_COMPOSER_SCHEMA_INVALID);
    }

    /**
     * @throws DeterministicException
     */
    private static function fail(string $code, ?\Throwable $previous = null): never
    {
        throw new DeterministicException($code, $code, $previous);
    }
}
