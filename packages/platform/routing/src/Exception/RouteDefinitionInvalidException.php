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

namespace Coretsia\Platform\Routing\Exception;

/**
 * Deterministic route provider, declaration, and matching-policy failure.
 *
 * Messages contain only the fixed Routing error code and an allowlisted
 * reason token. Provider ids, declarations, paths, methods, requirements,
 * metadata values, and previous throwable messages are never retained.
 *
 * @internal
 */
final class RouteDefinitionInvalidException extends \RuntimeException
{
    public const string ERROR_CODE = 'CORETSIA_ROUTING_ROUTE_DEFINITION_INVALID';

    public const string REASON_INVALID = 'routing-route-definition-invalid';
    public const string REASON_PROVIDER_INVALID = 'routing-route-provider-invalid';
    public const string REASON_PROVIDER_OUTPUT_INVALID = 'routing-route-provider-output-invalid';
    public const string REASON_PROVIDER_IDENTITY_DUPLICATE = 'routing-route-provider-identity-duplicate';
    public const string REASON_ROUTE_NAME_DUPLICATE = 'routing-route-name-duplicate';
    public const string REASON_MATCHING_DOMAIN_EQUIVALENT = 'routing-route-matching-domain-equivalent';
    public const string REASON_DECLARATION_UNSUPPORTED = 'routing-route-declaration-unsupported';
    public const string REASON_TEMPLATE_INVALID = 'routing-route-template-invalid';
    public const string REASON_METHOD_INVALID = 'routing-route-method-invalid';
    public const string REASON_REQUIREMENT_INVALID = 'routing-route-requirement-invalid';
    public const string REASON_RESOURCE_LIMIT_EXCEEDED = 'routing-route-resource-limit-exceeded';
    public const string REASON_METADATA_UNSAFE = 'routing-route-metadata-unsafe';

    /**
     * @var array<string, true>
     */
    private const array REASONS = [
        self::REASON_INVALID => true,
        self::REASON_PROVIDER_INVALID => true,
        self::REASON_PROVIDER_OUTPUT_INVALID => true,
        self::REASON_PROVIDER_IDENTITY_DUPLICATE => true,
        self::REASON_ROUTE_NAME_DUPLICATE => true,
        self::REASON_MATCHING_DOMAIN_EQUIVALENT => true,
        self::REASON_DECLARATION_UNSUPPORTED => true,
        self::REASON_TEMPLATE_INVALID => true,
        self::REASON_METHOD_INVALID => true,
        self::REASON_REQUIREMENT_INVALID => true,
        self::REASON_RESOURCE_LIMIT_EXCEEDED => true,
        self::REASON_METADATA_UNSAFE => true,
    ];

    private function __construct(
        private readonly string $reason,
    ) {
        if (!isset(self::REASONS[$reason])) {
            throw new \InvalidArgumentException('routing-route-definition-reason-invalid');
        }

        parent::__construct(self::ERROR_CODE . ': ' . $reason);
    }

    public static function withReason(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
