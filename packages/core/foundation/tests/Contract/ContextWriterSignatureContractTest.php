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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Context\ContextKeys;
use Coretsia\Contracts\Context\ContextWriterInterface;
use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Context\ContextBag;
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Context\ContextStorePolicy;
use Coretsia\Foundation\Context\Exception\ContextInvalidKeyException;
use Coretsia\Foundation\Context\Exception\ContextWriteForbiddenException;
use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use PHPUnit\Framework\TestCase;

final class ContextWriterSignatureContractTest extends TestCase
{
    public function testContextStoreImplementsCanonicalContextPortsAndResetInterface(): void
    {
        $store = new ContextStore(new ContextStorePolicy());

        self::assertInstanceOf(ContextWriterInterface::class, $store);
        self::assertInstanceOf(ContextAccessorInterface::class, $store);
        self::assertInstanceOf(ResetInterface::class, $store);
    }

    public function testContextStoreSetSignatureIsStable(): void
    {
        self::assertMutationSignature('set', ['key' => 'string', 'value' => 'mixed']);
    }

    public function testContextStoreRemoveSignatureIsStable(): void
    {
        self::assertMutationSignature('remove', ['key' => 'string']);
    }

    public function testContextBagExposesReadOnlySnapshotApi(): void
    {
        $reflection = new \ReflectionClass(ContextBag::class);
        self::assertSame([ContextAccessorInterface::class], $reflection->getInterfaceNames());
        self::assertSame([], $reflection->getProperties(\ReflectionProperty::IS_PUBLIC));

        $methods = \array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        \sort($methods, \SORT_STRING);

        self::assertSame(['__construct', 'all', 'get', 'has'], $methods);

        foreach (['all', 'get', 'has'] as $method) {
            self::assertFalse($reflection->getMethod($method)->returnsReference());
        }

        $store = new ContextStore(new ContextStorePolicy());
        $store->set(ContextKeys::PATH_TEMPLATE, '/users/{id}');
        $snapshot = $store->snapshot();

        self::assertInstanceOf(ContextAccessorInterface::class, $snapshot);
        self::assertNotInstanceOf(ContextWriterInterface::class, $snapshot);
        self::assertNotInstanceOf(ResetInterface::class, $snapshot);

        $store->set(ContextKeys::PATH_TEMPLATE, '/teams/{id}');
        self::assertSame('/teams/{id}', $store->get(ContextKeys::PATH_TEMPLATE));
        $store->remove(ContextKeys::PATH_TEMPLATE);
        self::assertFalse($store->has(ContextKeys::PATH_TEMPLATE));
        self::assertNull($store->get(ContextKeys::PATH_TEMPLATE));
        self::assertSame([ContextKeys::PATH_TEMPLATE => '/users/{id}'], $snapshot->all());
    }

