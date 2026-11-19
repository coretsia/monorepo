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

use Coretsia\Devtools\InternalToolkit\Json;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\GateRuntime;
use Coretsia\Tools\Support\MarkdownSectionReader;
use Coretsia\Tools\Support\PhpTokenStream;
use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Support\WorkspacePackageCatalog;

require_once __DIR__ . '/../support/GateRuntime.php';

exit(
    GateRuntime::execute(
        'CORETSIA_SYSTEM_ROUTE_TABLE_DRIFT',
        'CORETSIA_SYSTEM_ROUTE_TABLE_GATE_FAILED',
        static function (RepositoryContext $repository): array {
            return GateRuntime::withSuppressedErrors(
                static fn (): array => coretsia_system_route_table_gate_scan(
                    $repository,
                    isset($_SERVER['argv']) && is_array($_SERVER['argv'])
                        ? $_SERVER['argv']
                        : [],
                ),
            );
        },
    )
);

/**
 * @param list<mixed> $argv
 * @return list<string>
 */
function coretsia_system_route_table_gate_scan(RepositoryContext $repository, array $argv): array
{
    $repository = coretsia_system_route_table_gate_resolve_repository($repository, $argv);

    $registryPath = 'packages/platform/http/src/System/SystemRouteTable.php';
    $ssotPath = 'docs/ssot/system-route-table.md';
    $baselinePath = 'tools/config/system-route-table.json';

    $registry = coretsia_system_route_table_gate_read_registry(
        PhpTokenStream::fromParsedFile($repository->resolveExistingFile($registryPath)),
    );
    $ssot = coretsia_system_route_table_gate_read_ssot(
        DeterministicFile::readTextNormalizedEol($repository->resolveExistingFile($ssotPath)),
    );
    $baseline = coretsia_system_route_table_gate_read_baseline(
        DeterministicFile::readBytesExact($repository->resolveExistingFile($baselinePath)),
    );

    $violations = [];

    if (is_string($registry)) {
        $violations[] = $registryPath . ': ' . $registry;
    }

    if (is_string($ssot)) {
        $violations[] = $ssotPath . ': ' . $ssot;
    } elseif (is_array($registry) && $ssot !== $registry) {
        $violations[] = $ssotPath . ': ssot-registry-drift';
    }

    if (is_string($baseline)) {
        $violations[] = $baselinePath . ': ' . $baseline;
    } elseif (is_array($registry)) {
        if ($registry['schemaVersion'] < $baseline['schemaVersion']) {
            $violations[] = $baselinePath . ': baseline-version-regression';
        } elseif ($registry !== $baseline) {
            $violations[] = $baselinePath . ': baseline-registry-drift';
        }
    }

    return $violations;
}

/**
 * @param list<mixed> $argv
 */
function coretsia_system_route_table_gate_resolve_repository(
    RepositoryContext $repository,
    array $argv,
): RepositoryContext {
    $root = null;

    foreach ($argv as $index => $arg) {
        if ($index === 0) {
            continue;
        }

        if (!is_string($arg) || !str_starts_with($arg, '--root=')) {
            throw new RuntimeException('system-route-table-argument-invalid');
        }

        if ($root !== null) {
            throw new RuntimeException('system-route-table-root-duplicate');
        }

        $root = substr($arg, strlen('--root='));

        if ($root === '') {
            throw new RuntimeException('system-route-table-root-empty');
        }
    }

    return $root === null
        ? $repository
        : RepositoryContext::fromRepoRoot($root);
}

/**
 * Inspect only the selected source's declarations. No fixture PHP is executed.
 *
 * @return array{entries:list<array{owner:string,pattern:string,status:string,type:string}>,schemaVersion:int}|string
 */
