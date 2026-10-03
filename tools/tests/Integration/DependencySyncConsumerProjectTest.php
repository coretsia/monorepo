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

use Composer\InstalledVersions;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
final class DependencySyncConsumerProjectTest extends TestCase
{
    private static bool $composerVerified = false;

    /** @var array<string, string> */
    private static array $consumerTemplates = [];

    public static function tearDownAfterClass(): void
    {
        foreach (self::$consumerTemplates as $template) {
            self::remove($template);
        }

        self::$consumerTemplates = [];
    }

    public function testConsumerAdapterSupportsExplicitPlanReviewApplyTargetsAndNoImplicitAppScan(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $source = \file_get_contents($repoRoot . '/packages/applications/skeleton/bin/dependency-sync.php');

        self::assertIsString($source);
        self::assertStringContainsString("['plan', 'review', 'apply']", $source);
        self::assertStringContainsString("'--target='", $source);
        self::assertStringContainsString("'--preset='", $source);
        self::assertStringContainsString('new ProjectApplicationSet($targets)', $source);
        self::assertStringContainsString('$sync = ProjectDependencySync::create();', $source);
        self::assertStringNotContainsString('glob(', $source);
        self::assertStringNotContainsString('scandir(', $source);
        self::assertStringNotContainsString('AppTarget::cases()', $source);
    }

    public function testRealConsumerMicroSyncProducesConsistentLockVendorAndPlan(): void
    {
        $root = self::consumer();

        try {
            \mkdir($root . '/apps/worker', 0777, true);

            $payload = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                ],
            );

            self::assertSame(
                ['web'],
                $payload['result']['plan']['applications'] ?? null,
            );
            self::assertNotContains(
                'platform.worker',
                $payload['result']['plan']['unionModuleIds'] ?? [],
            );

            $validation = self::runComposer(
                $root,
                [
                    'validate',
                    '--no-check-publish',
                    '--check-lock',
                    '--no-interaction',
                    '--no-plugins',
                    '--no-scripts',
                    '--no-ansi',
                ],
            );

