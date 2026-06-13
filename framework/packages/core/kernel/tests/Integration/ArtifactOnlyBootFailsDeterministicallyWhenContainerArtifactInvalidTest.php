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

use Coretsia\Kernel\Container\Exception\ContainerArtifactInvalidException;
use PHPUnit\Framework\TestCase;

final class ArtifactOnlyBootFailsDeterministicallyWhenContainerArtifactInvalidTest extends TestCase
{
    public function testProductionRuntimeContainerRejectsLegacyStubContainerArtifact(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('legacy-stub-container-artifact');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $containerPath = ArtifactPipelineTestSupport::artifactPath($root, 'container.php');

            ArtifactPipelineTestSupport::writePhpReturn(
                path: $containerPath,
                value: self::legacyStubContainerEnvelope(),
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

                self::fail('Expected legacy stub compiled-container artifact failure.');
            } catch (ContainerArtifactInvalidException $exception) {
                self::assertSame(
                    ContainerArtifactInvalidException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ContainerArtifactInvalidException::MESSAGE_TOKEN,
                    $exception->messageToken(),
                );
                self::assertSame(
                    ContainerArtifactInvalidException::REASON_LEGACY_STUB,
                    $exception->reason(),
                );
                self::assertSame(
                    'CORETSIA_CONTAINER_ARTIFACT_INVALID: container-artifact-invalid',
                    $exception->getMessage(),
                );

                self::assertSafeInvalidArtifactFailureMessage(
                    exception: $exception,
                    containerPath: $containerPath,
                );
                self::assertStringNotContainsString('stub', $exception->getMessage());
                self::assertStringNotContainsString('compiled', $exception->getMessage());
                self::assertStringNotContainsString('false', $exception->getMessage());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testProductionRuntimeContainerRejectsInvalidPayloadWithoutLeakingPayloadOrSourceSnippets(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('invalid-container-artifact-payload');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $containerPath = ArtifactPipelineTestSupport::artifactPath($root, 'container.php');

            ArtifactPipelineTestSupport::writePhpReturn(
                path: $containerPath,
                value: self::sourceSnippetContainerEnvelope(),
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

                self::fail('Expected invalid compiled-container artifact failure.');
            } catch (ContainerArtifactInvalidException $exception) {
                self::assertSame(
                    ContainerArtifactInvalidException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ContainerArtifactInvalidException::MESSAGE_TOKEN,
                    $exception->messageToken(),
                );
                self::assertSame(
                    'CORETSIA_CONTAINER_ARTIFACT_INVALID: container-artifact-invalid',
                    $exception->getMessage(),
                );

                self::assertSafeInvalidArtifactFailureMessage(
                    exception: $exception,
                    containerPath: $containerPath,
                );
                self::assertStringNotContainsString('<?php', $exception->getMessage());
                self::assertStringNotContainsString('function', $exception->getMessage());
                self::assertStringNotContainsString('fn', $exception->getMessage());
                self::assertStringNotContainsString('getenv', $exception->getMessage());
                self::assertStringNotContainsString('SECRET_TOKEN', $exception->getMessage());
                self::assertStringNotContainsString('Closure', $exception->getMessage());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testProductionRuntimeContainerRejectsReadFailedArtifactWithoutLeakingPhpWarningText(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('invalid-container-artifact-warning');

        try {
            ArtifactPipelineTestSupport::compileArtifacts(
                testCase: $this,
                skeletonRoot: $root,
                config: ArtifactPipelineTestSupport::defaultConfig(),
            );

            $containerPath = ArtifactPipelineTestSupport::artifactPath($root, 'container.php');
            $secretPath = $root . '/secret/source.php';

            \file_put_contents(
                $containerPath,
                "<?php\n\n"
                . "trigger_error('failed to open stream: " . \addslashes($secretPath) . "', E_USER_WARNING);\n\n"
                . "return [];\n",
            );

            try {
                ArtifactPipelineTestSupport::runtimeContainerFromArtifacts($root);

                self::fail('Expected read-failed compiled-container artifact failure.');
            } catch (ContainerArtifactInvalidException $exception) {
                self::assertSame(
                    ContainerArtifactInvalidException::ERROR_CODE,
                    $exception->errorCode(),
                );
                self::assertSame(
                    ContainerArtifactInvalidException::MESSAGE_TOKEN,
                    $exception->messageToken(),
                );
                self::assertSame(
                    'CORETSIA_CONTAINER_ARTIFACT_INVALID: container-artifact-invalid',
                    $exception->getMessage(),
                );

                self::assertSafeInvalidArtifactFailureMessage(
                    exception: $exception,
                    containerPath: $containerPath,
                );
                self::assertStringNotContainsString($secretPath, $exception->getMessage());
                self::assertStringNotContainsString('failed to open stream', $exception->getMessage());
                self::assertStringNotContainsString('trigger_error', $exception->getMessage());
                self::assertStringNotContainsString('E_USER_WARNING', $exception->getMessage());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function legacyStubContainerEnvelope(): array
    {
        return [
            '_meta' => self::containerHeader(),
            'payload' => [
                'compiled' => false,
                'kind' => 'stub',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function sourceSnippetContainerEnvelope(): array
    {
        return [
            '_meta' => self::containerHeader(),
            'payload' => [
                'aliases' => [],
                'compiled' => true,
                'kind' => 'compiled',
                'parameters' => [
                    'unsafe' => '<?php function leaked_secret() { return getenv("SECRET_TOKEN"); }',
                ],
                'services' => [],
                'tags' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function containerHeader(): array
    {
        return [
            'fingerprint' => \str_repeat('a', 64),
            'generator' => 'core/kernel/artifacts',
            'name' => 'container',
            'schemaVersion' => 1,
        ];
    }

    private static function assertSafeInvalidArtifactFailureMessage(
        ContainerArtifactInvalidException $exception,
        string $containerPath,
    ): void {
        self::assertStringNotContainsString($containerPath, $exception->getMessage());
        self::assertStringNotContainsString(\dirname($containerPath), $exception->getMessage());
        self::assertStringNotContainsString(\sys_get_temp_dir(), $exception->getMessage());

        self::assertStringNotContainsString('raw_payload', $exception->getMessage());
        self::assertStringNotContainsString('payload', $exception->getMessage());
        self::assertStringNotContainsString('raw_config', $exception->getMessage());
        self::assertStringNotContainsString('raw_env', $exception->getMessage());
        self::assertStringNotContainsString('stack trace', $exception->getMessage());
        self::assertStringNotContainsString('Stack trace', $exception->getMessage());
        self::assertStringNotContainsString('previous', $exception->getMessage());
        self::assertStringNotContainsString('Throwable', $exception->getMessage());
        self::assertStringNotContainsString('Exception', $exception->getMessage());
        self::assertStringNotContainsString('Warning', $exception->getMessage());
        self::assertStringNotContainsString('warning', $exception->getMessage());
    }
}
