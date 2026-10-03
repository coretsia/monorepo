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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use PHPUnit\Framework\TestCase;

final class ModePresetSchemaValidatorEnforcesMicroAndExpressRulesTest extends TestCase
{
    public function testCanonicalResourcesExposeOnlyImplementedRuntimeModules(): void
    {
        foreach (['micro', 'express', 'hybrid', 'enterprise'] as $name) {
            $file = \dirname(__DIR__, 2) . '/resources/modes/' . $name . '.php';
            $payload = require $file;
            self::assertIsArray($payload);
            $preset = new ModePresetSchemaValidator()->validate($name, $payload);
            self::assertSame(1, $preset->schemaVersion());
            self::assertSame($name, $preset->name());
            self::assertSame(
                ['core.foundation', 'core.kernel'],
                \array_map(static fn (ModuleId $id): string => $id->value(), $preset->required()),
            );
            self::assertSame(
                \in_array($name, ['hybrid', 'enterprise'], true) ? ['platform.worker'] : [],
                \array_map(static fn (ModuleId $id): string => $id->value(), $preset->modules()),
            );
            self::assertSame(
                ['schemaVersion', 'name', 'description', 'required', 'modules', 'featureBundles', 'metadata'],
                \array_keys($preset->toArray()),
            );
            self::assertSame(['observability' => 'minimal'], $preset->featureBundles());
            self::assertSame([], $preset->metadata());
        }
    }

    public function testMicroAndExpressResourcesHaveSameCurrentlyImplementedSelection(): void
    {
        $validator = new ModePresetSchemaValidator();
        $micro = $validator->validate(
            'micro',
            require \dirname(__DIR__, 2) . '/resources/modes/micro.php',
        );
        $express = $validator->validate(
            'express',
            require \dirname(__DIR__, 2) . '/resources/modes/express.php',
        );
        self::assertSame($micro->toArray()['required'], $express->toArray()['required']);
        self::assertSame([], $micro->toArray()['modules']);
        self::assertSame([], $express->toArray()['modules']);
        self::assertNotContains('platform.http', $express->toArray()['required']);
    }
}
