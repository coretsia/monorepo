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

use Composer\InstalledVersions;
use Coretsia\Kernel\DependencySync\Composer\InstalledProjectVerifier;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use PHPUnit\Framework\TestCase;

final class InstalledProjectVerifierTest extends TestCase
{
    public function testVerifierChecksPlanVendorMetadataAndProtectedRootsThroughInstalledResolutionSeam(): void
    {
        $path = new \ReflectionClass(InstalledProjectVerifier::class)->getFileName();

        self::assertIsString($path);

        $source = \file_get_contents($path);

        self::assertIsString($source);

        foreach (
            [
                'captureProtectedThirdPartyRootState',
                'verify(',
                'INSTALLED_PLAN_MISMATCH',
                'INSTALLED_VENDOR_INCOMPLETE',
                'PROTECTED_THIRD_PARTY_ROOT_CHANGED',
                'resolve(',
            ] as $needle
        ) {
            self::assertStringContainsString($needle, $source);
        }

        self::assertStringContainsString('ModuleResolutionOrchestrator', $source);

        foreach (
            [
                'ComposerManifestReader',
                'ModulePlanResolver',
                'ModuleGraphResolver',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString('Coretsia\\Kernel\\Module\\' . $forbidden, $source);
        }

        $verifySource = self::methodSource(InstalledProjectVerifier::class, 'verify');

        self::assertStringContainsString(
            "\$entry->composerName() !== \$expected['composerName']",
            $verifySource,
        );
        self::assertStringContainsString(
            "self::moduleIdsToStrings(\$entry->requires()) !== \$expected['requires']",
            $verifySource,
        );
        self::assertStringContainsString(
            "self::moduleIdsToStrings(\$entry->conflicts()) !== \$expected['conflicts']",
            $verifySource,
        );
    }

    public function testInstalledReferenceComparisonIsSymmetric(): void
    {
        $verifier = self::verifier();
        $original = InstalledVersions::getRawData();
        $installRoot = self::installRoot();

        try {
            foreach (
                [
                    [
                        'installedReference' => null,
                        'sourceReference' => 'source-ref',
                        'distReference' => null,
                    ],
                    [
                        'installedReference' => null,
                        'sourceReference' => null,
                        'distReference' => 'dist-ref',
                    ],
                    [
                        'installedReference' => 'installed-ref',
                        'sourceReference' => null,
                        'distReference' => null,
                    ],
                ] as $case
            ) {
                self::reloadInstalledPackage($installRoot, $case['installedReference']);

                try {
                    self::invokePrivate(
                        $verifier,
                        'assertInstalledIdentity',
                        [
                            'vendor/protected',
                            [
                                'type' => 'library',
                                'version' => '1.0.0',
                                'sourceReference' => $case['sourceReference'],
                                'distReference' => $case['distReference'],
                            ],
                            DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                        ],
                    );
                    self::fail('Expected installed reference mismatch.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::INSTALLED_VENDOR_INCOMPLETE,
                        $exception->errorCode(),
                    );
                }
            }
        } finally {
            InstalledVersions::reload($original);
            self::remove($installRoot);
        }
    }

    public function testProtectedRootMutationIsRejectedAcrossLockInstalledAndPhysicalIdentity(): void
    {
        $verifier = self::verifier();
        $original = InstalledVersions::getRawData();
        $installRoot = self::installRoot();
        $frozen = [
            'vendor/protected' => [
                'runtimeRequired' => true,
                'installed' => true,
                'type' => 'library',
                'version' => '1.0.0',
                'sourceReference' => 'source-ref',
                'distReference' => 'dist-ref',
                'installedReference' => 'source-ref',
            ],
        ];

        try {
            self::reloadInstalledPackage($installRoot, 'source-ref');
            self::invokePrivate(
                $verifier,
                'assertProtectedThirdPartyRootState',
                [
                    self::lockDocument(
                        type: 'library',
                        version: '1.0.0',
                        sourceReference: 'source-ref',
                        distReference: 'dist-ref',
                    ),
                    $frozen,
                ],
            );
            self::addToAssertionCount(1);

            foreach (
                [
                    [
                        'type' => 'metapackage',
                        'version' => '1.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'dist-ref',
                        'installed' => true,
                        'installedReference' => 'source-ref',
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '2.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'dist-ref',
                        'installed' => true,
                        'installedReference' => 'source-ref',
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '1.0.0',
                        'sourceReference' => 'other-source',
                        'distReference' => 'dist-ref',
                        'installed' => true,
                        'installedReference' => 'other-source',
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '1.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'other-dist',
                        'installed' => true,
                        'installedReference' => 'source-ref',
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '1.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'dist-ref',
                        'installed' => false,
                        'installedReference' => null,
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '1.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'dist-ref',
                        'installed' => true,
                        'installedReference' => 'dist-ref',
                        'installRoot' => $installRoot,
                    ],
                    [
                        'type' => 'library',
                        'version' => '1.0.0',
                        'sourceReference' => 'source-ref',
                        'distReference' => 'dist-ref',
                        'installed' => true,
                        'installedReference' => 'source-ref',
                        'installRoot' => $installRoot . '/missing',
                    ],
                ] as $case
            ) {
                self::reloadInstalledPackage(
                    $case['installRoot'],
                    $case['installedReference'],
                    $case['installed'],
                );

                try {
                    self::invokePrivate(
                        $verifier,
                        'assertProtectedThirdPartyRootState',
                        [
                            self::lockDocument(
                                type: $case['type'],
                                version: $case['version'],
                                sourceReference: $case['sourceReference'],
                                distReference: $case['distReference'],
                            ),
                            $frozen,
                        ],
                    );
                    self::fail('Expected protected root mutation rejection.');
                } catch (DependencySyncException $exception) {
                    self::assertSame(
                        DependencySyncErrorCodes::PROTECTED_THIRD_PARTY_ROOT_CHANGED,
                        $exception->errorCode(),
                    );
                }
            }
        } finally {
            InstalledVersions::reload($original);
            self::remove($installRoot);
        }
    }

    private static function verifier(): InstalledProjectVerifier
    {
        $facade = ProjectDependencySync::create();
        $coordinator = new \ReflectionProperty($facade, 'syncCoordinator')->getValue($facade);

        self::assertIsObject($coordinator);

        $verifier = new \ReflectionProperty($coordinator, 'installedProjectVerifier')->getValue($coordinator);

        self::assertInstanceOf(InstalledProjectVerifier::class, $verifier);

        return $verifier;
    }

    /** @param list<mixed> $arguments */
    private static function invokePrivate(
        object $object,
        string $method,
        array $arguments,
    ): mixed {
        return new \ReflectionMethod(
            $object,
            $method,
        )->invokeArgs(
            $object,
            $arguments,
        );
    }

    private static function methodSource(
        string $class,
        string $method,
    ): string {
        $reflection = new \ReflectionMethod($class, $method);
        $path = $reflection->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        return \implode(
            '',
            \array_slice(
                $lines,
                $reflection->getStartLine() - 1,
                $reflection->getEndLine() - $reflection->getStartLine() + 1,
            ),
        );
    }

    private static function installRoot(): string
    {
        $root = \sys_get_temp_dir() . '/coretsia-verifier-installed-' . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        return $root;
    }

    private static function reloadInstalledPackage(
        string $installRoot,
        ?string $reference,
        bool $installed = true,
    ): void {
        $versions = [];

        if ($installed) {
            $versions['vendor/protected'] = [
                'pretty_version' => '1.0.0',
                'version' => '1.0.0.0',
                'reference' => $reference,
                'type' => 'library',
                'install_path' => $installRoot,
                'aliases' => [],
                'dev_requirement' => false,
            ];
        }

        InstalledVersions::reload([
            'root' => [
                'name' => 'coretsia/verifier-test-root',
                'pretty_version' => '1.0.0',
                'version' => '1.0.0.0',
                'reference' => null,
                'type' => 'project',
                'install_path' => __DIR__,
                'aliases' => [],
                'dev' => true,
            ],
            'versions' => $versions,
        ]);
    }

    private static function lockDocument(
        string $type,
        string $version,
        ?string $sourceReference,
        ?string $distReference,
    ): \stdClass {
        $package = new \stdClass();
        $package->name = 'vendor/protected';
        $package->version = $version;
        $package->type = $type;

        if ($sourceReference !== null) {
            $package->source = (object) [
                'reference' => $sourceReference,
            ];
        }

        if ($distReference !== null) {
            $package->dist = (object) [
                'reference' => $distReference,
            ];
        }

        $document = new \stdClass();
        $document->packages = [$package];
        $document->{'packages-dev'} = [];

        return $document;
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || \is_file($path)) {
            @\unlink($path);

            return;
        }

        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }

        @\rmdir($path);
    }
}
