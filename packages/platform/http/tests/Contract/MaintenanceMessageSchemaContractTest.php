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

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaintenanceMessageSchemaContractTest extends TestCase
{
    public function testConstructorRequiresAllThreeExplicitParameters(): void
    {
        $reflection = new \ReflectionClass(MaintenanceMessage::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(3, $constructor->getNumberOfRequiredParameters());
        $parameters = $constructor->getParameters();
        self::assertSame(
            ['schemaVersion', 'message', 'retryAfterSeconds'],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            )
        );
        self::assertSame(
            ['int', 'string', '?int'],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                $parameters,
            )
        );
        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->isOptional());
        }
    }

    #[DataProvider('validMessages')]
    public function testDirectAndJsonConstructionPreserveExactSchemaAndCanonicalBytes(
        string $message,
        ?int $retryAfterSeconds,
    ): void {
        $expected = ['schemaVersion' => 1, 'message' => $message];
        if ($retryAfterSeconds !== null) {
            $expected['retryAfterSeconds'] = $retryAfterSeconds;
        }

        $direct = new MaintenanceMessage(1, $message, $retryAfterSeconds);
        $bytes = StableJsonEncoder::encodeStableMap($expected);
        $reordered = \array_reverse($expected, true);
        self::assertSame($bytes, StableJsonEncoder::encodeStableMap($reordered));
        $json = \json_encode($reordered, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE);

        foreach ([$direct, MaintenanceMessage::fromJson($bytes), MaintenanceMessage::fromJson($json)] as $actual) {
            self::assertSame($expected, $actual->toArray());
            self::assertSame($message, $actual->message());
            self::assertSame($retryAfterSeconds, $actual->retryAfterSeconds());
            self::assertSame($bytes, StableJsonEncoder::encodeStableMap($actual->toArray()));
        }

        self::assertSame(1, \substr_count($bytes, "\n"));
        self::assertStringEndsWith("\n", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
    }

    /** @return iterable<string, array{string, ?int}> */
    public static function validMessages(): iterable
    {
        yield 'optional-absent' => ['Maintenance / scheduled', null];
        yield 'minimum' => ['x', 1];
        yield 'maximum' => [\str_repeat('x', 1024), 86400];
        yield 'utf8-byte-boundary' => [\str_repeat('é', 512), 60];
        yield 'interior-unicode-space' => ["Public\u{00A0}notice", null];
    }

    public function testExactCanonicalJsonBytesAndInputByteLimit(): void
    {
        $json = '{"schemaVersion":1,"message":"Maintenance / café","retryAfterSeconds":60}';
        $canonical = "{\"message\":\"Maintenance / café\",\"retryAfterSeconds\":60,\"schemaVersion\":1}\n";
        self::assertSame(
            $canonical,
            StableJsonEncoder::encodeStableMap(MaintenanceMessage::fromJson($json)->toArray()),
        );
        self::assertSame(
            "{\"message\":\"Maintenance\",\"schemaVersion\":1}\n",
            StableJsonEncoder::encodeStableMap(new MaintenanceMessage(1, 'Maintenance', null)->toArray()),
        );
        $bounded = \str_pad($json, 8192, ' ');
        self::assertSame(
            $canonical,
            StableJsonEncoder::encodeStableMap(MaintenanceMessage::fromJson($bounded)->toArray()),
        );
        self::assertInvalid(static fn () => MaintenanceMessage::fromJson($bounded . ' '));
    }

    #[DataProvider('invalidJson')]
    public function testInvalidJsonUsesOnlyFixedSafeDiagnostic(string $json): void
    {
        self::assertInvalid(static fn () => MaintenanceMessage::fromJson($json));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidJson(): iterable
    {
        yield 'empty-object' => ['{}'];
        yield 'no-version' => ['{"message":"synthetic"}'];
        yield 'no-message' => ['{"schemaVersion":1}'];
        yield 'unknown-key' => ['{"schemaVersion":1,"message":"synthetic","other":true}'];
        yield 'duplicate-version' => ['{"schemaVersion":1,"schemaVersion":1,"message":"synthetic"}'];
        yield 'duplicate-message' => ['{"schemaVersion":1,"message":"synthetic","message":"other"}'];
        yield 'duplicate-retry' => ['{"schemaVersion":1,"message":"synthetic","retryAfterSeconds":1,"retryAfterSeconds":2}'];
        yield 'escaped-duplicate' => ['{"schemaVersion":1,"message":"synthetic","\u006dessage":"other"}'];
        yield 'escaped-retry-duplicate' => ['{"schemaVersion":1,"message":"synthetic","retryAfterSeconds":1,"retryAfterSecond\u0073":2}'];
        yield 'array-root' => ['[1,"synthetic"]'];
        yield 'empty-list' => ['[]'];
        yield 'string-root' => ['"synthetic"'];
        yield 'int-root' => ['1'];
        yield 'boolean-root' => ['false'];
        yield 'null-root' => ['null'];
        yield 'malformed' => ['{"message":"synthetic"'];
        yield 'trailing-data' => ['{"schemaVersion":1,"message":"synthetic"}{}'];
        yield 'invalid-utf8' => ["{\"schemaVersion\":1,\"message\":\"\xff\"}"];
        yield 'invalid-surrogate' => ['{"schemaVersion":1,"message":"\uD800"}'];
        foreach (['null', 'false', '"1"', '1.0', '1e0', '[]', '{}'] as $index => $value) {
            yield 'version-type-' . $index => ['{"schemaVersion":' . $value . ',"message":"synthetic"}'];
        }
        foreach (['null', 'true', '1', '1.5', '[]', '{}'] as $index => $value) {
            yield 'message-type-' . $index => ['{"schemaVersion":1,"message":' . $value . '}'];
        }
        foreach (['null', 'true', '"1"', '1.0', '1e2', '[]', '{}', '92233720368547758080'] as $index => $value) {
            yield 'retry-type-' . $index => ['{"schemaVersion":1,"message":"synthetic","retryAfterSeconds":' . $value . '}'];
        }
    }

    #[DataProvider('invalidMessages')]
    public function testDirectAndJsonConstructionRejectTheSameSemanticViolations(
        int $version,
        string $message,
        ?int $retryAfterSeconds,
    ): void {
        self::assertInvalid(static fn () => new MaintenanceMessage($version, $message, $retryAfterSeconds));
        $map = ['schemaVersion' => $version, 'message' => $message];
        if ($retryAfterSeconds !== null) {
            $map['retryAfterSeconds'] = $retryAfterSeconds;
        }
        $json = \json_encode($map, \JSON_THROW_ON_ERROR);
        self::assertInvalid(static fn () => MaintenanceMessage::fromJson($json));
    }

    /** @return iterable<string, array{int, string, ?int}> */
    public static function invalidMessages(): iterable
    {
        yield 'old-version' => [0, 'synthetic', null];
        yield 'future-version' => [2, 'synthetic', null];
        yield 'negative-version' => [-1, 'synthetic', null];
        yield 'retry-zero' => [1, 'synthetic', 0];
        yield 'retry-negative' => [1, 'synthetic', -1];
        yield 'retry-overflow' => [1, 'synthetic', 86401];
        yield 'empty' => [1, '', null];
        yield 'oversized' => [1, \str_repeat('x', 1025), null];
        yield 'oversized-utf8' => [1, \str_repeat('é', 513), null];
        foreach (
            [
                ' synthetic',
                'synthetic ',
                ' ',
                "a\0b",
                "a\tb",
                "a\nb",
                "a\rb",
                "a\x7fb",
                "a\u{0085}b",
                "\u{00A0}",
                "\u{2003}\u{3000}",
                "\u{2003}synthetic",
                "synthetic\u{00A0}",
                "a\u{2028}b",
                "a\u{2029}b",
            ] as $index => $message
        ) {
            yield 'whitespace-control-' . $index => [1, $message, null];
        }
    }

    public function testDirectInvalidUtf8HasSafeDiagnostic(): void
    {
        self::assertInvalid(static fn () => new MaintenanceMessage(1, "synthetic\xff", null));
    }

    private static function assertInvalid(\Closure $operation): void
    {
        try {
            $operation();
            self::fail('maintenance-message-invalid-value-accepted');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('http-maintenance-message-invalid', $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
