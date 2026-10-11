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
use Coretsia\Kernel\DependencySync\Composer\ComposerSyncCoordinator;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Process\DependencySyncProcessRunner;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Tools\Support\ReleaseLine;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('slow')]
final class DependencySyncFreshProcessVerificationTest extends TestCase
{
    private static bool $composerVerified = false;

    private static ?string $consumerTemplate = null;

    public static function tearDownAfterClass(): void
    {
        if (self::$consumerTemplate !== null) {
            self::remove(self::$consumerTemplate);
            self::$consumerTemplate = null;
        }
    }

    public function testVerifierAdapterUsesOnlyPublicFacadeAndMachineProtocolBoundary(): void
    {
        $repoRoot = \dirname(__DIR__, 3);
        $source = \file_get_contents($repoRoot . '/packages/core/kernel/bin/dependency-sync-verify.php');

        self::assertIsString($source);
        self::assertStringContainsString('ProjectDependencySync::create()', $source);
        self::assertStringContainsString('->verifyInstalled(', $source);

        foreach (
            [
                'InstalledProjectVerifier',
                'ComposerManifestReader',
                'ModulePlanResolver',
                'ModuleGraphResolver',
                'ModuleResolutionOrchestrator',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }

        self::assertStringContainsString('ob_start()', $source);
        self::assertStringContainsString('ob_end_clean()', $source);
    }

    public function testFreshVerifierTimeoutMapsToVerificationExecutionFailure(): void
    {
        $method = new \ReflectionMethod(ComposerSyncCoordinator::class, 'runFreshVerifier');
        $path = $method->getFileName();

        self::assertIsString($path);

        $lines = \file($path);

        self::assertIsArray($lines);

        $methodSource = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        self::assertStringContainsString('VERIFICATION_TIMEOUT_SECONDS', $methodSource);
        self::assertStringContainsString("\$result['timedOut']", $methodSource);
        self::assertStringContainsString('VERIFICATION_EXECUTION_FAILED', $methodSource);
    }

    public function testBufferedConsumerOutputIsDiscardedBeforeCanonicalProtocolEmission(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);

            \file_put_contents(
                $root . '/config/app.php',
                "<?php\n\n"
                . "echo 'buffered-noise';\n\n"
                . "return [];\n",
            );

            $result = self::runInstalledVerifier($root, $input);

            self::assertSame(0, $result['exitCode']);
            self::assertFalse($result['timedOut']);
            self::assertSame('{"schemaVersion":1,"status":"ok"}', $result['stdout']);
        } finally {
            self::remove($root);
        }
    }

    public function testDirectStdoutContaminationIsRejectedByParentProtocolBoundary(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);

            \file_put_contents(
                $root . '/config/app.php',
                "<?php\n\n"
                . "fwrite(STDOUT, 'protocol-noise');\n\n"
                . "return [];\n",
            );

            self::assertSame(
                DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
                self::parentVerifierErrorCode($root, $input),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testStructurallyInvalidVerificationInputNeverEntersInstalledVerifier(): void
    {
        $root = self::consumer();
        $marker = $root . '/verification-entered';

        try {
            $validInput = self::verificationInput($root);
            $count = 0;
            $unsupportedSchema = \str_replace(
                '"schemaVersion":1',
                '"schemaVersion":2',
                $validInput,
                $count,
            );

            self::assertSame(1, $count);

            \file_put_contents(
                $root . '/config/app.php',
                "<?php\n\n"
                . "file_put_contents("
                . \var_export($marker, true)
                . ", 'entered');\n\n"
                . "return [];\n",
            );

            foreach (
                [
                    '{',
                    $unsupportedSchema,
                    \str_repeat(
                        'x',
                        ProjectDependencySync::MAX_VERIFICATION_INPUT_BYTES + 1,
                    ),
                ] as $input
            ) {
                $result = self::runInstalledVerifier($root, $input);

                self::assertSame(2, $result['exitCode']);
                self::assertFalse($result['timedOut']);
                self::assertSame(
                    [
                        'schemaVersion' => 1,
                        'status' => 'error',
                        'code' => DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID,
                    ],
                    \json_decode(
                        $result['stdout'],
                        true,
                        512,
                        \JSON_THROW_ON_ERROR,
                    ),
                );
                self::assertFileDoesNotExist($marker);
            }
        } finally {
            self::remove($root);
        }
    }

    public function testValidInputWithInstalledMetadataDivergenceFailsWithPlanMismatch(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);
            $path = $root . '/vendor/composer/installed.json';
            $document = \json_decode(
                (string) \file_get_contents($path),
                true,
                512,
                \JSON_THROW_ON_ERROR,
            );

            self::assertIsArray($document);
            self::assertIsArray($document['packages'] ?? null);

            $updated = false;

            foreach ($document['packages'] as &$package) {
                if (
                    !\is_array($package)
                    || ($package['name'] ?? null)
                    !== 'coretsia/core-kernel'
                ) {
                    continue;
                }

                self::assertIsArray($package['extra']['coretsia'] ?? null);

                $package['extra']['coretsia']
                ['requires'] = [];
                $updated = true;

                break;
            }

            unset($package);

            self::assertTrue($updated);

            \file_put_contents(
                $path,
                \json_encode(
                    $document,
                    \JSON_THROW_ON_ERROR
                    | \JSON_PRETTY_PRINT
                    | \JSON_UNESCAPED_SLASHES,
                ) . "\n",
            );

            self::assertSame(
                DependencySyncErrorCodes::INSTALLED_PLAN_MISMATCH,
                self::directVerifierErrorCode($root, $input),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testValidInputWithChangedPolicyFailsWithInstalledPlanMismatch(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);

            \file_put_contents(
                $root . '/config/app.php',
                "<?php\n\n"
                . "return ['preset' => 'hybrid'];\n",
            );

            self::assertSame(
                DependencySyncErrorCodes::INSTALLED_PLAN_MISMATCH,
                self::directVerifierErrorCode($root, $input),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testTrustedPolicyMutationDuringVerificationIsCaughtByFinalStateRecheck(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);

            \file_put_contents(
                $root . '/config/app.php',
                <<<'PHP'
<?php

file_put_contents(
    dirname(__DIR__) . '/composer.json',
    " \n",
    FILE_APPEND,
);

return [];
PHP,
            );

            self::assertSame(
                DependencySyncErrorCodes::PROJECT_STATE_CHANGED,
                self::directVerifierErrorCode($root, $input),
            );
        } finally {
            self::remove($root);
        }
    }

    public function testParentRejectsMalformedNonCanonicalAndExitCodeMismatchProtocol(): void
    {
        $cases = [
            ['not-json', 0],
            [
                '{"schemaVersion":1,"status":"ok"}',
                1,
            ],
            [
                ' {"schemaVersion":1,"status":"ok"}',
                0,
            ],
            [
                '{"status":"ok","schemaVersion":1}',
                0,
            ],
            [
                '{"schemaVersion":1,"status":"ok",'
                . '"status":"ok"}',
                0,
            ],
            [\str_repeat('x', 4097), 0],
        ];

        foreach ($cases as [$stdout, $exitCode]) {
            $root = self::consumer();

            try {
                $input = self::verificationInput($root);

                self::writeProtocolFixture(
                    $root,
                    $stdout,
                    $exitCode,
                );

                self::assertSame(
                    DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
                    self::parentVerifierErrorCode($root, $input),
                );
            } finally {
                self::remove($root);
            }
        }
    }

    public function testMissingVerifierScriptFailsWithVerificationExecutionCode(): void
    {
        $root = self::consumer();

        try {
            $input = self::verificationInput($root);
            $script = self::installedVerifierPath($root);

            self::assertFileExists($script);
            self::assertTrue(\unlink($script));

            self::assertSame(
                DependencySyncErrorCodes::VERIFICATION_EXECUTION_FAILED,
                self::parentVerifierErrorCode($root, $input),
            );
            self::assertDirectoryDoesNotExist($root . '/var/dependency-sync/recovery');
        } finally {
            self::remove($root);
        }
    }

    private static function verificationInput(
        string $root,
    ): string {
        $script = $root . '/bin/build-verification-input.php';

        \file_put_contents(
            $script,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\Verification\DependencySyncVerificationCodec;

$root = dirname(__DIR__);

$plan = ProjectDependencySync::create()->plan(
    $root,
    new ProjectInstallationIntent(
        new ProjectApplicationSet([
            AppTarget::Web,
        ]),
    ),
);

$manifest = (string) file_get_contents($root . '/composer.json');
$lock = (string) file_get_contents($root . '/composer.lock');

$planning = DependencySyncVerificationCodec::encodeApprovedPlanningPayload(
            $plan,
            'sha256:'
            . hash('sha256', $manifest),
        );

echo DependencySyncVerificationCodec::encodeEnvelope(
        $planning,
        [],
        'sha256:' . hash('sha256', $lock),
    );
PHP,
        );

        $result = new DependencySyncProcessRunner()
            ->runPhpScript(
                $root,
                $script,
                [],
                60,
            );

        self::assertSame(
            0,
            $result['exitCode'],
            $result['stderr'],
        );
        self::assertFalse($result['timedOut']);
        self::assertNotSame('', $result['stdout']);

        return $result['stdout'];
    }

    /**
     * @return array{
     *     exitCode: int,
     *     timedOut: bool,
     *     stdout: string,
     *     stderr: string
     * }
     */
    private static function runInstalledVerifier(
        string $root,
        string $input,
    ): array {
        return new DependencySyncProcessRunner()
            ->runPhpScript(
                $root,
                self::installedVerifierPath($root),
                [],
                60,
                $input,
            );
    }

    private static function directVerifierErrorCode(
        string $root,
        string $input,
    ): string {
        $result = self::runInstalledVerifier($root, $input);

        self::assertSame(
            2,
            $result['exitCode'],
            $result['stderr'],
        );
        self::assertFalse($result['timedOut']);

        $decoded = \json_decode(
            $result['stdout'],
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);
        self::assertSame('error', $decoded['status'] ?? null);
        self::assertIsString($decoded['code'] ?? null);

        return $decoded['code'];
    }

    private static function parentVerifierErrorCode(
        string $root,
        string $input,
    ): string {
        $script = $root . '/bin/run-parent-verifier.php';

        \file_put_contents(
            $script,
            <<<'PHP'
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;

$root = dirname(__DIR__);
$input = stream_get_contents(STDIN);

if (!is_string($input)) {
    exit(1);
}

try {
    $facade = ProjectDependencySync::create();

    $property = new ReflectionProperty($facade, 'syncCoordinator');
    $coordinator = $property->getValue($facade);

    $method = new ReflectionMethod($coordinator, 'runFreshVerifier');
    $method->invoke($coordinator, $root, $input);

    fwrite(STDOUT, 'ok');
    exit(0);
} catch (DependencySyncException $exception) {
    fwrite(STDOUT, $exception->errorCode());

    exit(2);
}
PHP,
        );

        $result = new DependencySyncProcessRunner()
            ->runPhpScript(
                $root,
                $script,
                [],
                70,
                $input,
            );

        self::assertSame(2, $result['exitCode'], $result['stderr']);
        self::assertFalse($result['timedOut']);

        return $result['stdout'];
    }

    private static function writeProtocolFixture(
        string $root,
        string $stdout,
        int $exitCode,
    ): void {
        \file_put_contents(
            self::installedVerifierPath($root),
            "<?php\n"
            . 'fwrite(STDOUT, '
            . \var_export($stdout, true)
            . ");\n"
            . 'exit('
            . $exitCode
            . ");\n",
        );
    }

    private static function installedVerifierPath(
        string $root,
    ): string {
        return $root . '/vendor/coretsia/core-kernel/' . 'bin/dependency-sync-verify.php';
    }

    private static function consumer(): string
    {
        if (self::$consumerTemplate === null) {
            self::$consumerTemplate = self::createConsumerTemplate();
        }

        $root = \sys_get_temp_dir()
            . '/coretsia-fresh-verification-'
            . \bin2hex(\random_bytes(8));

        self::cloneConsumerTemplate(
            self::$consumerTemplate,
            $root,
        );

        return $root;
    }

    private static function createConsumerTemplate(): string
    {
        self::requireComposer();

        $root = \sys_get_temp_dir()
            . '/coretsia-fresh-verification-template-'
            . \bin2hex(\random_bytes(8));
        $repoRoot = \dirname(__DIR__, 3);
        $repositoryRoot = $root . '/repository';

        \mkdir($repositoryRoot, 0777, true);
        \mkdir($root . '/bin', 0777, true);
        \mkdir($root . '/config', 0777, true);

        \file_put_contents(
            $root . '/config/app.php',
            "<?php\n\nreturn [];\n",
        );

        $publicConstraint = ReleaseLine::fromFile(
            $repoRoot . '/tools/release/release-line.json',
        )->publicConstraint();
        $releaseVersion = \substr($publicConstraint, 1);

        $repositories = [];

        foreach (
            [
                [
                    'coretsia/core-contracts',
                    $repoRoot . '/packages/core/contracts',
                    $releaseVersion,
                ],
                [
                    'coretsia/core-foundation',
                    $repoRoot . '/packages/core/foundation',
                    $releaseVersion,
                ],
                [
                    'coretsia/core-kernel',
                    $repoRoot . '/packages/core/kernel',
                    $releaseVersion,
                ],
                [
                    'coretsia/framework',
                    $repoRoot . '/packages/framework',
                    $releaseVersion,
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

        \file_put_contents(
            $root . '/composer.json',
            \json_encode(
                [
                    'name' => 'coretsia/test-fresh-verification',
                    'type' => 'project',
                    'minimum-stability' => 'stable',
                    'prefer-stable' => true,
                    'repositories' => $repositories,
                    'require' => [
                        'php' => '^8.4',
                        'coretsia/framework' => $publicConstraint,
                        'coretsia/core-foundation' => $publicConstraint,
                        'coretsia/core-kernel' => $publicConstraint,
                    ],
                    'config' => [
                        'sort-packages' => true,
                    ],
                ],
                \JSON_THROW_ON_ERROR
                | \JSON_PRETTY_PRINT
                | \JSON_UNESCAPED_SLASHES,
            ) . "\n",
        );

        $install = new DependencySyncProcessRunner()
            ->runComposer(
                $root,
                [
                    'install',
                    '--no-interaction',
                    '--no-plugins',
                    '--no-scripts',
                    '--no-ansi',
                    '--no-progress',
                ],
                300,
            );

        self::assertSame(
            0,
            $install['exitCode'],
            $install['stdout'] . $install['stderr'],
        );
        self::assertFalse($install['timedOut']);

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
                    \str_starts_with($normalized, 'repository/')
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

        \mkdir($destination, 0777, true);

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
                    \mkdir(
                        $target,
                        0777,
                        true,
                    );
                }

                continue;
            }

            \copy(
                $item->getPathname(),
                $target,
            );
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
                    [
                        '--version',
                        '--no-ansi',
                    ],
                    30,
                );

            self::assertFalse(
                $result['timedOut'],
                'Bare Composer executable timed out during fresh verification acceptance probe.',
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
