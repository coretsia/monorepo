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
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-public-typed-properties');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/PublicTypedPropertiesDto.php',
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
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-public-promoted-properties');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/PublicPromotedPropertiesDto.php',
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
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-abstract');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/AbstractDto.php',
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
            . "packages/demo/package/src/AbstractDto.php: abstract-class\n",
            $output,
        );
    }

    public function testNonFinalDtoFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-non-final');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/NonFinalDto.php',
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
            . "packages/demo/package/src/NonFinalDto.php: not-final\n",
            $output,
        );
    }

    public function testDtoExtendingAnotherClassFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-extends');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/ExtendingDto.php',
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
            . "packages/demo/package/src/ExtendingDto.php: extends-class\n",
            $output,
        );
    }

    public function testDtoImplementingInterfaceFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-implements');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/ImplementingDto.php',
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
            . "packages/demo/package/src/ImplementingDto.php: implements-interface\n",
            $output,
        );
    }

    public function testDtoUsingTraitFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-trait-use');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/TraitUsingDto.php',
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
            . "packages/demo/package/src/TraitUsingDto.php: uses-trait\n",
            $output,
        );
    }

    public function testDtoWithStaticPropertyFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-static-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/StaticPropertyDto.php',
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
            . "packages/demo/package/src/StaticPropertyDto.php: static-property\n",
            $output,
        );
    }

    public function testDtoWithUntypedPropertyFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-untyped-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/UntypedPropertyDto.php',
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
            . "packages/demo/package/src/UntypedPropertyDto.php: untyped-property\n",
            $output,
        );
    }

    public function testDtoWithNonPublicPropertyFails(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-non-public-property');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/NonPublicPropertyDto.php',
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
            . "packages/demo/package/src/NonPublicPropertyDto.php: non-public-property\n",
            $output,
        );
    }

    public function testUnmarkedClassIsIgnored(): void
    {
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-unmarked-ignored');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/UnmarkedModel.php',
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
        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-path-override');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/demo/package/src/PathOverrideDto.php',
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
        $supportDir = $toolsRoot . '/spikes/_support';

        $this->writeBytesExact(
            $gateDir . '/dto_shape_gate.php',
            $this->readBytes($this->frameworkRoot() . '/tools/gates/dto_shape_gate.php'),
        );

        $this->writeBytesExact(
            $supportDir . '/ConsoleOutput.php',
            $this->readBytes($this->frameworkRoot() . '/tools/spikes/_support/ConsoleOutput.php'),
        );

        $this->writeBytesExact(
            $supportDir . '/ErrorCodes.php',
            $this->readBytes($this->frameworkRoot() . '/tools/spikes/_support/ErrorCodes.php'),
        );

        $scanRoot = $this->syntheticFrameworkRoot('dto-shape-bootstrap-scan-root');

        [$code, $output] = $this->runPhp(
            $gateDir . '/dto_shape_gate.php',
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
    private function runDtoShapeGate(string $scanRoot): array
    {
        return $this->runPhp(
            $this->frameworkRoot() . '/tools/gates/dto_shape_gate.php',
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
