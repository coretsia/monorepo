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

namespace Coretsia\Platform\Cli\UX;

use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;

/**
 * Stateless, bounded suggestions from final visible command catalog names.
 *
 * The catalog already owns canonical ASCII command-name validation and order.
 * Only lowercase ASCII bytes are compared, with no command resolution or
 * mutation of the finalized descriptors.
 *
 * @internal
 */
final class SmartSuggestor
{
    /**
     * @return list<string>
     */
    public function suggest(string $unknownName, CommandCatalog $catalog): array
    {
        $length = \strlen($unknownName);

        if ($length === 0 || $length > 128 || !CommandTagSchema::isValidName($unknownName)) {
            return [];
        }

        $threshold = $length <= 8 ? 2 : 3;

        /** @var list<array{name: string, distance: int}> $best */
        $best = [];

        foreach ($catalog->descriptors() as $descriptor) {
            if ($descriptor->hidden()) {
                continue;
            }

            $candidate = $descriptor->name();
            $candidateLength = \strlen($candidate);

            if ($candidateLength > 128 || \abs($candidateLength - $length) > $threshold) {
                continue;
            }

            $distance = \levenshtein($unknownName, $candidate);
            if ($distance > $threshold) {
                continue;
            }

            $position = 0;
            foreach ($best as $entry) {
                if ($distance < $entry['distance']) {
                    break;
                }

                ++$position;
            }

            if ($position >= 3) {
                continue;
            }

            \array_splice($best, $position, 0, [['name' => $candidate, 'distance' => $distance]]);

            if (\count($best) > 3) {
                \array_pop($best);
            }
        }

        return \array_column($best, 'name');
    }
}
