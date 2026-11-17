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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\IdGeneratorInterface;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
use Coretsia\Platform\Http\Response\HttpResponseFinalizer;
use Coretsia\Platform\Http\SecurityHeaders\SecurityHeadersPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;

final class SecurityHeadersDeterministicOrderContractTest extends TestCase
{
    private const array HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'geolocation=()',
        'Content-Security-Policy' => "default-src 'none'",
    ];

    public function testPolicyOrderAndImmutableResponseMutationChainAreExact(): void
    {
        $policy = self::policy(true);
        self::assertSame(self::HEADERS, $policy->headers());
        $first = $this->createMock(ResponseInterface::class);
        $current = $first;
        foreach (self::HEADERS as $name => $value) {
            $next = $this->createMock(ResponseInterface::class);
            $current->expects(self::once())->method('withHeader')->with($name, $value)->willReturn($next);
            $current->expects(self::never())->method('withAddedHeader');
            $current->expects(self::never())->method('withoutHeader');
            $current = $next;
        }
        $current->expects(self::never())->method('withHeader');

        self::assertSame($current, $this->finalizer($policy)->finalize($first));
    }

    #[DataProvider('mutationFailures')]
    public function testFailedMutationStopsTheChain(int $index, bool $invalidResult): void
    {
        $input = $this->createMock(ResponseInterface::class);
        $current = $input;
        $failure = new \RuntimeException('synthetic-security-header-failure');
        $invalid = $this->createMock(MessageInterface::class);
        $invalid->expects(self::never())->method('withHeader');
        $position = 0;
        foreach (self::HEADERS as $name => $value) {
            $mutation = $current->expects(self::once())->method('withHeader')->with($name, $value);
            if ($position++ === $index) {
                if ($invalidResult) {
                    $mutation->willReturn($invalid);
                } else {
                    $mutation->willThrowException($failure);
                }
                break;
            }
            $next = $this->createMock(ResponseInterface::class);
            $mutation->willReturn($next);
            $current = $next;
        }

        try {
            $this->finalizer(self::policy(true))->finalize($input);
            self::fail('security-header-failure-not-propagated');
        } catch (\RuntimeException $actual) {
            if ($invalidResult) {
                self::assertSame('http-security-header-response-invalid', $actual->getMessage());
            } else {
                self::assertSame($failure, $actual);
            }
        }
    }

    /** @return iterable<string, array{int, bool}> */
    public static function mutationFailures(): iterable
    {
        for ($index = 0; $index < 5; ++$index) {
            yield 'invalid-' . $index => [$index, true];
            yield 'throw-' . $index => [$index, false];
        }
    }

    public function testReplacementUsesWithHeaderAndRetainsUnconfiguredOptionalHeaders(): void
    {
        $policy = new SecurityHeadersPolicy(true, 'nosniff', 'sameorigin', 'strict-origin', '', '');
        $original = [
            'x-content-type-options' => ['application-value', 'second-value'],
            'x-frame-options' => ['DENY'],
            'referrer-policy' => ['unsafe-url'],
            'permissions-policy' => ['camera=()'],
            'content-security-policy' => ["default-src 'self'"],
            'x-application' => ['retained'],
        ];
        $input = $this->response($original, 3);
        $actual = $this->finalizer($policy)->finalize($input);

        self::assertNotSame($input, $actual);
        self::assertSame($original['x-content-type-options'], $input->getHeader('X-Content-Type-Options'));
        self::assertSame(['nosniff'], $actual->getHeader('x-content-type-options'));
        self::assertSame(['SAMEORIGIN'], $actual->getHeader('X-FRAME-OPTIONS'));
        self::assertSame(['strict-origin'], $actual->getHeader('Referrer-Policy'));
        foreach (['permissions-policy', 'content-security-policy', 'x-application'] as $header) {
            self::assertSame($original[$header], $actual->getHeader($header));
        }

        $input = $this->response($original, 5);
        $actual = $this->finalizer(self::policy(true))->finalize($input);
        foreach (self::HEADERS as $name => $value) {
            self::assertSame([$value], $actual->getHeader(\strtolower($name)));
            self::assertSame($original[\strtolower($name)], $input->getHeader($name));
        }
        self::assertSame($original['x-application'], $actual->getHeader('X-Application'));
    }

    public function testDisabledPolicyHasNoMutationEvenWithConfiguredValues(): void
    {
        $policy = self::policy(false);
        $input = $this->createMock(ResponseInterface::class);
        $input->expects(self::never())->method('withHeader');
        $input->expects(self::never())->method('withAddedHeader');
        $input->expects(self::never())->method('withoutHeader');
        self::assertSame([], $policy->headers());
        self::assertSame($input, $this->finalizer($policy)->finalize($input));
    }

    #[DataProvider('referrerPolicies')]
    public function testSupportedReferrerPoliciesAndOptionalHeaderByteBound(string $directive): void
    {
        $value = \str_repeat('a', 4096);
        $policy = new SecurityHeadersPolicy(true, 'nosniff', 'sameorigin', $directive, $value, $value);
        self::assertSame([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => $directive,
            'Permissions-Policy' => $value,
            'Content-Security-Policy' => $value,
        ], $policy->headers());
    }

    /** @return iterable<string, array{string}> */
    public static function referrerPolicies(): iterable
    {
        foreach (
            [
                'no-referrer',
                'no-referrer-when-downgrade',
                'origin',
                'origin-when-cross-origin',
                'same-origin',
                'strict-origin',
                'strict-origin-when-cross-origin',
                'unsafe-url',
            ] as $directive
        ) {
            yield $directive => [$directive];
        }
    }

    #[DataProvider('invalidPolicies')]
    public function testInvalidValuesFailEvenWhenDisabled(int $index, string $value, string $token): void
    {
        foreach ([true, false] as $enabled) {
            try {
                new SecurityHeadersPolicy(
                    $enabled,
                    $index === 1 ? $value : 'nosniff',
                    $index === 2 ? $value : 'deny',
                    $index === 3 ? $value : 'no-referrer',
                    $index === 4 ? $value : '',
                    $index === 5 ? $value : '',
                );
                self::fail('invalid-security-policy-accepted');
            } catch (\InvalidArgumentException $actual) {
                self::assertSame($token, $actual->getMessage());
            }
        }
    }

    /** @return iterable<string, array{int, string, string}> */
    public static function invalidPolicies(): iterable
    {
        yield 'content-type' => [1, 'sniff', 'http-security-headers-x-content-type-options-invalid'];
        yield 'empty-content-type' => [1, '', 'http-security-headers-x-content-type-options-invalid'];
        yield 'content-type-injection' => [
            1,
            "nosniff\r\nX-Injected: yes",
            'http-security-headers-x-content-type-options-invalid',
        ];
        yield 'frame' => [2, 'DENY', 'http-security-headers-x-frame-options-invalid'];
        yield 'frame-directive' => [2, 'allow-from', 'http-security-headers-x-frame-options-invalid'];
        yield 'referrer' => [3, 'invalid', 'http-security-headers-referrer-policy-invalid'];
        yield 'referrer-injection' => [
            3,
            "no-referrer\r\nX-Injected: yes",
            'http-security-headers-referrer-policy-invalid',
        ];
        foreach ([4 => 'permissions-policy', 5 => 'csp'] as $index => $key) {
            foreach (
                [
                    'crlf' => "a\r\nX-Injected: yes",
                    'nul' => "a\0b",
                    'tab' => "a\tb",
                    'del' => "a\x7f",
                    'non-ascii' => "a\xc3\xa9",
                    'leading-space' => ' a',
                    'trailing-space' => 'a ',
                    'too-long' => \str_repeat('a', 4097),
                ] as $case => $value
            ) {
                yield $key . '-' . $case => [$index, $value, 'http-security-headers-' . $key . '-invalid'];
            }
        }
    }

    private static function policy(bool $enabled): SecurityHeadersPolicy
    {
        return new SecurityHeadersPolicy(
            $enabled,
            'nosniff',
            'deny',
            'no-referrer',
            'geolocation=()',
            "default-src 'none'",
        );
    }

    private function finalizer(SecurityHeadersPolicy $policy): HttpResponseFinalizer
    {
        $correlation = $this->createMock(CorrelationIdProviderInterface::class);
        $correlation->expects(self::never())->method('correlationId');
        $generator = $this->createMock(IdGeneratorInterface::class);
        $generator->expects(self::never())->method('generate');

        return new HttpResponseFinalizer(
            $correlation,
            false,
            'X-Correlation-Id',
            new RequestIdPolicy(new ContextStore(), $generator, false, 'X-Request-Id', false, 128),
            $policy,
        );
    }

    /** @param array<string, list<string>> $headers */
    private function response(array $headers, int $remaining): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getHeader')->willReturnCallback(
            static fn (string $name): array => $headers[\strtolower($name)] ?? [],
        );
        $response->expects(self::never())->method('withAddedHeader');
        $response->expects(self::never())->method('withoutHeader');
        $response->expects($remaining > 0 ? self::once() : self::never())->method('withHeader')
            ->willReturnCallback(function (string $name, mixed $value) use ($headers, $remaining): ResponseInterface {
                self::assertIsString($value);
                $headers[\strtolower($name)] = [$value];

                return $this->response($headers, $remaining - 1);
            });

        return $response;
    }
}
