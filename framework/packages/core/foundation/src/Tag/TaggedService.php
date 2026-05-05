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

namespace Coretsia\Foundation\Tag;

/**
 * Value object representing one tagged service registration.
 *
 * The `id` is the PSR-11 service id.
 * The `priority` participates in deterministic discovery ordering.
 * The `meta` map is preserved for owner-defined consumers but must not be
 * emitted by diagnostics unless the caller has explicitly redacted it.
 */
final readonly class TaggedService
{
    /**
     * @param array<string, mixed> $meta
     */
    public function __construct(
        public string $id,
        public int $priority = 0,
        public array $meta = [],
    ) {
        if ($id === '') {
            throw new \InvalidArgumentException('tagged-service-id-empty');
        }

        if (\trim($id) !== $id || \preg_match('/\s/u', $id) === 1) {
            throw new \InvalidArgumentException('tagged-service-id-whitespace-forbidden');
        }

        self::assertStringMap($meta);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function priority(): int
    {
        return $this->priority;
    }

    /**
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        return $this->meta;
    }

    /**
     * @param array<mixed> $meta
     */
    private static function assertStringMap(array $meta): void
    {
        foreach ($meta as $key => $_) {
            if (!\is_string($key)) {
                throw new \InvalidArgumentException('tagged-service-meta-key-must-be-string');
            }
        }
    }
}
