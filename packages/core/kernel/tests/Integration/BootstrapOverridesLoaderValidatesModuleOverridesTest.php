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

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\BootstrapOverridesLoader;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class BootstrapOverridesLoaderValidatesModuleOverridesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = ModeInfrastructureTestSupport::root();
    }

    protected function tearDown(): void
    {
        ModeInfrastructureTestSupport::remove($this->root);
    }

    public function testReadsAllKnownTargetsAndPreservesSourceDuplicatesForPhaseA(): void
    {
        $value = [
            'worker' => [
                'include' => ['core.kernel', 'core.kernel'],
                'exclude' => ['platform.http'],
            ],
            'web' => [
                'include' => [],
                'exclude' => [],
            ],
            'api' => [],
            'console' => [],
        ];
        ModeInfrastructureTestSupport::write(
            $this->root . '/config/app.php',
            '<?php return ' . \var_export(['moduleOverrides' => $value], true) . ';',
        );
        $result = new BootstrapOverridesLoader()->load(new BootstrapInput($this->root, AppTarget::Worker));
        self::assertSame(['api', 'console', 'web', 'worker'], \array_keys($result['moduleOverrides']));
        self::assertSame(['core.kernel', 'core.kernel'], $result['moduleOverrides']['worker']['include']);
        self::assertSame(['platform.http'], $result['moduleOverrides']['worker']['exclude']);
        self::assertSame(['include' => [], 'exclude' => []], $result['moduleOverrides']['api']);
    }

    public function testRejectsMalformedOverrideShapesWithoutChangingBootstrapTaxonomy(): void
    {
        foreach (
            [
                ['desktop' => ['include' => []]],
                ['web' => ['enabled' => ['core.kernel']]],
                ['web' => ['include' => 'core.kernel']],
                ['web' => ['include' => ['core.kernel' => true]]],
                ['web' => ['include' => [new \stdClass()]]],
                ['web' => ['include' => ["core.kernel\n"]]],
                ['web' => [['core.kernel']]],
            ] as $invalid
        ) {
            ModeInfrastructureTestSupport::write(
                $this->root . '/config/app.php',
                '<?php return ' . \var_export(['moduleOverrides' => $invalid], true) . ';',
            );
            try {
                new BootstrapOverridesLoader()->load(new BootstrapInput($this->root, AppTarget::Web));
                self::fail('Invalid module override source must be rejected.');
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
            }
        }
    }
}
