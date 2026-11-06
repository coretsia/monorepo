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

namespace Coretsia\Platform\ProblemDetails\Tests\Unit;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\ProblemDetails\ProblemDetails;
use Coretsia\Platform\ProblemDetails\Renderer\ProblemDetailsJsonRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class JsonRendererDeterministicOrderTest extends TestCase
{
    public function testRfc9457BaselineHasExactContentTypeAndStableJsonBytes(): void
    {
        $renderer = new ProblemDetailsJsonRenderer();
        $problem = new ProblemDetails(404);
        $expected = "{\"status\":404,\"title\":\"Not Found\",\"type\":\"about:blank\"}\n";

        self::assertSame('application/problem+json', $renderer->contentType());
        self::assertSame(ProblemDetailsJsonRenderer::CONTENT_TYPE, $renderer->contentType());
        self::assertSame($expected, $renderer->render($problem));
        self::assertSame(['status' => 404, 'title' => 'Not Found', 'type' => 'about:blank'], $problem->toArray());
        self::assertSame(['status', 'title', 'type'], \array_keys($problem->toArray()));

        for ($attempt = 0; $attempt < 32; ++$attempt) {
            self::assertSame($expected, $renderer->render($problem));
            self::assertSame($expected, new ProblemDetailsJsonRenderer()->render(new ProblemDetails(404)));
        }
        self::assertSame("\n", \substr($expected, -1));
        self::assertSame(1, \substr_count($expected, "\n"));
    }

    public function testCanonicalEncoderSortsMapsRecursivelyAndPreservesListOrder(): void
    {
        $first = [
            'z' => ['right' => 2, 'left' => 1],
            'a' => [['z' => 4, 'a' => 3], ['two', 'one']],
        ];
        $second = [
            'a' => [['a' => 3, 'z' => 4], ['two', 'one']],
            'z' => ['left' => 1, 'right' => 2],
        ];
        $expected = "{\"a\":[{\"a\":3,\"z\":4},[\"two\",\"one\"]],\"z\":{\"left\":1,\"right\":2}}\n";

        self::assertSame($expected, StableJsonEncoder::encodeStableMap($first));
        self::assertSame($expected, StableJsonEncoder::encodeStableMap($second));
        self::assertSame(
            StableJsonEncoder::encodeStableMap(new ProblemDetails(500)->toArray()),
            new ProblemDetailsJsonRenderer()->render(new ProblemDetails(500)),
        );
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function unsafeJsonValues(): iterable
    {
        yield 'finite float' => [1.5];
        yield 'NaN' => [\NAN];
        yield 'infinite float' => [\INF];
        yield 'object' => [new \stdClass()];
        yield 'invalid UTF-8' => ["\xB1"];
        yield 'mixed-key map' => [['valid' => 'ok', 3 => 'SYNTHETIC_PRIVATE']];
        yield 'nested mixed-key map' => [['nested' => ['valid' => 'ok', 3 => 'SYNTHETIC_PRIVATE']]];
    }

    #[DataProvider('unsafeJsonValues')]
    public function testCanonicalEncoderRejectsValuesOutsideSafeJsonModel(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StableJsonEncoder::encodeStableMap(['status' => $value]);
    }

    public function testCanonicalEncoderRejectsResourceValues(): void
    {
        $resource = \fopen('php://memory', 'r+');
        self::assertIsResource($resource);

        try {
            $this->expectException(\InvalidArgumentException::class);
            StableJsonEncoder::encodeStableMap(['status' => $resource]);
        } finally {
            \fclose($resource);
        }
    }

    public function testCanonicalEncoderRejectsClosures(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        StableJsonEncoder::encodeStableMap(['status' => static fn (): string => 'SYNTHETIC_PRIVATE']);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidProblemStatuses(): iterable
    {
        yield 'informational' => [100];
        yield 'success' => [200];
        yield 'redirect' => [302];
        yield 'below errors' => [399];
        yield 'above errors' => [600];
    }

    #[DataProvider('invalidProblemStatuses')]
    public function testPublicProblemModelRejectsInvalidResponseStatuses(int $status): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ProblemDetails($status);
    }

    public function testRendererDoesNotOutputDiagnosticsOrMaintainMutableState(): void
    {
        $renderer = new ProblemDetailsJsonRenderer();
        $reflection = new \ReflectionClass($renderer);
        $warnings = [];

        self::assertTrue($reflection->isFinal());
        self::assertNull($reflection->getConstructor());
        self::assertSame([], $reflection->getProperties());
        self::assertFalse($reflection->hasMethod('reset'));

        \set_error_handler(static function (int $severity, string $message) use (&$warnings): bool {
            $warnings[] = [$severity, $message];

            return true;
        });
        \ob_start();
        try {
            for ($attempt = 0; $attempt < 16; ++$attempt) {
                self::assertSame(
                    "{\"status\":500,\"title\":\"Internal Server Error\",\"type\":\"about:blank\"}\n",
                    $renderer->render(new ProblemDetails(500)),
                );
            }
        } finally {
            $output = (string) \ob_get_clean();
            \restore_error_handler();
        }

        self::assertSame('', $output);
        self::assertSame([], $warnings);
    }
}
