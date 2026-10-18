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

use Coretsia\Platform\Cli\Command\ConfigCompileCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class ConfigCompileRendersGenerationAwareOpsResultTest extends KernelOpsIntegrationTestCase
{
    public function testCompileRendersPublishedGenerationAndExactlyKernelProvidedArtifacts(): void
    {
        $artifacts = [
            ['basename' => 'module-manifest.php', 'identity' => 'module-manifest@1'],
            ['basename' => 'config.php', 'identity' => 'config@1'],
            ['basename' => 'container.php', 'identity' => 'container@1'],
            ['basename' => 'generation-manifest.php', 'identity' => 'artifact-generation@1'],
        ];
        $data = ['artifacts' => $artifacts, 'generation_id' => 'published-gen-34'];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'compileConfig',
            RecordingKernelOpsPort::success(
                'config.compile',
                'worker',
                'enterprise',
                $data,
            ),
        );

        $result = $fixture->run(ConfigCompileCommand::NAME, 'worker');
        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        $document = KernelOpsCommandFixture::document($result);
        self::assertSame(ConfigCompileCommand::NAME, $document['meta']['command']);
        self::assertSame(0, $document['meta']['exit_code']);
        $payload = KernelOpsCommandFixture::payload($result);
        self::assertSame([
            'app_target' => 'worker',
            'data' => $data,
            'operation' => 'config.compile',
            'outcome' => 'success',
            'preset' => 'enterprise',
        ], $payload);
        self::assertSame($artifacts, $payload['data']['artifacts']);
        self::assertCount(4, $payload['data']['artifacts']);
        self::assertSame('published-gen-34', $payload['data']['generation_id']);
        self::assertStringNotContainsString('generations/current/', $result['stdout']);
        self::assertStringNotContainsString('PRIVATE_RAW_CONFIG', $result['stdout']);
        self::assertStringNotContainsString('PRIVATE_COMPOSER_METADATA', $result['stdout']);
        self::assertStringNotContainsString('PRIVATE_ARTIFACT_PAYLOAD', $result['stdout']);
        self::assertDelegatedExactlyOnce($fixture, ConfigCompileCommand::class, 'compileConfig', 'worker');
        self::assertPortOnlySource(ConfigCompileCommand::class);
        self::assertTargetOnlyMetadata(ConfigCompileCommand::class);
    }

    public function testCompilePreservesProducerListOrderWithoutLocalSorting(): void
    {
        $artifacts = [
            ['basename' => 'container.php', 'identity' => 'container@1'],
            ['basename' => 'module-manifest.php', 'identity' => 'module-manifest@1'],
            ['basename' => 'generation-manifest.php', 'identity' => 'artifact-generation@1'],
            ['basename' => 'config.php', 'identity' => 'config@1'],
        ];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'compileConfig',
            RecordingKernelOpsPort::success(
                'config.compile',
                'api',
                'hybrid',
                ['artifacts' => $artifacts, 'generation_id' => 'published-gen-35'],
            ),
        );
        $result = $fixture->run(ConfigCompileCommand::NAME);
        self::assertSame(0, $result['exit']);
        self::assertSame($artifacts, KernelOpsCommandFixture::payload($result)['data']['artifacts']);
        self::assertDelegatedExactlyOnce($fixture, ConfigCompileCommand::class, 'compileConfig', 'api');
    }
}
