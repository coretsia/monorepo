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

final class DtoShapeGateTest extends ToolContractTestCase
{
    public function testCompliantDtoWithPublicTypedPropertiesPasses(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-public-typed-properties');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/PublicTypedPropertiesDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class PublicTypedPropertiesDto
{
    public string $name;

    public int $count;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testCompliantDtoWithPublicPromotedTypedPropertiesPasses(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-public-promoted-properties');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/PublicPromotedPropertiesDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class PublicPromotedPropertiesDto
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testAbstractDtoFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-abstract');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/AbstractDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
abstract class AbstractDto
{
    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/AbstractDto.php: abstract-class\n",
            $output,
        );
    }

    public function testNonFinalDtoFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-non-final');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/NonFinalDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
class NonFinalDto
{
    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/NonFinalDto.php: not-final\n",
            $output,
        );
    }

    public function testDtoExtendingAnotherClassFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-extends');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/ExtendingDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

class BaseDto
{
}

#[Dto]
final class ExtendingDto extends BaseDto
{
    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/ExtendingDto.php: extends-class\n",
            $output,
        );
    }

    public function testDtoImplementingInterfaceFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-implements');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/ImplementingDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

interface DemoContract
{
}

#[Dto]
final class ImplementingDto implements DemoContract
{
    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/ImplementingDto.php: implements-interface\n",
            $output,
        );
    }

    public function testDtoUsingTraitFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-trait-use');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/TraitUsingDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

trait DemoTrait
{
}

#[Dto]
final class TraitUsingDto
{
    use DemoTrait;

    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/TraitUsingDto.php: uses-trait\n",
            $output,
        );
    }

    public function testDtoWithStaticPropertyFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-static-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/StaticPropertyDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class StaticPropertyDto
{
    public static string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/StaticPropertyDto.php: static-property\n",
            $output,
        );
    }

    public function testDtoWithUntypedPropertyFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-untyped-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/UntypedPropertyDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class UntypedPropertyDto
{
    public $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/UntypedPropertyDto.php: untyped-property\n",
            $output,
        );
    }

    public function testDtoWithNonPublicPropertyFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-non-public-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/NonPublicPropertyDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class NonPublicPropertyDto
{
    private string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_SHAPE_VIOLATION\n"
            . "packages/core/demo/src/NonPublicPropertyDto.php: non-public-property\n",
            $output,
        );
    }

    public function testUnmarkedClassIsIgnored(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-unmarked-ignored');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/UnmarkedModel.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

class UnmarkedModel extends BaseModel implements DemoContract
{
    use DemoTrait;

    private static $name;
}

class BaseModel
{
}

interface DemoContract
{
}

trait DemoTrait
{
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testPathOverrideWorksOnSyntheticTree(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-shape-path-override');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/PathOverrideDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

#[\Coretsia\Dto\Attribute\Dto]
final class PathOverrideDto
{
    public function __construct(
        public string $name,
    ) {
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoShapeGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testMissingBootstrapTriggersDeterministicScanFailedCode(): void
    {
        $fixtureRoot = $this->tempDir('dto-shape-missing-bootstrap');
        $toolsRoot = $fixtureRoot . '/tools';
        $gateDir = $toolsRoot . '/gates';
        $supportDir = $toolsRoot . '/support';

        $this->writeBytesExact(
            $gateDir . '/dto_shape_gate.php',
            $this->readBytes(
                $this->repoRoot() . '/tools/gates/dto_shape_gate.php',
            ),
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
            $gateDir . '/dto_shape_gate.php',
            [
                '--path=' . $fixtureRoot,
            ],
            $fixtureRoot,
        );

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_GATE_SCAN_FAILED\n",
            $output,
        );
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
            . "  \"name\": \"coretsia/dto-shape-fixture\",\n"
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
            $repoRoot . '/tools/gates/dto_shape_gate.php',
            $this->readBytes(
                $this->repoRoot() . '/tools/gates/dto_shape_gate.php',
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
    private function runDtoShapeGate(string $scanRoot): array
    {
        return $this->runPhp(
            $scanRoot . '/tools/gates/dto_shape_gate.php',
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
