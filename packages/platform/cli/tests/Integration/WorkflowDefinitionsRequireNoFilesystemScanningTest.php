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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Exception\WorkflowNotFoundException;
use Coretsia\Platform\Cli\Provider\CliServiceFactory;
use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;
use Coretsia\Platform\Cli\Tests\Integration\Support\RecordingOwnerCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;

final class WorkflowDefinitionsRequireNoFilesystemScanningTest extends CliIntegrationTestCase
{
    public function testEmptyValidatedDefinitionMapNeverDiscoversFiles(): void
    {
        $fixture = new WorkflowIntegrationFixture([]);
        self::assertSame([], $fixture->workflows->definitions());
        $result = $fixture->run(['workflow:list', '--format=json']);
        self::assertSame(0, $result['exit']);
        self::assertSame('', $result['stderr']);
        self::assertSame('coretsia.cli-output@1', WorkflowIntegrationFixture::document($result)['schema']);

        $unknown = $fixture->run(['workflow:run', 'ghost', '--format=json']);
        self::assertSame(1, $unknown['exit']);
        self::assertSame(
            WorkflowNotFoundException::ERROR_CODE,
            WorkflowIntegrationFixture::document($unknown)['data']['records'][0]['code'],
        );
        self::assertSame([
            WorkflowListCommand::class,
            WorkflowRunCommand::class,
        ], $fixture->container->resolved);
    }

    public function testConfiguredDefinitionsAreIndependentOfCurrentWorkingDirectoryAndUnrelatedFiles(): void
    {
        $root = self::temporaryRoot('workflow-cwd');
        $previous = \getcwd();
        self::assertIsString($previous);
        $definition = self::definition();
        try {
            self::write($root . '/workflow.php', '<?php return ["ghost" => ["summary" => "Injected", "steps" => []]];');
            self::write($root . '/config/workflows/ghost.php', '<?php return ["ghost" => true];');
            self::write($root . '/unrelated/project/workflow.json', '{"ghost":true}');
            self::assertTrue(\chdir($root));

            $empty = new WorkflowIntegrationFixture([]);
            self::assertSame([], $empty->workflows->definitions());
            self::assertSame(1, $empty->run(['workflow:run', 'ghost', '--format=json'])['exit']);

            RecordingOwnerCommand::reset();
            $configured = new WorkflowIntegrationFixture(['configured' => $definition]);
            self::assertSame(['configured'], \array_keys($configured->workflows->definitions()));
            $result = $configured->run(['workflow:run', 'configured', '--format=json']);
            self::assertSame(0, $result['exit'], $result['stderr']);
            self::assertSame(1, RecordingOwnerCommand::$executed);
            self::assertSame(['configured'], \array_keys($configured->workflows->definitions()));
            self::assertSame(1, $configured->run(['workflow:run', 'ghost', '--format=json'])['exit']);

            self::assertTrue(\chdir($previous));
            $withoutFiles = new WorkflowIntegrationFixture(['configured' => $definition]);
            self::assertSame(['configured'], \array_keys($withoutFiles->workflows->definitions()));
            self::assertSame(0, $withoutFiles->run(['workflow:run', 'configured', '--format=json'])['exit']);
        } finally {
            \chdir($previous);
            self::removeTree($root);
        }
    }

    public function testConfigRulesRejectWorkflowScanningSettingsWithoutRestrictingKernelBootstrap(): void
    {
        $package = self::packageRoot();
        $defaults = require $package . '/config/cli.php';
        $rules = require $package . '/config/rules.php';
        self::assertSame(['definitions'], \array_keys($defaults['workflows']));
        self::assertFalse($rules['keys']['workflows']['additionalKeys']);
        self::assertSame(['definitions'], \array_keys($rules['keys']['workflows']['keys']));
        self::assertTrue($rules['keys']['workflows']['keys']['definitions']['additionalKeys']);

        foreach (['paths', 'directories', 'glob', 'scan', 'templates'] as $key) {
            $config = $defaults;
            $config['workflows'][$key] = ['PRIVATE_WORKFLOW_PATH'];
            $validation = new ConfigValidator()->validate(['cli' => $config], [new ConfigRuleset('cli', $rules)]);
            self::assertTrue($validation->isFailure(), $key);
        }
        self::assertSame(['configured'], \array_keys(new WorkflowSchema()->normalize([
            'configured' => self::definition(),
        ])));
    }

    public function testWorkflowInfrastructureHasNoIndependentFilesystemDiscoveryPath(): void
    {
        $root = self::packageRoot() . '/src/Workflow';
        self::assertDirectoryExists($root);
        foreach (new \DirectoryIterator($root) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:glob|scandir|opendir|readdir|file_get_contents|file|realpath|getcwd|chdir)\s*\(|\b(?:DirectoryIterator|RecursiveDirectoryIterator|FilesystemIterator)\b/',
                    $code,
                ),
                $file->getPathname(),
            );
        }
    }

    public function testWorkflowCatalogServiceFactoryHasNoFilesystemDiscoveryFallback(): void
    {
        $method = new \ReflectionMethod(
            CliServiceFactory::class,
            'workflowCatalog',
        );
        $path = $method->getFileName();
        self::assertIsString($path);

        $lines = \file($path);
        self::assertTrue(\is_array($lines));

        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        $code = '';
        foreach (\token_get_all('<?php ' . $source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= \is_array($token) ? $token[1] : $token;
        }

        self::assertStringContainsString('self::cliConfigRoot($config)', $code);
        self::assertStringContainsString('new WorkflowCatalog(', $code);
        self::assertSame(
            0,
            \preg_match(
                '/\b(?:glob|scandir|opendir|readdir|file_get_contents|file|realpath|getcwd|chdir|file_exists|is_file|is_dir)\s*\(|\b(?:DirectoryIterator|RecursiveDirectoryIterator|FilesystemIterator|GlobIterator)\b/',
                $code,
            ),
            $path,
        );
    }

    /** @return array<string,mixed> */
    private static function definition(): array
    {
        return [
            'summary' => 'Configured only.',
            'steps' => [
                ['command' => RecordingOwnerCommand::NAME, 'arguments' => [], 'options' => []],
            ],
        ];
    }
}
