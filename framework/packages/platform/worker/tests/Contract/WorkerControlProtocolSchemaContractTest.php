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

use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Worker\Communication\WorkerControlOperation;
use Coretsia\Platform\Worker\Communication\WorkerControlProtocol;
use Coretsia\Platform\Worker\Communication\WorkerControlRequest;
use Coretsia\Platform\Worker\Communication\WorkerControlResponse;
use PHPUnit\Framework\TestCase;

final class WorkerControlProtocolSchemaContractTest extends TestCase
{
    public function testRequestAndResponseSchemasRoundTripExactly(): void
    {
        $protocol = new WorkerControlProtocol(
            new StableJsonEncoder(),
            new StableJsonDecoder(),
        );
        $request = new WorkerControlRequest(
            WorkerControlOperation::STATUS,
            'request-1',
        );

        self::assertEquals(
            $request,
            $protocol->decodeRequest(
                $protocol->encodeRequest($request),
            ),
        );

        $response = WorkerControlResponse::ok(
            'request-1',
            ['status' => 'running'],
        );

        self::assertEquals(
            $response,
            $protocol->decodeResponse(
                $protocol->encodeResponse($response),
            ),
        );
    }

    public function testUnknownKeysVersionsAndStartOperationAreRejected(): void
    {
        $protocol = new WorkerControlProtocol(
            new StableJsonEncoder(),
            new StableJsonDecoder(),
        );

        $rejected = 0;

        foreach (
            [
                "{\"operation\":\"start\",\"request_id\":\"x\",\"version\":1}\n",
                "{\"operation\":\"status\",\"payload\":{},\"request_id\":\"x\",\"version\":1}\n",
                "{\"operation\":\"status\",\"request_id\":\"x\",\"version\":2}\n",
            ] as $frame
        ) {
            try {
                $protocol->decodeRequest($frame);
                self::fail('Expected invalid control request.');
            } catch (
                \Coretsia\Platform\Worker\Exception\WorkerCommunicationFailedException
            ) {
                $rejected++;
            }
        }

        self::assertSame(3, $rejected);
    }
}
