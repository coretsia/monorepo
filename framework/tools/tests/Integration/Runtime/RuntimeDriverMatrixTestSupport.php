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

namespace Coretsia\Tools\Tests\Integration\Runtime;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Module\ModulePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverGuard;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverConflictException;
use Coretsia\Kernel\Runtime\Exception\RuntimeDriverInvalidConfigException;
use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;
use Coretsia\Tools\Tests\Integration\Runtime\Support\RuntimeDriverMatrixConfigRepository;
use RuntimeException;

/**
 * Shared deterministic runtime-driver matrix fixture runner.
 *
 * This support class intentionally:
 *
 * - loads fixture arrays from framework/tools/tests/Fixtures/RuntimeDriverMatrix;
 * - validates fixture shape before invoking RuntimeDriverGuard;
 * - builds an in-memory ConfigRepositoryInterface;
 * - builds a minimal caller-provided ModulePlan;
 * - invokes RuntimeDriverGuard directly;
 * - asserts only deterministic outcome, error code, reason token, driver ids,
 *   and required module ids.
 *
 * It must not shell out, read environment variables, depend on runtime adapters,
 * start runtime loops, or write artifacts.
 */
abstract class RuntimeDriverMatrixTestSupport extends ToolContractTestCase
{
    /**
     * @var array<string, true>
     */
    private const array CONFIG_KEYS = [
        'kernel.runtime.frankenphp.enabled' => true,
        'kernel.runtime.swoole.enabled' => true,
        'kernel.runtime.roadrunner.enabled' => true,
        'worker.enabled' => true,
        'worker.task_type' => true,
    ];

    /**
     * @var list<string>
     */
    private const array EXPECTED_KEYS = [
        'outcome',
        'code',
        'reason',
        'activeDriverIds',
        'conflictingDriverIds',
        'requiredModuleIds',
    ];

