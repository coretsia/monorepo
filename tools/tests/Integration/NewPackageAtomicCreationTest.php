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

use Coretsia\Tools\Support\ComposerJson;
use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class NewPackageAtomicCreationTest extends ToolContractTestCase
{
    public function testExistingLayerCreationCommitsFullyPreparedPackageAtomically(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $this->ensureDir($sandbox . '/packages/core');

        $packageDir = $sandbox . '/packages/core/atomic-library';

        self::assertDirectoryDoesNotExist($packageDir);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-library',
                '--kind=library',
            ],
        );

        self::assertSame(0, $code, $output);

        self::assertDirectoryExists($packageDir);
        self::assertDirectoryExists($packageDir . '/src');
        self::assertFileExists($packageDir . '/composer.json');

        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $composerBytes = $this->readBytes($packageDir . '/composer.json');
        $composer = \json_decode(
            $composerBytes,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame(
            'coretsia/core-atomic-library',
            $composer['name'] ?? null,
        );
        self::assertSame(
            'library',
            $composer['extra']['coretsia']['kind'] ?? null,
        );
        self::assertSame('library', $composer['type'] ?? null);
        self::assertSame(
            'Coretsia core/atomic-library library package.',
            $composer['description'] ?? null,
        );
        self::assertSame(
            ['coretsia', 'php', 'framework'],
            $composer['keywords'] ?? null,
        );
        self::assertSame(
            $this->sandboxRootComposerMetadata()['authors'],
            $composer['authors'] ?? null,
        );
        self::assertSame(
            $this->sandboxRootComposerMetadata()['homepage'],
            $composer['homepage'] ?? null,
        );
        self::assertSame(
            $this->sandboxRootComposerMetadata()['support'],
            $composer['support'] ?? null,
        );
        self::assertSame('8.4.0', $composer['config']['platform']['php'] ?? null);
        self::assertTrue($composer['config']['sort-packages'] ?? false);
        self::assertSame(['php' => '^8.4'], $composer['require'] ?? null);
        self::assertArrayNotHasKey('require-dev', $composer);
        self::assertArrayNotHasKey('scripts', $composer);
        self::assertArrayNotHasKey('root-only', $composer['extra']);
        self::assertSame(
            ['Coretsia\\AtomicLibrary\\' => 'src/'],
            $composer['autoload']['psr-4'] ?? null,
        );
        self::assertSame(
            ['Coretsia\\AtomicLibrary\\Tests\\' => 'tests/'],
            $composer['autoload-dev']['psr-4'] ?? null,
        );
        self::assertSame(
            $composerBytes,
            ComposerJson::encodeCanonical(ComposerJson::readObject($packageDir . '/composer.json')),
        );
        self::assertFalse(\str_contains($composerBytes, "\r"));
        self::assertSame("\n", \substr($composerBytes, -1));

        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    public function testDtoAttributeUsesCanonicalPsr4NamespaceAndSourcePath(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $packageDir = $sandbox . '/packages/core/dto-attribute';

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=dto-attribute',
                '--kind=library',
            ],
        );

        self::assertSame(0, $code, $output);
        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame(
            ['Coretsia\\Dto\\Attribute\\' => 'src/Attribute/'],
            $composer['autoload']['psr-4'] ?? null,
        );
        self::assertSame(
            ['Coretsia\\Dto\\Attribute\\Tests\\' => 'tests/'],
            $composer['autoload-dev']['psr-4'] ?? null,
        );
        self::assertStringContainsString(
            'namespace Coretsia\\Dto\\Attribute\\Tests\\Contract;',
            $this->readBytes($packageDir . '/tests/Contract/CrossCuttingNoopDoesNotThrowTest.php'),
        );
    }

    public function testAbsentLayerCreationCommitsPreparedLayerAtomically(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $layerDir = $sandbox . '/packages/integrations';
        $packageDir = $layerDir . '/atomic-runtime';

        self::assertDirectoryDoesNotExist($layerDir);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=integrations',
                '--slug=atomic-runtime',
                '--kind=runtime',
            ],
        );

        self::assertSame(0, $code, $output);

        self::assertDirectoryExists($packageDir);
        self::assertDirectoryExists($packageDir . '/src');
        self::assertFileExists($packageDir . '/composer.json');

        $this->assertPackageScaffoldIsCanonical(
            $sandbox,
            $packageDir,
        );

        $composerBytes = $this->readBytes($packageDir . '/composer.json');
        $composer = \json_decode(
            $composerBytes,
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);
        self::assertSame(
            'coretsia/integrations-atomic-runtime',
            $composer['name'] ?? null,
        );
        self::assertSame(
            'runtime',
            $composer['extra']['coretsia']['kind'] ?? null,
        );
        self::assertSame(
            'Coretsia integrations/atomic-runtime runtime package.',
            $composer['description'] ?? null,
        );
        self::assertSame(
            ['coretsia', 'php', 'framework'],
            $composer['keywords'] ?? null,
        );
        self::assertSame([], $composer['extra']['coretsia']['requires'] ?? null);
        self::assertSame([], $composer['extra']['coretsia']['conflicts'] ?? null);
        self::assertSame(
            'integrations.atomic-runtime',
            $composer['extra']['coretsia']['moduleId'] ?? null,
        );
        self::assertSame(
            'Coretsia\\Integrations\\AtomicRuntime\\Module\\AtomicRuntimeModule',
            $composer['extra']['coretsia']['moduleClass'] ?? null,
        );
        self::assertSame(
            ['Coretsia\\Integrations\\AtomicRuntime\\Provider\\AtomicRuntimeServiceProvider'],
            $composer['extra']['coretsia']['providers'] ?? null,
        );
        self::assertArrayNotHasKey('defaultsConfigPath', $composer['extra']['coretsia']);
        self::assertDirectoryDoesNotExist($packageDir . '/config');
        self::assertFalse(\str_contains($composerBytes, "\r"));
        self::assertSame("\n", \substr($composerBytes, -1));

        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    public function testExplicitMetadataUsesVerbatimDescriptionAndCallerKeywordOrder(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $packageDir = $sandbox . '/packages/platform/tracing';
        $description = 'Coretsia tracing runtime package: deterministic W3C propagation.';
        $keywords = 'coretsia,php,framework,tracing,runtime,w3c,trace-context,traceparent,'
            . 'context-propagation,noop,deterministic,observability,tracing';

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=platform',
                '--slug=tracing',
                '--kind=runtime',
                '--description=' . $description,
                '--keywords=' . $keywords,
            ],
        );

        self::assertSame(0, $code, $output);
        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $bytes = $this->readBytes($packageDir . '/composer.json');
        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame($description, $composer['description'] ?? null);
        self::assertSame(
            [
                'coretsia',
                'php',
                'framework',
                'tracing',
                'runtime',
                'w3c',
                'trace-context',
                'traceparent',
                'context-propagation',
                'noop',
                'deterministic',
                'observability',
            ],
            $composer['keywords'] ?? null,
        );
        self::assertSame('coretsia/platform-tracing', $composer['name'] ?? null);
        self::assertSame(
            'config/tracing.php',
            $composer['extra']['coretsia']['defaultsConfigPath'] ?? null,
        );
        self::assertSame([], require $packageDir . '/config/tracing.php');
        self::assertSame(
            [
                'schemaVersion' => 1,
                'configRoot' => 'tracing',
                'additionalKeys' => false,
                'keys' => [],
            ],
            require $packageDir . '/config/rules.php',
        );
        self::assertSame(['php' => '^8.4'], $composer['require'] ?? null);
        self::assertSame(
            $this->sandboxRootComposerMetadata()['authors'],
            $composer['authors'] ?? null,
        );
        self::assertSame(
            $this->sandboxRootComposerMetadata()['homepage'],
            $composer['homepage'] ?? null,
        );
        self::assertSame(
            $this->sandboxRootComposerMetadata()['support'],
            $composer['support'] ?? null,
        );
        self::assertSame('8.4.0', $composer['config']['platform']['php'] ?? null);
        self::assertSame($bytes, ComposerJson::encodeCanonical($composer));
        self::assertSame([], \glob($sandbox . '/var/tmp/new-package-*') ?: []);
    }

    public function testSplitMetadataArgumentsAndBoundaryLimitsAreSupported(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $description = \str_repeat('x', 240);
        $keywords = \implode(
            ',',
            \array_map(
                static fn (int $i): string => 'keyword-' . $i,
                \range(1, 24),
            ),
        );

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer',
                'devtools',
                '--slug',
                'metadata-boundary',
                '--kind',
                'library',
                '--description',
                $description,
                '--keywords',
                $keywords . ',keyword-1',
            ],
        );

        self::assertSame(0, $code, $output);

        $packageDir = $sandbox . '/packages/devtools/metadata-boundary';
        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame($description, $composer['description'] ?? null);
        self::assertSame(\explode(',', $keywords), $composer['keywords'] ?? null);
        self::assertSame(24, \count($composer['keywords']));
        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);
    }

    public function testExplicitHyphenatedKeywordsReplaceBaselineWithoutInference(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $packageDir = $sandbox . '/packages/platform/keyword-policy';

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=platform',
                '--slug=keyword-policy',
                '--kind=runtime',
                '--keywords',
                'psr-11,data-transfer-object,unit-of-work,psr-11',
            ],
        );

        self::assertSame(0, $code, $output);
        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame(
            'Coretsia platform/keyword-policy runtime package.',
            $composer['description'] ?? null,
        );
        self::assertSame(
            ['psr-11', 'data-transfer-object', 'unit-of-work'],
            $composer['keywords'] ?? null,
        );
        self::assertSame([], \glob($sandbox . '/var/tmp/new-package-*') ?: []);
    }

    public function testRegisteredSnakeCaseConfigRootIsNotDerivedFromHyphenatedSlug(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $packageDir = $sandbox . '/packages/platform/problem-details';

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=platform',
                '--slug=problem-details',
                '--kind=runtime',
            ],
        );

        self::assertSame(0, $code, $output);
        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame(
            'platform.problem-details',
            $composer['extra']['coretsia']['moduleId'] ?? null,
        );

        self::assertSame(
            'config/problem_details.php',
            $composer['extra']['coretsia']['defaultsConfigPath'] ?? null,
        );
        self::assertFileExists($packageDir . '/config/problem_details.php');
        self::assertFileDoesNotExist($packageDir . '/config/problem-details.php');
        self::assertSame([], require $packageDir . '/config/problem_details.php');
        self::assertSame(
            [
                'schemaVersion' => 1,
                'configRoot' => 'problem_details',
                'additionalKeys' => false,
                'keys' => [],
            ],
            require $packageDir . '/config/rules.php',
        );
        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    public function testMalformedConfigRootRegistryFailsBeforeCommit(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $registryPath = $sandbox . '/docs/ssot/config-roots.md';
        $original = $this->readBytes($registryPath);
        $malformed = \str_replace('| `tracing`', '`tracing`', $original);

        self::assertNotSame($original, $malformed);
        $this->writeBytesExact($registryPath, $malformed);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=platform',
                '--slug=tracing',
                '--kind=runtime',
            ],
        );

        self::assertSame(1, $code);
        self::assertSame("CORETSIA_NEW_PACKAGE_FAILED\n", $output);
        self::assertDirectoryDoesNotExist($sandbox . '/packages/platform/tracing');
        self::assertSame([], \glob($sandbox . '/var/tmp/new-package-*') ?: []);
    }

    public function testInvalidRawMetadataFailsBeforeCommitWithoutDisclosingInputs(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $this->ensureDir($sandbox . '/packages/core');

        $cases = [
            ['--description='],
            ['--description', ''],
            ['--description'],
            ['--description= leading'],
            ['--description=trailing '],
            ['--description', "\tleading"],
            ['--description', "trailing\t"],
            ['--description', "multiline\nvalue"],
            ['--description', "control\x01value"],
            ['--description=' . \str_repeat('x', 241)],
            ['--description=' . \str_repeat('é', 121)],
            ['--description', 'duplicate', '--description=duplicate'],
            ['--keywords='],
            ['--keywords', ''],
            ['--keywords'],
            ['--keywords= '],
            ['--keywords', ' '],
            ['--keywords= leading'],
            ['--keywords=trailing '],
            ['--keywords=one, two'],
            ['--keywords=one,two '],
            ['--keywords=one,,two'],
            ['--keywords=,one'],
            ['--keywords=one,'],
            ['--keywords=UPPER'],
            ['--keywords=two--words'],
            ['--keywords=two_words'],
            ['--keywords=-leading'],
            ['--keywords=trailing-'],
            ['--keywords', "one,control\x7F"],
            ['--keywords', "one,control\nvalue"],
            [
                '--keywords=' . \implode(
                    ',',
                    \array_map(
                        static fn (int $i): string => 'keyword-' . $i,
                        \range(1, 25),
                    ),
                ),
            ],
            ['--keywords=one', '--keywords=two'],
        ];

        foreach ($cases as $index => $metadataArgs) {
            $slug = 'invalid-metadata-' . $index;
            [$code, $output] = $this->runNewPackage(
                $sandbox,
                [
                    '--layer=core',
                    '--slug=' . $slug,
                    '--kind=library',
                    ...$metadataArgs,
                ],
            );

            self::assertNotSame(0, $code, $output);
            self::assertSame("CORETSIA_NEW_PACKAGE_FAILED\n", $output);
            self::assertDirectoryDoesNotExist($sandbox . '/packages/core/' . $slug);
            self::assertSame([], \glob($sandbox . '/var/tmp/new-package-*') ?: []);
        }
    }

    public function testValidOptionalSharedComposerContactMetadataIsInherited(): void
    {
        $sandbox = $this->createNewPackageSandbox();
        $metadata = $this->sandboxRootComposerMetadata();

        $metadata['authors'] = [
            [
                'name' => 'Test Contributor',
                'email' => 'contributor@example.test',
                'homepage' => 'https://example.test/contributor',
                'role' => 'Developer',
            ],
        ];

        $metadata['support'] = [
            'issues' => 'https://example.test/issues',
            'source' => 'https://example.test/source',
            'email' => 'support@example.test',
            'irc' => 'irc://irc.example.test/coretsia',
        ];

        $this->writeJson($sandbox . '/composer.json', $metadata);

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=valid-contact-metadata',
                '--kind=library',
            ],
        );

        self::assertSame(0, $code, $output);

        $packageDir = $sandbox . '/packages/core/valid-contact-metadata';
        $composer = ComposerJson::readObject($packageDir . '/composer.json');

        self::assertSame($metadata['authors'], $composer['authors'] ?? null);
        self::assertSame($metadata['support'], $composer['support'] ?? null);

        $this->assertPackageScaffoldIsCanonical($sandbox, $packageDir);

        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    public function testAbsentOrMalformedRootMetadataFailsBeforeCommit(): void
    {
        $canonical = $this->sandboxRootComposerMetadata();
        $cases = [
            ['authors', null],
            ['authors', []],
            ['authors', ['unstructured']],
            ['authors', [['name' => '']]],
            ['authors', [['name' => 'Test Contributor', 'email' => 'not-an-email']]],
            ['authors', [['name' => 'Test Contributor', 'homepage' => 'not-a-url']]],
            ['homepage', null],
            ['homepage', 'invalid-homepage'],
            ['support', null],
            ['support', []],
            ['support', ['issues' => 42]],
            ['support', ['issues' => 'not-a-url']],
            ['support', ['email' => 'not-an-email']],
            ['config', null],
            ['config', ['platform' => []]],
            ['config', ['platform' => ['php' => 84]]],
            ['config', ['platform' => ['php' => '^8.4']]],
        ];

        foreach ($cases as $index => [$key, $value]) {
            $sandbox = $this->createNewPackageSandbox();
            $invalid = $canonical;

            if ($value === null) {
                unset($invalid[$key]);
            } else {
                $invalid[$key] = $value;
            }

            $this->writeJson($sandbox . '/composer.json', $invalid);
            $this->ensureDir($sandbox . '/packages/core');

            [$code, $output] = $this->runNewPackage(
                $sandbox,
                [
                    '--layer=core',
                    '--slug=invalid-root-' . $index,
                    '--kind=library',
                ],
            );

            self::assertNotSame(0, $code, $output);
            self::assertSame("CORETSIA_NEW_PACKAGE_FAILED\n", $output);
            self::assertDirectoryDoesNotExist(
                $sandbox . '/packages/core/invalid-root-' . $index,
            );
            self::assertSame([], \glob($sandbox . '/var/tmp/new-package-*') ?: []);
        }
    }

    public function testFailureBeforeCommitLeavesNoPartialPackageOrStagingTree(): void
    {
        $sandbox = $this->createNewPackageSandbox(
            includeScaffoldTool: false,
        );

        $this->ensureDir($sandbox . '/packages/core');

        $packageDir = $sandbox . '/packages/core/atomic-failure';

        self::assertDirectoryDoesNotExist($packageDir);
        self::assertFileDoesNotExist($sandbox . '/tools/build/sync_package_scaffold.php');

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-failure',
                '--kind=library',
            ],
        );

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString(
            'CORETSIA_NEW_PACKAGE_FAILED',
            $output,
        );
        self::assertDirectoryDoesNotExist($packageDir);

        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    public function testExistingTargetFailsWithoutMutationOrStagingArtifacts(): void
    {
        $sandbox = $this->createNewPackageSandbox();

        $existingPackage = $sandbox . '/packages/core/atomic-existing';

        $this->ensureDir($existingPackage);

        $this->writeBytesExact(
            $existingPackage . '/keep.txt',
            "keep-existing-package\n",
        );

        $before = $this->readBytes($existingPackage . '/keep.txt');

        [$code, $output] = $this->runNewPackage(
            $sandbox,
            [
                '--layer=core',
                '--slug=atomic-existing',
                '--kind=library',
            ],
        );

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString(
            'CORETSIA_NEW_PACKAGE_FAILED',
            $output,
        );
        self::assertDirectoryExists($existingPackage);
        self::assertSame(
            $before,
            $this->readBytes($existingPackage . '/keep.txt'),
        );
        self::assertFileDoesNotExist($existingPackage . '/composer.json');

        self::assertSame(
            [],
            \glob($sandbox . '/var/tmp/new-package-*') ?: [],
        );
    }

    private function createNewPackageSandbox(
        bool $includeScaffoldTool = true,
    ): string {
        $sandbox = $this->tempDir('coretsia-new-package');

        $this->ensureDir($sandbox . '/packages');
        $this->ensureDir($sandbox . '/tools/build');
        $this->ensureDir($sandbox . '/vendor');
        $this->ensureDir($sandbox . '/docs/ssot');

        $this->writeBytesExact(
            $sandbox . '/docs/ssot/config-roots.md',
            $this->readBytes($this->repoRoot() . '/docs/ssot/config-roots.md'),
        );

        $this->writeJson(
            $sandbox . '/composer.json',
            $this->sandboxRootComposerMetadata(),
        );

        $this->writeBytesExact(
            $sandbox . '/LICENSE',
            "test-license\n",
        );

        $this->writeBytesExact(
            $sandbox . '/NOTICE',
            "test-notice\n",
        );

        $this->writeBytesExact(
            $sandbox . '/SECURITY.md',
            "test-security\n",
        );

        $this->writeBytesExact(
            $sandbox . '/vendor/autoload.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn true;\n",
        );

        $this->copyDir(
            $this->repoRoot() . '/tools/support',
            $sandbox . '/tools/support',
        );

        if ($includeScaffoldTool) {
            $this->writeBytesExact(
                $sandbox . '/tools/build/sync_package_scaffold.php',
                $this->readBytes(
                    $this->repoRoot() . '/tools/build/sync_package_scaffold.php',
                ),
            );
        }

        return $sandbox;
    }

    /**
     * @return array<string,mixed>
     */
    private function sandboxRootComposerMetadata(): array
    {
        return [
            'name' => 'coretsia/new-package-test',
            'type' => 'project',
            'keywords' => ['root-only'],
            'require' => [
                'php' => '^9.0',
                'coretsia/core-kernel' => '*',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^12.5',
            ],
            'autoload' => [
                'psr-4' => [
                    'Test\\Root\\' => 'src/',
                ],
            ],
            'scripts' => [
                'root-only' => 'echo root',
            ],
            'extra' => [
                'root-only' => true,
            ],
            'authors' => [
                [
                    'name' => 'Test Contributor',
                    'email' => 'contributor@example.test',
                    'role' => 'Developer',
                ],
            ],
            'homepage' => 'https://example.test/',
            'support' => [
                'issues' => 'https://example.test/issues',
                'source' => 'https://example.test/source',
            ],
            'config' => [
                'platform' => [
                    'php' => '8.4.0',
                ],
            ],
        ];
    }

    private function assertPackageScaffoldIsCanonical(
        string $sandbox,
        string $packageDir,
    ): void {
        [$code, $output] = $this->runPhp(
            $sandbox . '/tools/build/sync_package_scaffold.php',
            [
                '--check',
                $packageDir,
            ],
            $sandbox,
        );

        self::assertSame(
            0,
            $code,
            "Expected final package scaffold --check to pass.\nOutput:\n" . $output,
        );

        self::assertSame(
            '',
            $this->normalizeEol($output),
            'Successful final package scaffold --check must be silent.',
        );
    }

    /**
     * @param list<string> $args
     *
     * @return array{0: int, 1: string}
     */
    private function runNewPackage(string $sandbox, array $args): array
    {
        return $this->runPhp(
            $this->repoRoot() . '/tools/build/new-package.php',
            \array_merge(
                ['--repo-root=' . $sandbox],
                $args,
            ),
            $this->repoRoot(),
        );
    }
}
