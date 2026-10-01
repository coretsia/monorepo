#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
 * Coretsia Skeleton
 *
 * Project: Coretsia Skeleton
 * Authors: Vladyslav Mudrichenko and contributors
 * Copyright (c) 2026 Vladyslav Mudrichenko
 *
 * SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
 * SPDX-License-Identifier: Apache-2.0
 *
 * For contributors list, see git history.
 * See LICENSE and NOTICE in the project root for full license information.
 */

use Coretsia\Kernel\Boot\AppTarget;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\DependencySync\DependencySyncExecutionPolicy;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectApplicationSet;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;
use Coretsia\Kernel\DependencySync\ProjectDependencySyncResult;
use Coretsia\Kernel\DependencySync\ProjectInstallationIntent;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;

$projectRoot = \dirname(__DIR__);
$autoload = $projectRoot
    . \DIRECTORY_SEPARATOR
    . 'vendor'
    . \DIRECTORY_SEPARATOR
    . 'autoload.php';

if (!\is_file($autoload) || \is_link($autoload)) {
    emitFailure(DependencySyncErrorCodes::BASELINE_NOT_INSTALLED);
}

require $autoload;

try {
    $request = parseRequest($argv);
    $targets = [];

    foreach ($request['targets'] as $targetName) {
        try {
            $targets[] = AppTarget::fromString($targetName);
        } catch (BootstrapException) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
        }
    }

    $intent = new ProjectInstallationIntent(
        new ProjectApplicationSet($targets),
        $request['presets'],
    );
    $sync = ProjectDependencySync::create();

    if ($request['operation'] === 'plan') {
        emitSuccess(
            'plan',
            planPayload(
                $sync->plan($projectRoot, $intent),
            ),
        );
    }

    $policy = new DependencySyncExecutionPolicy(
        apply: $request['operation'] === 'apply',
        allowComposerScripts: $request['allowComposerScripts'],
        allowComposerPlugins: $request['allowComposerPlugins'],
        allowBroadUpdate: $request['allowBroadUpdate'],
        allowRepair: $request['allowRepair'],
    );
    $result = $sync->synchronize(
        $projectRoot,
        $intent,
        $policy,
    );

    emitSuccess(
        $request['operation'],
        resultPayload($result, $policy),
    );
} catch (DependencySyncException $exception) {
    emitFailure(
        $exception->errorCode(),
        $exception->context(),
    );
} catch (\Throwable) {
    emitFailure(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
}

/**
 * @param list<string> $argv
 *
 * @return array{
 *     operation: 'plan'|'review'|'apply',
 *     targets: non-empty-list<non-empty-string>,
 *     presets: array<string, non-empty-string>,
 *     allowComposerScripts: bool,
 *     allowComposerPlugins: bool,
 *     allowBroadUpdate: bool,
 *     allowRepair: bool
 * }
 */
function parseRequest(array $argv): array
{
    $operation = $argv[1] ?? null;

    if (!\in_array($operation, ['plan', 'review', 'apply'], true)) {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
    }

    $targets = [];
    $targetSet = [];
    $presets = [];
    $flags = [
        '--allow-composer-scripts' => false,
        '--allow-composer-plugins' => false,
        '--allow-broad-update' => false,
        '--allow-repair' => false,
    ];

    foreach (\array_slice($argv, 2) as $argument) {
        if (\str_starts_with($argument, '--target=')) {
            $target = \substr($argument, \strlen('--target='));

            if (
                !isSafeToken($target)
                || isset($targetSet[$target])
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
            }

            $targetSet[$target] = true;
            $targets[] = $target;
            continue;
        }

        if (\str_starts_with($argument, '--preset=')) {
            $value = \substr($argument, \strlen('--preset='));
            $separator = \strpos($value, '=');

            if ($separator === false) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
            }

            $target = \substr($value, 0, $separator);
            $preset = \substr($value, $separator + 1);

            if (
                !isSafeToken($target)
                || !isSafeToken($preset)
                || isset($presets[$target])
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
            }

            $presets[$target] = $preset;
            continue;
        }

        if (\array_key_exists($argument, $flags)) {
            if ($flags[$argument]) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
            }

            $flags[$argument] = true;
            continue;
        }

        throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
    }

    if ($targets === []) {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::APPLICATION_SET_INVALID);
    }

    foreach (\array_keys($presets) as $target) {
        if (!isset($targetSet[$target])) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
        }
    }

    \sort($targets, \SORT_STRING);
    \ksort($presets, \SORT_STRING);

    if ($operation !== 'apply' && \in_array(true, $flags, true)) {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::INSTALLATION_INTENT_INVALID);
    }

    /** @var non-empty-list<non-empty-string> $targets */
    return [
        'operation' => $operation,
        'targets' => $targets,
        'presets' => $presets,
        'allowComposerScripts' => $flags['--allow-composer-scripts'],
        'allowComposerPlugins' => $flags['--allow-composer-plugins'],
        'allowBroadUpdate' => $flags['--allow-broad-update'],
        'allowRepair' => $flags['--allow-repair'],
    ];
}

/** @return array<string, mixed> */
function planPayload(ProjectPackagePlan $plan): array
{
    return [
        'applications' => $plan->applications(),
        'effectivePresetsByTarget' => $plan->effectivePresetsByTarget(),
        'fixedPresetByTarget' => $plan->fixedPresetByTarget(),
        'enabledModuleIdsByTarget' => $plan->enabledModuleIdsByTarget(),
        'excludedModuleIdsByTarget' => $plan->excludedModuleIdsByTarget(),
        'unionModuleIds' => $plan->unionModuleIds(),
        'desiredRootRequirements' => $plan->desiredRootRequirements(),
    ];
}

/** @return array<string, mixed> */
function resultPayload(
    ProjectDependencySyncResult $result,
    DependencySyncExecutionPolicy $policy,
): array {
    return [
        'changed' => $result->changed(),
        'managedRequireWrites' => $result->managedRequireWrites(),
        'managedRequireRemovals' => $result->managedRequireRemovals(),
        'preservedSatisfiers' => $result->preservedSatisfiers(),
        'composerUpdateScope' => [
            'constrainedTargetPolicy' => 'all-coretsia-packages-in-reconciled-root-require',
            'withDependencies' => true,
            'broadUpdateAuthorized' => $policy->allowBroadUpdate(),
        ],
        'plan' => planPayload($result->plan()),
    ];
}

function isSafeToken(string $value): bool
{
    return $value !== ''
        && \trim($value) === $value
        && \preg_match('/[\x00-\x1F\x7F]/', $value) === 0;
}

/** @param array<string, mixed> $payload */
function emitSuccess(string $operation, array $payload): never
{
    writeOutput([
        'schemaVersion' => 1,
        'status' => 'ok',
        'operation' => $operation,
        'result' => $payload,
    ]);

    exit(0);
}

/** @param array<string, non-empty-string> $context */
function emitFailure(
    string $code,
    array $context = [],
): never {
    writeOutput([
        'schemaVersion' => 1,
        'status' => 'error',
        'code' => $code,
        'context' => $context,
    ]);

    exit(1);
}

/** @param array<string, mixed> $payload */
function writeOutput(array $payload): void
{
    try {
        $json = \json_encode(
            $payload,
            \JSON_THROW_ON_ERROR
            | \JSON_UNESCAPED_SLASHES
            | \JSON_UNESCAPED_UNICODE,
        );
    } catch (\JsonException) {
        $json = '{"schemaVersion":1,"status":"error","code":"INSTALLATION_INTENT_INVALID","context":{}}';
    }

    \fwrite(\STDOUT, $json . "\n");
}
