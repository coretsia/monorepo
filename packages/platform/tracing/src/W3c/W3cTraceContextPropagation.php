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

namespace Coretsia\Platform\Tracing\W3c;

use Coretsia\Contracts\Observability\Tracing\ContextPropagationInterface;

/**
 * Stateless, transport-neutral W3C traceparent propagation baseline.
 *
 * Only a validated three-field context crosses the extraction boundary.
 * Existing tracestate is retained solely for a byte-identical, canonical
 * traceparent; every tracing-field mutation otherwise removes tracestate.
 *
 * Version 00 deliberately rejects reserved trace-flags bits. Future versions
 * are accepted at the supported field offsets and downgraded to version 00,
 * discarding unsupported flags and unknown extension fields.
 *
 * It neither generates identities nor owns spans, sampling, ContextStore,
 * UnitOfWork state, I/O, or the Foundation noop fallback binding.
 */
final class W3cTraceContextPropagation implements ContextPropagationInterface
{
    private const int MAX_CARRIER_ENTRIES = 128;
    private const int MAX_TRACEPARENT_BYTES = 512;
    private const int VERSION_00_BYTES = 55;
    private const int SUPPORTED_FLAGS_MASK = 0x03;

    /**
     * @param array<string,string|list<string>> $carrier
     * @param array<string,mixed> $context
     *
     * @return array<string,string|list<string>>
     */
    public function inject(array $carrier, array $context = []): array
    {
        // Oversized carriers receive only a key-oriented tracing-field removal.
        if (\count($carrier) > self::MAX_CARRIER_ENTRIES) {
            return self::withoutTracingFields($carrier);
        }

        $existingValue = null;
        $traceparentCount = 0;
        $invalidCarrier = false;

        foreach ($carrier as $name => $value) {
            if (!\is_string($name)) {
                $invalidCarrier = true;
                continue;
            }

            if (!self::isTraceparentName($name)) {
                continue;
            }

            $traceparentCount++;

            if ($traceparentCount !== 1) {
                $invalidCarrier = true;
                continue;
            }

            $existingValue = self::traceparentValue($value);

            if ($existingValue === null) {
                $invalidCarrier = true;
            }
        }

        $outgoingContext = self::validatedContext($context);

        if ($invalidCarrier || $outgoingContext === null) {
            return self::withoutTracingFields($carrier);
        }

        $existingContext = null;

        if ($traceparentCount === 1) {
            if ($existingValue === null) {
                return self::withoutTracingFields($carrier);
            }

            $existingContext = self::parseTraceparent($existingValue);

            if ($existingContext === null) {
                return self::withoutTracingFields($carrier);
            }

            $previousFlags = \hexdec($existingContext['trace_flags']);
            $nextFlags = \hexdec($outgoingContext['trace_flags']);

            if (
                ($existingContext['trace_id'] === $outgoingContext['trace_id']
                    && (($previousFlags ^ $nextFlags) & 0x02) !== 0)
                || ($existingContext['parent_id'] === $outgoingContext['parent_id']
                    && (($previousFlags ^ $nextFlags) & 0x01) !== 0)
            ) {
                return self::withoutTracingFields($carrier);
            }
        }

        $outgoingValue = '00-'
            . $outgoingContext['trace_id'] . '-'
            . $outgoingContext['parent_id'] . '-'
            . $outgoingContext['trace_flags'];

        // The caller's opaque tracestate is retained only with an unchanged
        // canonical version-00 value; no cross-carrier transfer is attempted.
        $preserveTracestate = $traceparentCount === 1
            && $existingValue === $outgoingValue;

        $result = $preserveTracestate
            ? self::withoutTraceparent($carrier)
            : self::withoutTracingFields($carrier);

        $result['traceparent'] = $outgoingValue;

        return $result;
    }

    /**
     * @param array<string,string|list<string>> $carrier
     *
     * @return array<string,mixed>
     */
    public function extract(array $carrier): array
    {
        if (\count($carrier) > self::MAX_CARRIER_ENTRIES) {
            return [];
        }

        $candidate = null;
        $traceparentCount = 0;

        foreach ($carrier as $name => $value) {
            if (!\is_string($name)) {
                return [];
            }

            if (!self::isTraceparentName($name)) {
                continue;
            }

            $traceparentCount++;

            if ($traceparentCount > 1) {
                return [];
            }

            $candidate = self::traceparentValue($value);

            if ($candidate === null) {
                return [];
            }
        }

        if ($traceparentCount !== 1 || $candidate === null) {
            return [];
        }

        return self::parseTraceparent($candidate) ?? [];
    }

