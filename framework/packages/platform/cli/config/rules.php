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


return static function (array $cfg): void {
    $cli = $cfg['cli'] ?? null;
    if (!is_array($cli)) {
        throw new \InvalidArgumentException('cli config: missing root key "cli"');
    }

    // Phase 0 schema (single-choice):
    // - cli.commands: list<FQCN> (may be empty)
    // - cli.output.format: 'text'|'json'
    // - cli.output.redaction.enabled: bool

    $allowedCliKeys = [
        'commands' => true,
        'output' => true,
    ];
    foreach ($cli as $k => $_v) {
        if (!is_string($k) || !isset($allowedCliKeys[$k])) {
            throw new \InvalidArgumentException('cli config: forbidden key "cli.' . (string)$k . '"');
        }
    }

    $commands = $cli['commands'] ?? null;
    if (!is_array($commands) || !array_is_list($commands)) {
        throw new \InvalidArgumentException('cli config: missing "cli.commands" (list<string>)');
    }
    foreach ($commands as $i => $fqcn) {
        if (!is_string($fqcn) || trim($fqcn) === '' || preg_match('~\s~', $fqcn) === 1) {
            throw new \InvalidArgumentException(
                'cli config: "cli.commands" must be list<non-empty-string-no-ws> (invalid at index ' . (string)$i . ')'
            );
        }
    }

    $output = $cli['output'] ?? null;
    if (!is_array($output)) {
        throw new \InvalidArgumentException('cli config: missing "cli.output" (map)');
    }

    $allowedOutputKeys = [
        'format' => true,
        'redaction' => true,
    ];
    foreach ($output as $k => $_v) {
        if (!is_string($k) || !isset($allowedOutputKeys[$k])) {
            throw new \InvalidArgumentException('cli config: forbidden key "cli.output.' . (string)$k . '"');
        }
    }

    $format = $output['format'] ?? null;
    if (!is_string($format) || trim($format) === '') {
        throw new \InvalidArgumentException('cli config: missing "cli.output.format" (non-empty string)');
    }
    if ($format !== 'text' && $format !== 'json') {
        throw new \InvalidArgumentException('cli config: "cli.output.format" must be one of: text|json');
    }

    $redaction = $output['redaction'] ?? null;
    if (!is_array($redaction)) {
        throw new \InvalidArgumentException('cli config: missing "cli.output.redaction" (map)');
    }

    $allowedRedactionKeys = [
        'enabled' => true,
    ];
    foreach ($redaction as $k => $_v) {
        if (!is_string($k) || !isset($allowedRedactionKeys[$k])) {
            throw new \InvalidArgumentException('cli config: forbidden key "cli.output.redaction.' . (string)$k . '"');
        }
    }

    if (!array_key_exists('enabled', $redaction) || !is_bool($redaction['enabled'])) {
        throw new \InvalidArgumentException('cli config: missing "cli.output.redaction.enabled" (bool)');
    }
};
