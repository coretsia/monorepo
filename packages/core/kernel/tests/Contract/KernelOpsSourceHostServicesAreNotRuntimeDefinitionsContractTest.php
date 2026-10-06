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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Definition\ServiceDefinition;
use Coretsia\Kernel\Container\RuntimeContainerSeedIds;
use Coretsia\Kernel\Ops\KernelOpsExecutionServices;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Ops\KernelOpsHostSeedConfigLoader;
use Coretsia\Kernel\Ops\KernelOpsSourceDefinitionProviderAdapter;
use Coretsia\Kernel\Provider\KernelServiceProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;

final class KernelOpsSourceHostServicesAreNotRuntimeDefinitionsContractTest extends TestCase
{
    /**
     * @var list<class-string>
     */
    private const array SOURCE_OPERATIONS_HOST_SERVICE_IDS = [
        KernelOpsHostBooter::class,
        KernelOpsHostInput::class,
        KernelOpsHostSeedConfigLoader::class,
        KernelOpsExecutionServices::class,
        KernelOpsSourceDefinitionProviderAdapter::class,
        KernelOpsFacade::class,
        KernelOpsInterface::class,
    ];

    /**
     * @var list<class-string>
     */
    private const array SOURCE_OPERATIONS_HOST_IMPLEMENTATION_CLASSES = [
        KernelOpsHostBooter::class,
        KernelOpsHostInput::class,
        KernelOpsHostSeedConfigLoader::class,
        KernelOpsExecutionServices::class,
        KernelOpsSourceDefinitionProviderAdapter::class,
        KernelOpsFacade::class,
    ];

    public function testValidatorLocksCompleteSourceOperationsHostForbiddenRuntimeSet(): void
    {
        $reflection = new ReflectionClass(ContainerGraphCompletenessValidator::class);
        $constant = $reflection->getReflectionConstant('SOURCE_OPERATIONS_HOST_SERVICE_IDS');

        self::assertInstanceOf(ReflectionClassConstant::class, $constant);

        $sourceHostIds = $constant->getValue();

        if (!\is_array($sourceHostIds)) {
            self::fail('SOURCE_OPERATIONS_HOST_SERVICE_IDS must remain an array constant.');
        }

        self::assertSame(
            self::SOURCE_OPERATIONS_HOST_SERVICE_IDS,
            $sourceHostIds,
        );

        $compileHostConstant = $reflection->getReflectionConstant('COMPILE_HOST_SERVICE_IDS');

        self::assertInstanceOf(ReflectionClassConstant::class, $compileHostConstant);

        $compileHostIds = $compileHostConstant->getValue();

        if (!\is_array($compileHostIds)) {
            self::fail('COMPILE_HOST_SERVICE_IDS must remain an array constant.');
        }

        self::assertSame(
            [],
            \array_values(
                \array_intersect(
                    $compileHostIds,
                    self::SOURCE_OPERATIONS_HOST_SERVICE_IDS,
                ),
            ),
            'Source-operations-host-only ids must remain a separate classification ' .
            'from the canonical compile-host list.',
        );
    }

    public function testSourceOperationsHostIdsAreNotProductionRuntimeSeedIds(): void
    {
        foreach (self::SOURCE_OPERATIONS_HOST_SERVICE_IDS as $serviceId) {
            self::assertNotContains($serviceId, RuntimeContainerSeedIds::all());
        }
    }

    public function testValidatorRejectsEverySourceOperationsHostServiceOrAliasBindingId(): void
    {
        foreach (self::SOURCE_OPERATIONS_HOST_SERVICE_IDS as $serviceId) {
            self::assertRejected(
                graph: DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: $serviceId,
                        class: \stdClass::class,
                    ),
                ),
            );

