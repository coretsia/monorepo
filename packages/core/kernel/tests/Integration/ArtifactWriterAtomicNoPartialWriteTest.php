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

use Coretsia\Foundation\Filesystem\Exception\AtomicFileWriteException;
use Coretsia\Kernel\Artifacts\Exception\ArtifactWriteFailedException;
use PHPUnit\Framework\TestCase;

final class ArtifactWriterAtomicNoPartialWriteTest extends TestCase
{
    private string $applicationRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->applicationRoot = ArtifactPipelineTestSupport::temporaryRoot('artifact-writer-atomic');
    }

    protected function tearDown(): void
    {
        ArtifactPipelineTestSupport::removeTree($this->applicationRoot);

        parent::tearDown();
    }

    public function testFailedWriteDoesNotLeavePartiallyWrittenFinalArtifactAndCleansTempFiles(): void
    {
        $targetDirectory = $this->applicationRoot . '/var/cache/web';

        \mkdir($targetDirectory, 0777, true);

        $targetPath = $targetDirectory . '/config.php';

        \mkdir($targetPath, 0777, true);

        $markerPath = $targetPath . '/existing.txt';
        self::assertSame(9, \file_put_contents($markerPath, "existing\n"));

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
            self::assertSame(
                ArtifactWriteFailedException::ERROR_CODE . ': ' . ArtifactWriteFailedException::REASON_TEMP_FILE_RENAME_FAILED,
                $exception->getMessage(),
            );
            $previous = $exception->getPrevious();
            self::assertInstanceOf(AtomicFileWriteException::class, $previous);
            self::assertSame(AtomicFileWriteException::REASON_TEMP_FILE_RENAME_FAILED, $previous->reason());
        }

        self::assertDirectoryExists($targetPath);
        self::assertSame("existing\n", \file_get_contents($markerPath));
        self::assertSame([], self::temporaryArtifactFiles($targetDirectory));
    }

    public function testSuccessfulWriteProducesLfOnlyBytesWithFinalNewline(): void
    {
        $targetPath = $this->applicationRoot . '/var/cache/web/custom.txt';

        $result = ArtifactPipelineTestSupport::artifactWriter($this)->writeTextArtifact(
            targetPath: $targetPath,
            relativePath: 'var/cache/web/custom.txt',
            bytes: "first line\r\nsecond line",
        );

        self::assertSame('custom.txt', $result['basename']);
        self::assertSame('var/cache/web/custom.txt', $result['path']);
        self::assertSame(\strlen("first line\nsecond line\n"), $result['bytes']);

        $bytes = \file_get_contents($targetPath);

        self::assertSame("first line\nsecond line\n", $bytes);
        self::assertStringNotContainsString("\r", $bytes);
        self::assertStringEndsWith("\n", $bytes);
        self::assertFalse(
            \str_ends_with($bytes, "\n\n"),
            'ArtifactWriter must produce exactly one final LF.',
        );
        self::assertSame([], self::temporaryArtifactFiles(\dirname($targetPath)));
    }

    public function testSuccessfulReplacementPublishesOnlyCompleteNormalizedBytes(): void
    {
        $relativePath = 'var/cache/web/custom.txt';
        $targetPath = $this->applicationRoot . '/' . $relativePath;
        $writer = ArtifactPipelineTestSupport::artifactWriter($this);
        $original = \str_repeat('original', 128) . "\n";

        self::assertSame(
            [
                'basename' => 'custom.txt',
                'bytes' => \strlen($original),
                'path' => $relativePath,
            ],
            $writer->writeTextArtifact(
                targetPath: $targetPath,
                relativePath: $relativePath,
                bytes: $original,
            ),
        );
        self::assertSame($original, \file_get_contents($targetPath));
        self::assertSame([], self::temporaryArtifactFiles(\dirname($targetPath)));

        self::assertSame(
            [
                'basename' => 'custom.txt',
                'bytes' => \strlen("replacement\n"),
                'path' => $relativePath,
            ],
            $writer->writeTextArtifact(
                targetPath: $targetPath,
                relativePath: $relativePath,
                bytes: "replacement\r\n\r\n",
            ),
        );
        self::assertSame("replacement\n", \file_get_contents($targetPath));
        self::assertSame([], self::temporaryArtifactFiles(\dirname($targetPath)));
    }

    public function testWriteTimePermissionChangesDoNotAffectCacheVerifyCleanDirtySemantics(): void
    {
        ArtifactPipelineTestSupport::compileArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
            config: ArtifactPipelineTestSupport::defaultConfig(),
        );

        self::assertSame(
            'clean',
            ArtifactPipelineTestSupport::verifyArtifacts($this, $this->applicationRoot)['outcome'],
        );

        foreach (ArtifactPipelineTestSupport::artifactPaths($this->applicationRoot) as $path) {
            @\chmod($path, 0600);
        }

        $result = ArtifactPipelineTestSupport::verifyArtifacts(
            testCase: $this,
            applicationRoot: $this->applicationRoot,
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
