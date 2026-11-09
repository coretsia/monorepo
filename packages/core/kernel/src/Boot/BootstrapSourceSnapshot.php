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

namespace Coretsia\Kernel\Boot;

use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Env\EnvRepositoryInterface;
use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Immutable, resolution-local Bootstrap Phase A source snapshot.
 *
 * The snapshot retains the complete environment repository for Phase B and
 * project-wide bootstrap values for independent target-local resolution.
 * It does not choose an AppTarget or construct an installation identity.
 *
 * @internal
 */
final readonly class BootstrapSourceSnapshot
{
    /** @var array<string, mixed> */
    private array $overrides;

    /** @var array<string, ConfigValueSource> */
    private array $sourceProvenance;

    /**
     * @param array<string, mixed> $overrides Already validated, complete bootstrap overrides.
     * @param array<string, ConfigValueSource> $sourceProvenance Safe logical-key provenance.
     */
    public function __construct(
        private string $applicationRoot,
        private string $appEnv,
        private BootstrapEnvSourcePolicy $envSourcePolicy,
        array $overrides,
        array $sourceProvenance,
        private EnvRepositoryInterface $envRepository,
    ) {
        if (!self::isSafeString($this->applicationRoot)) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APPLICATION_ROOT);
        }

        if (!self::isSafeString($this->appEnv)) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        $canonicalOverrides = BootstrapOverridesLoader::completeModuleOverrides(
            BootstrapOverridesLoader::normalizePartialOverrides($overrides),
        );

        if (
            $canonicalOverrides !== $overrides
            || ($overrides['appEnv'] ?? null) !== $this->appEnv
        ) {
            throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
        }

        foreach ($sourceProvenance as $logicalKey => $source) {
            if (
                !\is_string($logicalKey)
                || $logicalKey === ''
                || !$source instanceof ConfigValueSource
            ) {
                throw BootstrapException::withReason(BootstrapException::REASON_OVERRIDES_INVALID);
            }
        }

        $this->overrides = $overrides;
        $this->sourceProvenance = $sourceProvenance;
    }

    public function applicationRoot(): string
    {
        return $this->applicationRoot;
    }

    public function assertApplicationRoot(string $applicationRoot): void
    {
        if ($this->applicationRoot !== $applicationRoot) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APPLICATION_ROOT);
        }
    }

    public function appEnv(): string
    {
        return $this->appEnv;
    }

    public function envSourcePolicy(): BootstrapEnvSourcePolicy
    {
        return $this->envSourcePolicy;
    }

    /** @return array<string, mixed> */
    public function overrides(): array
    {
        return $this->overrides;
    }

    /** @return non-empty-list<string>|null */
    public function activeTargets(): ?array
    {
        return $this->overrides['activeTargets'] ?? null;
    }

    /** @return array<string, ConfigValueSource> */
    public function sourceProvenance(): array
    {
        return $this->sourceProvenance;
    }

    public function envRepository(): EnvRepositoryInterface
    {
        return $this->envRepository;
    }

    /**
     * Returns only installation-relevant normalized bootstrap values for an
     * explicitly supplied application set. The caller owns final preset and
     * ModulePlan resolution; no project-wide identity is generated here.
     *
     * @param non-empty-list<string> $targets
     * @return array<string, mixed>
     */
    public function installationOverridesForTargets(array $targets): array
    {
        if (!\array_is_list($targets) || $targets === []) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APP_TARGET);
        }

        $selected = [];

        foreach ($targets as $target) {
            if (!\is_string($target) || !AppTarget::isKnown($target) || isset($selected[$target])) {
                throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APP_TARGET);
            }

            $selected[$target] = true;
        }

        \ksort($selected, \SORT_STRING);
        $presets = [];
        $moduleOverrides = [];

        foreach (\array_keys($selected) as $target) {
            $preset = $this->overrides['presets'][$target]
                ?? $this->overrides['preset']
                ?? null;

            if ($preset !== null) {
                $presets[$target] = $preset;
            }

            if (isset($this->overrides['moduleOverrides'][$target])) {
                $entry = $this->overrides['moduleOverrides'][$target];
                $include = $entry['include'] ?? [];
                $exclude = $entry['exclude'] ?? [];

                if ($include !== [] || $exclude !== []) {
                    \sort($include, \SORT_STRING);
                    \sort($exclude, \SORT_STRING);

                    $moduleOverrides[$target] = [
                        'include' => $include,
                        'exclude' => $exclude,
                    ];
                }
            }
        }

        return [
            'appEnv' => $this->appEnv,
            'envSourcePolicy' => $this->envSourcePolicy->value,
            'presets' => $presets,
            'moduleOverrides' => $moduleOverrides,
        ];
    }

    private static function isSafeString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }
}
