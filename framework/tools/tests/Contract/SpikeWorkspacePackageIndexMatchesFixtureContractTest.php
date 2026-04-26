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

namespace Coretsia\Tools\Tests\Contract;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;
use DirectoryIterator;
use RuntimeException;

final class SpikeWorkspacePackageIndexMatchesFixtureContractTest extends ToolContractTestCase
{
    public function testWorkspacePackageIndexMatchesPromotedSpikeFixture(): void
    {
        $workspaceRoot = $this->spikeFixturePath('workspace_min');

        self::assertDirectoryExists($workspaceRoot);

        $expected = $this->requireArrayFixture('workspace_min/expected_package_index.php');
        $actual = $this->buildWorkspacePackageIndex($workspaceRoot);

        self::assertSame($expected, $actual);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function buildWorkspacePackageIndex(string $workspaceRoot): array
    {
        $packagesRoot = rtrim(str_replace('\\', '/', $workspaceRoot), '/') . '/framework/packages';

        if (!is_dir($packagesRoot)) {
            throw new RuntimeException('Missing fixture packages root.');
        }

        $entries = [];

        foreach ($this->childDirectories($packagesRoot) as $layer) {
            foreach ($this->childDirectories($packagesRoot . '/' . $layer) as $slug) {
                $packageRoot = $packagesRoot . '/' . $layer . '/' . $slug;
                $composerPath = $packageRoot . '/composer.json';

                if (!is_file($composerPath)) {
                    continue;
                }

                $composer = json_decode($this->readBytes($composerPath), true, 512, JSON_THROW_ON_ERROR);

                if (!is_array($composer)) {
                    throw new RuntimeException('Invalid composer fixture: ' . $composerPath);
                }

                $entry = [];
                $entry['slug'] = $slug;
                $entry['layer'] = $layer;
                $entry['path'] = 'packages/' . $layer . '/' . $slug;
                $entry['composerName'] = $this->stringField($composer, 'name');
                $entry['psr4'] = $this->psr4($composer);
                $entry['kind'] = $this->coretsiaKind($composer);

                $moduleClass = $this->coretsiaModuleClass($composer);
                if ($moduleClass !== null) {
                    $entry['moduleClass'] = $moduleClass;
                }

                $entries[] = $entry;
            }
        }

        usort(
            $entries,
            static fn (array $a, array $b): int => strcmp((string)$a['path'], (string)$b['path']),
        );

        return array_values($entries);
    }

    /**
     * @return list<string>
     */
    private function childDirectories(string $path): array
    {
        $out = [];

        foreach (new DirectoryIterator($path) as $item) {
            if ($item->isDot() || !$item->isDir()) {
                continue;
            }

            $out[] = $item->getFilename();
        }

        sort($out, SORT_STRING);

        return array_values($out);
    }

    /**
     * @param array<mixed> $composer
     */
    private function stringField(array $composer, string $field): string
    {
        $value = $composer[$field] ?? null;

        if (!is_string($value) || $value === '') {
            throw new RuntimeException('Invalid composer fixture string field: ' . $field);
        }

        return $value;
    }

    /**
     * @param array<mixed> $composer
     * @return array<string,string>
     */
    private function psr4(array $composer): array
    {
        $autoload = $composer['autoload'] ?? null;

        if (!is_array($autoload)) {
            throw new RuntimeException('Invalid composer fixture autoload.');
        }

        $psr4 = $autoload['psr-4'] ?? null;

        if (!is_array($psr4) || array_is_list($psr4)) {
            throw new RuntimeException('Invalid composer fixture psr-4.');
        }

        $out = [];
        foreach ($psr4 as $prefix => $path) {
            if (!is_string($prefix) || !is_string($path)) {
                throw new RuntimeException('Invalid composer fixture psr-4 entry.');
            }

            $out[$prefix] = $path;
        }

        return $out;
    }

    /**
     * @param array<mixed> $composer
     */
    private function coretsiaKind(array $composer): string
    {
        $coretsia = $this->coretsiaExtra($composer);
        $kind = $coretsia['kind'] ?? null;

        if (!is_string($kind) || $kind === '') {
            throw new RuntimeException('Invalid composer fixture extra.coretsia.kind.');
        }

        return $kind;
    }

    /**
     * @param array<mixed> $composer
     */
    private function coretsiaModuleClass(array $composer): ?string
    {
        $coretsia = $this->coretsiaExtra($composer);
        $moduleClass = $coretsia['moduleClass'] ?? null;

        if ($moduleClass === null) {
            return null;
        }

        if (!is_string($moduleClass) || $moduleClass === '') {
            throw new RuntimeException('Invalid composer fixture extra.coretsia.moduleClass.');
        }

        return $moduleClass;
    }

    /**
     * @param array<mixed> $composer
     * @return array<mixed>
     */
    private function coretsiaExtra(array $composer): array
    {
        $extra = $composer['extra'] ?? null;

        if (!is_array($extra)) {
            throw new RuntimeException('Invalid composer fixture extra.');
        }

        $coretsia = $extra['coretsia'] ?? null;

        if (!is_array($coretsia)) {
            throw new RuntimeException('Invalid composer fixture extra.coretsia.');
        }

        return $coretsia;
    }
}
