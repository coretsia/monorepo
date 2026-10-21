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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Platform\Cli\Command\WorkflowListCommand;
use Coretsia\Platform\Cli\Command\WorkflowRunCommand;
use Coretsia\Platform\Cli\Output\ConsoleOutputWriter;
use Coretsia\Platform\Cli\Workflow\WorkflowCatalog;
use Coretsia\Platform\Cli\Workflow\WorkflowRunner;
use PHPUnit\Framework\TestCase;

final class WorkflowInfrastructureDoesNotWriteToStdoutTest extends TestCase
{
    public function testWorkflowAndSuggestionProductionFilesContainNoDirectOutputSink(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $paths = [
            $root . '/Workflow',
            $root . '/UX',
            $root . '/Command/WorkflowListCommand.php',
            $root . '/Command/WorkflowRunCommand.php',
            $root . '/Runner/CompositeCommandInterface.php',
        ];
        $checked = 0;

        foreach ($paths as $path) {
            self::assertTrue(\file_exists($path), $path);
            $files = \is_dir($path)
                ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS))
                : [$path];

            foreach ($files as $file) {
                $filename = $file instanceof \SplFileInfo ? $file->getPathname() : $file;
                if (!\is_file($filename) || !\str_ends_with($filename, '.php')) {
                    continue;
                }

                ++$checked;
                $source = \file_get_contents($filename);
                self::assertIsString($source);
                $tokens = \token_get_all($source);
                $code = '';

                foreach ($tokens as $token) {
                    if (!\is_array($token)) {
                        $code .= $token;
                        continue;
                    }
                    if (\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                        continue;
                    }
                    self::assertNotContains(
                        $token[0],
                        [\T_ECHO, \T_PRINT, \T_OPEN_TAG_WITH_ECHO, \T_EXIT, \T_INLINE_HTML],
                        $filename,
                    );
                    $code .= $token[1];
                }

                self::assertStringNotContainsString('ConsoleOutputWriter', $code, $filename);
                self::assertSame(
                    0,
                    \preg_match(
                        '~(?<!->|::)(?<![A-Za-z0-9_])\\\\?(?:fwrite|fputs|fprintf|printf|vprintf|print_r|var_dump|var_export|debug_zval_dump|error_log|file_put_contents|readfile|fpassthru|stream_copy_to_stream|passthru|system)\s*\(~i',
                        $code,
                    ),
                    $filename,
                );
                self::assertSame(
                    0,
                    \preg_match('~(?<![A-Za-z0-9_$])(?:STDOUT|STDERR)\b|php://(?:stdout|stderr|output)\b~i', $code),
                    $filename,
                );
            }
        }

        self::assertGreaterThan(10, $checked);
    }

    public function testWorkflowCommandsOnlyProduceRecordsThroughTheirOutputPort(): void
    {
        foreach ([WorkflowListCommand::class, WorkflowRunCommand::class] as $command) {
            $reflection = new \ReflectionClass($command);
            $sourcePath = $reflection->getFileName();
            self::assertIsString($sourcePath);
            $source = \file_get_contents($sourcePath);
            self::assertIsString($source);
            self::assertStringContainsString('$output->json(', $source);
            self::assertStringNotContainsString('new ConsoleOutputWriter', $source);
        }

        $runner = new \ReflectionClass(WorkflowRunner::class);
        $catalog = new \ReflectionClass(WorkflowCatalog::class);
        self::assertTrue($runner->isReadOnly());
        self::assertTrue($catalog->isReadOnly());
        self::assertTrue(\method_exists(ConsoleOutputWriter::class, 'write'));
    }
}
