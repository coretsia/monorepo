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

final class CommandTagSchemaRejectsUnknownKeysTest extends TestCase
{
    public function testRejectsUnexpectedAndMissingMetadataKeysDeterministically(): void
    {
        $canonical = [
            'name' => DoctorCommand::NAME,
            'summary' => DoctorCommand::SUMMARY,
            'group' => DoctorCommand::GROUP,
            'hidden' => DoctorCommand::HIDDEN,
            'arguments' => DoctorCommand::ARGUMENTS,
            'options' => DoctorCommand::OPTIONS,
        ];

        foreach (
            [
                'unknown-text-key' => \array_merge($canonical, ['description' => 'Extra field.']),
                'unknown-map-key' => \array_merge($canonical, ['owner' => []]),
                'missing-option-key' => \array_diff_key($canonical, ['options' => true]),
                'missing-name-key' => \array_diff_key($canonical, ['name' => true]),
            ] as $case => $metadata
        ) {
            try {
                new CommandTagSchema()->validate(new TaggedService(DoctorCommand::class, meta: $metadata));
            } catch (InvalidCommandTagMetaException $exception) {
                self::assertSame(InvalidCommandTagMetaException::REASON_SCHEMA_INVALID, $exception->reason(), $case);
                self::assertSame(InvalidCommandTagMetaException::ERROR_CODE, $exception->errorCode());
                continue;
            }

            self::fail('Expected unknown or missing key to fail: ' . $case);
        }
    }
}
