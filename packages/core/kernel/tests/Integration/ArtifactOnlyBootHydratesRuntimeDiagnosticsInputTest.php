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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Kernel\Ops\KernelOpsInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGeneration;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationId;
use Coretsia\Kernel\Artifacts\Generation\ArtifactGenerationPathResolver;
use Coretsia\Kernel\Artifacts\Verifier\ArtifactSchemaValidator;
use Coretsia\Kernel\Boot\ArtifactRuntimeBooter;
use Coretsia\Kernel\Boot\ArtifactRuntimeInput;
use Coretsia\Kernel\Boot\Exception\ArtifactRuntimeBootException;
use Coretsia\Kernel\Container\ContainerGraphCompletenessValidator;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootHydratesRuntimeDiagnosticsInputTest extends TestCase
{
    public function testActualBootRestoresFrozenEnvironmentAndRetainsSelectedGenerationState(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-runtime-diagnostics');
        $artifactRoot = $root . '/var/cache/console';
        $environmentName = 'CORETSIA_BOOT_APP_ENV';
        $previous = \getenv($environmentName);

        try {
            $source = self::compile($root, 'local');
            self::assertSame('local', $source->appEnv());
            self::assertFalse($source->artifactGenerationLoaded());

            $first = self::currentGeneration($artifactRoot);
            $config = ArtifactPipelineTestSupport::readArtifactEnvelope($first->configPath());
            self::assertSame('local', $config['payload']['appEnv']);

            self::assertTrue(\putenv($environmentName . '=prod'));
            $input = new ArtifactRuntimeInput($root, $artifactRoot);
            $container = new ArtifactRuntimeBooter()->boot($input);
            $diagnostics = $container->get(RuntimeDiagnosticsInput::class);

            self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
            self::assertSame('local', $diagnostics->appEnv());
            self::assertTrue($diagnostics->artifactGenerationLoaded());
            self::assertSame($diagnostics, $container->get(RuntimeDiagnosticsInput::class));

            self::assertNoCompileHostDefinitions($first);

            $nextSource = self::compile($root, 'dev');
            self::assertSame('dev', $nextSource->appEnv());
            self::assertFalse($nextSource->artifactGenerationLoaded());

            $second = self::currentGeneration($artifactRoot);
            self::assertNotSame($first->generationId()->value(), $second->generationId()->value());
            $nextConfig = ArtifactPipelineTestSupport::readArtifactEnvelope($second->configPath());
            self::assertSame('dev', $nextConfig['payload']['appEnv']);

            $nextContainer = new ArtifactRuntimeBooter()->boot($input);
            $nextDiagnostics = $nextContainer->get(RuntimeDiagnosticsInput::class);
            self::assertInstanceOf(RuntimeDiagnosticsInput::class, $nextDiagnostics);
            self::assertSame('dev', $nextDiagnostics->appEnv());
            self::assertTrue($nextDiagnostics->artifactGenerationLoaded());
            self::assertNotSame($diagnostics, $nextDiagnostics);
            self::assertSame($diagnostics, $container->get(RuntimeDiagnosticsInput::class));
            self::assertSame('local', $diagnostics->appEnv());
            self::assertTrue($diagnostics->artifactGenerationLoaded());

            self::assertTrue(\unlink(new ArtifactGenerationPathResolver()->currentPath($artifactRoot)));
            self::assertSame('local', $diagnostics->appEnv());
            self::assertTrue($diagnostics->artifactGenerationLoaded());
            self::assertSame('dev', $nextDiagnostics->appEnv());
            self::assertTrue($nextDiagnostics->artifactGenerationLoaded());

            try {
                new ArtifactRuntimeBooter()->boot($input);
                self::fail('Expected boot without a selected generation to fail.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertSame(ArtifactRuntimeBootException::REASON_GENERATION_INVALID, $exception->reason());
            }
        } finally {
            \putenv($previous === false ? $environmentName : $environmentName . '=' . $previous);
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    #[DataProvider('invalidEnvironments')]
    public function testRejectsInvalidEnvironmentThroughCanonicalArtifactValidation(
        bool $present,
        mixed $appEnv,
    ): void {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-runtime-diagnostics-invalid');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                applicationRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $generation = ArtifactPipelineTestSupport::currentGeneration($root);
            $envelope = ArtifactPipelineTestSupport::readArtifactEnvelope($generation->configPath());
            if ($present) {
                $envelope['payload']['appEnv'] = $appEnv;
            } else {
                unset($envelope['payload']['appEnv']);
            }
            try {
                new ArtifactSchemaValidator()->validate($envelope);
                self::fail('Expected invalid config artifact metadata.');
            } catch (ArtifactInvalidException $exception) {
                self::assertSame(ArtifactInvalidException::REASON_SCHEMA_INVALID, $exception->reason());
            }

            // Recompute the manifest entry so the failure is not merely a stale
            // size/hash mismatch. The malformed config@1 payload must be rejected.
            ArtifactPipelineTestSupport::writeArtifactEnvelope($generation->configPath(), $envelope);
            $bytes = \file_get_contents($generation->configPath());
            self::assertIsString($bytes);
            $manifest = ArtifactPipelineTestSupport::readArtifactEnvelope($generation->generationManifestPath());
            $manifest['payload']['artifacts'][ArtifactGeneration::CONFIG_BASENAME] = [
                'bytes' => \strlen($bytes),
                'sha256' => \hash('sha256', $bytes),
            ];
            ArtifactPipelineTestSupport::writeArtifactEnvelope($generation->generationManifestPath(), $manifest);

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);
                self::fail('Expected artifact-only boot to reject invalid config environment metadata.');
            } catch (ArtifactRuntimeBootException $exception) {
                self::assertSame(ArtifactRuntimeBootException::REASON_GENERATION_INVALID, $exception->reason());
                self::assertSame(
                    ArtifactRuntimeBootException::ERROR_CODE . ': '
                    . ArtifactRuntimeBootException::REASON_GENERATION_INVALID,
                    $exception->getMessage(),
                );
                self::assertStringNotContainsString('PRIVATE_MARKER', $exception->getMessage());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /** @return iterable<string, array{bool, mixed}> */
    public static function invalidEnvironments(): iterable
    {
        yield 'missing' => [false, null];
        yield 'null' => [true, null];
        yield 'integer' => [true, 42];
        yield 'empty' => [true, ''];
        yield 'surrounding-space' => [true, ' local '];
        yield 'line-break' => [true, "PRIVATE_MARKER\nlocal"];
        yield 'control-byte' => [true, "PRIVATE_MARKER\x1F"];
    }

    private static function compile(string $root, string $appEnv): RuntimeDiagnosticsInput
    {
        ArtifactPipelineTestSupport::writePhpReturn(
            $root . '/config/app.php',
            [
                'appEnv' => $appEnv,
                'moduleOverrides' => [
                    'console' => [
                        'include' => [],
                        'exclude' => ['platform.http', 'platform.problem-details'],
                    ],
                ],
            ],
        );
        ArtifactPipelineTestSupport::writePhpReturn($root . '/config/logging.php', ['driver' => 'null']);

        $host = new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
        $diagnostics = $host->get(RuntimeDiagnosticsInput::class);
        $ops = $host->get(KernelOpsInterface::class);
        self::assertInstanceOf(RuntimeDiagnosticsInput::class, $diagnostics);
        self::assertInstanceOf(KernelOpsInterface::class, $ops);
        self::assertSame('success', $ops->compileConfig(new KernelOpsRequest('console'))->outcome());

        return $diagnostics;
    }

    private static function currentGeneration(string $artifactRoot): ArtifactGeneration
    {
        $resolver = new ArtifactGenerationPathResolver();
        $pointer = \file_get_contents($resolver->currentPath($artifactRoot));
        self::assertIsString($pointer);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\n\z/', $pointer);

        return $resolver->generation($artifactRoot, ArtifactGenerationId::fromString(\rtrim($pointer, "\n")));
    }

    private static function assertNoCompileHostDefinitions(ArtifactGeneration $generation): void
    {
        $graph = ArtifactPipelineTestSupport::readArtifactEnvelope($generation->containerPath())['payload'];
        self::assertIsArray($graph['services']);
        self::assertIsArray($graph['aliases']);
        self::assertNotEmpty($graph['services']);

        $policy = new \ReflectionClass(ContainerGraphCompletenessValidator::class);
        foreach (['COMPILE_HOST_SERVICE_IDS', 'SOURCE_OPERATIONS_HOST_SERVICE_IDS'] as $constant) {
            $ids = $policy->getConstant($constant);
            self::assertIsArray($ids);
            self::assertNotEmpty($ids);
            foreach ($ids as $id) {
                self::assertArrayNotHasKey($id, $graph['services']);
                self::assertArrayNotHasKey($id, $graph['aliases']);
                self::assertNotContains($id, $graph['aliases']);
            }
        }
        self::assertArrayNotHasKey(RuntimeDiagnosticsInput::class, $graph['services']);
        self::assertArrayNotHasKey(RuntimeDiagnosticsInput::class, $graph['aliases']);
    }
}
