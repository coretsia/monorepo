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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

final class ErrorDescriptorShapeContractTest extends TestCase
{
    public function testConstructorShapeIsStable(): void
    {
        $reflection = new ReflectionClass(ErrorDescriptor::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(5, $parameters);
        self::assertSame(2, $constructor->getNumberOfRequiredParameters());

        self::assertSame('code', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'string', false);
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertSame('message', $parameters[1]->getName());
        self::assertParameterNamedType($parameters[1], 'string', false);
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        self::assertSame('severity', $parameters[2]->getName());
        self::assertParameterNamedType($parameters[2], ErrorSeverity::class, false);
        self::assertTrue($parameters[2]->isDefaultValueAvailable());
        self::assertSame(ErrorSeverity::Error, $parameters[2]->getDefaultValue());

        self::assertSame('httpStatus', $parameters[3]->getName());
        self::assertParameterNamedType($parameters[3], 'int', true);
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertNull($parameters[3]->getDefaultValue());

        self::assertSame('extensions', $parameters[4]->getName());
        self::assertParameterNamedType($parameters[4], 'array', false);
        self::assertTrue($parameters[4]->isDefaultValueAvailable());
        self::assertSame([], $parameters[4]->getDefaultValue());
    }

    public function testGettersAndArrayShapeAreStable(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Error,
            httpStatus: 500,
            extensions: [
                'z' => 'last',
                'a' => 'first',
            ],
        );

        self::assertSame(1, $descriptor->schemaVersion());
        self::assertSame('core.example', $descriptor->code());
        self::assertSame('Example message.', $descriptor->message());
        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame(500, $descriptor->httpStatus());
        self::assertSame(
            [
                'a' => 'first',
                'z' => 'last',
            ],
            $descriptor->extensions(),
        );

        self::assertSame(
            [
                'code' => 'core.example',
                'extensions' => [
                    'a' => 'first',
                    'z' => 'last',
                ],
                'httpStatus' => 500,
                'message' => 'Example message.',
                'schemaVersion' => 1,
                'severity' => 'error',
            ],
            $descriptor->toArray(),
        );

        self::assertSame(
            [
                'code',
                'extensions',
                'httpStatus',
                'message',
                'schemaVersion',
                'severity',
            ],
            array_keys($descriptor->toArray()),
        );
    }

    public function testSeverityDefaultsToError(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
        );

