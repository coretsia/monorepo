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
use RuntimeException;

final class SpikeComposerRepositoriesSyncManagedOnlyContractTest extends ToolContractTestCase
{
    public function testSyncRewritesOnlyManagedRepositoriesAndPreservesUnmanagedRepositories(): void
    {
        $sandbox = $this->createWorkspaceSandbox('workspace_min');
        $rootComposer = $sandbox . '/composer.json';

        $composer = $this->readComposerJson($rootComposer);

        $unmanagedRepo = [
            'type' => 'vcs',
            'url' => 'https://example.invalid/vendor/package.git',
        ];

        $existingRepos = $composer['repositories'] ?? [];
        self::assertIsArray($existingRepos);

        $existingManagedRepos = [];
        $existingUnmanagedRepos = [];

        foreach ($existingRepos as $repo) {
            self::assertIsArray($repo);

            if (($repo['coretsia_managed'] ?? false) === true) {
                $existingManagedRepos[] = $repo;
                continue;
            }

            $existingUnmanagedRepos[] = $repo;
        }

        self::assertSame(
            [
                [
                    'type' => 'composer',
                    'url' => 'https://repo.packagist.org',
                ],
            ],
            $existingUnmanagedRepos,
            'Fixture precondition: root composer.json must contain the unmanaged Packagist repository.',
        );

        /*
         * Deliberately drift only the managed block:
         * - unmanaged repositories stay unmanaged and must be preserved in order
         * - managed repositories are reversed and must be restored canonically
         */
        $composer['repositories'] = array_merge(
            [$unmanagedRepo],
            $existingUnmanagedRepos,
            array_reverse($existingManagedRepos),
        );

        $this->writeJson($rootComposer, $composer);

        [$checkCode, $checkOutput] = $this->runWorkspaceSync($sandbox, ['--check']);
        self::assertNotSame(0, $checkCode, "Expected managed repositories drift to be detected.\nOutput:\n" . $checkOutput);
        self::assertStringContainsString('CORETSIA_WORKSPACE_MANAGED_REPOS_OUT_OF_SYNC', $checkOutput);

        [$applyCode, $applyOutput] = $this->runWorkspaceSync($sandbox, []);
        self::assertSame(0, $applyCode, "Expected sync apply to pass.\nOutput:\n" . $applyOutput);

        $after = $this->readComposerJson($rootComposer);
        $repos = $after['repositories'] ?? null;

        self::assertIsArray($repos);
        self::assertSame($unmanagedRepo, $repos[0], 'New unmanaged repository must be preserved before managed block.');

        $managed = [];
        $unmanaged = [];

        foreach ($repos as $repo) {
            self::assertIsArray($repo);

            if (($repo['coretsia_managed'] ?? false) === true) {
                $managed[] = $repo;
                continue;
            }

            $unmanaged[] = $repo;
        }

        self::assertSame(
            array_merge([$unmanagedRepo], $existingUnmanagedRepos),
            $unmanaged,
            'Sync must preserve all unmanaged repositories in deterministic order.',
        );

        self::assertSame($this->expectedRootManagedRepositories(), $managed);
    }

    /**
     * @return array<string,mixed>
     */
    private function readComposerJson(string $path): array
    {
        $decoded = json_decode($this->readBytes($path), true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Invalid composer.json fixture: ' . $path);
        }

        return $decoded;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function expectedRootManagedRepositories(): array
    {
        return [
            [
                'type' => 'path',
                'url' => 'framework',
                'options' => [
                    'symlink' => true,
                ],
                'coretsia_managed' => true,
            ],
            [
                'type' => 'path',
                'url' => 'framework/packages/*/*',
                'options' => [
                    'symlink' => true,
                ],
                'coretsia_managed' => true,
            ],
            [
                'type' => 'path',
                'url' => 'skeleton',
                'options' => [
                    'symlink' => true,
                ],
                'coretsia_managed' => true,
            ],
        ];
    }
}
