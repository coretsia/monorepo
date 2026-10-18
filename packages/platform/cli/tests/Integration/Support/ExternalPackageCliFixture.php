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

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;

/** An enabled test package contributes through its provider and Foundation DI tags. */
final class ExternalPackageCliFixture
{
    public readonly TaggedCommandApplicationFixture $cli;
    public readonly Container $container;
    public readonly CommandCatalog $catalog;

    public function __construct()
    {
        ExternalOwnerCommand::reset();
        $builder = new ContainerBuilder();
        $builder->registerProviders([new ExternalOwnerServiceProvider()]);
        $builder->tag(ReservedTags::CLI_COMMAND, ListCommand::class, meta: self::meta(ListCommand::class));
        $builder->tag(ReservedTags::CLI_COMMAND, HelpCommand::class, meta: self::meta(HelpCommand::class));
        $this->catalog = new CommandCatalog(
            $builder->tagRegistry(),
            new CommandTagSchema(),
            new CommandOverrides(),
            [],
            ['list' => ListCommand::class, 'help' => HelpCommand::class],
        );
        $catalog = $this->catalog;
        $builder->factory(
            ListCommand::class,
            static fn (Container $container): ListCommand => new ListCommand($catalog),
        );
        $builder->factory(
            HelpCommand::class,
            static fn (Container $container): HelpCommand => new HelpCommand($catalog),
        );
        $this->container = $builder->build();
        $this->cli = new TaggedCommandApplicationFixture(
            $catalog,
            $this->container,
            new DefaultSensitiveDataRedactor(
                new SensitiveKeyClassifier(),
                new SensitiveValueClassifier(),
                new StableRedactionHasher(),
            ),
        );
    }

    /** @return array<string,mixed> */
    private static function meta(string $command): array
    {
        return [
            'name' => $command::NAME,
            'summary' => $command::SUMMARY,
            'group' => $command::GROUP,
            'hidden' => $command::HIDDEN,
            'arguments' => $command::ARGUMENTS,
            'options' => $command::OPTIONS,
        ];
    }
}