    private static function isTraceparentName(string $name): bool
    {
        return \strlen($name) === 11 && \strcasecmp($name, 'traceparent') === 0;
    }

    private static function isTracestateName(string $name): bool
    {
        return \strlen($name) === 10 && \strcasecmp($name, 'tracestate') === 0;
    }

    private static function isTracingName(string $name): bool
    {
        return self::isTraceparentName($name) || self::isTracestateName($name);
    }

    /**
     * @param array<string,string|list<string>> $carrier
     *
     * @return array<string,string|list<string>>
     */
    private static function withoutTracingFields(array $carrier): array
    {
        foreach ($carrier as $name => $_value) {
            if (\is_string($name) && self::isTracingName($name)) {
                unset($carrier[$name]);
            }
        }

        return $carrier;
    }

    /**
     * @param array<string,string|list<string>> $carrier
     *
     * @return array<string,string|list<string>>
     */
    private static function withoutTraceparent(array $carrier): array
    {
        foreach ($carrier as $name => $_value) {
            if (\is_string($name) && self::isTraceparentName($name)) {
                unset($carrier[$name]);
            }
        }

        return $carrier;
    }

    private static function traceparentValue(mixed $value): ?string
    {
        if (\is_string($value)) {
            return \strlen($value) <= self::MAX_TRACEPARENT_BYTES ? $value : null;
        }

        if (
            !\is_array($value)
            || \count($value) !== 1
            || !\array_is_list($value)
            || !\is_string($value[0])
        ) {
            return null;
        }

        return \strlen($value[0]) <= self::MAX_TRACEPARENT_BYTES ? $value[0] : null;
    }

    /**
     * @return array{trace_id:string,parent_id:string,trace_flags:string}|null
     */
    private static function parseTraceparent(string $value): ?array
    {
        $length = \strlen($value);

        if (
            $length < self::VERSION_00_BYTES
            || $length > self::MAX_TRACEPARENT_BYTES
            || \preg_match('/[\x00-\x1F\x7F]/', $value) === 1
            || $value[2] !== '-'
            || $value[35] !== '-'
            || $value[52] !== '-'
        ) {
            return null;
        }

        $version = \substr($value, 0, 2);
        $traceId = \substr($value, 3, 32);
        $parentId = \substr($value, 36, 16);
        $flags = \substr($value, 53, 2);

        if (
            \preg_match('/\A[0-9a-f]{2}\z/D', $version) !== 1
            || $version === 'ff'
            || !self::validIdentifiers($traceId, $parentId)
            || \preg_match('/\A[0-9a-f]{2}\z/D', $flags) !== 1
        ) {
            return null;
        }

        $flagValue = \hexdec($flags);

        if ($version === '00') {
            if (
                $length !== self::VERSION_00_BYTES
                || ($flagValue & ~self::SUPPORTED_FLAGS_MASK) !== 0
            ) {
                return null;
            }
        } else {
            if ($length > self::VERSION_00_BYTES && $value[self::VERSION_00_BYTES] !== '-') {
                return null;
            }

            // Unknown future-version fields are deliberately not decoded.
            $flags = \str_pad(
                \dechex($flagValue & self::SUPPORTED_FLAGS_MASK),
                2,
                '0',
                STR_PAD_LEFT,
            );
        }

        return [
            'trace_id' => $traceId,
            'parent_id' => $parentId,
            'trace_flags' => $flags,
        ];
    }

    private static function validIdentifiers(string $traceId, string $parentId): bool
    {
        return \strlen($traceId) === 32
            && \strlen($parentId) === 16
            && \preg_match('/\A[0-9a-f]{32}\z/D', $traceId) === 1
            && \preg_match('/\A[0-9a-f]{16}\z/D', $parentId) === 1
            && $traceId !== \str_repeat('0', 32)
            && $parentId !== \str_repeat('0', 16);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array{trace_id:string,parent_id:string,trace_flags:string}|null
     */
    private static function validatedContext(array $context): ?array
    {
        if (
            \count($context) !== 3
            || !\is_string($context['trace_id'] ?? null)
            || !\is_string($context['parent_id'] ?? null)
            || !\is_string($context['trace_flags'] ?? null)
        ) {
            return null;
        }

        $traceId = $context['trace_id'];
        $parentId = $context['parent_id'];
        $flags = $context['trace_flags'];

        if (
            !self::validIdentifiers($traceId, $parentId)
            || !\in_array($flags, ['00', '01', '02', '03'], true)
        ) {
            return null;
        }

        return [
            'trace_id' => $traceId,
            'parent_id' => $parentId,
            'trace_flags' => $flags,
        ];
    }
}
