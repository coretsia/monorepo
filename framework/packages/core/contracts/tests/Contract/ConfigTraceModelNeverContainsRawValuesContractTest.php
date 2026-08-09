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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

final class ConfigTraceModelNeverContainsRawValuesContractTest extends TestCase
{
    /**
     * @var list<string>
     */
    private const array FORBIDDEN_RAW_VALUE_KEYS = [
        'value',
        'rawValue',
        'configValue',
        'envValue',
        'rawEnvValue',
        'secret',
        'password',
        'token',
        'credential',
        'credentials',
        'privateKey',
        'authorizationHeader',
        'cookie',
        'requestBody',
        'responseBody',
    ];

    public function testConstructorDoesNotAcceptRawValueArguments(): void
    {
        $constructor = new ReflectionMethod(ConfigValueSource::class, '__construct');

        self::assertSame(
            [
                'type',
                'root',
                'sourceId',
                'path',
                'keyPath',
                'directive',
                'precedence',
                'redacted',
                'meta',
            ],
            array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $constructor->getParameters(),
            ),
        );
    }

    public function testModelPropertiesDoNotStoreRawConfigOrEnvValues(): void
    {
        $class = new ReflectionClass(ConfigValueSource::class);

        self::assertSame(
            [
                'type',
                'root',
                'sourceId',
                'path',
                'keyPath',
                'directive',
                'precedence',
                'redacted',
                'meta',
            ],
            array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $class->getProperties(),
            ),
        );
    }

    public function testExportedSourceTraceContainsOnlySafeContractKeys(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'foundation',
            sourceId: 'env.runtime',
            path: 'env/runtime',
            keyPath: 'container.cache',
            precedence: 30,
            redacted: true,
        );

        $exported = $source->toArray();

        self::assertSame(
            [
                'directive',
                'keyPath',
                'meta',
                'path',
                'precedence',
                'redacted',
                'root',
                'schemaVersion',
                'sourceId',
                'type',
            ],
            array_keys($exported),
        );

        foreach (self::FORBIDDEN_RAW_VALUE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $exported);
        }

        self::assertTrue($exported['redacted']);
    }

    public function testExportedSourceTraceIsJsonLikeWithoutFloatsObjectsResourcesOrCallables(): void
    {
        $source = new ConfigValueSource(
            type: ConfigSourceType::GeneratedArtifact,
            root: 'kernel',
            sourceId: 'compiled.kernel',
            path: 'generated/config',
            keyPath: 'boot.providers',
            precedence: 50,
            meta: [
                'safeHash' => 'sha256:abc',
                'valueLength' => 12,
                'nested' => [
                    'a' => true,
                    'b' => null,
                ],
            ],
        );

        self::assertJsonLikeWithoutForbiddenRuntimeValues($source->toArray());
    }

    public function testSourceTraceRejectsAbsolutePathsUrlsAndTraversalIdentifiers(): void
    {
        foreach (['/tmp/config.php', 'C:\\app\\config.php', 'https://example.test/config', '../secret'] as $unsafe) {
            try {
                new ConfigValueSource(
                    type: ConfigSourceType::AppConfig,
                    root: 'foundation',
                    sourceId: 'app.foundation',
                    path: $unsafe,
                    keyPath: 'container',
                );

                self::fail('Expected unsafe path to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('path', $exception->getMessage());
            }

            try {
                new ConfigValueSource(
                    type: ConfigSourceType::AppConfig,
                    root: 'foundation',
                    sourceId: $unsafe,
                    path: 'app.config',
                    keyPath: 'container',
                );

                self::fail('Expected unsafe sourceId to be rejected.');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('sourceId', $exception->getMessage());
            }
        }
    }

    public function testMetaRejectsFloatObjectResourceAndClosureValues(): void
    {
        $resource = fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            foreach (
                [
                    ['float' => 1.1],
                    ['object' => new \stdClass()],
                    ['resource' => $resource],
                    ['closure' => static fn (): string => 'invalid'],
                ] as $meta
            ) {
                try {
                    new ConfigValueSource(
                        type: ConfigSourceType::Runtime,
                        root: 'kernel',
                        sourceId: 'runtime.kernel',
                        meta: $meta,
                    );

                    self::fail('Expected invalid meta to be rejected.');
                } catch (InvalidArgumentException $exception) {
                    self::assertStringContainsString('metadata', $exception->getMessage());
                }
            }
        } finally {
            fclose($resource);
        }
    }

    public function testRedactionMarkerIsMetadataNotRawValueStorage(): void
    {
        $redacted = new ConfigValueSource(
            type: ConfigSourceType::Env,
            root: 'foundation',
            sourceId: 'env.runtime',
            path: 'env/runtime',
            keyPath: 'container.password',
            precedence: 30,
            redacted: true,
        );

        $notRedacted = new ConfigValueSource(
            type: ConfigSourceType::PackageDefault,
            root: 'foundation',
            sourceId: 'core.foundation',
            path: 'package.defaults',
            keyPath: 'container.cache',
            precedence: 10,
            redacted: false,
        );

        self::assertTrue($redacted->isRedacted());
        self::assertFalse($notRedacted->isRedacted());

        self::assertSame(true, $redacted->toArray()['redacted']);
        self::assertSame(false, $notRedacted->toArray()['redacted']);

        foreach (self::FORBIDDEN_RAW_VALUE_KEYS as $key) {
            self::assertArrayNotHasKey($key, $redacted->toArray());
            self::assertArrayNotHasKey($key, $notRedacted->toArray());
        }
    }

    private static function assertJsonLikeWithoutForbiddenRuntimeValues(mixed $value): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        self::assertFalse(is_float($value), 'Float values are forbidden in exported contract trace shapes.');
        self::assertFalse(is_object($value), 'Objects are forbidden in exported contract trace shapes.');
        self::assertFalse(is_resource($value), 'Resources are forbidden in exported contract trace shapes.');
        self::assertFalse(is_callable($value), 'Callables are forbidden in exported contract trace shapes.');

        self::assertIsArray($value);

        foreach ($value as $key => $item) {
            self::assertTrue(is_int($key) || is_string($key));
            self::assertJsonLikeWithoutForbiddenRuntimeValues($item);
        }
    }
}
