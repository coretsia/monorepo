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

namespace Coretsia\Platform\Redaction\Provider;

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;

/**
 * Declarative, config-free sensitive-data redaction DI wiring.
 *
 * The provider contributes one shared redactor implementation and the
 * contracts-level port alias only when its module is explicitly enabled.
 * Registration performs no service resolution, runtime work, or config reads.
 */
final class RedactionServiceProvider implements
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
        $definitions
            ->classService(
                SensitiveKeyClassifier::class,
                SensitiveKeyClassifier::class,
            )
            ->classService(
                SensitiveValueClassifier::class,
                SensitiveValueClassifier::class,
            )
            ->classService(
                StableRedactionHasher::class,
                StableRedactionHasher::class,
            )
            ->classService(
                id: DefaultSensitiveDataRedactor::class,
                class: DefaultSensitiveDataRedactor::class,
                arguments: [
                    ContainerValueReference::service(SensitiveKeyClassifier::class),
                    ContainerValueReference::service(SensitiveValueClassifier::class),
                    ContainerValueReference::service(StableRedactionHasher::class),
                ],
            )
            ->alias(
                SensitiveDataRedactorInterface::class,
                DefaultSensitiveDataRedactor::class,
            );
    }
}
