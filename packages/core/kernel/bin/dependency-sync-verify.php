#!/usr/bin/env php
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

use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectDependencySync;

$autoload = \dirname(__DIR__, 3) . '/autoload.php';

if (!\is_file($autoload)) {
    exit(1);
}

$protocolBufferBaseLevel = \ob_get_level();

if (!\ob_start()) {
    exit(1);
}

$discardProtocolBuffers = static function () use (
    $protocolBufferBaseLevel,
): bool {
    while (\ob_get_level() > $protocolBufferBaseLevel) {
        if (!@\ob_end_clean()) {
            return false;
        }
    }

    return \ob_get_level() === $protocolBufferBaseLevel;
};

$failExecution = static function () use (
    $discardProtocolBuffers,
): never {
    $discardProtocolBuffers();

    exit(1);
};

$emitProtocol = static function (
    array $payload,
    int $exitCode,
) use (
    $discardProtocolBuffers,
): never {
    if (!$discardProtocolBuffers()) {
        exit(1);
    }

    try {
        $json = \json_encode(
            $payload,
            \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES,
        );
    } catch (\Throwable) {
        exit(1);
    }

    if (\strlen($json) > 4096) {
        exit(1);
    }

    $written = @\fwrite(
        STDOUT,
        $json,
    );

    if (
        $written !== \strlen($json)
        || !@\fflush(STDOUT)
    ) {
        exit(1);
    }

    exit($exitCode);
};

try {
    require $autoload;

    $projectRoot = \getcwd();

    if (!\is_string($projectRoot)) {
        $failExecution();
    }

    $maxBytes = ProjectDependencySync::MAX_VERIFICATION_INPUT_BYTES;

    $verificationInput = \stream_get_contents(
        STDIN,
        $maxBytes + 1,
    );

    if (!\is_string($verificationInput)) {
        $failExecution();
    }

    if (\strlen($verificationInput) > $maxBytes) {
        $emitProtocol(
            [
                'schemaVersion' => 1,
                'status' => 'error',
                'code' => DependencySyncErrorCodes::VERIFICATION_INPUT_INVALID,
            ],
            2,
        );
    }

    ProjectDependencySync::create()->verifyInstalled(
        $projectRoot,
        $verificationInput,
    );

    $emitProtocol(
        [
            'schemaVersion' => 1,
            'status' => 'ok',
        ],
        0,
    );
} catch (DependencySyncException $exception) {
    if (!DependencySyncErrorCodes::has($exception->errorCode())) {
        $failExecution();
    }

    $emitProtocol(
        [
            'schemaVersion' => 1,
            'status' => 'error',
            'code' => $exception->errorCode(),
        ],
        2,
    );
} catch (\Throwable) {
    $failExecution();
}
