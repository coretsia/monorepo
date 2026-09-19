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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ContainerDiagnostics;
use PHPUnit\Framework\TestCase;

final class ContainerDiagnosticsJsonIsDeterministicContractTest extends TestCase
{
    public function testJsonIsStableForEquivalentBuilderSnapshotsWithDifferentRegistrationOrder(): void
    {
        $firstJson = ContainerDiagnostics::fromBuilder(self::firstBuilder())->toJson();
        $secondJson = ContainerDiagnostics::fromBuilder(self::secondBuilder())->toJson();

        self::assertSame($firstJson, ContainerDiagnostics::fromBuilder(self::firstBuilder())->toJson());
        self::assertSame($firstJson, $secondJson);

        self::assertSame(
            "{\"schemaVersion\":\"coretsia.foundation.containerDiagnostics.v1\",\"services\":[\"service.alpha\",\"service.beta\",\"service.zeta\"],\"tags\":{\"cli.command\":[{\"id\":\"service.command\",\"priority\":0}],\"http.middleware.app\":[{\"id\":\"service.middleware\",\"priority\":10}],\"kernel.reset\":[{\"id\":\"service.alpha\",\"priority\":50},{\"id\":\"service.zeta\",\"priority\":0}]}}\n",
            $firstJson,
        );
    }

    public function testJsonUsesFinalLfAndNoCrLf(): void
    {
        $json = ContainerDiagnostics::fromBuilder(self::firstBuilder())->toJson();

        self::assertStringEndsWith("\n", $json);
        self::assertStringNotContainsString("\r", $json);
    }

    public function testArrayShapeIsNormalizedBeforeJsonEncoding(): void
    {
        $diagnostics = ContainerDiagnostics::fromBuilder(self::firstBuilder())->toArray();

        self::assertSame('coretsia.foundation.containerDiagnostics.v1', $diagnostics['schemaVersion']);

        self::assertSame(
            [
                'service.alpha',
                'service.beta',
                'service.zeta',
            ],
            $diagnostics['services'],
        );

        self::assertSame(
            [
                'cli.command',
                'http.middleware.app',
                'kernel.reset',
            ],
            \array_keys($diagnostics['tags']),
        );

        self::assertSame(
            [
                [
                    'id' => 'service.alpha',
                    'priority' => 50,
                ],
                [
                    'id' => 'service.zeta',
                    'priority' => 0,
                ],
            ],
            $diagnostics['tags']['kernel.reset'],
        );
    }

    private static function firstBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->set('service.zeta', 'zeta');
        $builder->set('service.alpha', 'alpha');
        $builder->set('service.beta', 'beta');

        $builder->tag('kernel.reset', 'service.zeta', 0, ['meta' => 'must-not-be-serialized']);
        $builder->tag('kernel.reset', 'service.alpha', 50);
        $builder->tag('cli.command', 'service.command', 0);
        $builder->tag('http.middleware.app', 'service.middleware', 10);

        return $builder;
    }

    private static function secondBuilder(): ContainerBuilder
    {
        $builder = new ContainerBuilder(config: self::validConfig());

        $builder->set('service.beta', 'beta');
        $builder->set('service.zeta', 'zeta');
        $builder->set('service.alpha', 'alpha');

        $builder->tag('http.middleware.app', 'service.middleware', 10);
        $builder->tag('kernel.reset', 'service.alpha', 50);
        $builder->tag('cli.command', 'service.command', 0);
        $builder->tag('kernel.reset', 'service.zeta', 0, ['meta' => 'must-not-be-serialized']);

        return $builder;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
            ],
        ];
    }
}
