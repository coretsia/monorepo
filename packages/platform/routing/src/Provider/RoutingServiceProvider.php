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

namespace Coretsia\Platform\Routing\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Routing\RouterInterface;
use Coretsia\Contracts\Routing\RoutingArtifactReadinessInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Filesystem\AtomicFileWriter;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Foundation\Serialization\StablePhpArrayParser;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactPath;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactReader;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactWriter;
use Coretsia\Platform\Routing\Compile\ReservedRoutesGuard;
use Coretsia\Platform\Routing\Compile\RouteProviderRegistry;
use Coretsia\Platform\Routing\Compile\RoutesCompiler;
use Coretsia\Platform\Routing\Console\RoutesCommandTargetResolver;
use Coretsia\Platform\Routing\Console\RoutesCompileCommand;
use Coretsia\Platform\Routing\Console\RoutesValidateCommand;
use Coretsia\Platform\Routing\Observability\RoutingMatchObserver;
use Coretsia\Platform\Routing\Runtime\CompiledRouter;
use Coretsia\Platform\Routing\Runtime\CompiledRouteTable;
use Coretsia\Platform\Routing\Runtime\RoutingArtifactReadiness;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Canonical declarative source and compiled Routing service definitions.
 *
 * No providers or artifacts are loaded during registration or definition
 * construction. All Routing services are shared and resolved lazily. External
 * Foundation, Kernel, Redaction, and observability bindings are reused.
 *
 * @internal
 */
