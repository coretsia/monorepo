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

namespace Coretsia\Kernel\DependencySync\Composer;

use Coretsia\Contracts\Module\ModuleId;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncErrorCodes;
use Coretsia\Kernel\DependencySync\Exception\DependencySyncException;
use Coretsia\Kernel\DependencySync\ProjectPackagePlan;
use Coretsia\Kernel\Module\ModulePlanEntry;

/** @internal */
final readonly class ComposerRootReconciler
{
    /**
     * @return array{
     *     candidateDocument: \stdClass,
     *     manifestChanged: bool,
     *     managedRequireWrites: array<string, non-empty-string>,
     *     managedRequireRemovals: list<string>,
     *     preservedSatisfiers: array<string, non-empty-string>,
     *     protectedThirdPartyRoots: array<string, 'require'|'require-dev'>
     * }
     */
    public function reconcile(
        \stdClass $originalDocument,
        \stdClass $candidateDocument,
        ProjectPackagePlan $plan,
    ): array {
        $originalRequire = self::requireMap($originalDocument, 'require', true);
        $requireDev = self::requireMap($originalDocument, 'require-dev', false);
        $marker = self::readMarker($originalDocument);
        $managed = $marker['managedRequire'];
        $lastApplied = $marker['lastAppliedRequire'];
        $managedMap = \array_fill_keys($managed, true);

        foreach ($managed as $packageName) {
            if (!\array_key_exists($packageName, $originalRequire)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
            }

            if (
                !\array_key_exists($packageName, $lastApplied)
                || $originalRequire[$packageName] !== $lastApplied[$packageName]
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_CONFLICT);
            }
        }

        $desired = $plan->desiredRootRequirements();

        foreach ($desired as $packageName => $_constraint) {
            if (
                !\array_key_exists($packageName, $originalRequire)
                && \array_key_exists($packageName, $requireDev)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_UNSUPPORTED_POLICY);
            }
        }

        $protected = self::protectedThirdPartyRoots($originalRequire, $requireDev);
        $projectOwned = [];

        foreach ($originalRequire as $packageName => $constraint) {
            if (!isset($managedMap[$packageName])) {
                $projectOwned[$packageName] = $constraint;
            }
        }

        $candidateRequire = self::requireObject($candidateDocument);
        $nextManaged = [];
        $writes = [];
        $removals = [];
        $preserved = [];
        $changed = false;

        foreach ($managed as $packageName) {
            if (\array_key_exists($packageName, $desired)) {
                continue;
            }

            unset($candidateRequire->{$packageName});
            $removals[] = $packageName;
            $changed = true;
        }

        foreach ($desired as $packageName => $constraint) {
            if (\array_key_exists($packageName, $projectOwned)) {
                $preserved[$packageName] = $projectOwned[$packageName];
                continue;
            }

            $nextManaged[$packageName] = $constraint;
            $current = $originalRequire[$packageName] ?? null;

            if ($current !== $constraint) {
                $candidateRequire->{$packageName} = $constraint;
                $writes[$packageName] = $constraint;
                $changed = true;
            }
        }

        \ksort($nextManaged, \SORT_STRING);
        \ksort($writes, \SORT_STRING);
        \sort($removals, \SORT_STRING);
        \ksort($preserved, \SORT_STRING);

        if ($nextManaged !== $lastApplied || \array_keys($nextManaged) !== $managed) {
            self::writeMarker($candidateDocument, $nextManaged);
            $changed = true;
        }

        return [
            'candidateDocument' => $candidateDocument,
            'manifestChanged' => $changed,
            'managedRequireWrites' => $writes,
            'managedRequireRemovals' => $removals,
            'preservedSatisfiers' => $preserved,
            'protectedThirdPartyRoots' => $protected,
        ];
    }

    /** @return array<string, non-empty-string> */
    private static function requireMap(
        \stdClass $document,
        string $property,
        bool $required,
    ): array {
        if (!\property_exists($document, $property)) {
            if ($required) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
            }

            return [];
        }

        $value = $document->{$property};

        if (!$value instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
        }

        $map = [];

        foreach (\get_object_vars($value) as $name => $constraint) {
            if (\str_contains($name, '/') && !self::isCanonicalComposerPackageName($name)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
            }

            if (!\is_string($constraint) || !self::isSafeSingleLine($constraint)) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
            }

            $map[$name] = $constraint;
        }

        return $map;
    }

    private static function requireObject(\stdClass $document): \stdClass
    {
        if (!\property_exists($document, 'require') || !$document->require instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
        }

        return $document->require;
    }

    /**
     * @return array{
     *     managedRequire: list<string>,
     *     lastAppliedRequire: array<string, non-empty-string>
     * }
     */
    private static function readMarker(\stdClass $document): array
    {
        if (!\property_exists($document, 'extra')) {
            return ['managedRequire' => [], 'lastAppliedRequire' => []];
        }

        if (!$document->extra instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        if (!\property_exists($document->extra, 'coretsia')) {
            return ['managedRequire' => [], 'lastAppliedRequire' => []];
        }

        if (!$document->extra->coretsia instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        if (!\property_exists($document->extra->coretsia, 'dependencySync')) {
            return [
                'managedRequire' => [],
                'lastAppliedRequire' => [],
            ];
        }

        $marker = $document->extra->coretsia->dependencySync;

        if (
            !$marker instanceof \stdClass
            || !self::hasExactObjectKeys(
                $marker,
                [
                    'schemaVersion',
                    'managedRequire',
                    'lastAppliedRequire',
                ],
            )
            || $marker->schemaVersion !== 1
            || !\is_array($marker->managedRequire)
            || !\array_is_list($marker->managedRequire)
            || !$marker->lastAppliedRequire instanceof \stdClass
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        $managed = [];
        $seen = [];
        $previous = null;

        foreach ($marker->managedRequire as $packageName) {
            if (
                !\is_string($packageName)
                || !\str_starts_with($packageName, 'coretsia/')
                || isset($seen[$packageName])
                || ($previous !== null && \strcmp($previous, $packageName) >= 0)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
            }

            $seen[$packageName] = true;
            $managed[] = $packageName;
            $previous = $packageName;
        }

        $lastApplied = [];

        foreach (\get_object_vars($marker->lastAppliedRequire) as $packageName => $constraint) {
            if (
                !isset($seen[$packageName])
                || !\is_string($constraint)
                || !self::isSafeSingleLine($constraint)
            ) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
            }

            $lastApplied[$packageName] = $constraint;
        }

        \ksort($lastApplied, \SORT_STRING);

        if (\array_keys($lastApplied) !== $managed) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        return [
            'managedRequire' => $managed,
            'lastAppliedRequire' => $lastApplied,
        ];
    }

    /** @param array<string, non-empty-string> $managed */
    private static function writeMarker(\stdClass $document, array $managed): void
    {
        if (!\property_exists($document, 'extra')) {
            $document->extra = new \stdClass();
        }

        if (!$document->extra instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        if (!\property_exists($document->extra, 'coretsia')) {
            $document->extra->coretsia = new \stdClass();
        }

        if (!$document->extra->coretsia instanceof \stdClass) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::MANAGED_STATE_INVALID);
        }

        if ($managed === []) {
            unset($document->extra->coretsia->dependencySync);
            return;
        }

        $lastApplied = new \stdClass();

        foreach ($managed as $packageName => $constraint) {
            $lastApplied->{$packageName} = $constraint;
        }

        $marker = new \stdClass();
        $marker->schemaVersion = 1;
        $marker->managedRequire = \array_keys($managed);
        $marker->lastAppliedRequire = $lastApplied;
        $document->extra->coretsia->dependencySync = $marker;
    }

    /**
     * @param array<string, non-empty-string> $require
     * @param array<string, non-empty-string> $requireDev
     *
     * @return array<string, 'require'|'require-dev'>
     */
    private static function protectedThirdPartyRoots(array $require, array $requireDev): array
    {
        $protected = [];

        foreach ($requireDev as $packageName => $_constraint) {
            if (self::isProtectedThirdPartyPackage($packageName)) {
                $protected[$packageName] = 'require-dev';
            }
        }

        foreach ($require as $packageName => $_constraint) {
            if (self::isProtectedThirdPartyPackage($packageName)) {
                $protected[$packageName] = 'require';
            }
        }

        \ksort($protected, \SORT_STRING);

        return $protected;
    }

    private static function isProtectedThirdPartyPackage(string $packageName): bool
    {
        return \str_contains($packageName, '/') && !\str_starts_with($packageName, 'coretsia/');
    }

    private static function isSafeSingleLine(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && \preg_match('/[\x00-\x1F\x7F]/', $value) === 0;
    }

    private static function isCanonicalComposerPackageName(string $packageName): bool
    {
        try {
            new ModulePlanEntry(
                ModuleId::fromString('core.kernel'),
                $packageName,
            );
        } catch (\InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /** @param list<string> $keys */
    private static function hasExactObjectKeys(
        \stdClass $object,
        array $keys,
    ): bool {
        $actual = \array_keys(\get_object_vars($object));
        \sort($actual, \SORT_STRING);
        \sort($keys, \SORT_STRING);

        return $actual === $keys;
    }
}
