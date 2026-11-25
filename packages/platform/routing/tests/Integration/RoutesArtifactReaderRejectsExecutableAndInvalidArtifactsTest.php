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

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Foundation\Serialization\StablePhpArrayDumper;
use Coretsia\Platform\Routing\Artifacts\RoutesArtifactSchema;
use Coretsia\Platform\Routing\Exception\RoutesArtifactInvalidException;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingFilesystemProbe;
use Coretsia\Platform\Routing\Tests\Integration\Support\RoutingIntegrationFixture;
use PHPUnit\Framework\TestCase;

final class RoutesArtifactReaderRejectsExecutableAndInvalidArtifactsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = RoutingIntegrationFixture::root();
    }

    protected function tearDown(): void
    {
        RoutingIntegrationFixture::remove($this->root);
        parent::tearDown();
    }

    public function testMissingExecutableMalformedAndOversizedArtifactsAreRejected(): void
    {
        $path = $this->root . '/routes.php';
        self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_MISSING);
        $marker = $this->root . '/executed';
        $valid = RoutingIntegrationFixture::envelope('original');
        $bytes = new StablePhpArrayDumper()->dumpNormalized($valid);
        foreach (
            [
                '<?php file_put_contents(' . \var_export($marker, true) . ', "executed"); return [];',
                $bytes . '<?php file_put_contents(' . \var_export($marker, true) . ', "executed");',
                '<?php return [',
                $bytes . "\n",
            ] as $invalid
        ) {
            RoutingIntegrationFixture::write($path, $invalid);
            self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_MALFORMED);
            self::assertFileDoesNotExist($marker);
            self::assertSame($invalid, \file_get_contents($path));
        }
        $handle = \fopen($path, 'wb');
        self::assertIsResource($handle);
        try {
            self::assertTrue(\ftruncate($handle, RoutesArtifactSchema::MAX_ARTIFACT_BYTES + 1));
        } finally {
            \fclose($handle);
        }
        self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_OVERSIZED);
    }

    public function testSchemaFingerprintTargetEnvironmentAndReservedHydrationAreRejected(): void
    {
        $base = RoutingIntegrationFixture::envelope('original');
        $wrongFingerprint = $base;
        $wrongFingerprint['_meta']['fingerprint'] = \str_repeat('0', 64);
        $wrongVersion = $base;
        $wrongVersion['_meta']['schemaVersion'] = 2;
        $wrongTarget = RoutingIntegrationFixture::schema()->createEnvelope([], 'api', 'local');
        $wrongEnv = RoutingIntegrationFixture::schema()->createEnvelope([], 'web', 'prod');
        $reserved = $base;
        $reserved['payload']['routes'] = [
            new RouteDefinition('forbidden', ['GET'], '/health/{id}', 'Handler::show')->toArray(),
        ];
        $reserved['_meta']['fingerprint'] = RoutingIntegrationFixture::fingerprint($reserved['payload'], 'local');
        foreach (
            [
                [$wrongFingerprint, RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID],
                [$wrongVersion, RoutesArtifactInvalidException::REASON_INCOMPATIBLE],
                [$wrongTarget, RoutesArtifactInvalidException::REASON_INCOMPATIBLE],
                [$wrongEnv, RoutesArtifactInvalidException::REASON_FINGERPRINT_INVALID],
                [$reserved, RoutesArtifactInvalidException::REASON_UNSAFE],
            ] as [$envelope, $reason]
        ) {
            RoutingIntegrationFixture::write(
                $this->root . '/routes.php',
                new StablePhpArrayDumper()->dumpNormalized($envelope),
            );
            self::assertRejected($this->root, $reason);
        }
        RoutingIntegrationFixture::write(
            $this->root . '/routes.php',
            new StablePhpArrayDumper()->dumpNormalized($base),
        );
        self::assertSame('original', RoutingIntegrationFixture::reader($this->root)->read()[0]->name());
    }

    public function testUnreadableArtifactIsRejected(): void
    {
        RoutingIntegrationFixture::runFilesystemScenario(self::class, 'exerciseUnreadable');
    }

    public function testNonRegularArtifactAndUnsafeRootComponentsAreRejected(): void
    {
        self::assertTrue(\mkdir($this->root . '/routes.php'));
        self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_UNSAFE);
        \rmdir($this->root . '/routes.php');
        RoutingIntegrationFixture::write($this->root . '/not-directory', 'sentinel');
        self::assertRejected($this->root . '/not-directory/child', RoutesArtifactInvalidException::REASON_UNSAFE);
        if (\function_exists('posix_mkfifo') && @\posix_mkfifo($this->root . '/routes.php', 0600)) {
            try {
                self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_UNSAFE);
            } finally {
                \unlink($this->root . '/routes.php');
            }
        }
    }

    public function testSymlinkedArtifactAndRootAreRejectedWithoutReadingTheTarget(): void
    {
        $target = $this->root . '/real';
        self::assertTrue(\mkdir($target));
        RoutingIntegrationFixture::write(
            $target . '/routes.php',
            new StablePhpArrayDumper()->dumpNormalized(RoutingIntegrationFixture::envelope('original')),
        );
        self::assertTrue(
            \function_exists('symlink') && @\symlink($target . '/routes.php', $this->root . '/routes.php'),
            'Creating the artifact symbolic link failed.',
        );
        self::assertTrue(\is_link($this->root . '/routes.php'));
        self::assertRejected($this->root, RoutesArtifactInvalidException::REASON_UNSAFE);
        self::assertTrue(
            @\symlink($target, $this->root . '/linked'),
            'Creating the artifact-root symbolic link failed.',
        );
        self::assertTrue(\is_link($this->root . '/linked'));
        self::assertRejected($this->root . '/linked', RoutesArtifactInvalidException::REASON_UNSAFE);
        self::assertSame('original', RoutingIntegrationFixture::reader($target)->read()[0]->name());
    }

    public function testReaderUsesOneBoundedHandleAndDoesNotReopenDuringValidation(): void
    {
        RoutingIntegrationFixture::runFilesystemScenario(self::class);
    }

    public static function exerciseUnreadable(string $root): void
    {
        $path = $root . '/routes.php';
        $bytes = new StablePhpArrayDumper()->dumpNormalized(RoutingIntegrationFixture::envelope('original'));
        RoutingIntegrationFixture::write($path, $bytes);
        $reader = RoutingIntegrationFixture::reader($root);

        try {
            RoutingFilesystemProbe::run($root, 'unreadable', static fn () => $reader->read());
            self::fail('Expected unreadable artifact rejection.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame(RoutesArtifactInvalidException::REASON_UNREADABLE, $exception->reason());
            self::assertSame(
                RoutesArtifactInvalidException::ERROR_CODE . ': ' . RoutesArtifactInvalidException::REASON_UNREADABLE,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
        }

        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(0, RoutingFilesystemProbe::$readBytes);
        self::assertSame($bytes, \file_get_contents($path));
        self::assertSame('original', $reader->read()[0]->name());
    }

    public static function exercise(string $root): void
    {
        $path = $root . '/routes.php';
        $bytes = new StablePhpArrayDumper()->dumpNormalized(RoutingIntegrationFixture::envelope('original'));
        RoutingIntegrationFixture::write($path, $bytes);
        $reader = RoutingIntegrationFixture::reader($root);
        $routes = RoutingFilesystemProbe::run($root, 'read', static fn () => $reader->read());
        self::assertSame('original', $routes[0]->name());
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(\strlen($bytes), RoutingFilesystemProbe::$readBytes);
        self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);

        foreach (['opened-nonregular', 'opened-identity'] as $fault) {
            try {
                RoutingFilesystemProbe::run($root, $fault, static fn () => $reader->read());
                self::fail('Expected unsafe opened artifact rejection: ' . $fault);
            } catch (RoutesArtifactInvalidException $exception) {
                self::assertSame(RoutesArtifactInvalidException::REASON_UNSAFE, $exception->reason());
            }
            self::assertTrue(RoutingFilesystemProbe::$faultReached, $fault);
            self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
            self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);
            self::assertSame(0, RoutingFilesystemProbe::$readBytes);
            self::assertSame($bytes, \file_get_contents($path));
        }

        // Removing an invalid suffix after fstat yields valid bytes of the wrong snapshot length.
        RoutingIntegrationFixture::write($path, $bytes . "\n");
        try {
            RoutingFilesystemProbe::run($root, 'shrink', static fn () => $reader->read());
            self::fail('Expected incomplete snapshot rejection.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame(RoutesArtifactInvalidException::REASON_READ_FAILED, $exception->reason());
        }
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);
        self::assertSame(\strlen($bytes), RoutingFilesystemProbe::$readBytes);
        self::assertSame($bytes, \file_get_contents($path));

        // Replace the path only after the opened snapshot has been consumed and closed.
        $replacement = new StablePhpArrayDumper()->dumpNormalized(RoutingIntegrationFixture::envelope('replacement'));
        RoutingIntegrationFixture::write($root . '/replacement.php', $replacement);
        $routes = RoutingFilesystemProbe::run($root, 'replace-after-read', static fn () => $reader->read());
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame('original', $routes[0]->name());
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);
        self::assertSame($replacement, \file_get_contents($path));
        self::assertSame('replacement', $reader->read()[0]->name());

        // Grow the native file after the initial size check, before its first stream read.
        // PHP may read ahead by one stream buffer; the logical result must still be bounded.
        RoutingIntegrationFixture::write($path, $bytes);
        try {
            RoutingFilesystemProbe::run($root, 'grow', static fn () => $reader->read());
            self::fail('Expected oversized snapshot rejection.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame(RoutesArtifactInvalidException::REASON_OVERSIZED, $exception->reason());
        }
        self::assertTrue(RoutingFilesystemProbe::$faultReached);
        self::assertSame(1, RoutingFilesystemProbe::$artifactOpens);
        self::assertSame(1, RoutingFilesystemProbe::$artifactCloses);
        self::assertTrue(RoutingFilesystemProbe::$readBytes <= RoutesArtifactSchema::MAX_ARTIFACT_BYTES + 8192);
        $file = new \ReflectionClass($reader)->getFileName();
        self::assertIsString($file);
        $source = \file_get_contents($file);
        self::assertIsString($source);
        $code = '';
        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token) || !\in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                $code .= \is_array($token) ? $token[1] : $token;
            }
        }
        self::assertMatchesRegularExpression(
            '/stream_get_contents\(\$handle,\s*RoutesArtifactSchema::MAX_ARTIFACT_BYTES\s*\+\s*1\)/',
            $code,
        );
        self::assertDoesNotMatchRegularExpression('/\bfile_get_contents\s*\(/', $code);
        self::assertSame(1, \preg_match_all('/\bfopen\s*\(/', $code));
    }

    private static function assertRejected(string $root, string $reason): void
    {
        try {
            RoutingIntegrationFixture::reader($root)->read();
            self::fail('Expected artifact read failure.');
        } catch (RoutesArtifactInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RoutesArtifactInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
            self::assertNull($exception->getPrevious());
        }
    }
}