final class RoutingServiceProvider implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->assertDefinitionProviderRegistrationAllowed();
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        // Require the authoritative compiled config root; runtime values are
        // resolved from ConfigRepositoryInterface when the observer is created.
        $context->configRoot('routing');

        $definitions
            ->requireService(ContainerInterface::class)
            ->requireService(ConfigRepositoryInterface::class)
            ->requireService(TagRegistry::class)
            ->requireService(ModulePlan::class)
            ->requireService(RuntimePathContext::class)
            ->requireService(RuntimeDiagnosticsInput::class)
            ->requireService(ContextWriterInterface::class)
            ->requireService(TracerPortInterface::class)
            ->requireService(MeterPortInterface::class)
            ->requireService(Stopwatch::class)
            ->requireService(ScopedFileLock::class)
            ->requireService(SensitiveDataRedactorInterface::class)
            ->requireService(SensitiveKeyClassifierInterface::class)
            ->requireService(LoggerInterface::class)
            ->classService(
                StablePhpArrayDumper::class,
                StablePhpArrayDumper::class,
            )
            ->classService(
                StablePhpArrayParser::class,
                StablePhpArrayParser::class,
            )
            ->classService(
                AtomicFileWriter::class,
                AtomicFileWriter::class,
            )
            ->classService(
                ReservedRoutesGuard::class,
                ReservedRoutesGuard::class,
            )
            ->classService(
                RouteProviderRegistry::class,
                RouteProviderRegistry::class,
                [self::ref(TagRegistry::class), self::ref(ContainerInterface::class)],
            )
            ->classService(
                RoutesArtifactSchema::class,
                RoutesArtifactSchema::class,
                [
                    self::ref(ReservedRoutesGuard::class),
                    self::ref(SensitiveKeyClassifierInterface::class),
                    self::ref(SensitiveDataRedactorInterface::class),
                    self::ref(StablePhpArrayDumper::class),
                ],
            )
            ->classService(
                RoutesArtifactPath::class,
                RoutesArtifactPath::class,
                [self::ref(RuntimePathContext::class)],
            )
            ->classService(
                RoutesCompiler::class,
                RoutesCompiler::class,
                [
                    self::ref(ModulePlan::class),
                    self::ref(RuntimeDiagnosticsInput::class),
                    self::ref(RouteProviderRegistry::class),
                    self::ref(RoutesArtifactSchema::class),
                ],
            )
            ->classService(
                RoutesArtifactWriter::class,
                RoutesArtifactWriter::class,
                [
                    self::ref(RoutesArtifactSchema::class),
                    self::ref(RoutesArtifactPath::class),
                    self::ref(ScopedFileLock::class),
                    self::ref(AtomicFileWriter::class),
                    self::ref(ModulePlan::class),
                    self::ref(RuntimeDiagnosticsInput::class),
                ],
            )
            ->classService(
                RoutesArtifactReader::class,
                RoutesArtifactReader::class,
                [
                    self::ref(RoutesArtifactPath::class),
                    self::ref(StablePhpArrayParser::class),
                    self::ref(RoutesArtifactSchema::class),
                    self::ref(ModulePlan::class),
                    self::ref(RuntimeDiagnosticsInput::class),
                ],
            )
            ->classService(
                CompiledRouteTable::class,
                CompiledRouteTable::class,
                [self::ref(RoutesArtifactReader::class)],
            )
            ->classMethodFactory(
                RoutingMatchObserver::class,
                RoutingServiceFactory::class,
                'matchObserver',
                [
                    self::ref(TracerPortInterface::class),
                    self::ref(MeterPortInterface::class),
                    self::ref(LoggerInterface::class),
                    self::ref(Stopwatch::class),
                    self::ref(ConfigRepositoryInterface::class),
                ],
            )
            ->classService(
                CompiledRouter::class,
                CompiledRouter::class,
                [
                    self::ref(CompiledRouteTable::class),
                    self::ref(ContextWriterInterface::class),
                    self::ref(RoutingMatchObserver::class),
                ],
            )
            ->classService(
                RoutingArtifactReadiness::class,
                RoutingArtifactReadiness::class,
                [self::ref(CompiledRouteTable::class)],
            )
            ->alias(RouterInterface::class, CompiledRouter::class)
            ->alias(RoutingArtifactReadinessInterface::class, RoutingArtifactReadiness::class)
            ->classMethodFactory(
                RoutesCommandTargetResolver::class,
                RoutingServiceFactory::class,
                'commandTargetResolver',
                [
                    self::ref(RuntimePathContext::class),
                    self::ref(RuntimeDiagnosticsInput::class),
                    self::ref(ModulePlan::class),
                ],
            )
            ->classMethodFactory(
                RoutesCompileCommand::class,
                RoutingServiceFactory::class,
                'compileCommand',
                [self::ref(RoutesCommandTargetResolver::class)],
            )
            ->classMethodFactory(
                RoutesValidateCommand::class,
                RoutingServiceFactory::class,
                'validateCommand',
                [self::ref(RoutesCommandTargetResolver::class)],
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                RoutesCompileCommand::class,
                meta: self::commandMeta(
                    RoutesCompileCommand::NAME,
                    RoutesCompileCommand::SUMMARY,
                    RoutesCompileCommand::GROUP,
                    RoutesCompileCommand::HIDDEN,
                    RoutesCompileCommand::ARGUMENTS,
                    RoutesCompileCommand::OPTIONS,
                ),
            )
            ->tag(
                ReservedTags::CLI_COMMAND,
                RoutesValidateCommand::class,
                meta: self::commandMeta(
                    RoutesValidateCommand::NAME,
                    RoutesValidateCommand::SUMMARY,
                    RoutesValidateCommand::GROUP,
                    RoutesValidateCommand::HIDDEN,
                    RoutesValidateCommand::ARGUMENTS,
                    RoutesValidateCommand::OPTIONS,
                ),
            );
    }

    /**
     * @param list<array<string, mixed>> $arguments
     * @param list<array<string, mixed>> $options
     *
     * @return array{
     *     name: string,
     *     summary: string,
     *     group: string,
     *     hidden: bool,
     *     arguments: list<array<string, mixed>>,
     *     options: list<array<string, mixed>>
     * }
     */
    private static function commandMeta(
        string $name,
        string $summary,
        string $group,
        bool $hidden,
        array $arguments,
        array $options,
    ): array {
        return compact(
            'name',
            'summary',
            'group',
            'hidden',
            'arguments',
            'options',
        );
    }

    private static function ref(string $serviceId): ContainerValueReference
    {
        return ContainerValueReference::service($serviceId);
    }
}