function coretsia_system_route_table_gate_read_registry(PhpTokenStream $stream): array|string
{
    $tokens = $stream->tokens();
    $classes = $stream->classLikes();

    if (count($classes) !== 1 || $stream->namespace() !== 'Coretsia\\Platform\\Http\\System') {
        return 'registry-class-invalid';
    }

    $class = $classes[0];

    if ($class['kind'] !== 'class' || $class['name'] !== 'SystemRouteTable' || $class['anonymous']) {
        return 'registry-class-invalid';
    }

    $header = PhpTokenStream::significantTokens(array_slice($tokens, 0, $class['body_start'] + 1));
    $openTag = array_shift($header);
    $tokenText = static fn (array|string $token): string => is_array($token) ? $token[1] : $token;

    if (
        !is_array($openTag)
        || $openTag[0] !== T_OPEN_TAG
        || trim($openTag[1]) !== '<?php'
        || array_map($tokenText, $header) !== [
            'declare',
            '(',
            'strict_types',
            '=',
            '1',
            ')',
            ';',
            'namespace',
            'Coretsia\\Platform\\Http\\System',
            ';',
            'final',
            'class',
            'SystemRouteTable',
            '{',
        ]
        || PhpTokenStream::significantTokens(array_slice($tokens, $class['body_end'] + 1)) !== []
    ) {
        return 'registry-class-invalid';
    }

    $methods = $stream->methods($class['body_start'], $class['body_end']);

    if (count($methods) !== 2) {
        return 'registry-api-invalid';
    }

    $expectedConstants = [
        'SCHEMA_VERSION' => ['public', 'const', 'int', 'SCHEMA_VERSION', '='],
        'ALIVE_PATH' => ['public', 'const', 'string', 'ALIVE_PATH', '='],
        'ENTRIES' => ['private', 'const', 'array', 'ENTRIES', '='],
    ];
    $expectedMethods = [
        '__construct' => ['private', 'function', '__construct', '(', ')', '{', '}'],
        'entries' => [
            'public',
            'static',
            'function',
            'entries',
            '(',
            ')',
            ':',
            'array',
            '{',
            'return',
            'self',
            '::',
            'ENTRIES',
            ';',
            '}',
        ],
    ];

    $constants = [];
    $methodIndex = 0;
    $cursor = $stream->skipTrivia($class['body_start'] + 1);

    while ($cursor < $class['body_end']) {
        $next = $stream->skipTrivia($cursor + 1);

        if (PhpTokenStream::isToken($tokens[$next], T_CONST)) {
            $end = PhpTokenStream::findStatementEnd($tokens, $cursor);

            if ($end === null || $end >= $class['body_end']) {
                return 'registry-constant-invalid';
            }

            $declaration = PhpTokenStream::significantTokens(array_slice($tokens, $cursor, $end - $cursor));
            $signature = array_map($tokenText, array_slice($declaration, 0, 5));
            $name = $signature[3] ?? '';

            if (
                !isset($expectedConstants[$name])
                || isset($constants[$name])
                || $signature !== $expectedConstants[$name]
            ) {
                return 'registry-constant-invalid';
            }

            $constants[$name] = array_slice($declaration, 5);
        } else {
            $method = $methods[$methodIndex] ?? null;
            $name = $method['name'] ?? '';
            $end = $method['body_end'] ?? null;

            if ($end === null || $end < $cursor || !isset($expectedMethods[$name])) {
                return 'registry-api-invalid';
            }

            $declaration = PhpTokenStream::significantTokens(array_slice($tokens, $cursor, $end - $cursor + 1));

            if (array_map($tokenText, $declaration) !== $expectedMethods[$name]) {
                return 'registry-api-invalid';
            }

            unset($expectedMethods[$name]);
            $methodIndex++;
        }

        $cursor = $stream->skipTrivia($end + 1);
    }

    if ($expectedMethods !== [] || count($constants) !== 3) {
        return 'registry-shape-invalid';
    }

    $versionTokens = $constants['SCHEMA_VERSION'];
    $aliveTokens = $constants['ALIVE_PATH'];

    if (count($versionTokens) !== 1 || !PhpTokenStream::isToken($versionTokens[0], T_LNUMBER)) {
        return 'registry-version-invalid';
    }

    $version = coretsia_system_route_table_gate_positive_version($tokenText($versionTokens[0]));

    if ($version === null) {
        return 'registry-version-invalid';
    }

    if (count($aliveTokens) !== 1 || !PhpTokenStream::isToken($aliveTokens[0], T_CONSTANT_ENCAPSED_STRING)) {
        return 'registry-alive-path-invalid';
    }

    $aliveLiteral = $tokenText($aliveTokens[0]);
    $alivePath = PhpTokenStream::decodeStringLiteral($aliveLiteral);

    if ($alivePath !== '/_alive' || str_contains($aliveLiteral, '\\')) {
        return 'registry-alive-path-invalid';
    }

    $entries = coretsia_system_route_table_gate_read_entries($constants['ENTRIES'], $alivePath);

    if (is_string($entries)) {
        return $entries;
    }

    $reason = coretsia_system_route_table_gate_validate_entries($entries);

    if ($reason !== null) {
        return $reason;
    }

    return ['entries' => $entries, 'schemaVersion' => $version];
}

