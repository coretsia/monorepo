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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Cli\Application\CliApplication;
use Coretsia\Platform\Cli\Catalog\CommandCatalog;
use Coretsia\Platform\Cli\Catalog\CommandOverrides;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\ExternalOwnerCommand;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;

final class WorkflowCatalogIsResolvedOnlyForWorkflowCommandsTest extends CliIntegrationTestCase
{
    public function testDeepInvalidDefinitionsDoNotBlockNormalCommandDispatch(): void
    {
        $root = self::consumer();

        try {
            self::setWorkflows($root, self::deepInvalidDefinitions());
            foreach (
                [
                    ['list', '--format=json'],
                    ['help', 'list', '--format=json'],
                    ['config:validate', '--target=web', '--format=json'],
                ] as $tokens
            ) {
                $result = self::execute($root . '/vendor/bin/coretsia', $tokens);
                self::assertSame(0, $result['exit'], $result['stderr']);
                self::assertSame('', $result['stderr']);
                self::assertSame('coretsia.cli-output@1', self::jsonRecord($result['stdout'])['schema']);
                self::assertStringNotContainsString(WorkflowDefinitionInvalidException::ERROR_CODE, $result['stdout']);
            }
            self::assertNoArtifacts($root);
        } finally {
            self::removeTree($root);
        }
    }

    public function testWorkflowCommandsResolveCatalogLazilyAndRejectDeepInvalidDefinitionsBeforeStepDispatch(): void
    {
        $root = self::consumer();

        try {
            self::setWorkflows($root, self::deepInvalidDefinitions());
            $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $config = $host->get(ConfigRepositoryInterface::class);
            self::assertInstanceOf(ConfigRepositoryInterface::class, $config);
            $cli = $config->get('cli');
            self::assertIsArray($cli);
            self::assertArrayHasKey('verify', $cli['workflows']['definitions']);
            self::assertInstanceOf(CommandCatalog::class, $host->get(CommandCatalog::class));
            self::assertInstanceOf(CliApplication::class, $host->get(CliApplication::class));

            try {
                $host->get(WorkflowCatalog::class);
                self::fail('Deep-invalid workflows must fail only when WorkflowCatalog is resolved.');
            } catch (ContainerException $exception) {
                self::assertSame('container-definition-factory-failed', $exception->reason());
                self::assertInstanceOf(WorkflowDefinitionInvalidException::class, $exception->getPrevious());
                self::assertSame(
                    WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                    $exception->getPrevious()->reason(),
                );
            }

            foreach (
                [
                    ['workflow:list', '--format=json'],
                    ['workflow:run', 'verify', '--format=json'],
                ] as $tokens
            ) {
                $result = self::execute($root . '/vendor/bin/coretsia', $tokens);
                self::assertSame(1, $result['exit']);
                self::assertSame('', $result['stderr']);
                $document = self::jsonRecord($result['stdout']);
                $records = $document['data']['records'];
                self::assertCount(1, $records);
                self::assertSame('error', $records[0]['type']);
                self::assertSame('CORETSIA_CLI_INTERNAL_ERROR', $records[0]['code']);
                self::assertSame('Internal CLI error.', $records[0]['message']);
                self::assertStringNotContainsString('PRIVATE_', $result['stdout']);
                self::assertStringNotContainsString('missing:private', $result['stdout']);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testStructurallyInvalidWorkflowMapsFailAtHostBoot(): void
    {
        foreach (
            [
                ['workflows' => 'PRIVATE_WORKFLOWS_MAP'],
                ['workflows' => ['definitions' => 'PRIVATE_DEFINITIONS_MAP']],
            ] as $invalid
        ) {
            $root = self::consumer();

            try {
                self::write($root . '/config/cli.php', '<?php return ' . \var_export($invalid, true) . ';');
                $result = self::execute($root . '/vendor/bin/coretsia', ['list']);
                self::assertSafeFailure($result, 'CORETSIA_CLI_HOST_BOOT_FAILED: host-boot-failed');
                self::assertStringNotContainsString('PRIVATE_', $result['stderr']);
                self::assertNoArtifacts($root);
            } finally {
                self::removeTree($root);
            }
        }
    }

    public function testCommandCatalogConstructionNeverResolvesServicesOrWorkflowCatalog(): void
    {
        ExternalOwnerCommand::reset();
        $registry = new TagRegistry();
        $registry->add(ReservedTags::CLI_COMMAND, ExternalOwnerCommand::class, meta: [
            'name' => ExternalOwnerCommand::NAME,
            'summary' => ExternalOwnerCommand::SUMMARY,
            'group' => ExternalOwnerCommand::GROUP,
            'hidden' => ExternalOwnerCommand::HIDDEN,
            'arguments' => ExternalOwnerCommand::ARGUMENTS,
            'options' => ExternalOwnerCommand::OPTIONS,
        ]);
        $catalog = new CommandCatalog($registry, new CommandTagSchema(), new CommandOverrides(), [], []);
        self::assertSame(ExternalOwnerCommand::class, $catalog->require(ExternalOwnerCommand::NAME)->serviceId());
        self::assertSame(0, ExternalOwnerCommand::$constructed);
        self::assertSame(0, ExternalOwnerCommand::$executed);
        self::assertNull($catalog->find(WorkflowListCommand::NAME));
        self::assertNull($catalog->find(WorkflowRunCommand::NAME));

        $sourcePath = new \ReflectionClass(CliApplication::class)->getFileName();
        self::assertIsString($sourcePath);
        $source = \file_get_contents($sourcePath);
        self::assertIsString($source);
        self::assertStringNotContainsString('WorkflowCatalog', $source);
        self::assertStringNotContainsString('WorkflowSchema', $source);
    }

    /** @return array<string, mixed> */
    private static function deepInvalidDefinitions(): array
    {
        return [
            'verify' => [
                'summary' => 'Only workflow resolution should reject this definition.',
                'steps' => [
                    ['command' => 'config:validate', 'arguments' => [], 'options' => ['target' => 'web']],
                    ['command' => 'missing:private', 'arguments' => [], 'options' => []],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $definitions */
    private static function setWorkflows(string $root, array $definitions): void
    {
        self::write(
            $root . '/config/cli.php',
            '<?php return ' . \var_export(['workflows' => ['definitions' => $definitions]], true) . ';',
        );
    }
}
