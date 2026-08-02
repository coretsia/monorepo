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

namespace Coretsia\Platform\Worker\Process\Proc;

use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Process\WorkerProcessExit;

/**
 * Synchronous client for the pre-lock proc process host.
 *
 * The host is started before the supervisor acquires its lifecycle lock or
 * opens control/readiness listeners. Communication uses one authenticated
 * loopback TCP connection, avoiding proc_open pipe selection on Windows.
 */
final class WorkerProcProcessHostClient
{
    private const int CONNECT_RETRY_US = 1_000;
    private const int HOST_FALLBACK_SHUTDOWN_MS = 3_000;
    private const int MAX_TIMEOUT_MS = 86_400_000;
    private const int MAX_REQUEST_ID = 2_147_483_647;

    /** @var list<non-empty-string> */
    private array $command;

    private mixed $process = null;
    private mixed $connection = null;
    private int $nextRequestId = 1;
    private int $requestTimeoutMs = 1_000;

    /** @var array<string, positive-int> */
    private array $children = [];

    /**
     * @param list<non-empty-string> $command
     */
    public function __construct(
        array $command,
        private readonly string $workingDirectory,
        private readonly WorkerProcProcessHostProtocol $protocol,
    ) {
        if (
            $command === []
            || !\array_is_list($command)
            || !self::isSafePath($workingDirectory)
        ) {
            throw new \InvalidArgumentException('worker-proc-host-client-invalid');
        }

        foreach ($command as $part) {
            if (!self::isSafeCommandPart($part)) {
                throw new \InvalidArgumentException('worker-proc-host-client-invalid');
            }
        }

        $this->command = $command;
    }

