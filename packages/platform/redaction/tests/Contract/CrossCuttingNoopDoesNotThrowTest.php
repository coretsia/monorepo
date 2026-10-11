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

namespace Coretsia\Platform\Redaction\Tests\Contract;

use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Redaction\Module\RedactionModule;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class CrossCuttingNoopDoesNotThrowTest extends TestCase
{
    public function testModuleAndProviderAreLoadableWithoutSideEffects(): void
    {
        \ob_start();

        try {
            $module = new RedactionModule();
            $provider = new RedactionServiceProvider();
        } finally {
            $output = \ob_get_clean();
        }

        self::assertSame('', $output);

        self::assertSame(RedactionModule::MODULE_ID, $module->id());
        self::assertSame(RedactionModule::PACKAGE_ID, $module->packageId());
        self::assertSame(RedactionModule::COMPOSER_PACKAGE, $module->composerPackage());
        self::assertSame(RedactionModule::KIND, $module->kind());

        self::assertSame('platform.redaction', $module->id());
        self::assertSame('platform/redaction', $module->packageId());
        self::assertSame('coretsia/platform-redaction', $module->composerPackage());
        self::assertSame('runtime', $module->kind());

        self::assertSame(
            [RedactionServiceProvider::class],
            $module->providers(),
        );

        self::assertInstanceOf(RedactionServiceProvider::class, $provider);

        self::assertFalse(\defined(RedactionModule::class . '::CONFIG_ROOT'));
        self::assertFalse(\method_exists($module, 'configRoot'));
    }

    public function testDefaultRedactorPreservesSafeValuesWithoutSideEffects(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.noop');

        $value = [
            'count' => 1,
            'enabled' => true,
            'status' => 'ok',
        ];

        \ob_start();

        try {
            $result = $redactor->redactJsonLike($value, $context);
        } finally {
            $output = \ob_get_clean();
        }

        self::assertSame('', $output);
        self::assertSame($value, $result);
    }

    public function testDefaultRedactorUsesPlaceholderWithoutSideEffects(): void
    {
        $redactor = self::redactor();
        $context = new RedactionContext('redaction.noop');

        \ob_start();

        try {
            $result = $redactor->redactValue(
                'synthetic-test-secret',
                RedactionKind::Secret,
                $context,
            );
        } finally {
            $output = \ob_get_clean();
        }

        self::assertSame('', $output);

        self::assertSame(
            [
                'hash' => null,
                'kind' => 'secret',
                'length' => null,
                'mode' => 'placeholder',
                'redacted' => true,
                'schemaVersion' => 1,
            ],
            $result->toArray(),
        );
    }

    public function testPackageMetadataDeclaresConfigFreeRuntimeModuleSurface(): void
    {
        $composer = self::packageComposer();
        $metadata = $composer['extra']['coretsia'] ?? null;

        self::assertIsArray($metadata);

        self::assertSame('coretsia/platform-redaction', $composer['name'] ?? null);
        self::assertSame('library', $composer['type'] ?? null);

        self::assertSame('runtime', $metadata['kind'] ?? null);
        self::assertSame(RedactionModule::MODULE_ID, $metadata['moduleId'] ?? null);
        self::assertSame(RedactionModule::class, $metadata['moduleClass'] ?? null);

        self::assertSame(
            [RedactionServiceProvider::class],
            $metadata['providers'] ?? null,
        );

        self::assertSame(
            ['core.foundation'],
            $metadata['requires'] ?? null,
        );

        self::assertSame([], $metadata['conflicts'] ?? null);

        self::assertArrayNotHasKey('defaultsConfigPath', $metadata);
        self::assertFalse(\is_dir(self::packageRoot() . '/config'));
    }

    private static function redactor(): SensitiveDataRedactorInterface
    {
        return new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function packageComposer(): array
    {
        $composerPath = self::packageRoot() . '/composer.json';

        self::assertFileExists($composerPath);

        $contents = \file_get_contents($composerPath);

        self::assertIsString($contents);

        $decoded = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded);

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private static function packageRoot(): string
    {
        return \dirname(__DIR__, 2);
    }
}
