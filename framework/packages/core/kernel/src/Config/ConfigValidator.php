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

use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigValidationResult;
use Coretsia\Contracts\Config\ConfigValidationViolation;
use Coretsia\Contracts\Config\ConfigValidatorInterface;
use Coretsia\Kernel\Config\Exception\ConfigInvalidException;

/**
 * Deterministic declarative config validator.
 *
 * This validator validates the final merged global config against loaded
 * declarative ConfigRuleset instances.
 *
 * It intentionally does not:
 *
 * - discover rules files;
 * - load package rules;
 * - load user/application rules;
 * - execute validation callables;
 * - invent rules for user-owned/custom roots;
 * - reject user-owned/custom roots solely because no ruleset exists.
 *
 * Validation applies only to roots with loaded rulesets.
 *
 * User-owned/custom roots without loaded rulesets are accepted by validation
 * and can be reported through validationSubjects() as:
 *
 * - ownership: user_owned
 * - validation: unvalidated
 *
 * ConfigKernel can pass that safe metadata to ConfigExplainer.
 *
 * Supported baseline rules DSL:
 *
 * - configRoot
 * - schemaVersion
 * - additionalKeys
 * - keys
 * - required
 * - type
 * - items
 * - allowedValues
 *
 * Existing package rules also use:
 *
 * - min
 * - max
 *
 * Supported baseline types:
 *
 * - map
 * - list
 * - string
 * - non-empty-string
 * - non-empty-string-no-ws
 * - relative-safe-path
 * - bool
 * - int
 *
 * Existing package rules also use:
 *
 * - reset-group-id
 *
 * Diagnostics are intentionally structural and safe. They MUST NOT include raw
 * config values, raw env values, absolute paths, secrets, tokens, DSNs, cookies,
 * headers, raw SQL, payloads, object dumps, stack traces, or previous throwable
 * messages.
 *
 * @internal
 */
