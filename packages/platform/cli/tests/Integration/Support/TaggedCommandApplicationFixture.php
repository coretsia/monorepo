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

namespace Coretsia\Platform\Cli\Tests\Integration\Support;

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Psr\Container\ContainerInterface;

/** Real parser/catalog/UoW/render pipeline with interchangeable owner DI. */
final class TaggedCommandApplicationFixture
{
    public readonly BoundaryScenarioRuntime $runtime;
    public readonly CommandCatalog $catalog;
    private readonly CliApplicationFixtureHarness $application;

    public function __construct(
        CommandCatalog $catalog,
        ContainerInterface $container,
        SensitiveDataRedactorInterface $redactor,
    ) {
        $this->catalog = $catalog;
        $this->runtime = new BoundaryScenarioRuntime();
        $this->application = new CliApplicationFixtureHarness(
            $catalog,
            $this->runtime,
            $container,
            $redactor,
        );
    }

    /**
     * @param list<string> $tokens
     * @param (\Closure(resource,resource): void)|null $beforeRun
     * @return array{exit:int,stdout:string,stderr:string}
     */
    public function run(array $tokens, ?\Closure $beforeRun = null): array
    {
        return $this->application->run($tokens, beforeRun: $beforeRun);
    }

    /** @return array<string,mixed> */
    public static function document(array $result): array
    {
        return \json_decode($result['stdout'], true, flags: JSON_THROW_ON_ERROR);
    }
}