    /**
     * @var array<string, true>
     */
    private const array OUTCOMES = [
        'allowed' => true,
        'conflict' => true,
        'invalid_config' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array CONFLICT_REASONS = [
        'multiple-http-drivers' => true,
        'worker-http-conflicts-with-http-driver' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array INVALID_CONFIG_REASONS = [
        'requires-platform-http-module' => true,
        'worker-task-type-invalid' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array CANONICAL_DRIVER_IDS = [
        'bg.worker_queue' => true,
        'http.classic' => true,
        'http.frankenphp' => true,
        'http.roadrunner' => true,
        'http.swoole' => true,
        'http.worker' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array CANONICAL_REQUIRED_MODULE_IDS = [
        'platform.http' => true,
    ];

    /**
     * Returns runtime-driver matrix fixture names in deterministic order.
     *
     * @return list<string>
     */
    protected function runtimeDriverMatrixFixtureNames(): array
    {
        $items = $this->globSorted($this->runtimeDriverMatrixFixtureRoot() . '/*');

        $fixtures = [];

        foreach ($items as $item) {
            if (!is_dir($item)) {
                continue;
            }

            $fixtures[] = basename($item);
        }

        usort(
            $fixtures,
            static fn (string $left, string $right): int => strcmp($left, $right),
        );

        return $fixtures;
    }

    /**
     * Loads a fixture, validates it, invokes the guard, and asserts deterministic
     * expected output.
     */
    protected function assertRuntimeDriverMatrixFixtureMatchesGuard(string $fixtureName): void
    {
        $fixture = $this->loadRuntimeDriverMatrixFixture($fixtureName);

        $actual = $this->runRuntimeDriverMatrix(
            config: $fixture['config'],
            moduleIds: $fixture['modules'],
        );

        self::assertSame(
            $fixture['expected'],
            $actual,
            'Runtime driver matrix fixture mismatch: ' . self::normalizeFixtureName($fixtureName),
        );
    }

    /**
     * Loads and validates a runtime-driver matrix fixture.
     *
     * @return array{
     *     config: array<string, mixed>,
     *     modules: list<string>,
     *     expected: array{
     *         outcome: string,
     *         code: ?string,
     *         reason: ?string,
     *         activeDriverIds: list<string>,
     *         conflictingDriverIds: list<string>,
     *         requiredModuleIds: list<string>
     *     }
     * }
     */
    protected function loadRuntimeDriverMatrixFixture(string $fixtureName): array
    {
        $fixtureName = self::normalizeFixtureName($fixtureName);

        $config = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'config.php');
        $modules = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'modules.php');
        $expected = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'expected.php');

        $this->validateRuntimeDriverMatrixConfig($fixtureName, $config);
        $this->validateRuntimeDriverMatrixModules($fixtureName, $modules);
        $this->validateRuntimeDriverMatrixExpected($fixtureName, $expected);

        return [
            'config' => $config,
            'modules' => $modules,
            'expected' => $expected,
        ];
    }

    /**
     * Runs RuntimeDriverGuard against already validated fixture arrays.
     *
     * @param array<string, mixed> $config
     * @param list<string> $moduleIds
     *
     * @return array{
     *     outcome: string,
     *     code: ?string,
     *     reason: ?string,
     *     activeDriverIds: list<string>,
     *     conflictingDriverIds: list<string>,
     *     requiredModuleIds: list<string>
     * }
     */
    protected function runRuntimeDriverMatrix(array $config, array $moduleIds): array
    {
        $cfg = new RuntimeDriverMatrixConfigRepository($config);
        $plan = $this->buildRuntimeDriverMatrixModulePlan($moduleIds);
        $guard = new RuntimeDriverGuard();

        try {
            $drivers = $guard->detect($cfg);
            $guard->assertHttpDriverCompatibleWithModules($cfg, $plan);

            return [
                'outcome' => 'allowed',
                'code' => null,
                'reason' => null,
                'activeDriverIds' => $drivers->driverIds(),
                'conflictingDriverIds' => [],
                'requiredModuleIds' => [],
            ];
        } catch (RuntimeDriverConflictException $exception) {
            return [
                'outcome' => 'conflict',
                'code' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'activeDriverIds' => $exception->activeDriverIds(),
                'conflictingDriverIds' => $exception->conflictingDriverIds(),
                'requiredModuleIds' => [],
            ];
        } catch (RuntimeDriverInvalidConfigException $exception) {
            return [
                'outcome' => 'invalid_config',
                'code' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'activeDriverIds' => $exception->activeDriverIds(),
                'conflictingDriverIds' => [],
                'requiredModuleIds' => $exception->requiredModuleIds(),
            ];
        }
    }

    private function runtimeDriverMatrixFixtureRoot(): string
    {
        return $this->frameworkRoot() . '/tools/tests/Fixtures/RuntimeDriverMatrix';
    }

    private function runtimeDriverMatrixFixturePath(string $fixtureName, string $file): string
    {
        return $this->runtimeDriverMatrixFixtureRoot() . '/' . $fixtureName . '/' . $file;
    }

    private static function runtimeDriverMatrixFixtureRelativePath(string $fixtureName, string $file): string
    {
        return self::normalizePathForMessage(
            'framework/tools/tests/Fixtures/RuntimeDriverMatrix/' . $fixtureName . '/' . $file,
        );
    }

    /**
     * @return array<mixed>
     */
    private function loadRuntimeDriverMatrixArrayFixture(string $fixtureName, string $file): array
    {
        $path = $this->runtimeDriverMatrixFixturePath($fixtureName, $file);
        $relativePath = self::runtimeDriverMatrixFixtureRelativePath($fixtureName, $file);

        if (!is_file($path)) {
            throw new RuntimeException('Missing runtime driver matrix fixture: ' . $relativePath);
        }

        $value = require $path;

        if (!is_array($value)) {
            throw new RuntimeException('Runtime driver matrix fixture must return array: ' . $relativePath);
        }

        self::assertPlainRuntimeDriverMatrixValue($value, $relativePath);

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private function validateRuntimeDriverMatrixConfig(string $fixtureName, array $config): void
    {
        $label = self::runtimeDriverMatrixFixtureRelativePath($fixtureName, 'config.php');

        foreach ($config as $key => $value) {
            if (!is_string($key) || !isset(self::CONFIG_KEYS[$key])) {
                throw new RuntimeException('Runtime driver matrix config key invalid: ' . $label);
            }

            if ($key === 'worker.task_type') {
                if (!is_string($value) || $value === '') {
                    throw new RuntimeException(
                        'Runtime driver matrix worker.task_type must be non-empty string: ' . $label
                    );
                }

                continue;
            }

            if (!is_bool($value)) {
                throw new RuntimeException('Runtime driver matrix config flag must be bool: ' . $label);
            }
        }
    }

    /**
     * @param array<mixed> $modules
     */
    private function validateRuntimeDriverMatrixModules(string $fixtureName, array $modules): void
    {
        $label = self::runtimeDriverMatrixFixtureRelativePath($fixtureName, 'modules.php');

        if (!array_is_list($modules)) {
            throw new RuntimeException('Runtime driver matrix modules fixture must be list<string>: ' . $label);
        }

        $seen = [];

        foreach ($modules as $moduleId) {
            if (!is_string($moduleId) || $moduleId === '' || !ModuleId::isValid($moduleId)) {
                throw new RuntimeException('Runtime driver matrix module id invalid: ' . $label);
            }

            if (isset($seen[$moduleId])) {
                throw new RuntimeException('Runtime driver matrix module id duplicate: ' . $label);
            }

            $seen[$moduleId] = true;
        }

        self::assertSortedStringList(array_keys($seen), $label);
    }

    /**
     * @param array<mixed> $expected
     */
    private function validateRuntimeDriverMatrixExpected(string $fixtureName, array $expected): void
    {
        $label = self::runtimeDriverMatrixFixtureRelativePath($fixtureName, 'expected.php');

        if (array_keys($expected) !== self::EXPECTED_KEYS) {
            throw new RuntimeException('Runtime driver matrix expected keys invalid: ' . $label);
        }

        $outcome = $expected['outcome'];

        if (!is_string($outcome) || !isset(self::OUTCOMES[$outcome])) {
            throw new RuntimeException('Runtime driver matrix expected outcome invalid: ' . $label);
        }

        self::assertNullableString($expected['code'], 'code', $label);
        self::assertNullableString($expected['reason'], 'reason', $label);

        $activeDriverIds = self::assertCanonicalDriverIdList($expected['activeDriverIds'], 'activeDriverIds', $label);
        $conflictingDriverIds = self::assertCanonicalDriverIdList(
            $expected['conflictingDriverIds'],
            'conflictingDriverIds',
            $label
        );
        $requiredModuleIds = self::assertRequiredModuleIdList($expected['requiredModuleIds'], $label);

        if ($outcome === 'allowed') {
            self::assertAllowedExpectedShape($expected, $conflictingDriverIds, $requiredModuleIds, $label);

            return;
        }

        if ($outcome === 'conflict') {
            self::assertConflictExpectedShape(
                $expected,
                $activeDriverIds,
                $conflictingDriverIds,
                $requiredModuleIds,
                $label
            );

            return;
        }

        self::assertInvalidConfigExpectedShape($expected, $conflictingDriverIds, $label);
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $conflictingDriverIds
     * @param list<string> $requiredModuleIds
     */
    private static function assertAllowedExpectedShape(
        array $expected,
        array $conflictingDriverIds,
        array $requiredModuleIds,
        string $label,
    ): void {
        if ($expected['code'] !== null || $expected['reason'] !== null) {
            throw new RuntimeException('Runtime driver matrix allowed expected code/reason must be null: ' . $label);
        }

        if ($conflictingDriverIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix allowed expected conflictingDriverIds must be empty: ' . $label
            );
        }

        if ($requiredModuleIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix allowed expected requiredModuleIds must be empty: ' . $label
            );
        }
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     * @param list<string> $requiredModuleIds
     */
    private static function assertConflictExpectedShape(
        array $expected,
        array $activeDriverIds,
        array $conflictingDriverIds,
        array $requiredModuleIds,
        string $label,
    ): void {
        if ($expected['code'] !== RuntimeDriverConflictException::ERROR_CODE) {
            throw new RuntimeException('Runtime driver matrix conflict expected code invalid: ' . $label);
        }

        if (!is_string($expected['reason']) || !isset(self::CONFLICT_REASONS[$expected['reason']])) {
            throw new RuntimeException('Runtime driver matrix conflict expected reason invalid: ' . $label);
        }

        if ($activeDriverIds === []) {
            throw new RuntimeException(
                'Runtime driver matrix conflict expected activeDriverIds must be non-empty: ' . $label
            );
        }

        if ($conflictingDriverIds === []) {
            throw new RuntimeException(
                'Runtime driver matrix conflict expected conflictingDriverIds must be non-empty: ' . $label
            );
        }

        if ($requiredModuleIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix conflict expected requiredModuleIds must be empty: ' . $label
            );
        }
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $conflictingDriverIds
     */
    private static function assertInvalidConfigExpectedShape(
        array $expected,
        array $conflictingDriverIds,
        string $label,
    ): void {
        if ($expected['code'] !== RuntimeDriverInvalidConfigException::ERROR_CODE) {
            throw new RuntimeException('Runtime driver matrix invalid-config expected code invalid: ' . $label);
        }

        if (!is_string($expected['reason']) || !isset(self::INVALID_CONFIG_REASONS[$expected['reason']])) {
            throw new RuntimeException('Runtime driver matrix invalid-config expected reason invalid: ' . $label);
        }

        if ($conflictingDriverIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix invalid-config expected conflictingDriverIds must be empty: ' . $label
            );
        }
    }

    /**
     * @param list<string> $moduleIds
     */
    private function buildRuntimeDriverMatrixModulePlan(array $moduleIds): ModulePlan
    {
        usort(
            $moduleIds,
            static fn (string $left, string $right): int => strcmp($left, $right),
        );

        $enabled = [];
        $entries = [];

        foreach ($moduleIds as $moduleIdString) {
            $moduleId = ModuleId::fromString($moduleIdString);

            $enabled[] = $moduleId;
            $entries[] = new ModulePlanEntry(
                moduleId: $moduleId,
                composerName: self::composerNameForModuleId($moduleId),
            );
        }

        return new ModulePlan(
            app: 'web',
            preset: 'micro',
            enabled: $enabled,
            disabled: [],
            optionalMissing: [],
            topologicalOrder: $enabled,
            modules: $entries,
            warnings: [],
        );
    }

    private static function composerNameForModuleId(ModuleId $moduleId): string
    {
        return 'coretsia/' . str_replace('.', '-', $moduleId->value());
    }

    private static function normalizeFixtureName(string $fixtureName): string
    {
        $fixtureName = trim(str_replace('\\', '/', $fixtureName));

        if ($fixtureName === '' || str_contains($fixtureName, '/')) {
            throw new RuntimeException('Runtime driver matrix fixture name invalid.');
        }

        if (preg_match('/^[A-Za-z0-9]+App$/', $fixtureName) !== 1) {
            throw new RuntimeException('Runtime driver matrix fixture name invalid.');
        }

        return $fixtureName;
    }

    private static function normalizePathForMessage(string $path): string
    {
        return str_replace('\\', '/', $path);
    }

    private static function assertNullableString(mixed $value, string $field, string $label): void
    {
        if ($value !== null && !is_string($value)) {
            throw new RuntimeException('Runtime driver matrix expected ' . $field . ' must be string|null: ' . $label);
        }
    }

    /**
     * @return list<string>
     */
    private static function assertCanonicalDriverIdList(mixed $value, string $field, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException('Runtime driver matrix expected ' . $field . ' must be list<string>: ' . $label);
        }

        $seen = [];

        foreach ($value as $driverId) {
            if (!is_string($driverId) || !isset(self::CANONICAL_DRIVER_IDS[$driverId])) {
                throw new RuntimeException(
                    'Runtime driver matrix expected ' . $field . ' driver id invalid: ' . $label
                );
            }

            if (isset($seen[$driverId])) {
                throw new RuntimeException(
                    'Runtime driver matrix expected ' . $field . ' driver id duplicate: ' . $label
                );
            }

            $seen[$driverId] = true;
        }

        $driverIds = array_keys($seen);
        self::assertSortedStringList($driverIds, $label);

        return $driverIds;
    }

