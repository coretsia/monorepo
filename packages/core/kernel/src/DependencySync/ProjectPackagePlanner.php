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

namespace Coretsia\Kernel\DependencySync;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\Boot\BootstrapConfigResolver;
use Coretsia\Kernel\Boot\BootstrapInput;
use Coretsia\Kernel\Boot\Exception\BootstrapException;
use Coretsia\Kernel\DependencySync\Catalog\ReleaseInstallationCatalogLoader;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\Module\Exception\CanonicalPresetOverrideException;
use Coretsia\Kernel\Module\Exception\InvalidModuleSelectionException;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Exception\ModuleConflictException;
use Coretsia\Kernel\Module\Exception\ModuleCycleDetectedException;
use Coretsia\Kernel\Module\Exception\ModuleDiscoverySourceUnsupportedException;
use Coretsia\Kernel\Module\Exception\ModuleRequiredMissingException;
use Coretsia\Kernel\Module\KernelModule;
use Coretsia\Kernel\Module\ModuleGraphResolver;
use Coretsia\Kernel\Module\ModuleIdSetNormalizer;
use Coretsia\Kernel\Module\ModulePlanEntry;
use Coretsia\Kernel\Module\ModuleResolutionOrchestrator;

/** @internal */
final readonly class ProjectPackagePlanner
{
    /** @param array<string, mixed> $kernelConfig */
    public function __construct(
        private BootstrapConfigResolver $bootstrapConfigResolver,
        private ModuleResolutionOrchestrator $moduleResolutionOrchestrator,
        private ModuleGraphResolver $moduleGraphResolver,
        private ModuleIdSetNormalizer $moduleIdSetNormalizer,
        private ReleaseInstallationCatalogLoader $catalogLoader,
        private array $kernelConfig,
    ) {
    }

    public function plan(
        string $projectRoot,
        ProjectInstallationIntent $intent,
    ): ProjectPackagePlan {
        $catalog = null;
        $applications = [];
        $effectivePresetsByTarget = [];
        $enabledModuleIdsByTarget = [];
        $excludedModuleIdsByTarget = [];
        $union = [];
        $kernelModuleId = ModuleId::fromString(KernelModule::MODULE_ID);

        foreach ($intent->applications()->targets() as $target) {
            $targetName = $target->value;
            $applications[] = $targetName;
            $fixedPreset = $intent->fixedPresetByTarget()[$targetName] ?? null;

            try {
                $planningInput = new BootstrapInput(
                    applicationRoot: $projectRoot,
                    appTarget: $target,
                    preset: $fixedPreset,
                );
                $planningConfig = $this->bootstrapConfigResolver->resolve(
                    $planningInput,
                    $this->kernelConfig,
                );
                $selection = $this->moduleResolutionOrchestrator->resolveSelection($planningConfig);
            } catch (BootstrapException $exception) {
                throw $this->translateBootstrapException($exception);
            } catch (
                ModePresetNotFoundException
                |ModePresetInvalidException
                |CanonicalPresetOverrideException
                |InvalidModuleSelectionException
                |ModuleDiscoverySourceUnsupportedException $exception
            ) {
                throw self::presetPolicyInvalid($exception);
            }

            $catalog ??= $this->catalogLoader->load();

            try {
                $graph = $this->moduleGraphResolver->resolveEntries(
                    $catalog->entries(),
                    $selection,
                );
            } catch (ModuleRequiredMissingException) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_MODULE_UNKNOWN);
            } catch (ModuleConflictException $exception) {
                throw DependencySyncException::forCode(
                    $exception->reason() === ModuleConflictException::REASON_DEPENDENCY_EXCLUDED
                        ? DependencySyncErrorCodes::EXCLUDED_REQUIRED_DEPENDENCY
                        : DependencySyncErrorCodes::MODULE_GRAPH_CONFLICT,
                    self::sourceContext($exception),
                );
            } catch (ModuleCycleDetectedException) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_INVALID);
            }

            $enabled = self::moduleIdsToStrings($graph['enabled']);

            if (!\in_array($kernelModuleId->value(), $enabled, true)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::PRESET_POLICY_INVALID);
            }

            $excluded = self::moduleIdsToStrings($selection->excluded());
            $effectivePresetsByTarget[$targetName] = $planningConfig->preset();
            $enabledModuleIdsByTarget[$targetName] = $enabled;
            $excludedModuleIdsByTarget[$targetName] = $excluded;

            foreach ($graph['enabled'] as $moduleId) {
                $union[] = $moduleId;
            }
        }

        $unionIds = $this->moduleIdSetNormalizer->normalize($union);
        $unionModuleIds = self::moduleIdsToStrings($unionIds);
        $expectedModuleMetadataById = [];
        $desiredRootRequirements = [];

        foreach ($unionIds as $moduleId) {
            $entry = $catalog->entry($moduleId);

            if (!$entry instanceof ModulePlanEntry) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::CATALOG_MODULE_UNKNOWN);
            }

            $expectedModuleMetadataById[$moduleId->value()] = [
                'composerName' => $entry->composerName(),
                'requires' => self::moduleIdsToStrings($entry->requires()),
                'conflicts' => self::moduleIdsToStrings($entry->conflicts()),
            ];
            $desiredRootRequirements[$entry->composerName()] = $catalog->publicConstraint();
        }

        \ksort($expectedModuleMetadataById, \SORT_STRING);
        \ksort($desiredRootRequirements, \SORT_STRING);

        return new ProjectPackagePlan(
            $applications,
            $effectivePresetsByTarget,
            $intent->fixedPresetByTarget(),
            $enabledModuleIdsByTarget,
            $excludedModuleIdsByTarget,
            $unionModuleIds,
            $expectedModuleMetadataById,
            $desiredRootRequirements,
        );
    }

    private function translateBootstrapException(BootstrapException $exception): DependencySyncException
    {
        $code = match ($exception->reason()) {
            BootstrapException::REASON_INVALID_APPLICATION_ROOT => DependencySyncErrorCodes::PROJECT_ROOT_INVALID,
            BootstrapException::REASON_INVALID_APP_TARGET => DependencySyncErrorCodes::APPLICATION_SET_INVALID,
            default => DependencySyncErrorCodes::PRESET_POLICY_INVALID,
        };

        return DependencySyncException::forCode(
            $code,
            self::sourceContext($exception),
        );
    }

    private static function presetPolicyInvalid(object $exception): DependencySyncException
    {
        return DependencySyncException::forCode(
            DependencySyncErrorCodes::PRESET_POLICY_INVALID,
            self::sourceContext($exception),
        );
    }

    /** @return array<string, non-empty-string> */
    private static function sourceContext(object $exception): array
    {
        if (!\method_exists($exception, 'errorCode') || !\method_exists($exception, 'reason')) {
            return [];
        }

        $errorCode = $exception->errorCode();
        $reason = $exception->reason();

        if (!\is_string($errorCode) || !\is_string($reason) || $errorCode === '' || $reason === '') {
            return [];
        }

        return [
            'sourceErrorCode' => $errorCode,
            'sourceReason' => $reason,
        ];
    }

    /** @param list<ModuleId> $moduleIds @return list<string> */
    private static function moduleIdsToStrings(array $moduleIds): array
    {
        $values = [];

        foreach ($moduleIds as $moduleId) {
            $values[] = $moduleId->value();
        }

        return $values;
    }
}
