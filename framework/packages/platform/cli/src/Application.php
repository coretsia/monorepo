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

namespace Coretsia\Platform\Cli;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Platform\Cli\Command\HelpCommand;
use Coretsia\Platform\Cli\Command\ListCommand;
use Coretsia\Platform\Cli\Error\ErrorCodes;
use Coretsia\Platform\Cli\Exception\CliCommandClassMissingException;
use Coretsia\Platform\Cli\Exception\CliCommandFailedException;
use Coretsia\Platform\Cli\Exception\CliCommandInvalidException;
use Coretsia\Platform\Cli\Exception\CliConfigInvalidException;
use Coretsia\Platform\Cli\Exception\CliExceptionInterface;
use Coretsia\Platform\Cli\Input\CliInput;
use Coretsia\Platform\Cli\Output\CliOutput;
use Coretsia\Platform\Cli\Output\TrackedOutput;

/**
 * Minimal Phase 0 CLI runtime (kernel-free).
 *
 * Responsibilities:
 * - deterministic config loading + merge (defaults → preset → skeleton)
 * - command registry instantiation (new $fqcn(), zero-arg ctor only)
 * - built-in commands: help/list
 * - deterministic error boundary for CliExceptionInterface
 *
 * IMPORTANT integration contract:
 * - $autoloadFile MUST be the resolved absolute Composer autoload file selected by the launcher.
 * - $launcherFile MUST be the resolved absolute launcher file path (framework/bin/coretsia),
 *   used for deterministic repo root resolution (no probing).
 *
 * @internal
 */
final class Application
{
    private const string DEVTOOLS_PACKAGE = 'coretsia/devtools-cli-spikes';
    private const string PRESET_RELATIVE_FILE = '/coretsia/devtools-cli-spikes/config/cli.php';

    private const string SKELETON_RELATIVE_FILE = '/config/cli.php';

    private readonly string $autoloadFile;
    private readonly string $launcherFile;

    public function __construct(string $autoloadFile, string $launcherFile)
    {
        $this->autoloadFile = $autoloadFile;
        $this->launcherFile = $launcherFile;
    }

    /**
     * Execute the CLI application.
     *
     * Failure boundary (cemented):
     * - Catches CliExceptionInterface and renders exactly 2 lines via CliOutput::error():
     *   line1: code()
     *   line2: reason()
     * - Returns exit code 1 for any caught CliExceptionInterface.
     * - Any other Throwable bubbles to launcher catch-all.
     * @throws \Throwable
     */
    public function run(?array $argv = null): int
    {
        $input = CliInput::fromArgv($argv);

        // Always have an output instance available for deterministic error rendering.
        $output = new CliOutput(true);

        try {
            $cli = $this->loadCliConfigSubtree();

            $redactionEnabled = $this->readRedactionEnabled($cli);
            $output = new CliOutput($redactionEnabled);

            $catalog = $this->buildCommandCatalog($cli);

            $available = $this->availableCommandNames($catalog);

            $help = new HelpCommand($available);
            $list = new ListCommand($available);

            return $this->dispatch($input, $output, $catalog, $help, $list);
        } catch (\Throwable $e) {
            if ($e instanceof CliExceptionInterface) {
                $output->error($e->code(), $e->reason());
                return 1;
            }

            throw $e;
        }
    }

    /**
     * Deterministically load and merge the `cli` subtree:
     * - defaults: framework/packages/platform/cli/config/cli.php
     * - preset: vendor/coretsia/devtools-cli-spikes/config/cli.php (only if devtools package is installed)
     * - skeleton: skeleton/config/cli.php (repo layout derived from launcher path; OPTIONAL file)
     *
     * @return array<string, mixed>
     */
    private function loadCliConfigSubtree(): array
    {
        $defaultsFile = $this->defaultsConfigFile();
        $defaults = $this->loadCliSubtreeFromFile(
            $defaultsFile,
            CliConfigInvalidException::REASON_CLI_SUBTREE_INVALID
        );

        $preset = $this->loadOptionalDevtoolsPresetSubtree();

        // Optional overlay: absence is allowed (user override zone).
        $skeleton = $this->loadSkeletonSubtree();

        // Merge order is single-choice and deterministic: defaults → preset → skeleton.
        $cli = $defaults;
        $cli = $this->mergeCliSubtrees($cli, $preset);
        $cli = $this->mergeCliSubtrees($cli, $skeleton);

        // Post-merge: cement required keys presence deterministically.
        if (!\array_key_exists('commands', $cli)) {
            $cli['commands'] = [];
        }
        if (!\array_key_exists('output', $cli) || !\is_array($cli['output'])) {
            $cli['output'] = [];
        }

        return $cli;
    }

