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

namespace Coretsia\Kernel\Tests\Unit\Config;

use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use PHPUnit\Framework\TestCase;

final class ConfigRulesLoaderRejectsCallableRulesTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-config-rules-loader-callable-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testRejectsClosureInsideRulesArray(): void
    {
        $rulesFile = $this->writeRulesFile(
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    'configRoot' => 'kernel',
    'schemaVersion' => 1,
    'keys' => [
        'boot' => [
            'type' => static function (): void {
            },
        ],
    ],
];
PHP
        );

        try {
            new ConfigRulesLoader()->loadRulesets([
                self::source($rulesFile),
            ]);

            self::fail('Expected ConfigInvalidException was not thrown.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_RULESET_INVALID, $exception->reason());
            self::assertSame([], $exception->violations());
            self::assertStringNotContainsString('Closure', $exception->getMessage());
            self::assertStringNotContainsString('boot', $exception->getMessage());
        }
    }

    public function testRejectsCallableArrayInsideRulesArray(): void
    {
        $rulesFile = $this->writeRulesFile(
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    'configRoot' => 'kernel',
    'schemaVersion' => 1,
    'keys' => [
        'boot' => [
            'validator' => [
                \Coretsia\Kernel\Tests\Unit\Config\ConfigRulesLoaderCallableRulesFixture::class,
                'validate',
            ],
        ],
    ],
];
PHP
        );

        try {
            new ConfigRulesLoader()->loadRulesets([
                self::source($rulesFile),
            ]);

            self::fail('Expected ConfigInvalidException was not thrown.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_RULESET_INVALID, $exception->reason());
            self::assertSame([], $exception->violations());
            self::assertStringNotContainsString('ConfigRulesLoaderCallableRulesFixture', $exception->getMessage());
            self::assertStringNotContainsString('validate', $exception->getMessage());
            self::assertStringNotContainsString('validator', $exception->getMessage());
        }
    }

    private function writeRulesFile(string $contents): string
    {
        $directory = $this->temporaryDirectory . '/package/config';

        \mkdir($directory, 0777, true);

        $path = $directory . '/rules.php';

        \file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @return array{
     *     root: string,
     *     packageId: string,
     *     moduleId: null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId: string,
     *     precedence: int
     * }
     */
    private static function source(string $filesystemPath): array
    {
        return [
            'root' => 'kernel',
            'packageId' => 'core/kernel',
            'moduleId' => null,
            'path' => 'framework/packages/core/kernel/config/rules.php',
            'filesystemPath' => $filesystemPath,
            'sourceId' => 'core/kernel/config/rules/kernel',
            'precedence' => 10,
        ];
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

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);

                continue;
            }

            \unlink($itemPath);
        }

        \rmdir($path);
    }
}

final class ConfigRulesLoaderCallableRulesFixture
{
    public static function validate(): void
    {
    }
}
