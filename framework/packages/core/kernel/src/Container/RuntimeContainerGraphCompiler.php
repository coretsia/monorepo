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

namespace Coretsia\Kernel\Container;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Provider\ContainerProviderPlanResolver;
use Coretsia\Kernel\Module\ModuleResolution;

/**
 * Compiles the production runtime container graph from enabled module providers.
 *
 * Provider discovery and ordering consume one ModuleResolution snapshot. The
 * compiler instantiates providers only for the duration of one compile
 * operation, collects immutable definition sets in provider-plan order, and
 * delegates descriptor normalization to ContainerCompiler.
 *
 * @internal Kernel production runtime graph compilation service.
 */
final readonly class RuntimeContainerGraphCompiler
{
    public function __construct(
        private ContainerProviderPlanResolver $providerPlanResolver,
        private ContainerCompiler $containerCompiler,
        private ContainerGraphCompletenessValidator $completenessValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $compiledConfig
     */
    public function compile(
        ModuleResolution $moduleResolution,
        array $compiledConfig,
    ): DefinitionGraph {
        $context = new ContainerDefinitionContext($compiledConfig);
        $providerPlan = $this->providerPlanResolver->resolve(
            $moduleResolution,
        );

        /** @var list<ContainerDefinitionSet> $definitionSets */
        $definitionSets = [];

        foreach ($providerPlan->entries() as $entry) {
            $providerClass = $entry['providerClass'];

            /** @var class-string<ContainerDefinitionProviderInterface> $providerClass */
            $provider = self::instantiateProvider($providerClass);
            $definitions = new ContainerDefinitionBuilder();

            try {
                $provider->define(
                    $definitions,
                    $context,
                );

                $definitionSets[] = $definitions->build();
            } catch (ContainerDefinitionInvalidException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw self::providerInvalid($exception);
            }
        }

        $definitions = ContainerDefinitionSet::merge(
            ...$definitionSets,
        );

        $graph = $this->containerCompiler->compile($definitions);

        $this->completenessValidator->validate(
            graph: $graph,
            definitions: $definitions,
        );

        return $graph;
    }

    /**
     * @param class-string<ContainerDefinitionProviderInterface> $providerClass
     */
    private static function instantiateProvider(
        string $providerClass,
    ): ContainerDefinitionProviderInterface {
        try {
            $provider = new $providerClass();
        } catch (\Throwable $exception) {
            throw self::providerInvalid($exception);
        }

        if (!$provider instanceof ContainerDefinitionProviderInterface) {
            throw self::providerInvalid();
        }

        return $provider;
    }

    private static function providerInvalid(
        ?\Throwable $previous = null,
    ): ContainerDefinitionInvalidException {
        return ContainerDefinitionInvalidException::withReason(
            reason: ContainerDefinitionInvalidException::REASON_PROVIDER_INVALID,
            previous: $previous,
        );
    }
}
