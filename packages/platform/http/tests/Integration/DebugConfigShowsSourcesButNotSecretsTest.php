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
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Config\ArrayConfigRepository;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Platform\Http\Debug\DebugConfigHandler;
use Coretsia\Platform\Http\Debug\DebugGuard;
use Coretsia\Platform\Http\Exception\DebugForbiddenException;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class DebugConfigShowsSourcesButNotSecretsTest extends TestCase
{
    private const string TOKEN = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testExactPublicTypeAndEffectiveSourceProjection(string $mode): void
    {
        $sources = [
            new ConfigValueSource(
                type: ConfigSourceType::PackageDefault,
                root: 'kernel',
                sourceId: 'private-package/config/kernel.php',
                precedence: 0,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::ApplicationConfig,
                root: 'kernel',
                sourceId: 'private-app/config/kernel.php',
                path: 'private-app/config/kernel.php',
                keyPath: 'kernel.uow.attributes.max_depth',
                precedence: 10,
            ),
            new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: 'kernel',
                sourceId: 'PRIVATE_ENV_NAME',
                keyPath: 'kernel.uow.attributes.max_keys',
                precedence: 20,
                redacted: true,
                meta: ['length' => 987, 'hash' => \str_repeat('b', 64)],
            ),
        ];
        $fixture = $this->boot($mode, ['kernel', 'http.metrics', 'http.debug'], $sources);

        try {
            $repository = $fixture->container->get(ConfigRepositoryInterface::class);
            self::assertInstanceOf(ArrayConfigRepository::class, $repository);
            foreach (
                [
                    [
                        'kernel.runtime.http_driver',
                        'string',
                        ['precedence' => 0, 'redacted' => false, 'type' => 'package_default'],
                    ],
                    [
                        'kernel.uow.attributes.max_depth',
                        'int',
                        ['precedence' => 10, 'redacted' => false, 'type' => 'application_config'],
                    ],
                    [
                        'kernel.uow.attributes.max_keys',
                        'int',
                        ['precedence' => 20, 'redacted' => true, 'type' => 'env'],
                    ],
                    [
                        'kernel.uow.attributes',
                        'map',
                        ['precedence' => 0, 'redacted' => false, 'type' => 'package_default'],
                    ],
                    ['http.metrics.enabled', 'bool', null],
                ] as [$path, $type, $source]
            ) {
                $body = self::assertProjection($this->request($fixture, 'key=' . $path), $type, $source);
                foreach (
                    [
                        'private-app',
                        'private-package',
                        'PRIVATE_ENV_NAME',
                        '987',
                        \str_repeat('b', 64),
                        'http.classic',
                    ] as $private
                ) {
                    self::assertStringNotContainsString($private, $body);
                }
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testAllPublicValueTypesAndMissingSourceAreIndependentOfRawValues(string $mode): void
    {
        $fixture = $this->boot($mode, ['sample']);
        try {
            $repository = new ArrayConfigRepository([
                'sample' => [
                    'nil' => null,
                    'flag' => false,
                    'number' => 987654,
                    'text' => 'PRIVATE_VALUE',
                    'items' => ['PRIVATE_FIRST', 'PRIVATE_SECOND'],
                    'map' => ['private_member' => 'PRIVATE_VALUE'],
                ],
            ], []);
            $handler = $this->handler($fixture, $repository);
            foreach (
                [
                    'nil' => 'null',
                    'flag' => 'bool',
                    'number' => 'int',
                    'text' => 'string',
                    'items' => 'list',
                    'map' => 'map',
                ] as $key => $type
            ) {
                $response = $handler->handle($fixture->request('GET', '/_debug/config', 'key=sample.' . $key));
                self::assertInstanceOf(ResponseInterface::class, $response);
                self::assertProjection($response, $type, null);
            }
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testOriginalQueryAndExactOrSegmentBoundaryAllowlist(string $mode): void
    {
        $fixture = $this->boot($mode, ['kernel.uow.attributes', 'http.metrics.enabled', 'http']);
        try {
            foreach (
                [
                    'key=kernel.uow.attributes',
                    'key=kernel.uow.attributes.max_keys',
                    'key=http.metrics.enabled',
                ] as $query
            ) {
                self::assertSame(200, $this->request($fixture, $query)->getStatusCode(), $query);
            }
            foreach (
                [
                    '',
                    'key=',
                    'KEY=kernel.uow.attributes',
                    'key=kernel.uow.attributes_extra',
                    'key=kernel.uow',
                    'key=kernel.uow.attributes..max_keys',
                    'key=kernel.uow.attributes[0]',
                    'key[]=kernel.uow.attributes',
                    'key%5B%5D=kernel.uow.attributes',
                    'key=kernel%2Euow.attributes',
                    'key=kernel.uow.attributes&key=kernel.uow.attributes',
                    'key=kernel.uow.attributes&key=kernel.uow.attributes.max_keys',
                    'key=kernel.uow.attributes&other=x',
                    'key=kernel.uow.attributes;key=kernel.uow.attributes',
                    'key=kernel.uow.attributes' . \str_repeat('.segment', 40),
                    'key=http.debug',
                    'key=http.debug.token_sha256',
                ] as $query
            ) {
                self::assertSame(403, $this->request($fixture, $query)->getStatusCode(), $query);
            }
            $before = $fixture->recordingMeter()->incrementsFor('http.debug_total');
            $warnings = $fixture->logger()->recordsFor('http.debug.blocked');
            $missing = $this->request($fixture, 'key=kernel.uow.attributes.not_present');
            self::assertSame(404, $missing->getStatusCode());
            self::assertSame('', (string) $missing->getBody());
            self::assertSame($before, $fixture->recordingMeter()->incrementsFor('http.debug_total'));
            self::assertSame($warnings, $fixture->logger()->recordsFor('http.debug.blocked'));
        } finally {
            $fixture->close();
        }

        $fixture = $this->boot($mode, []);
        try {
            self::assertSame(403, $this->request($fixture, 'key=kernel.uow.attributes')->getStatusCode());
        } finally {
            $fixture->close();
        }
    }

    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testSensitiveSegmentsAndDebugSubtreeAreDeniedBeforeRepositoryAccess(string $mode): void
    {
        $fixture = $this->boot($mode, ['http', 'sample']);
        try {
            $repository = $this->createMock(ConfigRepositoryInterface::class);
            foreach (['has', 'get', 'sourceOf', 'all', 'explain'] as $method) {
                $repository->expects(self::never())->method($method);
            }
            $handler = $this->handler($fixture, $repository);

            foreach (
                [
                    'sample.password',
                    'sample.api_key',
                    'sample.token.child',
                    'sample.Authorization',
                    'http.debug',
                    'http.debug.endpoints.enabled',
                    'http.debug.token_sha256',
                ] as $key
            ) {
                try {
                    $handler->handle($fixture->request('GET', '/_debug/config', 'key=' . $key));
                    self::fail('Expected denial before repository access.');
                } catch (DebugForbiddenException $exception) {
                    self::assertSame('http-debug-forbidden', $exception->reason());
                }
            }
        } finally {
            $fixture->close();
        }
    }

    public function testActualSourceHostAndArtifactOnlyRepositoriesProduceIdenticalDiagnostics(): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-debug-config-' . \bin2hex(\random_bytes(12));
        self::assertTrue(\mkdir($root . '/config', 0700, true));
        $fixture = null;

        try {
            self::writeConfig($root . '/config/app.php', [
                'appEnv' => 'local',
                'moduleOverrides' => [
                    'console' => [
                        'include' => [],
                        'exclude' => ['platform.http', 'platform.http-app', 'platform.problem-details'],
                    ],
                ],
            ]);
            self::writeConfig($root . '/config/logging.php', ['driver' => 'null']);
            self::writeConfig($root . '/config/kernel.php', [
                'uow' => ['attributes' => ['max_depth' => 12, 'max_keys' => 150]],
            ]);
            self::assertNotFalse(\file_put_contents($root . '/.env', "KERNEL_UOW_ATTRIBUTES_MAX_KEYS=123\n"));

            $sourceHost = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
            $ops = $sourceHost->get(KernelOpsInterface::class);
            self::assertInstanceOf(KernelOpsInterface::class, $ops);
            self::assertSame('success', $ops->compileConfig(new KernelOpsRequest('console'))->outcome());
            $artifactHost = new ArtifactRuntimeBooter()->boot(
                new ArtifactRuntimeInput($root, $root . '/var/cache/console'),
            );
            $source = $sourceHost->get(ConfigRepositoryInterface::class);
            $artifact = $artifactHost->get(ConfigRepositoryInterface::class);
            self::assertInstanceOf(ArrayConfigRepository::class, $source);
            self::assertInstanceOf(ArrayConfigRepository::class, $artifact);
            self::assertNotEmpty($artifact->explain());
            self::assertSame(
                \array_map(static fn (ConfigValueSource $item): array => $item->toArray(), $source->explain()),
                \array_map(static fn (ConfigValueSource $item): array => $item->toArray(), $artifact->explain()),
            );

            // The HTTP projection consumes the real runtime repositories; the
            // isolated console target needs no production PSR-17 implementation.
            $fixture = $this->boot('source', ['kernel']);
            $sourceHandler = $this->handler($fixture, $source);
            $artifactHandler = $this->handler($fixture, $artifact);
            foreach (
                [
                    ['kernel.runtime.http_driver', 'http.classic', 'string', ConfigSourceType::PackageDefault],
                    ['kernel.uow.attributes.max_depth', 12, 'int', ConfigSourceType::ApplicationConfig],
                    ['kernel.uow.attributes.max_keys', 123, 'int', ConfigSourceType::Env],
                ] as [$key, $value, $type, $sourceType]
            ) {
                self::assertSame($value, $source->get($key));
                self::assertSame($value, $artifact->get($key));
                $effective = $source->sourceOf($key);
                $hydrated = $artifact->sourceOf($key);
                self::assertInstanceOf(ConfigValueSource::class, $effective);
                self::assertInstanceOf(ConfigValueSource::class, $hydrated);
                self::assertSame($sourceType, $hydrated->type());
                self::assertSame($effective->toArray(), $hydrated->toArray());
                $expected = [
                    'precedence' => $effective->precedence(),
                    'redacted' => $effective->isRedacted(),
                    'type' => $sourceType->value,
                ];
                $request = $fixture->request('GET', '/_debug/config', 'key=' . $key);
                $left = $sourceHandler->handle($request);
                $right = $artifactHandler->handle($request);
                self::assertInstanceOf(ResponseInterface::class, $left);
                self::assertInstanceOf(ResponseInterface::class, $right);
                self::assertSame(
                    self::assertProjection($left, $type, $expected),
                    self::assertProjection($right, $type, $expected),
                );
            }
        } finally {
            $fixture?->close();
            self::removeTree($root);
        }
    }

    /**
     * @param list<string> $allowlist
     * @param list<ConfigValueSource> $sources
     */
    private function boot(string $mode, array $allowlist, array $sources = []): SystemEndpointRuntimeFixture
    {
        return SystemEndpointRuntimeFixture::boot($mode, $this->createStub(...), [
            'appEnv' => 'local',
            'problemDetails' => true,
            'sources' => $sources,
            'http' => [
                'debug' => [
                    'endpoints' => ['enabled' => true],
                    'token_sha256' => \hash('sha256', self::TOKEN),
                    'config' => ['allowlist' => $allowlist],
                ],
            ],
        ]);
    }

    private function handler(
        SystemEndpointRuntimeFixture $fixture,
        ConfigRepositoryInterface $repository,
    ): DebugConfigHandler {
        return new DebugConfigHandler(
            $repository,
            $fixture->container->get(DebugGuard::class),
            $fixture->container->get(SensitiveDataRedactorInterface::class),
            $fixture->container->get(SensitiveKeyClassifierInterface::class),
            $fixture->container->get(FallbackResponses::class),
        );
    }

    private function request(SystemEndpointRuntimeFixture $fixture, string $query): ResponseInterface
    {
        return $fixture->kernel()->handle(
            $fixture->request(
                'GET',
                '/_debug/config',
                $query,
                ['X-Debug-Token' => [self::TOKEN]],
                serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
            ),
        );
    }

    /** @param array<string, mixed>|null $source */
    private static function assertProjection(ResponseInterface $response, string $type, ?array $source): string
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $body = (string) $response->getBody();
        $expected = ['source' => $source, 'type' => $type];
        self::assertSame($expected, \json_decode($body, true, 512, \JSON_THROW_ON_ERROR));
        self::assertSame(\json_encode($expected, \JSON_THROW_ON_ERROR) . "\n", $body);

        return $body;
    }

    /** @param array<string, mixed> $config */
    private static function writeConfig(string $path, array $config): void
    {
        self::assertNotFalse(\file_put_contents($path, '<?php return ' . \var_export($config, true) . ";\n"));
    }

    private static function removeTree(string $path): void
    {
        if (\is_dir($path) && !\is_link($path)) {
            foreach (new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS) as $entry) {
                self::removeTree($entry->getPathname());
            }
            \rmdir($path);
        } elseif (\file_exists($path) || \is_link($path)) {
            \unlink($path);
        }
    }
}
