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
use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorHandlerInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Coretsia\Platform\Http\Error\HttpExceptionMapper;
use Coretsia\Platform\Http\Exception\IntentionalErrorException;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

final class ErrorRouteTriggersCanonicalErrorFlowTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testSelectedProblemDetailsReportsCanonicalDescriptorAndSafePublicResponse(string $mode): void
    {
        foreach ([false, true] as $throwMeter) {
            $fixture = SystemEndpointRuntimeFixture::boot(
                $mode,
                fn (string $class): object => $this->createStub($class),
                ['problemDetails' => true, 'reporter' => true, 'throwMeter' => $throwMeter],
            );

            try {
                $config = $fixture->container->get(ConfigRepositoryInterface::class);
                self::assertTrue($config->get('errors.reporting.enabled'));
                self::assertSame(10000, $config->get('errors.reporting.sample_rate'));
                self::assertNotContains(
                    'CORETSIA_HTTP_INTENTIONAL_ERROR',
                    $config->get('errors.reporting.exclude_codes'),
                );

                self::assertInstanceOf(ErrorHandler::class, $fixture->container->get(ErrorHandlerInterface::class));
                $tags = $fixture->container->get(TagRegistry::class)->all(ReservedTags::ERROR_MAPPER);
                $httpMappers = \array_values(
                    \array_filter($tags, static fn ($tag): bool => $tag->id === HttpExceptionMapper::class),
                );
                self::assertCount(1, $httpMappers);
                self::assertSame(100, $httpMappers[0]->priority);
                self::assertSame([], $httpMappers[0]->meta);
                self::assertInstanceOf(HttpExceptionMapper::class, $fixture->container->get($httpMappers[0]->id));

                $mapped = $fixture->container->get(ExceptionMapperRegistry::class)->map(
                    IntentionalErrorException::intentional(),
                );
                self::assertInstanceOf(ErrorDescriptor::class, $mapped);
                self::assertIntentionalDescriptor($mapped);
                self::assertSame([], $fixture->reporter()->reports);

                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/error',
                        'token=private-query',
                        ['Authorization' => ['Bearer private-credential']],
                    ),
                );

                // Reporter evidence distinguishes canonical normalization from
                // the emergency response, whose public bytes may be identical.
                $reports = $fixture->reporter()->reports;
                self::assertCount(1, $reports);
                self::assertIntentionalDescriptor($reports[0][0]);

                self::assertSame(500, $response->getStatusCode());
                self::assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
                self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
                self::assertSame([
                    'Content-Type' => ['application/problem+json'],
                    'Cache-Control' => ['no-store'],
                    'X-Correlation-Id' => [SystemEndpointRuntimeFixture::CORRELATION_ID],
                    'X-Content-Type-Options' => ['nosniff'],
                    'X-Frame-Options' => ['DENY'],
                    'Referrer-Policy' => ['no-referrer'],
                ], $response->getHeaders());
                $body = (string) $response->getBody();
                self::assertSame(
                    "{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n",
                    $body,
                );
                self::assertSame([
                    'status' => 500,
                    'title' => 'Internal Server Error',
                    'type' => 'about:blank',
                ], \json_decode($body, true, 512, JSON_THROW_ON_ERROR));
                self::assertSame([500], $fixture->factories()->statuses);
                self::assertCount(1, $fixture->runtime()->begins);
                self::assertSame(
                    [[$fixture->runtime()->begins[0], 'handled_error', null]],
                    $fixture->runtime()->completions
                );
                self::assertSame([
                    [
                        'http.system_endpoint_total',
                        1,
                        ['operation' => 'error', 'status' => 'unknown', 'outcome' => 'intentional_error'],
                    ],
                ], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame([
                    ['http.request_total', 1, ['method' => 'GET', 'status' => 500, 'outcome' => 'handled_error']],
                ], $fixture->recordingMeter()->incrementsFor('http.request_total'));
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testAbsentProblemDetailsPreservesIntentionalThrowableEvenWhenMeterThrows(string $mode): void
    {
        foreach ([false, true] as $throwMeter) {
            $fixture = SystemEndpointRuntimeFixture::boot(
                $mode,
                fn (string $class): object => $this->createStub($class),
                ['problemDetails' => false, 'reporter' => true, 'throwMeter' => $throwMeter],
            );

            try {
                try {
                    $fixture->kernel()->handle($fixture->request('GET', '/error'));
                    self::fail('The intentional endpoint must throw without the selected HTTP error boundary.');
                } catch (IntentionalErrorException $failure) {
                    self::assertSame('CORETSIA_HTTP_INTENTIONAL_ERROR', $failure->errorCode());
                    self::assertSame('http-intentional-error', $failure->reason());
                    self::assertSame('CORETSIA_HTTP_INTENTIONAL_ERROR: http-intentional-error', $failure->getMessage());
                    self::assertNull($failure->getPrevious());
                    self::assertCount(1, $fixture->runtime()->begins);
                    self::assertSame(
                        [[$fixture->runtime()->begins[0], 'fatal_error', $failure]],
                        $fixture->runtime()->completions
                    );
                }

                self::assertSame([], $fixture->reporter()->reports);
                self::assertSame([], $fixture->factories()->statuses);
                self::assertSame([
                    [
                        'http.system_endpoint_total',
                        1,
                        ['operation' => 'error', 'status' => 'unknown', 'outcome' => 'intentional_error'],
                    ],
                ], $fixture->recordingMeter()->incrementsFor('http.system_endpoint_total'));
                self::assertSame([
                    ['http.request_total', 1, ['method' => 'GET', 'status' => 'unknown', 'outcome' => 'fatal_error']],
                ], $fixture->recordingMeter()->incrementsFor('http.request_total'));
            } finally {
                $fixture->close();
            }
        }
    }

    private static function assertIntentionalDescriptor(ErrorDescriptor $descriptor): void
    {
        self::assertSame('CORETSIA_HTTP_INTENTIONAL_ERROR', $descriptor->code());
        self::assertSame('http-intentional-error', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame(500, $descriptor->httpStatus());
        self::assertSame([], $descriptor->extensions());
    }
}
