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

namespace Coretsia\Kernel\Container;

use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;
use Coretsia\Contracts\Observability\Tracing\SpanInterface;
use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;
use Coretsia\Foundation\Time\Stopwatch;
use Coretsia\Kernel\Container\Definition\DefinitionGraph;
use Coretsia\Kernel\Container\Definition\ServiceDefinition;
use Coretsia\Kernel\Container\Exception\ContainerCompileFailedException;
use Psr\Log\LoggerInterface;

/**
 * Kernel-owned deterministic compiled-container graph compiler.
 *
 * This compiler consumes explicit descriptor-based, closure-free container
 * input and produces a deterministic DefinitionGraph suitable for
 * `container@1` compiled payload emission.
 *
 * Input order is caller-owned and semantically significant.
 *
 * The compiler MUST preserve the caller-supplied deterministic provider/module
 * order exactly as represented by the descriptor stream. It MUST NOT globally
 * sort providers, modules, or descriptors before applying binding-collision
 * semantics.
 *
 * Binding collision semantics intentionally match Foundation ContainerBuilder:
 *
 * - later service binding overrides earlier service binding for the same id;
 * - later alias binding overrides earlier alias binding for the same alias;
 * - later parameter binding overrides earlier parameter binding for the same
 *   parameter name;
 * - tag dedupe remains first-wins per `(tag, serviceId)`.
 *
 * This compiler intentionally does not:
 *
 * - read source config files;
 * - read generated artifacts;
 * - write artifacts;
 * - calculate fingerprints;
 * - resolve BootstrapConfig;
 * - resolve ModulePlan;
 * - run provider-based runtime boot;
 * - instantiate runtime services;
 * - emit stdout/stderr;
 * - instantiate Noop observability implementations.
 *
 * Observability dependencies are injected through public ports/interfaces.
 * Logger/meter/tracer/stopwatch failures are swallowed and MUST NOT change
 * compile behavior.
 *
 * Supported descriptor kinds:
 *
 * - service.class
 * - service.factory.class-method
 * - service.factory.service-method
 * - alias
 * - parameter
 * - parameters
 * - tag
 *
 * @internal
 */
