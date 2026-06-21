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

use Coretsia\Foundation\Serialization\Exception\JsonLikeNormalizationException;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonDecoder;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use PHPUnit\Framework\TestCase;

final class StableJsonSerializationSafetyContractTest extends TestCase
{
    public function testSerializationFoundationDoesNotUseFilesystemEnvOrStdoutStderr(): void
    {
        foreach (
            [
                StableJsonEncoder::class,
                StableJsonDecoder::class,
                JsonLikeNormalizer::class,
                JsonLikeNormalizationException::class,
            ] as $className
        ) {
            $source = self::classSource($className);

            self::assertStringNotContainsString('file_get_contents(', $source);
            self::assertStringNotContainsString('file_put_contents(', $source);
            self::assertStringNotContainsString('fopen(', $source);
            self::assertStringNotContainsString('fwrite(', $source);
            self::assertStringNotContainsString('mkdir(', $source);
            self::assertStringNotContainsString('realpath(', $source);
            self::assertStringNotContainsString('is_file(', $source);
            self::assertStringNotContainsString('is_dir(', $source);

            self::assertStringNotContainsString('getenv(', $source);
            self::assertStringNotContainsString('putenv(', $source);
            self::assertStringNotContainsString('$_ENV', $source);
            self::assertStringNotContainsString('$_SERVER', $source);

            self::assertStringNotContainsString('STDOUT', $source);
            self::assertStringNotContainsString('STDERR', $source);
            self::assertStringNotContainsString('echo ', $source);
            self::assertStringNotContainsString('print ', $source);
            self::assertStringNotContainsString('var_dump(', $source);
            self::assertStringNotContainsString('print_r(', $source);
            self::assertStringNotContainsString('error_log(', $source);
        }
    }

    public function testSerializationOperationsDoNotEmitStdout(): void
    {
        self::captureStdout(static function (): void {
            self::assertSame(
                "{\"a\":1,\"z\":2}\n",
                StableJsonEncoder::encodeStable([
                    'z' => 2,
                    'a' => 1,
                ]),
            );

            self::assertSame(
                [
                    'a' => 1,
                    'z' => 2,
                ],
                StableJsonDecoder::decodeStable('{"z":2,"a":1}'),
            );

            self::assertSame(
                [
                    'a' => 1,
                    'z' => 2,
                ],
                JsonLikeNormalizer::normalize([
                    'z' => 2,
                    'a' => 1,
                ]),
            );
        });
    }

    public function testEncoderDiagnosticsDoNotExposeRawValuesPayloadsPathsSecretsOrClassNames(): void
    {
        $object = new class() {
            public string $secret = 'raw-encoder-secret-token-/tmp/coretsia-secret';
        };

        self::assertUnsafeDiagnosticsNotExposed(
            operation: static fn (): string => StableJsonEncoder::encodeStable([
                'safe' => [
                    'value' => $object,
                ],
                'payload' => 'raw-payload-fragment',
                'headers' => [
                    'Authorization' => 'Bearer raw-token',
                ],
                'environment' => 'ENV_SECRET_VALUE',
            ]),
            expectedMessage: 'stable-json-object-forbidden: value at value.safe.value',
            forbiddenDiagnosticsNeedles: [
                'raw-encoder-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-payload-fragment',
                'Authorization',
                'Bearer raw-token',
                'raw-token',
                'ENV_SECRET_VALUE',
                $object::class,
                'class@anonymous',
                'stdClass',
            ],
        );
    }

    public function testDecoderDiagnosticsDoNotExposeRawJsonPayloadsPathsSecretsHeadersOrTokens(): void
    {
        self::assertUnsafeDiagnosticsNotExposed(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable(
                '{"0":"raw-decoder-payload-secret-token-/tmp/coretsia-secret","headers":{"Authorization":"Bearer raw-token"}}',
            ),
            expectedMessage: 'stable-json-map-key-must-be-string: value at value',
            forbiddenDiagnosticsNeedles: [
                '{"0":"raw-decoder-payload-secret-token-/tmp/coretsia-secret","headers":{"Authorization":"Bearer raw-token"}}',
                'raw-decoder-payload-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-decoder-payload-secret-token',
                'payload-secret-token',
                'Authorization',
                'Bearer raw-token',
                'raw-token',
                'headers',
                'ENV_SECRET_VALUE',
            ],
        );
    }

