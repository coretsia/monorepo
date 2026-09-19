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

namespace Coretsia\Kernel\Runtime\Exception;

/**
 * Deterministic runtime driver matrix conflict failure.
 *
 * This exception is used when Kernel detects an invalid runtime driver
 * composition, such as multiple active HTTP drivers.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT: reason_token
 *
 * Diagnostics intentionally expose only canonical runtime driver ids and stable
 * reason tokens. They must not expose config paths, config values, env values,
 * adapter internals, stack traces, previous throwable messages, payload dumps,
 * filesystem paths, process details, or generated artifact payloads.
 */
final class RuntimeDriverConflictException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_RUNTIME_DRIVER_MATRIX_CONFLICT';

    public const string REASON_MULTIPLE_HTTP_DRIVERS = 'multiple-http-drivers';
    public const string REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER = 'worker-http-conflicts-with-http-driver';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_MULTIPLE_HTTP_DRIVERS => true,
        self::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array CANONICAL_DRIVER_IDS = [
        'bg.worker_queue' => true,
        'http.classic' => true,
        'http.frankenphp' => true,
        'http.roadrunner' => true,
        'http.swoole' => true,
        'http.worker' => true,
    ];

    /**
     * @var list<string>
     */
    private readonly array $activeDriverIds;

    /**
     * @var list<string>
     */
    private readonly array $conflictingDriverIds;

    /**
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    private function __construct(
        private readonly string $reason,
        array $activeDriverIds,
        array $conflictingDriverIds,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('runtime-driver-conflict-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('runtime-driver-conflict-reason-invalid');
        }

        $this->activeDriverIds = self::normalizeDriverIds(
            driverIds: $activeDriverIds,
            field: 'active-driver-ids',
        );

        $this->conflictingDriverIds = self::normalizeDriverIds(
            driverIds: $conflictingDriverIds,
            field: 'conflicting-driver-ids',
        );

        parent::__construct(self::message($this->reason));
    }

    /**
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    public static function multipleHttpDrivers(
        array $activeDriverIds,
        array $conflictingDriverIds,
    ): self {
        return new self(
            self::REASON_MULTIPLE_HTTP_DRIVERS,
            $activeDriverIds,
            $conflictingDriverIds,
        );
    }

    /**
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    public static function workerHttpConflictsWithHttpDriver(
        array $activeDriverIds,
        array $conflictingDriverIds,
    ): self {
        return new self(
            self::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER,
            $activeDriverIds,
            $conflictingDriverIds,
        );
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return list<string>
     */
    public function activeDriverIds(): array
    {
        return $this->activeDriverIds;
    }

    /**
     * @return list<string>
     */
    public function conflictingDriverIds(): array
    {
        return $this->conflictingDriverIds;
    }

    private static function message(string $reason): string
    {
        return self::ERROR_CODE . ': ' . $reason;
    }

    /**
     * @param list<string> $driverIds
     *
     * @return list<string>
     */
    private static function normalizeDriverIds(
        array $driverIds,
        string $field,
    ): array {
        if (!\array_is_list($driverIds)) {
            throw new \InvalidArgumentException('runtime-driver-conflict-' . $field . '-must-be-list');
        }

        if ($driverIds === []) {
            throw new \InvalidArgumentException('runtime-driver-conflict-' . $field . '-empty');
        }

        $set = [];

        foreach ($driverIds as $driverId) {
            if (!\is_string($driverId) || !isset(self::CANONICAL_DRIVER_IDS[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-conflict-' . $field . '-driver-id-invalid');
            }

            if (isset($set[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-conflict-' . $field . '-driver-id-duplicate');
            }

            $set[$driverId] = true;
        }

        \uksort(
            $set,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_keys($set);
    }
}