/**
 * @param list<array{0:int,1:string,2:int}|string> $tokens
 * @return list<array{owner:string,pattern:string,status:string,type:string}>|string
 */
function coretsia_system_route_table_gate_read_entries(array $tokens, string $alivePath): array|string
{
    if (
        ($tokens[0] ?? null) !== '['
        || PhpTokenStream::findMatchingPairIn($tokens, 0, '[', ']') !== count($tokens) - 1
    ) {
        return 'registry-entries-invalid';
    }

    $rows = PhpTokenStream::splitTopLevel(array_slice($tokens, 1, -1), ',');

    if (end($rows) === []) {
        array_pop($rows);
    }

    $entries = [];

    foreach ($rows as $row) {
        if (
            ($row[0] ?? null) !== '['
            || PhpTokenStream::findMatchingPairIn($row, 0, '[', ']') !== count($row) - 1
        ) {
            return 'registry-entry-shape-invalid';
        }

        $fields = PhpTokenStream::splitTopLevel(array_slice($row, 1, -1), ',');

        if (end($fields) === []) {
            array_pop($fields);
        }

        $entry = [];

        foreach ($fields as $field) {
            if (
                !isset($field[0], $field[1], $field[2])
                || !is_array($field[0])
                || !PhpTokenStream::isToken($field[0], T_CONSTANT_ENCAPSED_STRING)
                || !PhpTokenStream::isToken($field[1], T_DOUBLE_ARROW)
                || str_contains($field[0][1], '\\')
            ) {
                return 'registry-entry-field-invalid';
            }

            $key = PhpTokenStream::decodeStringLiteral($field[0][1]);

            if (isset($entry[$key])) {
                return 'registry-entry-field-duplicate';
            }

            if (
                count($field) === 3
                && is_array($field[2])
                && PhpTokenStream::isToken($field[2], T_CONSTANT_ENCAPSED_STRING)
                && !str_contains($field[2][1], '\\')
            ) {
                $value = PhpTokenStream::decodeStringLiteral($field[2][1]);

                if ($key === 'pattern' && $value === $alivePath) {
                    return 'registry-alive-reference-invalid';
                }
            } elseif (
                $key === 'pattern'
                && count($field) === 5
                && is_array($field[2])
                && PhpTokenStream::isToken($field[2], T_STRING)
                && $field[2][1] === 'self'
                && PhpTokenStream::isToken($field[3], T_DOUBLE_COLON)
                && is_array($field[4])
                && PhpTokenStream::isToken($field[4], T_STRING)
                && $field[4][1] === 'ALIVE_PATH'
            ) {
                $value = $alivePath;
            } else {
                return 'registry-entry-field-invalid';
            }

            $entry[$key] = $value;
        }

        if (array_keys($entry) !== ['owner', 'pattern', 'status', 'type']) {
            return 'registry-entry-shape-invalid';
        }

        $entries[] = [
            'owner' => $entry['owner'],
            'pattern' => $entry['pattern'],
            'status' => $entry['status'],
            'type' => $entry['type'],
        ];
    }

    return $entries;
}

