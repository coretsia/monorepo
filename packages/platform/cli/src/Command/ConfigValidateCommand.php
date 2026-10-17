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
final readonly class ConfigValidateCommand implements CommandInterface
{
    public const string NAME = 'config:validate';
    public const string SUMMARY = 'Validate configuration for the configured target preset.';
    public const string GROUP = 'config';
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
        $result = $this->kernelOps->validateConfig($request);

        if ($result->operation() !== 'config.validate') {
            throw new \UnexpectedValueException('cli-kernel-ops-operation-invalid');
        }

        $data = $result->data();
        if ($result->outcome() === 'success' && !\is_bool($data['valid'] ?? null)) {
            throw new \UnexpectedValueException('cli-kernel-ops-data-invalid');
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

        return $result->outcome() === 'success' && $data['valid'] === true ? 0 : 1;
    }
}
