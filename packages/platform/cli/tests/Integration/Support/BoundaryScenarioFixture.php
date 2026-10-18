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

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;

/** Real application/parser/catalog/runner/rendering with one observable owner command. */
final class BoundaryScenarioFixture
{
    public readonly BoundaryScenarioRuntime $runtime;
    private readonly CliApplicationFixtureHarness $application;

    public function __construct()
    {
        $this->runtime = new BoundaryScenarioRuntime();
        BoundaryScenarioCommand::reset($this->runtime);
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, BoundaryScenarioCommand::class, meta: [
            'name' => BoundaryScenarioCommand::NAME,
            'summary' => BoundaryScenarioCommand::SUMMARY,
            'group' => BoundaryScenarioCommand::GROUP,
            'hidden' => BoundaryScenarioCommand::HIDDEN,
            'arguments' => BoundaryScenarioCommand::ARGUMENTS,
            'options' => BoundaryScenarioCommand::OPTIONS,
        ]);
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('Only the JSON-like redaction port is used.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };
        $this->application = new CliApplicationFixtureHarness(
            new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []),
            $this->runtime,
            new BoundaryScenarioContainer($this->runtime),
            $redactor,
        );
    }

    /** @param list<string> $arguments @return array{exit:int,stdout:string,stderr:string} */
    public function run(array $arguments, ?TerminalCapabilities $capabilities = null): array
    {
        return $this->application->run($arguments, $capabilities);
    }
}
