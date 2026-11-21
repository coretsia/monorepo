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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Tools\Support\RepositoryContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SystemRouteTableGateTest extends TestCase
{
    private const string REGISTRY_PATH = 'packages/platform/http/src/System/SystemRouteTable.php';
    private const string SSOT_PATH = 'docs/ssot/system-route-table.md';
    private const string BASELINE_PATH = 'tools/config/system-route-table.json';
    private const string DRIFT = 'CORETSIA_SYSTEM_ROUTE_TABLE_DRIFT';
    private const string FAILED = 'CORETSIA_SYSTEM_ROUTE_TABLE_GATE_FAILED';

    /** @var list<string> */
    private array $tmpRoots = [];

    protected function tearDown(): void
    {
        foreach (\array_reverse($this->tmpRoots) as $tmpRoot) {
            $this->removeDirectory($tmpRoot);
        }

        $this->tmpRoots = [];

        parent::tearDown();
    }

    public function testCanonicalVersionOneFixturePassesWithoutWrites(): void
    {
        $bytes = $this->fixtureBytes();
        $history = $this->fixtureHistory();
        self::assertCount(1, $history);
        self::assertSame(['entries', 'schemaVersion'], \array_keys($history[0]));
        self::assertSame(1, $history[0]['schemaVersion']);
        self::assertCount(9, $history[0]['entries']);
        self::assertSame(Json::encodeStable($history) . "\n", $bytes);

        $root = $this->createFixtureRoot();
        $source = $this->readFile($root . '/' . self::REGISTRY_PATH);
        self::assertStringContainsString("'pattern' => self::ALIVE_PATH,", $source);
        self::assertDirectoryDoesNotExist($root . '/vendor');
        self::assertFileDoesNotExist($root . '/tools/gates/system_route_table_gate.php');
        $before = $this->snapshotTree($root);

        $first = $this->runGate($root);
        $second = $this->runGate($root);
        self::assertSame(['exitCode' => 0, 'stdout' => '', 'stderr' => ''], $first);
        self::assertSame($first, $second);
        self::assertSame($before, $this->snapshotTree($root));
        self::assertSame($bytes, $this->fixtureBytes());
    }

    #[DataProvider('invalidRegistrySourceCases')]
    public function testInspectsFixtureLocalPhpAndRejectsInvalidDeclarations(
        string $search,
        string $replacement,
        string $reason,
    ): void {
        $root = $this->createFixtureRoot();
        $path = $root . '/' . self::REGISTRY_PATH;
        $source = $this->readFile($path);
        $this->writeFile($path, $this->replaceOnce($source, $search, $replacement));

        $this->assertDrift($this->runGate($root), [self::REGISTRY_PATH . ': ' . $reason]);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidRegistrySourceCases(): iterable
    {
        yield 'namespace' => [
            'namespace Coretsia\Platform\Http\System;',
            'namespace Fixture\System;',
            'registry-class-invalid',
        ];
        yield 'class-name' => ['final class SystemRouteTable', 'final class OtherTable', 'registry-class-invalid'];
        yield 'not-final' => ['final class SystemRouteTable', 'class SystemRouteTable', 'registry-class-invalid'];
        yield 'additional-class' => [
            'final class SystemRouteTable',
            "final class ExtraTable {}\n\nfinal class SystemRouteTable",
            'registry-class-invalid',
        ];
        yield 'inheritance' => [
            'final class SystemRouteTable',
            'final class SystemRouteTable extends FixtureParent',
            'registry-class-invalid',
        ];
        yield 'constructor-visibility' => [
            'private function __construct()',
            'public function __construct()',
            'registry-api-invalid',
        ];
        yield 'constructor-parameter' => [
            'private function __construct()',
            'private function __construct(string $value)',
            'registry-api-invalid',
        ];
        yield 'accessor-not-static' => [
            'public static function entries(): array',
            'public function entries(): array',
            'registry-api-invalid',
        ];
        yield 'accessor-not-direct' => ['return self::ENTRIES;', 'return [];', 'registry-api-invalid'];
        yield 'additional-accessor' => [
            'public static function entries(): array',
            "public static function other(): array { return []; }\n\n    public static function entries(): array",
            'registry-api-invalid',
        ];
        yield 'mutable-property' => [
            'public const int SCHEMA_VERSION',
            "private array \$cache = [];\n\n    public const int SCHEMA_VERSION",
            'registry-api-invalid',
        ];
        yield 'entries-visibility' => [
            'private const array ENTRIES',
            'public const array ENTRIES',
            'registry-constant-invalid',
        ];
        yield 'entries-untyped' => [
            'private const array ENTRIES',
            'private const ENTRIES',
            'registry-constant-invalid',
        ];
        yield 'version-untyped' => [
            'public const int SCHEMA_VERSION',
            'public const SCHEMA_VERSION',
            'registry-constant-invalid',
        ];
        yield 'version-zero' => ['SCHEMA_VERSION = 1;', 'SCHEMA_VERSION = 0;', 'registry-version-invalid'];
        yield 'version-expression' => ['SCHEMA_VERSION = 1;', 'SCHEMA_VERSION = 1 + 0;', 'registry-version-invalid'];
        yield 'version-hexadecimal' => ['SCHEMA_VERSION = 1;', 'SCHEMA_VERSION = 0x1;', 'registry-version-invalid'];
        yield 'version-string' => ['SCHEMA_VERSION = 1;', "SCHEMA_VERSION = '1';", 'registry-version-invalid'];
        yield 'alive-path-literal-instead-of-reference' => [
            "'pattern' => self::ALIVE_PATH,",
            "'pattern' => '/_alive',",
            'registry-alive-reference-invalid',
        ];
        yield 'unexpected-field' => [
            "'pattern' => '/metrics',",
            "'pattern' => '/metrics',\n            'extra' => 'unexpected',",
            'registry-entry-shape-invalid',
        ];
        yield 'duplicate-field' => [
            "'pattern' => '/metrics',",
            "'pattern' => '/metrics',\n            'pattern' => '/metrics',",
            'registry-entry-field-duplicate',
        ];
        yield 'missing-field' => ["'owner' => 'platform/metrics',", '', 'registry-entry-shape-invalid'];
        yield 'non-string-field' => ["'pattern' => '/metrics',", "'pattern' => 42,", 'registry-entry-field-invalid'];
        yield 'non-literal-field' => [
            "'pattern' => '/metrics',",
            "'pattern' => '/metric' . 's',",
            'registry-entry-field-invalid',
        ];
        yield 'noncanonical-field-order' => [
            "'owner' => 'platform/metrics',\n            'pattern' => '/metrics',",
            "'pattern' => '/metrics',\n            'owner' => 'platform/metrics',",
            'registry-entry-shape-invalid',
        ];
        yield 'non-array-entry' => [
            self::entrySource([
                'owner' => 'platform/metrics',
                'pattern' => '/metrics',
                'status' => 'reserved',
                'type' => 'exact',
            ]),
            '        42,',
            'registry-entry-shape-invalid',
        ];
    }

    #[DataProvider('invalidEntryCases')]
    public function testRejectsInvalidRegistryEntryPolicy(
        int $index,
        string $field,
        string $value,
        string $reason,
    ): void {
        $root = $this->createFixtureRoot();
        $snapshot = $this->fixtureHistory()[0];
        $snapshot['entries'][$index][$field] = $value;
        $this->writeFile($root . '/' . self::REGISTRY_PATH, self::registrySource($snapshot));

        $this->assertDrift($this->runGate($root), [self::REGISTRY_PATH . ': ' . $reason]);
    }

    /** @return iterable<string, array{int, string, string, string}> */
    public static function invalidEntryCases(): iterable
    {
        yield 'unknown-owner-layer' => [7, 'owner', 'unknown/metrics', 'registry-owner-invalid'];
        yield 'malformed-owner' => [7, 'owner', 'platform/Metrics', 'registry-owner-invalid'];
        yield 'nested-owner' => [7, 'owner', 'platform/metrics/extra', 'registry-owner-invalid'];
        yield 'reserved-core-layer-as-slug' => [7, 'owner', 'core/platform', 'registry-owner-invalid'];
        yield 'status' => [7, 'status', 'enabled', 'registry-status-invalid'];
        yield 'type' => [7, 'type', 'wildcard', 'registry-type-invalid'];
        yield 'root-prefix' => [0, 'type', 'prefix', 'registry-path-invalid'];
        yield 'missing-slash' => [7, 'pattern', 'metrics', 'registry-path-invalid'];
        yield 'uppercase' => [7, 'pattern', '/Metrics', 'registry-path-invalid'];
        yield 'wildcard' => [7, 'pattern', '/metrics/*', 'registry-path-invalid'];
        yield 'placeholder' => [7, 'pattern', '/metrics/{name}', 'registry-path-invalid'];
        yield 'query' => [7, 'pattern', '/metrics?secret=fixture', 'registry-path-invalid'];
        yield 'fragment' => [7, 'pattern', '/metrics#fixture', 'registry-path-invalid'];
        yield 'percent-encoded' => [7, 'pattern', '/%6Detrics', 'registry-path-invalid'];
        yield 'dot-segment' => [7, 'pattern', '/metrics/../ready', 'registry-path-invalid'];
        yield 'empty-segment' => [7, 'pattern', '/metrics//ready', 'registry-path-invalid'];
        yield 'control-character' => [7, 'pattern', "/metrics/\x1F", 'registry-path-invalid'];
        yield 'exact-trailing-slash' => [7, 'pattern', '/metrics/', 'registry-path-invalid'];
        yield 'prefix-missing-trailing-slash' => [6, 'pattern', '/health/ready', 'registry-path-invalid'];
        yield 'duplicate-pattern' => [7, 'pattern', '/health', 'registry-pattern-duplicate'];
        yield 'exact-under-prefix' => [7, 'pattern', '/health/ready', 'registry-pattern-overlap'];
        yield 'noncanonical-order' => [7, 'pattern', '/a', 'registry-order-invalid'];
        yield 'alive-owner' => [1, 'owner', 'platform/health', 'registry-alive-declaration-invalid'];
    }

    public function testRejectsOverlappingPrefixesInEitherDeclarationOrder(): void
    {
        foreach (['/health/ready/', '/_debug/toolbar/'] as $pattern) {
            $root = $this->createFixtureRoot();
            $snapshot = $this->fixtureHistory()[0];
            $snapshot['entries'][7]['pattern'] = $pattern;
            $snapshot['entries'][7]['type'] = 'prefix';
            $this->writeFile($root . '/' . self::REGISTRY_PATH, self::registrySource($snapshot));

            $this->assertDrift($this->runGate($root), [self::REGISTRY_PATH . ': registry-pattern-overlap']);
        }

        $root = $this->createFixtureRoot();
        $snapshot = $this->fixtureHistory()[0];
        $snapshot['entries'][5]['pattern'] = '/health/ready';
        $snapshot['entries'][5]['type'] = 'exact';
        $this->writeFile($root . '/' . self::REGISTRY_PATH, self::registrySource($snapshot));

        $this->assertDrift($this->runGate($root), [self::REGISTRY_PATH . ': registry-pattern-overlap']);

        $root = $this->createFixtureRoot();
        $snapshot['entries'][5]['pattern'] = '/health/ready/';
        $snapshot['entries'][5]['type'] = 'prefix';
        $this->writeFile($root . '/' . self::REGISTRY_PATH, self::registrySource($snapshot));

        $this->assertDrift($this->runGate($root), [self::REGISTRY_PATH . ': registry-pattern-overlap']);
    }

    public function testAcceptsAlignedNormativeMarkdownTable(): void
    {
        $root = $this->createFixtureRoot();
        $path = $root . '/' . self::SSOT_PATH;

        $this->writeFile(
            $path,
            $this->replaceOnce(
                $this->readFile($path),
                "| pattern | type | status | owner |\n|---|---|---|---|",
                "| pattern    | type     | status     | owner              |\n"
                . '|------------|----------|------------|--------------------|',
            ),
        );

        $before = $this->snapshotTree($root);

        self::assertSame(['exitCode' => 0, 'stdout' => '', 'stderr' => ''], $this->runGate($root));
        self::assertSame($before, $this->snapshotTree($root));
    }

    #[DataProvider('invalidSsotCases')]
    public function testRejectsInvalidNormativeMarkdown(
        string $search,
        string $replacement,
        string $reason,
    ): void {
        $root = $this->createFixtureRoot();
        $path = $root . '/' . self::SSOT_PATH;
        $this->writeFile($path, $this->replaceOnce($this->readFile($path), $search, $replacement));

        $this->assertDrift($this->runGate($root), [self::SSOT_PATH . ': ' . $reason]);
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function invalidSsotCases(): iterable
    {
        $heading = '## System Route Registry (MUST)';
        $version = 'Schema version: `1`';
        $header = '| pattern | type | status | owner |';
        $row = '| `/metrics` | `exact` | `reserved` | `platform/metrics` |';

        yield 'missing-normative-section' => [$heading, '## Registry example', 'ssot-registry-section-invalid'];
        yield 'duplicate-normative-section' => [
            $heading,
            $heading . "\n\n" . $heading,
            'ssot-registry-section-invalid',
        ];
        yield 'missing-version' => [$version, '', 'ssot-version-invalid'];
        yield 'malformed-version' => [$version, 'Schema version: 1', 'ssot-version-invalid'];
        yield 'nonpositive-version' => [$version, 'Schema version: `0`', 'ssot-version-invalid'];
        yield 'duplicate-version' => [$version, $version . "\n" . $version, 'ssot-registry-table-invalid'];
        yield 'version-drift' => [$version, 'Schema version: `2`', 'ssot-registry-drift'];
        yield 'fenced-version' => [$version, "```text\n" . $version . "\n```", 'ssot-version-invalid'];
        yield 'missing-table-header' => [$header, '', 'ssot-registry-table-invalid'];
        yield 'reordered-table-header' => [
            $header,
            '| type | pattern | status | owner |',
            'ssot-registry-table-invalid',
        ];
        yield 'malformed-separator' => ['|---|---|---|---|', '|---|---|---|', 'ssot-registry-table-invalid'];
        yield 'short-separator' => [
            '|---|---|---|---|',
            '|---|--|---|---|',
            'ssot-registry-table-invalid',
        ];
        yield 'extra-separator-column' => [
            '|---|---|---|---|',
            '|---|---|---|---|---|',
            'ssot-registry-table-invalid',
        ];
        yield 'extra-table-header-column' => [
            $header,
            '| pattern | type | status | owner | description |',
            'ssot-registry-table-invalid',
        ];
        yield 'fenced-table' => [$header, "~~~markdown\n" . $header, 'ssot-registry-table-invalid'];
        yield 'malformed-row' => [
            $row,
            '| /metrics | exact | reserved | platform/metrics |',
            'ssot-registry-row-invalid',
        ];
        yield 'duplicate-row' => [$row, $row . "\n" . $row, 'registry-pattern-duplicate'];
        yield 'missing-row' => [$row . "\n", '', 'ssot-registry-drift'];
        yield 'owner-drift' => [
            $row,
            '| `/metrics` | `exact` | `reserved` | `platform/health` |',
            'ssot-registry-drift',
        ];
        yield 'row-type-invalid' => [
            $row,
            '| `/metrics` | `wildcard` | `reserved` | `platform/metrics` |',
            'registry-type-invalid',
        ];
        yield 'second-table-header' => [$row, $row . "\n" . $header, 'ssot-registry-row-invalid'];
        yield 'unexpected-prose' => [$row, $row . "\nUnexpected policy prose.", 'ssot-registry-row-invalid'];
        yield 'unexpected-nested-heading' => [$row, $row . "\n### Nested section", 'ssot-registry-row-invalid'];
    }

    public function testFencedExamplesCannotSatisfyNormativeSectionButDoNotCountAsDuplicates(): void
    {
        $root = $this->createFixtureRoot();
        $path = $root . '/' . self::SSOT_PATH;
        $ssot = $this->readFile($path);
        $example = "```markdown\n" . $ssot . "```\n";

        $this->writeFile($path, $example);
        $this->assertDrift($this->runGate($root), [self::SSOT_PATH . ': ssot-registry-section-invalid']);

        $tableStart = \strpos($ssot, '| pattern | type | status | owner |');
        self::assertIsInt($tableStart);

        $fencedTable = \substr($ssot, 0, $tableStart)
            . "~~~markdown\n"
            . \substr($ssot, $tableStart)
            . "~~~\n";

        $this->writeFile($path, $fencedTable);
        $this->assertDrift($this->runGate($root), [self::SSOT_PATH . ': ssot-registry-table-invalid']);

        $this->writeFile($path, $example . "\n" . $ssot);
        self::assertSame(['exitCode' => 0, 'stdout' => '', 'stderr' => ''], $this->runGate($root));
    }

    #[DataProvider('invalidBaselineCases')]
    public function testRejectsInvalidBaselineBytesOrHistory(string $variant, string $reason): void
    {
        $root = $this->createFixtureRoot();
        $history = $this->fixtureHistory();
        $bytes = $this->fixtureBytes();
        $snapshot = $history[0];
        $invalid = match ($variant) {
            'malformed' => "{\n",
            'empty' => "[]\n",
            'object' => Json::encodeStable($snapshot) . "\n",
            'no-final-lf' => \rtrim($bytes, "\n"),
            'extra-final-lf' => $bytes . "\n",
            'crlf' => \str_replace("\n", "\r\n", $bytes),
            'leading-space' => ' ' . $bytes,
            'duplicate-key' => $this->replaceOnce($bytes, '"schemaVersion":1}', '"schemaVersion":1,"schemaVersion":1}'),
            'duplicate-entry-key' => $this->replaceOnce(
                $bytes,
                '"owner":"platform/metrics"',
                '"owner":"platform/metrics","owner":"platform/metrics"',
            ),
            'noncanonical-map-order' => \json_encode(
                [['schemaVersion' => 1, 'entries' => $snapshot['entries']]],
                \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR,
            ) . "\n",
            'missing-version' => Json::encodeStable([['entries' => $snapshot['entries']]]) . "\n",
            'missing-entries' => Json::encodeStable([['schemaVersion' => 1]]) . "\n",
            'scalar-snapshot' => Json::encodeStable([1]) . "\n",
            'unexpected-field' => Json::encodeStable([$snapshot + ['unexpected' => true]]) . "\n",
            'string-version' => Json::encodeStable([
                    ['entries' => $snapshot['entries'], 'schemaVersion' => '1'],
                ]) . "\n",
            'zero-version' => Json::encodeStable([['entries' => $snapshot['entries'], 'schemaVersion' => 0]]) . "\n",
            'empty-entries' => Json::encodeStable([['entries' => [], 'schemaVersion' => 1]]) . "\n",
            'non-list-entries' => Json::encodeStable([
                    ['entries' => $snapshot['entries'][0], 'schemaVersion' => 1],
                ]) . "\n",
            'unexpected-entry-field' => $this->baselineWithChangedEntry($history, 'extra', true),
            'non-string-entry-field' => $this->baselineWithChangedEntry($history, 'owner', 42),
            'entry-order' => Json::encodeStable([
                    ['entries' => \array_reverse($snapshot['entries']), 'schemaVersion' => 1],
                ]) . "\n",
            'float-version' => $this->replaceOnce($bytes, '"schemaVersion":1}', '"schemaVersion":1.0}'),
        };
        $this->writeFile($root . '/' . self::BASELINE_PATH, $invalid);

        $this->assertDrift($this->runGate($root), [self::BASELINE_PATH . ': ' . $reason]);
        self::assertSame($bytes, $this->fixtureBytes());
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidBaselineCases(): iterable
    {
        yield 'malformed-json' => ['malformed', 'baseline-json-invalid'];
        yield 'empty-history' => ['empty', 'baseline-shape-invalid'];
        yield 'object-instead-of-history' => ['object', 'baseline-shape-invalid'];
        yield 'missing-final-lf' => ['no-final-lf', 'baseline-noncanonical'];
        yield 'extra-final-lf' => ['extra-final-lf', 'baseline-noncanonical'];
        yield 'crlf' => ['crlf', 'baseline-noncanonical'];
        yield 'leading-space' => ['leading-space', 'baseline-noncanonical'];
        yield 'duplicate-snapshot-key' => ['duplicate-key', 'baseline-noncanonical'];
        yield 'duplicate-entry-key' => ['duplicate-entry-key', 'baseline-noncanonical'];
        yield 'noncanonical-map-order' => ['noncanonical-map-order', 'baseline-noncanonical'];
        yield 'missing-version' => ['missing-version', 'baseline-snapshot-invalid'];
        yield 'missing-entries' => ['missing-entries', 'baseline-snapshot-invalid'];
        yield 'scalar-snapshot' => ['scalar-snapshot', 'baseline-snapshot-invalid'];
        yield 'unexpected-snapshot-field' => ['unexpected-field', 'baseline-snapshot-invalid'];
        yield 'version-string' => ['string-version', 'baseline-snapshot-invalid'];
        yield 'version-zero' => ['zero-version', 'baseline-snapshot-invalid'];
        yield 'empty-entries' => ['empty-entries', 'registry-entries-invalid'];
        yield 'non-list-entries' => ['non-list-entries', 'registry-entries-invalid'];
        yield 'unexpected-entry-field' => ['unexpected-entry-field', 'registry-entry-shape-invalid'];
        yield 'non-string-entry-field' => ['non-string-entry-field', 'registry-entry-field-invalid'];
        yield 'entry-list-order' => ['entry-order', 'registry-order-invalid'];
        yield 'float-version' => ['float-version', 'baseline-noncanonical'];
    }

    public function testMissingBaselineProducesOnlyTheCanonicalGateFailure(): void
    {
        $root = $this->createFixtureRoot();
        self::assertTrue(\unlink($root . '/' . self::BASELINE_PATH));

        $this->assertFailure($this->runGate($root), [self::FAILED]);
    }

    public function testAlignedRegistryChangesRequireLocalBaselineConsistency(): void
    {
        $fixtureBytes = $this->fixtureBytes();
        $history = $this->fixtureHistory();
        $changed = $this->nextSnapshot($history[0]);
        $changed['schemaVersion'] = 1;

        $root = $this->createFixtureRoot($changed, $history);
        $this->assertDrift($this->runGate($root), [
            self::BASELINE_PATH . ': baseline-registry-drift',
        ]);

        $root = $this->createFixtureRoot($changed, [$changed]);
        $before = $this->snapshotTree($root);

        $first = $this->runGate($root);
        $second = $this->runGate($root);

        self::assertSame(['exitCode' => 0, 'stdout' => '', 'stderr' => ''], $first);
        self::assertSame($first, $second);
        self::assertSame($before, $this->snapshotTree($root));

        $root = $this->createFixtureRoot($changed, [...$history, $changed]);
        $this->assertDrift($this->runGate($root), [
            self::BASELINE_PATH . ': baseline-version-sequence-invalid',
        ]);

        $changed['schemaVersion'] = 2;
        $root = $this->createFixtureRoot($changed, $history);
        $this->assertDrift($this->runGate($root), [
            self::BASELINE_PATH . ': baseline-registry-drift',
        ]);

        $root = $this->createFixtureRoot($changed, [...$history, $changed]);
        self::assertSame(['exitCode' => 0, 'stdout' => '', 'stderr' => ''], $this->runGate($root));

        self::assertSame($fixtureBytes, $this->fixtureBytes());
    }

    #[DataProvider('invalidVersionTransitions')]
    public function testRejectsInvalidVersionTransitions(string $variant, string $reason): void
    {
        $history = $this->fixtureHistory();
        $initial = $history[0];
        $next = $this->nextSnapshot($initial);
        $current = $next;

        switch ($variant) {
            case 'regression':
                $current = $initial;
                break;
            case 'skip':
                $next['schemaVersion'] = 3;
                $current = $next;
                break;
            case 'duplicate':
                $next['schemaVersion'] = 1;
                $current = $next;
                break;
            case 'unchanged':
                $next['entries'] = $initial['entries'];
                $current = $next;
                break;
            case 'missing-initial':
                $history = [];
                break;
            case 'invalid-historical-entry':
                $history[0]['entries'][7]['status'] = 'enabled';
                break;
            case 'invalid-historical-envelope':
                $history[0]['unexpected'] = true;
                break;
            case 'historical-duplicate':
                $history[] = $initial;
                $next['schemaVersion'] = 3;
                $current = $next;
                break;
            case 'historical-unchanged':
                $unchanged = $initial;
                $unchanged['schemaVersion'] = 2;
                $history[] = $unchanged;
                $next['schemaVersion'] = 3;
                $current = $next;
                break;
        }

        $root = $this->createFixtureRoot($current, [...$history, $next]);
        $this->assertDrift($this->runGate($root), [self::BASELINE_PATH . ': ' . $reason]);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidVersionTransitions(): iterable
    {
        yield 'current-version-regression' => ['regression', 'baseline-version-regression'];
        yield 'skipped-version' => ['skip', 'baseline-version-sequence-invalid'];
        yield 'duplicate-version' => ['duplicate', 'baseline-version-sequence-invalid'];
        yield 'bump-without-change' => ['unchanged', 'baseline-version-unchanged'];
        yield 'missing-initial-version' => ['missing-initial', 'baseline-version-sequence-invalid'];
        yield 'invalid-historical-entry' => ['invalid-historical-entry', 'registry-status-invalid'];
        yield 'invalid-historical-envelope' => ['invalid-historical-envelope', 'baseline-snapshot-invalid'];
        yield 'historical-duplicate-version' => ['historical-duplicate', 'baseline-version-sequence-invalid'];
        yield 'historical-bump-without-change' => ['historical-unchanged', 'baseline-version-unchanged'];
    }

    public function testCanonicalAliveIdentifierCannotBeChangedByAnAlignedVersionUpdate(): void
    {
        $history = $this->fixtureHistory();
        $next = $history[0];
        $next['schemaVersion'] = 2;
        $next['entries'][1]['pattern'] = '/_alive2';
        $root = $this->createFixtureRoot($next, [...$history, $next], '/_alive2');

        $this->assertDrift($this->runGate($root), [
            self::SSOT_PATH . ': registry-alive-declaration-invalid',
            self::REGISTRY_PATH . ': registry-alive-path-invalid',
            self::BASELINE_PATH . ': registry-alive-declaration-invalid',
        ]);
    }

    #[DataProvider('invalidDebugDeclarations')]
    public function testMandatoryDebugReservationsCannotBeRemovedOrActivated(
        int $index,
        ?string $status,
    ): void {
        $history = $this->fixtureHistory();
        $next = $history[0];
        $next['schemaVersion'] = 2;
        if ($status === null) {
            \array_splice($next['entries'], $index, 1);
        } else {
            $next['entries'][$index]['status'] = $status;
        }

        $root = $this->createFixtureRoot($next, [...$history, $next]);

        $this->assertDrift($this->runGate($root), [
            self::SSOT_PATH . ': registry-debug-declaration-invalid',
            self::REGISTRY_PATH . ': registry-debug-declaration-invalid',
            self::BASELINE_PATH . ': registry-debug-declaration-invalid',
        ]);
    }

    /** @return iterable<string, array{int, ?string}> */
    public static function invalidDebugDeclarations(): iterable
    {
        yield 'missing-exact' => [2, null];
        yield 'missing-prefix' => [3, null];
        yield 'exact-active' => [2, 'active'];
        yield 'prefix-active' => [3, 'active'];
        yield 'exact-reserved' => [2, 'reserved'];
        yield 'prefix-reserved' => [3, 'reserved'];
    }

    #[DataProvider('invalidArguments')]
    public function testInvalidGateArgumentsProduceOnlyTheCanonicalFailure(array $arguments): void
    {
        $root = $this->createFixtureRoot();
        $arguments = \array_map(
            static fn (string $argument): string => \str_replace('{root}', $root, $argument),
            $arguments,
        );
        $this->assertFailure($this->runGate($root, $arguments), [self::FAILED]);
    }

    /** @return iterable<string, array{list<string>}> */
    public static function invalidArguments(): iterable
    {
        yield 'bare-root-option' => [['--root']];
        yield 'separate-root-value' => [['--root', '{root}']];
        yield 'empty-root' => [['--root=']];
        yield 'duplicate-root' => [['--root={root}', '--root={root}']];
        yield 'unsupported-option' => [['--root={root}', '--unknown=fixture']];
        yield 'positional-argument' => [['--root={root}', 'fixture']];
        yield 'missing-root-directory' => [['--root={root}/missing']];
        yield 'missing-repository-markers' => [['--root={root}/docs']];
    }

    public function testExecutableAndMalformedFixturePhpIsNeverExecutedOrDisclosed(): void
    {
        $root = $this->createFixtureRoot();
        $path = $root . '/' . self::REGISTRY_PATH;
        $source = $this->readFile($path);
        $marker = \dirname($path) . '/executed.txt';
        $payload = <<<'PHP'
\file_put_contents(__DIR__ . '/executed.txt', 'fixture-registry-secret');
throw new \RuntimeException('fixture-registry-secret');
PHP;
        $executable = $this->replaceOnce(
            $source,
            'final class SystemRouteTable',
            $payload . "\n\nfinal class SystemRouteTable",
        );
        $this->writeFile($path, $executable);

        $result = $this->runGate($root);
        $this->assertDrift($result, [self::REGISTRY_PATH . ': registry-class-invalid']);
        self::assertFileDoesNotExist($marker);
        self::assertStringNotContainsString('fixture-registry-secret', $result['stderr']);

        $this->writeFile(
            $path,
            \rtrim($source, "\n") . "\n\n" . $payload . "\n",
        );

        $result = $this->runGate($root);
        $this->assertDrift($result, [self::REGISTRY_PATH . ': registry-class-invalid']);
        self::assertFileDoesNotExist($marker);
        self::assertStringNotContainsString('fixture-registry-secret', $result['stderr']);
        self::assertStringNotContainsString($root, $result['stderr']);

        $invalid = $this->replaceOnce($executable, 'return self::ENTRIES;', 'return self::ENTRIES');
        $this->writeFile($path, $invalid);
        $result = $this->runGate($root);
        $this->assertFailure($result, [self::FAILED]);
        self::assertFileDoesNotExist($marker);
        self::assertStringNotContainsString('fixture-registry-secret', $result['stderr']);
        self::assertStringNotContainsString($root, $result['stderr']);
    }

    public function testFailureDiagnosticsAreSortedRelativeDeterministicAndReadOnly(): void
    {
        $root = $this->createFixtureRoot();
        $registry = $root . '/' . self::REGISTRY_PATH;
        $ssot = $root . '/' . self::SSOT_PATH;
        $this->writeFile(
            $registry,
            $this->replaceOnce(
                $this->readFile($registry),
                'return self::ENTRIES;',
                'return [];',
            ),
        );
        $this->writeFile(
            $ssot,
            $this->replaceOnce(
                $this->readFile($ssot),
                'Schema version: `1`',
                'Schema version: fixture-secret',
            ),
        );
        $this->writeFile($root . '/' . self::BASELINE_PATH, "{fixture-secret\n");
        $before = $this->snapshotTree($root);

        $first = $this->runGate($root);
        $second = $this->runGate($root);
        $this->assertDrift($first, [
            self::SSOT_PATH . ': ssot-version-invalid',
            self::REGISTRY_PATH . ': registry-api-invalid',
            self::BASELINE_PATH . ': baseline-json-invalid',
        ]);
        self::assertSame($first, $second);
        self::assertSame($before, $this->snapshotTree($root));
        self::assertStringNotContainsString($root, $first['stderr']);
        self::assertStringNotContainsString('\\', $first['stderr']);
        self::assertStringNotContainsString('fixture-secret', $first['stderr']);
        self::assertStringNotContainsString('Stack trace', $first['stderr']);
    }

    public function testComposerRegistersTheCanonicalGateImmediatelyAfterReservedTags(): void
    {
        $composer = \json_decode(
            $this->readFile($this->repoRoot() . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        $scripts = $composer['scripts'];
        self::assertSame('@php tools/gates/system_route_table_gate.php', $scripts['system-route-table:gate']);
        $gates = $scripts['gates'];
        $reservedTags = \array_search('@reserved-tags:gate', $gates, true);
        self::assertIsInt($reservedTags);
        self::assertSame('@system-route-table:gate', $gates[$reservedTags + 1]);
        self::assertSame(1, \count(\array_keys($gates, '@system-route-table:gate', true)));
        self::assertContains('@gates', $scripts['ci']);
    }

    /**
     * @param array{exitCode:int,stdout:string,stderr:string} $result
     * @param list<string> $diagnostics
     */
    private function assertDrift(array $result, array $diagnostics): void
    {
        $sorted = $diagnostics;
        \sort($sorted, \SORT_STRING);
        self::assertSame($sorted, $diagnostics);
        $this->assertFailure($result, [self::DRIFT, ...$diagnostics]);
    }

    /**
     * @param array{exitCode:int,stdout:string,stderr:string} $result
     * @param list<string> $expectedLines
     */
    private function assertFailure(array $result, array $expectedLines): void
    {
        self::assertSame(1, $result['exitCode']);
        self::assertSame('', $result['stdout']);
        self::assertSame(\implode("\n", $expectedLines) . "\n", $result['stderr']);
    }

    private function fixtureBytes(): string
    {
        return $this->readFile(__DIR__ . '/../Fixtures/SystemRouteTable/baseline.json');
    }

    /**
     * @return list<array{entries:list<array{owner:string,pattern:string,status:string,type:string}>,schemaVersion:int}>
     */
    private function fixtureHistory(): array
    {
        $history = \json_decode($this->fixtureBytes(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($history);
        self::assertTrue(\array_is_list($history));
        self::assertCount(1, $history);
        self::assertSame(1, $history[0]['schemaVersion']);

        return $history;
    }

    private function nextSnapshot(array $initial): array
    {
        $initial['schemaVersion']++;
        $initial['entries'][] = [
            'owner' => 'platform/future-health',
            'pattern' => '/status',
            'status' => 'reserved',
            'type' => 'exact',
        ];

        return $initial;
    }

    private function baselineWithChangedEntry(array $history, string $field, mixed $value): string
    {
        $history[0]['entries'][7][$field] = $value;

        return Json::encodeStable($history) . "\n";
    }

    private function createFixtureRoot(
        ?array $snapshot = null,
        ?array $history = null,
        string $alivePath = '/_alive',
    ): string {
        $root = $this->newTemporaryDirectory();
        $snapshot ??= $this->fixtureHistory()[0];

        $this->writeFile(
            $root . '/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/system-route-table-fixture\",\n"
            . "  \"type\": \"project\"\n"
            . "}\n",
        );
        $this->writeFile($root . '/' . self::REGISTRY_PATH, self::registrySource($snapshot, $alivePath));
        $this->writeFile($root . '/' . self::SSOT_PATH, self::ssotSource($snapshot));
        $this->writeFile(
            $root . '/' . self::BASELINE_PATH,
            $history === null ? $this->fixtureBytes() : Json::encodeStable($history) . "\n",
        );

        return $root;
    }

    private static function registrySource(array $snapshot, string $alivePath = '/_alive'): string
    {
        $rows = [];
        foreach ($snapshot['entries'] as $entry) {
            $rows[] = self::entrySource($entry, $alivePath);
        }

        return \sprintf(
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Platform\Http\System;

final class SystemRouteTable
{
    public const int SCHEMA_VERSION = %d;
    public const string ALIVE_PATH = %s;
    private const array ENTRIES = [
%s
    ];

    private function __construct()
    {
    }

    public static function entries(): array
    {
        return self::ENTRIES;
    }
}

PHP,
            $snapshot['schemaVersion'],
            \var_export($alivePath, true),
            \implode("\n", $rows),
        );
    }

    private static function entrySource(array $entry, string $alivePath = '/_alive'): string
    {
        $fields = ['        ['];
        foreach ($entry as $field => $value) {
            $literal = $field === 'pattern' && $value === $alivePath
                ? 'self::ALIVE_PATH'
                : \var_export($value, true);
            $fields[] = '            ' . \var_export($field, true) . ' => ' . $literal . ',';
        }
        $fields[] = '        ],';

        return \implode("\n", $fields);
    }

    private static function ssotSource(array $snapshot): string
    {
        $lines = [
            '# System Route Table',
            '',
            '## System Route Registry (MUST)',
            '',
            'Schema version: `' . $snapshot['schemaVersion'] . '`',
            '',
            '| pattern | type | status | owner |',
            '|---|---|---|---|',
        ];
        foreach ($snapshot['entries'] as $entry) {
            $lines[] = \sprintf(
                '| `%s` | `%s` | `%s` | `%s` |',
                $entry['pattern'],
                $entry['type'],
                $entry['status'],
                $entry['owner'],
            );
        }

        return \implode("\n", $lines) . "\n";
    }

    private function replaceOnce(string $source, string $search, string $replacement): string
    {
        $result = \str_replace($search, $replacement, $source, $count);
        self::assertSame(1, $count);

        return $result;
    }

    /**
     * @param list<string>|null $arguments
     * @return array{exitCode:int,stdout:string,stderr:string}
     */
    private function runGate(string $root, ?array $arguments = null): array
    {
        $command = [
            \PHP_BINARY,
            $this->repoRoot() . '/tools/gates/system_route_table_gate.php',
            ...($arguments ?? ['--root=' . $root]),
        ];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = \proc_open($command, $descriptorSpec, $pipes, $this->repoRoot());
        if (!\is_resource($process)) {
            self::fail('Failed to start system route table gate process.');
        }

        \fclose($pipes[0]);
        $stdout = \stream_get_contents($pipes[1]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        return [
            'exitCode' => $exitCode,
            'stdout' => $this->normalizeNewlines($stdout),
            'stderr' => $this->normalizeNewlines($stderr),
        ];
    }

    private function repoRoot(): string
    {
        return RepositoryContext::discoverFrom(__DIR__)->repoRoot();
    }

    private function newTemporaryDirectory(): string
    {
        $path = \rtrim(\sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'coretsia-system-route-table-gate-'
            . \bin2hex(\random_bytes(8));

        self::assertTrue(\mkdir($path, 0777, true));
        $path = $this->normalizePath($path);
        $this->tmpRoots[] = $path;

        return $path;
    }

    private function writeFile(string $path, string $contents): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir) && !\mkdir($dir, 0777, true) && !\is_dir($dir)) {
            self::fail('Failed to create fixture directory: ' . $dir);
        }

        self::assertSame(\strlen($contents), \file_put_contents($path, $contents));
    }

    private function readFile(string $path): string
    {
        $contents = \file_get_contents($path);
        self::assertIsString($contents);

        return $contents;
    }

    /** @return array<string, string|null> */
    private function snapshotTree(string $root): array
    {
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $file) {
            self::assertInstanceOf(\SplFileInfo::class, $file);
            $path = $this->normalizePath($file->getPathname());
            $relative = \substr($path, \strlen($root) + 1);
            $snapshot[$relative] = $file->isDir() ? null : $this->readFile($path);
        }
        \ksort($snapshot, \SORT_STRING);

        return $snapshot;
    }

    private function removeDirectory(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo) {
                continue;
            }
            if ($fileInfo->isDir() && !$fileInfo->isLink()) {
                \rmdir($fileInfo->getPathname());
                continue;
            }

            \unlink($fileInfo->getPathname());
        }

        \rmdir($path);
    }

    private function normalizePath(string $path): string
    {
        return \rtrim(\str_replace('\\', '/', $path), '/');
    }

    private function normalizeNewlines(string $value): string
    {
        return \str_replace(["\r\n", "\r"], "\n", $value);
    }
}
