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

namespace Coretsia\Platform\Errors\Provider;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Observability\Errors\ErrorReporterPortInterface;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Platform\Errors\ErrorHandler;
use Coretsia\Platform\Errors\Mapper\DefaultExceptionMapper;
use Coretsia\Platform\Errors\Mapper\ExceptionMapperRegistry;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Stateless construction of canonical Errors runtime services.
 *
 * Reads the already-merged reporting configuration during service construction
 * and passes immutable policy values to the registry and handler. The Kernel
 * ConfigValidator remains the owner of declarative configuration validation.
 * No configuration or runtime service state is retained by this factory.
 */
final class ErrorsServiceFactory
{
    public static function exceptionMapperRegistry(
        TagRegistry $tags,
        ContainerInterface $container,
        LoggerInterface $logger,
        ConfigRepositoryInterface $config,
    ): ExceptionMapperRegistry {
        $policy = self::reportingPolicy($config);

        return new ExceptionMapperRegistry(
            tags: $tags,
            container: $container,
            logger: $logger,
            reportingEnabled: $policy['enabled'],
            mapperFailureLoggingEnabled: $policy['mapperFailuresEnabled'],
        );
    }

    public static function errorHandler(
        ExceptionMapperRegistry $registry,
        DefaultExceptionMapper $fallback,
        SensitiveDataRedactorInterface $redactor,
        ErrorReporterPortInterface $reporter,
        CorrelationIdProviderInterface $correlations,
        TracerPortInterface $tracer,
        MeterPortInterface $meter,
        Stopwatch $stopwatch,
        ConfigRepositoryInterface $config,
    ): ErrorHandler {
        $policy = self::reportingPolicy($config);

        return new ErrorHandler(
            registry: $registry,
            fallback: $fallback,
            redactor: $redactor,
            reporter: $reporter,
            correlations: $correlations,
            tracer: $tracer,
            meter: $meter,
            stopwatch: $stopwatch,
            reportingEnabled: $policy['enabled'],
            minSeverity: $policy['minSeverity'],
            excludeCodes: $policy['excludeCodes'],
            sampleRate: $policy['sampleRate'],
        );
    }

    /**
     * Enforces required runtime types even when services are constructed
     * outside the normal Kernel configuration-validation pipeline.
     *
     * @return array{
     *     enabled: bool,
     *     minSeverity: ErrorSeverity,
     *     excludeCodes: list<non-empty-string>,
     *     sampleRate: int,
     *     mapperFailuresEnabled: bool
     * }
     */
    private static function reportingPolicy(ConfigRepositoryInterface $config): array
    {
        try {
            $enabled = self::requiredConfigValue($config, 'errors.reporting.enabled');
            $minSeverity = self::requiredConfigValue($config, 'errors.reporting.min_severity');
            $excludeCodes = self::requiredConfigValue($config, 'errors.reporting.exclude_codes');
            $sampleRate = self::requiredConfigValue($config, 'errors.reporting.sample_rate');
            $mapperFailuresEnabled = self::requiredConfigValue($config, 'errors.reporting.mapper_failures.enabled');

            if (
                !is_bool($enabled)
                || !is_string($minSeverity)
                || !is_array($excludeCodes)
                || !array_is_list($excludeCodes)
                || !is_int($sampleRate)
                || $sampleRate < 0
                || $sampleRate > 10000
                || !is_bool($mapperFailuresEnabled)
            ) {
                throw new \InvalidArgumentException('Invalid errors reporting configuration.');
            }

            $severity = ErrorSeverity::tryFrom($minSeverity);

            if ($severity === null) {
                throw new \InvalidArgumentException('Invalid errors reporting configuration.');
            }

            foreach ($excludeCodes as $code) {
                if (
                    !is_string($code)
                    || $code === ''
                    || preg_match('/\s/u', $code) === 1
                ) {
                    throw new \InvalidArgumentException('Invalid errors reporting configuration.');
                }
            }

            /** @var list<non-empty-string> $excludeCodes */
            return [
                'enabled' => $enabled,
                'minSeverity' => $severity,
                'excludeCodes' => $excludeCodes,
                'sampleRate' => $sampleRate,
                'mapperFailuresEnabled' => $mapperFailuresEnabled,
            ];
        } catch (Throwable) {
            // Never expose malformed reporting values or config-source failures.
            throw new \InvalidArgumentException('Invalid errors reporting configuration.');
        }
    }

    private static function requiredConfigValue(
        ConfigRepositoryInterface $config,
        string $key,
    ): mixed {
        if (!$config->has($key)) {
            throw new \InvalidArgumentException('Invalid errors reporting configuration.');
        }

        return $config->get($key);
    }
}
