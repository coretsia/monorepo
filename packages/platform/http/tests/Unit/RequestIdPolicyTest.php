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

namespace Coretsia\Platform\Http\Tests\Unit;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RequestIdPolicyTest extends TestCase
{
    private const string HEADER = 'X-Edge-Request-Id';
    private const string GENERATED = '01ARZ3NDEKTSV4RRFFQ69G5FAA';
    private const string CORRELATION = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    private const string UOW = '01ARZ3NDEKTSV4RRFFQ69G5FAW';

    #[DataProvider('upstreamModes')]
    public function testDisabledPolicyDoesNotAccessContextHeadersOrGenerator(bool $acceptFromUpstream): void
    {
        $writer = $this->createMock(ContextWriterInterface::class);
        $writer->expects(self::never())->method('set');
        $writer->expects(self::never())->method('remove');
        $accessor = $this->createMock(ContextAccessorInterface::class);
        $accessor->expects(self::never())->method('has');
        $accessor->expects(self::never())->method('get');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $request = $this->requestWithoutHeaderAccess();
        $policy = new RequestIdPolicy($writer, $accessor, $generator, false, self::HEADER, $acceptFromUpstream, 128);

        self::assertTrue($policy->initialize($request) === null, 'disabled-initialize-not-null');
        self::assertTrue($policy->current() === null, 'disabled-current-not-null');
    }

    public function testDisabledPolicyPreservesExistingContext(): void
    {
        $context = self::baseContext();
        $context->set(ContextKeys::REQUEST_ID, 'existing-identity');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $policy = new RequestIdPolicy($context, $context, $generator, false, self::HEADER, true, 128);

        self::assertTrue(
            $policy->initialize($this->requestWithoutHeaderAccess()) === null,
            'disabled-initialize-not-null',
        );
        self::assertTrue($policy->current() === null, 'disabled-current-not-null');
        self::assertTrue($context->get(ContextKeys::REQUEST_ID) === 'existing-identity', 'disabled-context-mutated');
        self::assertBaseContext($context);
    }

    /** @return iterable<string, array{bool}> */
    public static function upstreamModes(): iterable
    {
        yield 'upstream-disabled' => [false];
        yield 'upstream-enabled' => [true];
    }

    #[DataProvider('upstreamModes')]
    public function testEnabledPolicyWritesAndReadsRequestIdOnlyThroughContextPorts(bool $acceptFromUpstream): void
    {
        $selected = $acceptFromUpstream ? 'upstream-identity' : self::GENERATED;
        $writer = $this->createMock(ContextWriterInterface::class);
        $writer->expects(self::once())->method('set')->with(ContextKeys::REQUEST_ID, $selected);
        $writer->expects(self::never())->method('remove');
        $accessor = $this->createMock(ContextAccessorInterface::class);
        $accessor->expects(self::never())->method('has');
        $accessor->expects(self::exactly(2))->method('get')->with(ContextKeys::REQUEST_ID)
            ->willReturnOnConsecutiveCalls($selected, 'replacement-identity');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($acceptFromUpstream ? self::never() : self::once())->method('generate')
            ->willReturn(self::GENERATED);

        if ($acceptFromUpstream) {
            $request = $this->createMock(ServerRequestInterface::class);
            $request->expects(self::once())->method('getHeader')->with(self::HEADER)->willReturn([$selected]);
            $request->expects(self::never())->method('getHeaderLine');
            $request->expects(self::never())->method('getHeaders');
            $request->expects(self::never())->method('hasHeader');
        } else {
            $request = $this->requestWithoutHeaderAccess();
        }

        $policy = new RequestIdPolicy($writer, $accessor, $generator, true, self::HEADER, $acceptFromUpstream, 128);

        self::assertSame($selected, $policy->initialize($request));
        self::assertSame($selected, $policy->current());
        self::assertSame('replacement-identity', $policy->current());
    }

    public function testEnabledPolicyWithoutUpstreamAcceptanceDoesNotReadHeaders(): void
    {
        $context = self::baseContext();
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willReturn(self::GENERATED);
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, false, 26);

