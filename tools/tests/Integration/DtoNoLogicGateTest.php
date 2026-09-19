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

final class DtoNoLogicGateTest extends ToolContractTestCase
{
    public function testDtoWithNoConstructorPasses(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-no-constructor');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/NoConstructorDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class NoConstructorDto
{
    public string $name;
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testDtoWithPromotedPublicTypedPropertiesPasses(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-promoted');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/PromotedDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class PromotedDto
{
    public function __construct(
        public string $name,
        public int $count,
    ) {
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testDtoWithTrivialAssignmentConstructorPasses(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-trivial-assignment');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/TrivialAssignmentDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class TrivialAssignmentDto
{
    public string $name;

    public int $count;

    public function __construct(string $name, int $count)
    {
        $this->name = $name;
        $this->count = $count;
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testUnmarkedClassWithLogicIsIgnored(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-unmarked-ignored');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/UnmarkedModel.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

final class UnmarkedModel
{
    public function __construct(private string $name)
    {
        $this->name = trim($name);
    }

    public function normalizedName(): string
    {
        return strtolower($this->name);
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testDtoWithExtraMethodFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-extra-method');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/BehaviorDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class BehaviorDto
{
    public function __construct(public string $name)
    {
    }

    public function normalizedName(): string
    {
        return trim($this->name);
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/BehaviorDto.php: disallowed-method\n",
            $output,
        );
    }

    public function testConstructorWithFunctionCallFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-function-call');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/FunctionCallDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class FunctionCallDto
{
    public string $name;

    public function __construct(string $name)
    {
        $this->name = trim($name);
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/FunctionCallDto.php: constructor-calls-function\n",
            $output,
        );
    }

    public function testConstructorWithMethodCallFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-method-call');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/MethodCallDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class MethodCallDto
{
    public function __construct(string $name)
    {
        $this->helper($name);
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/MethodCallDto.php: constructor-calls-method\n",
            $output,
        );
    }

    public function testConstructorWithStaticCallFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-static-call');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/StaticCallDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class StaticCallDto
{
    public function __construct(string $name)
    {
        self::normalize($name);
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/StaticCallDto.php: constructor-static-call\n",
            $output,
        );
    }

    public function testConstructorWithIfFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-if');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/IfDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class IfDto
{
    public function __construct(string $name)
    {
        if ($name === '') {
        }
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/IfDto.php: constructor-control-flow\n",
            $output,
        );
    }

    public function testConstructorWithMatchFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-match');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/MatchDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class MatchDto
{
    public function __construct(string $name)
    {
        match ($name) {
            '' => null,
            default => null,
        };
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/MatchDto.php: constructor-control-flow\n",
            $output,
        );
    }

    public function testConstructorWithLoopFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-loop');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/LoopDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class LoopDto
{
    public function __construct(array $values)
    {
        foreach ($values as $value) {
        }
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/LoopDto.php: constructor-loop\n",
            $output,
        );
    }

    public function testConstructorWithTryCatchFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-try-catch');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/TryCatchDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class TryCatchDto
{
    public function __construct(string $name)
    {
        try {
        } catch (\Throwable) {
        }
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/TryCatchDto.php: constructor-try-catch\n",
            $output,
        );
    }

    public function testConstructorWithThrowFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-throw');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/ThrowDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class ThrowDto
{
    public function __construct(\Throwable $error)
    {
        throw $error;
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/ThrowDto.php: constructor-throw\n",
            $output,
        );
    }

    public function testConstructorWithNewObjectFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-new-object');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/NewObjectDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class NewObjectDto
{
    public \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/NewObjectDto.php: constructor-new-object\n",
            $output,
        );
    }

    public function testConstructorWithComputedExpressionFails(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-computed-expression');

        $this->writeSyntheticPhpFile(
            $scanRoot,
            'packages/core/demo/src/ComputedExpressionDto.php',
            <<<'PHP'
<?php

declare(strict_types=1);

namespace Demo\Package;

use Coretsia\Dto\Attribute\Dto;

#[Dto]
final class ComputedExpressionDto
{
    public string $name;

    public function __construct(?string $name)
    {
        $this->name = $name ?? '';
    }
}
PHP,
        );

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(1, $code);
        self::assertSame(
            "CORETSIA_DTO_NO_LOGIC_VIOLATION\n"
            . "packages/core/demo/src/ComputedExpressionDto.php: constructor-nontrivial-body\n",
            $output,
        );
    }

    public function testPathOverrideWorksOnSyntheticTree(): void
    {
        $scanRoot = $this->syntheticScanRoot('dto-no-logic-path-override');

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

        [$code, $output] = $this->runDtoNoLogicGate($scanRoot);

        self::assertSame(0, $code);
        self::assertSame('', $output);
    }

    public function testMissingBootstrapTriggersDeterministicScanFailedCode(): void
    {
        $fixtureRoot = $this->tempDir('dto-no-logic-missing-bootstrap');
        $toolsRoot = $fixtureRoot . '/tools';
        $gateDir = $toolsRoot . '/gates';
        $supportDir = $toolsRoot . '/support';

        $this->writeBytesExact(
            $gateDir . '/dto_no_logic_gate.php',
            $this->readBytes($this->repoRoot() . '/tools/gates/dto_no_logic_gate.php'),
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
            $gateDir . '/dto_no_logic_gate.php',
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
            . "  \"name\": \"coretsia/dto-no-logic-fixture\",\n"
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
            $repoRoot . '/tools/gates/dto_no_logic_gate.php',
            $this->readBytes(
                $this->repoRoot() . '/tools/gates/dto_no_logic_gate.php',
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
    private function runDtoNoLogicGate(string $scanRoot): array
    {
        return $this->runPhp(
            $scanRoot . '/tools/gates/dto_no_logic_gate.php',
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
