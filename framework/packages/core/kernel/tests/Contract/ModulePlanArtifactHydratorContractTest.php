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

use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\ModulePlanArtifactHydrator;
use Coretsia\Kernel\Module\Warning\ModuleOptionalMissingWarning;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModulePlanArtifactHydratorContractTest extends TestCase
{
    private const string INVALID_REASON = 'module-plan-artifact-payload-invalid';

    public function testHydratesCanonicalModuleManifestPayloadWithoutComposerDiscovery(): void
    {
        $payload = self::validPayload();

        $plan = new ModulePlanArtifactHydrator()->hydrate($payload);

        self::assertSame($payload, $plan->toArray());
        self::assertSame('worker', $plan->app());
        self::assertSame('default', $plan->preset());
        self::assertTrue($plan->hasEnabledModule('core.foundation'));
        self::assertTrue($plan->hasEnabledModule('core.kernel'));
        self::assertTrue($plan->hasOptionalMissingModule('platform.metrics'));
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testRejectsInvalidModuleManifestPayload(
        string $scenario,
    ): void {
        $payload = self::invalidPayload($scenario);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(self::INVALID_REASON);

        new ModulePlanArtifactHydrator()->hydrate($payload);
    }

    public function testHydratorSourceDoesNotUseComposerOrFilesystemDiscovery(): void
    {
        $reflection = new \ReflectionClass(ModulePlanArtifactHydrator::class);
        $file = $reflection->getFileName();

        self::assertIsString($file);

        $source = \file_get_contents($file);

        self::assertIsString($source);

        foreach (
            [
                'ComposerManifestReader',
                'ManifestReaderInterface',
                'InstalledVersions',
                'composer/installed',
                'vendor/composer',
                'file_get_contents(',
                'scandir(',
                'glob(',
            ] as $forbiddenNeedle
        ) {
            self::assertStringNotContainsString(
                $forbiddenNeedle,
                $source,
            );
        }
    }

    /**
     * @return iterable<string, array{scenario: string}>
     */
    public static function invalidPayloadProvider(): iterable
    {
        yield 'unknown top-level key' => [
            'scenario' => 'unknown-top-level-key',
        ];

        yield 'schema version mismatch' => [
            'scenario' => 'schema-version-mismatch',
        ];

        yield 'unknown app target' => [
            'scenario' => 'unknown-app-target',
        ];

        yield 'non-canonical enabled set order' => [
            'scenario' => 'enabled-set-order-invalid',
        ];

        yield 'module map key does not match module id' => [
            'scenario' => 'module-entry-id-mismatch',
        ];

        yield 'required dependency is not enabled' => [
            'scenario' => 'required-dependency-missing',
        ];

        yield 'conflicting modules are both enabled' => [
            'scenario' => 'enabled-conflict',
        ];

        yield 'topological order violates dependencies' => [
            'scenario' => 'topological-order-invalid',
        ];

        yield 'module dependency cycle' => [
            'scenario' => 'dependency-cycle',
        ];

        yield 'warning set does not match optional missing set' => [
            'scenario' => 'warning-set-mismatch',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function invalidPayload(string $scenario): array
    {
        $payload = self::validPayload();

        switch ($scenario) {
            case 'unknown-top-level-key':
                $payload['unknown'] = true;

                break;

            case 'schema-version-mismatch':
                $payload['schemaVersion'] = 2;

                break;

            case 'unknown-app-target':
                $payload['app'] = 'unknown';

                break;

            case 'enabled-set-order-invalid':
                $payload['enabled'] = [
                    'core.kernel',
                    'core.foundation',
                ];

                break;

            case 'module-entry-id-mismatch':
                $payload['modules']['core.kernel']['moduleId'] = 'core.other';

                break;

            case 'required-dependency-missing':
                $payload['enabled'] = [
                    'core.kernel',
                ];
                unset($payload['modules']['core.foundation']);
                $payload['topologicalOrder'] = [
                    'core.kernel',
                ];

                break;

            case 'enabled-conflict':
                $payload['modules']['core.kernel']['conflicts'] = [
                    'core.foundation',
                ];

                break;

            case 'topological-order-invalid':
                $payload['topologicalOrder'] = [
                    'core.kernel',
                    'core.foundation',
                ];

                break;

            case 'dependency-cycle':
                $payload['modules']['core.foundation']['requires'] = [
                    'core.kernel',
                ];

                break;

            case 'warning-set-mismatch':
                $payload['warnings'] = [];

                break;

            default:
                throw new \LogicException(
                    'module-plan-artifact-test-scenario-invalid',
                );
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validPayload(): array
    {
        return [
            'app' => 'worker',
            'disabled' => [
                'platform.http',
            ],
            'enabled' => [
                'core.foundation',
                'core.kernel',
            ],
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
                    'requires' => [
                        'core.foundation',
                    ],
                ],
            ],
            'optionalMissing' => [
                'platform.metrics',
            ],
            'preset' => 'default',
            'schemaVersion' => 1,
            'topologicalOrder' => [
                'core.foundation',
                'core.kernel',
            ],
            'warnings' => [
                [
                    'code' => ModuleErrorCodes::CORETSIA_MODULE_OPTIONAL_MISSING,
                    'moduleId' => 'platform.metrics',
                    'preset' => 'default',
                    'reason' => ModuleOptionalMissingWarning::REASON_PRESET_OPTIONAL_MODULE_MISSING,
                ],
            ],
        ];
    }
}
