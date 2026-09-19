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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionSet;
use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Definition\ServiceDefinition;
use PHPUnit\Framework\TestCase;

final class ContainerGraphCompletenessValidatorRejectsServiceAliasIdCollisionTest extends TestCase
{
    public function testRejectsServiceAndAliasBindingsUsingTheSameId(): void
    {
        $bindingId = 'kernel.test.fixture.binding';
        $aliasTargetId = 'kernel.test.fixture.alias_target';
        $graph = DefinitionGraph::empty()
            ->withService(
                ServiceDefinition::class(
                    id: $bindingId,
                    class: \stdClass::class,
                ),
            )
            ->withService(
                ServiceDefinition::class(
                    id: $aliasTargetId,
                    class: \stdClass::class,
                ),
            )
            ->withAlias(
                alias: $bindingId,
                serviceId: $aliasTargetId,
            );

        try {
            new ContainerGraphCompletenessValidator()->validate(
                graph: $graph,
                definitions: ContainerDefinitionSet::empty(),
            );

            self::fail('Expected service and alias binding id collision rejection.');
        } catch (ContainerDefinitionInvalidException $exception) {
            self::assertSame(
                ContainerDefinitionInvalidException::ERROR_CODE,
                $exception->errorCode(),
            );
            self::assertSame(
                ContainerDefinitionInvalidException::MESSAGE_TOKEN,
                $exception->messageToken(),
            );
            self::assertSame(
                ContainerDefinitionInvalidException::REASON_DEFINITION_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
        }
    }
}
