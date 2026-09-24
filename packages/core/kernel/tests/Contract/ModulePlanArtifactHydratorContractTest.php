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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanArtifactHydrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModulePlanArtifactHydratorContractTest extends TestCase
{
    public function testCanonicalPayloadRoundTripWithoutPresetOrComposerDiscovery(): void
    {
        $payload = self::validPayload();
        $plan = new ModulePlanArtifactHydrator()->hydrate($payload);
        self::assertSame($payload, $plan->toArray());
        self::assertSame(ModulePlan::SCHEMA_VERSION, $plan->schemaVersion());
        self::assertSame('worker', $plan->app());
        self::assertTrue($plan->hasEnabledModule('core.foundation'));
        self::assertTrue($plan->hasEnabledModule('core.kernel'));
        self::assertSame(['platform.http'], $payload['excluded']);
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsRejected(string $scenario): void
    {
        $payload = self::validPayload();
        switch ($scenario) {
            case 'legacy-preset':
                $payload['preset'] = 'micro';
                break;
            case 'legacy-disabled':
                $payload['disabled'] = [];
                break;
            case 'legacy-optional-missing':
                $payload['optionalMissing'] = [];
                break;
            case 'legacy-warnings':
                $payload['warnings'] = [];
                break;
            case 'unknown-key':
                $payload['unexpected'] = true;
                break;
            case 'schema-version':
                $payload['schemaVersion'] = 2;
                break;
            case 'app-target':
                $payload['app'] = 'unknown';
                break;
            case 'enabled-order':
                $payload['enabled'] = ['core.kernel', 'core.foundation'];
                break;
            case 'excluded-overlap':
                $payload['excluded'] = ['core.foundation'];
                break;
            case 'module-id':
                $payload['modules']['core.kernel']['moduleId'] = 'core.other';
                break;
            case 'dependency':
                $payload['enabled'] = ['core.kernel'];
                unset($payload['modules']['core.foundation']);
                $payload['topologicalOrder'] = ['core.kernel'];
                break;
            case 'conflict':
                $payload['modules']['core.kernel']['conflicts'] = ['core.foundation'];
                break;
            case 'topological-order':
                $payload['topologicalOrder'] = ['core.kernel', 'core.foundation'];
                break;
            case 'cycle':
                $payload['modules']['core.foundation']['requires'] = ['core.kernel'];
                break;
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('module-plan-artifact-payload-invalid');
        new ModulePlanArtifactHydrator()->hydrate($payload);
    }

    public static function invalidPayloadProvider(): iterable
    {
        foreach (
            [
                'legacy-preset',
                'legacy-disabled',
                'legacy-optional-missing',
                'legacy-warnings',
                'unknown-key',
                'schema-version',
                'app-target',
                'enabled-order',
                'excluded-overlap',
                'module-id',
                'dependency',
                'conflict',
                'topological-order',
                'cycle',
            ] as $scenario
        ) {
            yield $scenario => ['scenario' => $scenario];
        }
    }

    public function testHydratorSourceDoesNotReadPresetOrInstalledMetadata(): void
    {
        $source = \file_get_contents(new \ReflectionClass(ModulePlanArtifactHydrator::class)->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'ComposerManifestReader',
                'ManifestReaderInterface',
                'ModePresetLoaderFactory',
                'ModuleSelectionFactory',
                'InstalledVersions',
                'file_get_contents(',
                'scandir(',
                'glob(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private static function validPayload(): array
    {
        return [
            'app' => 'worker',
            'enabled' => ['core.foundation', 'core.kernel'],
            'excluded' => ['platform.http'],
            'modules' => [
                'core.foundation' => [
                    'composerName' => 'coretsia/core-foundation',
                    'conflicts' => [],
                    'moduleId' => 'core.foundation',
                    'requires' => [],
                ],
                'core.kernel' => [
                    'composerName' => 'coretsia/core-kernel',
                    'conflicts' => [],
                    'moduleId' => 'core.kernel',
                    'requires' => ['core.foundation'],
                ],
            ],
            'schemaVersion' => ModulePlan::SCHEMA_VERSION,
            'topologicalOrder' => ['core.foundation', 'core.kernel'],
        ];
    }
}
