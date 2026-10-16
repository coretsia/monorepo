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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Provider\CliServiceFactory;
use PHPUnit\Framework\TestCase;

final class CliServiceFactoryReadsValidatedCliRootTest extends TestCase
{
    public function testEachConfigDependentFactoryReadsCompleteCliRootExactlyOnce(): void
    {
        foreach (['catalog', 'policy'] as $factory) {
            $config = new FactoryConfigProbe(self::root());
            if ($factory === 'catalog') {
                $result = CliServiceFactory::commandCatalog(
                    $config,
                    new TagRegistry(),
                    new CommandTagSchema(),
                    new CommandOverrides(),
                );
                self::assertSame([], $result->descriptors());
            } else {
                $result = CliServiceFactory::cliOutputPolicy($config);
                self::assertSame('adaptive', $result->formatDefault());
                self::assertSame(120, $result->tableMaxWidth());
            }
            self::assertSame(['cli'], $config->gets);
            self::assertSame(['cli'], $config->hasChecks);
            self::assertSame(0, $config->allCalls);
            self::assertSame([], $config->sources);
        }
    }

    public function testMissingOrInvalidCliRootFailsClosedWithoutChildPathReads(): void
    {
        foreach ([false, 'bad', ['output' => []]] as $root) {
            $config = new FactoryConfigProbe($root);
            try {
                CliServiceFactory::cliOutputPolicy($config);
                self::fail('Incomplete or absent CLI root must fail.');
            } catch (CliConfigInvalidException $exception) {
                self::assertSame(CliConfigInvalidException::REASON_SERVICE_CONFIG_INVALID, $exception->reason());
                self::assertSame($root === false ? [] : ['cli'], $config->gets);
                self::assertSame(['cli'], $config->hasChecks);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function root(): array
    {
        return [
            'commands' => ['overrides' => []],
            'output' => [
                'format_default' => 'adaptive',
                'adaptive' => ['interactive' => 'table', 'non_interactive' => 'plain'],
                'color_default' => 'auto',
                'table' => ['max_width' => 120],
            ],
        ];
    }
}

final class FactoryConfigProbe implements ConfigRepositoryInterface
{
    /** @var list<string> */
    public array $gets = [];
    /** @var list<string> */
    public array $hasChecks = [];
    /** @var list<string> */
    public array $sources = [];
    public int $allCalls = 0;

    public function __construct(private mixed $root)
    {
    }

    public function has(string $keyPath): bool
    {
        $this->hasChecks[] = $keyPath;
        self::guard($keyPath);
        return $this->root !== false;
    }

    public function get(string $keyPath, mixed $default = null): mixed
    {
        $this->gets[] = $keyPath;
        self::guard($keyPath);
        return $this->root;
    }

    public function all(): array
    {
        ++$this->allCalls;
        throw new \LogicException('Factory must not read the full config tree.');
    }

    public function sourceOf(string $keyPath): ?ConfigValueSource
    {
        $this->sources[] = $keyPath;
        throw new \LogicException('Factory must not read source metadata.');
    }

    public function explain(): array
    {
        throw new \LogicException('Factory must not read the explain trace.');
    }

    private static function guard(string $key): void
    {
        if ($key !== 'cli') {
            throw new \LogicException('Factory must not query a cli.* child path.');
        }
    }
}
