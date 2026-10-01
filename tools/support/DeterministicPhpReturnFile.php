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

final class DeterministicPhpReturnFile
{
    private function __construct()
    {
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param non-empty-list<non-empty-string> $generatedNoticeLines
     */
    public static function render(
        array $payload,
        array $generatedNoticeLines,
    ): string {
        $payload = self::normalizePayload($payload);

        $export = self::normalizeEol(
            self::renderPhpValue($payload, 0),
        );

        $out = "<?php\n\n";
        $out .= "declare(strict_types=1);\n\n";
        $out .= self::licenseHeaderPhp();
        $out .= "/*\n";

        foreach ($generatedNoticeLines as $line) {
            $out .= ' * ' . $line . "\n";
        }

        $out .= " */\n\n";
        $out .= 'return ' . $export . ";\n";

        return $out;
    }

    private static function licenseHeaderPhp(): string
    {
        return "/*\n"
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
            . " */\n\n";
    }

    private static function renderPhpValue(
        mixed $value,
        int $indent,
    ): string {
        if (\is_array($value)) {
            return self::renderPhpArray($value, $indent);
        }

        if ($value === null) {
            return 'null';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (
            \is_int($value)
            || \is_float($value)
            || \is_string($value)
        ) {
            return \var_export($value, true);
        }

        throw new \RuntimeException('unsupported-deterministic-php-return-file-value');
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private static function renderPhpArray(
        array $value,
        int $indent,
    ): string {
        $pad = \str_repeat(' ', $indent);
        $childPad = \str_repeat(' ', $indent + 2);

        $lines = [
            $pad . 'array(',
        ];

        foreach ($value as $key => $item) {
            $keyOut = self::renderPhpArrayKey($key);

            if (\is_array($item)) {
                $lines[] = $childPad . $keyOut . ' =>';

                $nestedLines = \explode(
                    "\n",
                    self::renderPhpArray($item, $indent + 2),
                );

                $last = \count($nestedLines) - 1;

                foreach ($nestedLines as $index => $line) {
                    $lines[] = $line . ($index === $last ? ',' : '');
                }

                continue;
            }

            $lines[] = $childPad
                . $keyOut
                . ' => '
                . self::renderPhpValue($item, $indent + 2)
                . ',';
        }

        $lines[] = $pad . ')';

        return \implode("\n", $lines);
    }

    private static function renderPhpArrayKey(
        int|string $key,
    ): string {
        return \is_int($key) ? (string) $key : \var_export($key, true);
    }

    private static function normalizePayload(
        mixed $value,
    ): mixed {
        if (!\is_array($value)) {
            return $value;
        }

        if (\array_is_list($value)) {
            $normalized = [];

            foreach ($value as $item) {
                $normalized[] = self::normalizePayload($item);
            }

            return $normalized;
        }

        $keys = \array_keys($value);

        \usort(
            $keys,
            static fn (
                int|string $left,
                int|string $right,
            ): int => \strcmp(
                (string) $left,
                (string) $right,
            ),
        );

        $normalized = [];

        foreach ($keys as $key) {
            $normalized[(string) $key] = self::normalizePayload($value[$key]);
        }

        return $normalized;
    }

    private static function normalizeEol(
        string $value,
    ): string {
        return \str_replace(
            ["\r\n", "\r"],
            "\n",
            $value,
        );
    }
}
