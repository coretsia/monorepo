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

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use Coretsia\Platform\Http\Debug\DebugCacheHandler;
use Coretsia\Platform\Http\Routing\FallbackResponses;
use Coretsia\Platform\Http\Tests\Support\SystemEndpointRuntimeFixture;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class DebugCacheUsesBootSelectedGenerationTest extends TestCase
{
    #[DataProviderExternal(SystemEndpointRuntimeFixture::class, 'modes')]
    public function testSourceAndSyntheticCompiledRuntimeReportUnavailableWithoutDiscovery(string $mode): void
    {
        $token = \str_repeat('a', 64);
        $fixture = SystemEndpointRuntimeFixture::boot(
            $mode,
            $this->createStub(...),
            [
                'appEnv' => 'local',
                'problemDetails' => true,
                'http' => [
                    'debug' => [
                        'endpoints' => ['enabled' => true],
                        'token_sha256' => \hash('sha256', $token),
                    ],
                ],
            ],
        );

        try {
            $diagnostics = $fixture->container->get(RuntimeDiagnosticsInput::class);
            self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
            self::assertFalse($diagnostics->artifactGenerationLoaded());
            for ($i = 0; $i < 2; ++$i) {
                self::assertSummary(
                    $fixture->kernel()->handle(
                        $fixture->request(
                            'GET',
                            '/_debug/cache',
                            headers: ['X-Debug-Token' => [$token]],
                            serverParams: ['REMOTE_ADDR' => '127.0.0.1'],
                        ),
                    ),
                    'unavailable',
                );
            }
        } finally {
            $fixture->close();
        }
    }

    public function testActualArtifactBootStateIsIndependentOfSubsequentPointerAndPayloadChanges(): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-debug-cache-' . \bin2hex(\random_bytes(12));
        self::assertTrue(\mkdir($root . '/config', 0700, true));
        $artifactRoot = $root . '/var/cache/console';
        $fixture = null;
        try {
            $sourceDiagnostics = self::compile($root, 'local');
            $pointer = $artifactRoot . '/current';
            $first = \file_get_contents($pointer);
            self::assertIsString($first);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\n\z/', $first);

            // This is an actual Kernel artifact-only boot, not a synthetic
            // RuntimeDiagnosticsInput with an asserted loaded state.
            $container = new ArtifactRuntimeBooter()->boot(new ArtifactRuntimeInput($root, $artifactRoot));
            $diagnostics = $container->get(RuntimeDiagnosticsInput::class);
            self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
            self::assertSame('local', $diagnostics->appEnv());
            self::assertTrue($diagnostics->artifactGenerationLoaded());

            // Reuse the canonical HTTP response construction and the existing
            // PSR-17 doubles, independently of the console compile target.
            $fixture = SystemEndpointRuntimeFixture::boot('source', $this->createStub(...));
            $responses = $fixture->container->get(FallbackResponses::class);
            self::assertInstanceOf(FallbackResponses::class, $responses);
            $sourceHandler = new DebugCacheHandler($sourceDiagnostics, $responses);
            $handler = new DebugCacheHandler(
                $diagnostics,
                $responses,
            );
            $before = self::snapshot($root);
            $unavailableBody = self::assertSummary($sourceHandler->handle(), 'unavailable');
            $body = self::assertSummary($handler->handle(), 'loaded');
            self::assertSame($before, self::snapshot($root));

            self::compile($root, 'dev');
            $second = \file_get_contents($pointer);
            self::assertIsString($second);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\n\z/', $second);
            self::assertNotSame($first, $second);
            $afterPublication = self::snapshot($root);
            self::assertSame($body, self::assertSummary($handler->handle(), 'loaded'));
            self::assertSame($unavailableBody, self::assertSummary($sourceHandler->handle(), 'unavailable'));
            self::assertSame($afterPublication, self::snapshot($root));

            // Neither a selected artifact nor the new current pointer may be
            // consulted by an already constructed diagnostic handler.
            $firstConfig = $artifactRoot . '/generations/' . \rtrim($first, "\n") . '/config.php';
            $secondConfig = $artifactRoot . '/generations/' . \rtrim($second, "\n") . '/config.php';
            $sentinel = "<?php throw new \\RuntimeException('PRIVATE_ARTIFACT_PAYLOAD');\n";
            self::assertSame(\strlen($sentinel), \file_put_contents($firstConfig, $sentinel));
            self::assertSame(\strlen($sentinel), \file_put_contents($secondConfig, $sentinel));
            self::assertTrue(\unlink($pointer));

            $changed = self::snapshot($root);
            self::assertSame($body, self::assertSummary($handler->handle(), 'loaded'));
            self::assertSame($unavailableBody, self::assertSummary($sourceHandler->handle(), 'unavailable'));
            self::assertSame($changed, self::snapshot($root));
            self::assertFileDoesNotExist($pointer);
            self::assertSame($diagnostics, $container->get(RuntimeDiagnosticsInput::class));
            self::assertSame('local', $diagnostics->appEnv());
            self::assertStringNotContainsString($root, $body);
            self::assertStringNotContainsString(\rtrim($first, "\n"), $body);
            self::assertStringNotContainsString(\rtrim($second, "\n"), $body);
            self::assertStringNotContainsString('PRIVATE_ARTIFACT_PAYLOAD', $body);
        } finally {
            $fixture?->close();
            self::removeTree($root);
        }
    }

    public function testHandlerDependsOnlyOnRuntimeInputAndResponseConstruction(): void
    {
        $reflection = new \ReflectionClass(DebugCacheHandler::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(
            [RuntimeDiagnosticsInput::class, FallbackResponses::class],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                $constructor->getParameters(),
            ),
        );
        $source = \file_get_contents($reflection->getFileName());
        self::assertIsString($source);

        foreach (
            [
                'ArtifactGenerationLocator',
                'ArtifactRuntimeBooter',
                'RuntimePathContext',
                'ConfigRepositoryInterface',
                'PhpArtifactReader',
                'file_get_contents(',
                'file_put_contents(',
                'file_exists(',
                'is_file(',
                'is_dir(',
                'fopen(',
                'scandir(',
                'glob(',
                'realpath(',
                'DirectoryIterator',
                'getenv(',
                'require ',
                'include ',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    private static function assertSummary(ResponseInterface $response, string $status): string
    {
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        $body = (string) $response->getBody();
        self::assertSame(
            [
                'artifacts' => [
                    'config' => $status,
                    'container' => $status,
                    'generation-manifest' => $status,
                    'module-manifest' => $status,
                ],
            ],
            \json_decode($body, true, 512, \JSON_THROW_ON_ERROR),
        );

        return $body;
    }

    private static function compile(string $root, string $appEnv): RuntimeDiagnosticsInput
    {
        $config = [
            'appEnv' => $appEnv,
            'moduleOverrides' => [
                'console' => [
                    'include' => [],
                    'exclude' => ['platform.http', 'platform.problem-details'],
                ],
            ],
        ];
        self::assertNotFalse(
            \file_put_contents(
                $root . '/config/app.php',
                '<?php return ' . \var_export($config, true) . ";\n",
            ),
        );
        self::assertNotFalse(
            \file_put_contents(
                $root . '/config/logging.php',
                "<?php return ['driver' => 'null'];\n",
            ),
        );
        $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
        $diagnostics = $host->get(RuntimeDiagnosticsInput::class);
        $ops = $host->get(KernelOpsInterface::class);
        self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
        self::assertSame($appEnv, $diagnostics->appEnv());
        self::assertFalse($diagnostics->artifactGenerationLoaded());
        self::assertInstanceOf(KernelOpsInterface::class, $ops);
        self::assertSame('success', $ops->compileConfig(new KernelOpsRequest('console'))->outcome());

        return $diagnostics;
    }

    /** @return array<string, string> */
    private static function snapshot(string $root): array
    {
        \clearstatcache();
        $snapshot = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            $relative = \substr($path, \strlen($root) + 1);
            if ($entry->isDir()) {
                $snapshot[$relative] = 'directory';
                continue;
            }
            $bytes = \file_get_contents($path);
            self::assertIsString($bytes);
            $snapshot[$relative] = \hash('sha256', $bytes) . ':' . $entry->getMTime();
        }
        \ksort($snapshot, \SORT_STRING);

        return $snapshot;
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path) || \is_link($path)) {
            @\unlink($path);
            return;
        }
        $items = \scandir($path);
        if ($items !== false) {
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..') {
                    self::removeTree($path . '/' . $item);
                }
            }
        }
        @\rmdir($path);
    }
}
