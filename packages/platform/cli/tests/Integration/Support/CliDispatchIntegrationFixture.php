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
use Coretsia\Platform\Cli\Output\CliOutputPolicy;
use Coretsia\Platform\Cli\Output\TerminalCapabilities;

/**
 * Full normal-path CLI dispatch using real parser, validator, catalog, UoW
 * callback, output formatting and a deliberately isolated owner command.
 */
final class CliDispatchIntegrationFixture
{
    public readonly RecordingOwnerContainer $container;
    public readonly RecordingCliRuntime $runtime;

    private readonly CliApplicationFixtureHarness $application;

    public function __construct(?CliOutputPolicy $policy = null)
    {
        $policy ??= new CliOutputPolicy(
            'adaptive',
            'table',
            'plain',
            'auto',
            120,
        );
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, RecordingOwnerCommand::class, meta: [
            'name' => RecordingOwnerCommand::NAME,
            'summary' => RecordingOwnerCommand::SUMMARY,
            'group' => RecordingOwnerCommand::GROUP,
            'hidden' => RecordingOwnerCommand::HIDDEN,
            'arguments' => RecordingOwnerCommand::ARGUMENTS,
            'options' => RecordingOwnerCommand::OPTIONS,
        ]);
        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        $this->container = new RecordingOwnerContainer();
        $this->runtime = new RecordingCliRuntime();
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public function redactValue(
                string $value,
                RedactionKind $kind,
                RedactionContext $context,
            ): RedactedValue {
                throw new \LogicException('Direct redaction is not used by this fixture.');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                return $value;
            }
        };

        $this->application = new CliApplicationFixtureHarness(
            $catalog,
            $this->runtime,
            $this->container,
            $redactor,
            $policy,
        );
    }

    /**
     * @param list<string> $tokens
     * @return array{exit:int,stdout:string,stderr:string}
     */
    public function run(array $tokens, ?TerminalCapabilities $capabilities = null): array
    {
        return $this->application->run($tokens, $capabilities);
    }
}
