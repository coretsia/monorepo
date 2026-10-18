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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Kernel\Ops\OpsResult;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;

/** One invocation-local, strict Kernel Ops boundary spy. */
final class RecordingKernelOpsPort implements KernelOpsInterface
{
    /** @var list<array{method:string,appTarget:string}> */
    public array $calls = [];

    /** @var array<string,OpsResult> */
    private array $results = [];

    /** @param array<string,mixed> $data */
    public static function success(string $operation, string $target, string $preset, array $data): OpsResult
    {
        $normalized = JsonLikeNormalizer::normalize($data, 'data');
        if (!\is_array($normalized)) {
            throw new \LogicException('ops-fixture-data-invalid');
        }

        return new OpsResult($operation, $target, $preset, 'success', null, $normalized);
    }

    public function respondTo(string $method, OpsResult $result): void
    {
        if (!\in_array($method, [
            'validateConfig',
            'debugConfig',
            'compileConfig',
            'hashConfig',
            'verifyCache',
            'debugModules',
        ], true)) {
            throw new \InvalidArgumentException('ops-fixture-method-invalid');
        }
        $this->results[$method] = $result;
    }

    private function record(string $method, KernelOpsRequest $request): OpsResult
    {
        $this->calls[] = ['method' => $method, 'appTarget' => $request->appTarget()];
        return $this->results[$method] ?? throw new \LogicException('unexpected-kernel-ops-call');
    }

    public function validateConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }

    public function debugConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }

    public function compileConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }

    public function hashConfig(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }

    public function verifyCache(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }

    public function debugModules(KernelOpsRequest $request): OpsResult
    {
        return $this->record(__FUNCTION__, $request);
    }
}
