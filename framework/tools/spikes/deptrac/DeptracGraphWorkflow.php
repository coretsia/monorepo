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

namespace Coretsia\Tools\Spikes\deptrac;

use Coretsia\Tools\Spikes\_support\DeterministicException;
use Coretsia\Tools\Spikes\_support\ErrorCodes;

/**
 * Tools-only workflow for deptrac graph artifact generation.
 *
 * Contract:
 * - accepts repo root + fixture/output relative inputs
 * - creates output directory deterministically
 * - delegates artifact generation to GraphArtifactBuilder
 * - returns structured result only
 * - never writes to stdout/stderr
 */
final class DeptracGraphWorkflow
{
    private const string DEFAULT_FIXTURE = 'deptrac_min/package_index_ok.php';

    private function __construct()
    {
    }

    /**
     * @return array{
     *   fixture:string,
     *   output_dir:string,
     *   files:list<string>
     * }
     *
     * @throws DeterministicException
     */
    public static function run(string $repoRootAbs, ?string $fixtureRelativePath, string $outputDirRelative): array
    {
        $repoRootAbs = self::normalizeRepoRoot($repoRootAbs);
        $fixtureRelativePath = self::normalizeFixtureRelativePath($fixtureRelativePath ?? self::DEFAULT_FIXTURE);
        $outputDirRelative = self::normalizeOutputDirRelative($outputDirRelative);

        $outputDirAbs = self::joinAbs($repoRootAbs, $outputDirRelative);

        self::ensureDirectoryExists($outputDirAbs);

        try {
            GraphArtifactBuilder::buildToDirFromFixture($fixtureRelativePath, $outputDirAbs);
        } catch (DeterministicException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'graph-artifact-build-failed',
                $e,
            );
        }

        $files = [
            $outputDirRelative . '/deptrac_graph.dot',
            $outputDirRelative . '/deptrac_graph.svg',
            $outputDirRelative . '/deptrac_graph.html',
        ];

        foreach ($files as $relFile) {
            $absFile = self::joinAbs($repoRootAbs, $relFile);
            if (!\is_file($absFile) || !\is_readable($absFile)) {
                throw new DeterministicException(
                    ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                    'graph-artifact-missing',
                );
            }
        }

        return [
            'fixture' => $fixtureRelativePath,
            'output_dir' => $outputDirRelative,
            'files' => $files,
        ];
    }

    /**
     * @throws DeterministicException
     */
    private static function normalizeRepoRoot(string $repoRootAbs): string
    {
        $repoRootAbs = \rtrim(\str_replace('\\', '/', \trim($repoRootAbs)), '/');

        if ($repoRootAbs === '' || !\is_dir($repoRootAbs)) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'repo-root-invalid',
            );
        }

        return $repoRootAbs;
    }

    /**
     * @throws DeterministicException
     */
    private static function normalizeFixtureRelativePath(string $fixtureRelativePath): string
    {
        $fixtureRelativePath = \str_replace('\\', '/', \trim($fixtureRelativePath));
        $fixtureRelativePath = \ltrim($fixtureRelativePath, '/');

        while (\str_starts_with($fixtureRelativePath, './')) {
            $fixtureRelativePath = \substr($fixtureRelativePath, 2);
        }

        if ($fixtureRelativePath === '' || !\str_starts_with($fixtureRelativePath, 'deptrac_min/')) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_FIXTURE_PATH_INVALID,
                'deptrac-min-only',
            );
        }

        return $fixtureRelativePath;
    }

    /**
     * @throws DeterministicException
     */
    private static function normalizeOutputDirRelative(string $outputDirRelative): string
    {
        $s = \trim($outputDirRelative);
        if ($s === '') {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'output-dir-invalid',
            );
        }

        if (\str_contains($s, '\\')) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'output-dir-invalid',
            );
        }

        $s = \str_replace('\\', '/', $s);
        $s = \ltrim($s, '/');

        while (\str_starts_with($s, './')) {
            $s = \substr($s, 2);
        }

        $s = \preg_replace('~/+~', '/', $s);
        if (!\is_string($s) || $s === '') {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'output-dir-invalid',
            );
        }

        if (
            $s === '.'
            || $s === '..'
            || \str_starts_with($s, '../')
            || \str_contains($s, '/../')
            || \str_contains($s, '://')
            || \preg_match('~(?i)\A[A-Z]:/~', $s) === 1
            || \preg_match('~(?i)\A[A-Z]:[\\\\/]~', $s) === 1
            || \str_starts_with($s, '//')
        ) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_DEPTRAC_GRAPH_ARTIFACT_INVALID,
                'output-dir-invalid',
            );
        }

        return $s;
    }

    /**
     * @throws DeterministicException
     */
    private static function ensureDirectoryExists(string $outputDirAbs): void
    {
        if (\is_dir($outputDirAbs)) {
            return;
        }

        $hadPhpError = false;

        \set_error_handler(
            static function () use (&$hadPhpError): bool {
                $hadPhpError = true;
                return true;
            }
        );

        try {
            $ok = \mkdir($outputDirAbs, 0777, true);
        } catch (\Throwable $e) {
            \restore_error_handler();

            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                'output-dir-create-failed',
                $e,
            );
        }

        \restore_error_handler();

        if ($hadPhpError || ($ok !== true && !\is_dir($outputDirAbs))) {
            throw new DeterministicException(
                ErrorCodes::CORETSIA_SPIKES_IO_WRITE_FAILED,
                'output-dir-create-failed',
            );
        }
    }

    private static function joinAbs(string $absRoot, string $relPath): string
    {
        $root = \rtrim(\str_replace('\\', '/', $absRoot), '/');
        $rel = \ltrim(\str_replace('\\', '/', $relPath), '/');

        return $root . '/' . $rel;
    }
}
