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

namespace Coretsia\Kernel\Config;

use Coretsia\Contracts\Config\ConfigDirective;
use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Boot\BootstrapConfig;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;
use Coretsia\Kernel\Config\Explain\ConfigExplainer;
use Coretsia\Kernel\Config\Loaders\EnvironmentOverlayLoader;
use Coretsia\Kernel\Config\Loaders\PackageDefaultsConfigLoader;
use Coretsia\Kernel\Config\Loaders\SkeletonConfigLoader;
use Coretsia\Kernel\Module\ModulePlan;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned Phase B config orchestration service.
 *
 * ConfigKernel is intentionally an orchestration boundary only. It does not
 * discover package paths, parse config files directly, validate rules itself,
 * implement merge semantics, implement directive normalization, or implement
 * explain formatting.
 *
 * Responsibilities:
 *
 * - load rulesets through ConfigRulesLoader;
 * - load package defaults through PackageDefaultsConfigLoader;
 * - load skeleton/app config through SkeletonConfigLoader;
 * - build env overlays through EnvironmentOverlayLoader;
 * - fold normalized config entries through ConfigMerger in explicit Phase B
 *   precedence order;
 * - build effective per-path source traces in lockstep with value merge;
 * - validate only after final global config is built;
 * - optionally build a safe explain trace when requested by the caller;
 * - own ConfigKernel-level observability boundaries.
 *
 * This class MUST NOT:
 *
 * - read $_ENV, $_SERVER, or getenv();
 * - read skeleton/config/app.php;
 * - scan package directories;
 * - scan skeleton/app config directories;
 * - infer package filesystem paths from ModulePlanEntry;
 * - expose absolute filesystem paths;
 * - expose raw env values;
 * - put merge/explain observability inside loaders, ConfigMerger,
 *   DirectiveProcessor, ConfigValidator, or ConfigExplainer.
 *
 * Config explain is a baseline kernel facility. It is not feature-disabled via
 * runtime config. Whether explain is produced is decided by the caller through
 * the `explain` argument of compile().
 *
 * @internal
 */
