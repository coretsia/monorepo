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

namespace Coretsia\Contracts\Runtime;

/**
 * Opaque low-level UnitOfWork lifecycle handle.
 *
 * The handle carries only the exported safe context shape. It MUST NOT expose
 * Stopwatch tokens, wall-clock timestamps, persistence handles, service
 * instances, transport objects, or runtime internals.
 *
 * KernelRuntime implementations may associate private lifecycle state with this
 * object instance. Adapters must pass the exact handle returned by
 * KernelRuntimeInterface::beginUnitOfWork() back to
 * KernelRuntimeInterface::afterUnitOfWork().
 */
final readonly class UnitOfWorkHandle
{
    /**
     * @var array<string, true>
     */
    private const array CONTEXT_KEYS = [
        'attributes' => true,
        'correlationId' => true,
        'type' => true,
        'uowId' => true,
    ];

    /**
     * @var array{
     *     attributes: array<string, mixed>,
     *     correlationId: string,
     *     type: string,
     *     uowId: string
     * }
     */
    private array $context;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(array $context)
    {
        self::assertContext($context);

        $this->context = [
            'attributes' => $context['attributes'],
            'correlationId' => $context['correlationId'],
            'type' => $context['type'],
            'uowId' => $context['uowId'],
        ];
    }

    /**
     * Returns the exported safe UnitOfWork context.
     *
     * This array is hook/adaptor metadata only. It MUST NOT contain Stopwatch
     * tokens or wall-clock timestamps.
     *
     * @return array{
     *     attributes: array<string, mixed>,
     *     correlationId: string,
     *     type: string,
     *     uowId: string
     * }
     */
    public function context(): array
    {
        return $this->context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function assertContext(array $context): void
    {
        foreach (self::CONTEXT_KEYS as $requiredKey => $_) {
            if (!\array_key_exists($requiredKey, $context)) {
                throw new \InvalidArgumentException('Invalid UnitOfWork handle context.');
            }
        }

        if (
            !\is_array($context['attributes'])
            || !\is_string($context['correlationId'])
            || !\is_string($context['type'])
            || !\is_string($context['uowId'])
        ) {
            throw new \InvalidArgumentException('Invalid UnitOfWork handle context.');
        }

        foreach (['startedAt', 'startedAtToken', 'finishedAt'] as $forbiddenKey) {
            if (\array_key_exists($forbiddenKey, $context)) {
                throw new \InvalidArgumentException('Invalid UnitOfWork handle context.');
            }
        }

        foreach ($context as $key => $_) {
            if (!\is_string($key) || !isset(self::CONTEXT_KEYS[$key])) {
                throw new \InvalidArgumentException('Invalid UnitOfWork handle context.');
            }
        }
    }
}
