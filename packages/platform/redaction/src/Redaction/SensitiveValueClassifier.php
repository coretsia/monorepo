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

namespace Coretsia\Platform\Redaction\Redaction;

use Coretsia\Contracts\Security\RedactionKind;

/**
 * Immutable, high-confidence whole-value classification policy.
 *
 * The classifier never decodes tokens, URLs, JSON, or payloads. Non-matches
 * are not declassification; owners still enforce destination-boundary policy.
 *
 * @internal
 */
final class SensitiveValueClassifier
{
    public function classify(string $value): ?RedactionKind
    {
        if (
            \preg_match(
                '~\A(?:Bearer[ \t]+[^\r\n]+|(?:Authorization|Proxy-Authorization):[ \t]*[^\r\n]+)\z~iD',
                $value,
            ) === 1
        ) {
            return RedactionKind::Authorization;
        }

        if (\preg_match('~\A(?:Cookie|Set-Cookie):[ \t]*[^\r\n]+\z~iD', $value) === 1) {
            return RedactionKind::Cookie;
        }

        if (self::isCredentialUri($value)) {
            return RedactionKind::Credential;
        }

        if (
            \preg_match('~\AeyJ[A-Za-z0-9_-]*\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\z~D', $value) === 1
            || \preg_match('~\A(?:AKIA|ASIA)[A-Z0-9]{16}\z~D', $value) === 1
            || \preg_match('~\A(?:sk_|tok_)[A-Za-z0-9_-]{8,512}\z~D', $value) === 1
        ) {
            return RedactionKind::Token;
        }

        if (self::isLocalPath($value)) {
            return RedactionKind::LocalPath;
        }

        if (self::isEmailAddress($value)) {
            return RedactionKind::Pii;
        }

        return null;
    }

    private static function isCredentialUri(string $value): bool
    {
        return \preg_match(
            '~\A(?=[\x21-\x7E]*\z)[A-Za-z][A-Za-z0-9+.-]*://[^:/?#@]+:[^@/?#]+@[^/?#]+(?:[/?#][\x21-\x7E]*)?\z~D',
            $value,
        ) === 1;
    }

    private static function isLocalPath(string $value): bool
    {
        $candidate = \ltrim($value, " \t");

        if (\str_starts_with($candidate, '\\\\')) {
            return true;
        }

        if (\preg_match('~\A[A-Za-z]:[\\\\/]~D', $candidate) === 1) {
            return true;
        }

        if (\strncasecmp($candidate, 'file://', 7) !== 0) {
            return false;
        }

        $remainder = \substr($candidate, 7);

        return \str_starts_with($remainder, '/')
            || \preg_match('~\A[^/\\\\]+[/\\\\]~D', $remainder) === 1;
    }

    private static function isEmailAddress(string $value): bool
    {
        if (\substr_count($value, '@') !== 1) {
            return false;
        }

        [$local, $domain] = \explode('@', $value, 2);

        if (
            $local === ''
            || $local[0] === '.'
            || \str_ends_with($local, '.')
            || \str_contains($local, '..')
            || \preg_match('~\A[A-Za-z0-9.!#$%&\'*+/=?^_{}|\~-]+\z~D', $local) !== 1
        ) {
            return false;
        }

        return \preg_match(
            '~\A[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+\z~D',
            $domain,
        ) === 1;
    }
}