function coretsia_system_route_table_gate_positive_version(string $literal): ?int
{
    if (preg_match('/\A[1-9][0-9]*\z/', $literal) !== 1) {
        return null;
    }

    $version = (int) $literal;

    return (string) $version === $literal ? $version : null;
}

/**
 * Shared normative entry policy for source, SSoT, and baseline snapshots.
 */
function coretsia_system_route_table_gate_validate_entries(mixed $entries): ?string
{
    if (!is_array($entries) || !array_is_list($entries) || $entries === []) {
        return 'registry-entries-invalid';
    }

    $seen = [];
    $prefixes = [];
    $previous = null;

    foreach ($entries as $entry) {
        if (!is_array($entry) || array_keys($entry) !== ['owner', 'pattern', 'status', 'type']) {
            return 'registry-entry-shape-invalid';
        }

        $owner = $entry['owner'];
        $pattern = $entry['pattern'];
        $status = $entry['status'];
        $type = $entry['type'];

        if (!is_string($owner) || !is_string($pattern) || !is_string($status) || !is_string($type)) {
            return 'registry-entry-field-invalid';
        }

        if (
            preg_match(
                '~\A([a-z0-9]+(?:[._-][a-z0-9]+)*)/([a-z0-9]+(?:[._-][a-z0-9]+)*)\z~',
                $owner,
                $match,
            ) !== 1
            || !WorkspacePackageCatalog::isLayeredRoot($match[1])
            || ($match[1] === 'core' && WorkspacePackageCatalog::isLayeredRoot($match[2]))
        ) {
            return 'registry-owner-invalid';
        }

        if (!in_array($status, ['active', 'reserved', 'dev_only'], true)) {
            return 'registry-status-invalid';
        }

        if (!in_array($type, ['exact', 'prefix'], true)) {
            return 'registry-type-invalid';
        }

        if (
            ($pattern !== '/' && preg_match('~\A/[a-z0-9_-]+(?:/[a-z0-9_-]+)*/?\z~', $pattern) !== 1)
            || ($pattern === '/' && $type !== 'exact')
            || ($pattern !== '/' && str_ends_with($pattern, '/') !== ($type === 'prefix'))
        ) {
            return 'registry-path-invalid';
        }

        if (isset($seen[$pattern])) {
            return 'registry-pattern-duplicate';
        }

        foreach ($prefixes as $prefix) {
            if (str_starts_with($pattern, $prefix)) {
                return 'registry-pattern-overlap';
            }
        }

        if ($type === 'prefix') {
            foreach (array_keys($seen) as $other) {
                if (str_starts_with($other, $pattern)) {
                    return 'registry-pattern-overlap';
                }
            }

            $prefixes[] = $pattern;
        }

        if ($previous !== null && strcmp($previous, $pattern) > 0) {
            return 'registry-order-invalid';
        }

        $previous = $pattern;
        $seen[$pattern] = $entry;
    }

    $alive = $seen['/_alive'] ?? null;

    if ($alive === null || $alive['type'] !== 'exact' || $alive['owner'] !== 'platform/http') {
        return 'registry-alive-declaration-invalid';
    }

    $debugExact = $seen['/_debug'] ?? null;
    $debugPrefix = $seen['/_debug/'] ?? null;

    if (
        $debugExact === null
        || $debugExact['type'] !== 'exact'
        || $debugExact['status'] !== 'dev_only'
        || $debugPrefix === null
        || $debugPrefix['type'] !== 'prefix'
        || $debugPrefix['status'] !== 'dev_only'
    ) {
        return 'registry-debug-declaration-invalid';
    }

    return null;
}

/**
 * @return array{entries:list<array{owner:string,pattern:string,status:string,type:string}>,schemaVersion:int}|string
 */
