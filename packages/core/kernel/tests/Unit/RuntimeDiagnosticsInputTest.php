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

namespace Coretsia\Kernel\Tests\Unit;

use Coretsia\Kernel\Runtime\RuntimeDiagnosticsInput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RuntimeDiagnosticsInputTest extends TestCase
{
    #[DataProvider('runtimeStates')]
    public function testPreservesAuthoritativeEnvironmentAndGenerationState(string $appEnv, bool $loaded): void
    {
        $input = new RuntimeDiagnosticsInput(
            appEnv: $appEnv,
            artifactGenerationLoaded: $loaded,
        );

        self::assertSame($appEnv, $input->appEnv());
        self::assertSame($loaded, $input->artifactGenerationLoaded());
    }

    /** @return iterable<string, array{string, bool}> */
    public static function runtimeStates(): iterable
    {
        yield 'local-source' => ['local', false];
        yield 'dev-loaded' => ['dev', true];
        yield 'prod-loaded' => ['prod', true];
        yield 'case-preserved' => ['CustomEnv', false];
    }

    public function testBothInputsAreMandatoryAndTheValueObjectIsImmutable(): void
    {
        $reflection = new \ReflectionClass(RuntimeDiagnosticsInput::class);
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        self::assertSame(2, $constructor->getNumberOfParameters());
        self::assertSame(2, $constructor->getNumberOfRequiredParameters());

        $parameters = $constructor->getParameters();
        self::assertSame(
            ['appEnv', 'artifactGenerationLoaded'],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
                $parameters,
            )
        );
        self::assertSame(
            ['string', 'bool'],
            \array_map(
                static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
                $parameters,
            )
        );
        foreach ($parameters as $parameter) {
            self::assertFalse($parameter->allowsNull());
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    #[DataProvider('invalidEnvironments')]
    public function testRejectsInvalidEnvironment(string $appEnv): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('runtime-diagnostics-input-app-env-invalid');

        new RuntimeDiagnosticsInput($appEnv, false);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidEnvironments(): iterable
    {
        yield 'empty' => [''];
        yield 'leading-space' => [' local'];
        yield 'trailing-space' => ['dev '];
        yield 'line-feed' => ["de\nv"];
        yield 'carriage-return' => ["de\rv"];
        yield 'null-byte' => ["de\0v"];
        yield 'control-byte' => ["de\x1Fv"];
        yield 'delete-byte' => ["de\x7Fv"];
    }

    public function testInputDoesNotResolveEnvironmentOrDiscoverArtifacts(): void
    {
        $source = \file_get_contents(new \ReflectionClass(RuntimeDiagnosticsInput::class)->getFileName());
        self::assertIsString($source);

        foreach (
            [
                'getenv(',
                'putenv(',
                '$_ENV',
                '$_SERVER',
                'file_get_contents(',
                'file_exists(',
                'is_file(',
                'is_dir(',
                'fopen(',
                'realpath(',
                'scandir(',
                'glob(',
                'getcwd(',
                'ArtifactGenerationLocator',
                'BootstrapConfigResolver',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function testRuntimeDiagnosticsInputIsDeclaredInKernelPublicApi(): void
    {
        $manifest = \file_get_contents(\dirname(__DIR__, 2) . '/PUBLIC_API.md');
        self::assertIsString($manifest);
        $lines = \preg_split('/\R/', $manifest);
        self::assertIsArray($lines);
        self::assertContains(
            '- `' . RuntimeDiagnosticsInput::class . '`',
            $lines,
        );
    }
}
