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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Platform\Cli\Tests\Integration\Support\CliIntegrationTestCase;

final class PreAutoloadFailureIsFixedAndPathSafeTest extends CliIntegrationTestCase
{
    private const string FAILURE = 'CORETSIA_CLI_BOOTSTRAP_FAILED: autoload-unavailable';

    public function testMissingAndInvalidComposerAutoloadHaveOneFixedPreAutoloadDiagnostic(): void
    {
        $root = self::temporaryRoot('preautoload');
        try {
            $launcher = $root . '/coretsia';
            self::write($launcher, self::launcher());
            self::assertSafeFailure(self::execute($launcher, ['list']), self::FAILURE);

            \mkdir($root . '/vendor/autoload.php', 0700, true);
            self::assertSafeFailure(self::execute($launcher, ['list']), self::FAILURE);
            \rmdir($root . '/vendor/autoload.php');

            self::write($root . '/vendor/autoload.php', '<?php return null;');
            \chmod($root . '/vendor/autoload.php', 0000);
            if (!\is_readable($root . '/vendor/autoload.php')) {
                self::assertSafeFailure(self::execute($launcher, ['list']), self::FAILURE);
            }
        } finally {
            self::removeTree($root);
        }
    }

    public function testConflictingAutoloadInputsAreRejectedBeforeAnyApplicationClassIsLoaded(): void
    {
        $root = self::temporaryRoot('conflict');
        try {
            $script = $root . '/coretsia';
            self::write($script, self::launcher(true));
            self::write($root . '/vendor/autoload.php', "<?php throw new \\RuntimeException('PRIVATE_SENTINEL');");
            $result = self::execute($script, ['list']);
            self::assertSafeFailure($result, self::FAILURE);
            self::assertStringNotContainsString('PRIVATE_SENTINEL', $result['stderr']);
            self::assertStringNotContainsString($root, $result['stderr']);
        } finally {
            self::removeTree($root);
        }
    }

    public function testSkeletonMissingOrUnreadableProxyFailsBeforeRequire(): void
    {
        $root = self::temporaryRoot('skeleton-preautoload');
        try {
            $script = $root . '/coretsia';
            self::write(
                $script,
                (string) \file_get_contents(self::repositoryRoot() . '/packages/applications/skeleton/coretsia'),
            );
            self::assertSafeFailure(self::execute($script, ['list']), self::FAILURE);
            \mkdir($root . '/vendor/bin/coretsia', 0700, true);
            self::assertSafeFailure(self::execute($script, ['list']), self::FAILURE);
            \rmdir($root . '/vendor/bin/coretsia');
            self::write($root . '/vendor/bin/coretsia', '<?php throw new \\RuntimeException("PRIVATE_SENTINEL");');
            \chmod($root . '/vendor/bin/coretsia', 0000);
            if (!\is_readable($root . '/vendor/bin/coretsia')) {
                self::assertSafeFailure(self::execute($script, ['list']), self::FAILURE);
            }
        } finally {
            self::removeTree($root);
        }
    }

    private static function launcher(bool $conflict = false): string
    {
        $binary = \var_export(self::repositoryRoot() . '/packages/platform/cli/bin/coretsia', true);
        return '<?php ' . "\n" . '$GLOBALS[\'coretsia_application_root\'] = __DIR__;' . "\n"
            . ($conflict ? '$_composer_autoload_path = __DIR__ . \'/vendor/autoload.php\';' . "\n" : '')
            . '$result = require ' . $binary . ';' . "\n";
    }
}