    public function testContextWriterPreservesCanonicalMutationSemantics(): void
    {
        $store = new ContextStore(new ContextStorePolicy());
        self::assertInstanceOf(ContextWriterInterface::class, $store);

        $store->set(ContextKeys::PATH_TEMPLATE, '/users/{id}');
        $store->set(ContextKeys::REQUEST_ID, '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $store->set(ContextKeys::PATH_TEMPLATE, null);
        self::assertTrue($store->has(ContextKeys::PATH_TEMPLATE));
        self::assertNull($store->get(ContextKeys::PATH_TEMPLATE));

        $store->remove(ContextKeys::PATH_TEMPLATE);
        $store->remove(ContextKeys::PATH_TEMPLATE);
        self::assertFalse($store->has(ContextKeys::PATH_TEMPLATE));
        self::assertSame([ContextKeys::REQUEST_ID => '01ARZ3NDEKTSV4RRFFQ69G5FAV'], $store->all());
    }

    public function testSetAndRemoveRejectInvalidKeysThroughContextStorePolicy(): void
    {
        $store = new ContextStore(new ContextStorePolicy());
        $store->set(ContextKeys::PATH_TEMPLATE, '/users/{id}');
        $expected = $store->all();

        foreach (
            [
                '' => 'context-key-empty',
                '@reserved' => 'context-key-reserved',
                'unknown_key' => 'context-key-unknown',
            ] as $key => $reason
        ) {
            foreach (['value', new \stdClass()] as $value) {
                try {
                    $store->set($key, $value);
                    self::fail('Expected ContextStore key rejection during set().');
                } catch (ContextInvalidKeyException $exception) {
                    self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
                    self::assertSame($reason, $exception->reason());
                    self::assertPolicyValidation($exception, 'assertCanWrite');
                }
                self::assertSame($expected, $store->all());
            }

            try {
                $store->remove($key);
                self::fail('Expected ContextStore key rejection during remove().');
            } catch (ContextInvalidKeyException $exception) {
                self::assertSame(ContextInvalidKeyException::ERROR_CODE, $exception->errorCode());
                self::assertSame($reason, $exception->reason());
                self::assertPolicyValidation($exception, 'assertKey');
            }
            self::assertSame($expected, $store->all());
        }
    }

    public function testSetRejectsForbiddenValuesThroughContextStorePolicyWithoutChangingContext(): void
    {
        $store = new ContextStore(new ContextStorePolicy());
        $store->set(ContextKeys::PATH_TEMPLATE, '/users/{id}');
        $expected = $store->all();
        $deep = 'leaf';
        for ($depth = 0; $depth < 9; ++$depth) {
            $deep = [$deep];
        }

        foreach (
            [
                [['nested' => 1.5], 'context-write-forbidden-float'],
                [new \stdClass(), 'context-write-forbidden-object'],
                [static fn (): string => 'value', 'context-write-forbidden-closure'],
                [[1 => 'value'], 'context-write-forbidden-map-key'],
                [\str_repeat('a', 4097), 'context-write-forbidden-string-bytes'],
                [\array_fill(0, 257, 'value'), 'context-write-forbidden-max-nodes'],
                [$deep, 'context-write-forbidden-max-depth'],
            ] as [$value, $reason]
        ) {
            try {
                $store->set(ContextKeys::PATH_TEMPLATE, $value);
                self::fail('Expected ContextStore value rejection during set().');
            } catch (ContextWriteForbiddenException $exception) {
                self::assertSame(ContextWriteForbiddenException::ERROR_CODE, $exception->errorCode());
                self::assertSame($reason, $exception->reason());
                self::assertInstanceOf(JsonLikeNormalizationException::class, $exception->getPrevious());
                self::assertPolicyValidation($exception, 'assertCanWrite');
            }
            self::assertSame($expected, $store->all());
        }
    }

    /**
     * @param array<string, string> $expectedParameters
     */
    private static function assertMutationSignature(string $name, array $expectedParameters): void
    {
        $method = new \ReflectionMethod(ContextStore::class, $name);

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertFalse($method->returnsReference());
        self::assertSame(\count($expectedParameters), $method->getNumberOfParameters());
        self::assertSame(\count($expectedParameters), $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();
        self::assertSame(
            \array_keys($expectedParameters),
            \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            ),
        );
        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->isOptional());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->isVariadic());
            self::assertFalse($parameter->isPassedByReference());

            $expectedType = $expectedParameters[$parameter->getName()];
            $type = $parameter->getType();
            self::assertInstanceOf(\ReflectionNamedType::class, $type);
            self::assertTrue($type->isBuiltin());
            self::assertSame($expectedType, $type->getName());
            self::assertSame($expectedType === 'mixed', $type->allowsNull());
        }

        $returnType = $method->getReturnType();
        self::assertInstanceOf(\ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->isBuiltin());
        self::assertSame('void', $returnType->getName());
        self::assertFalse($returnType->allowsNull());
    }

    private static function assertPolicyValidation(\Throwable $exception, string $method): void
    {
        $policyMethods = [];
        foreach ($exception->getTrace() as $frame) {
            if (($frame['class'] ?? null) === ContextStorePolicy::class) {
                $policyMethods[] = $frame['function'];
            }
        }

        self::assertContains($method, $policyMethods);
    }
}
