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
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsCommandFixture;
use Coretsia\Platform\Cli\Tests\Integration\Support\KernelOpsIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingKernelOpsPort;

final class CacheVerifyRendersFourGenerationArtifactsTest extends KernelOpsIntegrationTestCase
{
    public function testEveryKernelVerificationStatePreservesFourArtifactsAndExitCode(): void
    {
        foreach (['clean' => 0, 'dirty' => 2, 'invalid' => 3] as $state => $exitCode) {
            $status = $state === 'clean' ? 'clean' : $state;
            $reason = match ($state) {
                'clean' => 'ok',
                'dirty' => 'changed',
                'invalid' => 'invalid',
            };
            $artifacts = [
                self::artifact('module-manifest', 'module-manifest.php', 'clean', 'ok', 120, 120),
                self::artifact('config', 'config.php', $status, $reason, 240, $state === 'invalid' ? null : 215),
                self::artifact('container', 'container.php', 'clean', 'ok', 360, 360),
                self::artifact('artifact-generation', 'generation-manifest.php', 'clean', 'ok', 480, 480),
            ];
            $current = $state === 'invalid' ? null : 'current-gen-40';
            $data = [
                'artifacts' => $artifacts,
                'current_generation_id' => $current,
                'expected_generation_id' => 'expected-gen-41',
                'state' => $state,
            ];
            $fixture = new KernelOpsCommandFixture();
            $fixture->ops->respondTo(
                'verifyCache',
                RecordingKernelOpsPort::success(
                    'cache.verify',
                    'console',
                    'micro',
                    $data,
                ),
            );

            $result = $fixture->run(CacheVerifyCommand::NAME, 'console');
            self::assertSame($exitCode, $result['exit'], $state . ': ' . $result['stderr']);
            self::assertSame('', $result['stderr']);
            $document = KernelOpsCommandFixture::document($result);
            self::assertSame($exitCode, $document['meta']['exit_code']);
            $payload = KernelOpsCommandFixture::payload($result);
            self::assertSame([
                'app_target' => 'console',
                'data' => $data,
                'operation' => 'cache.verify',
                'outcome' => 'success',
                'preset' => 'micro',
            ], $payload);
            self::assertSame($artifacts, $payload['data']['artifacts']);
            self::assertCount(4, $payload['data']['artifacts']);
            self::assertSame($current, $payload['data']['current_generation_id']);
            self::assertSame(240, $payload['data']['artifacts'][1]['expected_byte_count']);
            self::assertSame(
                $state === 'invalid' ? null : 215,
                $payload['data']['artifacts'][1]['existing_byte_count'],
            );
            self::assertStringNotContainsString('generations/current/', $result['stdout']);
            self::assertDelegatedExactlyOnce($fixture, CacheVerifyCommand::class, 'verifyCache', 'console');
        }
        self::assertPortOnlySource(CacheVerifyCommand::class);
        self::assertTargetOnlyMetadata(CacheVerifyCommand::class);
    }

    public function testArtifactOrderIsOwnedByKernelOpsResult(): void
    {
        $artifacts = [
            self::artifact('container', 'container.php', 'dirty', 'changed', 10, 5),
            self::artifact('artifact-generation', 'generation-manifest.php', 'clean', 'ok', 20, 20),
            self::artifact('module-manifest', 'module-manifest.php', 'clean', 'ok', 30, 30),
            self::artifact('config', 'config.php', 'clean', 'ok', 40, 40),
        ];
        $fixture = new KernelOpsCommandFixture();
        $fixture->ops->respondTo(
            'verifyCache',
            RecordingKernelOpsPort::success(
                'cache.verify',
                'api',
                'hybrid',
                [
                    'artifacts' => $artifacts,
                    'current_generation_id' => null,
                    'expected_generation_id' => 'expected-99',
                    'state' => 'dirty',
                ],
            ),
        );
        $result = $fixture->run(CacheVerifyCommand::NAME);
        self::assertSame(2, $result['exit']);
        self::assertSame($artifacts, KernelOpsCommandFixture::payload($result)['data']['artifacts']);
        self::assertDelegatedExactlyOnce($fixture, CacheVerifyCommand::class, 'verifyCache', 'api');
    }

    /** @return array<string,mixed> */
    private static function artifact(
        string $name,
        string $basename,
        string $status,
        string $reason,
        int $expected,
        ?int $existing,
    ): array {
        return [
            'basename' => $basename,
            'existing_byte_count' => $existing,
            'expected_byte_count' => $expected,
            'name' => $name,
            'reason' => $reason,
            'status' => $status,
        ];
    }
}
