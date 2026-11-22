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

namespace Coretsia\Platform\Http\Debug;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Security\Exception\RedactionException;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Platform\Http\Exception\DebugForbiddenException;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Bounded type and effective-source projection for an authorized config key.
 *
 * The original URI query is used without decoding or parsed-query fallbacks.
 * Config values and complete source metadata never enter the public encoder
 * or the shared redactor.
 *
 * @internal
 */
final readonly class DebugConfigHandler
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private DebugGuard $guard,
        private SensitiveDataRedactorInterface $redactor,
        private SensitiveKeyClassifierInterface $keyClassifier,
        private FallbackResponses $responses,
    ) {
    }

    public function handle(ServerRequestInterface $request): ?ResponseInterface
    {
        $query = $request->getUri()->getQuery();
        if (
            \strlen($query) > 4 + DebugGuard::MAX_CONFIG_PATH_BYTES
            || !\str_starts_with($query, 'key=')
        ) {
            throw DebugForbiddenException::forbidden();
        }

        // The guard owns the single bounded logical-path grammar and prefix
        // policy. Extra query selectors and encoded paths fail that grammar.
        $keyPath = \substr($query, 4);
        if (!$this->guard->allowsConfigKey($keyPath)) {
            throw DebugForbiddenException::forbidden();
        }

        foreach (\explode('.', $keyPath) as $segment) {
            if ($this->keyClassifier->classify($segment) !== null) {
                throw DebugForbiddenException::forbidden();
            }
        }

        if (!$this->config->has($keyPath)) {
            return null;
        }

        $type = self::valueType($this->config->get($keyPath));
        $source = $this->config->sourceOf($keyPath);
        $payload = [
            'source' => $source === null ? null : [
                'precedence' => $source->precedence(),
                'redacted' => $source->isRedacted(),
                'type' => $source->type()->value,
            ],
            'type' => $type,
        ];

        $redacted = $this->redactor->redactJsonLike(
            value: $payload,
            context: new RedactionContext('http.debug.config', RedactionMode::Placeholder),
        );

        // The canonical projection is already byte-order sorted and contains
        // only allowed identifiers, a non-negative rank, a boolean or null.
        // An exact match rejects replacement, extra or missing fields, and
        // changed types or values without introducing a second schema.
        if ($redacted !== $payload) {
            throw RedactionException::outputInvalid();
        }

        return $this->responses->debugJson($redacted);
    }

    /** @return 'null'|'bool'|'int'|'string'|'list'|'map' */
    private static function valueType(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            \is_bool($value) => 'bool',
            \is_int($value) => 'int',
            \is_string($value) => 'string',
            \is_array($value) => \array_is_list($value) ? 'list' : 'map',
            default => throw new \UnexpectedValueException('http-debug-config-value-type-invalid'),
        };
    }
}
