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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Kernel\Artifacts\Exception\FingerprintSymlinkForbiddenException;
use Coretsia\Kernel\Artifacts\Fingerprint\DeterministicFileLister;
use PHPUnit\Framework\TestCase;

final class SpikeFingerprintSymlinkForbiddenLockTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = \sys_get_temp_dir()
            . '/coretsia-spike-symlink-lock-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryRoot . '/config', 0777, true);
        \file_put_contents($this->temporaryRoot . '/config/app.php', "<?php\nreturn [];\n");
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryRoot);

        parent::tearDown();
    }

    public function testDirectoryListingRejectsSymlinkWithoutLeakingPath(): void
    {
        if (!\function_exists('symlink')) {
            self::markTestSkipped('symlink() is unavailable in this environment.');
        }

        $target = $this->temporaryRoot . '/config/app.php';
        $link = $this->temporaryRoot . '/config/app-link.php';

        if (!@\symlink($target, $link)) {
            self::markTestSkipped('Symlink creation is not allowed in this environment.');
        }

        try {
            new DeterministicFileLister()->listFiles($this->temporaryRoot);

            self::fail('Expected FingerprintSymlinkForbiddenException was not thrown.');
        } catch (FingerprintSymlinkForbiddenException $exception) {
            self::assertSame(FingerprintSymlinkForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(FingerprintSymlinkForbiddenException::REASON_SYMLINK_FORBIDDEN, $exception->reason());

            self::assertStringNotContainsString($this->temporaryRoot, $exception->getMessage());
            self::assertStringNotContainsString($target, $exception->getMessage());
            self::assertStringNotContainsString($link, $exception->getMessage());
            self::assertStringNotContainsString('app-link.php', $exception->getMessage());
        }
    }

    public function testSingleFileCandidateRejectsSymlinkWithoutLeakingPath(): void
    {
        if (!\function_exists('symlink')) {
            self::markTestSkipped('symlink() is unavailable in this environment.');
        }

        $target = $this->temporaryRoot . '/config/app.php';
        $link = $this->temporaryRoot . '/config/app-candidate.php';

        if (!@\symlink($target, $link)) {
            self::markTestSkipped('Symlink creation is not allowed in this environment.');
        }

        try {
            new DeterministicFileLister()->listFileCandidate($link);

            self::fail('Expected FingerprintSymlinkForbiddenException was not thrown.');
        } catch (FingerprintSymlinkForbiddenException $exception) {
            self::assertSame(FingerprintSymlinkForbiddenException::ERROR_CODE, $exception->errorCode());
            self::assertSame(FingerprintSymlinkForbiddenException::REASON_SYMLINK_FORBIDDEN, $exception->reason());

            self::assertStringNotContainsString($this->temporaryRoot, $exception->getMessage());
            self::assertStringNotContainsString($target, $exception->getMessage());
            self::assertStringNotContainsString($link, $exception->getMessage());
            self::assertStringNotContainsString('app-candidate.php', $exception->getMessage());
        }
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        $items = \scandir($path);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath) && !\is_link($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}
