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

namespace Coretsia\Foundation\Tests\Contract;

use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\ContainerDiagnostics;
use PHPUnit\Framework\TestCase;

final class ContainerDiagnosticsDoesNotLeakSecretsContractTest extends TestCase
{
    private const string SECRET_CONFIG_VALUE = 'env-secret-value-must-not-leak';
    private const string SECRET_CONSTRUCTOR_VALUE = 'constructor-token-must-not-leak';
    private const string SECRET_FACTORY_VALUE = 'factory-token-must-not-leak';
    private const string SECRET_AUTHORIZATION_HEADER = 'Bearer diagnostics-secret-token';
    private const string SECRET_COOKIE_HEADER = 'session=diagnostics-secret-cookie';
    private const string SECRET_META_TOKEN = 'tag-meta-token-must-not-leak';

    public function testDiagnosticsDoesNotDumpConfigInstancesFactoriesConstructorArgsOrTagMeta(): void
    {
        $json = ContainerDiagnostics::fromBuilder(self::builderWithSecretCarriers())->toJson();

        self::assertSame(
            "{\"schemaVersion\":\"coretsia.foundation.containerDiagnostics.v1\",\"services\":[\"service.factory\",\"service.instance\"],\"tags\":{\"health.check\":[{\"id\":\"service.factory\",\"priority\":0}],\"kernel.reset\":[{\"id\":\"service.instance\",\"priority\":10}]}}\n",
            $json,
        );
    }

    public function testDiagnosticsDoesNotLeakSecretValues(): void
    {
        $json = ContainerDiagnostics::fromBuilder(self::builderWithSecretCarriers())->toJson();

        foreach (self::forbiddenValues() as $forbiddenValue) {
            self::assertStringNotContainsString($forbiddenValue, $json);
        }
    }

    public function testDiagnosticsDoesNotSerializeSecretLikeKeysOrRuntimeInternals(): void
    {
        $json = ContainerDiagnostics::fromBuilder(self::builderWithSecretCarriers())->toJson();

        foreach (self::forbiddenFragments() as $forbiddenFragment) {
            self::assertStringNotContainsString($forbiddenFragment, $json);
        }
    }

    private static function builderWithSecretCarriers(): ContainerBuilder
    {
        $builder = new ContainerBuilder(config: [
            'foundation' => [
                'container' => [
                    'autowire_concrete' => true,
                    'allow_reflection_for_concrete' => true,
                ],
                'unsafe_payload' => [
                    'api_key' => self::SECRET_CONFIG_VALUE,
                ],
            ],
        ]);

        $builder->instance(
            'service.instance',
            new ContainerDiagnosticsSecretCarrier(self::SECRET_CONSTRUCTOR_VALUE),
        );

        $builder->factory(
            'service.factory',
            static fn (): ContainerDiagnosticsSecretCarrier => new ContainerDiagnosticsSecretCarrier(
                self::SECRET_FACTORY_VALUE,
            ),
        );

        $builder->tag(
            tag: 'kernel.reset',
            serviceId: 'service.instance',
            priority: 10,
            meta: [
                'Authorization' => self::SECRET_AUTHORIZATION_HEADER,
                'Cookie' => self::SECRET_COOKIE_HEADER,
                'token' => self::SECRET_META_TOKEN,
            ],
        );

        $builder->tag(
            tag: 'health.check',
            serviceId: 'service.factory',
            priority: 0,
            meta: [
                'api_key' => self::SECRET_META_TOKEN,
            ],
        );

        return $builder;
    }

    /**
     * @return list<string>
     */
    private static function forbiddenValues(): array
    {
        return [
            self::SECRET_CONFIG_VALUE,
            self::SECRET_CONSTRUCTOR_VALUE,
            self::SECRET_FACTORY_VALUE,
            self::SECRET_AUTHORIZATION_HEADER,
            self::SECRET_COOKIE_HEADER,
            self::SECRET_META_TOKEN,
        ];
    }

    /**
     * @return list<string>
     */
    private static function forbiddenFragments(): array
    {
        return [
            'Authorization',
            'Cookie',
            'api_key',
            'token',
            'unsafe_payload',
            'constructor-token',
            'factory-token',
            ContainerDiagnosticsSecretCarrier::class,
        ];
    }
}

final readonly class ContainerDiagnosticsSecretCarrier
{
    public function __construct(
        private string $secret,
    ) {
    }

    public function secret(): string
    {
        return $this->secret;
    }
}
