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

use Coretsia\Foundation\Tag\TaggedService;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Command\DoctorCommand;
use Coretsia\Platform\Cli\Exception\InvalidCommandTagMetaException;
use PHPUnit\Framework\TestCase;

final class CommandTagSchemaRejectsPriorityMetadataKeyTest extends TestCase
{
    public function testPriorityMetadataKeyAlwaysFailsWithStableSchemaReason(): void
    {
        foreach ([0, 100, -10] as $metadataPriority) {
            $metadata = [
                'name' => DoctorCommand::NAME,
                'summary' => DoctorCommand::SUMMARY,
                'group' => DoctorCommand::GROUP,
                'hidden' => DoctorCommand::HIDDEN,
                'arguments' => DoctorCommand::ARGUMENTS,
                'options' => DoctorCommand::OPTIONS,
                'priority' => $metadataPriority,
            ];

            try {
                new CommandTagSchema()->validate(new TaggedService(DoctorCommand::class, meta: $metadata));
            } catch (InvalidCommandTagMetaException $exception) {
                self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason());
                self::assertSame(
                    InvalidCommandTagMetaException::ERROR_CODE . ': ' . InvalidCommandTagMetaException::REASON_SCHEMA_INVALID,
                    $exception->getMessage(),
                );
                continue;
            }

            self::fail('Metadata priority must be rejected regardless of its value.');
        }
    }
}
