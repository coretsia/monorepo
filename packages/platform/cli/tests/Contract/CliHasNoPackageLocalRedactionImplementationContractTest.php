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

final class CliHasNoPackageLocalRedactionImplementationContractTest extends TestCase
{
    public function testCliContainsNoLocalRedactionRuntimeOrClassifierPolicyHasherRegistry(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        self::assertDirectoryDoesNotExist($root . '/Redaction');
        self::assertDirectoryDoesNotExist($root . '/Output/Redaction');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $checked = 0;
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            ++$checked;
            self::assertNotContains($file->getFilename(), [
                'RedactionEngine.php',
                'RedactionPolicy.php',
                'DefaultSensitiveDataRedactor.php',
                'SensitiveKeyClassifier.php',
                'SensitiveValueClassifier.php',
                'RedactionHasher.php',
                'RedactionPatternRegistry.php',
            ]);
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }
            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:class|interface|trait|enum)\s+\w*(?:Sensitive\w*(?:Classifier|Detector|Matcher)|Redaction\w*(?:Classifier|Detector|Policy|Hasher|Registry|Engine)|PatternRegistry)\b/i',
                    $code,
                ),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match(
                    '/\bfunction\s+(?:isSensitive(?:Key|Value)|(?:classify|detect|match)(?:Sensitive|Secret)(?:Key|Value)|shouldRedact(?:Key|Value)?|redact(?:Value|JsonLike|Key)|hash(?:Sensitive|Redacted)(?:Value)?)\s*\(/i',
                    $code,
                ),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match(
                    '/Coretsia\\\\Platform\\\\Redaction(?:\\\\|\b)|\bDefaultSensitiveDataRedactor\b/',
                    $code,
                ),
                $file->getFilename(),
            );
        }
        self::assertGreaterThan(30, $checked);
    }

    public function testOutputFormatterHasOnlySharedRedactorPortAndFormatterDependencies(): void
    {
        $parameters = new \ReflectionMethod(OutputFormatter::class, '__construct')->getParameters();
        self::assertCount(4, $parameters);
        self::assertSame(SensitiveDataRedactorInterface::class, $parameters[0]->getType()?->getName());
        self::assertSame(
            'Coretsia\\Platform\\Cli\\Output\\Formatter\\JsonFormatter',
            $parameters[1]->getType()?->getName(),
        );
        self::assertSame(
            'Coretsia\\Platform\\Cli\\Output\\Formatter\\TableFormatter',
            $parameters[2]->getType()?->getName(),
        );
        self::assertSame(
            'Coretsia\\Platform\\Cli\\Output\\Formatter\\PlainFormatter',
            $parameters[3]->getType()?->getName(),
        );
        $source = \file_get_contents(new \ReflectionClass(OutputFormatter::class)->getFileName());
        self::assertIsString($source);
        self::assertStringContainsString('$this->redactor->redactJsonLike(', $source);
        self::assertSame(
            0,
            \preg_match(
                '/\bnew\s+(?:DefaultSensitiveDataRedactor|\w*Redaction(?:Engine|Policy|Hasher|Registry))\b/',
                $source,
            ),
        );
    }
}
