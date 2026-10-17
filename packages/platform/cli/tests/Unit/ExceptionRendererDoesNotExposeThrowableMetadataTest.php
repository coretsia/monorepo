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

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Platform\Cli\Diagnostics\ExceptionRenderer;
use Coretsia\Platform\Cli\Output\CommandOutputBuffer;
use PHPUnit\Framework\TestCase;

final class ExceptionRendererDoesNotExposeThrowableMetadataTest extends TestCase
{
    public function testRendererAcceptsDescriptorOnlyAndEmitsItsTwoPublicFields(): void
    {
        $parameters = new \ReflectionMethod(ExceptionRenderer::class, 'render')->getParameters();
        self::assertCount(2, $parameters);
        self::assertSame(ErrorDescriptor::class, $parameters[0]->getType()?->getName());
        self::assertSame('Coretsia\\Contracts\\Cli\\Output\\OutputInterface', $parameters[1]->getType()?->getName());

        $buffer = new CommandOutputBuffer();
        new ExceptionRenderer()->render(new ErrorDescriptor('SAFE_ERROR', 'safe-reason'), $buffer);
        self::assertSame(
            [['code' => 'SAFE_ERROR', 'message' => 'safe-reason', 'type' => 'error']],
            $buffer->finalize()->records(),
        );
    }

    public function testRendererIgnoresDescriptorExtensionsEvenWhenTheyContainPrivateMetadata(): void
    {
        $descriptor = new ErrorDescriptor(
            'SAFE_ERROR',
            'safe-reason',
            extensions: ['detail' => 'PRIVATE_THROWABLE_MESSAGE', 'position' => 204],
        );
        $buffer = new CommandOutputBuffer();

        new ExceptionRenderer()->render($descriptor, $buffer);

        self::assertSame(
            [['code' => 'SAFE_ERROR', 'message' => 'safe-reason', 'type' => 'error']],
            $buffer->finalize()->records(),
        );
    }

    public function testRendererCannotInventThrowableMetadata(): void
    {
        $error = new \RuntimeException(
            'SECRET_TOKEN /private/keys.ini',
            0,
            new \RuntimeException('previous-secret'),
        );
        $descriptor = new ErrorDescriptor('SAFE_ERROR', 'handled-failure');
        $buffer = new CommandOutputBuffer();
        new ExceptionRenderer()->render($descriptor, $buffer);
        $rendered = \json_encode($buffer->finalize()->records(), JSON_THROW_ON_ERROR);

        foreach (
            [
                'SECRET_TOKEN',
                '/private/keys.ini',
                'previous-secret',
                __FILE__,
                $error::class,
                'stack_trace',
                'line_number',
                'Throwable',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $rendered);
        }
        self::assertSame(
            [['code' => 'SAFE_ERROR', 'message' => 'handled-failure', 'type' => 'error']],
            \json_decode($rendered, true, flags: JSON_THROW_ON_ERROR)
        );
    }
}