    private function defaultsConfigFile(): string
    {
        // src/Application.php -> package root -> config/cli.php
        return \dirname(__DIR__) . '/config/cli.php';
    }

    /**
     * @return array<string, mixed>
     */
    private function loadOptionalDevtoolsPresetSubtree(): array
    {
        if (!$this->isDevtoolsPresetInstalled()) {
            return [];
        }

        $presetFile = $this->vendorDir() . self::PRESET_RELATIVE_FILE;

        if (!\is_file($presetFile) || !\is_readable($presetFile)) {
            throw CliConfigInvalidException::cliSpikesPresetMissing();
        }

        try {
            return $this->loadCliSubtreeFromFile(
                $presetFile,
                CliConfigInvalidException::REASON_CLI_SPIKES_PRESET_INVALID
            );
        } catch (CliConfigInvalidException $e) {
            // Normalize any shape failure into the prescribed preset-invalid token.
            throw CliConfigInvalidException::cliSpikesPresetInvalid($e);
        }
    }

    /**
     * Skeleton override subtree (user override zone).
     *
     * Policy (cemented):
     * - skeleton/ directory MAY be absent (treated as empty overlay)
     * - skeleton/config/cli.php MAY be absent (treated as empty overlay)
     * - if file exists but is unreadable OR returns invalid subtree => deterministic config invalid
     *
     * @return array<string, mixed>
     */
    private function loadSkeletonSubtree(): array
    {
        $roots = $this->resolveRoots();
        $skeletonRoot = $roots['skeletonRoot'];

        // Optional directory: absence is OK (empty overlay).
        if (!\is_dir($skeletonRoot)) {
            return [];
        }

        $file = $skeletonRoot . self::SKELETON_RELATIVE_FILE;

        // Optional file: absence is OK (empty overlay).
        if (!\is_file($file)) {
            return [];
        }

        // If present, it must be readable and valid.
        if (!\is_readable($file)) {
            throw CliConfigInvalidException::cliSubtreeInvalid();
        }

        return $this->loadCliSubtreeFromFile(
            $file,
            CliConfigInvalidException::REASON_CLI_SUBTREE_INVALID
        );
    }

    /**
     * Load a config file that MUST return the `cli` subtree (NO repeated root key).
     *
     * @return array<string, mixed>
     */
    private function loadCliSubtreeFromFile(string $file, string $reasonOnInvalid): array
    {
        if (!\is_file($file) || !\is_readable($file)) {
            throw new CliConfigInvalidException($reasonOnInvalid);
        }

        $raw = require $file;

        if (!\is_array($raw)) {
            throw new CliConfigInvalidException($reasonOnInvalid);
        }

        // MUST be a subtree: config/cli.php MUST NOT return ['cli' => ...].
        if (\array_key_exists('cli', $raw)) {
            throw new CliConfigInvalidException($reasonOnInvalid);
        }

        $this->assertCliSubtreeShapeIsSane($raw, $reasonOnInvalid);

        /** @var array<string, mixed> $raw */
        return $raw;
    }

    /**
     * Minimal, deterministic sanity checks (Phase 0).
     * We intentionally allow partial subtrees for overlays (preset/skeleton),
     * but if keys exist they MUST have the correct types.
     *
     * @param array $cli
     * @param string $reasonOnInvalid
     */
    private function assertCliSubtreeShapeIsSane(array $cli, string $reasonOnInvalid): void
    {
        if (\array_key_exists('commands', $cli)) {
            $commands = $cli['commands'];

            if (!\is_array($commands) || !\array_is_list($commands)) {
                throw new CliConfigInvalidException($reasonOnInvalid);
            }

            foreach ($commands as $v) {
                if (!\is_string($v) || $v === '') {
                    throw new CliConfigInvalidException($reasonOnInvalid);
                }
            }
        }

        if (\array_key_exists('output', $cli)) {
            if (!\is_array($cli['output'])) {
                throw new CliConfigInvalidException($reasonOnInvalid);
            }
        }
    }

