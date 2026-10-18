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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Kernel\Ops\KernelOpsRequest;
use Coretsia\Platform\Cli\Exception\CliInputInvalidException;
use Coretsia\Platform\Cli\Input\ArgvInput;
use Coretsia\Platform\Cli\Kernel\KernelOpsRequestResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class KernelOpsRequestResolverTest extends TestCase
{
    #[DataProvider('structurallyValidTargets')]
    public function testAcceptsOneScalarTargetWithoutKernelDomainValidation(string $target): void
    {
        $input = new ArgvInput(['config:hash', '--target=' . $target], 'config:hash', [], ['target' => $target]);
        $request = new KernelOpsRequestResolver()->resolve($input);
        self::assertInstanceOf(KernelOpsRequest::class, $request);
        self::assertSame($target, $request->appTarget());
    }

    /** @return iterable<string, array{string}> */
    public static function structurallyValidTargets(): iterable
    {
        yield 'web' => ['web'];
        yield 'console' => ['console'];
        yield 'worker' => ['worker'];
        yield 'noncanonical-but-structurally-valid' => ['not-a-kernel-target'];
        yield 'mixed-case-owner-token' => ['Custom_2'];
    }

    #[DataProvider('invalidTargets')]
    public function testRejectsMissingNonScalarAndUnsafeTargets(array $options): void
    {
        $input = new ArgvInput(['config:hash'], 'config:hash', [], $options);
        try {
            new KernelOpsRequestResolver()->resolve($input);
        } catch (CliInputInvalidException $exception) {
            self::assertSame(CliInputInvalidException::REASON_TARGET_INVALID, $exception->reason());
            self::assertSame(CliInputInvalidException::ERROR_CODE, $exception->errorCode());
            return;
        }
        self::fail('Kernel Ops target must be a single non-empty scalar token.');
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTargets(): iterable
    {
        yield 'missing' => [[]];
        yield 'null' => [['target' => null]];
        yield 'bare-flag' => [['target' => true]];
        yield 'false' => [['target' => false]];
        yield 'list' => [['target' => ['web', 'worker']]];
        yield 'one-item-list' => [['target' => ['web']]];
        yield 'empty' => [['target' => '']];
        yield 'space' => [['target' => 'web target']];
        yield 'control' => [['target' => "web\nworker"]];
        yield 'absolute-path' => [['target' => '/private/worker']];
        yield 'url' => [['target' => 'https://example.invalid']];
        yield 'leading-digit' => [['target' => '3worker']];
    }

    public function testResolverSignatureDependsOnlyOnInputInterface(): void
    {
        $method = new \ReflectionMethod(KernelOpsRequestResolver::class, 'resolve');
        $parameters = $method->getParameters();
        self::assertCount(1, $parameters);
        self::assertSame(InputInterface::class, $parameters[0]->getType()?->getName());
        self::assertSame(KernelOpsRequest::class, $method->getReturnType()?->getName());
        self::assertCount(
            0,
            new \ReflectionClass(KernelOpsRequestResolver::class)->getConstructor()?->getParameters() ?? [],
        );
    }
}
