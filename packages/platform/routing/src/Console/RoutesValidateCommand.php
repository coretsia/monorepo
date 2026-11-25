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

namespace Coretsia\Platform\Routing\Console;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;
use Psr\Container\ContainerInterface;

/**
 * Validates complete target-local source routes without publishing artifacts.
 *
 * No artifact writer, publication directory, or owner-local lock is resolved.
 * All successful validation and resource-limit checks belong to RoutesCompiler.
 *
 * @internal
 */
final readonly class RoutesValidateCommand implements CommandInterface
{
    public const string NAME = 'routes:validate';
    public const string SUMMARY = 'Validate target-local routes without publication.';
    public const string GROUP = 'routes';
    public const bool HIDDEN = false;
    public const array ARGUMENTS = [];
    public const array OPTIONS = [
        [
            'name' => 'app',
            'summary' => 'Application target (web or api).',
            'value' => 'required',
            'repeatable' => false,
        ],
    ];

    public function __construct(private RoutesCommandTargetResolver $targets)
    {
    }

    public function name(): string
    {
        return self::NAME;
    }

    public function run(InputInterface $input, OutputInterface $output): int
    {
        try {
            $container = $this->targets->resolve($input, self::NAME);
            $compiler = self::compiler($container);
            $envelope = $compiler->compile();
        } catch (
            RoutesCommandTargetInvalidException
            |KernelOpsFailedException
            |RouteDefinitionInvalidException
            |ReservedRouteViolationException
            |RoutesArtifactInvalidException $failure
        ) {
            $output->error($failure->errorCode(), $failure->reason());
            return 1;
        }

        $output->json([
            'app' => $envelope['payload']['app'],
            'valid' => true,
            'route_count' => \count($envelope['payload']['routes']),
        ]);
        return 0;
    }

    private static function compiler(ContainerInterface $container): RoutesCompiler
    {
        try {
            $compiler = $container->get(RoutesCompiler::class);
        } catch (\Throwable) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (!$compiler instanceof RoutesCompiler) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        return $compiler;
    }
}