    private function readRedactionEnabled(array $cli): bool
    {
        $enabled = $cli['output']['redaction']['enabled'] ?? true;

        return \is_bool($enabled) ? $enabled : true;
    }

    /**
     * Build command catalog from `cli.commands` list<FQCN>.
     *
     * Instantiation policy (Phase 0, single-choice):
     * - new $fqcn() only (zero-arg, public ctor)
     *
     * Built-in reservation:
     * - if instantiated command name is `help` or `list` => deterministic failure
     *
     * @return array<string, CommandInterface> map: command-name => instance
     */
    private function buildCommandCatalog(array $cli): array
    {
        $commands = $cli['commands'] ?? [];

        if (!\is_array($commands) || !\array_is_list($commands)) {
            throw CliConfigInvalidException::cliSubtreeInvalid();
        }

        /** @var list<string> $fqcnList */
        $fqcnList = $this->appendUniqueCommands([], $commands);

        $catalog = [];

        foreach ($fqcnList as $fqcn) {
            if (!\class_exists($fqcn)) {
                throw CliCommandClassMissingException::classNotFound();
            }

            if (!\is_subclass_of($fqcn, CommandInterface::class)) {
                throw CliCommandInvalidException::notACommand();
            }

            $command = $this->instantiateZeroArg($fqcn);

            $name = $command->name();

            // Built-in name reservation (cemented).
            if ($name === 'help' || $name === 'list') {
                throw CliCommandInvalidException::reservedCommandName();
            }

            // Deterministic resolution for duplicates: first wins.
            if (!\array_key_exists($name, $catalog)) {
                $catalog[$name] = $command;
            }
        }

        return $catalog;
    }

    /**
     * @param class-string<CommandInterface> $fqcn
     */
    private function instantiateZeroArg(string $fqcn): CommandInterface
    {
        try {
            $ref = new \ReflectionClass($fqcn);
        } catch (\ReflectionException $e) {
            // Deterministic: treat as "class not found" (autoload/reflect failed).
            throw CliCommandClassMissingException::classNotFound($e);
        }

        // Instantiable implies: not abstract, ctor accessible, etc.
        if (!$ref->isInstantiable()) {
            throw CliCommandInvalidException::nonPublicConstructor();
        }

        $ctor = $ref->getConstructor();
        if ($ctor !== null) {
            if (!$ctor->isPublic()) {
                throw CliCommandInvalidException::nonPublicConstructor();
            }

            if ($ctor->getNumberOfRequiredParameters() !== 0) {
                throw CliCommandInvalidException::nonZeroArgConstructor();
            }
        }

        try {
            /** @var CommandInterface $instance */
            $instance = $ref->newInstance();
            return $instance;
        } catch (\Throwable $e) {
            // Deterministic Phase 0 policy: ctor must be zero-arg and safe.
            throw CliCommandInvalidException::nonZeroArgConstructor($e);
        }
    }

