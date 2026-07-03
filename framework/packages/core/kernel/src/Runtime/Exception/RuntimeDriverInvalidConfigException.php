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
 * Deterministic runtime driver matrix invalid-config failure.
 *
 * This exception is used when Kernel detects a runtime-driver matrix
 * configuration problem that is not a driver conflict, such as a selected
 * non-classic HTTP driver without the required module plan support.
 *
 * The message is intentionally stable and safe. It contains only the package
 * error code and a stable reason token:
 *
 *     CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG: reason_token
 *
 * Diagnostics intentionally expose only canonical runtime driver ids, canonical
 * required module ids, and stable reason tokens. They must not expose ModulePlan
 * dumps, config dumps, config paths, config values, env values, adapter
 * internals, stack traces, previous throwable messages, payload dumps,
 * filesystem paths, process details, or generated artifact payloads.
 */
final class RuntimeDriverInvalidConfigException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_RUNTIME_DRIVER_MATRIX_INVALID_CONFIG';

    public const string REASON_REQUIRES_PLATFORM_HTTP_MODULE = 'requires-platform-http-module';
    public const string REASON_CONFIG_KEY_MISSING = 'config-key-missing';
    public const string REASON_CONFIG_KEY_INVALID = 'config-key-invalid';
    public const string REASON_WORKER_TASK_TYPE_MISSING = 'worker-task-type-missing';
    public const string REASON_WORKER_TASK_TYPE_INVALID = 'worker-task-type-invalid';

    private const string MODULE_PLATFORM_HTTP = 'platform.http';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_REQUIRES_PLATFORM_HTTP_MODULE => true,
        self::REASON_CONFIG_KEY_MISSING => true,
        self::REASON_CONFIG_KEY_INVALID => true,
        self::REASON_WORKER_TASK_TYPE_MISSING => true,
        self::REASON_WORKER_TASK_TYPE_INVALID => true,
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
    private readonly array $requiredModuleIds;

    /**
     * @param list<string> $activeDriverIds
     * @param list<string> $requiredModuleIds
     */
    private function __construct(
        private readonly string $reason,
        array $activeDriverIds,
        array $requiredModuleIds,
    ) {
        if ($reason === '') {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-reason-empty');
        }

        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-reason-invalid');
        }

        $this->activeDriverIds = self::normalizeDriverIds($activeDriverIds);
        $this->requiredModuleIds = self::normalizeRequiredModuleIds($requiredModuleIds);

        parent::__construct(self::message($this->reason));
    }

    /**
     * @param list<string> $activeDriverIds
     */
    public static function requiresPlatformHttpModule(array $activeDriverIds): self
    {
        return new self(
            self::REASON_REQUIRES_PLATFORM_HTTP_MODULE,
            $activeDriverIds,
            [self::MODULE_PLATFORM_HTTP],
        );
    }

    public static function configKeyMissing(): self
    {
        return new self(
            self::REASON_CONFIG_KEY_MISSING,
            [],
            [],
        );
    }

    public static function configKeyInvalid(): self
    {
        return new self(
            self::REASON_CONFIG_KEY_INVALID,
            [],
            [],
        );
    }

    /**
     * @param list<string> $activeDriverIds
     */
    public static function workerTaskTypeMissing(array $activeDriverIds = []): self
    {
        return new self(
            self::REASON_WORKER_TASK_TYPE_MISSING,
            $activeDriverIds,
            [],
        );
    }

    /**
     * @param list<string> $activeDriverIds
     */
    public static function workerTaskTypeInvalid(array $activeDriverIds = []): self
    {
        return new self(
            self::REASON_WORKER_TASK_TYPE_INVALID,
            $activeDriverIds,
            [],
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
    public function requiredModuleIds(): array
    {
        return $this->requiredModuleIds;
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
    private static function normalizeDriverIds(array $driverIds): array
    {
        if (!\array_is_list($driverIds)) {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-active-driver-ids-must-be-list');
        }

        if ($driverIds === []) {
            return [];
        }

        $set = [];

        foreach ($driverIds as $driverId) {
            if (!\is_string($driverId) || !isset(self::CANONICAL_DRIVER_IDS[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-invalid-config-active-driver-id-invalid');
            }

            if (isset($set[$driverId])) {
                throw new \InvalidArgumentException('runtime-driver-invalid-config-active-driver-id-duplicate');
            }

            $set[$driverId] = true;
        }

        \uksort(
            $set,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_keys($set);
    }

    /**
     * @param list<string> $moduleIds
     *
     * @return list<string>
     */
    private static function normalizeRequiredModuleIds(array $moduleIds): array
    {
        if (!\array_is_list($moduleIds)) {
            throw new \InvalidArgumentException('runtime-driver-invalid-config-required-module-ids-must-be-list');
        }

        if ($moduleIds === []) {
            return [];
        }

        $set = [];

        foreach ($moduleIds as $moduleId) {
            if ($moduleId !== self::MODULE_PLATFORM_HTTP) {
                throw new \InvalidArgumentException('runtime-driver-invalid-config-required-module-id-invalid');
            }

            if (isset($set[$moduleId])) {
                throw new \InvalidArgumentException('runtime-driver-invalid-config-required-module-id-duplicate');
            }

            $set[$moduleId] = true;
        }

        \uksort(
            $set,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        return \array_keys($set);
    }
}
