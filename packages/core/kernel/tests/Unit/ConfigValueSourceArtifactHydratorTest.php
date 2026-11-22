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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Config\Source\ConfigValueSourceArtifactHydrator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigValueSourceArtifactHydratorTest extends TestCase
{
    public function testHydratesRequiredFieldsForEveryCanonicalSourceType(): void
    {
        self::assertSame([], ConfigValueSourceArtifactHydrator::hydrate([]));

        foreach (ConfigSourceType::cases() as $type) {
            $entry = self::entry();
            $entry['type'] = $type->value;
            [$source] = ConfigValueSourceArtifactHydrator::hydrate([$entry]);

            self::assertInstanceOf(ConfigValueSource::class, $source);
            self::assertSame(1, $source->schemaVersion());
            self::assertSame($type, $source->type());
            self::assertSame('http', $source->root());
            self::assertSame('application/config/http.php', $source->sourceId());
            self::assertSame(20, $source->precedence());
            self::assertFalse($source->isRedacted());
            self::assertNull($source->path());
            self::assertNull($source->keyPath());
            self::assertNull($source->directive());
            self::assertSame([], $source->meta());
        }
    }

    public function testHydratesOptionalFieldsAndNormalizesMetadataWithoutReorderingSources(): void
    {
        $first = [
            ...self::entry(),
            'path' => 'config/http.php',
            'keyPath' => 'http.debug.ips[1]',
            'directive' => 'append',
            'redacted' => true,
            'meta' => [
                'sourceOrder' => 5,
                'moduleId' => 'platform.http',
                'details' => ['z' => [2, 1], 'a' => true],
            ],
        ];
        $second = [
            ...self::entry(),
            'sourceId' => 'package/http',
            'type' => ConfigSourceType::PackageDefault->value,
            'precedence' => 0,
        ];
        $sources = ConfigValueSourceArtifactHydrator::hydrate([$first, $second]);
        self::assertCount(2, $sources);
        self::assertSame('config/http.php', $sources[0]->path());
        self::assertSame('http.debug.ips[1]', $sources[0]->keyPath());
        self::assertSame('append', $sources[0]->directive());
        self::assertTrue($sources[0]->isRedacted());
        self::assertSame([
            'details' => ['a' => true, 'z' => [2, 1]],
            'moduleId' => 'platform.http',
            'sourceOrder' => 5,
        ], $sources[0]->meta());
        self::assertSame('package/http', $sources[1]->sourceId());
        self::assertSame(0, $sources[1]->precedence());

        $expected = \array_map(static fn (ConfigValueSource $source): array => $source->toArray(), $sources);
        $reordered = ConfigValueSourceArtifactHydrator::hydrate([
            \array_reverse($first, true),
            \array_reverse($second, true),
        ]);
        self::assertSame(
            $expected,
            \array_map(static fn (ConfigValueSource $source): array => $source->toArray(), $reordered),
        );
        self::assertSame(
            \array_reverse($expected),
            \array_map(
                static fn (ConfigValueSource $source): array => $source->toArray(),
                ConfigValueSourceArtifactHydrator::hydrate([$second, $first]),
            ),
        );
    }

    #[DataProvider('invalidSources')]
    public function testRejectsMalformedOrUnsafeSourceRecords(mixed $sources): void
    {
        try {
            ConfigValueSourceArtifactHydrator::hydrate($sources);
            self::fail('Expected invalid artifact source metadata.');
        } catch (ArtifactInvalidException $exception) {
            self::assertSame(ArtifactInvalidException::REASON_SCHEMA_INVALID, $exception->reason());
            self::assertSame(
                ArtifactInvalidException::ERROR_CODE . ': ' . ArtifactInvalidException::REASON_SCHEMA_INVALID,
                $exception->getMessage(),
            );
            self::assertStringNotContainsString('PRIVATE_MARKER', $exception->getMessage());
        }
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidSources(): iterable
    {
        $entry = self::entry();
        yield 'non-list' => [['source' => $entry]];
        yield 'non-array' => [null];
        yield 'scalar-record' => [[42]];
        yield 'list-record' => [[['http']]];
        foreach (\array_keys($entry) as $key) {
            $missing = $entry;
            unset($missing[$key]);
            yield 'missing-' . $key => [[$missing]];
        }

        foreach (
            [
                'unknown-type' => ['type' => 'unsupported'],
                'schema-version' => ['schemaVersion' => 2],
                'precedence-negative' => ['precedence' => -1],
                'precedence-string' => ['precedence' => '20'],
                'redacted-string' => ['redacted' => 'false'],
                'invalid-root' => ['root' => 'Http'],
                'empty-id' => ['sourceId' => ''],
                'absolute-id' => ['sourceId' => '/PRIVATE_MARKER'],
                'traversal-id' => ['sourceId' => 'config/../PRIVATE_MARKER'],
                'empty-path' => ['path' => ''],
                'absolute-path' => ['path' => 'C:\\PRIVATE_MARKER'],
                'null-path' => ['path' => null],
                'null-key-path' => ['keyPath' => null],
                'invalid-key-path' => ['keyPath' => 'http..debug'],
                'null-directive' => ['directive' => null],
                'unsupported-directive' => ['directive' => 'unsupported'],
                'unknown-field' => ['unexpected' => true],
                'raw-field' => ['value' => 'PRIVATE_MARKER'],
                'meta-list' => ['meta' => ['PRIVATE_MARKER']],
                'meta-null' => ['meta' => null],
                'meta-order' => ['meta' => ['sourceOrder' => -1]],
                'meta-order-type' => ['meta' => ['sourceOrder' => '1']],
                'meta-raw' => ['meta' => ['rawValue' => 'PRIVATE_MARKER']],
                'meta-nested-secret' => ['meta' => ['details' => ['password' => 'PRIVATE_MARKER']]],
                'meta-absolute-path' => ['meta' => ['details' => ['/PRIVATE_MARKER']]],
                'meta-url' => ['meta' => ['details' => 'https://PRIVATE_MARKER']],
                'meta-control' => ['meta' => ['details' => "PRIVATE_MARKER\n"]],
                'meta-float' => ['meta' => ['details' => 1.5]],
            ] as $case => $override
        ) {
            yield $case => [[\array_replace($entry, $override)]];
        }
    }

    /** @return array<string, mixed> */
    private static function entry(): array
    {
        return [
            'precedence' => 20,
            'redacted' => false,
            'root' => 'http',
            'schemaVersion' => 1,
            'sourceId' => 'application/config/http.php',
            'type' => ConfigSourceType::ApplicationConfig->value,
        ];
    }
}
