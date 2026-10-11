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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class RedactionRuntimeHasNoContextObservabilityOrResetDependencyContractTest extends TestCase
{
    public function testProductionServicesHaveNoForbiddenDependenciesOrMutableState(): void
    {
        $classes = [
            DefaultSensitiveDataRedactor::class,
            SensitiveKeyClassifier::class,
            SensitiveValueClassifier::class,
            StableRedactionHasher::class,
            RedactionServiceProvider::class,
        ];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal());
            foreach ($reflection->getInterfaceNames() as $interface) {
                self::assertStringNotContainsString('ResetInterface', $interface);
            }

            foreach ($reflection->getProperties() as $property) {
                self::assertFalse($property->isStatic());
                self::assertTrue($property->isReadOnly());
            }
        }

        $src = \dirname(__DIR__, 2) . '/src';
        $count = 0;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$count;
            $contents = \file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $code = self::withoutComments($contents);
            foreach (
                [
                    'ContextStore',
                    'ContextAccessorInterface',
                    'KernelRuntimeInterface',
                    'ResetInterface',
                    'LoggerInterface',
                    'TracerPortInterface',
                    'MeterPortInterface',
                    'ErrorReporterPortInterface',
                    'ProfilerPortInterface',
                    'Logger',
                    'Tracer',
                    'Meter',
                    'Reporter',
                    'kernel.stateful',
                    'kernel.reset',
                    'psr/log',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $code);
            }
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:echo|print|exit|die)\b|\b(?:fwrite|fputs|fprintf|printf|vprintf|var_dump|print_r|error_log|trigger_error|user_error|syslog|openlog|file_put_contents)\s*\(|\bSTD(?:OUT|ERR)\b|php:\/\/(?:stdout|stderr|output)/i',
                $code,
            );
        }
        self::assertSame(6, $count);
    }

    public function testProviderDoesNotIntroduceTagsAndRedactionWritesNoOutput(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new RedactionServiceProvider()->define($definitions, new ContainerDefinitionContext([]));
        foreach ($definitions->build()->toDescriptorStream() as $operation) {
            self::assertNotSame('tag', $operation['kind']);
            self::assertNotSame('parameter', $operation['kind']);
        }

        $redactor = new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
        \ob_start();
        try {
            $redactor->redactValue(
                'synthetic-private-string',
                RedactionKind::Secret,
                new RedactionContext('redaction.noop'),
            );
            $redactor->redactJsonLike(
                ['password' => 'synthetic-private-string'],
                new RedactionContext('redaction.noop'),
            );
            try {
                $redactor->redactJsonLike(
                    ['Bearer synthetic-token' => 'synthetic-private-string'],
                    new RedactionContext('redaction.noop'),
                );
                self::fail('A sensitive map key must fail closed.');
            } catch (RedactionException $exception) {
                self::assertSame(RedactionException::REASON_SENSITIVE_MAP_KEY, $exception->reason());
            }
        } finally {
            $output = \ob_get_clean();
        }
        self::assertSame('', $output);
    }

    private static function withoutComments(string $source): string
    {
        $code = '';
        foreach (\token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                continue;
            }
            $code .= \is_array($token) ? $token[1] : $token;
        }

        return $code;
    }
}
