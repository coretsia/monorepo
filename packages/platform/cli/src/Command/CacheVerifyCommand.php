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

namespace Coretsia\Platform\Cli\Command;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;

/**
 * Delegates one operation to the Kernel-owned Ops port.
 *
 * @internal
 */
final readonly class CacheVerifyCommand implements CommandInterface
{
    public const string NAME = 'cache:verify';
    public const string SUMMARY = 'Verify the current artifact generation against expected inputs.';
    public const string GROUP = 'cache';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [
        [
            'name' => 'target',
            'summary' => 'Application target: web, api, console, or worker.',
            'value' => 'required',
            'repeatable' => false,
        ],
    ];

    public function __construct(
        private KernelOpsInterface $kernelOps,
        private KernelOpsRequestResolver $requests,
    ) {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        $request = $this->requests->resolve($input);
        $result = $this->kernelOps->verifyCache($request);

        if ($result->operation() !== 'cache.verify') {
            throw new \UnexpectedValueException('cli-kernel-ops-operation-invalid');
        }

        if ($result->outcome() === 'success') {
            $data = $result->data();
            if (
                !\in_array($data['state'] ?? null, ['clean', 'dirty', 'invalid'], true)
                || !\is_string($data['expected_generation_id'] ?? null)
                || !\array_key_exists('current_generation_id', $data)
                || ($data['current_generation_id'] !== null && !\is_string($data['current_generation_id']))
                || !\is_array($data['artifacts'] ?? null)
                || !\array_is_list($data['artifacts'])
                || \count($data['artifacts']) !== 4
            ) {
                throw new \UnexpectedValueException('cli-kernel-ops-data-invalid');
            }

            foreach ($data['artifacts'] as $artifact) {
                if (
                    !\is_array($artifact)
                    || !\is_string($artifact['name'] ?? null)
                    || !\is_string($artifact['basename'] ?? null)
                    || !\in_array($artifact['status'] ?? null, ['clean', 'dirty', 'invalid'], true)
                    || !\is_string($artifact['reason'] ?? null)
                    || !\is_int($artifact['expected_byte_count'] ?? null)
                    || !\array_key_exists('existing_byte_count', $artifact)
                    || ($artifact['existing_byte_count'] !== null
                        && !\is_int($artifact['existing_byte_count']))
                ) {
                    throw new \UnexpectedValueException('cli-kernel-ops-data-invalid');
                }
            }
        }

        $payload = [
            'app_target' => $result->appTarget(),
            'data' => $result->data(),
            'operation' => $result->operation(),
            'outcome' => $result->outcome(),
        ];

        if ($result->preset() !== null) {
            $payload['preset'] = $result->preset();
        }

        if ($result->reason() !== null) {
            $payload['reason'] = $result->reason();
        }

        $output->json($payload);

        return match ($result->outcome()) {
            'handled_error' => 1,
            'success' => match ($data['state']) {
                'clean' => 0,
                'dirty' => 2,
                'invalid' => 3,
            },
        };
    }
}
