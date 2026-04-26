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

final class DtoMarkerConsistencyGateTest extends ToolContractTestCase
{
    public function testCanonicalMarkerUsagePasses(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-canonical');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/GoodDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class GoodDto
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testAliasImportResolvingToCanonicalMarkerPasses(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-alias');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/AliasedDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto as TransportDto;

#[TransportDto]
final class AliasedDto
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testCustomDtoMarkerAttributeFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-custom');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/Attribute/DtoMarker.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class DtoMarker
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_MARKER_VIOLATION\n"
            . "packages/demo/package/src/Attribute/DtoMarker.php: custom-dto-marker-class\n",
            $output,
        );
    }

    public function testLegacyDtoInterfaceMarkerFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-interface');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/DtoInterface.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

interface DtoInterface
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_MARKER_VIOLATION\n"
            . "packages/demo/package/src/DtoInterface.php: legacy-dto-interface-marker\n",
            $output,
        );
    }

    public function testMixedMarkerStrategyFailsWithMultipleStrategiesReason(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-mixed');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/GoodDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class GoodDto
{
}
PHP,
        );

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/Attribute/DtoMarker.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class DtoMarker
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_MARKER_VIOLATION\n"
            . "packages/demo/package/src/Attribute/DtoMarker.php: custom-dto-marker-class\n"
            . "packages/demo/package/src/Attribute/DtoMarker.php: multiple-dto-marker-strategies\n",
            $output,
        );
    }

    public function testPathOverrideWorksOnSyntheticTree(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-path-override');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/GoodDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

#[\Coretsia\Dto\Attribute\Dto]
final class GoodDto
{
}
PHP,
        );

        [$code, $output] = $this->runDtoMarkerConsistencyGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testMissingBootstrapTriggersDeterministicScanFailedCode(): void
    {
        $fixtureRoot = $this->tempDir('dto-marker-missing-bootstrap');
        $toolsRoot = $fixtureRoot . '/tools';
        $gateDir = $toolsRoot . '/gates';
        $supportDir = $toolsRoot . '/spikes/_support';

        $this->writeBytesExact(
            $gateDir . '/dto_marker_consistency_gate.php',
            $this->readBytes($this->frameworkRoot() . '/tools/gates/dto_marker_consistency_gate.php'),
        );

        $this->writeBytesExact(
            $supportDir . '/ConsoleOutput.php',
            $this->readBytes($this->frameworkRoot() . '/tools/spikes/_support/ConsoleOutput.php'),
        );

        $this->writeBytesExact(
            $supportDir . '/ErrorCodes.php',
            $this->readBytes($this->frameworkRoot() . '/tools/spikes/_support/ErrorCodes.php'),
        );

        $scanRoot = $this->syntheticFrameworkRoot('dto-marker-bootstrap-scan-root');

        [$code, $output] = $this->runPhp(
            $gateDir . '/dto_marker_consistency_gate.php',
            [
                '--path=' . $scanRoot,
            ],
            $fixtureRoot,
        );

        self::assertSame(1, $code);
        self::assertSame("CORETSIA_DTO_GATE_SCAN_FAILED\n", $output);
    }

    private function syntheticFrameworkRoot(string $name): string
    {
        return $this->tempDir($name);
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runDtoMarkerConsistencyGate(string $scanRoot): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/gates/dto_marker_consistency_gate.php',
            [
                '--path=' . $scanRoot,
            ],
            $this->frameworkRoot(),
        );
    }

    private function writeSyntheticPhpFile(string $scanRoot, string $relativePath, string $contents): void
    {
        $this->writeBytesExact(
            \rtrim($scanRoot, '/\\') . '/' . \ltrim($relativePath, '/\\'),
            $contents,
        );
    }
}
