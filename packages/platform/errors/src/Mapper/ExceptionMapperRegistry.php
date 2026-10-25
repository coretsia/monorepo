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

namespace Coretsia\Platform\Errors\Mapper;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlingContext;
use Coretsia\Contracts\Observability\Errors\ExceptionMapperInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deterministic, stateless dispatch of tagged exception mappers.
 *
 * TagRegistry owns discovery order and duplicate registration policy. Mapper
 * services are resolved lazily for each invocation and never cached here.
 * Invalid candidates are isolated without changing subsequent dispatch order.
 *
 * This registry does not own the canonical fallback descriptor.
 */
final readonly class ExceptionMapperRegistry
{
    public function __construct(
        private TagRegistry $tags,
        private ContainerInterface $container,
        private LoggerInterface $logger,
        private bool $reportingEnabled,
        private bool $mapperFailureLoggingEnabled,
    ) {
    }

    public function map(Throwable $throwable, ?ErrorHandlingContext $context = null): ?ErrorDescriptor
    {
        foreach ($this->tags->all(ReservedTags::ERROR_MAPPER) as $registration) {
            if ($registration->meta() !== []) {
                $this->logMapperFailure('invalid_metadata');

                continue;
            }

            try {
                $mapper = $this->container->get($registration->id());
            } catch (Throwable) {
                $this->logMapperFailure('service_resolution_failed');

                continue;
            }

            if (!$mapper instanceof ExceptionMapperInterface) {
                $this->logMapperFailure('invalid_service');

                continue;
            }

            try {
                $descriptor = $mapper->map($throwable, $context);
            } catch (Throwable) {
                $this->logMapperFailure('mapper_failed');

                continue;
            }

            if ($descriptor !== null) {
                return $descriptor;
            }
        }

        return null;
    }

    /**
     * Emits only fixed low-cardinality diagnostics; never includes mapper ids,
     * exceptions, messages, traces, or caller-supplied context.
     */
    private function logMapperFailure(string $reason): void
    {
        if (!$this->reportingEnabled || !$this->mapperFailureLoggingEnabled) {
            return;
        }

        try {
            $this->logger->warning('error.mapper_failed', [
                'operation' => 'map',
                'reason' => $reason,
            ]);
        } catch (Throwable) {
            // Optional mapper diagnostics never affect discovery or selection.
        }
    }
}
