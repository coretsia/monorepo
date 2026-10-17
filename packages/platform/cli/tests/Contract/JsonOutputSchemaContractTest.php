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

use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use Coretsia\Platform\Cli\Output\Formatter\JsonFormatter;
use PHPUnit\Framework\TestCase;

final class JsonOutputSchemaContractTest extends TestCase
{
    public function testActualJsonFormatterOutputConformsToVersionedResourceSchema(): void
    {
        $schema = self::schema();
        self::assertSame('https://json-schema.org/draft/2020-12/schema', $schema->{'$schema'});
        self::assertSame('coretsia.cli-output@1', $schema->properties->schema->const ?? null);
        $formatter = new JsonFormatter();

        foreach (
            [
                [null, 0],
                ['owner:commands', 0],
                ['owner:commands', 2],
                ['owner:commands', 255],
            ] as [$command, $exit]
        ) {
            $buffer = new CommandOutputBuffer();
            $buffer->text("hello\nworld");
            $buffer->json(['z' => ['last' => 2, 'first' => 1], 'a' => [true, false, null, 42]]);
            $buffer->error('SAFE_CODE', 'safe message');
            $formatted = $formatter->format($buffer->finalize(), $command, $exit);
            self::assertSame('', $formatted->stderrBytes());
            self::assertFalse(\str_ends_with($formatted->stdoutBytes(), "\n"));
            $document = \json_decode($formatted->stdoutBytes(), false, flags: JSON_THROW_ON_ERROR);
            self::assertTrue(self::conforms($document, $schema, $schema), 'Rendered JSON violates cli_output@1.json.');
            self::assertSame($schema->properties->schema->const, $document->schema);
        }
    }

    public function testResourceSchemaRejectsMissingFieldsWrongOutcomeExtraKeysAndUnsafeRecords(): void
    {
        $schema = self::schema();
        $valid = \json_decode(
            new JsonFormatter()->format(new CommandOutputBuffer()->finalize(), null, 0)->stdoutBytes(),
        );
        self::assertTrue(self::conforms($valid, $schema, $schema));

        foreach (
            [
                static function (object $doc): void {
                    unset($doc->meta);
                },
                static function (object $doc): void {
                    $doc->unsupported = 'extra';
                },
                static function (object $doc): void {
                    $doc->meta->outcome = 'failure';
                },
                static function (object $doc): void {
                    $doc->meta->exit_code = 256;
                },
                static function (object $doc): void {
                    $doc->data->records[] = (object) ['type' => 'text', 'text' => "invalid\x1B[31m"];
                },
                static function (object $doc): void {
                    $doc->data->records[] = (object) [
                        'type' => 'error',
                        'code' => 'SAFE',
                        'message' => "invalid\nmessage",
                    ];
                },
                static function (object $doc): void {
                    $doc->data->records[] = (object) ['type' => 'json', 'payload' => (object) ["invalid\nkey" => 'x']];
                },
            ] as $mutate
        ) {
            $candidate = \unserialize(\serialize($valid));
            $mutate($candidate);
            self::assertFalse(self::conforms($candidate, $schema, $schema));
        }
    }

    private static function schema(): object
    {
        $path = \dirname(__DIR__, 2) . '/resources/schema/cli_output@1.json';
        self::assertFileExists($path);
        $content = \file_get_contents($path);
        self::assertIsString($content);
        $schema = \json_decode($content, false, flags: JSON_THROW_ON_ERROR);
        self::assertIsObject($schema);
        return $schema;
    }

    /** Minimal generic Draft 2020-12 keyword interpreter for this versioned schema, not a duplicated inline schema. */
    private static function conforms(mixed $value, object $schema, object $root): bool
    {
        if (isset($schema->{'$ref'})) {
            $path = \explode('/', \substr($schema->{'$ref'}, 2));
            $target = $root;
            foreach ($path as $part) {
                if (!\is_object($target) || !\property_exists($target, $part)) {
                    return false;
                }
                $target = $target->{$part};
            }
            return \is_object($target) && self::conforms($value, $target, $root);
        }
        if (isset($schema->type)) {
            $types = \is_array($schema->type) ? $schema->type : [$schema->type];
            $matches = static fn (string $type): bool => match ($type) {
                'object' => \is_object($value),
                'array' => \is_array($value),
                'string' => \is_string($value),
                'integer' => \is_int($value),
                'boolean' => \is_bool($value),
                'null' => $value === null,
                default => false,
            };
            if (!\array_any($types, $matches)) {
                return false;
            }
        }
        if (\property_exists($schema, 'const') && $schema->const !== $value) {
            return false;
        }
        if (isset($schema->enum) && !\in_array($value, $schema->enum, true)) {
            return false;
        }
        if (\is_int($value)) {
            if (isset($schema->minimum) && $value < $schema->minimum) {
                return false;
            }
            if (isset($schema->maximum) && $value > $schema->maximum) {
                return false;
            }
        }
        if (\is_string($value)) {
            if (isset($schema->minLength) && \preg_match_all('/./us', $value) < $schema->minLength) {
                return false;
            }
            if (isset($schema->pattern) && !self::matchesSchemaPattern($schema->pattern, $value)) {
                return false;
            }
        }
        if (\is_object($value)) {
            foreach ($schema->required ?? [] as $required) {
                if (!\property_exists($value, $required)) {
                    return false;
                }
            }
            foreach (\get_object_vars($value) as $key => $entry) {
                if (isset($schema->propertyNames) && !self::conforms($key, $schema->propertyNames, $root)) {
                    return false;
                }
                if (isset($schema->properties) && \property_exists($schema->properties, $key)) {
                    if (!self::conforms($entry, $schema->properties->{$key}, $root)) {
                        return false;
                    }
                } elseif (($schema->additionalProperties ?? true) === false) {
                    return false;
                } elseif (isset($schema->additionalProperties) && \is_object($schema->additionalProperties)
                    && !self::conforms($entry, $schema->additionalProperties, $root)) {
                    return false;
                }
            }
        }
        if (\is_array($value) && isset($schema->items)) {
            foreach ($value as $item) {
                if (!self::conforms($item, $schema->items, $root)) {
                    return false;
                }
            }
        }
        foreach (['anyOf', 'oneOf'] as $keyword) {
            if (isset($schema->{$keyword})) {
                $matches = 0;
                foreach ($schema->{$keyword} as $option) {
                    $matches += (int) self::conforms($value, $option, $root);
                }
                if ($matches < 1 || ($keyword === 'oneOf' && $matches !== 1)) {
                    return false;
                }
            }
        }
        if (isset($schema->not) && self::conforms($value, $schema->not, $root)) {
            return false;
        }
        if (isset($schema->if)) {
            $branch = self::conforms($value, $schema->if, $root) ? 'then' : 'else';
            if (isset($schema->{$branch}) && !self::conforms($value, $schema->{$branch}, $root)) {
                return false;
            }
        }
        return true;
    }

    private static function matchesSchemaPattern(string $pattern, string $value): bool
    {
        $normalized = \preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $match): string => '\\x{' . $match[1] . '}',
            $pattern,
        );
        self::assertIsString($normalized);
        return \preg_match('~' . \str_replace('~', '\\~', $normalized) . '~uD', $value) === 1;
    }
}
