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

namespace Coretsia\Contracts\Config;

/**
 * Config validation port.
 *
 * Validation is contract-driven but kernel-implemented. Rulesets are
 * declarative data only and must not expose package-specific callable
 * validators or executable validation logic.
 */
interface ConfigValidatorInterface
{
    /**
     * Validates merged global config against loaded declarative rulesets.
     *
     * An empty ruleset list is valid and means there are no loaded contracts-level
     * config rules to apply.
     *
     * @param array<string,mixed> $config
     * @param list<ConfigRuleset> $rulesets
     */
    public function validate(array $config, array $rulesets): ConfigValidationResult;
}
