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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\ModePresetSchemaValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ModePresetSchemaValidatorRejectsOverlappingRequiredModulesTest extends TestCase
{
    #[DataProvider('invalidCollections')]
    public function testRequiredAndModulesOverlapOrSourceDuplicatesAreRejected(
        array $required,
        array $modules,
        string $reason,
    ): void {
        $payload = [
            'schemaVersion' => 1,
            'name' => 'micro',
            'description' => null,
            'required' => $required,
            'modules' => $modules,
            'featureBundles' => [],
            'metadata' => [],
        ];
        try {
            new ModePresetSchemaValidator()->validate('micro', $payload);
            self::fail('Expected invalid preset module-id collection.');
        } catch (ModePresetInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(['preset' => 'micro'], $exception->context());
            self::assertStringNotContainsString('/', $exception->getMessage());
        }
    }

    public static function invalidCollections(): iterable
    {
        yield 'required-modules-overlap' => [
            ['core.kernel'],
            ['core.kernel'],
            ModePresetInvalidException::REASON_SETS_OVERLAP,
        ];
        yield 'duplicate-required' => [
            ['core.kernel', 'core.kernel'],
            [],
            ModePresetInvalidException::REASON_MODULE_IDS_DUPLICATE,
        ];
        yield 'duplicate-modules' => [
            [],
            ['platform.worker', 'platform.worker'],
            ModePresetInvalidException::REASON_MODULE_IDS_DUPLICATE,
        ];
        yield 'non-canonical-required' => [['CORE.KERNEL'], [], ModePresetInvalidException::REASON_MODULE_ID_INVALID];
        yield 'non-canonical-modules' => [
            [],
            ['Platform.Worker'],
            ModePresetInvalidException::REASON_MODULE_ID_INVALID,
        ];
    }
}
