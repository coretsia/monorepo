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

namespace Coretsia\Platform\Cli\Tests\Contract;

use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use Coretsia\Platform\Cli\Output\Formatter\PlainFormatter;
use PHPUnit\Framework\TestCase;

final class CliReusesFoundationJsonLikePrimitivesContractTest extends TestCase
{
    public function testBufferUsesFoundationNormalizerTwiceAndFoundationEncoderForAccounting(): void
    {
        $source = self::source(CommandOutputBuffer::class);
        self::assertSame(3, \substr_count($source, 'JsonLikeNormalizer::normalize('));
        self::assertSame(1, \substr_count($source, 'StableJsonEncoder::encodeStable('));
        self::assertStringContainsString('JsonLikeNormalizationLimits', $source);
        self::assertStringContainsString('normalizeValueStrings(', $source);
        self::assertStringContainsString('assertSafeMapKey(', $source);
        self::assertStringContainsString('normalizeString(', $source);
        self::assertSame(0, \preg_match('/\b(?:json_encode|usort|uksort|ksort)\s*\(/', $source));
    }

    public function testJsonAndPlainFormattersUseCanonicalFoundationEncoding(): void
    {
        self::assertStringContainsString('StableJsonEncoder::encodeStableMap(', self::source(JsonFormatter::class));
        self::assertStringContainsString('StableJsonEncoder::encodeStable(', self::source(PlainFormatter::class));
        $buffer = new CommandOutputBuffer();
        $buffer->json(['z' => 'last', 'a' => ['y' => 2, 'b' => 1]]);
        $batch = $buffer->finalize();
        $json = new JsonFormatter()->format($batch, 'owner:test', 0);
        $document = [
            'schema' => 'coretsia.cli-output@1',
            'meta' => ['command' => 'owner:test', 'outcome' => 'success', 'exit_code' => 0],
            'data' => ['records' => $batch->records()],
        ];
        self::assertSame(\substr(StableJsonEncoder::encodeStableMap($document), 0, -1), $json->stdoutBytes());
        self::assertSame(
            '{"a":{"b":1,"y":2},"z":"last"}',
            PlainFormatter::stableValue($batch->records()[0]['payload']),
        );
    }

    public function testNoIndependentCliStructuralNormalizerOrRecursiveSorterIsIntroduced(): void
    {
        $root = \dirname(__DIR__, 2) . '/src';
        $names = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $names[] = $file->getFilename();
            $code = self::codeWithoutComments($file->getPathname());
            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:json_encode|json_decode|ksort|krsort|uksort|uasort|usort|array_multisort)\s*\(/',
                    $code,
                ),
                $file->getFilename(),
            );

            if (\str_contains(\str_replace('\\', '/', $file->getPathname()), '/Output/')) {
                self::assertSame(
                    0,
                    \preg_match('/\b(?:sort|asort|arsort|natsort|natcasesort)\s*\(/', $code),
                    $file->getFilename(),
                );
            }
            self::assertSame(
                0,
                \preg_match(
                    '/\b(?:class|trait|interface)\s+(?:JsonLikeNormalizer|StableJsonEncoder|RecursiveMapSorter|JsonLikeValidator)\b/',
                    $code,
                ),
            );
        }
        self::assertNotContains('JsonLikeNormalizer.php', $names);
        self::assertNotContains('StableJsonEncoder.php', $names);
        self::assertNotContains('RecursiveMapSorter.php', $names);
    }

    private static function source(string $class): string
    {
        return self::codeWithoutComments(new \ReflectionClass($class)->getFileName());
    }

    private static function codeWithoutComments(string $path): string
    {
        $source = \file_get_contents($path);
        self::assertIsString($source);
        $result = '';
        foreach (\token_get_all($source) as $token) {
            if (\is_array($token) && \in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $result .= \is_array($token) ? $token[1] : $token;
        }
        return $result;
    }
}
