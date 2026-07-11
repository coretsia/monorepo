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

namespace Coretsia\Platform\Worker\Internal;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Platform\Worker\Exception\WorkerStartFailedException;
use Coretsia\Platform\Worker\Runtime\WorkerPoolSpec;

/**
 * Package-local mapper from Worker-owned runtime inputs to Kernel runtime
 * driver contributions.
 *
 * This class owns the worker.task_type -> runtime-driver mapping.
 *
 * @internal
 */
final class WorkerRuntimeDriverContributions
{
    private const string CONFIG_WORKER_TASK_TYPE = 'worker.task_type';

    private const string TASK_TYPE_HTTP = 'http';
    private const string TASK_TYPE_QUEUE = 'queue';

    public static function fromConfig(ConfigRepositoryInterface $config): RuntimeDriverContributions
    {
        try {
            if (!$config->has(self::CONFIG_WORKER_TASK_TYPE)) {
                throw WorkerStartFailedException::invalidState();
            }

            $taskType = $config->get(self::CONFIG_WORKER_TASK_TYPE);
        } catch (WorkerStartFailedException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw WorkerStartFailedException::invalidState();
        }

        if (!\is_string($taskType)) {
            throw WorkerStartFailedException::invalidState();
        }

        return self::fromTaskType($taskType);
    }

    public static function fromSpec(WorkerPoolSpec $spec): RuntimeDriverContributions
    {
        return self::fromTaskType($spec->taskType());
    }

    private static function fromTaskType(string $taskType): RuntimeDriverContributions
    {
        return match ($taskType) {
            self::TASK_TYPE_QUEUE => RuntimeDriverContributions::fromDrivers(
                httpDrivers: [],
                backgroundDrivers: [BackgroundDriver::WORKER_QUEUE],
            ),

            self::TASK_TYPE_HTTP => RuntimeDriverContributions::fromDrivers(
                httpDrivers: [HttpDriver::WORKER],
                backgroundDrivers: [],
            ),

            default => throw WorkerStartFailedException::invalidState(),
        };
    }
}