        self::assertIdentity(
            $policy,
            $context,
            $policy->initialize($this->requestWithoutHeaderAccess()),
            self::GENERATED,
        );
    }

    /** @param array<array-key, mixed> $headers */
    #[DataProvider('upstreamValues')]
    public function testUpstreamSelectionUsesExactCardinalityTypeSyntaxAndByteLength(
        array $headers,
        int $maxLength,
        ?string $accepted,
    ): void {
        $context = self::baseContext();
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects($accepted === null ? self::once() : self::never())->method('generate')
            ->willReturn(self::GENERATED);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->willReturnCallback(
            static function (string $name) use ($headers): array {
                self::assertTrue($name === self::HEADER, 'request-id-header-name-mismatch');

                return $headers;
            },
        );
        $request->expects(self::never())->method('getHeaderLine');
        $request->expects(self::never())->method('getHeaders');
        $request->expects(self::never())->method('hasHeader');
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, true, $maxLength);

        self::assertTrue($policy->headerName() === self::HEADER, 'configured-header-name-mismatch');
        self::assertIdentity($policy, $context, $policy->initialize($request), $accepted ?? self::GENERATED);
    }

    /** @return iterable<string, array{array<array-key, mixed>, int, ?string}> */
    public static function upstreamValues(): iterable
    {
        yield 'single-valid' => [['AbC-0._9'], 128, 'AbC-0._9'];
        yield 'non-zero-index' => [[7 => 'MiXeD-42'], 128, 'MiXeD-42'];
        yield 'associative-index' => [['value' => 'opaque-1'], 128, 'opaque-1'];
        yield 'minimum-value' => [['0'], 26, '0'];
        yield '26-bytes' => [[\str_repeat('A', 26)], 26, \str_repeat('A', 26)];
        yield '128-bytes' => [[\str_repeat('b', 128)], 128, \str_repeat('b', 128)];
        yield 'missing' => [[], 128, null];
        yield 'multiple-equal' => [['same', 'same'], 128, null];
        yield 'multiple-distinct' => [['first', 'second'], 128, null];
        yield 'multiple-with-empty' => [['valid', ''], 128, null];
        yield 'integer' => [[42], 128, null];
        yield 'boolean' => [[true], 128, null];
        yield 'null' => [[null], 128, null];
        yield 'float' => [[1.5], 128, null];
        yield 'array' => [[['valid']], 128, null];
        yield 'stringable-object' => [
            [
                new class() implements \Stringable {
                    public function __toString(): string
                    {
                        return 'valid';
                    }
                },
            ],
            128,
            null,
        ];
        yield 'empty' => [[''], 128, null];
        yield 'space' => [['a b'], 128, null];
        yield 'leading-space' => [[' valid'], 128, null];
        yield 'trailing-space' => [['valid '], 128, null];
        yield 'tab' => [["a\tb"], 128, null];
        yield 'crlf' => [["a\r\nb"], 128, null];
        yield 'nul' => [["a\0b"], 128, null];
        yield 'newline-suffix' => [["valid\n"], 128, null];
        yield 'comma-list' => [['a,b'], 128, null];
        yield 'slash' => [['a/b'], 128, null];
        yield 'percent-encoding' => [['a%20b'], 128, null];
        yield 'non-ascii' => [['é'], 128, null];
        yield 'invalid-utf8' => [["\xff"], 128, null];
        yield '27-bytes' => [[\str_repeat('a', 27)], 26, null];
        yield '129-bytes' => [[\str_repeat('a', 129)], 128, null];
    }

    public function testHeaderAccessFailurePropagatesWithoutGenerationOrContextWrite(): void
    {
        $context = self::baseContext();
        $failure = new \RuntimeException('synthetic-header-read-failure');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::once())->method('getHeader')->willThrowException($failure);
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, true, 128);

        try {
            $policy->initialize($request);
            self::fail('header-read-failure-not-propagated');
        } catch (\RuntimeException $actual) {
            self::assertTrue($actual === $failure, 'header-read-throwable-replaced');
        }
        self::assertFalse($context->has(ContextKeys::REQUEST_ID), 'request-id-written-after-header-failure');
        self::assertBaseContext($context);
    }

    public function testGeneratorFailurePropagatesWithoutContextWrite(): void
    {
        $context = self::baseContext();
        $failure = new \RuntimeException('synthetic-generation-failure');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willThrowException($failure);
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, false, 128);

        try {
            $policy->initialize($this->requestWithoutHeaderAccess());
            self::fail('generator-failure-not-propagated');
        } catch (\RuntimeException $actual) {
            self::assertTrue($actual === $failure, 'generator-throwable-replaced');
        }
        self::assertFalse($context->has(ContextKeys::REQUEST_ID), 'request-id-written-after-generation-failure');
        self::assertBaseContext($context);
    }

    #[DataProvider('generatedValues')]
    public function testGeneratedValueMustBeCanonicalUlid(string $generated, bool $valid): void
    {
        $context = self::baseContext();
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::once())->method('generate')->willReturn($generated);
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, false, 26);
        $request = $this->requestWithoutHeaderAccess();

        if ($valid) {
            self::assertIdentity($policy, $context, $policy->initialize($request), $generated);

            return;
        }

        try {
            $policy->initialize($request);
            self::fail('invalid-generator-output-accepted');
        } catch (\RuntimeException $actual) {
            self::assertTrue(
                $actual->getMessage() === 'http-request-id-generation-invalid',
                'generation-reason-mismatch',
            );
        }
        self::assertFalse($context->has(ContextKeys::REQUEST_ID), 'invalid-generator-output-written');
        self::assertBaseContext($context);
    }

    /** @return iterable<string, array{string, bool}> */
    public static function generatedValues(): iterable
    {
        yield 'canonical' => [self::GENERATED, true];
        yield 'minimum' => [\str_repeat('0', 26), true];
        yield 'maximum' => ['7' . \str_repeat('Z', 25), true];
        yield 'overflow' => ['8' . \str_repeat('0', 25), false];
        yield 'lowercase' => [\strtolower(self::GENERATED), false];
        yield 'short' => ['0' . \str_repeat('A', 24), false];
        yield 'long' => ['0' . \str_repeat('A', 26), false];
        foreach (['I', 'L', 'O', 'U'] as $excluded) {
            yield 'excluded-alphabet-' . $excluded => ['0' . \str_repeat($excluded, 25), false];
        }
        yield 'empty' => ['', false];
        yield 'punctuation' => ['0' . \str_repeat('-', 25), false];
        yield 'newline' => [self::GENERATED . "\n", false];
        yield 'uuid' => ['12345678-1234-1234-1234-123456789012', false];
    }

    #[DataProvider('headerNames')]
    public function testConfiguredHeaderNameValidation(string $name, bool $valid): void
    {
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $context = new ContextStore();

        if ($valid) {
            $policy = new RequestIdPolicy($context, $context, $generator, false, $name, false, 128);
            self::assertTrue($policy->headerName() === $name, 'configured-header-name-changed');

            return;
        }

        try {
            new RequestIdPolicy($context, $context, $generator, false, $name, false, 128);
            self::fail('invalid-header-name-accepted');
        } catch (\InvalidArgumentException $actual) {
            self::assertTrue($actual->getMessage() === 'http-request-id-header-invalid', 'header-name-reason-mismatch');
        }
    }

    /** @return iterable<string, array{string, bool}> */
    public static function headerNames(): iterable
    {
        yield 'standard' => ['X-Request-Id', true];
        yield 'custom' => [self::HEADER, true];
        yield 'bare' => ['Request-Id', true];
        yield 'mixed-case' => ['x-EDGE-ReQuEsT-iD', true];
        yield '128-bytes' => [\str_repeat('a', 117) . '-request-id', true];
        yield '129-bytes' => [\str_repeat('a', 118) . '-request-id', false];
        yield 'empty' => ['', false];
        yield 'space' => ['X Request-Id', false];
        yield 'colon' => ['X:Request-Id', false];
        yield 'newline' => ["X-Request-Id\n", false];
        yield 'non-ascii' => ['é-Request-Id', false];
        yield 'underscore' => ['X_Edge-Request-Id', false];
        yield 'empty-prefix' => ['X--Request-Id', false];
        yield 'suffix' => ['X-Request-Id-Extra', false];
        foreach (
            [
                'Authorization',
                'Cookie',
                'Set-Cookie',
                'Content-Type',
                'Content-Length',
                'Host',
                'X-Correlation-Id',
                'Correlation-Id',
                'X-Edge-Correlation-Id',
            ] as $index => $protected
        ) {
            yield 'protected-' . $index => [$protected, false];
        }
    }

    #[DataProvider('lengthLimits')]
    public function testConfigurationLengthLimits(int $limit, bool $valid): void
    {
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $context = new ContextStore();

        if ($valid) {
            $policy = new RequestIdPolicy($context, $context, $generator, false, self::HEADER, false, $limit);
            self::assertTrue($policy->current() === null, 'disabled-current-not-null');

            return;
        }

        try {
            new RequestIdPolicy($context, $context, $generator, false, self::HEADER, false, $limit);
            self::fail('invalid-length-limit-accepted');
        } catch (\InvalidArgumentException $actual) {
            self::assertTrue($actual->getMessage() === 'http-request-id-max-length-invalid', 'length-reason-mismatch');
        }
    }

    /** @return iterable<string, array{int, bool}> */
    public static function lengthLimits(): iterable
    {
        yield '25' => [25, false];
        yield '26' => [26, true];
        yield '128' => [128, true];
        yield '129' => [129, false];
    }

    #[DataProvider('invalidContextValues')]
    public function testCurrentRejectsMissingOrInvalidStateWithoutRegeneration(bool $present, mixed $value): void
    {
        $context = self::baseContext();
        if ($present) {
            $context->set(ContextKeys::REQUEST_ID, $value);
        }
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');
        $policy = new RequestIdPolicy($context, $context, $generator, true, self::HEADER, true, 26);

        try {
            $policy->current();
            self::fail('invalid-current-state-accepted');
        } catch (\RuntimeException $actual) {
            self::assertTrue(
                $actual->getMessage() === 'http-request-id-context-invalid',
                'current-context-reason-mismatch',
            );
        }
        self::assertTrue($context->has(ContextKeys::REQUEST_ID) === $present, 'current-context-presence-changed');
        if ($present) {
            self::assertTrue($context->get(ContextKeys::REQUEST_ID) === $value, 'current-context-value-changed');
        }
        self::assertBaseContext($context);
    }

    /** @return iterable<string, array{bool, mixed}> */
    public static function invalidContextValues(): iterable
    {
        yield 'missing' => [false, null];
        yield 'null' => [true, null];
        yield 'integer' => [true, 42];
        yield 'boolean' => [true, true];
        yield 'array' => [true, ['valid']];
        yield 'empty' => [true, ''];
        yield 'whitespace' => [true, 'invalid value'];
        yield 'oversized' => [true, \str_repeat('a', 27)];
        yield 'non-ascii' => [true, 'é'];
        yield 'comma' => [true, 'a,b'];
    }

    private function requestWithoutHeaderAccess(): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        foreach (['getHeader', 'getHeaderLine', 'getHeaders', 'hasHeader'] as $method) {
            $request->expects(self::never())->method($method);
        }

        return $request;
    }

    private static function baseContext(): ContextStore
    {
        $context = new ContextStore();
        $context->set(ContextKeys::CORRELATION_ID, self::CORRELATION);
        $context->set(ContextKeys::UOW_ID, self::UOW);
        $context->set(ContextKeys::UOW_TYPE, 'http');

        return $context;
    }

    private static function assertBaseContext(ContextStore $context): void
    {
        self::assertTrue(
            $context->get(ContextKeys::CORRELATION_ID) === self::CORRELATION,
            'correlation-identity-changed',
        );
        self::assertTrue($context->get(ContextKeys::UOW_ID) === self::UOW, 'uow-identity-changed');
        self::assertTrue($context->get(ContextKeys::UOW_TYPE) === 'http', 'uow-type-changed');
    }

    private static function assertIdentity(
        RequestIdPolicy $policy,
        ContextStore $context,
        ?string $selected,
        string $expected,
    ): void {
        self::assertTrue($selected === $expected, 'selected-request-id-mismatch');
        self::assertTrue($context->has(ContextKeys::REQUEST_ID), 'request-id-context-missing');
        self::assertTrue($context->get(ContextKeys::REQUEST_ID) === $selected, 'stored-request-id-mismatch');
        self::assertTrue($policy->current() === $selected, 'current-request-id-mismatch');
        self::assertBaseContext($context);
    }
}