    /**
     * Dispatch based on raw argv tokens (no parsing semantics).
     *
     * Rules (Phase 0):
     * - no args => built-in help
     * - configured commands are matched by longest token-prefix against CommandInterface::name()
     * - help/list remain built-ins when no configured command matched
     *
     * Cross-cutting enforcement (Errors):
     * - If a command returns exitCode != 0 but never emitted OutputInterface::error(code, reason),
     *   Application MUST print one deterministic fallback error-record:
     *     code: CORETSIA_CLI_COMMAND_FAILED
     *     reason: command-failed
     */
    private function dispatch(
        InputInterface  $input,
        OutputInterface $output,
        array           $catalog,
        HelpCommand     $help,
        ListCommand     $list,
    ): int {
        $tracked = new TrackedOutput($output);

        $tokens = $input->tokens();
        $first = $tokens[0] ?? '';

        if ($first === '') {
            $exitCode = (int)$help->run($input, $tracked);
            return $this->finalizeExitCode($tracked, $exitCode);
        }

        $matched = $this->resolveCatalogCommand($tokens, $catalog);
        if ($matched !== null) {
            $exitCode = (int)$matched->run($input, $tracked);
            return $this->finalizeExitCode($tracked, $exitCode);
        }

        if ($first === 'help') {
            $exitCode = (int)$help->run($input, $tracked);
            return $this->finalizeExitCode($tracked, $exitCode);
        }

        if ($first === 'list') {
            $exitCode = (int)$list->run($input, $tracked);
            return $this->finalizeExitCode($tracked, $exitCode);
        }

        $tracked->error(ErrorCodes::CORETSIA_CLI_COMMAND_INVALID, 'unknown-command');
        return 1;
    }

    /**
     * Longest-prefix command resolution for multi-token command names.
     *
     * Examples:
     * - command name: "workspace:sync --dry-run"
     * - input tokens: ["workspace:sync", "--dry-run", "--format=text"]
     *   => MUST resolve to that command
     *
     * Tie-breaker (deterministic):
     * - longest token-prefix wins
     * - if prefix lengths are equal, first command in catalog order wins
     *
     * @param list<string> $inputTokens
     * @param array<string, CommandInterface> $catalog
     */
    private function resolveCatalogCommand(array $inputTokens, array $catalog): ?CommandInterface
    {
        $best = null;
        $bestTokenCount = 0;

        foreach ($catalog as $name => $command) {
            if (!\is_string($name) || $name === '') {
                continue;
            }

            $signatureTokens = self::tokenizeCommandName($name);
            $signatureTokenCount = \count($signatureTokens);

            if ($signatureTokenCount === 0 || $signatureTokenCount <= $bestTokenCount) {
                continue;
            }

            if (!self::tokensStartWith($inputTokens, $signatureTokens)) {
                continue;
            }

            $best = $command;
            $bestTokenCount = $signatureTokenCount;
        }

        return $best;
    }

    /**
     * @return list<string>
     */
    private static function tokenizeCommandName(string $name): array
    {
        $name = \trim($name);
        if ($name === '') {
            return [];
        }

        $parts = \preg_split('/\s+/', $name);
        if (!\is_array($parts)) {
            return [];
        }

        $out = [];
        foreach ($parts as $part) {
            if (!\is_string($part) || $part === '') {
                continue;
            }

            $out[] = $part;
        }

        /** @var list<string> $out */
        return $out;
    }

    /**
     * @param list<string> $inputTokens
     * @param list<string> $signatureTokens
     */
    private static function tokensStartWith(array $inputTokens, array $signatureTokens): bool
    {
        $signatureCount = \count($signatureTokens);
        if ($signatureCount === 0 || $signatureCount > \count($inputTokens)) {
            return false;
        }

        for ($i = 0; $i < $signatureCount; $i++) {
            if (($inputTokens[$i] ?? null) !== $signatureTokens[$i]) {
                return false;
            }
        }

        return true;
    }

    private function finalizeExitCode(TrackedOutput $tracked, int $exitCode): int
    {
        if ($exitCode !== 0 && !$tracked->errorEmitted()) {
            $tracked->error(
                ErrorCodes::CORETSIA_CLI_COMMAND_FAILED,
                CliCommandFailedException::REASON_COMMAND_FAILED
            );
        }

        return $exitCode;
    }

