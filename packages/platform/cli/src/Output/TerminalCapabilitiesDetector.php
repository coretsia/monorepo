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

/**
 * Captures invocation-local terminal capabilities from explicit output streams.
 *
 * No stream, environment variable, configuration, or command option is retained.
 * Terminal probing belongs solely to this service at the entrypoint boundary.
 *
 * @internal
 */
final class TerminalCapabilitiesDetector
{
    /**
     * @param resource $stdout
     * @param resource $stderr
     */
    public function detect(mixed $stdout, mixed $stderr): TerminalCapabilities
    {
        if (!self::isStream($stdout) || !self::isStream($stderr)) {
            throw new \InvalidArgumentException('cli-terminal-stream-invalid');
        }

        $stdoutInteractive = self::isInteractive($stdout);
        $stderrInteractive = self::isInteractive($stderr);

        return new TerminalCapabilities(
            interactive: $stdoutInteractive,
            ansiSupported: $stdoutInteractive
            && $stderrInteractive
            && self::supportsAnsi($stdout)
            && self::supportsAnsi($stderr),
        );
    }

    private static function isStream(mixed $stream): bool
    {
        return \is_resource($stream) && \get_resource_type($stream) === 'stream';
    }

    /** @param resource $stream */
    private static function isInteractive(mixed $stream): bool
    {
        return \function_exists('stream_isatty') && \stream_isatty($stream);
    }

    /** @param resource $stream */
    private static function supportsAnsi(mixed $stream): bool
    {
        if (\PHP_OS_FAMILY !== 'Windows') {
            $term = \getenv('TERM');

            return \is_string($term) && \preg_match(
                '/\A(?:ansi|cygwin|linux|xterm(?:-(?:256color|color|direct|kitty))?|screen(?:-(?:256color|xterm-256color))?|screen\.xterm-256color|tmux(?:-256color)?|rxvt(?:-unicode)?(?:-256color)?|alacritty|foot(?:-direct)?|wezterm|st(?:-256color)?|putty(?:-256color)?)\z/D',
                $term,
            ) === 1;
        }

        return \function_exists('sapi_windows_vt100_support')
            && \sapi_windows_vt100_support($stream);
    }
}
