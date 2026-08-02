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

namespace Coretsia\Platform\Worker\Communication;

use Coretsia\Platform\Worker\Exception\WorkerCommunicationFailedException;
use Coretsia\Platform\Worker\Runtime\WorkerHealthState;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;
use Coretsia\Platform\Worker\Runtime\WorkerPoolState;

/**
 * Owns the supervisor-side listening endpoint and typed control sessions.
 *
 * This class performs transport and protocol work only. Lifecycle decisions,
 * health semantics, and shutdown orchestration remain owned by WorkerSupervisor.
 */
final class WorkerControlServer
{
    /** @var resource|null */
    private mixed $server = null;
    private ?WorkerPoolSpec $spec = null;

    public function __construct(
        private readonly WorkerControlTransport $transport,
        private readonly WorkerControlProtocol $protocol,
    ) {
    }

    public function listen(WorkerPoolSpec $spec): void
    {
        if (\is_resource($this->server)) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        $this->server = $this->transport->listen($spec);
        $this->spec = $spec;
    }

    public function accept(int $timeoutMs): ?WorkerControlSession
    {
        if (!\is_resource($this->server)) {
            throw WorkerCommunicationFailedException::communicationFailed();
        }
        $connection = $this->transport->accept($this->server, $timeoutMs);
        if ($connection === null) {
            return null;
        }
        try {
            $request = $this->protocol->decodeRequest(
                $this->transport->readFrame(
                    $connection,
                    WorkerControlProtocol::MAX_FRAME_BYTES,
                ),
            );
            return new WorkerControlSession($connection, $request);
        } catch (\Throwable) {
            $this->transport->close($connection);
            return null;
        }
    }

    public function respondState(
        WorkerControlSession $session,
        WorkerPoolState $state,
    ): void {
        $this->respond(
            $session,
            WorkerControlResponse::ok($session->request()->requestId(), ['state' => $state->toArray()])
        );
    }

    public function respondHealth(
        WorkerControlSession $session,
        WorkerHealthState $health,
    ): void {
        $this->respond(
            $session,
            WorkerControlResponse::ok($session->request()->requestId(), ['health' => $health->toArray()])
        );
    }

    public function respondStopped(
        WorkerControlSession $session,
        WorkerPoolState $state,
    ): void {
        $this->respond(
            $session,
            WorkerControlResponse::stopped($session->request()->requestId(), ['state' => $state->toArray()])
        );
    }

    public function closeSession(WorkerControlSession $session): void
    {
        $this->transport->close($session->connection());
    }

    public function closeListener(): void
    {
        if (\is_resource($this->server)) {
            $this->transport->close($this->server);
        }
        $this->server = null;
        if ($this->spec !== null) {
            $this->transport->cleanup($this->spec);
        }
    }

    public function close(): void
    {
        $this->closeListener();
        $this->spec = null;
    }

    public function reset(): void
    {
        $this->server = null;
        $this->spec = null;
    }

    public function detachInForkedChild(): void
    {
        if (\is_resource($this->server)) {
            $this->transport->close($this->server);
        }
        $this->server = null;
        $this->spec = null;
    }

    private function respond(
        WorkerControlSession $session,
        WorkerControlResponse $response,
    ): void {
        $this->transport->writeFrame($session->connection(), $this->protocol->encodeResponse($response));
    }
}
