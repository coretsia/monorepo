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

namespace Coretsia\Kernel\Module;

use Coretsia\Contracts\Module\ManifestReaderInterface;
use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\Exception\ModuleManifestInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\Exception\ModuleResolutionException;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned ModulePlan resolution entrypoint.
 *
 * This class is the single orchestration brain for ModulePlan resolution.
 *
 * Single-choice module-selection inputs:
 *
 * - BootstrapConfig::preset();
 * - mode preset files loaded through FilesystemModePresetLoader:
 *   - skeleton override first;
 *   - framework default second;
 *   - first existing file wins;
 *   - no merge;
 * - Composer installed metadata through ManifestReaderInterface.
 *
 * BootstrapConfig::appTarget() is output metadata only for ModulePlan::app.
 * It must not introduce a parallel module-selection source.
 *
 * This resolver intentionally does not:
 *
 * - read skeleton/config/modules.php;
 * - read skeleton/apps/<app>/config/modules.php;
 * - scan skeleton/apps/<app>;
 * - infer the app target from filesystem;
 * - write artifacts;
 * - emit stdout/stderr;
 * - log raw paths or raw metadata.
 *
 * Preset schema validation is performed by the loader through
 * ModePresetSchemaValidator.
 *
 * Graph policy is delegated to ModuleGraphResolver.
 *
 * @internal Kernel provider-wired orchestration service. Public consumers
 * should rely on ModulePlan output and ModuleResolutionException boundaries.
 */
