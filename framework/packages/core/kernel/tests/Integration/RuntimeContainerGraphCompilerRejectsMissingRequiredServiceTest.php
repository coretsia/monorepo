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

use Coretsia\Foundation\Container\Exception\ContainerDefinitionInvalidException;
use Coretsia\Kernel\Tests\Fixtures\IncompleteContainerDefinitionProviderFixture;
use PHPUnit\Framework\TestCase;

final class RuntimeContainerGraphCompilerRejectsMissingRequiredServiceTest extends TestCase
{
    public function testRejectsMissingRequiredServiceAfterAllProvidersAreMerged(): void
    {
        try {
            ArtifactPipelineTestSupport::runtimeContainerGraphCompiler($this)->compile(
                moduleResolution: ArtifactPipelineTestSupport::moduleResolution([
                    IncompleteContainerDefinitionProviderFixture::class,
                ]),
                compiledConfig: ArtifactPipelineTestSupport::defaultConfig(),
            );

            self::fail('Expected missing required service rejection.');
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
                ContainerDefinitionInvalidException::REASON_REQUIRED_SERVICE_INVALID,
                $exception->reason(),
            );
            self::assertSame(
                'CORETSIA_CONTAINER_DEFINITION_INVALID: container-definition-invalid',
                $exception->getMessage(),
            );
        }
    }
}