function coretsia_system_route_table_gate_read_ssot(string $markdown): array|string
{
    $sections = MarkdownSectionReader::sections($markdown, '## System Route Registry (MUST)');

    if (count($sections) !== 1) {
        return 'ssot-registry-section-invalid';
    }

    $lines = explode("\n", $sections[0]);
    $index = 0;
    $count = count($lines);

    while ($index < $count && trim($lines[$index], " \t") === '') {
        $index++;
    }

    while ($count > $index && trim($lines[$count - 1], " \t") === '') {
        $count--;
    }

    if (preg_match('/\ASchema version: `([1-9][0-9]*)`[ \t]*\z/', $lines[$index] ?? '', $match) !== 1) {
        return 'ssot-version-invalid';
    }

    $version = coretsia_system_route_table_gate_positive_version($match[1]);

    if ($version === null) {
        return 'ssot-version-invalid';
    }

    $index++;

    while ($index < $count && trim($lines[$index], " \t") === '') {
        $index++;
    }

    if (
        preg_match(
            '/\A\|[ \t]*pattern[ \t]*\|[ \t]*type[ \t]*\|[ \t]*status[ \t]*\|[ \t]*owner[ \t]*\|[ \t]*\z/',
            $lines[$index] ?? '',
        ) !== 1
        || preg_match(
            '/\A\|(?:[ \t]*-{3,}[ \t]*\|){4}[ \t]*\z/',
            $lines[$index + 1] ?? '',
        ) !== 1
    ) {
        return 'ssot-registry-table-invalid';
    }

    $entries = [];

    for ($index += 2; $index < $count; $index++) {
        if (
            preg_match(
                '/\A\|[ \t]*`([^`]+)`[ \t]*\|[ \t]*`([^`]+)`[ \t]*\|[ \t]*`([^`]+)`[ \t]*\|[ \t]*`([^`]+)`[ \t]*\|[ \t]*\z/',
                $lines[$index],
                $match,
            ) !== 1
        ) {
            return 'ssot-registry-row-invalid';
        }

        $entries[] = [
            'owner' => $match[4],
            'pattern' => $match[1],
            'status' => $match[3],
            'type' => $match[2],
        ];
    }

    $reason = coretsia_system_route_table_gate_validate_entries($entries);

    if ($reason !== null) {
        return $reason;
    }

    return ['entries' => $entries, 'schemaVersion' => $version];
}

/**
 * Validate the complete local history and return its latest snapshot.
 * Historical immutability against a trusted earlier revision is a review invariant.
 *
 * @return array{entries:list<array{owner:string,pattern:string,status:string,type:string}>,schemaVersion:int}|string
 */
function coretsia_system_route_table_gate_read_baseline(string $bytes): array|string
{
    try {
        $baseline = json_decode($bytes, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return 'baseline-json-invalid';
    }

    if (!is_array($baseline) || !array_is_list($baseline) || $baseline === []) {
        return 'baseline-shape-invalid';
    }

    try {
        if ($bytes !== Json::encodeStable($baseline) . "\n") {
            return 'baseline-noncanonical';
        }
    } catch (JsonException|InvalidArgumentException) {
        return 'baseline-noncanonical';
    }

    $latest = null;

    foreach ($baseline as $index => $snapshot) {
        if (
            !is_array($snapshot)
            || array_keys($snapshot) !== ['entries', 'schemaVersion']
            || !is_int($snapshot['schemaVersion'])
            || $snapshot['schemaVersion'] < 1
        ) {
            return 'baseline-snapshot-invalid';
        }

        if ($snapshot['schemaVersion'] !== $index + 1) {
            return 'baseline-version-sequence-invalid';
        }

        $reason = coretsia_system_route_table_gate_validate_entries($snapshot['entries']);

        if ($reason !== null) {
            return $reason;
        }

        /** @var array{entries:list<array{owner:string,pattern:string,status:string,type:string}>,schemaVersion:int} $snapshot */
        if ($latest !== null && $snapshot['entries'] === $latest['entries']) {
            return 'baseline-version-unchanged';
        }

        $latest = $snapshot;
    }

    return $latest;
}
