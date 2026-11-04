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
 * Reads ownership from the canonical config-roots SSoT; does not own a registry.
 */
final class ConfigRootOwnership
{
    private function __construct()
    {
    }

    public static function forPackage(RepositoryContext $repository, string $packageId): ?string
    {
        $markdown = DeterministicFile::readBytesExact(
            $repository->resolveExistingFile('docs/ssot/config-roots.md'),
        );
        $section = MarkdownSectionReader::section($markdown, '## Reserved Config Roots (MUST)');

        if ($section === null) {
            throw new \RuntimeException('config-roots-registry-section-missing');
        }

        /** @var array<string,true> $roots */
        $roots = [];
        /** @var array<string,string> $owners */
        $owners = [];
        $rowIndex = 0;

        foreach (\explode("\n", $section) as $line) {
            $line = \trim($line);

            if ($line === '') {
                continue;
            }

            if (!\str_starts_with($line, '|') || !\str_ends_with($line, '|')) {
                throw new \RuntimeException('config-roots-registry-row-invalid');
            }

            $columns = \array_map('trim', \explode('|', $line));

            if (\count($columns) !== 7) {
                throw new \RuntimeException('config-roots-registry-row-invalid');
            }

            if ($rowIndex === 0) {
                if (\array_slice($columns, 1, 5) !== [
                        'root',
                        'owner package_id',
                        'defaults file',
                        'rules file',
                        'notes',
                    ]) {
                    throw new \RuntimeException('config-roots-registry-row-invalid');
                }

                $rowIndex++;
                continue;
            }

            if ($rowIndex === 1) {
                foreach (\array_slice($columns, 1, 5) as $separator) {
                    if (\preg_match('/\A:?-{3,}:?\z/D', $separator) !== 1) {
                        throw new \RuntimeException('config-roots-registry-row-invalid');
                    }
                }

                $rowIndex++;
                continue;
            }

            if (
                \preg_match('/\A`([a-z][a-z0-9_]*)`\z/D', $columns[1], $rootMatch) !== 1
                || \preg_match('/\A`([a-z0-9-]+\/[a-z0-9-]+)`\z/D', $columns[2], $ownerMatch) !== 1
            ) {
                throw new \RuntimeException('config-roots-registry-row-invalid');
            }

            $root = $rootMatch[1];
            $owner = $ownerMatch[1];
            $defaults = \trim($columns[3], '`');
            $rules = \trim($columns[4], '`');

            if (
                $defaults !== 'packages/' . $owner . '/config/' . $root . '.php'
                || $rules !== 'packages/' . $owner . '/config/rules.php'
                || isset($roots[$root])
                || isset($owners[$owner])
            ) {
                throw new \RuntimeException('config-roots-registry-row-invalid');
            }

            $roots[$root] = true;
            $owners[$owner] = $root;
            $rowIndex++;
        }

        if ($rowIndex < 3 || $owners === []) {
            throw new \RuntimeException('config-roots-registry-empty');
        }

        return $owners[$packageId] ?? null;
    }
}