        self::assertSame(ErrorSeverity::Error, $descriptor->severity());
        self::assertSame('error', $descriptor->toArray()['severity']);
    }

    public function testDescriptorRejectsEmptyCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('', 'Example message.');
    }

    public function testDescriptorRejectsEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('core.example', '');
    }

    public function testDescriptorRejectsInvalidCodeShape(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ErrorDescriptor('123.invalid', 'Example message.');
    }

    public function testFromArrayRoundTripPreservesValuesAndCanonicalExportShape(): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'core.example',
            message: 'Example message.',
            severity: ErrorSeverity::Critical,
            httpStatus: 503,
            extensions: [
                'z' => [
                    'enabled' => true,
                    'items' => [null, 1, 'safe'],
                ],
                'a' => 'first',
            ],
        );

        $reconstructed = ErrorDescriptor::fromArray($descriptor->toArray());

        self::assertNotSame($descriptor, $reconstructed);
        self::assertSame($descriptor->schemaVersion(), $reconstructed->schemaVersion());
        self::assertSame($descriptor->code(), $reconstructed->code());
        self::assertSame($descriptor->message(), $reconstructed->message());
        self::assertSame($descriptor->severity(), $reconstructed->severity());
        self::assertSame($descriptor->httpStatus(), $reconstructed->httpStatus());
        self::assertSame($descriptor->extensions(), $reconstructed->extensions());
        self::assertSame($descriptor->toArray(), $reconstructed->toArray());
        self::assertSame(
            ['code', 'extensions', 'httpStatus', 'message', 'schemaVersion', 'severity'],
            array_keys($reconstructed->toArray()),
        );
    }

    public function testFromArrayAcceptsUnorderedFieldsAndPreservesSeverityValues(): void
    {
        foreach (ErrorSeverity::cases() as $severity) {
            $descriptor = ErrorDescriptor::fromArray([
                'severity' => $severity->value,
                'schemaVersion' => ErrorDescriptor::SCHEMA_VERSION,
                'message' => 'Example message.',
                'extensions' => [
                    'z' => 'last',
                    'a' => [
                        'z' => true,
                        'a' => null,
                    ],
                ],
                'httpStatus' => null,
                'code' => 'core.example',
            ]);

            self::assertSame($severity, $descriptor->severity());
            self::assertNull($descriptor->httpStatus());
            self::assertSame(
                [
                    'a' => [
                        'a' => null,
                        'z' => true,
                    ],
                    'z' => 'last',
                ],
                $descriptor->extensions(),
            );
            self::assertSame(
                ['code', 'extensions', 'httpStatus', 'message', 'schemaVersion', 'severity'],
                array_keys($descriptor->toArray()),
            );
            self::assertSame($severity->value, $descriptor->toArray()['severity']);
        }
    }

    public function testFromArrayRejectsMissingAndUnexpectedFields(): void
    {
        $payload = new ErrorDescriptor('core.example', 'Example message.')->toArray();

        foreach (array_keys($payload) as $field) {
            $invalid = $payload;
            unset($invalid[$field]);

            self::assertInvalidReconstruction($invalid);
        }

        $unexpected = $payload;
        $unexpected['throwable'] = 'private-exception-data';
        self::assertInvalidReconstruction($unexpected);

        $numeric = $payload;
        $numeric[0] = 'private-exception-data';
        self::assertInvalidReconstruction($numeric);
    }

    public function testFromArrayRejectsInvalidSchemaVersionsAndFieldValues(): void
    {
        $payload = new ErrorDescriptor('core.example', 'Example message.')->toArray();

        foreach ([0, 2, '1', 1.0, false, null] as $version) {
            $invalid = $payload;
            $invalid['schemaVersion'] = $version;

            self::assertInvalidReconstruction($invalid);
        }

        foreach (
            [
                ['code', 123],
                ['code', ''],
                ['code', '123.invalid'],
                ['message', null],
                ['message', ''],
                ['message', "Unsafe\nmessage"],
                ['severity', 'fatal'],
                ['severity', 'ERROR'],
                ['severity', ErrorSeverity::Error],
                ['severity', null],
                ['httpStatus', '500'],
                ['httpStatus', true],
                ['httpStatus', 99],
                ['httpStatus', 600],
                ['extensions', 'invalid'],
                ['extensions', ['first', 'second']],
                ['extensions', [1 => 'value']],
                ['extensions', ['ratio' => 1.5]],
            ] as [$field, $value]
        ) {
            $invalid = $payload;
            $invalid[$field] = $value;

            self::assertInvalidReconstruction($invalid);
        }
    }

    public function testFromArrayRejectsUnsafeNestedExtensionsWithoutDisclosingPaths(): void
    {
        $payload = new ErrorDescriptor('core.example', 'Example message.')->toArray();
        $payload['extensions'] = [
            'diagnostics' => [
                'password' => 'private-marker',
            ],
        ];

        $exception = self::assertInvalidReconstruction($payload);

        self::assertStringNotContainsString('diagnostics.password', $exception->getMessage());
        self::assertStringNotContainsString('private-marker', $exception->getMessage());
        self::assertStringNotContainsString('password', $exception->getMessage());
    }

    public function testFromArrayRetainsForbiddenExtensionAndResourceBudgetRejections(): void
    {
        $payload = new ErrorDescriptor('core.example', 'Example message.')->toArray();

        $tooDeep = ['value' => 'safe'];

        for ($depth = 0; $depth < 8; ++$depth) {
            $tooDeep = ['nested' => $tooDeep];
        }

        $tooManyNodes = [
            'items' => array_fill(0, 256, true),
        ];

        $tooManyBytes = [];

        for ($index = 0; $index < 16; ++$index) {
            $tooManyBytes[sprintf('k%03d', $index)] = str_repeat('a', 4093);
        }

        foreach (
            [
                ['credentials' => 'private-marker'],
                ['details' => ['token' => 'private-marker']],
                ['reference' => '/home/app/private/.env'],
                ['details' => [1 => 'value']],
                $tooDeep,
                $tooManyNodes,
                ['reference' => str_repeat('a', 4097)],
                $tooManyBytes,
            ] as $extensions
        ) {
            $invalid = $payload;
            $invalid['extensions'] = $extensions;

            self::assertInvalidReconstruction($invalid);
        }
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    private static function assertInvalidReconstruction(array $payload): \InvalidArgumentException
    {
        try {
            ErrorDescriptor::fromArray($payload);

            self::fail('Expected invalid error descriptor payload.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Invalid error descriptor payload.', $exception->getMessage());
            self::assertNull($exception->getPrevious());

            return $exception;
        }
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }
}
