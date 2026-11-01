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

namespace Coretsia\Platform\Logging\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Stateless runtime wiring for the selected platform.logging module.
 *
 * Validated merged configuration is captured once at logger construction.
 * File-path syntax is checked here without filesystem access; directory
 * availability and daily file writes remain StreamLogger responsibilities.
 * Neither the application target nor UoW context is inferred or cached here.
 */
final class LoggingServiceFactory
{
    private function __construct()
    {
    }

    public static function createLogger(
        ConfigRepositoryInterface $config,
        ContextAccessorInterface $context,
        CorrelationIdProviderInterface $correlations,
        SensitiveDataRedactorInterface $redactor,
        SensitiveKeyClassifierInterface $keyClassifier,
        ClockInterface $clock,
        AppTargetProviderInterface $appTarget,
    ): LoggerInterface {
        // Even the null driver must pass the complete shared configuration
        // and file-map shape validation before choosing the NoopLogger.
        $driver = self::requiredConfigValue($config, 'logging.driver');
        $file = self::requiredConfigValue($config, 'logging.file');
        $filePath = self::requiredConfigValue($config, 'logging.file.path');
        $fileName = self::requiredConfigValue($config, 'logging.file.name');
        $level = self::requiredConfigValue($config, 'logging.level');
        $maxRecordBytes = self::requiredConfigValue($config, 'logging.max_record_bytes');
        $serviceName = self::requiredConfigValue($config, 'logging.service_name');
        $format = self::requiredConfigValue($config, 'logging.format');

        if (
            !\is_string($driver)
            || !\in_array($driver, ['stream', 'file', 'null'], true)
            || !\is_array($file)
            || \count($file) !== 2
            || !\array_key_exists('path', $file)
            || !\array_key_exists('name', $file)
            || $file['path'] !== $filePath
            || $file['name'] !== $fileName
            || !\is_string($filePath)
            || !\is_string($fileName)
            || !self::isServiceToken($fileName)
            || !\is_string($level)
            || !\in_array($level, [
                'debug',
                'info',
                'notice',
                'warning',
                'error',
                'critical',
                'alert',
                'emergency',
            ], true)
            || !\is_int($maxRecordBytes)
            || $maxRecordBytes < 1024
            || $maxRecordBytes > StreamLogger::MAX_RECORD_BYTES
            || !\is_string($serviceName)
            || !self::isServiceToken($serviceName)
            || !\is_string($format)
            || !\in_array($format, ['jsonl', 'logfmt'], true)
        ) {
            throw new \InvalidArgumentException('logging-config-invalid');
        }

        // The canonical target is owned by Kernel's existing ModulePlan.
        try {
            $selectedApp = $appTarget->app();
        } catch (\Throwable) {
            throw new \InvalidArgumentException('logging-app-target-invalid');
        }

        if (!\in_array($selectedApp, ['web', 'api', 'console', 'worker'], true)) {
            throw new \InvalidArgumentException('logging-app-target-invalid');
        }

        $effectiveServiceName = $serviceName === 'auto' ? $selectedApp : $serviceName;
        $fileDirectory = $driver === 'file' ? self::validateFileDirectory($filePath) : null;

        if ($driver === 'null') {
            return new NoopLogger();
        }

        $formatter = $format === 'jsonl' ? new JsonLineFormatter() : new LogfmtLineFormatter();
        $processor = new ContextBagProcessor($context, $correlations);
        $policy = new LogRecordPolicy($redactor, $keyClassifier);

        try {
            return new StreamLogger(
                policy: $policy,
                processor: $processor,
                formatter: $formatter,
                clock: $clock,
                minimumLevel: $level,
                maxRecordBytes: $maxRecordBytes,
                serviceName: $effectiveServiceName,
                appTarget: $selectedApp,
                driver: $driver,
                fileDirectory: $fileDirectory,
                fileName: $driver === 'file' ? $fileName : null,
            );
        } catch (\RuntimeException $exception) {
            // Only a fixed STDERR initialization failure permits Noop fallback.
            // Invalid configuration and all other construction errors propagate.
            if ($driver === 'stream' && $exception->getMessage() === 'logging-stderr-unavailable') {
                return new NoopLogger();
            }

            throw $exception;
        }
    }

    private static function requiredConfigValue(ConfigRepositoryInterface $config, string $key): mixed
    {
        try {
            $exists = $config->has($key);
            $value = $exists ? $config->get($key) : null;
        } catch (\Throwable) {
            throw new \InvalidArgumentException('logging-config-invalid');
        }

        if (!$exists) {
            throw new \InvalidArgumentException('logging-config-missing');
        }

        return $value;
    }

    private static function isServiceToken(string $value): bool
    {
        return \strlen($value) <= 64
            && \preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $value) === 1;
    }

    /**
     * Validates and normalizes an absolute local directory path without I/O.
     *
     * No existence, realpath, writability, symlink, or directory-creation
     * checks may occur during logger construction. The deployment owns the
     * directory and must prevent untrusted modifications to its contents.
     */
    private static function validateFileDirectory(string $path): string
    {
        if (
            $path === ''
            || \strlen($path) > 1024
            || \preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException('logging-file-path-invalid');
        }

        if (\PHP_OS_FAMILY === 'Windows') {
            // Only canonical drive-rooted local paths are supported: no UNC,
            // device namespaces, drive-relative paths, or alternate streams.
            if (\preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) !== 1) {
                throw new \InvalidArgumentException('logging-file-path-invalid');
            }

            $normalized = \str_replace('/', '\\', $path);
            $remainder = \substr($normalized, 3);

            if (
                \strpbrk($remainder, '<>:"|?*') !== false
                || \str_contains($remainder, '\\\\')
            ) {
                throw new \InvalidArgumentException('logging-file-path-invalid');
            }

            $segments = $remainder === '' ? [] : \explode('\\', \rtrim($remainder, '\\'));

            foreach ($segments as $segment) {
                if (
                    $segment === ''
                    || $segment === '.'
                    || $segment === '..'
                    || \str_ends_with($segment, '.')
                    || \str_ends_with($segment, ' ')
                ) {
                    throw new \InvalidArgumentException('logging-file-path-invalid');
                }
            }

            return \rtrim($normalized, '\\') === \substr($normalized, 0, 2)
                ? \substr($normalized, 0, 3)
                : \rtrim($normalized, '\\');
        }

        // POSIX: only one leading slash, no stream/URI notation, backslash,
        // empty components, or traversal. Root itself is a valid directory.
        if (
            !\str_starts_with($path, '/')
            || \str_starts_with($path, '//')
            || \str_contains($path, '\\')
            || \str_contains($path, ':')
            || \str_contains($path, '//')
        ) {
            throw new \InvalidArgumentException('logging-file-path-invalid');
        }

        $normalized = \rtrim($path, '/');

        foreach (\explode('/', \ltrim($normalized, '/')) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('logging-file-path-invalid');
            }
        }

        return $normalized === '' ? '/' : $normalized;
    }
}
