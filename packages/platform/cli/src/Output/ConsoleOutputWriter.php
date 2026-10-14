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

namespace Coretsia\Platform\Cli\Output;

use Coretsia\Platform\Cli\Exception\CliOutputFormatException;

/**
 * Invocation-local final sink and sole owner of trailing output newlines.
 *
 * @internal
 */
final class ConsoleOutputWriter
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function __construct(
        private mixed $stdout,
        private mixed $stderr,
    ) {
        if (!self::isStream($stdout) || !self::isStream($stderr)) {
            throw CliOutputFormatException::failed();
        }
    }

    public function write(FormattedOutput $output): void
    {
        self::writePayload($this->stdout, $output->stdoutBytes());
        self::writePayload($this->stderr, $output->stderrBytes());
    }

    private static function isStream(mixed $stream): bool
    {
        return \is_resource($stream) && \get_resource_type($stream) === 'stream';
    }

    /** @param resource $stream */
    private static function writePayload(mixed $stream, string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        self::writeAll($stream, $bytes . "\n");
    }

    /** @param resource $stream */
    private static function writeAll(mixed $stream, string $bytes): void
    {
        $offset = 0;
        $length = \strlen($bytes);

        while ($offset < $length) {
            try {
                $written = @\fwrite($stream, \substr($bytes, $offset));
            } catch (\Throwable) {
                throw CliOutputFormatException::failed();
            }

            if ($written === false || $written === 0) {
                throw CliOutputFormatException::failed();
            }

            $offset += $written;
        }
    }
}
