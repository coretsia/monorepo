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

use Coretsia\Kernel\Artifacts\Exception\ArtifactInvalidException;
use Coretsia\Kernel\Artifacts\Php\PhpArtifactReader;
use Coretsia\Kernel\Artifacts\Php\StablePhpArrayDumper;
use PHPUnit\Framework\TestCase;

final class PhpArtifactReaderRejectsExecutableArtifactSyntaxTest extends TestCase
{
    public function testRejectsExecutableSyntaxWithoutExecutingArtifactSource(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-reader-non-executable-source');
        $artifactPath = $root . '/artifact.php';
        $sentinelPath = $root . '/sentinel';
        $bytes = "<?php\n\n"
            . 'file_put_contents('
            . \var_export($sentinelPath, true)
            . ", 'executed');\n\n"
            . "return [];\n";

        try {
            self::assertNotFalse(\file_put_contents($artifactPath, $bytes));

            try {
                new PhpArtifactReader()->readExact($artifactPath);

                self::fail('Expected executable artifact syntax rejection.');
            } catch (ArtifactInvalidException $exception) {
                self::assertSame(
                    ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                    $exception->reason(),
                );
                self::assertSame(
                    ArtifactInvalidException::ERROR_CODE
                    . ': '
                    . ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                    $exception->getMessage(),
                );
                self::assertFileDoesNotExist($sentinelPath);
                self::assertStringNotContainsString($root, $exception->getMessage());
                self::assertStringNotContainsString($sentinelPath, $exception->getMessage());
                self::assertStringNotContainsString('file_put_contents', $exception->getMessage());
                self::assertStringNotContainsString('executed', $exception->getMessage());
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }

    public function testReadNormalizesLineEndingsBeforeDecodingWhileReadExactRemainsCanonical(): void
    {
        $root = ArtifactPipelineTestSupport::temporaryRoot('artifact-reader-normalized-decoding');
        $artifactPath = $root . '/artifact.php';
        $canonical = new StablePhpArrayDumper()->dump([
            'value' => 'ok',
        ]);
        $crlf = \str_replace("\n", "\r\n", $canonical);

        try {
            self::assertNotFalse(\file_put_contents($artifactPath, $crlf));

            $normalized = new PhpArtifactReader()->read($artifactPath);

            self::assertSame($canonical, $normalized['bytes']);
            self::assertSame(['value' => 'ok'], $normalized['envelope']);

            try {
                new PhpArtifactReader()->readExact($artifactPath);

                self::fail('Expected exact artifact serialization rejection.');
            } catch (ArtifactInvalidException $exception) {
                self::assertSame(
                    ArtifactInvalidException::REASON_SERIALIZATION_INVALID,
                    $exception->reason(),
                );
            }
        } finally {
            ArtifactPipelineTestSupport::removeTree($root);
        }
    }
}