final readonly class ConfigKernel
{
    private const string SPAN_CONFIG_MERGE = 'kernel.config_merge';
    private const string SPAN_CONFIG_EXPLAIN = 'kernel.config_explain';

    private const string METRIC_CONFIG_MERGE_TOTAL = 'kernel.config_merge_total';
    private const string METRIC_CONFIG_MERGE_DURATION_MS = 'kernel.config_merge_duration_ms';
    private const string METRIC_CONFIG_EXPLAIN_TOTAL = 'kernel.config_explain_total';
    private const string METRIC_CONFIG_EXPLAIN_DURATION_MS = 'kernel.config_explain_duration_ms';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    private const int PRECEDENCE_PACKAGE_DEFAULT = 10;
    private const int PRECEDENCE_ENV_OVERLAY = 500;

    private const string SENSITIVE_LOG_KEY_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key|dsn|sql|raw|payload|stacktrace|trace|email|phone|username|fullname|userid|tenantid)(?![A-Za-z0-9])/i';
    private const string SQL_LIKE_LOG_KEY_PATH_PATTERN = '/(?<![A-Za-z0-9])(?:select|insert|update|delete|drop|alter|create|truncate|union|where|from|join)(?![A-Za-z0-9])/i';

    private const string ENTRY_KIND_PACKAGE_DEFAULT = 'package_default';
    private const string ENTRY_KIND_ENV_OVERLAY = 'env_overlay';

    /**
     * @param list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }> $defaultExplicitEnvOverlayMappings
     */
    public function __construct(
        private ConfigMerger $merger,
        private ConfigRulesLoader $rulesLoader,
        private ConfigValidator $validator,
        private ConfigExplainer $explainer,
        private PackageDefaultsConfigLoader $packageDefaultsLoader,
        private SkeletonConfigLoader $skeletonLoader,
        private EnvironmentOverlayLoader $environmentOverlayLoader,
        private MeterPortInterface $meter,
        private TracerPortInterface $tracer,
        private Stopwatch $stopwatch,
        private LoggerInterface $logger,
        private array $defaultExplicitEnvOverlayMappings = [],
    ) {
        self::assertExplicitEnvOverlayMappings($this->defaultExplicitEnvOverlayMappings);
    }

    /**
     * Runs the full Phase B config pipeline.
     *
     * Source candidate arrays are explicit inputs supplied by the Kernel
     * config-location source builder. ConfigKernel does not infer package paths
     * and does not scan directories.
     *
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageDefaultSources
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $packageRuleSources
     * @param list<non-empty-string> $splitRoots
     * @param list<array{
     *     root: string,
     *     packageId: string,
     *     moduleId?: string|null,
     *     path: string,
     *     filesystemPath: string,
     *     sourceId?: string|null,
     *     precedence?: int
     * }> $explicitRuleSources
     * @param list<array{
     *     path: string,
     *     env: string,
     *     type: string,
     *     sourceId?: string|null,
     *     precedence?: int|null,
     *     allowedValues?: list<null|bool|int|string>
     * }> $explicitEnvOverlayMappings
     *
     * @return array{
     *     config: array<string,mixed>,
     *     explain: array<string,mixed>|null,
     *     owners: array<string, array<string, null|bool|int|string>>,
     *     sources: list<ConfigValueSource>,
     *     envOverlayMappings: list<array{
     *         env: non-empty-string,
     *         kind: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     configSourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>,
     *     validation: ConfigValidationResult,
     *     validationSubjects: array{
     *         unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *         validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     *     }
     * }
     *
     * @throws ConfigInvalidException
     */
    public function compile(
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        EnvRepositoryInterface $env,
        array $packageDefaultSources,
        array $packageRuleSources,
        array $splitRoots = [],
        array $explicitRuleSources = [],
        array $explicitEnvOverlayMappings = [],
        bool $explain = false,
    ): array {
        $startedAt = $this->safeStartTimer();
        $outcome = self::OUTCOME_SUCCESS;
        $mergeLogContext = [];

        try {
            $result = $this->withSpan(
                name: self::SPAN_CONFIG_MERGE,
                attributes: [],
                callback: function (?SpanInterface $span) use (
                    $bootstrapConfig,
                    $modulePlan,
                    $env,
                    $packageDefaultSources,
                    $packageRuleSources,
                    $splitRoots,
                    $explicitRuleSources,
                    $explicitEnvOverlayMappings
                ): array {
                    $compiled = $this->compileWithoutExplain(
                        bootstrapConfig: $bootstrapConfig,
                        modulePlan: $modulePlan,
                        env: $env,
                        packageDefaultSources: $packageDefaultSources,
                        packageRuleSources: $packageRuleSources,
                        splitRoots: $splitRoots,
                        explicitRuleSources: $explicitRuleSources,
                        explicitEnvOverlayMappings: $explicitEnvOverlayMappings,
                    );

                    return $compiled;
                },
            );

            if ($result['validation']->isFailure()) {
                $outcome = self::OUTCOME_FAILURE;
            }

            $mergeLogContext = self::sourceLogContext(
                sources: $result['effectiveSources'],
                extra: [
                    'env_overlay_mapping_count' => \count($result['envOverlayMappings']),
                    'ruleset_count' => \count($result['rulesets']),
                    'source_entry_count' => \count($result['effectiveSources']),
                    'unvalidated_root_count' => \count($result['validationSubjects']['unvalidated']),
                    'validated_root_count' => \count($result['validationSubjects']['validated']),
                ],
            );

            $explainOutput = null;

            $effectiveSources = \array_values($result['effectiveSources']);

            if ($explain) {
                $explainOutput = $this->explainCompiled(
                    config: $result['config'],
                    sources: $effectiveSources,
                    validationSubjects: $result['validationSubjects'],
                    validationResult: $result['validation'],
                    envOverlayMappings: $result['envOverlayMappings'],
                    owners: $result['owners'],
                );
            }

            return [
                'config' => $result['config'],
                'explain' => $explainOutput,
                'owners' => $result['owners'],
                'sources' => $effectiveSources,
                'envOverlayMappings' => $result['envOverlayMappings'],
                'configSourceFiles' => $result['configSourceFiles'],
                'validation' => $result['validation'],
                'validationSubjects' => $result['validationSubjects'],
            ];
        } catch (\Throwable $exception) {
            $outcome = self::OUTCOME_FAILURE;

            throw $exception;
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->emitMergeMetrics($outcome, $durationMs);
            $this->logLifecycleEvent(
                event: 'config.merge',
                outcome: $outcome,
                durationMs: $durationMs,
                context: $mergeLogContext,
            );
        }
    }

    /**
     * Produces a safe explain trace for already compiled config data.
     *
     * This method is public so callers can decide at the entrypoint whether to
     * produce explain without toggling a runtime config feature flag.
     *
     * @param array<string,mixed> $config
     * @param list<ConfigValueSource> $sources
     * @param array{
     *     unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *     validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     * } $validationSubjects
     * @param list<array{
     *     env?: string,
     *     kind?: string,
     *     path?: string,
     *     root?: string,
     *     sourceId?: string,
     *     type?: string
     * }> $envOverlayMappings
     * @param array<string, array<string, null|bool|int|string>> $owners
     *
     * @return array<string,mixed>
     */
    public function explainCompiled(
        array $config,
        array $sources,
        array $validationSubjects,
        ConfigValidationResult $validationResult,
        array $envOverlayMappings = [],
        array $owners = [],
    ): array {
        $startedAt = $this->safeStartTimer();
        $outcome = self::OUTCOME_SUCCESS;

        try {
            return $this->withSpan(
                name: self::SPAN_CONFIG_EXPLAIN,
                attributes: [],
                callback: fn (): array => $this->explainer->explain(
                    config: $config,
                    sources: self::sourceListToMap($sources),
                    validationSubjects: $validationSubjects,
                    validationResult: $validationResult,
                    envOverlayMappings: $envOverlayMappings,
                    owners: $owners,
                ),
            );
        } catch (\Throwable $exception) {
            $outcome = self::OUTCOME_FAILURE;

            throw $exception;
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->emitExplainMetrics($outcome, $durationMs);
            $this->logLifecycleEvent(
                event: 'config.explain',
                outcome: $outcome,
                durationMs: $durationMs,
                context: self::sourceLogContext(
                    sources: $sources,
                    extra: [
                        'env_overlay_mapping_count' => \count($envOverlayMappings),
                        'source_entry_count' => \count($sources),
                        'unvalidated_root_count' => \count($validationSubjects['unvalidated'] ?? []),
                        'validated_root_count' => \count($validationSubjects['validated'] ?? []),
                    ],
                ),
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $packageDefaultSources
     * @param list<array<string,mixed>> $packageRuleSources
     * @param list<non-empty-string> $splitRoots
     * @param list<array<string,mixed>> $explicitRuleSources
     * @param list<array<string,mixed>> $explicitEnvOverlayMappings
     *
     * @return array{
     *     config: array<string,mixed>,
     *     effectiveSources: array<string, ConfigValueSource>,
     *     envOverlayMappings: list<array{
     *         env: non-empty-string,
     *         kind: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     configSourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>,
     *     mergeEntries: list<array<string,mixed>>,
     *     owners: array<string, array<string, null|bool|int|string>>,
     *     rulesets: list<ConfigRuleset>,
     *     validation: ConfigValidationResult,
     *     validationSubjects: array{
     *         unvalidated: list<array{root: non-empty-string, ownership: string, validation: string}>,
     *         validated: list<array{root: non-empty-string, ownership: string, validation: string}>
     *     }
     * }
     *
     * @throws ConfigInvalidException
     */
    private function compileWithoutExplain(
        BootstrapConfig $bootstrapConfig,
        ModulePlan $modulePlan,
        EnvRepositoryInterface $env,
        array $packageDefaultSources,
        array $packageRuleSources,
        array $splitRoots,
        array $explicitRuleSources,
        array $explicitEnvOverlayMappings,
    ): array {
        self::assertSourceCandidateList($packageDefaultSources);
        self::assertSourceCandidateList($packageRuleSources);
        self::assertSourceCandidateList($explicitRuleSources);
        self::assertExplicitEnvOverlayMappings($explicitEnvOverlayMappings);

        $packageRules = $this->rulesLoader->loadPackageRulesets(
            modulePlan: $modulePlan,
            sources: $packageRuleSources,
        );

        $explicitRules = $explicitRuleSources === []
            ? [
                'owners' => [],
                'rulesets' => [],
                'sources' => [],
            ]
            : $this->rulesLoader->loadRulesets($explicitRuleSources);

        $rulesets = self::mergeRulesets(
            $packageRules['rulesets'],
            $explicitRules['rulesets'],
        );

        $packageDefaults = $this->packageDefaultsLoader->load(
            modulePlan: $modulePlan,
            sources: $packageDefaultSources,
        );

        $skeletonConfig = $this->skeletonLoader->load(
            bootstrapConfig: $bootstrapConfig,
            splitRoots: $splitRoots,
        );

        $envMappings = self::mergeExplicitEnvOverlayMappings(
            $this->defaultExplicitEnvOverlayMappings,
            $explicitEnvOverlayMappings,
        );

        $envOverlay = $this->environmentOverlayLoader->load(
            env: $env,
            rulesets: $rulesets,
            explicitMappings: $envMappings,
            precedence: self::PRECEDENCE_ENV_OVERLAY,
        );

        $owners = self::collectOwnerMetadata(
            $packageRules['owners'],
            $explicitRules['owners'],
            $packageDefaults['owners'],
            $skeletonConfig['owners'],
        );

        $mergeEntries = self::buildMergeEntries(
            packageDefaults: $packageDefaults,
            skeletonConfig: $skeletonConfig,
            envOverlay: $envOverlay,
        );

        $merged = $this->foldMergeEntries($mergeEntries);

        $validation = $this->validator->validate(
            config: $merged['config'],
            rulesets: $rulesets,
        );

        $validationSubjects = $this->validator->validationSubjects(
            config: $merged['config'],
            rulesets: $rulesets,
        );

        return [
            'config' => $merged['config'],
            'effectiveSources' => $merged['sources'],
            'envOverlayMappings' => $envOverlay['mappings'],
            'configSourceFiles' => $skeletonConfig['sourceFiles'],
            'mergeEntries' => $mergeEntries,
            'owners' => $owners,
            'rulesets' => $rulesets,
            'validation' => $validation,
            'validationSubjects' => $validationSubjects,
        ];
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

    /**
     * @param list<array<string,mixed>> $mergeEntries
     *
     * @return array{
     *     config: array<string,mixed>,
     *     sources: array<string, ConfigValueSource>
     * }
     */
    private function foldMergeEntries(array $mergeEntries): array
    {
        $config = [];
        $sources = [];

        foreach ($mergeEntries as $entryOrder => $entry) {
            $before = $config;

            /** @var array<string,mixed> $patch */
            $patch = $entry['config'];

            $defaultSource = self::sourceWithTraceMetadata(
                source: $entry['source'],
                sourceOrder: $entryOrder,
                sourceKind: $entry['kind'],
            );

            $pathSources = self::pathSourcesWithTraceMetadata(
                pathSources: $entry['pathSources'],
                sourceOrder: $entryOrder,
                sourceKind: $entry['kind'],
            );

            $config = $this->merger->merge($config, $patch);

            /*
             * Source tracing is built in lockstep with value folding, but value
             * semantics stay owned by ConfigMerger. This trace update does not
             * compute final config values.
             */
            self::tracePatchMap(
                traces: $sources,
                before: $before,
                after: $config,
                patch: $patch,
                defaultSource: $defaultSource,
                pathSources: $pathSources,
            );
        }

        \ksort($config, \SORT_STRING);
        self::sortSourceMap($sources);

        return [
            'config' => $config,
            'sources' => $sources,
        ];
    }

    private static function sourceWithTraceMetadata(
        ConfigValueSource $source,
        int $sourceOrder,
        string $sourceKind,
    ): ConfigValueSource {
        $meta = $source->meta();

        $meta['sourceOrder'] = $sourceOrder;

        if (!isset($meta['kind']) && self::isSafeSmallToken($sourceKind)) {
            $meta['kind'] = $sourceKind;
        }

        return new ConfigValueSource(
            type: $source->type(),
            root: $source->root(),
            sourceId: $source->sourceId(),
            path: $source->path(),
            keyPath: $source->keyPath(),
            directive: $source->directive(),
            precedence: $source->precedence(),
            redacted: $source->isRedacted(),
            meta: $meta,
        );
    }

    /**
     * @param array<string, ConfigValueSource> $pathSources
     *
     * @return array<string, ConfigValueSource>
     */
    private static function pathSourcesWithTraceMetadata(
        array $pathSources,
        int $sourceOrder,
        string $sourceKind,
    ): array {
        $normalized = [];

        foreach ($pathSources as $path => $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $normalized[$path] = self::sourceWithTraceMetadata(
                source: $source,
                sourceOrder: $sourceOrder,
                sourceKind: $sourceKind,
            );
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    /**
     * @param array<string, ConfigValueSource> $sources
     */
    private static function sortSourceMap(array &$sources): void
    {
        \uasort(
            $sources,
            static function (ConfigValueSource $a, ConfigValueSource $b): int {
                return ($a->precedence() <=> $b->precedence())
                    ?: \strcmp(self::sourceTracePath($a), self::sourceTracePath($b))
                        ?: \strcmp($a->sourceId(), $b->sourceId())
                            ?: \strcmp($a->type()->value, $b->type()->value)
                                ?: \strcmp($a->directive() ?? '', $b->directive() ?? '');
            },
        );
    }

    private static function sourceTracePath(ConfigValueSource $source): string
    {
        return $source->keyPath()
            ?? $source->path()
            ?? $source->root();
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     * @param array<string,mixed> $before
     * @param array<string,mixed> $after
     * @param array<string,mixed> $patch
     * @param array<string, ConfigValueSource> $pathSources
     */
    private static function tracePatchMap(
        array &$traces,
        array $before,
        array $after,
        array $patch,
        ConfigValueSource $defaultSource,
        array $pathSources,
    ): void {
        $keys = \array_keys($patch);

        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key) || !self::isValidRoot($key)) {
                continue;
            }

            self::tracePatchNode(
                traces: $traces,
                path: $key,
                before: \array_key_exists($key, $before) ? $before[$key] : null,
                after: \array_key_exists($key, $after) ? $after[$key] : null,
                patch: $patch[$key],
                defaultSource: self::sourceForPath(
                    path: $key,
                    defaultSource: $defaultSource,
                    pathSources: $pathSources,
                ),
                pathSources: $pathSources,
            );
        }
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     * @param array<string, ConfigValueSource> $pathSources
     */
    private static function tracePatchNode(
        array &$traces,
        string $path,
        mixed $before,
        mixed $after,
        mixed $patch,
        ConfigValueSource $defaultSource,
        array $pathSources,
    ): void {
        $source = self::sourceForPath(
            path: $path,
            defaultSource: $defaultSource,
            pathSources: $pathSources,
        );

        if (!\is_array($patch)) {
            self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: self::sourceWithPath($source, $path),
            );

            return;
        }

        $directive = self::directiveOf($patch);

        if ($directive !== null) {
            self::traceDirectiveNode(
                traces: $traces,
                path: $path,
                before: $before,
                after: $after,
                directive: $directive,
                directiveValue: $patch[$directive->key()],
                source: $source,
                pathSources: $pathSources,
            );

            return;
        }

        if (\array_is_list($patch)) {
            self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: self::sourceWithPath($source, $path),
            );

            return;
        }

        if (!self::isMapLike($before)) {
            self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: self::sourceWithPath($source, $path),
            );

            return;
        }

        $traces[$path] ??= self::sourceWithPath($source, $path);

        $keys = \array_keys($patch);

        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $childPath = $path . '.' . self::safePathSegment($key);

            self::tracePatchNode(
                traces: $traces,
                path: $childPath,
                before: \is_array($before) && \array_key_exists($key, $before) ? $before[$key] : null,
                after: \is_array($after) && \array_key_exists($key, $after) ? $after[$key] : null,
                patch: $patch[$key],
                defaultSource: $source,
                pathSources: $pathSources,
            );
        }
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     * @param array<string, ConfigValueSource> $pathSources
     */
    private static function traceDirectiveNode(
        array &$traces,
        string $path,
        mixed $before,
        mixed $after,
        ConfigDirective $directive,
        mixed $directiveValue,
        ConfigValueSource $source,
        array $pathSources,
    ): void {
        $directiveSource = self::sourceWithPath(
            source: $source,
            path: $path,
            directive: $directive->value,
        );

        match ($directive) {
            ConfigDirective::Merge => self::traceMergeDirective(
                traces: $traces,
                path: $path,
                before: $before,
                after: $after,
                directiveValue: $directiveValue,
                source: $directiveSource,
                pathSources: $pathSources,
            ),

            ConfigDirective::Replace => self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: $directiveSource,
            ),

            ConfigDirective::Append,
            ConfigDirective::Prepend,
            ConfigDirective::Remove => self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: $directiveSource,
            ),
        };
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     * @param array<string, ConfigValueSource> $pathSources
     */
    private static function traceMergeDirective(
        array &$traces,
        string $path,
        mixed $before,
        mixed $after,
        mixed $directiveValue,
        ConfigValueSource $source,
        array $pathSources,
    ): void {
        if (!\is_array($directiveValue) || \array_is_list($directiveValue)) {
            $traces[$path] ??= $source;

            return;
        }

        if (!self::isMapLike($before)) {
            self::markSubtree(
                traces: $traces,
                path: $path,
                value: $after,
                source: $source,
            );

            return;
        }

        $traces[$path] ??= $source;

        $keys = \array_keys($directiveValue);

        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            $childPath = $path . '.' . self::safePathSegment($key);

            self::tracePatchNode(
                traces: $traces,
                path: $childPath,
                before: \is_array($before) && \array_key_exists($key, $before) ? $before[$key] : null,
                after: \is_array($after) && \array_key_exists($key, $after) ? $after[$key] : null,
                patch: $directiveValue[$key],
                defaultSource: $source,
                pathSources: $pathSources,
            );
        }
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     */
    private static function markSubtree(
        array &$traces,
        string $path,
        mixed $value,
        ConfigValueSource $source,
    ): void {
        self::removeTraceSubtree($traces, $path);
        self::collectTraceSubtree($traces, $path, $value, $source);
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     */
    private static function removeTraceSubtree(array &$traces, string $path): void
    {
        foreach (\array_keys($traces) as $tracePath) {
            if (
                $tracePath === $path
                || \str_starts_with($tracePath, $path . '.')
                || \str_starts_with($tracePath, $path . '[')
            ) {
                unset($traces[$tracePath]);
            }
        }
    }

    /**
     * @param array<string, ConfigValueSource> $traces
     */
    private static function collectTraceSubtree(
        array &$traces,
        string $path,
        mixed $value,
        ConfigValueSource $source,
    ): void {
        $traces[$path] = self::sourceWithPath($source, $path);

        if (!\is_array($value)) {
            return;
        }

        if (\array_is_list($value)) {
            foreach ($value as $index => $item) {
                self::collectTraceSubtree(
                    traces: $traces,
                    path: $path . '[' . $index . ']',
                    value: $item,
                    source: $source,
                );
            }

            return;
        }

        $keys = \array_keys($value);

        \usort(
            $keys,
            static fn (int|string $a, int|string $b): int => \strcmp((string)$a, (string)$b),
        );

        foreach ($keys as $key) {
            if (!\is_string($key)) {
                continue;
            }

            self::collectTraceSubtree(
                traces: $traces,
                path: $path . '.' . self::safePathSegment($key),
                value: $value[$key],
                source: $source,
            );
        }
    }

    /**
     * @param array<string, ConfigValueSource> $pathSources
     */
    private static function sourceForPath(
        string $path,
        ConfigValueSource $defaultSource,
        array $pathSources,
    ): ConfigValueSource {
        $best = null;

        foreach ($pathSources as $sourcePath => $source) {
            if (
                $path === $sourcePath
                || \str_starts_with($path, $sourcePath . '.')
                || \str_starts_with($path, $sourcePath . '[')
            ) {
                if ($best === null || \strlen($sourcePath) > \strlen($best['path'])) {
                    $best = [
                        'path' => $sourcePath,
                        'source' => $source,
                    ];
                }
            }
        }

        return $best['source'] ?? $defaultSource;
    }

    private static function sourceWithPath(
        ConfigValueSource $source,
        string $path,
        ?string $directive = null,
    ): ConfigValueSource {
        return new ConfigValueSource(
            type: $source->type(),
            root: self::rootFromPath($path),
            sourceId: $source->sourceId(),
            path: $source->path(),
            keyPath: $path,
            directive: $directive ?? $source->directive(),
            precedence: $source->precedence(),
            redacted: $source->isRedacted(),
            meta: $source->meta(),
        );
    }

    /**
     * @param array{
     *     config: array<string,mixed>,
     *     sources: array<string, ConfigValueSource>,
     *     owners: array<string, array<string,mixed>>
     * } $packageDefaults
     * @param array{
     *     entries: list<array{
     *         config: array<string,mixed>,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         path: non-empty-string,
     *         precedence: int,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>,
     *     sources: list<ConfigValueSource>,
     *     owners: list<array<string,mixed>>,
     *     sourceFiles: list<array{
     *         exists: bool,
     *         hash?: non-empty-string,
     *         kind: non-empty-string,
     *         layer: non-empty-string,
     *         len?: int,
     *         path: non-empty-string,
     *         readable: bool,
     *         root?: non-empty-string,
     *         sourceId: non-empty-string
     *     }>
     * } $skeletonConfig
     * @param array{
     *     config: array<string,mixed>,
     *     sources: array<string, ConfigValueSource>,
     *     mappings: list<array{
     *         env: non-empty-string,
     *         kind: non-empty-string,
     *         path: non-empty-string,
     *         root: non-empty-string,
     *         sourceId: non-empty-string,
     *         type: non-empty-string
     *     }>
     * } $envOverlay
     *
     * @return list<array{
     *     config: array<string,mixed>,
     *     kind: non-empty-string,
     *     order: int,
     *     pathSources: array<string, ConfigValueSource>,
     *     precedence: int,
     *     source: ConfigValueSource,
     *     sourceId: non-empty-string
     * }>
     *
     * @throws ConfigInvalidException
     */
    private static function buildMergeEntries(
        array $packageDefaults,
        array $skeletonConfig,
        array $envOverlay,
    ): array {
        $entries = [];
        $order = 0;

        foreach ($packageDefaults['config'] as $root => $subtree) {
            if (!\is_string($root) || !self::isValidRoot($root)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $source = $packageDefaults['sources'][$root] ?? null;

            if (!$source instanceof ConfigValueSource) {
                $source = new ConfigValueSource(
                    type: ConfigSourceType::PackageDefault,
                    root: $root,
                    sourceId: 'package-default/' . $root,
                    path: null,
                    keyPath: $root,
                    directive: null,
                    precedence: self::PRECEDENCE_PACKAGE_DEFAULT,
                    redacted: false,
                    meta: [
                        'kind' => self::ENTRY_KIND_PACKAGE_DEFAULT,
                    ],
                );
            }

            $source = self::sourceWithPrecedence(
                source: $source,
                precedence: \max(self::PRECEDENCE_PACKAGE_DEFAULT, $source->precedence()),
            );

            $entries[] = [
                'config' => [
                    $root => $subtree,
                ],
                'kind' => self::ENTRY_KIND_PACKAGE_DEFAULT,
                'order' => $order++,
                'pathSources' => [
                    $root => self::sourceWithPath($source, $root),
                ],
                'precedence' => $source->precedence(),
                'source' => $source,
                'sourceId' => $source->sourceId(),
            ];
        }

        $skeletonSourcesByEntry = self::skeletonSourcesByEntry($skeletonConfig['sources']);

        foreach ($skeletonConfig['entries'] as $entry) {
            $type = self::sourceTypeFromString($entry['type']);
            $sourceId = $entry['sourceId'];
            $pathSources = $skeletonSourcesByEntry[$sourceId] ?? [];

            $root = self::firstRoot($entry['config']);

            $source = new ConfigValueSource(
                type: $type,
                root: $root,
                sourceId: $sourceId,
                path: $entry['path'],
                keyPath: $root,
                directive: null,
                precedence: $entry['precedence'],
                redacted: false,
                meta: [
                    'kind' => $entry['kind'],
                    'layer' => $entry['layer'],
                ],
            );

            if ($pathSources === []) {
                foreach (\array_keys($entry['config']) as $entryRoot) {
                    if (\is_string($entryRoot) && self::isValidRoot($entryRoot)) {
                        $pathSources[$entryRoot] = self::sourceWithPath($source, $entryRoot);
                    }
                }
            }

            $entries[] = [
                'config' => $entry['config'],
                'kind' => $entry['kind'],
                'order' => $order++,
                'pathSources' => $pathSources,
                'precedence' => $entry['precedence'],
                'source' => $source,
                'sourceId' => $sourceId,
            ];
        }

        if ($envOverlay['config'] !== []) {
            $source = new ConfigValueSource(
                type: ConfigSourceType::Env,
                root: self::firstRoot($envOverlay['config']),
                sourceId: 'env-overlay',
                path: null,
                keyPath: null,
                directive: null,
                precedence: self::PRECEDENCE_ENV_OVERLAY,
                redacted: true,
                meta: [
                    'kind' => self::ENTRY_KIND_ENV_OVERLAY,
                ],
            );

            $entries[] = [
                'config' => $envOverlay['config'],
                'kind' => self::ENTRY_KIND_ENV_OVERLAY,
                'order' => $order++,
                'pathSources' => $envOverlay['sources'],
                'precedence' => self::PRECEDENCE_ENV_OVERLAY,
                'source' => $source,
                'sourceId' => $source->sourceId(),
            ];
        }

        \usort(
            $entries,
            static function (array $a, array $b): int {
                return ($a['precedence'] <=> $b['precedence'])
                    ?: ($a['order'] <=> $b['order'])
                        ?: \strcmp($a['sourceId'], $b['sourceId'])
                            ?: \strcmp($a['kind'], $b['kind']);
            },
        );

        foreach ($entries as $index => $entry) {
            $entries[$index]['order'] = $index;
        }

        return $entries;
    }

    /**
     * @param list<ConfigValueSource> $sources
     *
     * @return array<string, array<string, ConfigValueSource>>
     */
    private static function skeletonSourcesByEntry(array $sources): array
    {
        $byEntry = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $parts = \explode('/', $source->sourceId());

            if (\count($parts) < 2) {
                continue;
            }

            $root = \array_pop($parts);
            $entrySourceId = \implode('/', $parts);

            if (!\is_string($root) || !self::isValidRoot($root)) {
                continue;
            }

            $byEntry[$entrySourceId][$root] = self::sourceWithPath($source, $root);
        }

        \ksort($byEntry, \SORT_STRING);

        foreach ($byEntry as $entrySourceId => $entrySources) {
            \ksort($entrySources, \SORT_STRING);
            $byEntry[$entrySourceId] = $entrySources;
        }

        return $byEntry;
    }

    /**
     * @param list<ConfigRuleset> $packageRulesets
     * @param list<ConfigRuleset> $explicitRulesets
     *
     * @return list<ConfigRuleset>
     *
     * @throws ConfigInvalidException
     */
    private static function mergeRulesets(array $packageRulesets, array $explicitRulesets): array
    {
        $byRoot = [];

        foreach ([$packageRulesets, $explicitRulesets] as $rulesets) {
            if (!\array_is_list($rulesets)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            foreach ($rulesets as $ruleset) {
                if (!$ruleset instanceof ConfigRuleset) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_SOURCE_INVALID,
                    );
                }

                $root = $ruleset->root();

                if (isset($byRoot[$root])) {
                    throw ConfigInvalidException::withReason(
                        ConfigInvalidException::REASON_RULESET_INVALID,
                    );
                }

                $byRoot[$root] = $ruleset;
            }
        }

        \ksort($byRoot, \SORT_STRING);

        return \array_values($byRoot);
    }

    /**
     * @param list<array<string,mixed>> $defaultMappings
     * @param list<array<string,mixed>> $runtimeMappings
     *
     * @return list<array<string,mixed>>
     *
     * @throws ConfigInvalidException
     */
    private static function mergeExplicitEnvOverlayMappings(
        array $defaultMappings,
        array $runtimeMappings,
    ): array {
        self::assertExplicitEnvOverlayMappings($defaultMappings);
        self::assertExplicitEnvOverlayMappings($runtimeMappings);

        $merged = [];

        foreach ([...$defaultMappings, ...$runtimeMappings] as $mapping) {
            $path = $mapping['path'] ?? null;

            if (!\is_string($path) || $path === '') {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            if (isset($merged[$path])) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }

            $merged[$path] = $mapping;
        }

        \ksort($merged, \SORT_STRING);

        return \array_values($merged);
    }

    /**
     * @param array<string|int, mixed> ...$ownerGroups
     *
     * @return array<string, array<string, null|bool|int|string>>
     */
    private static function collectOwnerMetadata(array ...$ownerGroups): array
    {
        $owners = [];

        foreach ($ownerGroups as $ownerGroup) {
            foreach ($ownerGroup as $owner) {
                if (!\is_array($owner)) {
                    continue;
                }

                $normalized = self::normalizeOwnerMetadata($owner);

                if ($normalized === []) {
                    continue;
                }

                $sourceId = $normalized['sourceId'] ?? null;

                if (!\is_string($sourceId) || $sourceId === '') {
                    continue;
                }

                $owners[$sourceId] = $normalized;
            }
        }

        \ksort($owners, \SORT_STRING);

        return $owners;
    }

    /**
     * @param array<string, mixed> $owner
     *
     * @return array<string, null|bool|int|string>
     */
    private static function normalizeOwnerMetadata(array $owner): array
    {
        $allowedKeys = [
            'appEnv' => true,
            'appTarget' => true,
            'kind' => true,
            'layer' => true,
            'moduleId' => true,
            'packageId' => true,
            'path' => true,
            'root' => true,
            'sourceId' => true,
            'type' => true,
        ];

        $normalized = [];

        foreach ($owner as $key => $value) {
            if (!\is_string($key) || !isset($allowedKeys[$key])) {
                continue;
            }

            if ($value === null || \is_bool($value) || \is_int($value)) {
                $normalized[$key] = $value;

                continue;
            }

            if (!\is_string($value) || !self::isSafeMetadataString($value)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        \ksort($normalized, \SORT_STRING);

        return $normalized;
    }

    private static function isSafeMetadataString(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        if (
            \str_contains($value, "\0")
            || \str_contains($value, "\r")
            || \str_contains($value, "\n")
            || \str_contains($value, '://')
            || \preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1
            || \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
        ) {
            return false;
        }

        return true;
    }

    /**
     * @template T
     *
     * @param non-empty-string $name
     * @param array<string,mixed> $attributes
     * @param callable(?SpanInterface):T $callback
     *
     * @return T
     */
    private function withSpan(
        string $name,
        array $attributes,
        callable $callback,
    ): mixed {
        $span = $this->startKnownSpan(
            name: $name,
            attributes: $attributes,
        );

        try {
            return $callback($span);
        } catch (\Throwable $exception) {
            /*
             * Do not record exception objects here.
             *
             * Tracer adapters may serialize exception messages, stack traces,
             * previous throwable chains, file paths, or other unsafe details.
             * ConfigKernel failure metrics/logs use bounded outcome metadata only.
             */
            throw $exception;
        } finally {
            if ($span !== null) {
                try {
                    $span->end();
                } catch (\Throwable) {
                    /*
                     * Observability must not change config pipeline success.
                     */
                }
            }
        }
    }

    /**
     * Starts only ConfigKernel-owned canonical spans.
     *
     * The direct TracerPortInterface::startSpan(...) calls intentionally pass
     * private string constants as the first argument so observability span naming
     * gates can statically resolve and validate span names.
     *
     * @param non-empty-string $name
     * @param array<string,mixed> $attributes
     */
    private function startKnownSpan(
        string $name,
        array $attributes,
    ): ?SpanInterface {
        $safeAttributes = self::safeSpanAttributes($attributes);

        try {
            return match ($name) {
                self::SPAN_CONFIG_MERGE => $this->tracer->startSpan(
                    self::SPAN_CONFIG_MERGE,
                    $safeAttributes,
                ),
                self::SPAN_CONFIG_EXPLAIN => $this->tracer->startSpan(
                    self::SPAN_CONFIG_EXPLAIN,
                    $safeAttributes,
                ),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function emitMergeMetrics(string $outcome, int $durationMs): void
    {
        $labels = [
            'outcome' => self::safeOutcome($outcome),
        ];

        try {
            $this->meter->increment(
                self::METRIC_CONFIG_MERGE_TOTAL,
                1,
                $labels,
            );

            $this->meter->observe(
                self::METRIC_CONFIG_MERGE_DURATION_MS,
                $durationMs,
                $labels,
            );
        } catch (\Throwable) {
            /*
             * Observability must not change config pipeline behavior.
             */
        }
    }

    private function emitExplainMetrics(string $outcome, int $durationMs): void
    {
        $labels = [
            'outcome' => self::safeOutcome($outcome),
        ];

        try {
            $this->meter->increment(
                self::METRIC_CONFIG_EXPLAIN_TOTAL,
                1,
                $labels,
            );

            $this->meter->observe(
                self::METRIC_CONFIG_EXPLAIN_DURATION_MS,
                $durationMs,
                $labels,
            );
        } catch (\Throwable) {
            /*
             * Observability must not change config pipeline behavior.
             */
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    private function logLifecycleEvent(
        string $event,
        string $outcome,
        int $durationMs,
        array $context = [],
    ): void {
        $safeContext = [
            'duration_ms' => $durationMs,
            'event' => $event,
            'outcome' => self::safeOutcome($outcome),
        ];

        foreach (self::sanitizeLogContext($context) as $key => $value) {
            $safeContext[$key] = $value;
        }

        \ksort($safeContext, \SORT_STRING);

        try {
            $this->logger->info('kernel-config-lifecycle', $safeContext);
        } catch (\Throwable) {
            /*
             * Logging must not change config pipeline behavior.
             */
        }
    }

    /**
     * @param list<ConfigValueSource>|array<string, ConfigValueSource> $sources
     * @param array<string,mixed> $extra
     *
     * @return array<string,mixed>
     */
    private static function sourceLogContext(
        array $sources,
        array $extra = [],
    ): array {
        $context = $extra;

        $sourcePaths = [];
        $keyPaths = [];
        $directiveNames = [];
        $sourceTypes = [];
        $sourceKinds = [];
        $sourceLayers = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $path = $source->path();

            if ($path !== null && self::isSafeLogMetadataString($path)) {
                $sourcePaths[$path] = true;
            }

            $keyPath = $source->keyPath();

            if ($keyPath !== null && self::isSafeLogKeyPath($keyPath)) {
                $keyPaths[$keyPath] = true;
            }

            $directive = $source->directive();

            if ($directive !== null && self::isSafeLogMetadataString($directive)) {
                $directiveNames[$directive] = true;
            }

            $sourceTypes[$source->type()->value] = true;

            $meta = $source->meta();

            $kind = $meta['kind'] ?? null;

            if (\is_string($kind) && self::isSafeLogMetadataString($kind)) {
                $sourceKinds[$kind] = true;
            }

            $layer = $meta['layer'] ?? null;

            if (\is_string($layer) && self::isSafeLogMetadataString($layer)) {
                $sourceLayers[$layer] = true;
            }
        }

        $context['source_paths'] = self::sortedBoundedKeys($sourcePaths);
        $context['key_paths'] = self::sortedBoundedKeys($keyPaths);
        $context['directive_names'] = self::sortedBoundedKeys($directiveNames);
        $context['source_types'] = self::sortedBoundedKeys($sourceTypes);
        $context['source_kinds'] = self::sortedBoundedKeys($sourceKinds);
        $context['source_layers'] = self::sortedBoundedKeys($sourceLayers);

        return $context;
    }

    /**
     * @param array<string,true> $values
     *
     * @return list<string>
     */
    private static function sortedBoundedKeys(
        array $values,
        int $limit = 20,
    ): array {
        $keys = \array_keys($values);

        \usort(
            $keys,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        return \array_slice($keys, 0, $limit);
    }

    /**
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    private static function sanitizeLogContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (!self::isSafeLogKey($key)) {
                continue;
            }

            if (\is_int($value) && $value >= 0) {
                $safe[$key] = $value;

                continue;
            }

            if (\is_string($value) && self::isSafeLogMetadataString($value)) {
                $safe[$key] = $value;

                continue;
            }

            if (\is_array($value) && \array_is_list($value)) {
                $list = [];

                foreach ($value as $item) {
                    if (!\is_string($item) || !self::isSafeLogMetadataString($item)) {
                        continue;
                    }

                    $list[$item] = true;
                }

                $safe[$key] = self::sortedBoundedKeys($list);
            }
        }

        \ksort($safe, \SORT_STRING);

        return $safe;
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return array<string,mixed>
     */
    private static function safeSpanAttributes(array $attributes): array
    {
        $safe = [];

        foreach ($attributes as $key => $value) {
            if (!self::isSafeSpanAttributeKey($key)) {
                continue;
            }

            if (!\is_int($value) && !\is_bool($value) && !\is_string($value)) {
                continue;
            }

            if (\is_string($value) && !self::isSafeSmallToken($value)) {
                continue;
            }

            $safe[$key] = $value;
        }

        \ksort($safe, \SORT_STRING);

        return $safe;
    }

    private static function sourceWithPrecedence(
        ConfigValueSource $source,
        int $precedence,
    ): ConfigValueSource {
        return new ConfigValueSource(
            type: $source->type(),
            root: $source->root(),
            sourceId: $source->sourceId(),
            path: $source->path(),
            keyPath: $source->keyPath(),
            directive: $source->directive(),
            precedence: $precedence,
            redacted: $source->isRedacted(),
            meta: $source->meta(),
        );
    }

    /**
     * @param list<ConfigValueSource> $sources
     *
     * @return array<string, ConfigValueSource>
     */
    private static function sourceListToMap(array $sources): array
    {
        $map = [];

        foreach ($sources as $source) {
            if (!$source instanceof ConfigValueSource) {
                continue;
            }

            $key = \str_pad((string)$source->precedence(), 10, '0', \STR_PAD_LEFT)
                . "\0" . self::sourceTracePath($source)
                . "\0" . $source->sourceId()
                . "\0" . $source->type()->value
                . "\0" . ($source->directive() ?? '');

            $map[$key] = $source;
        }

        \ksort($map, \SORT_STRING);

        return $map;
    }

    /**
     * @param array<string,mixed> $config
     *
     * @return non-empty-string
     *
     * @throws ConfigInvalidException
     */
    private static function firstRoot(array $config): string
    {
        $roots = [];

        foreach (\array_keys($config) as $root) {
            if (\is_string($root) && self::isValidRoot($root)) {
                $roots[] = $root;
            }
        }

        \usort(
            $roots,
            static fn (string $a, string $b): int => \strcmp($a, $b),
        );

        if ($roots === []) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $roots[0];
    }

    /**
     * @throws ConfigInvalidException
     */
    private static function sourceTypeFromString(string $type): ConfigSourceType
    {
        $sourceType = ConfigSourceType::tryFrom($type);

        if ($sourceType === null) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        return $sourceType;
    }

    private static function directiveOf(array $node): ?ConfigDirective
    {
        if (\array_is_list($node)) {
            return null;
        }

        $directive = null;
        $directiveCount = 0;
        $normalKeyCount = 0;

        foreach ($node as $key => $_value) {
            if (!\is_string($key) || !ConfigDirective::isReservedDirectiveKey($key)) {
                $normalKeyCount++;

                continue;
            }

            $resolved = ConfigDirective::tryFromKey($key);

            if ($resolved === null) {
                return null;
            }

            $directive = $resolved;
            $directiveCount++;
        }

        if ($directiveCount !== 1 || $normalKeyCount !== 0) {
            return null;
        }

        return $directive;
    }

    private static function isMapLike(mixed $value): bool
    {
        return \is_array($value) && ($value === [] || !\array_is_list($value));
    }

    /**
     * @return non-empty-string
     */
    private static function rootFromPath(string $path): string
    {
        $root = \strtok($path, '.[');

        if (!\is_string($root) || !self::isValidRoot($root)) {
            return 'unknown';
        }

        return $root;
    }

    private static function safePathSegment(string $key): string
    {
        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*\z/', $key) === 1) {
            return $key;
        }

        return '<key>';
    }

    private static function isValidRoot(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
    }

    private static function safeOutcome(string $outcome): string
    {
        return $outcome === self::OUTCOME_SUCCESS
            ? self::OUTCOME_SUCCESS
            : self::OUTCOME_FAILURE;
    }

    private static function isSafeSpanAttributeKey(string $key): bool
    {
        return \in_array(
            $key,
            [
                'env_overlay_mapping_count',
                'ruleset_count',
                'source_entry_count',
                'unvalidated_root_count',
                'validated_root_count',
            ],
            true,
        );
    }

    private static function isSafeLogKey(string $key): bool
    {
        return \in_array(
            $key,
            [
                'directive_names',
                'env_overlay_mapping_count',
                'key_paths',
                'ruleset_count',
                'source_entry_count',
                'source_kinds',
                'source_layers',
                'source_paths',
                'source_types',
                'unvalidated_root_count',
                'validated_root_count',
            ],
            true,
        );
    }

    private static function isSafeLogMetadataString(string $value): bool
    {
        if ($value === '' || \strlen($value) > 160) {
            return false;
        }

        if (
            \str_contains($value, "\0")
            || \str_contains($value, "\r")
            || \str_contains($value, "\n")
            || \str_contains($value, '://')
            || \preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1
            || \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
        ) {
            return false;
        }

        return \preg_match('/\A[A-Za-z0-9_@.<>\[\]\/-]+\z/', $value) === 1;
    }

    private static function isSafeLogKeyPath(string $value): bool
    {
        if (!self::isSafeLogMetadataString($value)) {
            return false;
        }

        if (\preg_match(self::SENSITIVE_LOG_KEY_PATH_PATTERN, $value) === 1) {
            return false;
        }

        if (\preg_match(self::SQL_LIKE_LOG_KEY_PATH_PATTERN, $value) === 1) {
            return false;
        }

        return true;
    }

    private static function isSafeSmallToken(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= 64
            && \preg_match('/\A[a-zA-Z0-9_.-]+\z/', $value) === 1;
    }

    /**
     * @param list<array<string,mixed>> $sources
     *
     * @throws ConfigInvalidException
     */
    private static function assertSourceCandidateList(array $sources): void
    {
        if (!\array_is_list($sources)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }
    }

    /**
     * @param list<array<string,mixed>> $mappings
     *
     * @throws ConfigInvalidException
     */
    private static function assertExplicitEnvOverlayMappings(array $mappings): void
    {
        if (!\array_is_list($mappings)) {
            throw ConfigInvalidException::withReason(
                ConfigInvalidException::REASON_SOURCE_INVALID,
            );
        }

        foreach ($mappings as $mapping) {
            if (!\is_array($mapping)) {
                throw ConfigInvalidException::withReason(
                    ConfigInvalidException::REASON_SOURCE_INVALID,
                );
            }
        }
    }
}
