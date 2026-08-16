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

use Coretsia\Kernel\Runtime\Driver\BackgroundDriver;
use Coretsia\Kernel\Runtime\Driver\HttpDriver;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverContributions;
use Coretsia\Kernel\Runtime\Driver\RuntimeDriverResolver;
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
 * - validates fixture shape before invoking RuntimeDriverResolver;
 * - builds an in-memory ConfigRepositoryInterface containing Kernel-owned config only;
 * - maps explicit canonical fixture driver ids to RuntimeDriverContributions;
 * - invokes RuntimeDriverResolver directly;
 * - asserts only deterministic outcome, error code, reason token, and driver ids.
 *
 * It must not inspect ModulePlan, map owner config, shell out, read environment
 * variables, depend on runtime adapters, start runtime loops, or write artifacts.
 */
abstract class RuntimeDriverMatrixTestSupport extends ToolContractTestCase
{
    /**
     * @var list<string>
     */
    private const array CONTRIBUTION_KEYS = [
        'httpDriverIds',
        'backgroundDriverIds',
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
        RuntimeDriverConflictException::REASON_MULTIPLE_HTTP_DRIVERS => true,
        RuntimeDriverConflictException::REASON_WORKER_HTTP_CONFLICTS_WITH_HTTP_DRIVER => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array INVALID_CONFIG_REASONS = [
        RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_MISSING => true,
        RuntimeDriverInvalidConfigException::REASON_CONFIG_KEY_INVALID => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array HTTP_DRIVER_IDS = [
        'http.classic' => true,
        'http.frankenphp' => true,
        'http.roadrunner' => true,
        'http.swoole' => true,
        'http.worker' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array BACKGROUND_DRIVER_IDS = [
        'bg.worker_queue' => true,
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
     * Loads a fixture, validates it, invokes the resolver, and asserts deterministic
     * expected output.
     */
    protected function assertRuntimeDriverMatrixFixtureMatchesResolver(string $fixtureName): void
    {
        $fixture = $this->loadRuntimeDriverMatrixFixture($fixtureName);

        $actual = $this->runRuntimeDriverMatrix(
            config: $fixture['config'],
            contributions: $fixture['contributions'],
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
     *     contributions: array{
     *         httpDriverIds: list<string>,
     *         backgroundDriverIds: list<string>
     *     },
     *     expected: array{
     *         outcome: string,
     *         code: ?string,
     *         reason: ?string,
     *         activeDriverIds: list<string>,
     *         conflictingDriverIds: list<string>
     *     }
     * }
     */
    protected function loadRuntimeDriverMatrixFixture(string $fixtureName): array
    {
        $fixtureName = self::normalizeFixtureName($fixtureName);

        $config = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'config.php');
        $contributions = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'contributions.php');
        $expected = $this->loadRuntimeDriverMatrixArrayFixture($fixtureName, 'expected.php');

        $this->validateRuntimeDriverMatrixConfig($fixtureName, $config);
        $this->validateRuntimeDriverMatrixContributions($fixtureName, $contributions);
        $this->validateRuntimeDriverMatrixExpected($fixtureName, $expected);

        return [
            'config' => $config,
            'contributions' => $contributions,
            'expected' => $expected,
        ];
    }

    /**
     * Runs RuntimeDriverResolver against already validated fixture arrays.
     *
     * @param array<string, mixed> $config
     * @param array{httpDriverIds: list<string>, backgroundDriverIds: list<string>} $contributions
     *
     * @return array{
     *     outcome: string,
     *     code: ?string,
     *     reason: ?string,
     *     activeDriverIds: list<string>,
     *     conflictingDriverIds: list<string>
     * }
     */
    protected function runRuntimeDriverMatrix(array $config, array $contributions): array
    {
        $resolver = new RuntimeDriverResolver();

        try {
            $drivers = $resolver->resolve(
                config: new RuntimeDriverMatrixConfigRepository($config),
                contributions: self::runtimeDriverContributions($contributions),
            );

            return [
                'outcome' => 'allowed',
                'code' => null,
                'reason' => null,
                'activeDriverIds' => $drivers->driverIds(),
                'conflictingDriverIds' => [],
            ];
        } catch (RuntimeDriverConflictException $exception) {
            return [
                'outcome' => 'conflict',
                'code' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'activeDriverIds' => $exception->activeDriverIds(),
                'conflictingDriverIds' => $exception->conflictingDriverIds(),
            ];
        } catch (RuntimeDriverInvalidConfigException $exception) {
            return [
                'outcome' => 'invalid_config',
                'code' => $exception->errorCode(),
                'reason' => $exception->reason(),
                'activeDriverIds' => [],
                'conflictingDriverIds' => [],
            ];
        }
    }

    /**
     * @param array{httpDriverIds: list<string>, backgroundDriverIds: list<string>} $fixture
     */
    private static function runtimeDriverContributions(array $fixture): RuntimeDriverContributions
    {
        return RuntimeDriverContributions::fromDrivers(
            httpDrivers: array_map(
                static fn (string $driverId): HttpDriver => HttpDriver::from($driverId),
                $fixture['httpDriverIds'],
            ),
            backgroundDrivers: array_map(
                static fn (string $driverId): BackgroundDriver => BackgroundDriver::from($driverId),
                $fixture['backgroundDriverIds'],
            ),
        );
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

        foreach ($config as $key => $_value) {
            if ($key !== 'kernel.runtime.http_driver') {
                throw new RuntimeException('Runtime driver matrix config key invalid: ' . $label);
            }
        }
    }

    /**
     * @param array<mixed> $contributions
     */
    private function validateRuntimeDriverMatrixContributions(string $fixtureName, array $contributions): void
    {
        $label = self::runtimeDriverMatrixFixtureRelativePath($fixtureName, 'contributions.php');

        if (array_keys($contributions) !== self::CONTRIBUTION_KEYS) {
            throw new RuntimeException('Runtime driver matrix contribution keys invalid: ' . $label);
        }

        self::assertDriverIdList(
            value: $contributions['httpDriverIds'],
            field: 'httpDriverIds',
            allowedDriverIds: self::HTTP_DRIVER_IDS,
            label: $label,
        );
        self::assertDriverIdList(
            value: $contributions['backgroundDriverIds'],
            field: 'backgroundDriverIds',
            allowedDriverIds: self::BACKGROUND_DRIVER_IDS,
            label: $label,
        );
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
            $label,
        );

        if ($outcome === 'allowed') {
            self::assertAllowedExpectedShape($expected, $activeDriverIds, $conflictingDriverIds, $label);

            return;
        }

        if ($outcome === 'conflict') {
            self::assertConflictExpectedShape($expected, $activeDriverIds, $conflictingDriverIds, $label);

            return;
        }

        self::assertInvalidConfigExpectedShape($expected, $activeDriverIds, $conflictingDriverIds, $label);
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    private static function assertAllowedExpectedShape(
        array $expected,
        array $activeDriverIds,
        array $conflictingDriverIds,
        string $label,
    ): void {
        if ($expected['code'] !== null || $expected['reason'] !== null) {
            throw new RuntimeException('Runtime driver matrix allowed expected code/reason must be null: ' . $label);
        }

        if ($activeDriverIds === []) {
            throw new RuntimeException(
                'Runtime driver matrix allowed expected activeDriverIds must be non-empty: ' . $label
            );
        }

        if ($conflictingDriverIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix allowed expected conflictingDriverIds must be empty: ' . $label
            );
        }
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    private static function assertConflictExpectedShape(
        array $expected,
        array $activeDriverIds,
        array $conflictingDriverIds,
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
    }

    /**
     * @param array<mixed> $expected
     * @param list<string> $activeDriverIds
     * @param list<string> $conflictingDriverIds
     */
    private static function assertInvalidConfigExpectedShape(
        array $expected,
        array $activeDriverIds,
        array $conflictingDriverIds,
        string $label,
    ): void {
        if ($expected['code'] !== RuntimeDriverInvalidConfigException::ERROR_CODE) {
            throw new RuntimeException('Runtime driver matrix invalid-config expected code invalid: ' . $label);
        }

        if (!is_string($expected['reason']) || !isset(self::INVALID_CONFIG_REASONS[$expected['reason']])) {
            throw new RuntimeException('Runtime driver matrix invalid-config expected reason invalid: ' . $label);
        }

        if ($activeDriverIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix invalid-config expected activeDriverIds must be empty: ' . $label
            );
        }

        if ($conflictingDriverIds !== []) {
            throw new RuntimeException(
                'Runtime driver matrix invalid-config expected conflictingDriverIds must be empty: ' . $label
            );
        }
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
     * @param array<string, true> $allowedDriverIds
     *
     * @return list<string>
     */
    private static function assertDriverIdList(
        mixed $value,
        string $field,
        array $allowedDriverIds,
        string $label,
    ): array {
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException('Runtime driver matrix ' . $field . ' must be list<string>: ' . $label);
        }

        $seen = [];

        foreach ($value as $driverId) {
            if (!is_string($driverId) || !isset($allowedDriverIds[$driverId])) {
                throw new RuntimeException(
                    'Runtime driver matrix ' . $field . ' driver id invalid: ' . $label
                );
            }

            if (isset($seen[$driverId])) {
                throw new RuntimeException(
                    'Runtime driver matrix ' . $field . ' driver id duplicate: ' . $label
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
    private static function assertCanonicalDriverIdList(mixed $value, string $field, string $label): array
    {
        return self::assertDriverIdList(
            value: $value,
            field: 'expected ' . $field,
            allowedDriverIds: self::CANONICAL_DRIVER_IDS,
            label: $label,
        );
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
