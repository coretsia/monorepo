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

namespace Coretsia\Platform\Routing\Tests\Integration;

use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Exception\RoutesArtifactWriteFailedException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingFilesystemProbe;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class RoutesArtifactPublicationPreservesPreviousArtifactTest extends TestCase
{
    public function testPublicationCommitBoundaryAndLockCoordination(): void
    {
        RoutingIntegrationFixture::runFilesystemScenario(self::class);
    }

    public static function exercise(string $root): void
    {
        $artifactRoot = $root . '/publication';
        self::assertTrue(\mkdir($artifactRoot));
        $path = $artifactRoot . '/routes.php';
        $writer = RoutingIntegrationFixture::writer($artifactRoot);
        $first = RoutingIntegrationFixture::envelope('original');
        $second = RoutingIntegrationFixture::envelope('replacement');
        $bytes = new StablePhpArrayDumper()->dumpNormalized($first);
        $replacement = new StablePhpArrayDumper()->dumpNormalized($second);
        self::assertTrue($writer->write($first));
        self::assertSame($bytes, \file_get_contents($path));

        $invalid = $second;
        $invalid['_meta']['fingerprint'] = \str_repeat('0', 64);
        try {
            RoutingFilesystemProbe::run($artifactRoot, 'validation', static fn () => $writer->write($invalid));
            self::fail('Expected validation failure before publication.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame(RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID, $exception->reason());
        }
        self::assertSame($bytes, \file_get_contents($path));
        self::assertSame(0, RoutingFilesystemProbe::$lockOpens);
        self::assertSame(0, RoutingFilesystemProbe::$artifactOpens);
        foreach (
            [
                ['lock', RoutesArtifactWriteFailedException::REASON_LOCK_ACQUIRE_FAILED],
                ['write', RoutesArtifactWriteFailedException::REASON_TEMP_FILE_WRITE_FAILED],
                ['rename', RoutesArtifactWriteFailedException::REASON_PUBLICATION_FAILED],
            ] as [$fault, $reason]
        ) {
            try {
                RoutingFilesystemProbe::run($artifactRoot, $fault, static fn () => $writer->write($second));
                self::fail('Expected publication failure: ' . $fault);
            } catch (RoutesArtifactWriteFailedException $exception) {
                self::assertSame($reason, $exception->reason());
                self::assertSame(
                    RoutesArtifactWriteFailedException::ERROR_CODE . ': ' . $reason,
                    $exception->getMessage(),
                );
                self::assertNull($exception->getPrevious());
            }
            self::assertSame($bytes, \file_get_contents($path));
            self::assertSame(['.', '..', 'routes.lock', 'routes.php'], \scandir($artifactRoot));
            self::assertTrue(RoutingFilesystemProbe::$faultReached, $fault);
            self::assertSame(1, RoutingFilesystemProbe::$lockOpens);
            self::assertSame($fault === 'write' ? 8 : 0, RoutingFilesystemProbe::$temporaryBytes);
        }
        \clearstatcache(true, $path);
        $previousStat = \stat($path);
        self::assertIsArray($previousStat);
        self::assertFalse(RoutingFilesystemProbe::run($artifactRoot, 'same', static fn () => $writer->write($first)));
        self::assertSame(0, RoutingFilesystemProbe::$renames);
        self::assertSame(0, RoutingFilesystemProbe::$artifactWriteOpens);
        self::assertSame(1, RoutingFilesystemProbe::$lockOpens);
        self::assertTrue(RoutingFilesystemProbe::$comparisonUnderLock);
        \clearstatcache(true, $path);
        $currentStat = \stat($path);
        self::assertIsArray($currentStat);
        self::assertSame($previousStat['ino'], $currentStat['ino']);
        self::assertSame($previousStat['mtime'], $currentStat['mtime']);
        self::assertSame($bytes, \file_get_contents($path));

        // A no-op comparison does not commit a replacement, even if unlocking fails.
        try {
            RoutingFilesystemProbe::run($artifactRoot, 'unlock', static fn () => $writer->write($first));
            self::fail('Expected lock cleanup failure without a publication commit.');
        } catch (RoutesArtifactWriteFailedException $exception) {
            self::assertSame(RoutesArtifactWriteFailedException::REASON_WRITE_FAILED, $exception->reason());
        }
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(0, RoutingFilesystemProbe::$renames);
        self::assertSame(0, RoutingFilesystemProbe::$artifactWriteOpens);
        self::assertSame($bytes, \file_get_contents($path));
        self::assertSame(['.', '..', 'routes.lock', 'routes.php'], \scandir($artifactRoot));

        self::assertTrue(RoutingFilesystemProbe::run($artifactRoot, 'unlock', static fn () => $writer->write($second)));
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(1, RoutingFilesystemProbe::$renames);
        self::assertTrue(RoutingFilesystemProbe::$renameUnderLock);
        self::assertSame(1, RoutingFilesystemProbe::$lockOpens);
        self::assertSame($replacement, \file_get_contents($path));
        self::assertSame(['.', '..', 'routes.lock', 'routes.php'], \scandir($artifactRoot));
        self::assertSame('replacement', RoutingIntegrationFixture::reader($artifactRoot)->read()[0]->name());

        $handle = \fopen($path, 'wb');
        self::assertIsResource($handle);
        try {
            self::assertTrue(\ftruncate($handle, RoutesArtifactSchema::MAX_ARTIFACT_BYTES + 1));
        } finally {
            \fclose($handle);
        }
        self::assertTrue(
            RoutingFilesystemProbe::run($artifactRoot, 'oversized', static fn () => $writer->write($first)),
        );
        self::assertSame(0, RoutingFilesystemProbe::$artifactOpens);
        self::assertTrue(RoutingFilesystemProbe::$renameUnderLock);
        self::assertSame($bytes, \file_get_contents($path));
        // The existing artifact has the same size at fstat, then grows during comparison.
        self::assertTrue(RoutingFilesystemProbe::run($artifactRoot, 'grow', static fn () => $writer->write($first)));
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);
        self::assertTrue(RoutingFilesystemProbe::$comparisonUnderLock);
        self::assertTrue(RoutingFilesystemProbe::$renameUnderLock);
        self::assertTrue(RoutingFilesystemProbe::$readBytes <= \strlen($bytes) + 8192);
        self::assertSame(1, RoutingFilesystemProbe::$renames);
        self::assertSame($bytes, \file_get_contents($path));
        self::assertSame(['.', '..', 'routes.lock', 'routes.php'], \scandir($artifactRoot));
    }

    public function testNonRegularTargetsAreRejectedBeforePublication(): void
    {
        $root = RoutingIntegrationFixture::root();
        try {
            $writer = RoutingIntegrationFixture::writer($root);
            $envelope = RoutingIntegrationFixture::envelope('original');
            foreach (['routes.php', 'routes.lock'] as $name) {
                $path = $root . '/' . $name;
                self::assertTrue(\mkdir($path));
                try {
                    $writer->write($envelope);
                    self::fail('Expected unsafe target rejection.');
                } catch (RoutesArtifactWriteFailedException $exception) {
                    self::assertSame(RoutesArtifactWriteFailedException::REASON_TARGET_UNSAFE, $exception->reason());
                }
                self::assertDirectoryExists($path);
                self::assertSame(['.', '..', $name], \scandir($root));
                \rmdir($path);
            }
        } finally {
            RoutingIntegrationFixture::remove($root);
        }
    }
}
