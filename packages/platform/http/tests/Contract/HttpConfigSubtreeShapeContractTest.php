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
use Coretsia\Foundation\Context\ContextStore;
use Coretsia\Foundation\Id\UlidGenerator;
use Coretsia\Kernel\Artifacts\ArtifactEnvelopeFactory;
use Coretsia\Kernel\Artifacts\Builders\CompiledConfigBuilder;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Http\RequestId\RequestIdPolicy;
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
            'factory',
            'middleware',
            'correlation',
            'request_id',
            'tracing',
            'metrics',
            'access_log',
            'proxy',
            'context',
            'hosts',
            'request',
        ], \array_keys($http));

        self::assertSame([
            'enabled' => false,
            'header_name' => 'X-Request-Id',
            'accept_from_upstream' => false,
            'max_length' => 128,
        ], $http['request_id']);
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

    public function testRequestIdMapAndAllDeclaredKeysAreRequired(): void
    {
        $http = self::defaults();
        unset($http['request_id']);

        self::assertTrue(self::validate($http)->isFailure());

        foreach (['enabled', 'header_name', 'accept_from_upstream', 'max_length'] as $key) {
            $http = self::defaults();
            unset($http['request_id'][$key]);

            self::assertTrue(self::validate($http)->isFailure(), $key);
        }
    }

    #[DataProvider('invalidRequestIdMaps')]
    public function testRequestIdMustBeAStrictMap(mixed $requestId): void
    {
        $http = self::defaults();
        $http['request_id'] = $requestId;

        self::assertTrue(self::validate($http)->isFailure());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidRequestIdMaps(): iterable
    {
        yield 'null' => [null];
        yield 'boolean' => [false];
        yield 'string' => ['disabled'];
        yield 'empty-map' => [[]];
        yield 'list' => [[false, 'X-Request-Id', false, 128]];
    }

    public function testRequestIdMapRejectsUnknownKeysAndReservedDirectives(): void
    {
        foreach (['unknown', '@enabled', '@replace', '@append', '@remove'] as $key) {
            $http = self::defaults();
            $http['request_id'][$key] = [];

            self::assertTrue(self::validate($http)->isFailure());
        }
    }

    #[DataProvider('invalidRequestIdValues')]
    public function testInvalidRequestIdValuesFailCanonicalValidationEvenWhenDisabled(
        string $key,
        mixed $value,
    ): void {
        $http = self::defaults();
        $http['request_id']['enabled'] = false;
        $http['request_id'][$key] = $value;

        $result = self::validate($http);

        self::assertTrue($result->isFailure());
        self::assertNotEmpty($result->violations());
    }

    /** @return iterable<string, array{string, mixed}> */
    public static function invalidRequestIdValues(): iterable
    {
        foreach (['enabled', 'accept_from_upstream'] as $key) {
            yield $key . '-string' => [$key, 'true'];
            yield $key . '-false-string' => [$key, 'false'];
            yield $key . '-integer' => [$key, 1];
            yield $key . '-zero' => [$key, 0];
            yield $key . '-float' => [$key, 1.0];
            yield $key . '-null' => [$key, null];
            yield $key . '-array' => [$key, []];
        }

        yield 'header-empty' => ['header_name', ''];
        yield 'header-space' => ['header_name', 'X Request Id'];
        yield 'header-tab' => ['header_name', "X-Request-Id\t"];
        yield 'header-newline' => ['header_name', "X-Request-Id\n"];
        yield 'header-integer' => ['header_name', 123];
        yield 'header-boolean' => ['header_name', false];
        yield 'header-null' => ['header_name', null];
        yield 'header-array' => ['header_name', []];

        yield 'length-below-minimum' => ['max_length', 25];
        yield 'length-above-maximum' => ['max_length', 129];
        yield 'length-string' => ['max_length', '128'];
        yield 'length-float' => ['max_length', 128.0];
        yield 'length-true' => ['max_length', true];
        yield 'length-false' => ['max_length', false];
        yield 'length-null' => ['max_length', null];
        yield 'length-array' => ['max_length', []];
    }

    #[DataProvider('validRequestIdLengthBoundaries')]
    public function testRequestIdLengthBoundariesPassCanonicalValidation(int $maxLength): void
    {
        foreach ([false, true] as $enabled) {
            foreach ([false, true] as $acceptFromUpstream) {
                $http = self::defaults();
                $http['request_id'] = [
                    'enabled' => $enabled,
                    'header_name' => 'X-Edge-Request-Id',
                    'accept_from_upstream' => $acceptFromUpstream,
                    'max_length' => $maxLength,
                ];

                self::assertTrue(self::validate($http)->isSuccess());
            }
        }
    }

    /** @return iterable<string, array{int}> */
    public static function validRequestIdLengthBoundaries(): iterable
    {
        yield 'minimum' => [26];
        yield 'maximum' => [128];
    }

    #[DataProvider('semanticallyInvalidRequestIdHeaderNames')]
    public function testRequestIdHeaderSemanticsRemainOwnedByRequestIdPolicy(string $headerName): void
    {
        $http = self::defaults();
        $http['request_id']['header_name'] = $headerName;

        self::assertTrue(self::validate($http)->isSuccess());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('http-request-id-header-invalid');

        new RequestIdPolicy(
            new ContextStore(),
            new UlidGenerator(),
            false,
            $headerName,
            false,
            128,
        );
    }

    /** @return iterable<string, array{string}> */
    public static function semanticallyInvalidRequestIdHeaderNames(): iterable
    {
        yield 'correlation-header' => ['X-Correlation-Id'];
        yield 'protected-header' => ['Authorization'];
        yield 'invalid-delimiter' => ['X_Request_Id'];
        yield 'invalid-token-character' => ['X:-Request-Id'];
        yield 'oversized-header' => [\str_repeat('A', 118) . '-Request-Id'];
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
