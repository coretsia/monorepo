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

use Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException;
use PHPUnit\Framework\TestCase;

final class ArtifactWriterAtomicNoPartialWriteTest extends TestCase
{
    private string $skeletonRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skeletonRoot = ArtifactPipelineTestSupport::temporaryRoot('artifact-writer-atomic');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->skeletonRoot);

        parent::tearDown();
    }

    public function testFailedWriteDoesNotLeavePartiallyWrittenFinalArtifactAndCleansTempFiles(): void
    {
        $targetDirectory = $this->skeletonRoot . '/var/cache/web';

        \mkdir($targetDirectory, 0777, true);

        $targetPath = $targetDirectory . '/config.php';

        \mkdir($targetPath, 0777, true);

        try {
            ArtifactPipelineTestSupport::artifactWriter($this)->writeTextArtifact(
                targetPath: $targetPath,
                relativePath: 'var/cache/web/config.php',
                bytes: "<?php\n\nreturn ['ok' => true];\n",
            );

            self::fail('Expected ArtifactWriteFailedException was not thrown.');
        } catch (ArtifactWriteFailedException $exception) {
            self::assertSame(ArtifactWriteFailedException::ERROR_CODE, $exception->errorCode());
            self::assertSame(
                ArtifactWriteFailedException::REASON_TEMP_FILE_RENAME_FAILED,
                $exception->reason(),
            );
        }

        self::assertDirectoryExists($targetPath);
        self::assertSame([], self::temporaryArtifactFiles($targetDirectory));
    }

    public function testSuccessfulWriteProducesLfOnlyBytesWithFinalNewline(): void
    {
        $targetPath = $this->skeletonRoot . '/var/cache/web/custom.txt';

        $result = ArtifactPipelineTestSupport::artifactWriter($this)->writeTextArtifact(
            targetPath: $targetPath,
            relativePath: 'var/cache/web/custom.txt',
            bytes: "first line\r\nsecond line",
        );

        self::assertSame('custom.txt', $result['basename']);
        self::assertSame('var/cache/web/custom.txt', $result['path']);

        $bytes = \file_get_contents($targetPath);

        self::assertSame("first line\nsecond line\n", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(
            \str_ends_with($bytes, "\n\n"),
            'ArtifactWriter must produce exactly one final LF.',
        );
    }

    public function testWriteTimePermissionChangesDoNotAffectCacheVerifyCleanDirtySemantics(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        self::assertSame(
            'clean',
            ArtifactPipelineTestSupport::verifyArtifacts($this, $this->skeletonRoot)['outcome'],
        );

        foreach (ArtifactPipelineTestSupport::artifactPaths($this->skeletonRoot) as $path) {
            @\chmod($path, 0600);
        }

        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            skeletonRoot: $this->skeletonRoot,
        );

        self::assertSame('clean', $result['outcome']);
        self::assertTrue($result['clean']);
        self::assertFalse($result['dirty']);
        self::assertFalse($result['invalid']);
    }

    /**
     * @return list<string>
     */
    private static function temporaryArtifactFiles(string $directory): array
    {
        $files = \glob($directory . '/.coretsia-artifact-*');

        if ($files === false) {
            return [];
        }

        \sort($files, \SORT_STRING);

        return \array_values($files);
    }
}