    public function testNormalizerDiagnosticsDoNotExposeRawValuesPathsSecretsHeadersOrTokens(): void
    {
        $object = new class() {
            public string $secret = 'raw-normalizer-secret-token-/tmp/coretsia-secret';
        };

        self::assertJsonLikeDiagnosticsNotExposed(
            operation: static fn (): mixed => JsonLikeNormalizer::normalize([
                'safe' => [
                    'value' => $object,
                ],
                'payload' => 'raw-payload-fragment',
                'headers' => [
                    'Authorization' => 'Bearer raw-token',
                ],
                'environment' => 'ENV_SECRET_VALUE',
            ]),
            expectedMessage: JsonLikeNormalizationException::ERROR_CODE
            . ': '
            . JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN
            . ': value at value.safe.value',
            expectedPath: 'value.safe.value',
            expectedReason: JsonLikeNormalizationException::REASON_OBJECT_FORBIDDEN,
            forbiddenDiagnosticsNeedles: [
                'raw-normalizer-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-payload-fragment',
                'Authorization',
                'Bearer raw-token',
                'raw-token',
                'ENV_SECRET_VALUE',
                $object::class,
                'class@anonymous',
                'stdClass',
            ],
        );
    }

    public function testInvalidJsonDiagnosticsDoNotExposeRawJsonPayloads(): void
    {
        $rawJson = '{"safe":"raw-json-payload-secret-token-/tmp/coretsia-secret"';

        self::assertUnsafeDiagnosticsNotExposed(
            operation: static fn (): mixed => StableJsonDecoder::decodeStable($rawJson),
            expectedMessage: 'stable-json-decode-failed',
            forbiddenDiagnosticsNeedles: [
                $rawJson,
                'raw-json-payload-secret-token-/tmp/coretsia-secret',
                '/tmp/coretsia-secret',
                'raw-json-payload-secret-token',
                'payload-secret-token',
            ],
        );
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertUnsafeDiagnosticsNotExposed(
        callable $operation,
        string $expectedMessage,
        array $forbiddenDiagnosticsNeedles,
    ): void {
        try {
            $operation();

            self::fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());

            self::assertExceptionChainDoesNotContain(
                exception: $exception,
                forbiddenDiagnosticsNeedles: $forbiddenDiagnosticsNeedles,
            );
        }
    }

    /**
     * @param callable(): mixed $operation
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertJsonLikeDiagnosticsNotExposed(
        callable $operation,
        string $expectedMessage,
        string $expectedPath,
        string $expectedReason,
        array $forbiddenDiagnosticsNeedles,
    ): void {
        try {
            $operation();

            self::fail('Expected JsonLikeNormalizationException was not thrown.');
        } catch (JsonLikeNormalizationException $exception) {
            self::assertSame($expectedMessage, $exception->getMessage());
            self::assertSame(JsonLikeNormalizationException::ERROR_CODE, $exception->errorCode());
            self::assertSame($expectedPath, $exception->path());
            self::assertSame($expectedReason, $exception->reason());

            self::assertExceptionChainDoesNotContain(
                exception: $exception,
                forbiddenDiagnosticsNeedles: $forbiddenDiagnosticsNeedles,
            );

            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                self::assertStringNotContainsString($needle, $exception->path());
                self::assertStringNotContainsString($needle, $exception->reason());
            }
        }
    }

    /**
     * @param list<string> $forbiddenDiagnosticsNeedles
     */
    private static function assertExceptionChainDoesNotContain(
        \Throwable $exception,
        array $forbiddenDiagnosticsNeedles,
    ): void {
        $current = $exception;

        do {
            foreach ($forbiddenDiagnosticsNeedles as $needle) {
                if ($needle === '') {
                    continue;
                }

                self::assertStringNotContainsString(
                    $needle,
                    $current->getMessage(),
                    'Stable JSON diagnostics must not leak raw values, payloads, paths, headers, tokens, class names, or environment-specific data.',
                );
            }

            $current = $current->getPrevious();
        } while ($current !== null);
    }

    private static function captureStdout(\Closure $callback): mixed
    {
        \ob_start();

        try {
            $result = $callback();
            $stdout = \ob_get_clean();
        } catch (\Throwable $throwable) {
            \ob_end_clean();

            throw $throwable;
        }

        self::assertSame('', $stdout);

        return $result;
    }

    /**
     * @param class-string $className
     */
    private static function classSource(string $className): string
    {
        $reflection = new \ReflectionClass($className);
        $file = $reflection->getFileName();

        self::assertIsString($file);

        $source = \file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }
}
