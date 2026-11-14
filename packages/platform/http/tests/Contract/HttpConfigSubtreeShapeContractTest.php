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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;

final class HttpConfigSubtreeShapeContractTest extends TestCase
{
    public function testDefaultsAreStrictSubtreeOnlyWithNineEmptyManualSlots(): void
    {
        $http = self::defaults();
        $rules = self::rules();

        self::assertArrayNotHasKey('http', $http);
        self::assertSame('external', $http['factory']);
        self::assertSame([
            'system_pre',
            'system',
            'system_post',
            'app_pre',
            'app',
            'app_post',
            'route_pre',
            'route',
            'route_post',
        ], \array_keys($http['middleware']));
        foreach ($http['middleware'] as $slot => $services) {
            self::assertSame([], $services, $slot);
        }
        self::assertSame(1, $rules['schemaVersion']);
        self::assertSame('http', $rules['configRoot']);
        self::assertFalse($rules['additionalKeys']);
        self::assertSame(\array_keys($rules['keys']), \array_keys($http));
        self::assertTrue(self::validate($http)->isSuccess());
        self::assertNoReservedDirectives($http);
        self::assertArrayNotHasKey('auto', $http['middleware']);
        self::assertArrayNotHasKey('auto', $rules['keys']['middleware']['keys']);
    }

    #[DataProvider('supportedFactories')]
    public function testExactlyTheSupportedFactorySelectionsPassCanonicalValidation(string $factory): void
    {
        $http = self::defaults();
        $http['factory'] = $factory;

        self::assertTrue(self::validate($http)->isSuccess());
    }

    /** @return iterable<string, array{string}> */
    public static function supportedFactories(): iterable
    {
        foreach (['external', 'nyholm', 'guzzlehttp', 'laminas', 'slim'] as $factory) {
            yield $factory => [$factory];
        }
    }

    #[DataProvider('rejectedFactories')]
    public function testUnsupportedAutoAndArbitraryFactorySelectorsFailCanonicalValidation(mixed $factory): void
    {
        $http = self::defaults();
        $http['factory'] = $factory;
        $result = self::validate($http);

        self::assertTrue($result->isFailure());
        self::assertNotEmpty($result->violations());
    }

    /** @return iterable<string, array{mixed}> */
    public static function rejectedFactories(): iterable
    {
        yield 'empty' => [''];
        yield 'auto' => ['auto'];
        yield 'uppercase' => ['Nyholm'];
        yield 'arbitrary-class' => ['App\\Http\\Psr17Factory'];
        yield 'unrecognized' => ['custom'];
        yield 'null' => [null];
        yield 'integer' => [123];
        yield 'array' => [['factory' => 'nyholm']];
    }

    public function testUnknownNestedKeysAndReservedDirectivesFailCanonicalValidation(): void
    {
        $mutations = [
            static function (array &$http): void {
                $http['http'] = [];
            },
            static function (array &$http): void {
                $http['@remove'] = true;
            },
            static function (array &$http): void {
                $http['middleware']['auto'] = [];
            },
            static function (array &$http): void {
                $http['middleware']['system']['@replace'] = [];
            },
            static function (array &$http): void {
                $http['tracing']['@enabled'] = true;
            },
            static function (array &$http): void {
                $http['request']['json']['extra'] = true;
            },
            static function (array &$http): void {
                $http['correlation']['enabled'] = 'true';
            },
        ];
        foreach ($mutations as $mutate) {
            $http = self::defaults();
            $mutate($http);
            self::assertTrue(self::validate($http)->isFailure());
        }
    }

    public function testFactoryChangeFlowsIntoCanonicalCompiledConfigAndContainerInputs(): void
    {
        $configBuilder = new CompiledConfigBuilder(new ArtifactEnvelopeFactory());
        $configPayloads = [];
        $descriptors = [];
        $compiledAliases = [];

        foreach (['nyholm', 'slim'] as $selection) {
            $http = self::defaults();
            $http['factory'] = $selection;
            self::assertTrue(self::validate($http)->isSuccess());
            $config = ['http' => $http];

            $envelope = $configBuilder->build([
                'config' => $config,
                'owners' => [],
                'sources' => [],
                'envOverlayMappings' => [],
                'configSourceFiles' => [],
                'validation' => ConfigValidationResult::success(),
                'validationSubjects' => ['validated' => [], 'unvalidated' => []],
            ], \str_repeat('a', 64));
            $configPayloads[$selection] = $envelope['payload']['config'];

            $builder = new ContainerDefinitionBuilder();
            new HttpServiceProvider()->define($builder, new ContainerDefinitionContext($config));
            $set = $builder->build();
            $descriptors[$selection] = $set->toDescriptorStream();
            $compiledAliases[$selection] = [];
            foreach ($descriptors[$selection] as $operation) {
                if (($operation['kind'] ?? null) === 'alias') {
                    $compiledAliases[$selection][$operation['alias']] = $operation['serviceId'];
                }
            }
        }

        self::assertSame('nyholm', $configPayloads['nyholm']['http']['factory']);
        self::assertSame('slim', $configPayloads['slim']['http']['factory']);
        self::assertNotSame($configPayloads['nyholm'], $configPayloads['slim']);
        self::assertNotSame($descriptors['nyholm'], $descriptors['slim']);
        self::assertNotSame(
            $compiledAliases['nyholm'][ResponseFactoryInterface::class],
            $compiledAliases['slim'][ResponseFactoryInterface::class],
        );
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return require __DIR__ . '/../../config/http.php';
    }

    /** @return array<string, mixed> */
    private static function rules(): array
    {
        return require __DIR__ . '/../../config/rules.php';
    }

    /** @param array<string, mixed> $http */
    private static function validate(array $http): ConfigValidationResult
    {
        $ruleset = ConfigRuleset::fromArray('http', self::rules());

        return new ConfigValidator()->validate(['http' => $http], [$ruleset]);
    }

    /** @param array<array-key, mixed> $values */
    private static function assertNoReservedDirectives(array $values): void
    {
        foreach ($values as $key => $value) {
            if (\is_string($key)) {
                self::assertFalse(\str_starts_with($key, '@'));
            }
            if (\is_array($value)) {
                self::assertNoReservedDirectives($value);
            }
        }
    }
}