final readonly class ModulePlanResolver
{
    private const string DISCOVERY_SOURCE_COMPOSER = 'composer';
    private const string INVALID_SOURCE_PLACEHOLDER = 'invalid';
    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_UNEXPECTED_FAILURE = 'unexpected_failure';

    private string $discoverySource;

    /**
     * @var list<string>
     */
    private array $allowedDiscoverySources;

    /**
     * @param array<string, mixed> $modulesConfig The `kernel.modules` config subtree.
     */
    public function __construct(
        private ModePresetLoaderFactory $presetLoaderFactory,
        private ManifestReaderInterface $manifestReader,
        private ModuleGraphResolver $graphResolver,
        private MeterPortInterface $meter,
        private Stopwatch $stopwatch,
        private LoggerInterface $logger,
        array $modulesConfig,
    ) {
        $this->discoverySource = self::readDiscoverySource($modulesConfig);
        $this->allowedDiscoverySources = self::readAllowedDiscoverySources($modulesConfig);
    }

    public function resolve(BootstrapConfig $bootstrapConfig): ModulePlan
    {
        $startedAt = $this->safeStartTimer();

        try {
            /*
             * Must happen before preset loading and before Composer metadata
             * discovery.
             */
            $this->assertSupportedDiscoverySource();

            /*
             * Per-resolution loader. The factory resolves:
             *
             * - BootstrapConfig::skeletonRoot() + kernel.modes.overrides_path
             * - package root + kernel.modes.defaults_path
             *
             * The loader owns first-existing-file-wins behavior and must not
             * export resolved paths in diagnostics.
             */
            $presetLoader = $this->presetLoaderFactory->createFor($bootstrapConfig);
            $preset = $presetLoader->load($bootstrapConfig->preset());

            /*
             * Composer installed metadata discovery only.
             */
            $manifest = $this->manifestReader->read();

            /*
             * appTarget is output metadata only. It does not affect module
             * selection.
             */
            $plan = $this->graphResolver->resolve(
                app: $bootstrapConfig->appTarget()->value,
                installed: $manifest,
                preset: $preset,
            );

            $this->logOptionalMissingWarnings($plan);

            $this->emitResolutionSummaryForStartedAt(
                startedAt: $startedAt,
                outcome: self::OUTCOME_SUCCESS,
            );

            return $plan;
        } catch (ModuleResolutionException $exception) {
            $outcome = self::outcomeForException($exception);

            $this->logResolutionFailure($exception, $bootstrapConfig);

            $this->emitResolutionSummaryForStartedAt(
                startedAt: $startedAt,
                outcome: $outcome,
            );

            throw $exception;
        } catch (\Throwable $exception) {
            $this->emitResolutionSummaryForStartedAt(
                startedAt: $startedAt,
                outcome: self::OUTCOME_UNEXPECTED_FAILURE,
            );

            throw $exception;
        }
    }

    private function assertSupportedDiscoverySource(): void
    {
        if (
            $this->discoverySource !== self::DISCOVERY_SOURCE_COMPOSER
            || !\in_array($this->discoverySource, $this->allowedDiscoverySources, true)
        ) {
            throw ModuleDiscoverySourceUnsupportedException::forSource(
                source: $this->discoverySource,
                allowedSources: $this->allowedDiscoverySources,
            );
        }
    }

    private function safeStartTimer(): mixed
    {
        try {
            return $this->stopwatch->start();
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeStopTimer(mixed $startedAt): int
    {
        if (!\is_int($startedAt)) {
            return 0;
        }

        try {
            $durationMs = $this->stopwatch->stop($startedAt);

            return $durationMs >= 0 ? $durationMs : 0;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function emitResolutionSummaryForStartedAt(mixed $startedAt, string $outcome): void
    {
        $durationMs = $this->safeStopTimer($startedAt);

        $this->emitResolutionSummary($outcome, $durationMs);
    }

    /**
     * Emits safe Kernel ModulePlan resolution summary metrics.
     *
     * The payload is intentionally summary-only:
     *
     * - no module ids;
     * - no preset name;
     * - no app target;
     * - no resolved filesystem paths;
     * - no Composer metadata;
     * - no preset payload;
     * - no exception message;
     * - no stack trace.
     *
     * Observability port failures are swallowed so telemetry cannot change
     * ModulePlan resolution failure precedence.
     */
    private function emitResolutionSummary(string $outcome, int $durationMs): void
    {
        $labels = [
            'operation' => 'resolve',
            'outcome' => $outcome,
        ];

        try {
            $this->meter->increment('kernel.modules_resolve_total', 1, $labels);
            $this->meter->observe('kernel.modules_resolve_duration_ms', $durationMs, $labels);
        } catch (\Throwable) {
        }
    }

    private function logOptionalMissingWarnings(ModulePlan $plan): void
    {
        foreach ($plan->warnings() as $warning) {
            $payload = $warning->toArray();

            $context = [
                'code' => $payload['code'],
                'reason' => $payload['reason'],
                'presetName' => $payload['preset'],
                'moduleIds' => [
                    $payload['moduleId'],
                ],
            ];

            try {
                $this->logger->warning(
                    'coretsia.kernel.modules.optional_missing',
                    $context,
                );
            } catch (\Throwable) {
                /*
                 * Logging is optional and must not affect ModulePlan resolution.
                 */
            }
        }
    }

    private function logResolutionFailure(
        ModuleResolutionException $exception,
        BootstrapConfig $bootstrapConfig,
    ): void {
        $context = [
            'code' => $exception->errorCode(),
            'reason' => $exception->reason(),
            'presetName' => self::safePresetNameForLog($bootstrapConfig->preset()),
        ];

        $moduleIds = self::moduleIdsFromExceptionContext($exception->context());

        if ($moduleIds !== []) {
            $context['moduleIds'] = $moduleIds;
        }

        try {
            $this->logger->warning(
                'coretsia.kernel.modules.resolve_failed',
                $context,
            );
        } catch (\Throwable) {
            /*
             * Logging is optional and must not affect ModulePlan resolution.
             */
        }
    }

    private static function outcomeForException(ModuleResolutionException $exception): string
    {
        return match (true) {
            $exception instanceof ModuleDiscoverySourceUnsupportedException => 'discovery_source_unsupported',
            $exception instanceof ModePresetNotFoundException => 'preset_not_found',
            $exception instanceof ModePresetInvalidException => 'preset_invalid',
            $exception instanceof ModuleManifestInvalidException => 'manifest_invalid',
            $exception instanceof ModuleConflictException => 'conflict',
            $exception instanceof ModuleRequiredMissingException => 'required_missing',
            $exception instanceof ModuleCycleDetectedException => 'cycle',
            default => 'manifest_invalid',
        };
    }

    /**
     * @param array<string, mixed> $modulesConfig
     */
    private static function readDiscoverySource(array $modulesConfig): string
    {
        $discovery = $modulesConfig['discovery'] ?? null;

        if (!\is_array($discovery) || \array_is_list($discovery)) {
            return self::INVALID_SOURCE_PLACEHOLDER;
        }

        $source = $discovery['source'] ?? null;

        if (!\is_string($source) || !self::isSafeDiscoverySource($source)) {
            return self::INVALID_SOURCE_PLACEHOLDER;
        }

        return $source;
    }

    /**
     * @param array<string, mixed> $modulesConfig
     *
     * @return list<string>
     */
    private static function readAllowedDiscoverySources(array $modulesConfig): array
    {
        $discovery = $modulesConfig['discovery'] ?? null;

        if (!\is_array($discovery) || \array_is_list($discovery)) {
            return [];
        }

        $allowedSources = $discovery['allowed_sources'] ?? null;

        if (!\is_array($allowedSources) || !\array_is_list($allowedSources)) {
            return [];
        }

        $set = [];

        foreach ($allowedSources as $source) {
            if (!\is_string($source) || !self::isSafeDiscoverySource($source)) {
                continue;
            }

            $set[$source] = true;
        }

        $sources = \array_keys($set);

        \usort(
            $sources,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $sources;
    }

    private static function isSafeDiscoverySource(string $source): bool
    {
        if ($source === '') {
            return false;
        }

        if (\strlen($source) > 64) {
            return false;
        }

        if (!self::isAsciiLowerAlpha($source[0])) {
            return false;
        }

        return \strspn($source, 'abcdefghijklmnopqrstuvwxyz0123456789_-') === \strlen($source);
    }

    private static function safePresetNameForLog(string $preset): string
    {
        if ($preset === '') {
            return 'invalid';
        }

        if (\strlen($preset) > 64) {
            return 'invalid';
        }

        if (!self::isAsciiLowerAlpha($preset[0])) {
            return 'invalid';
        }

        if (\str_contains($preset, '..')) {
            return 'invalid';
        }

        if (\strspn($preset, 'abcdefghijklmnopqrstuvwxyz0123456789-') !== \strlen($preset)) {
            return 'invalid';
        }

        return $preset;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<string>
     */
    private static function moduleIdsFromExceptionContext(array $context): array
    {
        $moduleIds = [];

        foreach (
            [
                'moduleId',
                'firstModuleId',
                'secondModuleId',
                'lowerModuleId',
                'higherModuleId',
                'missingModuleId',
                'requiredByModuleId',
                'disabledModuleId',
            ] as $key
        ) {
            $value = $context[$key] ?? null;

            if (\is_string($value) && self::isValidModuleIdString($value)) {
                $moduleIds[$value] = true;
            }
        }

        $listValue = $context['moduleIds'] ?? null;

        if (\is_array($listValue) && \array_is_list($listValue)) {
            foreach ($listValue as $value) {
                if (\is_string($value) && self::isValidModuleIdString($value)) {
                    $moduleIds[$value] = true;
                }
            }
        }

        $out = \array_keys($moduleIds);

        \usort(
            $out,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return $out;
    }

    private static function isValidModuleIdString(string $value): bool
    {
        try {
            ModuleId::fromString($value);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    private static function isAsciiLowerAlpha(string $char): bool
    {
        return $char >= 'a' && $char <= 'z';
    }
}
