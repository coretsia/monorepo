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

namespace Coretsia\Platform\Routing\Compile;

use Coretsia\Foundation\Routing\ReservedPathMatcher;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;

/**
 * Immutable canonical representation of a supported application route template.
 *
 * Static segments are lowercase; placeholders consume one complete encoded
 * URI segment. This class never executes provider-controlled regular expressions.
 *
 * @internal
 */
final readonly class RouteTemplate
{
    private const int MAX_TEMPLATE_BYTES = 256;

    /**
     * @param list<array{literal: string|null, name: string|null, requirement: string|null}> $segments
     */
    private function __construct(
        private string $template,
        private array $segments,
    ) {
    }

    /**
     * @param array<string, string> $requirements
     */
    public static function parse(string $template, array $requirements = []): self
    {
        if (
            $template === ''
            || \strlen($template) > self::MAX_TEMPLATE_BYTES
            || $template[0] !== '/'
            || ($template !== '/' && \str_ends_with($template, '/'))
        ) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }

        if ($requirements !== [] && \array_is_list($requirements)) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
            );
        }

        $segments = [];
        $names = [];

        if ($template !== '/') {
            foreach (\explode('/', \substr($template, 1)) as $segment) {
                if (\preg_match('/\A[a-z_-][a-z0-9_-]*\z/D', $segment) === 1) {
                    $segments[] = ['literal' => $segment, 'name' => null, 'requirement' => null];
                    continue;
                }

                if (\preg_match('/\A\{([A-Za-z_][A-Za-z0-9_]*)}\z/D', $segment, $matches) !== 1) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
                    );
                }

                $name = $matches[1];

                if (isset($names[$name])) {
                    throw RouteDefinitionInvalidException::withReason(
                        RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
                    );
                }

                $names[$name] = true;
                $segments[] = ['literal' => null, 'name' => $name, 'requirement' => null];
            }
        }

        foreach ($requirements as $name => $expression) {
            if (
                !\is_string($name)
                || !isset($names[$name])
                || !\is_string($expression)
                || !\in_array($expression, ['[0-9]+', '[A-Za-z]+', '[A-Za-z0-9_-]+'], true)
            ) {
                throw RouteDefinitionInvalidException::withReason(
                    RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID,
                );
            }
        }

        foreach ($segments as &$segment) {
            if ($segment['name'] !== null) {
                $segment['requirement'] = $requirements[$segment['name']] ?? null;
            }
        }
        unset($segment);

        return new self($template, $segments);
    }

    public function template(): string
    {
        return $this->template;
    }

    public function segmentCount(): int
    {
        return \count($this->segments);
    }

    /**
     * @return list<string>
     */
    public function placeholderNames(): array
    {
        $names = [];
        foreach ($this->segments as $segment) {
            if ($segment['name'] !== null) {
                $names[] = $segment['name'];
            }
        }
        return $names;
    }

    /**
     * Returns one deterministic segment in the segment's complete matching domain.
     */
    public function representativeSegment(int $index): string
    {
        $segment = $this->segments[$index] ?? null;
        if ($segment === null) {
            throw RouteDefinitionInvalidException::withReason(
                RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID,
            );
        }

        return match ($segment['requirement']) {
            '[0-9]+' => '0',
            default => $segment['literal'] ?? 'a',
        };
    }

    /**
     * Tests a concrete encoded URI segment against exactly one template segment.
     */
    public function segmentMatches(int $index, string $value): bool
    {
        $segment = $this->segments[$index] ?? null;
        if ($segment === null || $value === '') {
            return false;
        }

        if ($segment['literal'] !== null) {
            return $value === $segment['literal'];
        }

        // Valid encoded URI pchar bytes, with percent triplets retained verbatim.
        if (\preg_match("#\\A(?:[A-Za-z0-9._~!$&'()*+,;=:@-]|%[0-9A-Fa-f]{2})+\\z#D", $value) !== 1) {
            return false;
        }

        return match ($segment['requirement']) {
            '[0-9]+' => \preg_match('/\A[0-9]+\z/D', $value) === 1,
            '[A-Za-z]+' => \preg_match('/\A[A-Za-z]+\z/D', $value) === 1,
            '[A-Za-z0-9_-]+' => \preg_match('/\A[A-Za-z0-9_-]+\z/D', $value) === 1,
            default => true,
        };
    }

    /**
     * Matches only canonical concrete paths without percent-decoding or aliases.
     *
     * @return array<string, non-empty-string>|null
     */
    public function match(string $path): ?array
    {
        try {
            if (ReservedPathMatcher::normalize($path) !== $path) {
                return null;
            }
        } catch (\InvalidArgumentException) {
            return null;
        }

        if ($path !== '/' && \str_ends_with($path, '/')) {
            return null;
        }

        if ($path === '/') {
            return $this->segments === [] ? [] : null;
        }

        $values = \explode('/', \substr($path, 1));
        if (\count($values) !== \count($this->segments)) {
            return null;
        }

        $parameters = [];
        foreach ($values as $index => $value) {
            if (!$this->segmentMatches($index, $value)) {
                return null;
            }
            $name = $this->segments[$index]['name'];
            if ($name !== null) {
                $parameters[$name] = $value;
            }
        }
        return $parameters;
    }

    /**
     * Placeholder identifiers do not affect the matching domain.
     */
    public function domainSignature(): string
    {
        $segments = [];
        foreach ($this->segments as $segment) {
            $segments[] = $segment['literal'] !== null
                ? ['literal', $segment['literal']]
                : ['placeholder', $segment['requirement']];
        }
        return \json_encode($segments, \JSON_THROW_ON_ERROR);
    }
}
