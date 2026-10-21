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

namespace Coretsia\Platform\Cli\Workflow;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Foundation\Serialization\JsonLikeNormalizationLimits;
use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Foundation\Serialization\StableJsonEncoder;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;

/**
 * Stateless deep validation of the dynamic cli.workflows.definitions map.
 *
 * One Foundation normalization pass owns recursive validation, aggregate
 * resource limits and canonical byte-order map-key sorting. All subsequent
 * checks are bounded, workflow-specific shape and string-policy checks.
 * Command existence, execution kind and descriptor-dependent argument/option
 * validation are deliberately left to WorkflowCatalog and CommandInputValidator.
 *
 * @internal
 */
final class WorkflowSchema
{
    private const string WORKFLOW_NAME_PATTERN = '/\A[a-z][a-z0-9-]*\z/D';
    private const string OPTION_NAME_PATTERN = '/\A[a-z][a-z0-9-]*\z/D';
    private const string UNSAFE_VALUE_PATTERN = '/[\p{Cc}\p{Zl}\p{Zp}]/u';

    /**
     * @param array<array-key, mixed> $definitions
     * @return array<string, WorkflowDefinition>
     */
    public function normalize(array $definitions): array
    {
        if ((\count($definitions) > 128) || ($definitions !== [] && \array_is_list($definitions))) {
            throw self::invalid();
        }

        try {
            $normalized = JsonLikeNormalizer::normalize(
                $definitions,
                'cli.workflows.definitions',
                new JsonLikeNormalizationLimits(
                    maxDepth: 8,
                    maxNodes: 131072,
                    maxStringBytes: 512,
                    maxTotalStringBytes: 8388608,
                ),
            );
        } catch (\Throwable) {
            throw self::invalid();
        }

        /** @var array<string, mixed> $normalized */
        $workflows = [];

        foreach ($normalized as $name => $definition) {
            if (
                !\is_string($name)
                || \strlen($name) > 64
                || \preg_match(self::WORKFLOW_NAME_PATTERN, $name) !== 1
                || !\is_array($definition)
                || \count($definition) !== 2
                || !\array_key_exists('summary', $definition)
                || !\array_key_exists('steps', $definition)
            ) {
                throw self::invalid();
            }

            $summary = $definition['summary'];
            if (
                !\is_string($summary)
                || $summary === ''
                || \trim($summary) !== $summary
                || \strlen($summary) > 256
                || \preg_match(self::UNSAFE_VALUE_PATTERN, $summary) !== 0
            ) {
                throw self::invalid();
            }

            try {
                StableJsonEncoder::encodeStable($summary);
            } catch (\Throwable) {
                throw self::invalid();
            }

            $rawSteps = $definition['steps'];
            if (
                !\is_array($rawSteps)
                || !\array_is_list($rawSteps)
                || $rawSteps === []
                || \count($rawSteps) > 64
            ) {
                throw self::invalid();
            }

            $steps = [];
            foreach ($rawSteps as $rawStep) {
                if (
                    !\is_array($rawStep)
                    || \count($rawStep) !== 3
                    || !\array_key_exists('command', $rawStep)
                    || !\array_key_exists('arguments', $rawStep)
                    || !\array_key_exists('options', $rawStep)
                ) {
                    throw self::invalid();
                }

                $command = $rawStep['command'];
                if (
                    !\is_string($command)
                    || \strlen($command) > 512
                    || \preg_match('~' . CommandInterface::COMMAND_NAME_PATTERN . '~D', $command) !== 1
                ) {
                    throw self::invalid();
                }

                $arguments = $rawStep['arguments'];
                if (
                    !\is_array($arguments)
                    || !\array_is_list($arguments)
                    || \count($arguments) > 32
                ) {
                    throw self::invalid();
                }

                foreach ($arguments as $argument) {
                    if (!self::isSafeValue($argument)) {
                        throw self::invalid();
                    }
                }

                $options = $rawStep['options'];
                if (
                    !\is_array($options)
                    || \count($options) > 32
                    || ($options !== [] && \array_is_list($options))
                ) {
                    throw self::invalid();
                }

                foreach ($options as $optionName => $value) {
                    if (
                        !\is_string($optionName)
                        || \strlen($optionName) > 512
                        || \preg_match(self::OPTION_NAME_PATTERN, $optionName) !== 1
                        || $optionName === 'format'
                        || $optionName === 'color'
                    ) {
                        throw self::invalid();
                    }

                    if ($value === true || self::isSafeValue($value)) {
                        continue;
                    }

                    if (
                        !\is_array($value)
                        || !\array_is_list($value)
                        || $value === []
                        || \count($value) > 32
                    ) {
                        throw self::invalid();
                    }

                    foreach ($value as $item) {
                        if (!self::isSafeValue($item) || $item === '') {
                            throw self::invalid();
                        }
                    }
                }

                /** @var list<string> $arguments */
                /** @var array<string, string|true|list<string>> $options */
                $steps[] = new WorkflowStep($command, $arguments, $options);
            }

            $workflows[$name] = new WorkflowDefinition($name, $summary, $steps);
        }

        return $workflows;
    }

    private static function isSafeValue(mixed $value): bool
    {
        return \is_string($value)
            && \strlen($value) <= 512
            && \preg_match(self::UNSAFE_VALUE_PATTERN, $value) === 0;
    }

    private static function invalid(): WorkflowDefinitionInvalidException
    {
        return WorkflowDefinitionInvalidException::withReason(
            WorkflowDefinitionInvalidException::REASON_DEFINITION_INVALID,
        );
    }
}