final readonly class ConfigValidator implements ConfigValidatorInterface
{
    private const int SUPPORTED_SCHEMA_VERSION = ConfigRuleset::SCHEMA_VERSION;

    private const string DIAGNOSTIC_ROOT = 'kernel';

    private const string REASON_ALLOWED_VALUES = 'allowed-values';
    private const string REASON_ADDITIONAL_KEYS_TYPE = 'rule-additional-keys-type';
    private const string REASON_CONFIG_ROOT_MISMATCH = 'rule-config-root-mismatch';
    private const string REASON_CONFIG_ROOT_MISSING = 'rule-config-root-missing';
    private const string REASON_CONFIG_ROOT_TYPE = 'rule-config-root-type';
    private const string REASON_ITEMS_TYPE = 'rule-items-type';
    private const string REASON_KEYS_TYPE = 'rule-keys-type';
    private const string REASON_MIN = 'min';
    private const string REASON_MIN_TYPE = 'rule-min-type';
    private const string REASON_MAX = 'max';
    private const string REASON_MAX_TYPE = 'rule-max-type';
    private const string REASON_RELATIVE_SAFE_PATH = 'relative-safe-path';
    private const string REASON_REQUIRED = 'required';
    private const string REASON_REQUIRED_TYPE = 'rule-required-type';
    private const string REASON_RULE_NOT_MAP = 'rule-not-map';
    private const string REASON_RULESET_NOT_LIST = 'rulesets-not-list';
    private const string REASON_RULESET_TYPE = 'ruleset-type';
    private const string REASON_SCHEMA_VERSION_TYPE = 'rule-schema-version-type';
    private const string REASON_SCHEMA_VERSION_UNSUPPORTED = 'rule-schema-version-unsupported';
    private const string REASON_TYPE = 'type';
    private const string REASON_TYPE_MISSING = 'rule-type-missing';
    private const string REASON_TYPE_UNSUPPORTED = 'rule-type-unsupported';
    private const string REASON_UNKNOWN_KEY = 'unknown-key';
    private const string REASON_UNKNOWN_RULE_KEY = 'rule-unknown-key';

    private const string EXPECTED_ALLOWED_VALUES = 'allowedValues';
    private const string EXPECTED_BOOL = 'bool';
    private const string EXPECTED_INT = 'int';
    private const string EXPECTED_LIST = 'list';
    private const string EXPECTED_MAP = 'map';
    private const string EXPECTED_NON_EMPTY_STRING = 'non-empty-string';
    private const string EXPECTED_NON_EMPTY_STRING_NO_WS = 'non-empty-string-no-ws';
    private const string EXPECTED_PRESENT = 'present';
    private const string EXPECTED_RELATIVE_SAFE_PATH = 'relative-safe-path';
    private const string EXPECTED_RESET_GROUP_ID = 'reset-group-id';
    private const string EXPECTED_STRING = 'string';

    private const string TYPE_BOOL = 'bool';
    private const string TYPE_INT = 'int';
    private const string TYPE_LIST = 'list';
    private const string TYPE_MAP = 'map';
    private const string TYPE_NON_EMPTY_STRING = 'non-empty-string';
    private const string TYPE_NON_EMPTY_STRING_NO_WS = 'non-empty-string-no-ws';
    private const string TYPE_RELATIVE_SAFE_PATH = 'relative-safe-path';
    private const string TYPE_RESET_GROUP_ID = 'reset-group-id';
    private const string TYPE_STRING = 'string';

    /**
     * @var array<string, true>
     */
    private const array SUPPORTED_RULE_KEYS = [
        'additionalKeys' => true,
        'allowedValues' => true,
        'configRoot' => true,
        'items' => true,
        'keys' => true,
        'max' => true,
        'min' => true,
        'required' => true,
        'schemaVersion' => true,
        'type' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array TOP_LEVEL_ONLY_RULE_KEYS = [
        'configRoot' => true,
        'schemaVersion' => true,
    ];

    /**
     * @var array<string, true>
     */
    private const array SUPPORTED_TYPES = [
        self::TYPE_BOOL => true,
        self::TYPE_INT => true,
        self::TYPE_LIST => true,
        self::TYPE_MAP => true,
        self::TYPE_NON_EMPTY_STRING => true,
        self::TYPE_NON_EMPTY_STRING_NO_WS => true,
        self::TYPE_RELATIVE_SAFE_PATH => true,
        self::TYPE_RESET_GROUP_ID => true,
        self::TYPE_STRING => true,
    ];

    /**
     * Validates merged global config against loaded declarative rulesets.
     *
     * An empty ruleset list is valid. In that case no semantic rules are
     * applied, and every present root can be reported separately as unvalidated
     * through validationSubjects().
     *
     * @param array<string,mixed> $config
     * @param list<ConfigRuleset> $rulesets
     */
    public function validate(array $config, array $rulesets): ConfigValidationResult
    {
        $violations = [];

        if (!\array_is_list($rulesets)) {
            $violations[] = self::violation(
                root: self::DIAGNOSTIC_ROOT,
                path: 'rulesets',
                reason: self::REASON_RULESET_NOT_LIST,
                expected: self::EXPECTED_LIST,
                actualType: self::actualType($rulesets),
            );

            return ConfigValidationResult::failure($violations);
        }

        foreach ($rulesets as $index => $ruleset) {
            if (!$ruleset instanceof ConfigRuleset) {
                $violations[] = self::violation(
                    root: self::DIAGNOSTIC_ROOT,
                    path: self::appendPath('rulesets', $index),
                    reason: self::REASON_RULESET_TYPE,
                    expected: 'ConfigRuleset',
                    actualType: self::actualType($ruleset),
                );
            }
        }

        if ($violations !== []) {
            return ConfigValidationResult::failure($violations);
        }

        $rulesetsByRoot = self::rulesetsByRoot($rulesets);

        foreach ($rulesetsByRoot as $root => $ruleset) {
            $rules = $ruleset->rules();

            $shapeViolationCount = \count($violations);

            self::validateRuleShape(
                root: $root,
                rule: $rules,
                rulePath: '',
                topLevel: true,
                violations: $violations,
            );

            if (\count($violations) !== $shapeViolationCount) {
                continue;
            }

            if (!\array_key_exists($root, $config)) {
                $violations[] = self::violation(
                    root: $root,
                    path: '',
                    reason: self::REASON_REQUIRED,
                    expected: self::EXPECTED_PRESENT,
                    actualType: 'null',
                );

                continue;
            }

            self::validateNode(
                value: $config[$root],
                root: $root,
                path: '',
                rule: $rules,
                topLevel: true,
                violations: $violations,
            );
        }

        if ($violations === []) {
            return ConfigValidationResult::success();
        }

        return ConfigValidationResult::failure($violations);
    }

    /**
     * Validates merged global config and throws the canonical config exception
     * when validation fails.
     *
     * @param array<string,mixed> $config
     * @param list<ConfigRuleset> $rulesets
     *
     * @throws ConfigInvalidException
     */
    public function assertValid(array $config, array $rulesets): void
    {
        $result = $this->validate($config, $rulesets);

        if ($result->isFailure()) {
            throw ConfigInvalidException::fromValidationResult($result);
        }
    }

    /**
     * Returns validation subject metadata for ConfigExplainer.
     *
     * This method does not validate config values. It only classifies roots
     * according to loaded rulesets.
     *
     * @param array<string,mixed> $config
     * @param list<ConfigRuleset> $rulesets
     *
     * @return array{
     *     unvalidated: list<array{
     *         root: non-empty-string,
     *         ownership: 'user_owned',
     *         validation: 'unvalidated'
     *     }>,
     *     validated: list<array{
     *         root: non-empty-string,
     *         ownership: 'ruleset_owned',
     *         validation: 'validated'
     *     }>
     * }
     */
    public function validationSubjects(array $config, array $rulesets): array
    {
        $rulesetRoots = self::rulesetRootLookup($rulesets);

        $validated = [];
        $unvalidated = [];

        foreach ($rulesetRoots as $root => $_present) {
            if (!\array_key_exists($root, $config)) {
                continue;
            }

            $validated[$root] = [
                'ownership' => 'ruleset_owned',
                'root' => $root,
                'validation' => 'validated',
            ];
        }

        foreach ($config as $root => $_value) {
            if (!\is_string($root)) {
                continue;
            }

            if (!self::isValidRootName($root)) {
                continue;
            }

            if (isset($rulesetRoots[$root])) {
                continue;
            }

            $unvalidated[$root] = [
                'ownership' => 'user_owned',
                'root' => $root,
                'validation' => 'unvalidated',
            ];
        }

        \ksort($validated, \SORT_STRING);
        \ksort($unvalidated, \SORT_STRING);

        return [
            'unvalidated' => \array_values($unvalidated),
            'validated' => \array_values($validated),
        ];
    }

    /**
     * @param list<ConfigRuleset> $rulesets
     *
     * @return list<non-empty-string>
     */
    public function validatedRoots(array $rulesets): array
    {
        return \array_keys(self::rulesetRootLookup($rulesets));
    }

    /**
     * @param array<string,mixed> $config
     * @param list<ConfigRuleset> $rulesets
     *
     * @return list<non-empty-string>
     */
    public function unvalidatedRoots(array $config, array $rulesets): array
    {
        $subjects = $this->validationSubjects($config, $rulesets);

        return \array_map(
            static fn (array $subject): string => $subject['root'],
            $subjects['unvalidated'],
        );
    }

    /**
     * @param list<ConfigRuleset> $rulesets
     *
     * @return array<non-empty-string, ConfigRuleset>
     */
    private static function rulesetsByRoot(array $rulesets): array
    {
        $byRoot = [];

        foreach ($rulesets as $ruleset) {
            if (!$ruleset instanceof ConfigRuleset) {
                continue;
            }

            $byRoot[$ruleset->root()] = $ruleset;
        }

        \ksort($byRoot, \SORT_STRING);

        return $byRoot;
    }

    /**
     * @param list<ConfigRuleset> $rulesets
     *
     * @return array<non-empty-string, true>
     */
    private static function rulesetRootLookup(array $rulesets): array
    {
        $lookup = [];

        foreach ($rulesets as $ruleset) {
            if (!$ruleset instanceof ConfigRuleset) {
                continue;
            }

            $lookup[$ruleset->root()] = true;
        }

        \ksort($lookup, \SORT_STRING);

        return $lookup;
    }

    /**
     * @param array<string,mixed> $rule
     * @param list<ConfigValidationViolation> $violations
     */
    private static function validateRuleShape(
        string $root,
        array $rule,
        string $rulePath,
        bool $topLevel,
        array &$violations,
    ): void {
        if (\array_is_list($rule) && $rule !== []) {
            $violations[] = self::violation(
                root: $root,
                path: $rulePath,
                reason: self::REASON_RULE_NOT_MAP,
                expected: self::EXPECTED_MAP,
                actualType: self::actualType($rule),
            );

            return;
        }

        foreach ($rule as $key => $_value) {
            if (!isset(self::SUPPORTED_RULE_KEYS[$key])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, $key),
                    reason: self::REASON_UNKNOWN_RULE_KEY,
                    expected: 'supported-rule-key',
                    actualType: self::actualType($key),
                );

                continue;
            }

            if (!$topLevel && isset(self::TOP_LEVEL_ONLY_RULE_KEYS[$key])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, $key),
                    reason: self::REASON_UNKNOWN_RULE_KEY,
                    expected: 'nested-rule-key',
                    actualType: self::actualType($key),
                );
            }
        }

        if ($topLevel) {
            self::validateTopLevelRuleFields(
                root: $root,
                rule: $rule,
                rulePath: $rulePath,
                violations: $violations,
            );
        } elseif (!isset($rule['type'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'type'),
                reason: self::REASON_TYPE_MISSING,
                expected: self::EXPECTED_STRING,
                actualType: 'null',
            );
        }

        if (isset($rule['required']) && !\is_bool($rule['required'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'required'),
                reason: self::REASON_REQUIRED_TYPE,
                expected: self::EXPECTED_BOOL,
                actualType: self::actualType($rule['required']),
            );
        }

        if (isset($rule['additionalKeys']) && !\is_bool($rule['additionalKeys'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'additionalKeys'),
                reason: self::REASON_ADDITIONAL_KEYS_TYPE,
                expected: self::EXPECTED_BOOL,
                actualType: self::actualType($rule['additionalKeys']),
            );
        }

        if (isset($rule['type'])) {
            if (!\is_string($rule['type'])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'type'),
                    reason: self::REASON_TYPE,
                    expected: self::EXPECTED_STRING,
                    actualType: self::actualType($rule['type']),
                );
            } elseif (!isset(self::SUPPORTED_TYPES[$rule['type']])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'type'),
                    reason: self::REASON_TYPE_UNSUPPORTED,
                    expected: 'supported-type',
                    actualType: self::actualType($rule['type']),
                );
            }
        }

        if (isset($rule['min']) && !\is_int($rule['min'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'min'),
                reason: self::REASON_MIN_TYPE,
                expected: self::EXPECTED_INT,
                actualType: self::actualType($rule['min']),
            );
        }

        if (isset($rule['max']) && !\is_int($rule['max'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'max'),
                reason: self::REASON_MAX_TYPE,
                expected: self::EXPECTED_INT,
                actualType: self::actualType($rule['max']),
            );
        }

        if (isset($rule['allowedValues'])) {
            if (!\is_array($rule['allowedValues']) || !\array_is_list($rule['allowedValues'])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'allowedValues'),
                    reason: self::REASON_ALLOWED_VALUES,
                    expected: self::EXPECTED_LIST,
                    actualType: self::actualType($rule['allowedValues']),
                );
            }
        }

        if (isset($rule['items'])) {
            if (!\is_array($rule['items']) || (\array_is_list($rule['items']) && $rule['items'] !== [])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'items'),
                    reason: self::REASON_ITEMS_TYPE,
                    expected: self::EXPECTED_MAP,
                    actualType: self::actualType($rule['items']),
                );
            } else {
                self::validateRuleShape(
                    root: $root,
                    rule: $rule['items'],
                    rulePath: self::appendPath($rulePath, 'items'),
                    topLevel: false,
                    violations: $violations,
                );
            }
        }

        if (isset($rule['keys'])) {
            if (!\is_array($rule['keys']) || (\array_is_list($rule['keys']) && $rule['keys'] !== [])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'keys'),
                    reason: self::REASON_KEYS_TYPE,
                    expected: self::EXPECTED_MAP,
                    actualType: self::actualType($rule['keys']),
                );

                return;
            }

            foreach ($rule['keys'] as $key => $childRule) {
                if (!\is_array($childRule) || (\array_is_list($childRule) && $childRule !== [])) {
                    $violations[] = self::violation(
                        root: $root,
                        path: self::appendPath(self::appendPath($rulePath, 'keys'), $key),
                        reason: self::REASON_RULE_NOT_MAP,
                        expected: self::EXPECTED_MAP,
                        actualType: self::actualType($childRule),
                    );

                    continue;
                }

                self::validateRuleShape(
                    root: $root,
                    rule: $childRule,
                    rulePath: self::appendPath(self::appendPath($rulePath, 'keys'), $key),
                    topLevel: false,
                    violations: $violations,
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $rule
     * @param list<ConfigValidationViolation> $violations
     */
    private static function validateTopLevelRuleFields(
        string $root,
        array $rule,
        string $rulePath,
        array &$violations,
    ): void {
        if (!isset($rule['configRoot'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'configRoot'),
                reason: self::REASON_CONFIG_ROOT_MISSING,
                expected: self::EXPECTED_PRESENT,
                actualType: 'null',
            );
        } elseif (!\is_string($rule['configRoot'])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'configRoot'),
                reason: self::REASON_CONFIG_ROOT_TYPE,
                expected: self::EXPECTED_STRING,
                actualType: self::actualType($rule['configRoot']),
            );
        } elseif ($rule['configRoot'] !== $root) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($rulePath, 'configRoot'),
                reason: self::REASON_CONFIG_ROOT_MISMATCH,
                expected: 'ruleset-root',
                actualType: self::actualType($rule['configRoot']),
            );
        }

        if (isset($rule['schemaVersion'])) {
            if (!\is_int($rule['schemaVersion'])) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'schemaVersion'),
                    reason: self::REASON_SCHEMA_VERSION_TYPE,
                    expected: self::EXPECTED_INT,
                    actualType: self::actualType($rule['schemaVersion']),
                );
            } elseif ($rule['schemaVersion'] !== self::SUPPORTED_SCHEMA_VERSION) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($rulePath, 'schemaVersion'),
                    reason: self::REASON_SCHEMA_VERSION_UNSUPPORTED,
                    expected: 'supported-schema-version',
                    actualType: self::actualType($rule['schemaVersion']),
                );
            }
        }
    }

    /**
     * @param array<string,mixed> $rule
     * @param list<ConfigValidationViolation> $violations
     */
    private static function validateNode(
        mixed $value,
        string $root,
        string $path,
        array $rule,
        bool $topLevel,
        array &$violations,
    ): void {
        $type = $topLevel
            ? self::TYPE_MAP
            : ($rule['type'] ?? null);

        if (!\is_string($type) || !isset(self::SUPPORTED_TYPES[$type])) {
            $violations[] = self::violation(
                root: $root,
                path: self::appendPath($path, 'type'),
                reason: self::REASON_TYPE_UNSUPPORTED,
                expected: 'supported-type',
                actualType: self::actualType($type),
            );

            return;
        }

        if (!self::matchesType($value, $type)) {
            $violations[] = self::violation(
                root: $root,
                path: $path,
                reason: $type === self::TYPE_RELATIVE_SAFE_PATH
                    ? self::REASON_RELATIVE_SAFE_PATH
                    : self::REASON_TYPE,
                expected: self::expectedForType($type),
                actualType: self::actualType($value),
            );

            return;
        }

        if (isset($rule['allowedValues']) && !self::containsAllowedValue($rule['allowedValues'], $value)) {
            $violations[] = self::violation(
                root: $root,
                path: $path,
                reason: self::REASON_ALLOWED_VALUES,
                expected: self::EXPECTED_ALLOWED_VALUES,
                actualType: self::actualType($value),
            );
        }

        if (isset($rule['min']) && \is_int($rule['min']) && \is_int($value) && $value < $rule['min']) {
            $violations[] = self::violation(
                root: $root,
                path: $path,
                reason: self::REASON_MIN,
                expected: 'min',
                actualType: self::actualType($value),
            );
        }

        if (isset($rule['max']) && \is_int($rule['max']) && \is_int($value) && $value > $rule['max']) {
            $violations[] = self::violation(
                root: $root,
                path: $path,
                reason: self::REASON_MAX,
                expected: 'max',
                actualType: self::actualType($value),
            );
        }

        if ($type === self::TYPE_MAP) {
            self::validateMap(
                value: $value,
                root: $root,
                path: $path,
                rule: $rule,
                violations: $violations,
            );

            return;
        }

        if ($type === self::TYPE_LIST) {
            self::validateList(
                value: $value,
                root: $root,
                path: $path,
                rule: $rule,
                violations: $violations,
            );
        }
    }

    /**
     * @param array<array-key,mixed> $value
     * @param array<string,mixed> $rule
     * @param list<ConfigValidationViolation> $violations
     */
    private static function validateMap(
        array $value,
        string $root,
        string $path,
        array $rule,
        array &$violations,
    ): void {
        $keys = [];

        if (isset($rule['keys']) && \is_array($rule['keys']) && !\array_is_list($rule['keys'])) {
            $keys = $rule['keys'];
        }

        foreach ($keys as $key => $childRule) {
            if (!\is_array($childRule)) {
                continue;
            }

            $childPath = self::appendPath($path, $key);
            $required = ($childRule['required'] ?? false) === true;

            if (!\array_key_exists($key, $value)) {
                if ($required) {
                    $violations[] = self::violation(
                        root: $root,
                        path: $childPath,
                        reason: self::REASON_REQUIRED,
                        expected: self::EXPECTED_PRESENT,
                        actualType: 'null',
                    );
                }

                continue;
            }

            self::validateNode(
                value: $value[$key],
                root: $root,
                path: $childPath,
                rule: $childRule,
                topLevel: false,
                violations: $violations,
            );
        }

        $additionalKeys = ($rule['additionalKeys'] ?? true) === true;

        if ($additionalKeys) {
            return;
        }

        foreach ($value as $key => $_item) {
            if (!\is_string($key)) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($path, $key),
                    reason: self::REASON_UNKNOWN_KEY,
                    expected: 'declared-key',
                    actualType: self::actualType($key),
                );

                continue;
            }

            if (!\array_key_exists($key, $keys)) {
                $violations[] = self::violation(
                    root: $root,
                    path: self::appendPath($path, $key),
                    reason: self::REASON_UNKNOWN_KEY,
                    expected: 'declared-key',
                    actualType: self::actualType($key),
                );
            }
        }
    }

    /**
     * @param list<mixed> $value
     * @param array<string,mixed> $rule
     * @param list<ConfigValidationViolation> $violations
     */
    private static function validateList(
        array $value,
        string $root,
        string $path,
        array $rule,
        array &$violations,
    ): void {
        if (!isset($rule['items']) || !\is_array($rule['items'])) {
            return;
        }

        foreach ($value as $index => $item) {
            self::validateNode(
                value: $item,
                root: $root,
                path: self::appendPath($path, $index),
                rule: $rule['items'],
                topLevel: false,
                violations: $violations,
            );
        }
    }

    private static function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            self::TYPE_BOOL => \is_bool($value),
            self::TYPE_INT => \is_int($value),
            self::TYPE_LIST => \is_array($value) && \array_is_list($value),
            self::TYPE_MAP => \is_array($value) && ($value === [] || !\array_is_list($value)),
            self::TYPE_NON_EMPTY_STRING => \is_string($value) && $value !== '',
            self::TYPE_NON_EMPTY_STRING_NO_WS => \is_string($value)
                && $value !== ''
                && \preg_match('/\s/u', $value) !== 1,
            self::TYPE_RELATIVE_SAFE_PATH => self::isRelativeSafePath($value),
            self::TYPE_RESET_GROUP_ID => self::isResetGroupId($value),
            self::TYPE_STRING => \is_string($value),
            default => false,
        };
    }

    private static function expectedForType(string $type): string
    {
        return match ($type) {
            self::TYPE_BOOL => self::EXPECTED_BOOL,
            self::TYPE_INT => self::EXPECTED_INT,
            self::TYPE_LIST => self::EXPECTED_LIST,
            self::TYPE_MAP => self::EXPECTED_MAP,
            self::TYPE_NON_EMPTY_STRING => self::EXPECTED_NON_EMPTY_STRING,
            self::TYPE_NON_EMPTY_STRING_NO_WS => self::EXPECTED_NON_EMPTY_STRING_NO_WS,
            self::TYPE_RELATIVE_SAFE_PATH => self::EXPECTED_RELATIVE_SAFE_PATH,
            self::TYPE_RESET_GROUP_ID => self::EXPECTED_RESET_GROUP_ID,
            self::TYPE_STRING => self::EXPECTED_STRING,
            default => 'supported-type',
        };
    }

    private static function isRelativeSafePath(mixed $value): bool
    {
        if (!\is_string($value)) {
            return false;
        }

        if ($value === '') {
            return false;
        }

        if (\trim($value) !== $value || \preg_match('/\s/u', $value) === 1) {
            return false;
        }

        if (\str_contains($value, "\0") || \str_contains($value, "\r") || \str_contains($value, "\n")) {
            return false;
        }

        if (\str_starts_with($value, '/') || \str_starts_with($value, '\\')) {
            return false;
        }

        if (\preg_match('/\A[A-Za-z]:[\\\\\/]/', $value) === 1) {
            return false;
        }

        if (\str_contains($value, ':') || \str_contains($value, '://')) {
            return false;
        }

        if (\str_contains($value, '\\')) {
            return false;
        }

        if (\str_contains($value, '//')) {
            return false;
        }

        foreach (\explode('/', $value) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    private static function isResetGroupId(mixed $value): bool
    {
        return \is_string($value)
            && $value !== ''
            && \strlen($value) <= 64
            && \preg_match('/\A[a-z][a-z0-9_-]*\z/', $value) === 1;
    }

    /**
     * @param mixed $allowedValues
     */
    private static function containsAllowedValue(mixed $allowedValues, mixed $value): bool
    {
        if (!\is_array($allowedValues) || !\array_is_list($allowedValues)) {
            return false;
        }

        foreach ($allowedValues as $allowedValue) {
            if ($allowedValue === $value) {
                return true;
            }
        }

        return false;
    }

    private static function actualType(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (\is_bool($value)) {
            return 'bool';
        }

        if (\is_int($value)) {
            return 'int';
        }

        if (\is_string($value)) {
            return 'string';
        }

        if (\is_float($value)) {
            return 'float';
        }

        if (\is_array($value)) {
            return \array_is_list($value)
                ? 'list'
                : 'map';
        }

        if (\is_object($value)) {
            return 'object';
        }

        if (\is_resource($value)) {
            return 'resource';
        }

        return 'unknown';
    }

    private static function isValidRootName(string $root): bool
    {
        return \preg_match('/\A[a-z][a-z0-9_]*\z/', $root) === 1;
    }

    private static function appendPath(string $path, int|string $key): string
    {
        if (\is_int($key)) {
            if ($path === '') {
                return '[' . $key . ']';
            }

            return $path . '[' . $key . ']';
        }

        $segment = self::safePathSegment($key);

        if ($path === '') {
            return $segment;
        }

        return $path . '.' . $segment;
    }

    private static function safePathSegment(string $key): string
    {
        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_-]*\z/', $key) === 1) {
            return $key;
        }

        return '<key>';
    }

    private static function violation(
        string $root,
        string $path,
        string $reason,
        ?string $expected = null,
        ?string $actualType = null,
    ): ConfigValidationViolation {
        return new ConfigValidationViolation(
            root: $root,
            path: $path,
            reason: $reason,
            expected: $expected,
            actualType: $actualType,
        );
    }
}