    /**
     * @return list<string>
     */
    private static function assertRequiredModuleIdList(mixed $value, string $label): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException(
                'Runtime driver matrix expected requiredModuleIds must be list<string>: ' . $label
            );
        }

        $seen = [];

        foreach ($value as $moduleId) {
            if (!is_string($moduleId) || !isset(self::CANONICAL_REQUIRED_MODULE_IDS[$moduleId])) {
                throw new RuntimeException(
                    'Runtime driver matrix expected requiredModuleIds module id invalid: ' . $label
                );
            }

            if (isset($seen[$moduleId])) {
                throw new RuntimeException('Runtime driver matrix expected requiredModuleIds duplicate: ' . $label);
            }

            $seen[$moduleId] = true;
        }

        $moduleIds = array_keys($seen);
        self::assertSortedStringList($moduleIds, $label);

        return $moduleIds;
    }

    /**
     * @param list<string> $values
     */
    private static function assertSortedStringList(array $values, string $label): void
    {
        $sorted = $values;

        usort(
            $sorted,
            static fn (string $left, string $right): int => strcmp($left, $right),
        );

        if ($values !== $sorted) {
            throw new RuntimeException('Runtime driver matrix string list must be sorted by strcmp: ' . $label);
        }
    }

    private static function assertPlainRuntimeDriverMatrixValue(mixed $value, string $label): void
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return;
        }

        if (is_array($value)) {
            foreach ($value as $nestedValue) {
                self::assertPlainRuntimeDriverMatrixValue($nestedValue, $label);
            }

            return;
        }

        throw new RuntimeException('Runtime driver matrix fixture value must be plain scalar/array only: ' . $label);
    }
}
