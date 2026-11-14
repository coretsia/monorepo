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

namespace Coretsia\Platform\Logging\Record;

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;

/**
 * Immutable logging-sink admission boundary, not a generic redaction engine.
 *
 * Only producer-owned safe-by-construction event identifiers and explicitly
 * enumerated operational context fields are admitted. A syntactically valid
 * token does not prove its provenance; producers MUST NOT pass user-derived
 * messages or arbitrary diagnostic text as event identifiers.
 *
 * Shared redaction is mandatory defense in depth. It never grants emission
 * permission: a completed redaction result is checked again against the exact
 * canonical record schema and shared sensitive-key classifier. Rejected
 * key_paths are omitted during initial context admission; unsafe key_paths in
 * complete records reject emission. Classifier failures abort the record.
 * No raw fallback, duplicated classifier, or stored UoW state is introduced.
 */
final readonly class LogRecordPolicy
{
    private const string REDACTION_SCOPE = 'logging.record';
    private const string FALLBACK_MESSAGE = 'logging.message_rejected';

    private const int MAX_MESSAGE_BYTES = 128;
    private const int MAX_CONTEXT_FIELDS = 48;
    private const int MAX_CATEGORY_BYTES = 96;
    private const int MAX_CODE_BYTES = 128;
    private const int MAX_METADATA_BYTES = 160;
    private const int MAX_ARTIFACT_BASENAME_BYTES = 128;
    private const int MAX_RELATIVE_PATH_BYTES = 512;
    private const int MAX_PATH_TEMPLATE_BYTES = 256;
    private const int MAX_MODULE_ID_BYTES = 128;
    private const int MAX_UOW_ID_BYTES = 128;
    private const int MAX_SAFE_COUNT = 1_000_000_000;
    private const int MAX_DURATION_MS = 4_294_967_295;
    private const int MAX_EXIT_CODE = 255;
    private const int MAX_PID = 2_147_483_647;

    /** @var list<string> */
    private const array ROOT_FIELDS = [
        'ts_utc',
        'level',
        'message',
        'service_name',
        'app_target',
        'correlation_id',
        'uow_id',
        'uow_type',
        'context',
    ];

    /** @var array<string, true> */
    private const array CATEGORY_FIELDS = [
        'operation' => true,
        'outcome' => true,
        'event' => true,
        'reason' => true,
        'driver' => true,
        'control_transport' => true,
        'presetName' => true,
        'preset' => true,
    ];

    /** @var array<string, true> */
    private const array COUNT_FIELDS = [
        'artifact_count' => true,
        'bucket_count' => true,
        'byte_count' => true,
        'dirty_artifact_count' => true,
        'env_overlay_mapping_count' => true,
        'env_overlay_metadata_count' => true,
        'existing_artifact_count' => true,
        'expected_artifact_count' => true,
        'groups_count' => true,
        'invalid_artifact_count' => true,
        'missing_artifact_count' => true,
        'missing_source_candidate_count' => true,
        'ready_worker_count' => true,
        'ruleset_count' => true,
        'services_count' => true,
        'source_candidate_count' => true,
        'source_entry_count' => true,
        'unvalidated_root_count' => true,
        'validated_root_count' => true,
        'validation_subject_unvalidated_count' => true,
        'validation_subject_validated_count' => true,
        'worker_count' => true,
    ];

    /**
     * Fixed list admission matrix: field => [maximum items, maximum item bytes,
     * item semantic category]. Lists are unique and sorted by byte order.
     *
     * @var array<string, array{int, int, string}>
     */
    private const array LIST_FIELDS = [
        'artifact_paths' => [12, self::MAX_RELATIVE_PATH_BYTES, 'relative-path'],
        'artifact_reasons' => [12, self::MAX_METADATA_BYTES, 'artifact-reason'],
        'bucket_names' => [20, self::MAX_CATEGORY_BYTES, 'category'],
        'directive_names' => [20, self::MAX_METADATA_BYTES, 'metadata'],
        'key_paths' => [20, self::MAX_METADATA_BYTES, 'safe-key-path'],
        'moduleIds' => [24, self::MAX_MODULE_ID_BYTES, 'module-id'],
        'source_kinds' => [20, self::MAX_METADATA_BYTES, 'metadata'],
        'source_layers' => [20, self::MAX_METADATA_BYTES, 'metadata'],
        'source_paths' => [20, self::MAX_METADATA_BYTES, 'relative-metadata'],
        'source_types' => [20, self::MAX_METADATA_BYTES, 'metadata'],
    ];

    public function __construct(
        private SensitiveDataRedactorInterface $redactor,
        private SensitiveKeyClassifierInterface $keyClassifier,
    ) {
    }

    /**
     * Accepts only producer-owned static event identifiers. Never evaluates
     * Stringable::__toString(), interpolates context, or emits raw diagnostics.
     */
    public function admitMessage(mixed $message): string
    {
        return \is_string($message) && self::isEventToken($message)
            ? $message
            : self::FALLBACK_MESSAGE;
    }

    /**
     * Admits only the fixed sink-field vocabulary. Nested maps, floating-point
     * values, arbitrary objects, resource handles, and unknown keys are never
     * passed to the redactor or formatter. Empty context becomes null.
     *
     * Producer context identifiers are non-authoritative: StreamLogger MUST
     * assemble canonical root correlation_id/uow_id/uow_type from its current
     * ContextBagProcessor, never by copying these caller-supplied values.
     *
     * @param array<array-key, mixed> $context
     * @return array<string, mixed>|null
     */
    public function admitContext(array $context): ?array
    {
        if (\count($context) > self::MAX_CONTEXT_FIELDS) {
            return null;
        }

        $admitted = [];

        foreach ($context as $key => $value) {
            if (!\is_string($key) || !self::isAdmittedValue($key, $value)) {
                continue;
            }

            if ($key === 'key_paths' && !$this->areKeyPathsAdmitted($value)) {
                // Reject the entire branch, never a partial key-path list.
                continue;
            }

            if (isset(self::LIST_FIELDS[$key])) {
                // Validation above established a flat bounded list of strings.
                $value = \array_values(\array_unique($value, \SORT_STRING));
                \usort($value, static fn (string $a, string $b): int => \strcmp($a, $b));
            }

            $admitted[$key] = $value;
        }

        if ($admitted === []) {
            return null;
        }

        \ksort($admitted, \SORT_STRING);

        return $admitted;
    }

    /**
     * Performs exactly one mandatory shared redaction operation and checks
     * its output against the full, pre-assembled canonical record shape.
     *
     * A redacted summary map does not satisfy a scalar field type. Such a
     * context branch is omitted; an invalid root field or any redactor failure
     * rejects the entire record. No partial redaction output is returned.
     *
     * @param array<string, mixed> $record
     * @return array<string, mixed>|null
     */
    public function redactRecord(array $record): ?array
    {
        if (!self::isValidCanonicalRecord($record) || !$this->hasAdmittedKeyPaths($record['context'])) {
            return null;
        }

        try {
            $redacted = $this->redactor->redactJsonLike(
                $record,
                new RedactionContext(self::REDACTION_SCOPE, RedactionMode::Placeholder),
            );
        } catch (\Throwable) {
            // Never expose input or redaction diagnostics and never retry.
            return null;
        }

        if (!\is_array($redacted) || !self::hasExactRootFields($redacted)) {
            return null;
        }

        // A redacted context branch may no longer be a legal sink-field value.
        // Remove that branch, not an original unredacted fallback value.
        $context = $redacted['context'];

        if (\is_array($context)) {
            if (!$this->hasAdmittedKeyPaths($context)) {
                return null;
            }

            $redacted['context'] = $this->admitContext($context);
        } elseif ($context !== null) {
            return null;
        }

        return self::isValidCanonicalRecord($redacted) ? $redacted : null;
    }

    /**
     * Verifies that no serialized record contains an unknown or malformed
     * canonical root field. This is a sink schema check, not JSON encoding.
     *
     * @param array<string, mixed> $record
     */
    public static function isValidCanonicalRecord(array $record): bool
    {
        if (!self::hasExactRootFields($record)) {
            return false;
        }

        if (
            ($record['ts_utc'] !== null && !self::isUtcTimestamp($record['ts_utc']))
            || !self::isSeverity($record['level'])
            || !\is_string($record['message'])
            || !self::isEventToken($record['message'])
            || !self::isServiceName($record['service_name'])
            || !\in_array($record['app_target'], ['web', 'api', 'console', 'worker'], true)
            || !self::isNullableCorrelationId($record['correlation_id'])
            || !self::isNullableUowId($record['uow_id'])
            || !self::isNullableUowType($record['uow_type'])
        ) {
            return false;
        }

        $context = $record['context'];

        if ($context === null) {
            return true;
        }

        if (!\is_array($context) || $context === [] || \array_is_list($context) || \count(
            $context,
        ) > self::MAX_CONTEXT_FIELDS) {
            return false;
        }

        foreach ($context as $key => $value) {
            if (!\is_string($key) || !self::isAdmittedValue($key, $value)) {
                return false;
            }

            if (isset(self::LIST_FIELDS[$key])) {
                $previous = null;

                foreach ($value as $item) {
                    if ($previous !== null && \strcmp($previous, $item) >= 0) {
                        return false;
                    }

                    $previous = $item;
                }
            }
        }

        return true;
    }

    /** @param array<string, mixed> $record */
    private static function hasExactRootFields(array $record): bool
    {
        if (\count($record) !== \count(self::ROOT_FIELDS)) {
            return false;
        }

        foreach (self::ROOT_FIELDS as $key) {
            if (!\array_key_exists($key, $record)) {
                return false;
            }
        }

        return true;
    }

    private static function isAdmittedValue(string $key, mixed $value): bool
    {
        if ($key === 'status') {
            return (\is_int($value) && $value >= 100 && $value <= 599)
                || self::isCategory($value);
        }

        if (isset(self::CATEGORY_FIELDS[$key])) {
            return self::isCategory($value);
        }

        if (isset(self::COUNT_FIELDS[$key])) {
            return \is_int($value) && $value >= 0 && $value <= self::MAX_SAFE_COUNT;
        }

        if (isset(self::LIST_FIELDS[$key])) {
            return self::isAdmittedList($value, ...self::LIST_FIELDS[$key]);
        }

        return match ($key) {
            'code' => self::isCode($value),
            'duration_ms' => \is_int($value) && $value >= 0 && $value <= self::MAX_DURATION_MS,
            'exit_code' => \is_int($value) && $value >= 0 && $value <= self::MAX_EXIT_CODE,
            'pid' => \is_int($value) && $value >= 1 && $value <= self::MAX_PID,
            'artifact_basename' => self::isBasename($value),
            'artifact_path' => self::isRelativePath($value, self::MAX_RELATIVE_PATH_BYTES),
            'endpoint_hash' => \is_string($value) && \preg_match('/\A[a-f0-9]{64}\z/', $value) === 1,
            'app_target' => \in_array($value, ['web', 'api', 'console', 'worker'], true),
            'correlation_id' => self::isNullableCorrelationId($value) && $value !== null,
            'uow_id' => self::isNullableUowId($value) && $value !== null,
            'uow_type' => self::isNullableUowType($value) && $value !== null,
            'path_template' => self::isRouteTemplate($value),
            'method' => \is_string($value) && \in_array($value, [
                'GET',
                'HEAD',
                'POST',
                'PUT',
                'PATCH',
                'DELETE',
                'OPTIONS',
                'TRACE',
                'CONNECT',
                'OTHER',
            ], true),
            'route_template' => $value === 'unknown',
            default => false,
        };
    }

    private static function isEventToken(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_MESSAGE_BYTES
            && \preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value) === 1;
    }

    private static function isCategory(mixed $value): bool
    {
        return \is_string($value)
            && $value !== ''
            && \strlen($value) <= self::MAX_CATEGORY_BYTES
            && \preg_match('/\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z/', $value) === 1;
    }

    private static function isCode(mixed $value): bool
    {
        return \is_string($value)
            && $value !== ''
            && \strlen($value) <= self::MAX_CODE_BYTES
            && \preg_match('/\A[A-Za-z][A-Za-z0-9_:-]*\z/', $value) === 1;
    }

    private static function isBasename(mixed $value): bool
    {
        return \is_string($value)
            && $value !== ''
            && \strlen($value) <= self::MAX_ARTIFACT_BASENAME_BYTES
            && \preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\z/', $value) === 1
            && !\str_contains($value, '..');
    }

    private static function isRelativePath(mixed $value, int $maxBytes): bool
    {
        if (
            !\is_string($value)
            || $value === ''
            || \strlen($value) > $maxBytes
            || \str_contains($value, '\\')
            || \str_contains($value, ':')
            || \str_contains($value, '//')
            || \str_starts_with($value, '/')
            || $value === '.'
            || $value === '..'
            || \str_starts_with($value, './')
            || \str_starts_with($value, '../')
            || \str_contains($value, '/./')
            || \str_contains($value, '/../')
            || \str_ends_with($value, '/.')
            || \str_ends_with($value, '/..')
        ) {
            return false;
        }

        return \preg_match('/\A[A-Za-z0-9_@.<>\[\]\/-]+\z/', $value) === 1;
    }

    private static function isMetadata(mixed $value, int $maxBytes): bool
    {
        return self::isRelativePath($value, $maxBytes);
    }

    private static function isSafeKeyPath(mixed $value): bool
    {
        return self::isMetadata($value, self::MAX_METADATA_BYTES)
            && \preg_match(
                '/\A[A-Za-z0-9_@<>\[\]-]+(?:\.[A-Za-z0-9_@<>\[\]-]+)*\z/',
                $value,
            ) === 1;
    }

    /** @param array<array-key, mixed>|null $context */
    private function hasAdmittedKeyPaths(?array $context): bool
    {
        return $context === null
            || !\array_key_exists('key_paths', $context)
            || $this->areKeyPathsAdmitted($context['key_paths']);
    }

    private function areKeyPathsAdmitted(mixed $value): bool
    {
        if (!self::isAdmittedList($value, ...self::LIST_FIELDS['key_paths'])) {
            return false;
        }

        foreach ($value as $path) {
            if ($this->keyClassifier->classify($path) !== null) {
                return false;
            }

            foreach (\explode('.', $path) as $segment) {
                if ($this->keyClassifier->classify($segment) !== null) {
                    return false;
                }

                // Indexes and structural placeholders must not hide key aliases.
                if (\strpbrk($segment, '@<>[]') === false) {
                    continue;
                }

                $components = \preg_split('/[@<>\[\]]+/', $segment, -1, \PREG_SPLIT_NO_EMPTY);

                if ($components === false || $components === []) {
                    return false;
                }

                foreach ($components as $component) {
                    if ($this->keyClassifier->classify($component) !== null) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    private static function isModuleId(mixed $value): bool
    {
        return \is_string($value)
            && \strlen($value) <= self::MAX_MODULE_ID_BYTES
            && \preg_match('/\A[a-z][a-z0-9]*(?:[._-][a-z0-9]+)*\z/', $value) === 1;
    }

    private static function isArtifactReason(mixed $value): bool
    {
        if (!\is_string($value) || \strlen($value) > self::MAX_METADATA_BYTES) {
            return false;
        }

        $parts = \explode(':', $value);

        return \count($parts) === 2
            && self::isBasename($parts[0])
            && self::isCategory($parts[1]);
    }

    private static function isRouteTemplate(mixed $value): bool
    {
        if (!\is_string($value) || \strlen($value) > self::MAX_PATH_TEMPLATE_BYTES) {
            return false;
        }

        // Dynamic segments must be placeholders, never raw request parameters.
        return $value === '/'
            || \preg_match(
                '/\A\/(?:[a-z_-][a-z0-9_-]*|\{[A-Za-z_][A-Za-z0-9_]*})(?:\/(?:[a-z_-][a-z0-9_-]*|\{[A-Za-z_][A-Za-z0-9_]*}))*\z/',
                $value,
            ) === 1;
    }

    private static function isAdmittedList(mixed $value, int $maxItems, int $maxItemBytes, string $type): bool
    {
        if (!\is_array($value) || !\array_is_list($value) || \count($value) > $maxItems) {
            return false;
        }

        foreach ($value as $item) {
            if (!\is_string($item) || $item === '' || \strlen($item) > $maxItemBytes) {
                return false;
            }

            $valid = match ($type) {
                'relative-path' => self::isRelativePath($item, $maxItemBytes),
                'relative-metadata' => self::isRelativePath($item, $maxItemBytes),
                'artifact-reason' => self::isArtifactReason($item),
                'module-id' => self::isModuleId($item),
                'category' => self::isCategory($item),
                'metadata' => self::isMetadata($item, $maxItemBytes),
                'safe-key-path' => self::isSafeKeyPath($item),
                default => false,
            };

            if (!$valid) {
                return false;
            }
        }

        return true;
    }

    private static function isUtcTimestamp(mixed $value): bool
    {
        if (!\is_string($value) || \strlen($value) > 40) {
            return false;
        }

        if (
            \preg_match(
                '/\A([0-9]{4})-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])T(?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9](?:\.[0-9]{1,6})?(?:Z|\+00:00)\z/',
                $value,
                $parts,
            ) !== 1
        ) {
            return false;
        }

        return \checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }

    private static function isSeverity(mixed $value): bool
    {
        return \in_array($value, [
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
        ], true);
    }

    private static function isServiceName(mixed $value): bool
    {
        return \is_string($value)
            && \strlen($value) <= 64
            && \preg_match('/\A[a-z][a-z0-9_-]*\z/', $value) === 1;
    }

    private static function isNullableCorrelationId(mixed $value): bool
    {
        return $value === null
            || (\is_string($value) && \preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $value) === 1);
    }

    private static function isNullableUowId(mixed $value): bool
    {
        return $value === null
            || (\is_string($value)
                && \strlen($value) <= self::MAX_UOW_ID_BYTES
                && \preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $value) === 1);
    }

    private static function isNullableUowType(mixed $value): bool
    {
        return $value === null || \in_array($value, ['http', 'cli', 'queue', 'scheduler'], true);
    }
}
