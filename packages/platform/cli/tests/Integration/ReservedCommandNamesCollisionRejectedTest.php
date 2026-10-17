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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Composer\InstalledVersions;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\ReservedBoundaryHelpCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\ReservedBoundaryHelpServiceProvider;

final class ReservedCommandNamesCollisionRejectedTest extends CliIntegrationTestCase
{
    public function testExternalHelpTagFailsCatalogConstructionBeforeServiceResolution(): void
    {
        $root = self::consumer();
        try {
            ReservedBoundaryHelpCommand::$constructed = 0;
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $registry = $host->get(TagRegistry::class);
            $registry->add(ReservedTags::CLI_COMMAND, ReservedBoundaryHelpCommand::class, meta: [
                'name' => ReservedBoundaryHelpCommand::NAME,
                'summary' => ReservedBoundaryHelpCommand::SUMMARY,
                'group' => ReservedBoundaryHelpCommand::GROUP,
                'hidden' => ReservedBoundaryHelpCommand::HIDDEN,
                'arguments' => ReservedBoundaryHelpCommand::ARGUMENTS,
                'options' => ReservedBoundaryHelpCommand::OPTIONS,
            ]);
            try {
                $host->get(CliApplication::class);
                self::fail('An external help command must not replace the canonical built-in.');
            } catch (\Throwable $exception) {
                $previous = $exception;
                while ($previous !== null && !$previous instanceof InvalidCommandTagMetaException) {
                    $previous = $previous->getPrevious();
                }
                self::assertInstanceOf(InvalidCommandTagMetaException::class, $previous);
                self::assertSame(
                    InvalidCommandTagMetaException::REASON_NAME_COLLISION,
                    $previous->reason(),
                );
            }
            self::assertSame(0, ReservedBoundaryHelpCommand::$constructed);
            $builtIns = \array_filter(
                $registry->all(ReservedTags::CLI_COMMAND),
                static fn ($tag): bool => $tag->id() === HelpCommand::class,
            );
            self::assertCount(1, $builtIns);
        } finally {
            self::removeTree($root);
        }
    }

    public function testEnabledExternalHelpCollisionFailsAtTheHostBootstrapBoundary(): void
    {
        $root = self::consumer();
        try {
            self::installExternalReservedHelpFixture($root);
            $constructed = $root . '/external-help-constructed';
            $result = self::execute(
                $root . '/vendor/bin/coretsia',
                ['list'],
                environment: ['CORETSIA_TEST_RESERVED_HELP_CONSTRUCTED' => $constructed],
            );
            self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
            self::assertFileDoesNotExist($constructed);
            foreach (
                [
                    ReservedBoundaryHelpCommand::SUMMARY,
                    ReservedBoundaryHelpCommand::class,
                    'coretsia/test-cli-reserved',
                    $root,
                ] as $private
            ) {
                self::assertStringNotContainsString($private, $result['stderr']);
            }
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    private static function installExternalReservedHelpFixture(string $root): void
    {
        $runtime = new \ReflectionClass(InstalledVersions::class);
        $runtimePath = $runtime->getFileName();
        self::assertIsString($runtimePath);
        $runtimeDirectory = \dirname($runtimePath);
        $installed = require $runtimeDirectory . '/installed.php';
        $installedJson = \json_decode(
            (string) \file_get_contents($runtimeDirectory . '/installed.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($installed);
        self::assertIsArray($installedJson);
        self::assertIsArray($installedJson['packages'] ?? null);

        $packageName = 'coretsia/test-cli-reserved';
        $packagePath = $root . '/vendor/coretsia/test-cli-reserved';
        $metadata = [
            'kind' => 'runtime',
            'moduleId' => 'test.reserved',
            'providers' => [
                ReservedBoundaryHelpServiceProvider::class,
            ],
            'requires' => [],
            'conflicts' => [],
        ];
        $installed['versions'][$packageName] = [
            'pretty_version' => '0.0.0',
            'version' => '0.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => $packagePath,
            'aliases' => [],
            'dev_requirement' => false,
        ];
        $installedJson['packages'][] = [
            'name' => $packageName,
            'version' => '0.0.0',
            'type' => 'library',
            'extra' => ['coretsia' => $metadata],
            'dev_requirement' => false,
        ];
        \usort(
            $installedJson['packages'],
            static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']),
        );

        self::write(
            $packagePath . '/composer.json',
            \json_encode([
                'name' => $packageName,
                'type' => 'library',
                'extra' => ['coretsia' => $metadata],
            ], JSON_THROW_ON_ERROR),
        );
        self::write($root . '/vendor/composer/InstalledVersions.php', (string) \file_get_contents($runtimePath));
        self::write(
            $root . '/vendor/composer/installed.php',
            '<?php return ' . \var_export($installed, true) . ';' . "\n",
        );
        self::write(
            $root . '/vendor/composer/installed.json',
            \json_encode($installedJson, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . "\n",
        );

        $support = self::packageRoot() . '/tests/Integration/Support';
        self::write(
            $root . '/vendor/autoload.php',
            '<?php' . "\n"
            . 'require __DIR__ . \'/composer/InstalledVersions.php\';' . "\n"
            . '$loader = require ' . \var_export(self::repositoryRoot() . '/vendor/autoload.php', true) . ';' . "\n"
            . 'require ' . \var_export($support . '/ReservedBoundaryHelpCommand.php', true) . ';' . "\n"
            . 'require ' . \var_export($support . '/ReservedBoundaryHelpServiceProvider.php', true) . ';' . "\n"
            . 'return $loader;' . "\n",
        );
        self::write(
            $root . '/config/app.php',
            '<?php return ' . \var_export([
                'moduleOverrides' => [
                    'console' => ['include' => ['test.reserved'], 'exclude' => []],
                ],
            ], true) . ';' . "\n",
        );
    }
}