final readonly class ContainerCompiler
{
    private const string DESCRIPTOR_SERVICE_CLASS = 'service.class';
    private const string DESCRIPTOR_SERVICE_FACTORY_CLASS_METHOD = 'service.factory.class-method';
    private const string DESCRIPTOR_SERVICE_FACTORY_SERVICE_METHOD = 'service.factory.service-method';
    private const string DESCRIPTOR_ALIAS = 'alias';
    private const string DESCRIPTOR_PARAMETER = 'parameter';
    private const string DESCRIPTOR_PARAMETERS = 'parameters';
    private const string DESCRIPTOR_TAG = 'tag';

    private const string SPAN_CONTAINER_COMPILE = 'kernel.container_compile';

    private const string METRIC_CONTAINER_COMPILE_TOTAL = 'kernel.container_compile_total';
    private const string METRIC_CONTAINER_COMPILE_DURATION_MS = 'kernel.container_compile_duration_ms';

    private const string LOG_EVENT_CONTAINER_COMPILE = 'kernel.container.compile';

    private const string OUTCOME_SUCCESS = 'success';
    private const string OUTCOME_FAILURE = 'failure';

    private const int MAX_DESCRIPTORS = 100_000;
    private const int MAX_DESCRIPTOR_DEPTH = 24;
    private const int MAX_MAP_KEYS = 512;
    private const int MAX_LIST_ITEMS = 4096;
    private const int MAX_STRING_BYTES = 1024;
    private const int MAX_SAFE_COUNT = 1_000_000_000;

    private const string MAP_KEY_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_.-]{0,127}\z/';
    private const string METHOD_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]{0,127}\z/';
    private const string CLASS_LIKE_PATTERN = '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/';

    private const string CONTROL_CHARACTER_PATTERN = '/[\x00-\x1F\x7F]/';
    private const string SOURCE_SNIPPET_PATTERN = '/<\?php|<\?=|\bfunction\s*\(|\bfn\s*\(|=>\s*\{|;\s*}/i';
    private const string ENV_LIKE_PATTERN = '/\$\{[A-Z_][A-Z0-9_]*}|%env\(|\benv\s*\(/i';
    private const string SENSITIVE_VALUE_PATTERN = '/(?<![A-Za-z0-9])(?:authorization|bearer|cookie|session|token|secret|password|passwd|credential|api[_-]?key|access[_-]?key|private[_-]?key)(?![A-Za-z0-9])/i';

    public function __construct(
        private TracerPortInterface $tracer,
        private MeterPortInterface $meter,
        private LoggerInterface $logger,
        private Stopwatch $stopwatch,
    ) {
    }

    /**
     * Compiles a deterministic descriptor stream into a DefinitionGraph.
     *
     * The iterable order is authoritative and MUST already represent the
     * caller-owned deterministic provider/module order. This method never
     * re-sorts the descriptor stream before applying override semantics.
     *
     * @param iterable<array<string, mixed>> $descriptors
     *
     * @throws ContainerCompileFailedException
     */
    public function compile(iterable $descriptors): DefinitionGraph
    {
        $startedAt = $this->safeStartTimer();
        $span = $this->safeStartSpan();

        $outcome = self::OUTCOME_FAILURE;

        try {
            $graph = self::compileDescriptors($descriptors);

            self::assertCompiledGraphSafe($graph);

            $outcome = self::OUTCOME_SUCCESS;

            return $graph;
        } catch (ContainerCompileFailedException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_COMPILE_FAILED,
            );
        } finally {
            $durationMs = $this->safeStopTimer($startedAt);

            $this->safeEmitObservability(
                span: $span,
                outcome: $outcome,
                durationMs: $durationMs,
            );
        }
    }

    /**
     * @param iterable<array<string, mixed>> $descriptors
     *
     * @throws ContainerCompileFailedException
     */
    private static function compileDescriptors(iterable $descriptors): DefinitionGraph
    {
        $graph = DefinitionGraph::empty();
        $count = 0;

        foreach ($descriptors as $descriptor) {
            ++$count;

            if ($count > self::MAX_DESCRIPTORS) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }

            if (!\is_array($descriptor) || \array_is_list($descriptor)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_DEFINITION_INVALID,
                );
            }

            self::assertDescriptorValue($descriptor, 0);

            $graph = self::applyDescriptor($graph, $descriptor);
        }

        return $graph;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        $kind = self::requiredString($descriptor, 'kind');

        return match ($kind) {
            self::DESCRIPTOR_SERVICE_CLASS => self::applyServiceClassDescriptor($graph, $descriptor),
            self::DESCRIPTOR_SERVICE_FACTORY_CLASS_METHOD => self::applyServiceFactoryClassMethodDescriptor(
                $graph,
                $descriptor
            ),
            self::DESCRIPTOR_SERVICE_FACTORY_SERVICE_METHOD => self::applyServiceFactoryServiceMethodDescriptor(
                $graph,
                $descriptor
            ),
            self::DESCRIPTOR_ALIAS => self::applyAliasDescriptor($graph, $descriptor),
            self::DESCRIPTOR_PARAMETER => self::applyParameterDescriptor($graph, $descriptor),
            self::DESCRIPTOR_PARAMETERS => self::applyParametersDescriptor($graph, $descriptor),
            self::DESCRIPTOR_TAG => self::applyTagDescriptor($graph, $descriptor),
            default => throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_DEFINITION_INVALID,
            ),
        };
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyServiceClassDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        self::assertAllowedKeys(
            $descriptor,
            ['arguments', 'class', 'id', 'kind', 'shared'],
        );

        return $graph->withService(
            ServiceDefinition::class(
                id: self::requiredString($descriptor, 'id'),
                class: self::requiredString($descriptor, 'class'),
                arguments: self::optionalList($descriptor, 'arguments'),
                shared: self::optionalBool($descriptor, 'shared', true),
            ),
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyServiceFactoryClassMethodDescriptor(
        DefinitionGraph $graph,
        array $descriptor,
    ): DefinitionGraph {
        self::assertAllowedKeys(
            $descriptor,
            ['arguments', 'factoryClass', 'id', 'kind', 'method', 'shared'],
        );

        return $graph->withService(
            ServiceDefinition::factoryClassMethod(
                id: self::requiredString($descriptor, 'id'),
                factoryClass: self::requiredString($descriptor, 'factoryClass'),
                method: self::requiredString($descriptor, 'method'),
                arguments: self::optionalList($descriptor, 'arguments'),
                shared: self::optionalBool($descriptor, 'shared', true),
            ),
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyServiceFactoryServiceMethodDescriptor(
        DefinitionGraph $graph,
        array $descriptor,
    ): DefinitionGraph {
        self::assertAllowedKeys(
            $descriptor,
            ['arguments', 'factoryServiceId', 'id', 'kind', 'method', 'shared'],
        );

        return $graph->withService(
            ServiceDefinition::factoryServiceMethod(
                id: self::requiredString($descriptor, 'id'),
                factoryServiceId: self::requiredString($descriptor, 'factoryServiceId'),
                method: self::requiredString($descriptor, 'method'),
                arguments: self::optionalList($descriptor, 'arguments'),
                shared: self::optionalBool($descriptor, 'shared', true),
            ),
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyAliasDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        self::assertAllowedKeys(
            $descriptor,
            ['alias', 'kind', 'serviceId'],
        );

        return $graph->withAlias(
            alias: self::requiredString($descriptor, 'alias'),
            serviceId: self::requiredString($descriptor, 'serviceId'),
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyParameterDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        self::assertAllowedKeys(
            $descriptor,
            ['kind', 'name', 'value'],
        );

        if (!\array_key_exists('value', $descriptor)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        return $graph->withParameter(
            name: self::requiredString($descriptor, 'name'),
            value: $descriptor['value'],
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyParametersDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        self::assertAllowedKeys(
            $descriptor,
            ['kind', 'values'],
        );

        $values = self::requiredMap($descriptor, 'values');

        foreach ($values as $name => $value) {
            if (!\is_string($name)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_PARAMETER_INVALID,
                );
            }

            $graph = $graph->withParameter($name, $value);
        }

        return $graph;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function applyTagDescriptor(DefinitionGraph $graph, array $descriptor): DefinitionGraph
    {
        self::assertAllowedKeys(
            $descriptor,
            ['kind', 'meta', 'priority', 'serviceId', 'tag'],
        );

        if (\array_key_exists('meta', $descriptor)) {
            $meta = $descriptor['meta'];

            if (!\is_array($meta) || !self::isMapArray($meta)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_TAG_INVALID,
                );
            }

            self::assertDescriptorValue($meta, 0);
        }

        return $graph->withTag(
            tag: self::requiredString($descriptor, 'tag'),
            serviceId: self::requiredString($descriptor, 'serviceId'),
            priority: self::optionalInt($descriptor, 'priority', 0),
        );
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function requiredString(array $descriptor, string $key): string
    {
        $value = $descriptor[$key] ?? null;

        if (!\is_string($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_DEFINITION_INVALID,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @return list<mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function optionalList(array $descriptor, string $key): array
    {
        if (!\array_key_exists($key, $descriptor)) {
            return [];
        }

        $value = $descriptor[$key];

        if (!\is_array($value) || !\array_is_list($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_ARGUMENT_INVALID,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function optionalBool(array $descriptor, string $key, bool $default): bool
    {
        if (!\array_key_exists($key, $descriptor)) {
            return $default;
        }

        $value = $descriptor[$key];

        if (!\is_bool($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_DEFINITION_INVALID,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @throws ContainerCompileFailedException
     */
    private static function optionalInt(array $descriptor, string $key, int $default): int
    {
        if (!\array_key_exists($key, $descriptor)) {
            return $default;
        }

        $value = $descriptor[$key];

        if (!\is_int($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_TAG_INVALID,
            );
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $descriptor
     *
     * @return array<string, mixed>
     *
     * @throws ContainerCompileFailedException
     */
    private static function requiredMap(array $descriptor, string $key): array
    {
        $value = $descriptor[$key] ?? null;

        if (!\is_array($value) || !self::isMapArray($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_PARAMETER_INVALID,
            );
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $descriptor
     * @param list<string> $allowedKeys
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertAllowedKeys(array $descriptor, array $allowedKeys): void
    {
        $allowed = [];

        foreach ($allowedKeys as $key) {
            $allowed[$key] = true;
        }

        foreach (\array_keys($descriptor) as $key) {
            if (!\is_string($key) || !isset($allowed[$key])) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_DEFINITION_INVALID,
                );
            }
        }
    }

    /**
     * Rejects non-deterministic descriptor values before artifact write.
     *
     * This check intentionally rejects closures anywhere in the descriptor
     * stream, including definitions, factories, configurators, lazy factories,
     * arguments, parameters, tag metadata, and graph-owned schema values.
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertDescriptorValue(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_DESCRIPTOR_DEPTH) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            self::assertSafeDescriptorString($value);

            return;
        }

        if (\is_float($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (\is_resource($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if ($value instanceof \Closure) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CLOSURE_DEFINITION,
            );
        }

        if (\is_object($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (!\is_array($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (\array_is_list($value)) {
            self::assertDescriptorList($value, $depth + 1);

            return;
        }

        self::assertDescriptorMap($value, $depth + 1);
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertDescriptorList(array $value, int $depth): void
    {
        if (\count($value) > self::MAX_LIST_ITEMS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        self::rejectRawCallableArray($value);

        foreach ($value as $item) {
            self::assertDescriptorValue($item, $depth);
        }
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function assertDescriptorMap(array $value, int $depth): void
    {
        if (\count($value) > self::MAX_MAP_KEYS) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        foreach ($value as $key => $item) {
            if (!\is_string($key) || !self::isSafeMapKey($key)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
                );
            }

            self::assertDescriptorValue($item, $depth);
        }
    }

    /**
     * @param list<mixed> $value
     *
     * @throws ContainerCompileFailedException
     */
    private static function rejectRawCallableArray(array $value): void
    {
        if (\count($value) !== 2) {
            return;
        }

        [$target, $method] = $value;

        if (!\is_string($target) || !\is_string($method)) {
            return;
        }

        if (!self::isSafeMethodName($method)) {
            return;
        }

        if (self::isClassLikeString($target)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CALLABLE_DEFINITION,
            );
        }
    }

    private static function isMapArray(array $value): bool
    {
        return $value === [] || !\array_is_list($value);
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertCompiledGraphSafe(DefinitionGraph $graph): void
    {
        $payload = $graph->toArray();

        self::assertAllowedKeys(
            $payload,
            ['aliases', 'parameters', 'services', 'tags'],
        );

        foreach (['aliases', 'parameters', 'services', 'tags'] as $key) {
            if (
                !\array_key_exists($key, $payload)
                || !\is_array($payload[$key])
                || !self::isMapArray($payload[$key])
            ) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }
        }

        self::assertCompiledGraphValue($payload, 0);
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertCompiledGraphValue(mixed $value, int $depth): void
    {
        if ($depth > self::MAX_DESCRIPTOR_DEPTH) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if ($value === null || \is_bool($value) || \is_int($value)) {
            return;
        }

        if (\is_string($value)) {
            self::assertSafeDescriptorString($value);

            return;
        }

        if (\is_float($value) || \is_resource($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if ($value instanceof \Closure) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_CLOSURE_DEFINITION,
            );
        }

        if (\is_object($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }

        if (!\is_array($value)) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }

        if (\array_is_list($value)) {
            foreach ($value as $item) {
                self::assertCompiledGraphValue($item, $depth + 1);
            }

            return;
        }

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw ContainerCompileFailedException::withReason(
                    ContainerCompileFailedException::REASON_GRAPH_INVALID,
                );
            }

            self::assertSafeCompiledGraphKey($key);
            self::assertCompiledGraphValue($item, $depth + 1);
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertSafeCompiledGraphKey(string $key): void
    {
        if (
            $key === ''
            || \strlen($key) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $key) === 1
            || \str_contains($key, '://')
            || \str_contains($key, '::')
            || self::looksLikeAbsolutePath($key)
            || self::looksLikeSourceSnippet($key)
            || self::looksLikeEnvValue($key)
            || self::looksSensitive($key)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_GRAPH_INVALID,
            );
        }
    }

    /**
     * @throws ContainerCompileFailedException
     */
    private static function assertSafeDescriptorString(string $value): void
    {
        if (
            \strlen($value) > self::MAX_STRING_BYTES
            || \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) === 1
            || \str_contains($value, '://')
            || \str_contains($value, '::')
            || self::looksLikeAbsolutePath($value)
            || self::looksLikeSourceSnippet($value)
            || self::looksLikeEnvValue($value)
            || self::looksSensitive($value)
        ) {
            throw ContainerCompileFailedException::withReason(
                ContainerCompileFailedException::REASON_NON_DETERMINISTIC_VALUE,
            );
        }
    }

    private static function isSafeMapKey(string $key): bool
    {
        return $key !== ''
            && \strlen($key) <= 128
            && \preg_match(self::MAP_KEY_PATTERN, $key) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $key) !== 1
            && !self::looksLikeAbsolutePath($key)
            && !self::looksLikeSourceSnippet($key)
            && !self::looksLikeEnvValue($key)
            && !self::looksSensitive($key);
    }

    private static function isSafeMethodName(string $method): bool
    {
        return $method !== ''
            && \preg_match(self::METHOD_PATTERN, $method) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $method) !== 1;
    }

    private static function isClassLikeString(string $value): bool
    {
        return $value !== ''
            && \strlen($value) <= self::MAX_STRING_BYTES
            && \trim($value) === $value
            && \preg_match(self::CLASS_LIKE_PATTERN, $value) === 1
            && \preg_match(self::CONTROL_CHARACTER_PATTERN, $value) !== 1
            && !\str_starts_with($value, '\\')
            && !\str_contains($value, '::')
            && !self::looksLikeAbsolutePath($value)
            && !self::looksLikeSourceSnippet($value);
    }

    private static function looksLikeAbsolutePath(string $value): bool
    {
        return \str_starts_with($value, '/')
            || \str_starts_with($value, '\\')
            || \preg_match('/\A[A-Za-z]:[\/\\\\]/', $value) === 1;
    }

    private static function looksLikeSourceSnippet(string $value): bool
    {
        return \preg_match(self::SOURCE_SNIPPET_PATTERN, $value) === 1;
    }

    private static function looksLikeEnvValue(string $value): bool
    {
        return \preg_match(self::ENV_LIKE_PATTERN, $value) === 1;
    }

    private static function looksSensitive(string $value): bool
    {
        return \preg_match(self::SENSITIVE_VALUE_PATTERN, $value) === 1;
    }

    private function safeStartSpan(): ?SpanInterface
    {
        try {
            return $this->tracer->startSpan(self::SPAN_CONTAINER_COMPILE);
        } catch (\Throwable) {
            return null;
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
            return self::safeCount($this->stopwatch->stop($startedAt));
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeEmitObservability(
        ?SpanInterface $span,
        string $outcome,
        int $durationMs,
    ): void {
        $outcome = self::safeOutcome($outcome);
        $durationMs = self::safeCount($durationMs);

        $this->safeFinishSpan(
            span: $span,
            outcome: $outcome,
            durationMs: $durationMs,
        );

        $this->safeEmitMetrics(
            outcome: $outcome,
            durationMs: $durationMs,
        );

        $this->safeLogSummary(
            outcome: $outcome,
            durationMs: $durationMs,
        );
    }

    private function safeFinishSpan(
        ?SpanInterface $span,
        string $outcome,
        int $durationMs,
    ): void {
        if ($span === null) {
            return;
        }

        try {
            $span->setAttributes(self::spanAttributes($outcome, $durationMs));
        } catch (\Throwable) {
            // Observability is best-effort and must not alter compile behavior.
        }

        try {
            $span->end();
        } catch (\Throwable) {
            // Observability is best-effort and must not alter compile behavior.
        }
    }

    private function safeEmitMetrics(string $outcome, int $durationMs): void
    {
        try {
            $labels = [
                'outcome' => self::safeOutcome($outcome),
            ];

            $this->meter->increment(
                self::METRIC_CONTAINER_COMPILE_TOTAL,
                1,
                $labels,
            );

            $this->meter->observe(
                self::METRIC_CONTAINER_COMPILE_DURATION_MS,
                $durationMs,
                $labels,
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter compile behavior.
        }
    }

    private function safeLogSummary(string $outcome, int $durationMs): void
    {
        try {
            $this->logger->info(
                self::LOG_EVENT_CONTAINER_COMPILE,
                [
                    'duration_ms' => self::safeCount($durationMs),
                    'outcome' => self::safeOutcome($outcome),
                ],
            );
        } catch (\Throwable) {
            // Observability is best-effort and must not alter compile behavior.
        }
    }

    /**
     * @return array{duration_ms: int, outcome: string}
     */
    private static function spanAttributes(string $outcome, int $durationMs): array
    {
        return [
            'duration_ms' => self::safeCount($durationMs),
            'outcome' => self::safeOutcome($outcome),
        ];
    }

    private static function safeOutcome(string $outcome): string
    {
        return $outcome === self::OUTCOME_SUCCESS
            ? self::OUTCOME_SUCCESS
            : self::OUTCOME_FAILURE;
    }

    private static function safeCount(int $value): int
    {
        if ($value <= 0) {
            return 0;
        }

        return \min($value, self::MAX_SAFE_COUNT);
    }
}
