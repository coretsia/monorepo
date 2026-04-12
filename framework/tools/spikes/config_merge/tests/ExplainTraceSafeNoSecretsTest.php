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

namespace Coretsia\Tools\Spikes\config_merge\tests;

use Coretsia\Tools\Spikes\config_merge\ConfigExplainer;
use PHPUnit\Framework\TestCase;

final class ExplainTraceSafeNoSecretsTest extends TestCase
{
    public function testExplainTraceDoesNotLeakSecretValues(): void
    {
        $explainer = new ConfigExplainer();

        $secret = 'SUPER-SECRET-VALUE-DO-NOT-LEAK';

        $sources = [
            [
                'sourceType' => 'app',
                'file' => 'app.php',
                'config' => [
                    'db' => [
                        'password' => $secret,
                        'dsn' => 'pgsql://user:' . $secret . '@localhost/db',
                    ],
                    'tokens' => [
                        $secret,
                        'also-not-secret',
                    ],
                    'nested' => [
                        'deep' => [
                            'value' => $secret,
                        ],
                    ],
                ],
            ],
        ];

        $trace = $explainer->explain($sources);

        $encoded = json_encode($trace, JSON_THROW_ON_ERROR);

        // The trace shape MUST never include config values.
        self::assertStringNotContainsString($secret, $encoded);

        // Also ensure that the safe schema is respected: no accidental extra fields.
        foreach ($trace as $record) {
            self::assertSame(
                ['sourceType', 'file', 'keyPath', 'directiveApplied'],
                array_keys($record),
            );
        }
    }
}