    /**
     * Merge algorithm (cemented):
     * - cli.commands: append-unique, preserving first occurrence order
     * - all other keys:
     *   - associative arrays: recursive merge (higher precedence overrides lower precedence)
     *   - lists: replaced (no implicit list merge)
     *
     * @param array<string, mixed> $base
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function mergeCliSubtrees(array $base, array $overlay): array
    {
        foreach ($overlay as $k => $v) {
            if ($k === 'commands') {
                $left = $base['commands'] ?? [];
                if (!\is_array($left) || !\array_is_list($left)) {
                    $left = [];
                }

                if (!\is_array($v) || !\array_is_list($v)) {
                    throw CliConfigInvalidException::cliSubtreeInvalid();
                }

                $base['commands'] = $this->appendUniqueCommands($left, $v);
                continue;
            }

            if (!\array_key_exists($k, $base)) {
                $base[$k] = $v;
                continue;
            }

            $left = $base[$k];

            if (\is_array($left) && \is_array($v)) {
                // Lists are replaced (except cli.commands which is handled above).
                if (\array_is_list($left) || \array_is_list($v)) {
                    $base[$k] = $v;
                    continue;
                }

                // Associative map: recursive merge.
                /** @var array<string, mixed> $left */
                /** @var array<string, mixed> $v */
                $base[$k] = $this->mergeCliSubtrees($left, $v);
                continue;
            }

            // Scalar / type change: override.
            $base[$k] = $v;
        }

        return $base;
    }

    /**
     * Append-unique list<string>, preserving first occurrence order.
     *
     * @param array<int, mixed> $base
     * @param array<int, mixed> $extra
     * @return list<string>
     */
    private function appendUniqueCommands(array $base, array $extra): array
    {
        $out = [];
        $seen = [];

        foreach (\array_merge($base, $extra) as $v) {
            if (!\is_string($v) || $v === '') {
                throw CliConfigInvalidException::cliSubtreeInvalid();
            }

            if (isset($seen[$v])) {
                continue;
            }

            $seen[$v] = true;
            $out[] = $v;
        }

        return $out;
    }

    /**
     * @param array<string, CommandInterface> $catalog
     * @return list<string> configured command names in deterministic order
     */
    private function availableCommandNames(array $catalog): array
    {
        // Preserve insertion order from buildCommandCatalog() (derived from cli.commands order).
        $out = [];

        foreach ($catalog as $name => $_) {
            if (!\is_string($name) || $name === '') {
                continue;
            }

            $out[] = $name;
        }

        return $out;
    }

    private function isDevtoolsPresetInstalled(): bool
    {
        // Deterministic detection:
        // 1) if the package directory exists under the selected vendor dir, preset is considered installed
        //    and config/cli.php becomes mandatory
        // 2) otherwise, fall back to Composer\InstalledVersions when available
        $packageDir = $this->vendorDir() . '/coretsia/devtools-cli-spikes';
        if (\is_dir($packageDir)) {
            return true;
        }

        if (!\class_exists(\Composer\InstalledVersions::class)) {
            return false;
        }

        try {
            return \Composer\InstalledVersions::isInstalled(self::DEVTOOLS_PACKAGE);
        } catch (\Throwable) {
            // Deterministic safety: treat failure as "not installed".
            return false;
        }
    }

    private function vendorDir(): string
    {
        // Autoload file is selected by launcher and passed in; derive vendor dir without probing.
        return \dirname($this->autoloadFile);
    }

    /**
     * Root path resolution (single-choice, deterministic, no probing):
     * - launcherDir = dirname(framework/bin/coretsia)
     * - frameworkRoot = realpath(launcherDir . '/..')
     * - repoRoot = realpath(frameworkRoot . '/..')
     * - skeletonRoot = repoRoot . '/skeleton'  (MAY be absent; treated as empty overlay)
     *
     * @return array{launcherDir:string, frameworkRoot:string, repoRoot:string, skeletonRoot:string}
     */
    private function resolveRoots(): array
    {
        $launcherDir = \dirname($this->launcherFile);

        $frameworkRoot = \realpath($launcherDir . '/..');
        if ($frameworkRoot === false) {
            throw CliConfigInvalidException::layoutInvalid();
        }

        $repoRoot = \realpath($frameworkRoot . '/..');
        if ($repoRoot === false) {
            throw CliConfigInvalidException::layoutInvalid();
        }

        $skeletonRoot = $repoRoot . '/skeleton';

        return [
            'launcherDir' => $launcherDir,
            'frameworkRoot' => $frameworkRoot,
            'repoRoot' => $repoRoot,
            'skeletonRoot' => $skeletonRoot,
        ];
    }
}
