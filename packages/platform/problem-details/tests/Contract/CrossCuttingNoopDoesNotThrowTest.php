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

namespace Coretsia\Platform\ProblemDetails\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\Http\Middleware\HttpErrorHandlingMiddleware;
use Coretsia\Platform\ProblemDetails\ProblemDetails;
use Coretsia\Platform\ProblemDetails\Provider\ProblemDetailsServiceProvider;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testRepeatedAdaptationAndRenderingAreSafeWithoutObservabilityOrOutput(): void
    {
        $adapter = new ErrorDescriptorToProblemDetailsAdapter();
        $renderer = new ProblemDetailsJsonRenderer();
        $expected = "{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n";
        $descriptors = [
            new ErrorDescriptor(
                code: 'SYNTHETIC_FIRST_SECRET',
                message: 'SYNTHETIC_MESSAGE_PRIVATE',
                httpStatus: null,
                extensions: ['note' => 'SYNTHETIC_EXTENSION_PRIVATE'],
            ),
            new ErrorDescriptor(
                code: 'SYNTHETIC_SECOND_SECRET',
                message: 'SYNTHETIC_THROWABLE_PRIVATE',
                httpStatus: 302,
                extensions: ['other_note' => 'SYNTHETIC_OTHER_PRIVATE'],
            ),
        ];

        \ob_start();
        try {
            for ($attempt = 0; $attempt < 24; ++$attempt) {
                $problem = $adapter->map($descriptors[$attempt % 2]);

                self::assertSame(500, $problem->status());
                self::assertSame('Internal Server Error', $problem->title());
                self::assertSame('about:blank', $problem->type());
                self::assertSame($expected, $renderer->render($problem));
                self::assertSame($expected, new ProblemDetailsJsonRenderer()->render(new ProblemDetails(500)));
            }
        } finally {
            $output = (string) \ob_get_clean();
        }

        self::assertSame('', $output);
        self::assertStringNotContainsString('SYNTHETIC_', $expected);
    }

    public function testProviderContributesNoObservabilityOrResetServicesAndNoExtraTags(): void
    {
        $definitions = new ContainerDefinitionBuilder();
        new ProblemDetailsServiceProvider()->define(
            $definitions,
            new ContainerDefinitionContext(['problem_details' => []]),
        );
        $set = $definitions->build();
        $operations = $set->toDescriptorStream();

        self::assertSame(
            [
            ErrorDescriptorToProblemDetailsAdapter::class,
            ProblemDetailsJsonRenderer::class,
            HttpErrorHandlingMiddleware::class,
        ],
            \array_values(
                \array_column(
                    \array_filter(
                        $operations,
                        static fn (array $operation): bool => $operation['kind'] === 'service.class',
                    ),
                    'id',
                ),
            )
        );

        $tags = \array_values(
            \array_filter(
                $operations,
                static fn (array $operation): bool => $operation['kind'] === 'tag',
            ),
        );
        self::assertCount(1, $tags);
        self::assertSame(ReservedTags::HTTP_MIDDLEWARE_SYSTEM_PRE, $tags[0]['tag']);
        self::assertSame(HttpErrorHandlingMiddleware::class, $tags[0]['serviceId']);
        self::assertSame([], $tags[0]['meta']);
        self::assertNotContains(ReservedTags::KERNEL_RESET, \array_column($tags, 'tag'));
        self::assertSame(
            [],
            \array_values(
                \array_filter(
                    $operations,
                    static fn (array $operation): bool => \in_array($operation['kind'], ['alias', 'parameter'], true),
                ),
            )
        );
    }

    public function testRuntimeComponentsHaveNoMutableRegistryOrResetLifecycle(): void
    {
        foreach (
            [
                ProblemDetails::class,
                ErrorDescriptorToProblemDetailsAdapter::class,
                ProblemDetailsJsonRenderer::class,
                ProblemDetailsServiceProvider::class,
                HttpErrorHandlingMiddleware::class,
            ] as $class
        ) {
            $reflection = new ReflectionClass($class);
            self::assertTrue($reflection->isFinal(), $class);
            self::assertFalse($reflection->hasMethod('reset'), $class);
            self::assertFalse($reflection->hasMethod('report'), $class);
            self::assertFalse($reflection->hasMethod('log'), $class);
            self::assertFalse($reflection->hasMethod('record'), $class);

            foreach ($reflection->getInterfaceNames() as $interface) {
                self::assertStringNotContainsString('ResetInterface', $interface);
            }
            foreach ($reflection->getProperties() as $property) {
                self::assertFalse($property->isStatic(), $class);
                self::assertTrue($property->isReadOnly(), $class);
            }
        }

        self::assertSame([], new ReflectionClass(ErrorDescriptorToProblemDetailsAdapter::class)->getProperties());
        self::assertSame([], new ReflectionClass(ProblemDetailsJsonRenderer::class)->getProperties());
        self::assertSame([], new ReflectionClass(ProblemDetailsServiceProvider::class)->getProperties());
    }

    public function testProductionSourceDoesNotReadOrWriteContextOrInstrumentTheErrorAgain(): void
    {
        $sourceRoot = \dirname(__DIR__, 2) . '/src';
        $count = 0;

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceRoot)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            ++$count;
            $source = \file_get_contents($file->getPathname());
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (
                    \is_array($token)
                    && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT, \T_CONSTANT_ENCAPSED_STRING], true)
                ) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }

            self::assertDoesNotMatchRegularExpression(
                '/\b(?:ContextStore|ContextAccessorInterface|ContextKeys|UnitOfWork|ResetInterface|'
                . 'ErrorReporterPortInterface|LoggerInterface|TracerPortInterface|MeterPortInterface|'
                . 'SpanInterface|error_log|trigger_error|syslog|fwrite|file_put_contents)\b/',
                $code,
            );
            self::assertDoesNotMatchRegularExpression(
                '/\b(?:echo|print|exit|die|var_dump|print_r)\b|\b(?:log|record|startSpan|reset)\s*\(/',
                $code,
            );
        }

        self::assertSame(6, $count);
    }
}
