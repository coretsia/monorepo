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

namespace Coretsia\Platform\HttpApp\Tests\Unit;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Platform\HttpApp\Exception\HttpAppDispatchException;
use Coretsia\Platform\HttpApp\Provider\HttpAppServiceProvider;
use Coretsia\Platform\HttpApp\Response\ActionResponseMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class ActionResponseMapperJsonAndHtmlTest extends TestCase
{
    #[DataProvider('featurePolicies')]
    public function testExistingResponsePassesThroughWithoutInspection(bool $json, bool $html): void
    {
        $response = $this->createMock(ResponseInterface::class);
        foreach (new \ReflectionClass(ResponseInterface::class)->getMethods() as $method) {
            $response->expects(self::never())->method($method->getName());
        }
        $mapper = $this->unusedFactories($json, $html);
        self::assertSame($response, $mapper->map($response));
    }

    /** @return iterable<string, array{bool, bool}> */
    public static function featurePolicies(): iterable
    {
        yield 'both-enabled' => [true, true];
        yield 'json-only' => [true, false];
        yield 'html-only' => [false, true];
        yield 'both-disabled' => [false, false];
    }

    #[DataProvider('representations')]
    public function testMapsExactBytesThroughSuppliedFactories(
        mixed $value,
        string $body,
        string $contentType,
        bool $json,
        bool $html,
    ): void {
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $initial = $this->createMock(ResponseInterface::class);
        $headed = $this->createMock(ResponseInterface::class);
        $final = $this->createStub(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(200)->willReturn($initial);
        $initial->expects(self::once())->method('withHeader')->with('Content-Type', $contentType)->willReturn($headed);
        $initial->expects(self::never())->method('withBody');
        $streams->expects(self::once())->method('createStream')
            ->with(self::identicalTo($body))->willReturn($stream);
        $streams->expects(self::never())->method('createStreamFromFile');
        $streams->expects(self::never())->method('createStreamFromResource');
        $headed->expects(self::once())->method('withBody')->with(self::identicalTo($stream))->willReturn($final);
        $config = require __DIR__ . '/../../config/http_app.php';
        $config['response']['json']['enabled'] = $json;
        $config['response']['html']['enabled'] = $html;
        $builder = new ContainerBuilder(['http_app' => $config]);
        $builder->instance(ResponseFactoryInterface::class, $responses);
        $builder->instance(StreamFactoryInterface::class, $streams);
        $container = $builder->register(new HttpAppServiceProvider())->build();
        $mapper = $container->get(ActionResponseMapper::class);
        self::assertInstanceOf(ActionResponseMapper::class, $mapper);
        self::assertSame($final, $mapper->map($value));
    }

    /** @return iterable<string, array{mixed, string, string, bool, bool}> */
    public static function representations(): iterable
    {
        $json = [
            'null' => [null, "null\n"],
            'true' => [true, "true\n"],
            'false' => [false, "false\n"],
            'zero' => [0, "0\n"],
            'negative' => [-42, "-42\n"],
            'empty-list' => [[], "[]\n"],
            'recursive-map' => [
                ['z' => [3, 1, ['z' => 'é/', 'a' => []]], 'a' => ['y' => 2, 'b' => true]],
                '{"a":{"b":true,"y":2},"z":[3,1,{"a":[],"z":"é/"}]}' . "\n",
            ],
            'same-map-other-order' => [
                ['a' => ['b' => true, 'y' => 2], 'z' => [3, 1, ['a' => [], 'z' => 'é/']]],
                '{"a":{"b":true,"y":2},"z":[3,1,{"a":[],"z":"é/"}]}' . "\n",
            ],
            'root-list' => [[2, 1, ['z' => 2, 'a' => 1]], '[2,1,{"a":1,"z":2}]' . "\n"],
        ];
        foreach ($json as $name => [$value, $body]) {
            foreach ([false, true] as $html) {
                yield $name . '-html-' . (int) $html => [$value, $body, 'application/json', true, $html];
            }
        }
        foreach (['<p>é & <b>literal</b></p>', "<div>\r\n</div>\n", '', '0', '{"literal":true}'] as $i => $body) {
            foreach ([false, true] as $jsonEnabled) {
                yield 'html-' . $i . '-json-' . (int) $jsonEnabled => [
                    $body,
                    $body,
                    'text/html; charset=utf-8',
                    $jsonEnabled,
                    true,
                ];
            }
        }
    }

    #[DataProvider('disabledRepresentations')]
    public function testDisabledMappingDoesNotFallBackToAnotherRepresentation(
        mixed $value,
        bool $json,
        bool $html,
    ): void {
        self::assertRejected($this->unusedFactories($json, $html), $value, 'http-app-response-disabled');
    }

    /** @return iterable<string, array{mixed, bool, bool}> */
    public static function disabledRepresentations(): iterable
    {
        yield 'json-disabled' => [['value' => 1], false, true];
        yield 'json-null-disabled' => [null, false, true];
        yield 'json-bool-disabled' => [false, false, true];
        yield 'json-int-disabled' => [0, false, true];
        yield 'html-disabled' => ['<p>private</p>', true, false];
        yield 'empty-html-disabled' => ['', true, false];
        yield 'both-disabled-json' => [[], false, false];
        yield 'both-disabled-html' => ['0', false, false];
    }

    #[DataProvider('unsupportedValues')]
    public function testUnsupportedReturnTypesAreRejectedWithoutFactories(mixed $value): void
    {
        self::assertRejected($this->unusedFactories(true, true), $value, 'http-app-response-unsupported');
    }

    /** @return iterable<string, array{mixed}> */
    public static function unsupportedValues(): iterable
    {
        yield 'float' => [1.5];
        yield 'nan' => [\NAN];
        yield 'infinity' => [\INF];
        yield 'negative-infinity' => [-\INF];
        yield 'object' => [(object) ['private' => 'private-return-value']];
        yield 'closure' => [static fn () => self::fail('unexpected-closure-call')];
        yield 'stringable' => [
            new class() implements \Stringable {
                public function __toString(): string
                {
                    throw new \LogicException('unexpected-string-conversion');
                }
            },
        ];
        yield 'json-serializable' => [
            new class() implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    throw new \LogicException('unexpected-json-serialization');
                }
            },
        ];
    }

    #[DataProvider('invalidJsonValues')]
    public function testInvalidNestedJsonValuesHaveOnlyFixedSafeDiagnostics(array $value): void
    {
        self::assertRejected($this->unusedFactories(true, true), $value, 'http-app-response-mapping-failed');
    }

    /** @return iterable<string, array{array}> */
    public static function invalidJsonValues(): iterable
    {
        yield 'float' => [['private-key' => [1.5]]];
        yield 'nan' => [['private-key' => [\NAN]]];
        yield 'inf' => [['private-key' => [\INF]]];
        yield 'object' => [['private-key' => new \stdClass()]];
        yield 'closure' => [['private-key' => static fn () => 'private-value']];
        yield 'mixed-map-keys' => [['private-key' => 'private-value', 1 => 'invalid']];
        yield 'sparse-list' => [[2 => 'private-value']];
        yield 'invalid-utf8' => [['private-key' => "private-value\xff"]];
        yield 'invalid-utf8-key' => [["private-key\xff" => 'private-value']];
    }

    public function testResourcesAndCyclicArraysAreRejected(): void
    {
        $resource = \fopen('php://memory', 'r+');
        self::assertTrue(\is_resource($resource));
        try {
            $mapper = $this->unusedFactories(true, true);
            self::assertRejected($mapper, $resource, 'http-app-response-unsupported');
            self::assertRejected($mapper, ['private-key' => $resource], 'http-app-response-mapping-failed');
        } finally {
            \fclose($resource);
        }
        $cycle = [];
        $cycle['private-key'] = &$cycle;
        self::assertRejected($this->unusedFactories(true, true), $cycle, 'http-app-response-mapping-failed');
        unset($cycle);
    }

    #[DataProvider('transportFailureStages')]
    public function testTransportFailuresAreSanitizedWithoutReturningPartialResponse(
        string $stage,
        bool $admitted,
    ): void {
        $failure = $admitted
            ? HttpAppDispatchException::withReason('http-app-argument-invalid')
            : new \RuntimeException('private-factory-diagnostics');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $headed = $this->createMock(ResponseInterface::class);
        $stream = $this->createStub(StreamInterface::class);
        $create = $responses->expects(self::once())->method('createResponse')->with(200);
        if ($stage === 'response') {
            $create->willThrowException($failure);
        } else {
            $create->willReturn($response);
        }
        $header = $response->expects($stage === 'response' ? self::never() : self::once())->method('withHeader');
        if ($stage === 'header') {
            $header->willThrowException($failure);
        } else {
            $header->willReturn($headed);
        }
        $bodyStream = $streams
            ->expects(\in_array($stage, ['response', 'header'], true) ? self::never() : self::once())
            ->method('createStream');
        if ($stage === 'stream') {
            $bodyStream->willThrowException($failure);
        } else {
            $bodyStream->willReturn($stream);
        }
        $body = $headed->expects($stage === 'body' ? self::once() : self::never())->method('withBody');
        $body->willThrowException($failure);
        self::assertRejected(
            new ActionResponseMapper($responses, $streams, true, true),
            ['value' => 1],
            $admitted ? 'http-app-argument-invalid' : 'http-app-response-mapping-failed',
        );
    }

    /** @return iterable<string, array{string, bool}> */
    public static function transportFailureStages(): iterable
    {
        foreach (['response', 'header', 'stream', 'body'] as $stage) {
            yield $stage => [$stage, false];
            yield $stage . '-admitted' => [$stage, true];
        }
    }

    private function unusedFactories(bool $json, bool $html): ActionResponseMapper
    {
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        foreach (new \ReflectionClass(StreamFactoryInterface::class)->getMethods() as $method) {
            $streams->expects(self::never())->method($method->getName());
        }

        return new ActionResponseMapper($responses, $streams, $json, $html);
    }

    private static function assertRejected(ActionResponseMapper $mapper, mixed $value, string $reason): void
    {
        try {
            $mapper->map($value);
        } catch (HttpAppDispatchException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame($reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('invalid-response-mapping-accepted');
    }
}
