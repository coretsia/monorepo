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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class PackageComplianceGateAcceptsGoodFixtureTest extends ToolContractTestCase
{
    public function testGoodFixturePasses(): void
    {
        [$code, $output] = $this->runPackageComplianceGate(
            $this->fixtureRoot('package_good'),
        );

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runPackageComplianceGate(string $scanRoot): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/gates/package_compliance_gate.php',
            [
                '--path=' . $scanRoot,
            ],
            $this->frameworkRoot(),
        );
    }

    private function fixtureRoot(string $name): string
    {
        return $this->frameworkRoot() . '/tools/tests/Fixtures/' . $name;
    }
}
