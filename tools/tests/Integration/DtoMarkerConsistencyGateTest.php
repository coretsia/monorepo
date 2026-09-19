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
        $scanRoot = $this->syntheticScanRoot('dto-marker-canonical');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/GoodDto.php',
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
        $scanRoot = $this->syntheticScanRoot('dto-marker-alias');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/AliasedDto.php',
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
        $scanRoot = $this->syntheticScanRoot('dto-marker-custom');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/Attribute/DtoMarker.php',
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
            . "packages/core/demo/src/Attribute/DtoMarker.php: custom-dto-marker-class\n"
            . "packages/core/demo/src/Attribute/DtoMarker.php: multiple-dto-marker-strategies\n",
            $output,
        );
    }

    public function testLegacyDtoInterfaceMarkerFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-marker-interface');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/DtoInterface.php',
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
            . "packages/core/demo/src/DtoInterface.php: legacy-dto-interface-marker\n"
            . "packages/core/demo/src/DtoInterface.php: multiple-dto-marker-strategies\n",
            $output,
        );
    }

    public function testMixedMarkerStrategyFailsWithMultipleStrategiesReason(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-marker-mixed');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/GoodDto.php',
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
            'packages/core/demo/src/Attribute/DtoMarker.php',
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
            . "packages/core/demo/src/Attribute/DtoMarker.php: custom-dto-marker-class\n"
            . "packages/core/demo/src/Attribute/DtoMarker.php: multiple-dto-marker-strategies\n",
            $output,
        );
    }

    public function testPathOverrideWorksOnSyntheticTree(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-marker-path-override');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/GoodDto.php',
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
        $supportDir = $toolsRoot . '/support';

        $this->writeBytesExact(
            $gateDir . '/dto_marker_consistency_gate.php',
            $this->readBytes($this->repoRoot() . '/tools/gates/dto_marker_consistency_gate.php'),
        );

        foreach (
            ['ConsoleOutput.php', 'ErrorCodes.php', 'GateRuntime.php'] as $supportFile
        ) {
            $this->writeBytesExact(
                $supportDir . '/' . $supportFile,
                $this->readBytes(
                    $this->repoRoot()
                    . '/tools/support/'
                    . $supportFile,
                ),
            );
        }

        [$code, $output] = $this->runPhp(
            $gateDir . '/dto_marker_consistency_gate.php',
            [
                '--path=' . $fixtureRoot,
            ],
            $fixtureRoot,
        );

        self::assertSame(1, $code);
        self::assertSame("CORETSIA_DTO_GATE_SCAN_FAILED\n", $output);
    }

    private function syntheticScanRoot(string $name): string
    {
        $repoRoot = $this->tempDir($name);

        $this->ensureDir($repoRoot . '/packages/core/demo/src');
        $this->ensureDir($repoRoot . '/tools/gates');
        $this->copyDir(
            $this->repoRoot() . '/tools/support',
            $repoRoot . '/tools/support',
        );

        $this->writeBytesExact(
            $repoRoot . '/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/dto-marker-fixture\",\n"
            . "  \"type\": \"project\"\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $repoRoot . '/packages/core/demo/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/core-demo\",\n"
            . "  \"type\": \"library\"\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $repoRoot . '/packages/core/dto-attribute/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/core-dto-attribute\",\n"
            . "  \"type\": \"library\"\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $repoRoot . '/packages/core/dto-attribute/src/Attribute/Dto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Coretsia\Dto\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Dto
{
}

PHP,
        );

        $this->writeBytesExact(
            $repoRoot . '/tools/gates/dto_marker_consistency_gate.php',
            $this->readBytes(
                $this->repoRoot()
                . '/tools/gates/dto_marker_consistency_gate.php',
            ),
        );

        $this->writeBytesExact(
            $repoRoot . '/vendor/autoload.php',
            <<<'PHP'
<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../tools/support/*.php') ?: [] as $path) {
    if (basename($path) === 'bootstrap.php') {
        continue;
    }

    require_once $path;
}

PHP,
        );

        return $repoRoot;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runDtoMarkerConsistencyGate(string $scanRoot): array
    {
        return $this->runPhp(
            $scanRoot . '/tools/gates/dto_marker_consistency_gate.php',
            [
                '--path=' . $scanRoot,
            ],
            $scanRoot,
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
