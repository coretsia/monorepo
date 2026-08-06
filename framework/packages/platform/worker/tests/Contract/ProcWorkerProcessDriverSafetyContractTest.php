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

namespace Coretsia\Platform\Worker\Tests\Contract;

use Coretsia\Platform\Worker\Tests\Support\PackageTestCase;

final class ProcWorkerProcessDriverSafetyContractTest extends PackageTestCase
{
    public function testDriverDelegatesRawProcessOwnershipToPreLockHost(): void
    {
        $source = self::source('src/Process/Driver/ProcWorkerProcessDriver.php');

        foreach (
            [
                'processHost->start',
                'processHost->spawn',
                'processHost->pollExit',
                'processHost->terminate',
                'processHost->kill',
                'processHost->close',
                'processHost->shutdown',
            ] as $required
        ) {
            self::assertStringContainsString($required, $source);
        }

        $codeOnly = \preg_replace('/\/\*.*?\*\/|\/\/[^\n]*/s', '', $source) ?? $source;

        foreach (
            [
                'proc_open(',
                'proc_get_status(',
                'proc_terminate(',
                'proc_close(',
                'WorkerStateStore',
                'WorkerControlServer',
                'WorkerStopSignal',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $codeOnly);
        }
    }


    public function testProcessHostRotatesConnectionAroundEveryChildLaunch(): void
    {
        $host = self::source('bin/coretsia-worker-proc-host');
        $client = self::source('src/Process/Proc/WorkerProcProcessHostClient.php');
        $endpoint = self::source('src/Process/Proc/WorkerProcProcessHostHandoffEndpoint.php');
        $transport = self::source('src/Process/Proc/WorkerProcProcessHostTransport.php');

        foreach (
            [
                'WorkerProcProcessHostHandoffEndpoint::create',
                'handoff_port',
                'handoff_token',
                'decodeHandoff',
            ] as $required
        ) {
            self::assertStringContainsString($required, $client);
        }

        $hostClose = \strpos($host, '$this->closeConnection();');
        $hostSpawn = \strpos($host, '$this->spawn($payload)');
        $hostReconnect = \strpos($host, '$this->transport->connect(');

        self::assertIsInt($hostClose);
        self::assertIsInt($hostSpawn);
        self::assertIsInt($hostReconnect);
        self::assertLessThan($hostSpawn, $hostClose);
        self::assertLessThan($hostReconnect, $hostSpawn);

        foreach (
            [
                'stream_socket_server(',
                'stream_socket_accept(',
                'random_bytes(32)',
                'function accept(',
            ] as $required
        ) {
            self::assertStringContainsString($required, $endpoint);
        }

        self::assertStringContainsString('stream_socket_client(', $transport);
        self::assertStringNotContainsString('SOCK_CLOEXEC', $transport);
        self::assertStringNotContainsString('socket_create(', $transport);
    }

    public function testProcDriverCapabilityChecksEveryRequiredFunction(): void
    {
        $capabilities = self::source('src/Internal/WorkerProcessCapabilities.php');

        self::assertStringContainsString(
            'procProcessHostTransportAvailable',
            $capabilities,
        );
        self::assertStringContainsString(
            'procDriverAvailable',
            $capabilities,
        );

        foreach (
            [
                'proc_open',
                'proc_get_status',
                'proc_terminate',
                'proc_close',
                'stream_socket_server',
                'stream_socket_get_name',
                'stream_socket_client',
                'stream_socket_accept',
                'stream_set_blocking',
                'stream_select',
            ] as $requiredFunction
        ) {
            self::assertStringContainsString(
                "\\function_exists('{$requiredFunction}')",
                $capabilities,
            );
        }
    }

    public function testHostProtocolIsVersionedBoundedAndRejectsUnknownShapes(): void
    {
        $source = self::source('src/Process/Proc/WorkerProcProcessHostProtocol.php');

        self::assertStringContainsString('VERSION = 1', $source);
        self::assertStringContainsString('MAX_FRAME_BYTES', $source);
        self::assertStringContainsString('StableJsonEncoder', $source);
        self::assertStringContainsString('StableJsonDecoder', $source);
        self::assertStringNotContainsString('serialize(', $source);
        self::assertStringNotContainsString('unserialize(', $source);
    }
}
