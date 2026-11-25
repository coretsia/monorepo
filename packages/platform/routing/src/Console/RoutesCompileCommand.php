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
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Exception\ReservedRouteViolationException;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactWriteFailedException;
use Coretsia\Platform\Routing\Exception\RoutesCommandTargetInvalidException;
use Psr\Container\ContainerInterface;

/**
 * Explicitly compiles and publishes a validated target-local routes artifact.
 *
 * Only safe error codes and bounded reason tokens reach the CLI output port.
 * Source discovery, compilation and publication occur only during run().
 *
 * @internal
 */
final readonly class RoutesCompileCommand implements CommandInterface
{
    public const string NAME = 'routes:compile';
    public const string SUMMARY = 'Compile and publish a target-local routes artifact.';
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
            $compiler = self::service($container, RoutesCompiler::class);
            $envelope = $compiler->compile();

            // Resolve publication services only after complete source validation.
            $writer = self::service($container, RoutesArtifactWriter::class);
            $writer->write($envelope);
        } catch (
            RoutesCommandTargetInvalidException
            |KernelOpsFailedException
            |RouteDefinitionInvalidException
            |ReservedRouteViolationException
            |RoutesArtifactInvalidException
            |RoutesArtifactWriteFailedException $failure
        ) {
            $output->error($failure->errorCode(), $failure->reason());
            return 1;
        }

        $output->json([
            'app' => $envelope['payload']['app'],
            'artifact' => RoutesArtifactSchema::BASENAME,
            'route_count' => \count($envelope['payload']['routes']),
        ]);
        return 0;
    }

    /**
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    private static function service(ContainerInterface $container, string $id): object
    {
        try {
            $service = $container->get($id);
        } catch (\Throwable) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        if (!\is_object($service) || !($service instanceof $id)) {
            throw RoutesCommandTargetInvalidException::withReason(
                RoutesCommandTargetInvalidException::REASON_HOST_INVALID,
            );
        }

        /** @var T $service */
        return $service;
    }
}
