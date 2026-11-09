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
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\Module\ResolvedModuleOverrides;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class BootstrapConfigResolverResolvesModuleOverridesTest extends TestCase
{
    private string $root;

    public function testOnlyTheSelectedAppTargetIsResolvedAndMissingTargetIsEmpty(): void
    {
        $this->source(['worker' => ['include' => ['platform.worker', 'core.kernel'], 'exclude' => ['platform.http']]]);
        $web = $this->resolve(AppTarget::Web)->moduleOverrides();
        self::assertInstanceOf(ResolvedModuleOverrides::class, $web);
        self::assertSame([], $web->include());
        self::assertSame([], $web->exclude());
        $worker = $this->resolve(AppTarget::Worker)->moduleOverrides();
        self::assertSame(['core.kernel', 'platform.worker'], ModeInfrastructureTestSupport::values($worker->include()));
        self::assertSame(['platform.http'], ModeInfrastructureTestSupport::values($worker->exclude()));
        self::assertTrue(new \ReflectionClass($worker)->isReadOnly());
    }

    public function testAbsentAndExplicitlyEmptyModuleOverrideListsRemainDistinctUntilSourceMerge(): void
    {
        $this->source([
            'web' => ['include' => ['platform.logging'], 'exclude' => ['platform.worker']],
            'worker' => ['include' => ['platform.worker']],
        ]);
        ModeInfrastructureTestSupport::write(
            $this->root . '/.env',
            "CORETSIA_BOOT_MODULE_OVERRIDES_INCLUDE={\"web\":[]}\n",
        );

        $web = $this->resolve(AppTarget::Web)->moduleOverrides();
        self::assertSame([], ModeInfrastructureTestSupport::values($web->include()));
        self::assertSame(['platform.worker'], ModeInfrastructureTestSupport::values($web->exclude()));

        $worker = $this->resolve(AppTarget::Worker)->moduleOverrides();
        self::assertSame(['platform.worker'], ModeInfrastructureTestSupport::values($worker->include()));
        self::assertSame([], ModeInfrastructureTestSupport::values($worker->exclude()));
    }

    public function testExplicitEmptyExcludeClearsOnlyTheSelectedTargetField(): void
    {
        $this->source([
            'web' => ['include' => ['platform.logging'], 'exclude' => ['platform.worker']],
            'worker' => ['exclude' => ['platform.logging']],
        ]);
        ModeInfrastructureTestSupport::write(
            $this->root . '/.env',
            "CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={\"web\":[]}\n",
        );

        $web = $this->resolve(AppTarget::Web)->moduleOverrides();
        self::assertSame(['platform.logging'], ModeInfrastructureTestSupport::values($web->include()));
        self::assertSame([], ModeInfrastructureTestSupport::values($web->exclude()));

        $worker = $this->resolve(AppTarget::Worker)->moduleOverrides();
        self::assertSame([], ModeInfrastructureTestSupport::values($worker->include()));
        self::assertSame(['platform.logging'], ModeInfrastructureTestSupport::values($worker->exclude()));
    }

    public function testSourceMergeCannotHideTargetLocalIncludeExcludeConflict(): void
    {
        $this->source(['web' => ['include' => ['core.kernel']]]);
        ModeInfrastructureTestSupport::write(
            $this->root . '/.env',
            "CORETSIA_BOOT_MODULE_OVERRIDES_EXCLUDE={\"web\":[\"core.kernel\"]}\n",
        );

        try {
            $this->resolve();
            self::fail('A merged include/exclude conflict must fail in Phase A.');
        } catch (BootstrapException $exception) {
            self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
        }
    }

    public function testMalformedModuleIdForInactiveTargetIsRejectedBeforeTargetResolution(): void
    {
        $this->source(['worker' => ['include' => ['INVALID.MODULE']]]);

        try {
            $this->resolve(AppTarget::Web);
            self::fail('Inactive target module ids must be lexically validated.');
        } catch (BootstrapException $exception) {
            self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
        }
    }

    public function testRejectsDuplicateNonCanonicalAndOverlappingIdsBeforeSetNormalization(): void
    {
        foreach (
            [
                ['include' => ['core.kernel', 'core.kernel']],
                ['include' => ['CORE.KERNEL']],
                ['include' => ['core.kernel'], 'exclude' => ['core.kernel']],
                ['exclude' => ['platform.http', 'platform.http']],
            ] as $entry
        ) {
            $this->source(['web' => $entry]);
            try {
                $this->resolve();
                self::fail('Malformed bootstrap module overrides must fail in Phase A.');
            } catch (BootstrapException $exception) {
                self::assertSame(BootstrapException::REASON_OVERRIDES_INVALID, $exception->reason());
            }
        }
    }

    protected function setUp(): void
    {
        $this->root = ModeInfrastructureTestSupport::root();
    }

    protected function tearDown(): void
    {
        ModeInfrastructureTestSupport::remove($this->root);
    }

    private function resolve(AppTarget $target = AppTarget::Web): BootstrapConfig
    {
        return ModeInfrastructureTestSupport::bootstrapConfigResolver()->resolve(
            new BootstrapInput($this->root, $target),
            require \dirname(__DIR__, 2) . '/config/kernel.php',
        );
    }

    private function source(array $overrides): void
    {
        ModeInfrastructureTestSupport::write(
            $this->root . '/config/app.php',
            '<?php return ' . \var_export(['moduleOverrides' => $overrides], true) . ';',
        );
    }
}