            self::assertSame(0, $validation['exitCode'], $validation['stderr']);
            self::assertTrue(
                self::installedState($root, 'coretsia/core-foundation')['installed'],
            );
            self::assertTrue(
                self::installedState($root, 'coretsia/core-kernel')['installed'],
            );
            self::assertFalse(
                self::installedState($root, 'coretsia/platform-worker')['installed'],
            );
        } finally {
            self::remove($root);
        }
    }

    public function testRealConsumerHybridInstallsPlatformWorkerWhenCatalogProvidesIt(): void
    {
        $root = self::consumer();

        try {
            $payload = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            self::assertContains(
                'platform.worker',
                $payload['result']['plan']['unionModuleIds'] ?? [],
            );
            self::assertTrue(
                self::installedState($root, 'coretsia/platform-worker')['installed'],
            );
        } finally {
            self::remove($root);
        }
    }

    public function testTwoExplicitTargetsShareOneComposerGraphButRetainSeparatePlans(): void
    {
        $root = self::consumer();

        try {
            $payload = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--target=worker',
                    '--preset=web=micro',
                    '--preset=worker=hybrid',
                ],
            );
            $plan = $payload['result']['plan'] ?? [];

            self::assertSame(
                ['web', 'worker'],
                $plan['applications'] ?? null,
            );
            self::assertNotContains(
                'platform.worker',
                $plan['enabledModuleIdsByTarget']['web'] ?? [],
            );
            self::assertContains(
                'platform.worker',
                $plan['enabledModuleIdsByTarget']['worker'] ?? [],
            );
            self::assertFileExists($root . '/composer.lock');
        } finally {
            self::remove($root);
        }
    }

    public function testPolicyChangeRemovesOnlyStaleManagedRoot(): void
    {
        $root = self::consumer('plain');

        try {
            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            $before = self::manifest($root);

            $micro = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=micro',
                ],
            );

            self::assertSame(
                [],
                $micro['result']['managedRequireWrites'] ?? null,
            );
            self::assertSame(
                ['coretsia/platform-worker'],
                $micro['result']['managedRequireRemovals'] ?? null,
            );

            $validation = self::runComposer(
                $root,
                [
                    'validate',
                    '--no-check-publish',
                    '--check-lock',
                    '--no-interaction',
                    '--no-plugins',
                    '--no-scripts',
                    '--no-ansi',
                ],
            );

            self::assertSame(0, $validation['exitCode'], $validation['stderr']);

            $after = self::manifest($root);

            self::assertSame(
                $before['require']['acme/protected'] ?? null,
                $after['require']['acme/protected'] ?? null,
            );
            self::assertArrayNotHasKey('coretsia/platform-worker', $after['require']);
            self::assertNotContains(
                'coretsia/platform-worker',
                $after['extra']['coretsia']['dependencySync']['managedRequire'] ?? [],
            );
            self::assertFalse(
                self::installedState($root, 'coretsia/platform-worker')['installed'],
            );
        } finally {
            self::remove($root);
        }
    }

    public function testRemovedManagedRootMayRemainInstalledAsProjectOwnedTransitiveDependency(): void
    {
        $root = self::consumer('transitive-worker');

        try {
            $beforeProtected = self::installedState($root, 'acme/protected');

            self::assertTrue(
                self::installedState($root, 'coretsia/platform-worker')['installed'],
            );

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            $hybridManifest = self::manifest($root);

            self::assertArrayHasKey('coretsia/platform-worker', $hybridManifest['require']);
            self::assertContains(
                'coretsia/platform-worker',
                $hybridManifest['extra']['coretsia']
                ['dependencySync']['managedRequire'] ?? [],
            );

            $micro = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=micro',
                ],
            );
            $microManifest = self::manifest($root);

            self::assertArrayNotHasKey('coretsia/platform-worker', $microManifest['require']);
            self::assertNotContains(
                'coretsia/platform-worker',
                $microManifest['extra']['coretsia']
                ['dependencySync']['managedRequire'] ?? [],
            );
            self::assertNotContains(
                'platform.worker',
                $micro['result']['plan']['unionModuleIds'] ?? [],
            );
            self::assertTrue(
                self::installedState($root, 'coretsia/platform-worker')['installed'],
            );
            self::assertSame(
                $beforeProtected,
                self::installedState($root, 'acme/protected'),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testIncompatiblePreservedRootPackageConstraintFailsVisibly(): void
    {
        $root = self::consumer('conflict');

        try {
            $payload = self::failedAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            self::assertSame(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                $payload['code'] ?? null,
            );
            self::assertSame(
                DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                $payload['context']['causeCode'] ?? null,
            );
        } finally {
            self::remove($root);
        }
    }

    public function testSecondSyncFromFreshPhpProcessIsStableNoOp(): void
    {
        $root = self::consumer();

        try {
            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                ],
            );

            $second = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                ],
            );

            self::assertFalse($second['result']['changed'] ?? true);
        } finally {
            self::remove($root);
        }
    }

    public function testPostUpdateVerificationRunsFromFreshPhpProcess(): void
    {
        $root = self::consumer();

        try {
            self::writeFreshProcessPreset($root);

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=fresh-check',
                ],
            );

            $path = $root . '/var/fresh-verifier-pids.log';

            self::assertFileExists($path);

            $pids = \array_values(
                \array_unique(
                    \array_filter(
                        \array_map(
                            'intval',
                            \file(
                                $path,
                                \FILE_IGNORE_NEW_LINES
                                | \FILE_SKIP_EMPTY_LINES,
                            ) ?: [],
                        ),
                    ),
                ),
            );

            self::assertGreaterThanOrEqual(2, \count($pids));
        } finally {
            self::remove($root);
        }
    }

    public function testKernelReplacementUsesUpdatedImplementationForFreshVerification(): void
    {
        $root = self::consumer();

        try {
            $before = self::lockPackage($root, 'coretsia/core-kernel');

            self::assertSame('0.7.0', $before['version']);

            self::prepareKernelReplacement($root);

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--allow-broad-update',
                ],
            );

            $after = self::lockPackage($root, 'coretsia/core-kernel');

            self::assertSame('0.7.1', $after['version']);

            $marker = $root . '/var/replaced-kernel-verifier-pid';

            self::assertFileExists($marker);

            $pid = \trim(
                (string) \file_get_contents($marker),
            );

            self::assertMatchesRegularExpression('/\A[1-9][0-9]*\z/D', $pid);
        } finally {
            self::remove($root);
        }
    }

    public function testConsumerOutsideMonorepoReceivesNoRepositoryOrMonorepoPath(): void
    {
        $root = self::consumer();
        $repoRoot = \realpath(\dirname(__DIR__, 3));

        self::assertIsString($repoRoot);

        try {
            $canonicalRoot = \realpath($root);

            self::assertIsString($canonicalRoot);
            self::assertFalse(
                \str_starts_with(
                    $canonicalRoot,
                    \rtrim($repoRoot, '/\\') . \DIRECTORY_SEPARATOR,
                ),
            );

            $before = self::manifest($root)['repositories'] ?? [];

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                ],
            );

            $after = self::manifest($root)['repositories'] ?? [];

            self::assertSame($before, $after);

            foreach ($after as $repository) {
                self::assertIsArray($repository);

                $url = $repository['url'] ?? '';

                self::assertIsString($url);
                self::assertStringNotContainsString($repoRoot, $url);
            }
        } finally {
            self::remove($root);
        }
    }

    public function testInducedComposerFailureRetainsDocumentedRecoveryState(): void
    {
        $root = self::consumer('conflict');

        try {
            $payload = self::failedAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            self::assertSame(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                $payload['code'] ?? null,
            );

            $receipt = $payload['context']['recoveryReceiptId'] ?? null;

            self::assertIsString($receipt);
            self::assertMatchesRegularExpression('/\A[A-Za-z0-9_-]{1,128}\z/D', $receipt);
            self::assertDirectoryExists($root . '/var/dependency-sync/recovery/' . $receipt);
        } finally {
            self::remove($root);
        }
    }

    public function testFixedPresetAppliesOnlyToItsDeclaredTarget(): void
    {
        $root = self::consumer();

        try {
            $payload = self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--target=worker',
                    '--preset=worker=hybrid',
                ],
            );
            $plan = $payload['result']['plan'] ?? [];

            self::assertSame(
                [
                    'web' => 'micro',
                    'worker' => 'hybrid',
                ],
                $plan['effectivePresetsByTarget'] ?? null,
            );
            self::assertSame(
                ['worker' => 'hybrid'],
                $plan['fixedPresetByTarget'] ?? null,
            );
        } finally {
            self::remove($root);
        }
    }

    public function testProtectedThirdPartyRootKeepsExactLockedAndInstalledIdentity(): void
    {
        $root = self::consumer('plain');

        try {
            $beforeLock = self::lockPackage($root, 'acme/protected');
            $beforeInstalled = self::installedState($root, 'acme/protected');

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            self::assertSame(
                $beforeLock,
                self::lockPackage($root, 'acme/protected'),
            );
            self::assertSame(
                $beforeInstalled,
                self::installedState($root, 'acme/protected'),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testInstalledProtectedDevelopmentRootKeepsExactIdentity(): void
    {
        $root = self::consumer('plain', true);

        try {
            $beforeManifest = self::manifest($root);
            $beforeLock = self::lockPackage($root, 'acme/protected');
            $beforeInstalled = self::installedState($root, 'acme/protected');

            self::assertSame(
                '^1.0',
                $beforeManifest['require-dev']['acme/protected'] ?? null,
            );
            self::assertTrue(
                $beforeInstalled['physicalPackageRoot'] ?? false,
            );

            self::successfulAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            $afterManifest = self::manifest($root);

            self::assertSame(
                '^1.0',
                $afterManifest['require-dev']['acme/protected'] ?? null,
            );
            self::assertSame(
                $beforeLock,
                self::lockPackage($root, 'acme/protected'),
            );
            self::assertSame(
                $beforeInstalled,
                self::installedState($root, 'acme/protected'),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testSolveRequiringProtectedRootChangeFailsInsteadOfUpdatingIt(): void
    {
        $root = self::consumer('conflict');

        try {
            $beforeLock = self::lockPackage($root, 'acme/protected');
            $beforeInstalled = self::installedState($root, 'acme/protected');

            self::writeProtectedPackage(
                self::protectedPackageRoot($root),
                '1.1.0',
                false,
            );

            $payload = self::failedAdapter(
                $root,
                [
                    'apply',
                    '--target=web',
                    '--preset=web=hybrid',
                ],
            );

            self::assertSame(
                DependencySyncErrorCodes::RECOVERY_REQUIRED,
                $payload['code'] ?? null,
            );
            self::assertSame(
                DependencySyncErrorCodes::COMPOSER_EXECUTION_FAILED,
                $payload['context']['causeCode'] ?? null,
            );
            self::assertSame(
                $beforeLock,
                self::lockPackage($root, 'acme/protected'),
            );
            self::assertSame(
                $beforeInstalled,
                self::installedState($root, 'acme/protected'),
            );
        } finally {
            self::remove($root);
        }
    }

    private static function consumer(
        ?string $protectedMode = null,
        bool $protectedDevelopment = false,
    ): string {
        $templateKey = ($protectedMode ?? 'none') . ':' . ($protectedDevelopment ? 'dev' : 'runtime');

        if (!isset(self::$consumerTemplates[$templateKey])) {
            self::$consumerTemplates[$templateKey] = self::createConsumerTemplate(
                $protectedMode,
                $protectedDevelopment,
            );
        }

        $root = \sys_get_temp_dir() . '/coretsia-real-consumer-' . \bin2hex(\random_bytes(8));

        self::cloneConsumerTemplate(
            self::$consumerTemplates[$templateKey],
            $root,
        );

        return $root;
    }

    private static function createConsumerTemplate(
        ?string $protectedMode,
        bool $protectedDevelopment,
    ): string {
        self::requireComposer();

        $root = \sys_get_temp_dir()
            . '/coretsia-real-consumer-template-'
            . \bin2hex(\random_bytes(8));
        $repoRoot = \dirname(__DIR__, 3);
        $repositoryRoot = $root . '/repository';

        \mkdir($repositoryRoot, 0777, true);
        \mkdir($root . '/bin', 0777, true);
        \mkdir($root . '/config', 0777, true);

        self::copyTree(
            $repoRoot . '/packages/applications/skeleton/bin',
            $root . '/bin',
        );
        self::copyTree(
            $repoRoot . '/packages/applications/skeleton/config',
            $root . '/config',
        );

        $repositories = [];

        foreach (
            [
                [
                    'coretsia/core-contracts',
                    $repoRoot . '/packages/core/contracts',
                    '0.7.0',
                ],
                [
                    'coretsia/core-foundation',
                    $repoRoot . '/packages/core/foundation',
                    '0.7.0',
                ],
                [
                    'coretsia/core-kernel',
                    $repoRoot . '/packages/core/kernel',
                    '0.7.0',
                ],
                [
                    'coretsia/framework',
                    $repoRoot . '/packages/framework',
                    '0.7.0',
                ],
                [
                    'coretsia/platform-worker',
                    $repoRoot . '/packages/platform/worker',
                    '0.7.0',
                ],
            ] as [$name, $source, $version]
        ) {
            $repositories[] = self::copyPackageRepository(
                $repositoryRoot,
                $name,
                $source,
                $version,
            );
        }

        foreach (
            [
                'psr/clock',
                'psr/container',
                'psr/log',
            ] as $name
        ) {
            $source = InstalledVersions::getInstallPath($name);
            $version = InstalledVersions::getPrettyVersion($name);

            self::assertIsString($source);
            self::assertIsString($version);

            $repositories[] = self::copyPackageRepository(
                $repositoryRoot,
                $name,
                $source,
                $version,
            );
        }

        $require = [
            'php' => '^8.4',
            'coretsia/framework' => '^0.7.0',
        ];
        $requireDev = [];

        if ($protectedMode !== null) {
            $protectedRoot = self::protectedPackageRoot($root);

            self::writeProtectedPackage(
                $protectedRoot,
                '1.0.0',
                $protectedMode === 'conflict',
                $protectedMode === 'transitive-worker',
            );

            $repositories[] = [
                'type' => 'path',
                'url' => 'repository/acme-protected',
                'options' => [
                    'symlink' => false,
                ],
            ];

            if ($protectedDevelopment) {
                $requireDev['acme/protected'] = '^1.0';
            } else {
                $require['acme/protected'] = '^1.0';
            }
        }

        $manifest = [
            'name' => 'coretsia/test-consumer',
            'type' => 'project',
            'minimum-stability' => 'stable',
            'prefer-stable' => true,
            'repositories' => $repositories,
            'require' => $require,
        ];

        if ($requireDev !== []) {
            $manifest['require-dev'] = $requireDev;
        }

        $manifest['config'] = [
            'sort-packages' => true,
        ];

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                $manifest,
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $install = self::runComposer(
            $root,
            [
                'install',
                '--no-interaction',
                '--no-plugins',
                '--no-scripts',
                '--no-ansi',
                '--no-progress',
            ],
        );

        self::assertSame(
            0,
            $install['exitCode'],
            $install['stdout'] . $install['stderr'],
        );

        self::writeInstalledProbe($root);

        return $root;
    }

    /** @return array<string, mixed> */
    private static function copyPackageRepository(
        string $repositoryRoot,
        string $name,
        string $source,
        string $version,
    ): array {
        $destination = $repositoryRoot
            . '/'
            . \str_replace('/', '-', $name);

        self::copyTree($source, $destination);

        return [
            'type' => 'path',
            'url' => 'repository/' . \str_replace('/', '-', $name),
            'options' => [
                'symlink' => false,
                'versions' => [
                    $name => $version,
                ],
            ],
        ];
    }

    private static function writeProtectedPackage(
        string $root,
        string $version,
        bool $conflictsWithWorker,
        bool $requiresWorker = false,
    ): void {
        if (!\is_dir($root . '/src')) {
            \mkdir($root . '/src', 0777, true);
        }

        $composer = [
            'name' => 'acme/protected',
            'version' => $version,
            'type' => 'library',
            'require' => [
                'php' => '^8.4',
            ],
            'autoload' => [
                'psr-4' => [
                    'Acme\\Protected\\' => 'src/',
                ],
            ],
        ];

        if ($requiresWorker) {
            $composer['require']['coretsia/platform-worker'] = '^0.7.0';
        }

        if ($conflictsWithWorker) {
            $composer['conflict'] = [
                'coretsia/platform-worker' => '*',
            ];
        }

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                $composer,
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
        \file_put_contents(
            $root . '/src/ProtectedPackage.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Acme\\Protected;\n\n"
            . "final class ProtectedPackage {}\n",
        );
    }

    private static function protectedPackageRoot(
        string $consumerRoot,
    ): string {
        return $consumerRoot . '/repository/acme-protected';
    }

    private static function prepareKernelReplacement(
        string $root,
    ): void {
        $kernelRoot = $root . '/repository/coretsia-core-kernel';
        $sourcePath = $kernelRoot . '/src/DependencySync/ProjectDependencySync.php';
        $source = \file_get_contents($sourcePath);

        self::assertIsString($source);

        $needle = <<<'PHP'
    public function verifyInstalled(
        string $projectRoot,
        string $verificationInput,
    ): void {
        $projectRoot = $this->requireActiveConsumerProjectRoot($projectRoot);
PHP;

        $replacement = <<<'PHP'
    public function verifyInstalled(
        string $projectRoot,
        string $verificationInput,
    ): void {
        $projectRoot = $this->requireActiveConsumerProjectRoot($projectRoot);

        \file_put_contents(
            $projectRoot . '/var/replaced-kernel-verifier-pid',
            (string) \getmypid(),
        );
PHP;

        self::assertSame(
            1,
            \substr_count($source, $needle),
        );

        \file_put_contents(
            $sourcePath,
            \str_replace($needle, $replacement, $source),
        );

        $manifest = self::manifest($root);
        $updated = false;

        foreach ($manifest['repositories'] as &$repository) {
            if (
                ($repository['options']['versions']['coretsia/core-kernel'] ?? null)
                !== '0.7.0'
            ) {
                continue;
            }

            $repository['options']['versions']['coretsia/core-kernel'] = '0.7.1';
            $updated = true;

            break;
        }

        unset($repository);

        self::assertTrue($updated);

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                $manifest,
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );
    }

    private static function writeFreshProcessPreset(
        string $root,
    ): void {
        $directory = $root . '/config/modes';

        \mkdir($directory, 0777, true);
        \file_put_contents(
            $directory . '/fresh-check.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$root = \dirname(__DIR__, 2);
$var = $root . '/var';

if (!\is_dir($var)) {
    \mkdir($var, 0777, true);
}

\file_put_contents(
    $var . '/fresh-verifier-pids.log',
    (string) \getmypid() . "\n",
    \FILE_APPEND | \LOCK_EX,
);

return [
    'schemaVersion' => 1,
    'name' => 'fresh-check',
    'description' => null,
    'required' => [
        'core.kernel',
    ],
    'modules' => [],
    'featureBundles' => [],
    'metadata' => [],
];
PHP,
        );
    }

    /** @return array<string, mixed> */
    private static function successfulAdapter(
        string $root,
        array $arguments,
    ): array {
        $result = self::runAdapter($root, $arguments);

        self::assertSame(
            0,
            $result['process']['exitCode'],
            $result['process']['stderr'],
        );
        self::assertSame(
            'ok',
            $result['payload']['status'] ?? null,
        );

        return $result['payload'];
    }

    /** @return array<string, mixed> */
    private static function failedAdapter(
        string $root,
        array $arguments,
    ): array {
        $result = self::runAdapter($root, $arguments);

        self::assertNotSame(
            0,
            $result['process']['exitCode'],
        );
        self::assertSame(
            'error',
            $result['payload']['status'] ?? null,
        );

        return $result['payload'];
    }

    private static function runAdapter(
        string $root,
        array $arguments,
    ): array {
        $process = new DependencySyncProcessRunner()->runPhpScript(
            $root,
            $root . '/bin/dependency-sync.php',
            $arguments,
            300,
        );
        $payload = \json_decode(
            \trim($process['stdout']),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($payload);

        return [
            'process' => $process,
            'payload' => $payload,
        ];
    }

    private static function runComposer(
        string $root,
        array $arguments,
    ): array {
        return new DependencySyncProcessRunner()->runComposer(
            $root,
            $arguments,
            300,
        );
    }

    /** @return array<string, mixed> */
    private static function manifest(string $root): array
    {
        $decoded = \json_decode(
            (string) \file_get_contents($root . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    /** @return array<string, mixed> */
    private static function lockPackage(
        string $root,
        string $packageName,
    ): array {
        $lock = \json_decode(
            (string) \file_get_contents($root . '/composer.lock'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($lock);

        foreach (
            [
                ...($lock['packages'] ?? []),
                ...($lock['packages-dev'] ?? []),
            ] as $record
        ) {
            if (
                \is_array($record)
                && ($record['name'] ?? null) === $packageName
            ) {
                return [
                    'name' => $packageName,
                    'type' => $record['type'] ?? null,
                    'version' => $record['version'] ?? null,
                    'sourceReference' => $record['source']['reference'] ?? null,
                    'distReference' => $record['dist']['reference'] ?? null,
                ];
            }
        }

        self::fail('Package missing from composer.lock: ' . $packageName);
    }

    /** @return array<string, mixed> */
    private static function installedState(
        string $root,
        string $packageName,
    ): array {
        $result = new DependencySyncProcessRunner()->runPhpScript(
            $root,
            $root . '/bin/test-installed-state.php',
            [$packageName],
            60,
        );

        self::assertSame(
            0,
            $result['exitCode'],
            $result['stderr'],
        );

        $decoded = \json_decode(
            $result['stdout'],
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    private static function writeInstalledProbe(
        string $root,
    ): void {
        \file_put_contents(
            $root . '/bin/test-installed-state.php',
            <<<'PHP'
<?php

declare(strict_types=1);

require \dirname(__DIR__) . '/vendor/autoload.php';

$name = $argv[1] ?? '';
$installed = \Composer\InstalledVersions::isInstalled($name);
$payload = [
    'installed' => $installed,
    'version' => null,
    'reference' => null,
    'installPath' => null,
    'physicalPackageRoot' => false,
];

if ($installed) {
    $installPath = \Composer\InstalledVersions::getInstallPath($name);

    $payload['version'] = \Composer\InstalledVersions::getPrettyVersion($name);
    $payload['reference'] = \Composer\InstalledVersions::getReference($name);
    $payload['installPath'] = $installPath;
    $payload['physicalPackageRoot'] = \is_string($installPath) && \is_dir($installPath);
}

\fwrite(
    STDOUT,
    \json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
);
PHP,
        );
    }

    private static function cloneConsumerTemplate(
        string $source,
        string $destination,
    ): void {
        self::assertDirectoryExists($source);

        if (!\is_dir($destination)) {
            \mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $source,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = \substr(
                $item->getPathname(),
                \strlen(
                    \rtrim($source, '/\\'),
                ) + 1,
            );
            $target = $destination . \DIRECTORY_SEPARATOR . $relative;

            if ($item->isDir()) {
                if (!\is_dir($target)) {
                    \mkdir($target, 0777, true);
                }

                continue;
            }

            $normalized = \str_replace('\\', '/', $relative);

            $hardLinkAllowed = \PHP_OS_FAMILY === 'Windows'
                && (
                    (
                        \str_starts_with($normalized, 'repository/')
                        && !\str_starts_with($normalized, 'repository/coretsia-core-kernel/')
                        && !\str_starts_with($normalized, 'repository/acme-protected/')
                    )
                    || (
                        \str_starts_with($normalized, 'vendor/coretsia/')
                        && !\str_starts_with($normalized, 'vendor/coretsia/core-kernel/')
                    )
                    || \str_starts_with($normalized, 'vendor/psr/')
                );

            if (
                $hardLinkAllowed
                && @\link(
                    $item->getPathname(),
                    $target,
                )
            ) {
                continue;
            }

            \copy(
                $item->getPathname(),
                $target,
            );
        }
    }

    private static function copyTree(
        string $source,
        string $destination,
    ): void {
        self::assertDirectoryExists($source);

        if (!\is_dir($destination)) {
            \mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $source,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relative = \substr(
                $item->getPathname(),
                \strlen(\rtrim($source, '/\\')) + 1,
            );
            $target = $destination
                . \DIRECTORY_SEPARATOR
                . $relative;

            if ($item->isDir()) {
                if (!\is_dir($target)) {
                    \mkdir($target, 0777, true);
                }

                continue;
            }

            \copy($item->getPathname(), $target);
        }
    }

    private static function requireComposer(): void
    {
        if (self::$composerVerified) {
            return;
        }

        $root = \sys_get_temp_dir()
            . '/coretsia-composer-probe-'
            . \bin2hex(\random_bytes(8));

        \mkdir($root, 0777, true);

        try {
            $result = new DependencySyncProcessRunner()
                ->runComposer(
                    $root,
                    ['--version', '--no-ansi'],
                    30,
                );

            self::assertFalse(
                $result['timedOut'],
                'Bare Composer executable timed out during real process acceptance probe.',
            );
            self::assertSame(
                0,
                $result['exitCode'],
                $result['stdout'] . $result['stderr'],
            );

            self::$composerVerified = true;
        } finally {
            @\rmdir($root);
        }
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
