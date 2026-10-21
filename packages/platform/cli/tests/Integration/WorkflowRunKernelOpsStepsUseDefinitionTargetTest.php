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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Platform\Cli\Command\CacheVerifyCommand;
use Coretsia\Platform\Cli\Command\ConfigValidateCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;

final class WorkflowRunKernelOpsStepsUseDefinitionTargetTest extends KernelOpsIntegrationTestCase
{
    public function testEachKernelOpsStepReceivesItsOwnExplicitDefinitionTargetThroughTheExistingPort(): void
    {
        $fixture = self::fixture('web', 'api');
        $fixture->ops->respondTo(
            'validateConfig',
            RecordingKernelOpsPort::success(
                'config.validate',
                'web',
                'web',
                ['valid' => true],
            ),
        );
        $fixture->ops->respondTo(
            'verifyCache',
            RecordingKernelOpsPort::success(
                'cache.verify',
                'api',
                'api',
                self::cachePayload(),
            ),
        );
        $result = $fixture->run(['workflow:run', 'verify', '--format=json', '--color=never']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame('', $result['stderr']);
        self::assertSame([
            ['method' => 'validateConfig', 'appTarget' => 'web'],
            ['method' => 'verifyCache', 'appTarget' => 'api'],
        ], $fixture->ops->calls);
        self::assertSame(
            [ConfigValidateCommand::class, CacheVerifyCommand::class],
            \array_slice($fixture->container->resolved, 1)
        );
        self::assertSame(2, $fixture->runtime->calls);
        self::assertSame(['cli', 'cli'], \array_column($fixture->runtime->invocations, 'type'));
        $payload = WorkflowIntegrationFixture::document($result)['data']['records'][0]['payload'];
        self::assertSame('verify', $payload['workflow']);
        self::assertSame(['config:validate', 'cache:verify'], \array_column($payload['steps'], 'command'));
        self::assertSame('web', $payload['steps'][0]['records'][0]['data']['app_target']);
        self::assertSame('api', $payload['steps'][1]['records'][0]['data']['app_target']);
        self::assertSame(['web', 'api'], [
            $payload['steps'][0]['records'][0]['data']['preset'],
            $payload['steps'][1]['records'][0]['data']['preset'],
        ]);
    }

    public function testMissingRequiredTargetFailsThroughExistingKernelOpsResolver(): void
    {
        $fixture = self::fixture(null, 'api');
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame([
            [
                'code' => CliInputInvalidException::ERROR_CODE,
                'message' => CliInputInvalidException::REASON_TARGET_INVALID,
                'type' => 'error',
            ],
        ], WorkflowIntegrationFixture::document($result)['data']['records']);
        self::assertSame([], $fixture->ops->calls);
        self::assertSame([ConfigValidateCommand::class], \array_slice($fixture->container->resolved, 1));
        self::assertSame(1, $fixture->runtime->calls);
    }

    public function testNonStringTargetFailsStructuralValidationBeforeKernelOpsServiceResolution(): void
    {
        $fixture = new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Reject structurally invalid target.',
                'steps' => [
                    [
                        'command' => ConfigValidateCommand::NAME,
                        'arguments' => [],
                        'options' => ['target' => true],
                    ],
                ],
            ],
        ]);

        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);

        self::assertSame(1, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame([
            [
                'code' => CliInputInvalidException::ERROR_CODE,
                'message' => CliInputInvalidException::REASON_OPTION_INVALID,
                'type' => 'error',
            ],
        ], WorkflowIntegrationFixture::document($result)['data']['records']);

        self::assertSame([], $fixture->ops->calls);
        self::assertSame([
            WorkflowRunCommand::class,
        ], $fixture->container->resolved);
        self::assertSame(0, $fixture->runtime->calls);
    }

    public function testInvalidDomainTargetUsesExistingKernelOpsResolver(): void
    {
        $fixture = self::fixture('invalid.target', 'api');
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(1, $result['exit']);
        self::assertSame([
            [
                'code' => CliInputInvalidException::ERROR_CODE,
                'message' => CliInputInvalidException::REASON_TARGET_INVALID,
                'type' => 'error',
            ],
        ], WorkflowIntegrationFixture::document($result)['data']['records']);
        self::assertSame([], $fixture->ops->calls);
        self::assertSame([ConfigValidateCommand::class], \array_slice($fixture->container->resolved, 1));
        self::assertSame(1, $fixture->runtime->calls);
    }

    public function testWorkflowInfrastructureKeepsKernelOpsAndBootstrapPresetOwnershipOutsideTheRunner(): void
    {
        self::assertPortOnlySource(ConfigValidateCommand::class);
        self::assertPortOnlySource(CacheVerifyCommand::class);

        $root = \dirname(__DIR__, 2) . '/src';
        $paths = [
            $root . '/Workflow',
            $root . '/Command/WorkflowListCommand.php',
            $root . '/Command/WorkflowRunCommand.php',
        ];

        foreach ($paths as $path) {
            self::assertTrue(\file_exists($path), $path);

            $files = \is_dir($path)
                ? new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                )
                : [$path];

            foreach ($files as $file) {
                $filename = $file instanceof \SplFileInfo ? $file->getPathname() : $file;

                if (!\is_file($filename) || !\str_ends_with($filename, '.php')) {
                    continue;
                }

                $source = \file_get_contents($filename);
                self::assertIsString($source);

                $code = '';
                foreach (\token_get_all($source) as $token) {
                    if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                        continue;
                    }

                    $code .= \is_array($token) ? $token[1] : $token;
                }

                foreach (
                    [
                        'KernelOpsInterface',
                        'KernelOpsRequest',
                        'KernelOpsRequestResolver',
                        'BootstrapPreset',
                        'KernelOpsFacade',
                        'ConfigKernel',
                        'ArtifactCompiler',
                    ] as $forbidden
                ) {
                    self::assertStringNotContainsString($forbidden, $code, $filename);
                }
            }
        }
    }

    private static function fixture(?string $first, string $second): WorkflowIntegrationFixture
    {
        return new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Operate distinct targets.',
                'steps' => [
                    [
                        'command' => ConfigValidateCommand::NAME,
                        'arguments' => [],
                        'options' => $first === null ? [] : ['target' => $first],
                    ],
                    ['command' => CacheVerifyCommand::NAME, 'arguments' => [], 'options' => ['target' => $second]],
                ],
            ],
        ]);
    }

    /** @return array<string,mixed> */
    private static function cachePayload(): array
    {
        return [
            'state' => 'clean',
            'expected_generation_id' => 'expected',
            'current_generation_id' => 'expected',
            'artifacts' => \array_fill(0, 4, [
                'name' => 'artifact',
                'basename' => 'artifact.json',
                'status' => 'clean',
                'reason' => 'ok',
                'expected_byte_count' => 0,
                'existing_byte_count' => 0,
            ]),
        ];
    }
}
