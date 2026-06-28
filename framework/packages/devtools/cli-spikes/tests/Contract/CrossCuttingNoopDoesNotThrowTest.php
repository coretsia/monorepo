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

namespace Coretsia\Devtools\CliSpikes\Tests\Contract;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Devtools\CliSpikes\Command\DeptracGraphCommand;
use Coretsia\Devtools\CliSpikes\Command\DoctorCommand;
use Coretsia\Devtools\CliSpikes\Command\SpikeConfigDebugCommand;
use Coretsia\Devtools\CliSpikes\Command\SpikeFingerprintCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncApplyCommand;
use Coretsia\Devtools\CliSpikes\Command\WorkspaceSyncDryRunCommand;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesBootstrapFailedException;
use Coretsia\Devtools\CliSpikes\Spikes\SpikesExitCodeMapper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testCliPresetReturnsCommandsSubtreeOnly(): void
    {
        $config = require $this->packageRoot() . '/config/cli.php';

        self::assertIsArray($config);
        self::assertFalse(array_is_list($config), 'CLI preset MUST return an associative subtree.');
        self::assertArrayHasKey('commands', $config, 'CLI preset MUST expose commands directly.');
        self::assertArrayNotHasKey('cli', $config, 'CLI preset MUST NOT repeat the root cli key.');
        self::assertCount(1, $config, 'CLI preset MUST currently expose only the commands key.');

        $commands = $config['commands'];

        self::assertIsArray($commands);
        self::assertTrue(array_is_list($commands), 'CLI preset commands MUST be a list.');
        self::assertSame($this->expectedPresetCommandClasses(), $commands);
    }

    /**
     * @throws \ReflectionException
     */
    public function testRegisteredCommandsMatchCliCommandContract(): void
    {
        $names = [];

        foreach ($this->expectedCommandNames() as $className => $expectedName) {
            self::assertTrue(class_exists($className), $className . ' MUST be autoloadable.');

            $class = new ReflectionClass($className);

            self::assertTrue($class->isFinal(), $className . ' MUST remain final.');
            self::assertTrue(
                $class->implementsInterface(CommandInterface::class),
                $className . ' MUST implement CommandInterface.',
            );

            $command = $class->newInstance();

            self::assertInstanceOf(CommandInterface::class, $command);
            self::assertSame($expectedName, $command->name());

            $names[] = $command->name();

            $this->assertNameMethodContract($className);
            $this->assertRunMethodContract($className);
        }

        self::assertSame(
            array_values(array_unique($names)),
            $names,
            'Registered command names MUST be unique.',
        );
    }

    public function testSpikeSupportUsesBinaryExitPolicy(): void
    {
        self::assertSame(0, SpikesExitCodeMapper::SUCCESS);
        self::assertSame(1, SpikesExitCodeMapper::FAILURE);

        self::assertSame(0, SpikesExitCodeMapper::success());
        self::assertSame(1, SpikesExitCodeMapper::failure());

        self::assertSame(0, SpikesExitCodeMapper::fromSuccessFlag(true));
        self::assertSame(1, SpikesExitCodeMapper::fromSuccessFlag(false));
    }

    public function testBootstrapFailureReasonsAreDeterministicSafeTokens(): void
    {
        foreach ($this->allowedBootstrapFailureReasons() as $reason) {
            $exception = new SpikesBootstrapFailedException($reason);

            self::assertSame($reason, $exception->reason());
            self::assertSame($reason, $exception->getMessage());
            self::assertSame(0, $exception->getCode());

            self::assertMatchesRegularExpression('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $reason);
            self::assertStringNotContainsString('/', $reason);
            self::assertStringNotContainsString('\\', $reason);
            self::assertStringNotContainsString(':', $reason);
        }
    }

    public function testBootstrapFailureRejectsUnknownReasonToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('spikes-bootstrap-invalid-reason-token');

        new SpikesBootstrapFailedException('unknown-reason');
    }

    /**
     * @return list<class-string<CommandInterface>>
     */
    private function expectedPresetCommandClasses(): array
    {
        return [
            DoctorCommand::class,
            SpikeFingerprintCommand::class,
            SpikeConfigDebugCommand::class,
            DeptracGraphCommand::class,
            WorkspaceSyncDryRunCommand::class,
            WorkspaceSyncApplyCommand::class,
        ];
    }

    /**
     * @return array<class-string<CommandInterface>, string>
     */
    private function expectedCommandNames(): array
    {
        return [
            DoctorCommand::class => 'doctor',
            SpikeFingerprintCommand::class => 'spike:fingerprint',
            SpikeConfigDebugCommand::class => 'spike:config:debug',
            DeptracGraphCommand::class => 'deptrac:graph',
            WorkspaceSyncDryRunCommand::class => 'workspace:sync --dry-run',
            WorkspaceSyncApplyCommand::class => 'workspace:sync --apply',
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedBootstrapFailureReasons(): array
    {
        return [
            SpikesBootstrapFailedException::REASON_COMPOSER_AUTOLOAD_MISSING,
            SpikesBootstrapFailedException::REASON_LAUNCHER_PATH_UNRESOLVABLE,
            SpikesBootstrapFailedException::REASON_FRAMEWORK_ROOT_UNRESOLVABLE,
            SpikesBootstrapFailedException::REASON_REPO_ROOT_UNRESOLVABLE,
            SpikesBootstrapFailedException::REASON_SPIKES_BOOTSTRAP_MISSING,
        ];
    }

    /**
     * @param class-string<CommandInterface> $className
     * @throws \ReflectionException
     */
    private function assertNameMethodContract(string $className): void
    {
        $method = new ReflectionMethod($className, 'name');

        self::assertTrue($method->isPublic(), $className . '::name() MUST be public.');
        self::assertFalse($method->isStatic(), $className . '::name() MUST be an instance method.');

        $returnType = $method->getReturnType();

        self::assertInstanceOf(
            ReflectionNamedType::class,
            $returnType,
            $className . '::name() MUST declare a named return type.',
        );
        self::assertSame('string', $returnType->getName());
        self::assertFalse($returnType->allowsNull(), $className . '::name() MUST NOT return null.');
    }

    /**
     * @param class-string<CommandInterface> $className
     * @throws \ReflectionException
     */
    private function assertRunMethodContract(string $className): void
    {
        $method = new ReflectionMethod($className, 'run');

        self::assertTrue($method->isPublic(), $className . '::run() MUST be public.');
        self::assertFalse($method->isStatic(), $className . '::run() MUST be an instance method.');

        $parameters = $method->getParameters();

        self::assertCount(2, $parameters, $className . '::run() MUST accept exactly input and output.');

        $inputType = $parameters[0]->getType();
        self::assertInstanceOf(
            ReflectionNamedType::class,
            $inputType,
            $className . '::run() input parameter MUST declare a named type.',
        );
        self::assertSame(InputInterface::class, $inputType->getName());
        self::assertFalse($inputType->allowsNull(), $className . '::run() input parameter MUST NOT be nullable.');

        $outputType = $parameters[1]->getType();
        self::assertInstanceOf(
            ReflectionNamedType::class,
            $outputType,
            $className . '::run() output parameter MUST declare a named type.',
        );
        self::assertSame(OutputInterface::class, $outputType->getName());
        self::assertFalse($outputType->allowsNull(), $className . '::run() output parameter MUST NOT be nullable.');

        $returnType = $method->getReturnType();

        self::assertInstanceOf(
            ReflectionNamedType::class,
            $returnType,
            $className . '::run() MUST declare a named return type.',
        );
        self::assertSame('int', $returnType->getName());
        self::assertFalse($returnType->allowsNull(), $className . '::run() MUST NOT return null.');
    }

    private function packageRoot(): string
    {
        return rtrim(str_replace('\\', '/', dirname(__DIR__, 2)), '/');
    }
}
