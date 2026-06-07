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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Kernel\Config\ConfigRulesLoader;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigRulesLoaderRequiresPlainArrayRulesTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = \sys_get_temp_dir()
            . '/coretsia-config-rules-loader-plain-array-'
            . \bin2hex(\random_bytes(8));

        \mkdir($this->temporaryDirectory, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeTree($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testRejectsRulesFileThatDoesNotReturnArray(): void
    {
        $rulesFile = $this->writeRulesFile(
            <<<'PHP'
<?php

declare(strict_types=1);

return 'not-array';
PHP
        );

        try {
            new ConfigRulesLoader()->loadRulesets([
                self::source($rulesFile),
            ]);

            self::fail('Expected ConfigInvalidException was not thrown.');
        } catch (ConfigInvalidException $exception) {
            self::assertSame(ConfigInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(ConfigInvalidException::REASON_RULES_FILE_RETURN_TYPE_INVALID, $exception->reason());
            self::assertSame([], $exception->violations());
            self::assertStringNotContainsString('not-array', $exception->getMessage());
        }
    }

    #[DataProvider('invalidPlainRulesValueProvider')]
    public function testRejectsNonPlainValuesInsideReturnedRulesArray(string $payloadPhp): void
    {
        $rulesFile = $this->writeRulesFile(
            <<<PHP
<?php

declare(strict_types=1);

return [
    'configRoot' => 'kernel',
    'schemaVersion' => 1,
    'keys' => [
        'boot' => [
            'invalid' => {$payloadPhp},
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
            self::assertStringNotContainsString('stdClass', $exception->getMessage());
            self::assertStringNotContainsString('fopen', $exception->getMessage());
            self::assertStringNotContainsString('1.25', $exception->getMessage());
            self::assertStringNotContainsString('boot', $exception->getMessage());
        }
    }

    public function testAcceptsPlainArrayRulesAndReturnsRulesetSourceAndOwnerMetadata(): void
    {
        $rulesFile = $this->writeRulesFile(
            <<<'PHP'
<?php

declare(strict_types=1);

return [
    'configRoot' => 'kernel',
    'schemaVersion' => 1,
    'additionalKeys' => false,
    'keys' => [
        'boot' => [
            'type' => 'map',
            'required' => false,
            'keys' => [
                'default_env' => [
                    'type' => 'non-empty-string-no-ws',
                    'required' => true,
                ],
            ],
        ],
    ],
];
PHP
        );

        $result = new ConfigRulesLoader()->loadRulesets([
            self::source($rulesFile),
        ]);

        self::assertCount(1, $result['rulesets']);
        self::assertInstanceOf(ConfigRuleset::class, $result['rulesets'][0]);
        self::assertSame('kernel', $result['rulesets'][0]->root());

        self::assertArrayHasKey('kernel', $result['sources']);
        self::assertSame('kernel', $result['sources']['kernel']->root());
        self::assertSame('core/kernel/config/rules/kernel', $result['sources']['kernel']->sourceId());
        self::assertSame('framework/packages/core/kernel/config/rules.php', $result['sources']['kernel']->path());
        self::assertSame(10, $result['sources']['kernel']->precedence());

        self::assertSame(
            [
                'kernel' => [
                    'moduleId' => null,
                    'packageId' => 'core/kernel',
                    'path' => 'framework/packages/core/kernel/config/rules.php',
                    'root' => 'kernel',
                    'sourceId' => 'core/kernel/config/rules/kernel',
                ],
            ],
            $result['owners'],
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidPlainRulesValueProvider(): iterable
    {
        yield 'object' => [
            'new \stdClass()',
        ];

        yield 'resource' => [
            '\fopen(__FILE__, "rb")',
        ];

        yield 'float' => [
            '1.25',
        ];
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
