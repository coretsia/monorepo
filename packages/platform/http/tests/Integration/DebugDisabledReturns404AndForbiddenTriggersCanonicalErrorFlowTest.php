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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\DebugForbiddenException;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class DebugDisabledReturns404AndForbiddenTriggersCanonicalErrorFlowTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testDisabledIsUnmatchedButEligibleDenialUsesCanonicalErrorBoundary(string $mode): void
    {
        foreach ([false, true] as $enabled) {
            $fixture = SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), [
                'appEnv' => 'local',
                'problemDetails' => true,
                'reporter' => true,
                'http' => [
                    'debug' => [
                        'endpoints' => ['enabled' => $enabled],
                        'token_sha256' => \hash('sha256', \str_repeat('a', 64)),
                    ],
                ],
            ]);

            try {
                $config = $fixture->container->get(ConfigRepositoryInterface::class);
                self::assertTrue($config->get('errors.reporting.enabled'));
                self::assertSame('info', $config->get('errors.reporting.min_severity'));
                self::assertSame([], $config->get('errors.reporting.exclude_codes'));
                self::assertSame(10000, $config->get('errors.reporting.sample_rate'));

                $registry = $fixture->container->get(TagRegistry::class);
                $mappers = $registry->all(ReservedTags::ERROR_MAPPER);
                $httpMappers = \array_values(
                    \array_filter(
                        $mappers,
                        static fn ($entry): bool => $entry->id() === HttpExceptionMapper::class,
                    ),
                );
                self::assertCount(1, $httpMappers);
                self::assertSame(100, $httpMappers[0]->priority());
                self::assertSame([], $httpMappers[0]->meta());
                self::assertInstanceOf(HttpExceptionMapper::class, $fixture->container->get($httpMappers[0]->id()));
                self::assertInstanceOf(
                    ExceptionMapperRegistry::class,
                    $fixture->container->get(ExceptionMapperRegistry::class),
                );

                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/headers',
                        'key=PRIVATE_QUERY',
                        [
                            'X-Debug-Token' => ['PRIVATE_TOKEN'],
                            'Authorization' => ['Bearer PRIVATE_CREDENTIAL'],
                            'Cookie' => ['session=PRIVATE_COOKIE'],
                        ],
                        serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                    ),
                );

                $body = (string) $response->getBody();
                if (!$enabled) {
                    self::assertSame(404, $response->getStatusCode());
                    self::assertSame('', $body);
                    self::assertSame('', $response->getHeaderLine('Content-Type'));
                    self::assertSame([], $fixture->reporter()->reports);
                    self::assertSame([], $fixture->recordingMeter()->incrementsFor('error.map_total'));
                    self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.debug_total'));
                } else {
                    self::assertCount(1, $fixture->reporter()->reports);
                    [$descriptor] = $fixture->reporter()->reports[0];
                    self::assertSame(DebugForbiddenException::ERROR_CODE, $descriptor->code());
                    self::assertSame(DebugForbiddenException::REASON_FORBIDDEN, $descriptor->message());
                    self::assertSame(ErrorSeverity::Error, $descriptor->severity());
                    self::assertSame(403, $descriptor->httpStatus());
                    self::assertSame([], $descriptor->extensions());
                    self::assertCount(1, $fixture->recordingMeter()->incrementsFor('error.map_total'));

                    self::assertSame(403, $response->getStatusCode());
                    self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
                    self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                    self::assertSame("{\"status\":403,\"title\":\"Forbidden\",\"type\":\"about:blank\"}\n", $body);
                    foreach (
                        [
                            'PRIVATE_',
                            'http-debug-forbidden',
                            DebugForbiddenException::ERROR_CODE,
                            '127.0.0.1',
                            '/_debug/headers',
                            'extensions',
                            'severity',
                        ] as $private
                    ) {
                        self::assertStringNotContainsString($private, $body);
                    }
                }

                self::assertCount(1, $fixture->runtime()->begins);
                self::assertSame([
                    [$fixture->runtime()->begins[0], 'handled_error', null],
                ], $fixture->runtime()->completions);
                self::assertSame(
                    SystemEndpointRuntimeFixture::CORRELATION_ID,
                    $response->getHeaderLine('X-Correlation-Id'),
                );
            } finally {
                $fixture->close();
            }
        }
    }
}
