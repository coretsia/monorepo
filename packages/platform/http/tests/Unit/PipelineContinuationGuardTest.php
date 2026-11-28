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

use Coretsia\Platform\Http\Middleware\Pipeline;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class PipelineContinuationGuardTest extends TestCase
{
    private const string ATTRIBUTE = 'test.private.handoff';
    private const string OTHER_ATTRIBUTE = 'test.other.handoff';
    private const string REUSED = 'http-pipeline-continuation-reused';
    private const string EXPIRED = 'http-pipeline-continuation-expired';
    private const string ATTRIBUTE_CHANGED = 'http-pipeline-protected-attribute-changed';
    private const string ROUTING_CHANGED = 'http-pipeline-protected-routing-input-changed';

    public function testExactMandatoryConstructorArguments(): void
    {
        $constructor = new \ReflectionClass(Pipeline::class)->getConstructor();
        self::assertInstanceOf(\ReflectionMethod::class, $constructor);
        self::assertTrue($constructor->isPublic());
        self::assertSame(5, $constructor->getNumberOfParameters());
        self::assertSame(5, $constructor->getNumberOfRequiredParameters());
        $actual = [];
        foreach ($constructor->getParameters() as $parameter) {
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isPassedByReference());
            self::assertFalse($parameter->isVariadic());
            $actual[$parameter->getName()] = (string) $parameter->getType();
        }
        self::assertSame([
            'middleware' => 'array',
            'terminal' => RequestHandlerInterface::class,
            'guardedContinuation' => 'bool',
            'protectedRequestAttributes' => 'array',
            'protectRoutingInputs' => 'bool',
        ], $actual);
    }

    #[DataProvider('invalidPolicies')]
    public function testRejectsInvalidContinuationPolicies(bool $guarded, array $attributes, bool $routing): void
    {
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');

        try {
            new Pipeline([], $terminal, $guarded, $attributes, $routing);
        } catch (\InvalidArgumentException $exception) {
            self::assertSame(\InvalidArgumentException::class, $exception::class);
            self::assertSame('http-pipeline-continuation-policy-invalid', $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return;
        }

        self::fail('invalid-continuation-policy-accepted');
    }

    /** @return iterable<string, array{bool, array, bool}> */
    public static function invalidPolicies(): iterable
    {
        yield 'map' => [true, ['private-key' => self::ATTRIBUTE], false];
        yield 'sparse' => [true, [1 => self::ATTRIBUTE], false];
        yield 'empty-name' => [true, [self::ATTRIBUTE, ''], false];
        yield 'integer' => [true, [self::ATTRIBUTE, 1], false];
        yield 'null' => [true, [null], false];
        yield 'boolean' => [true, [false], false];
        yield 'array' => [true, [[]], false];
        yield 'object' => [true, [new \stdClass()], false];
        yield 'duplicate' => [true, [self::ATTRIBUTE, self::OTHER_ATTRIBUTE, self::ATTRIBUTE], false];
        yield 'unguarded-attributes' => [false, [self::ATTRIBUTE], false];
        yield 'unguarded-routing' => [false, [], true];
        yield 'unguarded-both' => [false, [self::ATTRIBUTE], true];
        yield 'routing-without-attributes' => [true, [], true];
        yield 'routing-invalid-attributes' => [true, [''], true];
    }

    public function testOrdinaryContinuationAllowsRepeatedDelegationAndRetryWithoutRequestInspection(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('getAttributes');
        $request->expects(self::never())->method('getMethod');
        $request->expects(self::never())->method('getUri');
        $response = $this->createStub(ResponseInterface::class);
        $failure = new \RuntimeException('synthetic-first-attempt-failure');
        $calls = 0;
        $terminal = self::handler(static function (ServerRequestInterface $incoming) use (
            $request,
            $response,
            $failure,
            &$calls,
        ): ResponseInterface {
            self::assertSame($request, $incoming);
            if (++$calls === 1) {
                throw $failure;
            }

            return $response;
        });
        $middleware = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            $failure,
            $response,
        ): ResponseInterface {
            self::assertSame($failure, self::failure(static fn (): ResponseInterface => $next->handle($incoming)));
            self::assertSame($response, $next->handle($incoming));

            return $next->handle($incoming);
        });

        self::assertSame($response, new Pipeline([$middleware], $terminal, false, [], false)->handle($request));
        self::assertSame(3, $calls);
    }

    #[DataProvider('guardedFirstEntries')]
    public function testGuardedContinuationIsConsumedBeforeValidationAndDownstreamExecution(
        string $firstEntry,
        array $attributes,
        bool $routing,
    ): void {
        $state = (object) ['value' => 'private-state'];
        $request = $this->request([self::ATTRIBUTE => $state]);
        $invalid = match ($firstEntry) {
            'attribute' => $this->request([self::ATTRIBUTE => clone $state]),
            'routing' => $this->request([self::ATTRIBUTE => $state], method: 'POST'),
            default => $request,
        };
        $response = $this->createStub(ResponseInterface::class);
        $failure = new \RuntimeException('synthetic-downstream-failure');
        $entries = 0;
        $terminalCalls = 0;
        $terminal = self::handler(static function (ServerRequestInterface $incoming) use (
            &$terminalCalls,
            $firstEntry,
            $failure,
            $response,
            $request,
        ): ResponseInterface {
            ++$terminalCalls;
            self::assertSame($request, $incoming);
            if ($firstEntry === 'throwable') {
                throw $failure;
            }

            return $response;
        });
        $downstream = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$entries,
        ): ResponseInterface {
            ++$entries;

            return $next->handle($incoming);
        });
        $first = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            $firstEntry,
            $invalid,
            $response,
            $failure,
        ): ResponseInterface {
            $invoke = static fn (): ResponseInterface => $next->handle($invalid);
            if ($firstEntry === 'success') {
                self::assertSame($response, $invoke());
            } elseif ($firstEntry === 'throwable') {
                self::assertSame($failure, self::failure($invoke));
            } else {
                self::assertGuardFailure(
                    $invoke,
                    $firstEntry === 'attribute' ? self::ATTRIBUTE_CHANGED : self::ROUTING_CHANGED,
                );
            }
            self::assertGuardFailure(static fn (): ResponseInterface => $next->handle($incoming), self::REUSED);

            return $response;
        });
        $pipeline = new Pipeline([$first, $downstream], $terminal, true, $attributes, $routing);
        self::assertSame($response, $pipeline->handle($request));
        $expected = \in_array($firstEntry, ['success', 'throwable'], true) ? 1 : 0;
        self::assertSame($expected, $entries);
        self::assertSame($expected, $terminalCalls);
    }

    /** @return iterable<string, array{string, array, bool}> */
    public static function guardedFirstEntries(): iterable
    {
        yield 'single-entry-only-success' => ['success', [], false];
        yield 'single-entry-only-throwable' => ['throwable', [], false];
        yield 'attributes-only-success' => ['success', [self::ATTRIBUTE], false];
        yield 'attributes-only-throwable' => ['throwable', [self::ATTRIBUTE], false];
        yield 'attributes-only-validation-failure' => ['attribute', [self::ATTRIBUTE], false];
        yield 'routing-protected-success' => ['success', [self::ATTRIBUTE], true];
        yield 'routing-protected-throwable' => ['throwable', [self::ATTRIBUTE], true];
        yield 'attribute-validation-failure' => ['attribute', [self::ATTRIBUTE], true];
        yield 'routing-validation-failure' => ['routing', [self::ATTRIBUTE], true];
    }

    #[DataProvider('expirationPaths')]
    public function testExpirationClearsCapturedStateAndPreservesPrimaryThrowable(string $path): void
    {
        $state = (object) ['secret' => 'private-value'];
        $request = $this->request([self::ATTRIBUTE => $state]);
        $response = $this->createStub(ResponseInterface::class);
        $primary = new \RuntimeException('primary-failure');
        $terminalCalls = 0;
        $terminal = self::handler(static function (ServerRequestInterface $incoming) use (
            &$terminalCalls,
            $path,
            $primary,
            $response,
        ): ResponseInterface {
            ++$terminalCalls;
            if ($path === 'downstream-throwable') {
                throw $primary;
            }

            return $response;
        });
        $escaped = null;
        $middleware = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$escaped,
            $state,
            $terminal,
            $path,
            $primary,
            $response,
        ): ResponseInterface {
            $escaped = $next;
            self::assertSame($terminal, new \ReflectionProperty($next, 'next')->getValue($next));
            self::assertSame(
                [self::ATTRIBUTE => $state],
                new \ReflectionProperty($next, 'protectedValues')->getValue($next),
            );
            self::assertSame(
                ['GET', '/private/%41', 'private.example'],
                new \ReflectionProperty($next, 'routingInputs')->getValue($next),
            );
            if ($path === 'short-circuit') {
                return $response;
            }
            if ($path === 'middleware-throwable') {
                throw $primary;
            }
            $result = $next->handle($incoming);
            if ($path === 'after-delegation-throwable') {
                throw $primary;
            }

            return $result;
        });
        $pipeline = new Pipeline([$middleware], $terminal, true, [self::ATTRIBUTE], true);
        if (\str_ends_with($path, 'throwable')) {
            self::assertSame($primary, self::failure(static fn (): ResponseInterface => $pipeline->handle($request)));
        } else {
            self::assertSame($response, $pipeline->handle($request));
        }
        self::assertInstanceOf(RequestHandlerInterface::class, $escaped);
        self::assertNull(new \ReflectionProperty($escaped, 'next')->getValue($escaped));
        self::assertSame([], new \ReflectionProperty($escaped, 'protectedValues')->getValue($escaped));
        self::assertNull(new \ReflectionProperty($escaped, 'routingInputs')->getValue($escaped));
        $expected = \in_array($path, ['normal', 'downstream-throwable', 'after-delegation-throwable'], true) ? 1 : 0;
        self::assertSame($expected, $terminalCalls);
        self::assertGuardFailure(static fn (): ResponseInterface => $escaped->handle($request), self::EXPIRED);
        self::assertGuardFailure(static fn (): ResponseInterface => $escaped->handle($request), self::EXPIRED);
        self::assertSame($expected, $terminalCalls);
    }

    /** @return iterable<string, array{string}> */
    public static function expirationPaths(): iterable
    {
        yield 'normal-return' => ['normal'];
        yield 'short-circuit' => ['short-circuit'];
        yield 'middleware-throwable' => ['middleware-throwable'];
        yield 'downstream-throwable' => ['downstream-throwable'];
        yield 'throwable-after-delegation' => ['after-delegation-throwable'];
    }

    public function testEscapedContinuationDoesNotKeepInvocationLocalDownstreamHandlerAlive(): void
    {
        $request = $this->request([self::ATTRIBUTE => new \stdClass()]);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = self::handler(static fn (): ResponseInterface => $response);
        $reference = \WeakReference::create($terminal);
        $escaped = null;
        $middleware = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$escaped,
        ): ResponseInterface {
            $escaped = $next;

            return $next->handle($incoming);
        });
        $pipeline = new Pipeline([$middleware], $terminal, true, [self::ATTRIBUTE], true);
        self::assertSame($response, $pipeline->handle($request));
        unset($pipeline, $terminal);
        self::assertInstanceOf(RequestHandlerInterface::class, $escaped);
        self::assertNull($reference->get());
        self::assertGuardFailure(static fn (): ResponseInterface => $escaped->handle($request), self::EXPIRED);
    }

    #[DataProvider('attributeViolations')]
    public function testExistingProtectedAttributesRequirePresenceAndStrictIdentity(array $before, array $after): void
    {
        $original = $this->request($before);
        $changed = $this->request($after);
        $first = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($changed));
        $downstream = $this->createMock(MiddlewareInterface::class);
        $downstream->expects(self::never())->method('process');
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline(
            [$first, $downstream],
            $terminal,
            true,
            [self::ATTRIBUTE, self::OTHER_ATTRIBUTE],
            false,
        );

        self::assertGuardFailure(
            static fn (): ResponseInterface => $pipeline->handle($original),
            self::ATTRIBUTE_CHANGED,
        );
    }

    /** @return iterable<string, array{array, array}> */
    public static function attributeViolations(): iterable
    {
        $state = (object) ['value' => 'private-value'];
        yield 'removed-object' => [[self::ATTRIBUTE => $state], []];
        yield 'removed-null' => [[self::ATTRIBUTE => null], []];
        yield 'null-replaced' => [[self::ATTRIBUTE => null], [self::ATTRIBUTE => $state]];
        yield 'same-class-different-value' => [
            [self::ATTRIBUTE => $state],
            [self::ATTRIBUTE => (object) ['value' => 'other']],
        ];
        yield 'equivalent-object' => [[self::ATTRIBUTE => $state], [self::ATTRIBUTE => clone $state]];
        yield 'strict-scalar-type' => [[self::ATTRIBUTE => 1], [self::ATTRIBUTE => '1']];
        yield 'strict-array-value' => [[self::ATTRIBUTE => ['private' => 1]], [self::ATTRIBUTE => ['private' => '1']]];
        yield 'second-protected-key' => [
            [self::ATTRIBUTE => $state, self::OTHER_ATTRIBUTE => null],
            [self::ATTRIBUTE => $state],
        ];
    }

    #[DataProvider('introducedValues')]
    public function testAbsentAttributeMayBeIntroducedAndIsProtectedAtTheNextBoundary(mixed $value): void
    {
        $original = $this->request([]);
        $introduced = $this->request([self::ATTRIBUTE => $value]);
        $adapted = $this->request([self::ATTRIBUTE => $value, 'unrelated' => 'new'], parsedBody: ['parsed' => true]);
        $original->method('withAttribute')->willReturn($introduced);
        $introduced->method('withParsedBody')->willReturn($adapted);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($adapted))->willReturn($response);
        $introduce = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            $original,
            $value,
        ): ResponseInterface {
            self::assertSame($original, $incoming);

            return $next->handle($incoming->withAttribute(self::ATTRIBUTE, $value));
        });
        $adapt = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            $introduced,
            $value,
        ): ResponseInterface {
            self::assertSame($introduced, $incoming);
            self::assertSame([self::ATTRIBUTE => $value], $incoming->getAttributes());
            $adapted = $incoming->withParsedBody(['parsed' => true]);
            self::assertSame(['parsed' => true], $adapted->getParsedBody());
            self::assertSame([self::ATTRIBUTE => $value, 'unrelated' => 'new'], $adapted->getAttributes());

            return $next->handle($adapted);
        });
        $pipeline = new Pipeline([$introduce, $adapt], $terminal, true, [self::ATTRIBUTE], false);
        self::assertSame($response, $pipeline->handle($original));

        $remove = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($original));
        $never = $this->createMock(RequestHandlerInterface::class);
        $never->expects(self::never())->method('handle');
        $pipeline = new Pipeline([$introduce, $remove], $never, true, [self::ATTRIBUTE], false);
        self::assertGuardFailure(
            static fn (): ResponseInterface => $pipeline->handle($original),
            self::ATTRIBUTE_CHANGED,
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function introducedValues(): iterable
    {
        yield 'object' => [(object) ['value' => 'private-value']];
        yield 'explicit-null' => [null];
    }

    #[DataProvider('nonRoutingPolicies')]
    public function testRoutingChangesRemainAdmittedWhenRoutingProtectionIsDisabled(bool $guarded): void
    {
        $state = new \stdClass();
        $original = $this->request([self::ATTRIBUTE => $state]);
        $changed = $this->request([self::ATTRIBUTE => $state], 'POST', '/changed', 'other.example');
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($changed))->willReturn($response);
        $middleware = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($changed));
        $pipeline = new Pipeline([$middleware], $terminal, $guarded, $guarded ? [self::ATTRIBUTE] : [], false);
        self::assertSame($response, $pipeline->handle($original));

        if ($guarded) {
            $removed = $this->request([], 'POST', '/changed', 'other.example');
            $remove = self::middleware(static fn (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ): ResponseInterface => $next->handle($removed));
            $never = $this->createMock(RequestHandlerInterface::class);
            $never->expects(self::never())->method('handle');
            $pipeline = new Pipeline([$remove], $never, true, [self::ATTRIBUTE], false);
            self::assertGuardFailure(
                static fn (): ResponseInterface => $pipeline->handle($original),
                self::ATTRIBUTE_CHANGED,
            );
        }
    }

    /** @return iterable<string, array{bool}> */
    public static function nonRoutingPolicies(): iterable
    {
        yield 'ordinary' => [false];
        yield 'guarded-attributes-only' => [true];
    }

    #[DataProvider('attributeOnlyPolicies')]
    public function testGuardedPoliciesDoNotInspectUnselectedRequestMetadata(array $attributes): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $request->expects(self::never())->method('getMethod');
        $request->expects(self::never())->method('getUri');
        $request->expects($attributes === [] ? self::never() : self::exactly(2))
            ->method('getAttributes')->willReturn([self::ATTRIBUTE => null]);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);
        $middleware = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($incoming));
        self::assertSame($response, new Pipeline([$middleware], $terminal, true, $attributes, false)->handle($request));
    }

    /** @return iterable<string, array{array}> */
    public static function attributeOnlyPolicies(): iterable
    {
        yield 'single-entry-only' => [[]];
        yield 'attributes-only' => [[self::ATTRIBUTE]];
    }

    public function testRoutingAdaptationsBeforeHandoffAndUnrelatedAdaptationsAfterHandoffAreAdmitted(): void
    {
        $state = new \stdClass();
        $original = $this->request([]);
        $preMatch = $this->request([], 'PATCH', '/new/%2f', 'new.example');
        $matched = $this->request([self::ATTRIBUTE => $state], 'PUT', '/matched/%41', 'matched.example');
        $adapted = $this->request(
            [self::ATTRIBUTE => $state, 'unrelated' => 'updated'],
            'PUT',
            '/matched/%41',
            'matched.example',
            ['parsed' => 'updated'],
            'http',
            8080,
            'query=changed',
            'fragment',
        );
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($adapted))->willReturn($response);
        $pipeline = new Pipeline([
            self::middleware(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use (
                $original,
                $preMatch,
            ): ResponseInterface {
                self::assertSame($original, $incoming);

                return $next->handle($preMatch);
            }),
            self::middleware(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use (
                $preMatch,
                $matched,
            ): ResponseInterface {
                self::assertSame($preMatch, $incoming);

                return $next->handle($matched);
            }),
            self::middleware(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use (
                $matched,
                $adapted,
            ): ResponseInterface {
                self::assertSame($matched, $incoming);
                self::assertNotSame($incoming->getUri(), $adapted->getUri());
                self::assertSame('http', $adapted->getUri()->getScheme());
                self::assertSame(8080, $adapted->getUri()->getPort());
                self::assertSame('query=changed', $adapted->getUri()->getQuery());
                self::assertSame('fragment', $adapted->getUri()->getFragment());
                self::assertSame(['parsed' => 'updated'], $adapted->getParsedBody());

                return $next->handle($adapted);
            }),
        ], $terminal, true, [self::ATTRIBUTE], true);
        self::assertSame($response, $pipeline->handle($original));
    }

    #[DataProvider('routingMutations')]
    public function testRoutingMutationAfterIntroductionFailsBeforeRestoringMiddleware(
        string $method,
        string $path,
        string $host,
        string $originalHost,
        bool $nullHandoff,
    ): void {
        $state = $nullHandoff ? null : new \stdClass();
        $attributes = [self::OTHER_ATTRIBUTE => $state];
        $original = $this->request([], host: $originalHost);
        $matched = $this->request($attributes, host: $originalHost);
        $mutated = $this->request($attributes, $method, $path, $host);
        $entries = [];
        $introduce = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$entries,
            $matched,
        ): ResponseInterface {
            $entries[] = 'introduce';

            return $next->handle($matched);
        });
        $mutate = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$entries,
            $mutated,
        ): ResponseInterface {
            $entries[] = 'mutate';

            return $next->handle($mutated);
        });
        $restore = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$entries,
            $matched,
        ): ResponseInterface {
            $entries[] = 'restore';

            return $next->handle($matched);
        });
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $pipeline = new Pipeline(
            [$introduce, $mutate, $restore],
            $terminal,
            true,
            [self::ATTRIBUTE, self::OTHER_ATTRIBUTE],
            true,
        );
        self::assertGuardFailure(
            static fn (): ResponseInterface => $pipeline->handle($original),
            self::ROUTING_CHANGED,
        );
        self::assertSame(['introduce', 'mutate'], $entries);
    }

    /** @return iterable<string, array{string, string, string, string, bool}> */
    public static function routingMutations(): iterable
    {
        yield 'method' => ['POST', '/private/%41', 'private.example', 'private.example', false];
        yield 'method-case' => ['get', '/private/%41', 'private.example', 'private.example', false];
        yield 'method-whitespace' => ['GET ', '/private/%41', 'private.example', 'private.example', false];
        yield 'encoded-path' => ['GET', '/private/A', 'private.example', 'private.example', false];
        yield 'path-case' => ['GET', '/Private/%41', 'private.example', 'private.example', false];
        yield 'host' => ['GET', '/private/%41', 'other.example', 'private.example', false];
        yield 'host-case' => ['GET', '/private/%41', 'PRIVATE.EXAMPLE', 'private.example', false];
        yield 'empty-host' => ['GET', '/private/%41', '', 'private.example', false];
        yield 'introduced-host' => ['GET', '/private/%41', 'private.example', '', false];
        yield 'null-handoff-is-present' => ['POST', '/private/%41', 'private.example', 'private.example', true];
    }

    #[DataProvider('finalRoutingMutations')]
    public function testFinalDelegationRequiresExactRoutingInputs(
        array $before,
        array $after,
    ): void {
        // A numeric-string name is a valid non-empty protected attribute name.
        $attributes = ['0' => new \stdClass()];
        $original = $this->request($attributes, ...$before);
        $changed = $this->request($attributes, ...$after);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::never())->method('handle');
        $middleware = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($changed));
        $pipeline = new Pipeline([$middleware], $terminal, true, ['0'], true);

        self::assertGuardFailure(
            static fn (): ResponseInterface => $pipeline->handle($original),
            self::ROUTING_CHANGED,
        );
    }

    /** @return iterable<string, array{array{string, string, string}, array{string, string, string}}> */
    public static function finalRoutingMutations(): iterable
    {
        yield 'numeric-method' => [
            ['1', '/private/%41', 'private.example'],
            ['01', '/private/%41', 'private.example'],
        ];
        yield 'encoded-path' => [
            ['GET', '/private/%41', 'private.example'],
            ['GET', '/private/A', 'private.example'],
        ];
        yield 'numeric-relative-path' => [
            ['GET', '1', ''],
            ['GET', '01', ''],
        ];
        yield 'numeric-host' => [
            ['GET', '/private/%41', '1'],
            ['GET', '/private/%41', '01'],
        ];
    }

    public function testRoutingInputsAreNotReadBeforeProtectedAttributeIntroduction(): void
    {
        $original = $this->createMock(ServerRequestInterface::class);
        $original->method('getAttributes')->willReturn(['unrelated' => 'value']);
        $original->expects(self::never())->method('getMethod');
        $original->expects(self::never())->method('getUri');
        $introduced = $this->request([self::ATTRIBUTE => new \stdClass()]);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')
            ->with(self::identicalTo($introduced))->willReturn($response);
        $middleware = self::middleware(static fn (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ): ResponseInterface => $next->handle($introduced));
        $pipeline = new Pipeline([$middleware], $terminal, true, [self::ATTRIBUTE], true);

        self::assertSame($response, $pipeline->handle($original));
    }

    #[DataProvider('executionPolicies')]
    public function testEmptySequenceAndShortCircuitPreserveResponseIdentity(
        bool $guarded,
        array $attributes,
        bool $routing,
    ): void {
        $request = $this->request([self::ATTRIBUTE => new \stdClass()]);
        $response = $this->createStub(ResponseInterface::class);
        $terminal = $this->createMock(RequestHandlerInterface::class);
        $terminal->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);
        self::assertSame($response, new Pipeline([], $terminal, $guarded, $attributes, $routing)->handle($request));

        $never = $this->createMock(RequestHandlerInterface::class);
        $never->expects(self::never())->method('handle');
        $downstream = $this->createMock(MiddlewareInterface::class);
        $downstream->expects(self::never())->method('process');
        $short = self::middleware(static fn (): ResponseInterface => $response);
        $pipeline = new Pipeline([$short, $downstream], $never, $guarded, $attributes, $routing);
        self::assertSame($response, $pipeline->handle($request));
    }

    /** @return iterable<string, array{bool, array, bool}> */
    public static function executionPolicies(): iterable
    {
        yield 'ordinary' => [false, [], false];
        yield 'single-entry-only' => [true, [], false];
        yield 'protected-attributes' => [true, [self::ATTRIBUTE], false];
        yield 'protected-routing' => [true, [self::ATTRIBUTE], true];
    }

    #[DataProvider('executionPolicies')]
    public function testDecorationUnwindsInReverseOrderAndThrowablePropagatesUnchanged(
        bool $guarded,
        array $attributes,
        bool $routing,
    ): void {
        $request = $this->request([self::ATTRIBUTE => new \stdClass()]);
        $initial = $this->createMock(ResponseInterface::class);
        $innerResponse = $this->createMock(ResponseInterface::class);
        $final = $this->createStub(ResponseInterface::class);
        $initial->expects(self::once())->method('withHeader')->with('X-Inner', 'yes')->willReturn($innerResponse);
        $innerResponse->expects(self::once())->method('withHeader')->with('X-Outer', 'yes')->willReturn($final);
        $events = [];
        $failure = new \RuntimeException('original-application-failure');
        $failing = false;
        $terminal = self::handler(static function (ServerRequestInterface $incoming) use (
            &$events,
            &$failing,
            $failure,
            $initial,
            $request,
        ): ResponseInterface {
            self::assertSame($request, $incoming);
            $events[] = 'terminal';
            if ($failing) {
                throw $failure;
            }

            return $initial;
        });
        $decorate = static function (string $name) use (&$events): MiddlewareInterface {
            return self::middleware(static function (
                ServerRequestInterface $incoming,
                RequestHandlerInterface $next,
            ) use (
                $name,
                &$events,
            ): ResponseInterface {
                $events[] = $name . ':enter';
                $response = $next->handle($incoming);
                $events[] = $name . ':leave';
                $decorated = $response->withHeader('X-' . $name, 'yes');
                self::assertInstanceOf(ResponseInterface::class, $decorated);

                return $decorated;
            });
        };
        $pipeline = new Pipeline([$decorate('Outer'), $decorate('Inner')], $terminal, $guarded, $attributes, $routing);
        self::assertSame($final, $pipeline->handle($request));
        self::assertSame(['Outer:enter', 'Inner:enter', 'terminal', 'Inner:leave', 'Outer:leave'], $events);
        $events = [];
        $failing = true;
        self::assertSame($failure, self::failure(static fn (): ResponseInterface => $pipeline->handle($request)));
        self::assertSame(['Outer:enter', 'Inner:enter', 'terminal'], $events);
    }

    public function testReusablePipelineRetainsOnlyImmutableCompositionAndCreatesIndependentContinuations(): void
    {
        $response = $this->createStub(ResponseInterface::class);
        $failure = new \RuntimeException('first-request-failure');
        $first = $this->request([self::ATTRIBUTE => new \stdClass()]);
        $second = $this->request([self::ATTRIBUTE => new \stdClass()], 'POST', '/second', 'second.example');
        $continuations = [];
        $requests = [];
        $terminal = self::handler(static function (ServerRequestInterface $incoming) use (
            &$requests,
            $first,
            $failure,
            $response,
        ): ResponseInterface {
            $requests[] = $incoming;
            if ($incoming === $first) {
                throw $failure;
            }

            return $response;
        });
        $middleware = self::middleware(static function (
            ServerRequestInterface $incoming,
            RequestHandlerInterface $next,
        ) use (
            &$continuations,
        ): ResponseInterface {
            $continuations[] = $next;

            return $next->handle($incoming);
        });
        $pipeline = new Pipeline([$middleware], $terminal, true, [self::ATTRIBUTE], true);
        $reflection = new \ReflectionObject($pipeline);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        self::assertSame([], $reflection->getStaticProperties());
        $expected = [
            'middleware' => [$middleware],
            'protectedRequestAttributes' => [self::ATTRIBUTE],
            'terminal' => $terminal,
            'guardedContinuation' => true,
            'protectRoutingInputs' => true,
        ];
        \ksort($expected);
        self::assertSame($expected, self::properties($pipeline));
        self::assertSame($failure, self::failure(static fn (): ResponseInterface => $pipeline->handle($first)));
        self::assertSame($expected, self::properties($pipeline));
        self::assertSame($response, $pipeline->handle($second));
        self::assertSame($expected, self::properties($pipeline));
        self::assertSame($response, $pipeline->handle($second));
        self::assertSame($expected, self::properties($pipeline));
        self::assertSame([$first, $second, $second], $requests);
        self::assertCount(3, $continuations);
        self::assertNotSame($continuations[0], $continuations[1]);
        self::assertNotSame($continuations[0], $continuations[2]);
        self::assertNotSame($continuations[1], $continuations[2]);
        foreach ($continuations as $next) {
            self::assertGuardFailure(static fn (): ResponseInterface => $next->handle($second), self::EXPIRED);
        }
        self::assertSame([$first, $second, $second], $requests);
    }

    /** @return array<string, mixed> */
    private static function properties(Pipeline $pipeline): array
    {
        $properties = [];
        foreach (new \ReflectionObject($pipeline)->getProperties() as $property) {
            self::assertTrue($property->isReadOnly());
            $properties[$property->getName()] = $property->getValue($pipeline);
        }

        \ksort($properties);

        return $properties;
    }

    private static function failure(\Closure $operation): \Throwable
    {
        try {
            $operation();
        } catch (\Throwable $failure) {
            return $failure;
        }

        self::fail('expected-pipeline-failure');
    }

    private static function assertGuardFailure(\Closure $operation, string $reason): void
    {
        $failure = self::failure($operation);
        self::assertSame(\LogicException::class, $failure::class);
        self::assertSame($reason, $failure->getMessage());
        self::assertSame(0, $failure->getCode());
        self::assertNull($failure->getPrevious());
        // Do not inspect native PHP trace arguments: their availability depends on PHP configuration.
    }

    /** @return ServerRequestInterface&\PHPUnit\Framework\MockObject\Stub */
    private function request(
        array $attributes,
        string $method = 'GET',
        string $path = '/private/%41',
        string $host = 'private.example',
        ?array $parsedBody = null,
        string $scheme = 'https',
        ?int $port = null,
        string $query = '',
        string $fragment = '',
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getScheme')->willReturn($scheme);
        $uri->method('getPort')->willReturn($port);
        $uri->method('getQuery')->willReturn($query);
        $uri->method('getFragment')->willReturn($fragment);
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttributes')->willReturn($attributes);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getParsedBody')->willReturn($parsedBody);

        return $request;
    }

    private static function middleware(\Closure $process): MiddlewareInterface
    {
        return new readonly class($process) implements MiddlewareInterface {
            public function __construct(private \Closure $process)
            {
            }

            public function process(
                ServerRequestInterface $request,
                RequestHandlerInterface $handler,
            ): ResponseInterface {
                return ($this->process)($request, $handler);
            }
        };
    }

    private static function handler(\Closure $handle): RequestHandlerInterface
    {
        return new readonly class($handle) implements RequestHandlerInterface {
            public function __construct(private \Closure $handle)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->handle)($request);
            }
        };
    }
}