    public function start(int $timeoutMs): void
    {
        self::assertTimeout($timeoutMs);

        if ($this->started()) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $deadlineNs = self::deadlineNs($timeoutMs);
        $port = self::reserveLoopbackPort();

        try {
            $token = \bin2hex(\random_bytes(32));
        } catch (\Throwable) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $command = [
            ...$this->command,
            '--coretsia-proc-host-port=' . $port,
            '--coretsia-proc-host-token=' . $token,
        ];

        $null = \PHP_OS_FAMILY === 'Windows'
            ? 'NUL'
            : '/dev/null';

        $descriptors = [
            0 => ['file', $null, 'r'],
            1 => ['file', $null, 'w'],
            2 => ['file', $null, 'w'],
        ];

        /** @var array{bypass_shell: true, create_process_group?: true} $options */
        $options = [
            'bypass_shell' => true,
        ];

        if (\PHP_OS_FAMILY === 'Windows') {
            $options['create_process_group'] = true;
        }

        $pipes = [];
        $process = @\proc_open(
            command: $command,
            descriptor_spec: $descriptors,
            pipes: $pipes,
            cwd: $this->workingDirectory,
            env_vars: null,
            options: $options,
        );

        if (!\is_resource($process)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $this->process = $process;
        $this->requestTimeoutMs = \min($timeoutMs, 1_000);

        try {
            $this->connection = $this->connect(
                port: $port,
                deadlineNs: $deadlineNs,
            );

            $response = $this->request(
                operation: WorkerProcProcessHostProtocol::OPERATION_HELLO,
                payload: ['token' => $token],
                timeoutMs: self::remainingMs($deadlineNs),
            );

            if (
                \array_keys($response) !== ['ready']
                || $response['ready'] !== true
            ) {
                throw WorkerStartFailedException::processHostFailed();
            }
        } catch (\Throwable $exception) {
            /*
             * Host startup failed before any worker child could be registered.
             * There is no child cleanup phase to wait for.
             */
            $this->forceCloseHost(
                allowCleanup: false,
            );

            if ($exception instanceof WorkerStartFailedException) {
                throw $exception;
            }

            throw WorkerStartFailedException::processHostFailed();
        }
    }

    /**
     * @param non-empty-list<non-empty-string> $command
     */
    public function spawn(
        array $command,
        string $workingDirectory,
        int $timeoutMs,
    ): WorkerProcProcessHostChild {
        self::assertTimeout($timeoutMs);
        $this->assertStarted();

        if (
            $command === []
            || !\array_is_list($command)
            || !self::isSafePath($workingDirectory)
        ) {
            throw WorkerStartFailedException::childStartFailed();
        }

        foreach ($command as $part) {
            if (!self::isSafeCommandPart($part)) {
                throw WorkerStartFailedException::childStartFailed();
            }
        }

        $response = $this->request(
            operation: WorkerProcProcessHostProtocol::OPERATION_SPAWN,
            payload: [
                'command' => $command,
                'working_directory' => $workingDirectory,
            ],
            timeoutMs: $timeoutMs,
            childStartFailureAllowed: true,
        );

        if (
            \array_keys($response) !== ['child_id', 'pid']
            || !\is_string($response['child_id'])
            || \preg_match(
                '/\Achild-[1-9][0-9]*\z/',
                $response['child_id'],
            ) !== 1
            || !\is_int($response['pid'])
            || $response['pid'] < 1
            || isset($this->children[$response['child_id']])
        ) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $this->children[$response['child_id']] = $response['pid'];

        return new WorkerProcProcessHostChild(
            id: $response['child_id'],
            pid: $response['pid'],
        );
    }

    public function pollExit(
        string $childId,
    ): ?WorkerProcessExit {
        $pid = $this->knownChildPid($childId);

        $response = $this->request(
            operation: WorkerProcProcessHostProtocol::OPERATION_POLL,
            payload: ['child_id' => $childId],
            timeoutMs: $this->requestTimeoutMs,
        );

        if (\array_keys($response) === ['state']) {
            if ($response['state'] !== 'running') {
                throw WorkerStartFailedException::processHostFailed();
            }

            return null;
        }

        if (
            \array_keys($response) !== [
                'exit_code',
                'expected',
                'pid',
                'signaled',
                'state',
                'terminating_signal',
            ]
            || $response['state'] !== 'exited'
            || $response['pid'] !== $pid
            || !\is_int($response['exit_code'])
            || $response['exit_code'] < 0
            || !\is_bool($response['signaled'])
            || !\is_int($response['terminating_signal'])
            || $response['terminating_signal'] < 0
            || !\is_bool($response['expected'])
            || (
                !$response['signaled']
                && $response['terminating_signal'] !== 0
            )
            || (
                $response['signaled']
                && $response['terminating_signal'] < 1
            )
        ) {
            throw WorkerStartFailedException::processHostFailed();
        }

        return new WorkerProcessExit(
            pid: $pid,
            exitCode: $response['exit_code'],
            signaled: $response['signaled'],
            terminatingSignal: $response['terminating_signal'],
            expected: $response['expected'],
        );
    }

    public function terminate(string $childId): void
    {
        $this->acknowledgeChildOperation(
            WorkerProcProcessHostProtocol::OPERATION_TERMINATE,
            $childId,
        );
    }

    public function kill(string $childId): void
    {
        $this->acknowledgeChildOperation(
            WorkerProcProcessHostProtocol::OPERATION_KILL,
            $childId,
        );
    }

    public function close(string $childId): void
    {
        $this->acknowledgeChildOperation(
            WorkerProcProcessHostProtocol::OPERATION_CLOSE,
            $childId,
        );

        unset($this->children[$childId]);
    }

    public function shutdown(): void
    {
        if (!$this->started()) {
            $this->reset();

            return;
        }

        try {
            $response = $this->request(
                operation: WorkerProcProcessHostProtocol::OPERATION_SHUTDOWN,
                payload: [],
                timeoutMs: $this->requestTimeoutMs,
            );

            if (
                \array_keys($response) !== ['acknowledged']
                || $response['acknowledged'] !== true
            ) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $this->children = [];
            $this->closeConnection();

            $this->waitForHostExit(
                self::deadlineNs(
                    self::HOST_FALLBACK_SHUTDOWN_MS,
                ),
            );

            $this->closeProcessResource();
            $this->reset();
        } catch (\Throwable $exception) {
            /*
             * Closing the protocol connection is the host's parent-death signal.
             * Give the host enough time to terminate and reap remaining workers
             * before resorting to a hard host kill.
             */
            $this->forceCloseHost(
                allowCleanup: true,
            );

            if ($exception instanceof WorkerStartFailedException) {
                throw $exception;
            }

            throw WorkerStartFailedException::processHostFailed();
        }
    }

    private function acknowledgeChildOperation(
        string $operation,
        string $childId,
    ): void {
        $this->knownChildPid($childId);

        $response = $this->request(
            operation: $operation,
            payload: ['child_id' => $childId],
            timeoutMs: $this->requestTimeoutMs,
        );

        if (
            \array_keys($response) !== ['acknowledged']
            || $response['acknowledged'] !== true
        ) {
            throw WorkerStartFailedException::processHostFailed();
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    private function request(
        string $operation,
        array $payload,
        int $timeoutMs,
        bool $childStartFailureAllowed = false,
    ): array {
        self::assertTimeout($timeoutMs);
        $this->assertStarted();

        $requestId = $this->nextRequestId();
        $deadlineNs = self::deadlineNs($timeoutMs);
        $frame = $this->protocol->encodeRequest(
            requestId: $requestId,
            operation: $operation,
            payload: $payload,
        );

        $this->writeFrame($frame, $deadlineNs);
        $response = $this->protocol->decodeResponse(
            $this->readFrame($deadlineNs),
        );

        if ($response['request_id'] !== $requestId) {
            throw WorkerStartFailedException::processHostFailed();
        }

        if ($response['status'] === WorkerProcProcessHostProtocol::STATUS_ERROR) {
            $reason = $response['payload']['reason'] ?? null;

            if (
                $childStartFailureAllowed
                && $reason
                === WorkerProcProcessHostProtocol::ERROR_CHILD_START_FAILED
            ) {
                throw WorkerStartFailedException::childStartFailed();
            }

            throw WorkerStartFailedException::processHostFailed();
        }

        return $response['payload'];
    }

    private function writeFrame(string $frame, int $deadlineNs): void
    {
        $connection = $this->connection;

        if (!\is_resource($connection)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $remaining = $frame;

        while ($remaining !== '') {
            $read = null;
            $write = [$connection];
            $except = null;
            [$seconds, $microseconds] = self::selectTimeout($deadlineNs);

            $selected = @\stream_select(
                $read,
                $write,
                $except,
                $seconds,
                $microseconds,
            );

            if ($selected === false) {
                /*
                 * SIGTERM/SIGINT may interrupt stream_select() after the supervisor
                 * signal handler has recorded shutdown intent. Retry while the host and
                 * connection remain live and the deterministic deadline has not expired.
                 */
                if (
                    $this->hostRunning()
                    && !@\feof($connection)
                    && \hrtime(true) < $deadlineNs
                ) {
                    continue;
                }

                throw WorkerStartFailedException::processHostFailed();
            }

            if ($selected !== 1) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $written = @\fwrite($connection, $remaining);

            if (!\is_int($written) || $written < 1) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $remaining = \substr($remaining, $written);
        }

        if (!@\fflush($connection)) {
            throw WorkerStartFailedException::processHostFailed();
        }
    }

    private function readFrame(int $deadlineNs): string
    {
        $connection = $this->connection;

        if (!\is_resource($connection)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $buffer = '';

        while (true) {
            $read = [$connection];
            $write = null;
            $except = null;
            [$seconds, $microseconds] = self::selectTimeout($deadlineNs);

            $selected = @\stream_select(
                $read,
                $write,
                $except,
                $seconds,
                $microseconds,
            );

            if ($selected === false) {
                if (
                    $this->hostRunning()
                    && !@\feof($connection)
                    && \hrtime(true) < $deadlineNs
                ) {
                    continue;
                }

                throw WorkerStartFailedException::processHostFailed();
            }

            if ($selected !== 1) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $remaining = WorkerProcProcessHostProtocol::MAX_FRAME_BYTES
                + 1
                - \strlen($buffer);

            if ($remaining < 1) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $chunk = @\fread($connection, $remaining);

            if ($chunk === false || $chunk === '') {
                throw WorkerStartFailedException::processHostFailed();
            }

            $buffer .= $chunk;
            $newline = \strpos($buffer, "\n");

            if ($newline === false) {
                continue;
            }

            if ($newline !== \strlen($buffer) - 1) {
                throw WorkerStartFailedException::processHostFailed();
            }

            return $buffer;
        }
    }

    private function connect(int $port, int $deadlineNs): mixed
    {
        do {
            if (!$this->hostRunning()) {
                throw WorkerStartFailedException::processHostFailed();
            }

            $remainingMs = self::remainingMs($deadlineNs);
            $timeoutSeconds = \max(
                0.001,
                \min(0.05, $remainingMs / 1_000),
            );

            $connection = @\stream_socket_client(
                'tcp://127.0.0.1:' . $port,
                $errorCode,
                $errorMessage,
                $timeoutSeconds,
                \STREAM_CLIENT_CONNECT,
            );

            if (\is_resource($connection)) {
                if (!@\stream_set_blocking($connection, false)) {
                    @\fclose($connection);

                    throw WorkerStartFailedException::processHostFailed();
                }

                return $connection;
            }

            \usleep(self::CONNECT_RETRY_US);
        } while (\hrtime(true) < $deadlineNs);

        throw WorkerStartFailedException::processHostFailed();
    }

    private function knownChildPid(string $childId): int
    {
        $this->assertStarted();

        if (
            \preg_match('/\Achild-[1-9][0-9]*\z/', $childId) !== 1
            || !isset($this->children[$childId])
        ) {
            throw WorkerStartFailedException::childExited();
        }

        return $this->children[$childId];
    }

    private function nextRequestId(): int
    {
        if ($this->nextRequestId > self::MAX_REQUEST_ID) {
            throw WorkerStartFailedException::processHostFailed();
        }

        return $this->nextRequestId++;
    }

    private function assertStarted(): void
    {
        if (!$this->started() || !$this->hostRunning()) {
            throw WorkerStartFailedException::processHostFailed();
        }
    }

    private function started(): bool
    {
        return \is_resource($this->process)
            && \is_resource($this->connection);
    }

    private function hostRunning(): bool
    {
        if (!\is_resource($this->process)) {
            return false;
        }

        $status = @\proc_get_status($this->process);

        return $status['running'];
    }

    private function waitForHostExit(int $deadlineNs): void
    {
        while ($this->hostRunning()) {
            if (\hrtime(true) >= $deadlineNs) {
                throw WorkerStartFailedException::processHostFailed();
            }

            \usleep(self::CONNECT_RETRY_US);
        }
    }

    private function forceCloseHost(bool $allowCleanup): void
    {
        $hadConnection = \is_resource(
            $this->connection,
        );

        /*
         * EOF on the authenticated protocol connection tells the host that its
         * supervisor disappeared. The host then owns termination and reap of all
         * remaining worker process resources.
         */
        $this->closeConnection();

        if (\is_resource($this->process)) {
            if ($allowCleanup && $hadConnection) {
                $deadlineNs = self::deadlineNs(
                    self::HOST_FALLBACK_SHUTDOWN_MS,
                );

                while (
                    $this->hostRunning()
                    && \hrtime(true) < $deadlineNs
                ) {
                    \usleep(self::CONNECT_RETRY_US);
                }
            }

            if ($this->hostRunning()) {
                @\proc_terminate(
                    $this->process,
                    9,
                );
            }

            @\proc_close($this->process);
        }

        $this->reset();
    }

    private function closeConnection(): void
    {
        if (\is_resource($this->connection)) {
            @\fclose($this->connection);
        }

        $this->connection = null;
    }

    private function closeProcessResource(): void
    {
        if (\is_resource($this->process)) {
            @\proc_close($this->process);
        }

        $this->process = null;
    }

    private function reset(): void
    {
        $this->process = null;
        $this->connection = null;
        $this->children = [];
        $this->nextRequestId = 1;
        $this->requestTimeoutMs = 1_000;
    }

    private static function reserveLoopbackPort(): int
    {
        $server = @\stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorMessage,
            \STREAM_SERVER_BIND | \STREAM_SERVER_LISTEN,
        );

        if (!\is_resource($server)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $name = @\stream_socket_get_name($server, false);
        @\fclose($server);

        if (!\is_string($name)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $separator = \strrpos($name, ':');
        $value = $separator === false
            ? ''
            : \substr($name, $separator + 1);

        if (!\ctype_digit($value)) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $port = (int)$value;

        if ($port < 1 || $port > 65_535) {
            throw WorkerStartFailedException::processHostFailed();
        }

        return $port;
    }

    private static function deadlineNs(int $timeoutMs): int
    {
        self::assertTimeout($timeoutMs);

        $nowNs = \hrtime(true);

        if (
            !\is_int($nowNs)
            || $timeoutMs > \intdiv(
                \PHP_INT_MAX - $nowNs,
                1_000_000,
            )
        ) {
            throw WorkerStartFailedException::processHostFailed();
        }

        return $nowNs + ($timeoutMs * 1_000_000);
    }

    /** @return array{0: non-negative-int, 1: int<0, 999999>} */
    private static function selectTimeout(int $deadlineNs): array
    {
        $remainingNs = $deadlineNs - \hrtime(true);

        if ($remainingNs <= 0) {
            throw WorkerStartFailedException::processHostFailed();
        }

        $seconds = \intdiv($remainingNs, 1_000_000_000);
        $microseconds = (int)\intdiv(
            $remainingNs % 1_000_000_000,
            1_000,
        );

        return [$seconds, $microseconds];
    }

    private static function remainingMs(int $deadlineNs): int
    {
        $remainingNs = $deadlineNs - \hrtime(true);

        if ($remainingNs <= 0) {
            throw WorkerStartFailedException::processHostFailed();
        }

        return \max(1, (int)\ceil($remainingNs / 1_000_000));
    }

    private static function assertTimeout(int $timeoutMs): void
    {
        if (
            $timeoutMs < 1
            || $timeoutMs > self::MAX_TIMEOUT_MS
        ) {
            throw WorkerStartFailedException::processHostFailed();
        }
    }

    private static function isSafeCommandPart(mixed $part): bool
    {
        return \is_string($part)
            && $part !== ''
            && \trim($part) === $part
            && \strlen($part) <= 8192
            && \preg_match('/[\x00-\x1F\x7F]/', $part) !== 1;
    }

    private static function isSafePath(string $path): bool
    {
        return $path !== ''
            && \trim($path) === $path
            && \strlen($path) <= 8192
            && \preg_match('/[\x00-\x1F\x7F]/', $path) !== 1;
    }
}
