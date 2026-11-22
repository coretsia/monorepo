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

namespace Coretsia\Platform\Http\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Debug\DebugGuard;
use Coretsia\Platform\Http\Middleware\TrustedProxyMiddleware;
use Coretsia\Platform\Http\Proxy\TrustedProxyPolicy;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;

final class DebugEndpointsGuardTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testAllExactRoutesRequireFrozenDevelopmentEnvironmentAndSelectedProblemDetails(string $mode): void
    {
        $previous = \getenv('CORETSIA_BOOT_APP_ENV');
        $previousEnv = $_ENV;
        $previousServer = $_SERVER;

        try {
            foreach (
                [
                    ['local', false, true, 'prod', 404],
                    ['prod', true, true, 'local', 404],
                    ['test', true, true, 'dev', 404],
                    ['Local', true, true, 'local', 404],
                    ['local', true, false, 'prod', 404],
                    ['local', true, true, 'prod', 200],
                    ['dev', true, true, 'prod', 200],
                ] as [$appEnv, $enabled, $problemDetails, $processEnv, $status]
            ) {
                self::assertTrue(\putenv('CORETSIA_BOOT_APP_ENV=' . $processEnv));
                $_ENV['CORETSIA_BOOT_APP_ENV'] = $processEnv;
                $_SERVER['CORETSIA_BOOT_APP_ENV'] = $processEnv;
                $fixture = $this->boot($mode, [
                    'appEnv' => $appEnv,
                    'problemDetails' => $problemDetails,
                    'http' => ['debug' => ['endpoints' => ['enabled' => $enabled]]],
                ]);

                try {
                    self::assertSame($appEnv, $fixture->container->get(RuntimeDiagnosticsInput::class)->appEnv());
                    self::assertSame(
                        'local',
                        $fixture->container->get(ConfigRepositoryInterface::class)->get('kernel.boot.default_env'),
                    );
                    foreach (['headers', 'modules', 'config', 'cache'] as $operation) {
                        $response = $fixture->kernel()->handle(
                            $fixture->request(
                                'GET',
                                '/_debug/' . $operation,
                                $operation === 'config' ? 'key=kernel.boot.default_env' : '',
                                ['X-Debug-Token' => [self::TOKEN]],
                                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                            ),
                        );
                        self::assertSame($status, $response->getStatusCode(), $operation);
                        if ($status === 404) {
                            self::assertSame('', (string) $response->getBody());
                        }
                    }
                } finally {
                    $fixture->close();
                }
            }

            // No debug override at all: the shipped default must remain disabled.
            $fixture = SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), [
                'appEnv' => 'local',
                'problemDetails' => true,
            ]);
            try {
                foreach (['headers', 'modules', 'config', 'cache'] as $operation) {
                    $response = $fixture->kernel()->handle($fixture->request('GET', '/_debug/' . $operation));
                    self::assertSame(404, $response->getStatusCode());
                    self::assertSame('', (string) $response->getBody());
                }
            } finally {
                $fixture->close();
            }
        } finally {
            \putenv($previous === false ? 'CORETSIA_BOOT_APP_ENV' : 'CORETSIA_BOOT_APP_ENV=' . $previous);
            $_ENV = $previousEnv;
            $_SERVER = $previousServer;
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testTokenAndImmediatePeerMustBeCanonicalAndAuthorized(string $mode): void
    {
        $fixture = $this->boot($mode);

        try {
            foreach (
                [
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => '127.0.0.1'], 200],
                    [['x-debug-token' => [self::TOKEN]], ['REMOTE_ADDR' => '::1'], 200],
                    [[], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => ['']], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [\str_repeat('b', 64)]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [\strtoupper(self::TOKEN)]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [\substr(self::TOKEN, 1)]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [\str_repeat('g', 64)]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN . "\n"]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN . ' ']], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN . ',' . self::TOKEN]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN, self::TOKEN]], ['REMOTE_ADDR' => '127.0.0.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN]], [], 403],
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => 42], 403],
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => 'invalid'], 403],
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => '192.0.2.1'], 403],
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => '0:0:0:0:0:0:0:1'], 403],
                    [['X-Debug-Token' => [self::TOKEN]], ['REMOTE_ADDR' => '127.000.0.1'], 403],
                ] as [$headers, $server, $status]
            ) {
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/cache',
                        headers: $headers,
                        serverParams: $server,
                    ),
                );
                self::assertSame($status, $response->getStatusCode());
            }

            self::assertSame('::1', TrustedProxyPolicy::canonicalIp('0:0:0:0:0:0:0:1'));
            self::assertSame('127.0.0.1', TrustedProxyPolicy::canonicalIp('127.0.0.1'));
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testOnlyValidatedTrustedProxyClientMetadataCanAuthorize(string $mode): void
    {
        foreach ([true, false] as $enabled) {
            $fixture = $this->boot($mode, [
                'http' => [
                    'proxy' => [
                        'enabled' => $enabled,
                        'trusted_proxies' => ['192.0.2.10', '2001:db8::10', '127.0.0.1'],
                    ],
                ],
            ]);

            try {
                foreach (
                    [
                        ['192.0.2.10', ['X-Forwarded-For' => ['127.0.0.1']], [], $enabled ? 200 : 403],
                        ['2001:db8::10', ['Forwarded' => ['for="[::1]"']], [], $enabled ? 200 : 403],
                        ['2001:db8::10', ['Forwarded' => ['for="[0:0:0:0:0:0:0:1]"']], [], $enabled ? 200 : 403],
                        ['192.0.2.10', ['Forwarded' => ['for=127.0.0.1'], 'X-Forwarded-For' => ['127.0.0.1']], [], 403],
                        ['192.0.2.10', ['X-Forwarded-For' => ['127.0.0.1, 198.51.100.5']], [], 403],
                        ['192.0.2.10', ['X-Forwarded-For' => ['invalid']], [], 403],
                        ['192.0.2.10', ['X-Forwarded-Proto' => ['https']], [], 403],
                        ['192.0.2.10', [], ['client_ip' => '127.0.0.1'], 403],
                        ['127.0.0.1', [], [], 403],
                        ['198.51.100.5', ['X-Forwarded-For' => ['127.0.0.1']], ['client_ip' => '127.0.0.1'], 403],
                        ['::1', ['X-Forwarded-For' => ['198.51.100.5']], ['client_ip' => '198.51.100.5'], 200],
                    ] as [$peer, $forwarding, $metadata, $status]
                ) {
                    $response = $fixture->kernel()->handle(
                        $fixture->request(
                            'GET',
                            '/_debug/cache',
                            headers: ['X-Debug-Token' => [self::TOKEN], ...$forwarding],
                            serverParams: ['REMOTE_ADDR' => $peer],
                            attributes: [TrustedProxyMiddleware::ATTRIBUTE => $metadata],
                        ),
                    );
                    self::assertSame($status, $response->getStatusCode(), $peer);
                }
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testExplicitIpAllowlistUsesExactCanonicalAddresses(string $mode): void
    {
        $fixture = $this->boot($mode, [
            'http' => [
                'debug' => [
                    'allowlist' => ['192.0.2.20', '2001:db8::20'],
                ],
            ],
        ]);
        try {
            foreach (
                [
                    ['192.0.2.20', 200],
                    ['2001:db8::20', 200],
                    ['192.0.2.21', 403],
                    ['2001:db8::21', 403],
                    ['2001:DB8::20', 403],
                    ['127.0.0.1', 403],
                    ['::1', 403],
                ] as [$peer, $status]
            ) {
                $response = $fixture->kernel()->handle(
                    $fixture->request(
                        'GET',
                        '/_debug/cache',
                        headers: ['X-Debug-Token' => [self::TOKEN]],
                        serverParams: ['REMOTE_ADDR' => $peer],
                    ),
                );
                self::assertSame($status, $response->getStatusCode(), $peer);
            }
            $source = \file_get_contents(new \ReflectionClass(DebugGuard::class)->getFileName());
            self::assertIsString($source);
            self::assertStringContainsString('TrustedProxyPolicy::canonicalIp(', $source);
            foreach (['inet_pton(', 'inet_ntop(', 'FILTER_VALIDATE_IP'] as $duplicate) {
                self::assertStringNotContainsString($duplicate, $source);
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testDisabledOperationsAndEmptyCredentialsDenyWhileAliasesRemainUnmatched(string $mode): void
    {
        foreach (
            [
                ['paths' => ['enabled' => ['headers']]],
                ['paths' => ['enabled' => []]],
                ['allowlist' => []],
                ['token_sha256' => ''],
            ] as $debug
        ) {
            $fixture = $this->boot($mode, ['http' => ['debug' => $debug]]);

            try {
                foreach (['headers', 'modules', 'config', 'cache'] as $operation) {
                    $response = $fixture->kernel()->handle(
                        $fixture->request(
                            'GET',
                            '/_debug/' . $operation,
                            $operation === 'config' ? 'key=kernel.boot.default_env' : '',
                            ['X-Debug-Token' => [self::TOKEN]],
                            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                        ),
                    );
                    $expected = $operation === 'headers' && $debug === ['paths' => ['enabled' => ['headers']]]
                        ? 200
                        : 403;
                    self::assertSame($expected, $response->getStatusCode(), $operation);
                }
                foreach (
                    [
                        ['GET', '/_debug/cache', 403],
                        ['POST', '/_debug/cache', 404],
                        ['HEAD', '/_debug/cache', 404],
                        ['GET', '/_debug/unknown', 404],
                        ['GET', '/_debug', 404],
                        ['GET', '/_debug/cache/', 404],
                        ['GET', '/_debug//cache', 404],
                        ['GET', '/_debug/%63ache', 404],
                        ['GET', '/%5fdebug/cache', 404],
                    ] as [$method, $path, $status]
                ) {
                    $response = $fixture->kernel()->handle(
                        $fixture->request(
                            $method,
                            $path,
                            headers: ['X-Debug-Token' => [self::TOKEN]],
                            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                        ),
                    );
                    self::assertSame($status, $response->getStatusCode(), $path);
                    if ($status === 404) {
                        self::assertSame('', (string) $response->getBody());
                    }
                }
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testEarlierMiddlewareAndMaintenancePrecedeDebugAuthorization(string $mode): void
    {
        foreach (
            [
                [['earlier' => true], 202],
                [['http' => ['maintenance' => ['enabled' => true]]], 503],
            ] as [$options, $status]
        ) {
            $fixture = $this->boot($mode, $options);
            try {
                if ($status === 503) {
                    $fixture->maintenance()->enable(null);
                }
                $response = $fixture->kernel()->handle($fixture->request('GET', '/_debug/cache'));
                self::assertSame($status, $response->getStatusCode());
                self::assertSame([], $fixture->recordingMeter()->incrementsFor('http.debug_total'));
            } finally {
                $fixture->close();
            }
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testInvalidPolicyConfigurationIsRejectedEvenWhenDisabled(string $mode): void
    {
        foreach (
            [
                [['allowlist' => ['invalid']], 'http-debug-allowlist-invalid'],
                [['allowlist' => ['127.0.0.1/32']], 'http-debug-allowlist-invalid'],
                [['allowlist' => ['0:0:0:0:0:0:0:1']], 'http-debug-allowlist-invalid'],
                [['allowlist' => ['::1', '::1']], 'http-debug-allowlist-invalid'],
                [
                    ['allowlist' => \array_map(static fn (int $i): string => '192.0.2.' . $i, \range(1, 65))],
                    'http-debug-allowlist-invalid',
                ],
                [['allowlist' => [42]], 'http-debug-allowlist-invalid'],
                [['allowlist' => ['peer' => '::1']], 'http-config-invalid'],
                [['token_sha256' => 'invalid'], 'http-debug-token-sha256-invalid'],
                [['token_sha256' => \str_repeat('A', 64)], 'http-debug-token-sha256-invalid'],
                [['token_sha256' => \str_repeat('a', 63)], 'http-debug-token-sha256-invalid'],
                [['token_sha256' => \str_repeat('a', 65)], 'http-debug-token-sha256-invalid'],
                [['token_sha256' => 42], 'http-config-invalid'],
                [['paths' => ['enabled' => ['unknown']]], 'http-debug-paths-enabled-invalid'],
                [['paths' => ['enabled' => ['cache', 'cache']]], 'http-debug-paths-enabled-invalid'],
                [
                    ['paths' => ['enabled' => ['headers', 'modules', 'config', 'cache', 'cache']]],
                    'http-debug-paths-enabled-invalid',
                ],
                [['paths' => ['enabled' => [42]]], 'http-debug-paths-enabled-invalid'],
                [['paths' => ['enabled' => ['operation' => 'cache']]], 'http-config-invalid'],
                [['config' => ['allowlist' => ['kernel..boot']]], 'http-debug-config-allowlist-invalid'],
                [['config' => ['allowlist' => ['kernel', 'kernel']]], 'http-debug-config-allowlist-invalid'],
                [
                    ['config' => ['allowlist' => ['kernel.' . \str_repeat('a', 65)]]],
                    'http-debug-config-allowlist-invalid',
                ],
                [
                    ['config' => ['allowlist' => \array_map(static fn (int $i): string => 'key' . $i, \range(1, 65))]],
                    'http-debug-config-allowlist-invalid',
                ],
                [['config' => ['allowlist' => [42]]], 'http-debug-config-allowlist-invalid'],
                [['config' => ['allowlist' => ['prefix' => 'kernel']]], 'http-config-invalid'],
            ] as [$invalid, $reason]
        ) {
            foreach ([false, true] as $enabled) {
                $fixture = $this->boot($mode, [
                    'http' => [
                        'debug' => [
                            'endpoints' => ['enabled' => $enabled],
                            ...$invalid,
                        ],
                    ],
                ]);
                try {
                    try {
                        $fixture->container->get(DebugGuard::class);
                        self::fail('Expected invalid debug policy to be rejected.');
                    } catch (ContainerExceptionInterface $exception) {
                        if ($mode === 'compiled') {
                            self::assertSame('container-compiled-factory-failed', $exception->getMessage());
                            self::assertNull($exception->getPrevious());
                        } else {
                            self::assertInstanceOf(\InvalidArgumentException::class, $exception->getPrevious());
                            self::assertSame($reason, $exception->getPrevious()->getMessage());
                        }
                    }
                } finally {
                    $fixture->close();
                }
            }
        }
    }

    /** @param array<string, mixed> $options */
    private function boot(string $mode, array $options = []): SystemEndpointRuntimeFixture
    {
        $defaults = [
            'appEnv' => 'local',
            'problemDetails' => true,
            'http' => [
                'debug' => [
                    'endpoints' => ['enabled' => true],
                    'token_sha256' => \hash('sha256', self::TOKEN),
                    'config' => ['allowlist' => ['kernel']],
                ],
            ],
        ];
        $merged = \array_replace_recursive($defaults, $options);
        // List overrides are replacements, never index-wise merges.
        $debug = $options['http']['debug'] ?? [];
        foreach (['allowlist', 'paths', 'config'] as $key) {
            if (\array_key_exists($key, $debug)) {
                $merged['http']['debug'][$key] = $debug[$key];
            }
        }

        return SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), $merged);
    }
}