            self::assertRejected(
                graph: DefinitionGraph::empty()
                    ->withService(
                        ServiceDefinition::class(
                            id: 'runtime.allowed',
                            class: \stdClass::class,
                        ),
                    )
                    ->withAlias($serviceId, 'runtime.allowed'),
            );
        }
    }

    public function testValidatorRejectsSourceHostImplementationClassUnderAnotherServiceId(): void
    {
        foreach (self::SOURCE_OPERATIONS_HOST_IMPLEMENTATION_CLASSES as $implementationClass) {
            self::assertRejected(
                graph: DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'runtime.probe',
                        class: $implementationClass,
                    ),
                ),
            );
        }
    }

    public function testValidatorRejectsSourceHostAliasTargetsAndServiceMethodFactoryReferences(): void
    {
        foreach (self::SOURCE_OPERATIONS_HOST_SERVICE_IDS as $serviceId) {
            self::assertRejected(
                graph: DefinitionGraph::empty()
                    ->withService(
                        ServiceDefinition::class(
                            id: 'runtime.allowed',
                            class: \stdClass::class,
                        ),
                    )
                    ->withAlias('runtime.alias', $serviceId),
            );

            self::assertRejected(
                graph: DefinitionGraph::empty()->withService(
                    ServiceDefinition::factoryServiceMethod(
                        id: 'runtime.probe',
                        factoryServiceId: $serviceId,
                        method: 'create',
                    ),
                ),
            );
        }
    }

    public function testValidatorRejectsSourceHostTagRequiredServiceAndNestedServiceReferences(): void
    {
        foreach (self::SOURCE_OPERATIONS_HOST_SERVICE_IDS as $serviceId) {
            self::assertRejected(
                graph: DefinitionGraph::empty()->withTag(
                    tag: 'kernel.runtime.probe',
                    serviceId: $serviceId,
                ),
            );

            $definitions = new ContainerDefinitionBuilder();
            $definitions->requireService($serviceId);

            self::assertRejected(
                graph: DefinitionGraph::empty(),
                definitions: $definitions->build(),
            );

            self::assertRejected(
                graph: DefinitionGraph::empty()->withService(
                    ServiceDefinition::class(
                        id: 'runtime.probe',
                        class: \stdClass::class,
                        arguments: [
                            [
                                'dependency' => ServiceDefinition::serviceReference($serviceId),
                            ],
                        ],
                    ),
                ),
            );
        }
    }

    public function testSourceOperationsHostIdsNeverEnterGeneratedRuntimeDefinitionDescriptors(): void
    {
        $definitions = self::completeRuntimeDefinitionSet(self::validConfig());
        $descriptorStrings = self::stringsFromValue($definitions->toDescriptorStream());
        $requiredServiceIds = $definitions->requiredServiceIds();

        foreach (self::SOURCE_OPERATIONS_HOST_SERVICE_IDS as $serviceId) {
            self::assertNotContains($serviceId, $descriptorStrings);
            self::assertNotContains($serviceId, $requiredServiceIds);
        }
    }

    private static function assertRejected(
        DefinitionGraph $graph,
        ?ContainerDefinitionSet $definitions = null,
    ): void {
        $validator = new ContainerGraphCompletenessValidator();

        try {
            $validator->validate(
                graph: $graph,
                definitions: $definitions ?? ContainerDefinitionSet::empty(),
            );

            self::fail('Expected source-operations-host-only runtime reference to be rejected.');
        } catch (ContainerDefinitionInvalidException) {
            self::assertTrue(true);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function completeRuntimeDefinitionSet(
        array $config,
    ): ContainerDefinitionSet {
        $definitions = new ContainerDefinitionBuilder();
        $context = new ContainerDefinitionContext($config);

        new FoundationServiceProvider()->define($definitions, $context);
        new KernelServiceProvider()->define($definitions, $context);

        return $definitions->build();
    }

    /**
     * @return list<string>
     */
    private static function stringsFromValue(mixed $value): array
    {
        if (\is_string($value)) {
            return [$value];
        }

        if (!\is_array($value)) {
            return [];
        }

        $strings = [];

        foreach ($value as $key => $item) {
            if (\is_string($key)) {
                $strings[] = $key;
            }

            foreach (self::stringsFromValue($item) as $string) {
                $strings[] = $string;
            }
        }

        return $strings;
    }

    /**
     * @return array<string, mixed>
     */
    private static function validConfig(): array
    {
        return [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => false,
                    'allow_reflection_for_concrete' => false,
                ],
                'ids' => [
                    'default' => 'ulid',
                ],
                'reset' => [
                    'tag' => ReservedTags::KERNEL_RESET,
                    'priority' => [
                        'enabled' => false,
                    ],
                ],
            ],
            'kernel' => [
                'uow' => [
                    'attributes' => [
                        'max_depth' => 10,
                        'max_keys' => 200,
                    ],
                ],
            ],
        ];
    }
}
