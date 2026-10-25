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

namespace Coretsia\Platform\Errors\Tests\Contract;

use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Provider\ErrorsServiceProvider;
use PHPUnit\Framework\TestCase;

final class ErrorsDoNotDuplicateRedactionPolicyContractTest extends TestCase
{
    public function testProductionSourcesContainNoConcreteRedactionImportsOrDuplicatedClassifierPolicy(): void
    {
        $sourceRoot = \dirname(__DIR__, 2) . '/src';
        self::assertDirectoryDoesNotExist($sourceRoot . '/Redaction');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot));
        $checked = 0;

        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$checked;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $tokens = \token_get_all($source);
            $code = '';

            foreach ($tokens as $token) {
                if (
                    \is_array($token)
                    && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT, T_CONSTANT_ENCAPSED_STRING], true)
                ) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertSame(
                0,
                \preg_match('/Coretsia\\\\Platform\\\\Redaction\\\\/i', $code),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:class|interface|trait|enum)\s+\w*'
                    . '(?:Redaction(?:Engine|Policy|Hasher|Registry|Classifier)'
                    . '|Sensitive(?:Key|Value)(?:Classifier|Detector)|PatternRegistry)\b/i',
                    $code,
                ),
                $file->getFilename(),
            );
            self::assertSame(
                0,
                \preg_match(
                    '/\bnew\s+(?:DefaultSensitiveDataRedactor|SensitiveKeyClassifier'
                    . '|SensitiveValueClassifier|StableRedactionHasher|RedactionPolicy|RedactionEngine)\b/',
                    $code,
                ),
                $file->getFilename(),
            );
        }

        self::assertGreaterThanOrEqual(5, $checked);
    }

    public function testHandlerAndProviderConsumeExistingRedactorPortWithoutRebindingIt(): void
    {
        $parameters = new \ReflectionMethod(ErrorHandler::class, '__construct')->getParameters();
        self::assertSame(SensitiveDataRedactorInterface::class, $parameters[2]->getType()?->getName());
        $provider = new ContainerDefinitionBuilder();
        new ErrorsServiceProvider()->define(
            $provider,
            new ContainerDefinitionContext([]),
        );
        $set = $provider->build();
        self::assertContains(SensitiveDataRedactorInterface::class, $set->requiredServiceIds());

        foreach ($set->toDescriptorStream() as $operation) {
            self::assertNotSame(SensitiveDataRedactorInterface::class, $operation['id'] ?? null);
            self::assertNotSame(SensitiveDataRedactorInterface::class, $operation['alias'] ?? null);
        }
    }
}
