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

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Output\OutputFormatter;
use PHPUnit\Framework\TestCase;

final class WorkflowUxIntroducesNoRedactionBoundaryContractTest extends TestCase
{
    public function testWorkflowAndSuggestionServicesContainNoLocalRedactorOrInteractiveUxPipeline(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $directories = [$root . '/Workflow', $root . '/UX'];
        $files = [$root . '/Command/WorkflowListCommand.php', $root . '/Command/WorkflowRunCommand.php'];

        foreach ($directories as $directory) {
            self::assertDirectoryExists($directory);
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );
            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        self::assertGreaterThan(10, \count($files));
        foreach ($files as $file) {
            $source = \file_get_contents($file);
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }

            foreach (
                [
                    'SensitiveDataRedactorInterface',
                    'DefaultSensitiveDataRedactor',
                    'RedactionContext',
                    'RedactionMode',
                    'SensitiveKeyClassifier',
                    'SensitiveValueClassifier',
                    'RedactionPatternRegistry',
                    'RedactionHasher',
                    'OutputFormatter',
                    'ExceptionRenderer',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $code, $file);
            }
            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:class|interface|trait|enum)\s+\w*(?:Redactor|Redaction|Classifier|Hasher|PatternRegistry|Prompt|Confirmation|Preview|DiagnosticsRenderer)\w*\b/i',
                    $code,
                ),
                $file,
            );
            self::assertSame(0, \preg_match('/->(?:redactJsonLike|redact|format|write)\s*\(/', $code), $file);
        }
    }

    public function testExistingOutputFormatterIsTheOnlySharedFinalRedactionBoundary(): void
    {
        $reflection = new \ReflectionClass(OutputFormatter::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        $parameters = $constructor->getParameters();
        self::assertCount(4, $parameters);
        $type = $parameters[0]->getType();
        self::assertInstanceOf(\ReflectionNamedType::class, $type);
        self::assertSame(SensitiveDataRedactorInterface::class, $type->getName());
        $path = $reflection->getFileName();
        self::assertIsString($path);
        $source = \file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('$this->redactor->redactJsonLike(', $source);

        $root = \dirname(__DIR__, 2) . '/src';
        foreach (
            [
                'Workflow/Redaction',
                'UX/Redaction',
                'Workflow/Prompt',
                'Workflow/Preview',
                'UX/Prompt',
                'UX/Preview',
            ] as $folder
        ) {
            self::assertDirectoryDoesNotExist($root . '/' . $folder);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            self::assertSame(
                0,
                \preg_match(
                    '/(?:Workflow|Suggestion|Suggestor)\w*(?:Redactor|Redaction|Classifier|Hasher|PatternRegistry|Prompt|Confirmation|Preview|DiagnosticsRenderer)|(?:Redactor|Redaction|Classifier|Hasher|PatternRegistry|Prompt|Confirmation|Preview|DiagnosticsRenderer)\w*(?:Workflow|Suggestion|Suggestor)/i',
                    $file->getFilename(),
                ),
                $file->getPathname(),
            );

            if (\realpath($file->getPathname()) === \realpath($path)) {
                continue;
            }

            $content = \file_get_contents($file->getPathname());
            self::assertIsString($content);
            $code = '';

            foreach (\token_get_all($content) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertSame(
                0,
                \preg_match('/->(?:redactJsonLike|redact)\s*\(/', $code),
                $file->getPathname(),
            );
        }
    }
}
