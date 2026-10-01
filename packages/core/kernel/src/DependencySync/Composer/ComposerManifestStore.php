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
use Coretsia\Kernel\Module\ModulePlanEntry;

/** @internal */
final readonly class ComposerManifestStore
{
    private const int MAX_COMPOSER_JSON_BYTES = 1_048_576;
    private const int MAX_COMPOSER_LOCK_BYTES = 33_554_432;
    private const int MAX_JSON_DEPTH = 512;

    private const string JSON_DOCUMENT_MANIFEST = 'manifest';
    private const string JSON_DOCUMENT_LOCK = 'lock';

    /**
     * @return array{
     *     bytes: string,
     *     document: \stdClass,
     *     identity: non-empty-string,
     * }
     */
    public function readManifest(string $projectRoot): array
    {
        return self::readRequiredDocument(
            $projectRoot . \DIRECTORY_SEPARATOR . 'composer.json',
            self::MAX_COMPOSER_JSON_BYTES,
            self::JSON_DOCUMENT_MANIFEST,
        );
    }

    /** @return array{bytes: string, document: \stdClass, identity: non-empty-string}|null */
    public function readLockState(string $projectRoot): ?array
    {
        $path = $projectRoot . \DIRECTORY_SEPARATOR . 'composer.lock';

        if (!\file_exists($path) && !\is_link($path)) {
            return null;
        }

        return self::readRequiredDocument(
            $path,
            self::MAX_COMPOSER_LOCK_BYTES,
            self::JSON_DOCUMENT_LOCK,
        );
    }

    public function encodeManifest(\stdClass $document): string
    {
        try {
            $json = \json_encode(
                $document,
                \JSON_THROW_ON_ERROR
                | \JSON_UNESCAPED_SLASHES
                | \JSON_UNESCAPED_UNICODE
                | \JSON_PRESERVE_ZERO_FRACTION
                | \JSON_PRETTY_PRINT,
                self::MAX_JSON_DEPTH,
            );
        } catch (\JsonException) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID);
        }

        $json = \str_replace(["\r\n", "\r"], "\n", $json);
        $lines = \explode("\n", $json);

        foreach ($lines as &$line) {
            if (\preg_match('/\A( +)/', $line, $matches) === 1) {
                $line = \str_repeat(' ', intdiv(\strlen($matches[1]), 2)) . \substr($line, \strlen($matches[1]));
            }
        }
        unset($line);

        return \rtrim(\implode("\n", $lines), "\n") . "\n";
    }

    public function copyManifestDocument(\stdClass $document): \stdClass
    {
        return self::decodeRootObject(
            $this->encodeManifest($document),
            self::JSON_DOCUMENT_MANIFEST,
        );
    }

    public function assertProjectStateUnchanged(
        string $projectRoot,
        string $expectedManifestIdentity,
        ?string $expectedLockIdentity,
    ): void {
        try {
            $manifest = $this->readManifest($projectRoot);
            $lock = $this->readLockState($projectRoot);
        } catch (DependencySyncException) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }

        $lockIdentity = $lock['identity'] ?? null;

        if (
            $manifest['identity'] !== $expectedManifestIdentity
            || $lockIdentity !== $expectedLockIdentity
        ) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::PROJECT_STATE_CHANGED);
        }
    }

    public function publishManifestCandidate(
        string $projectRoot,
        string $candidateBytes,
        string $expectedManifestIdentity,
        ?string $expectedLockIdentity,
    ): void {
        $this->assertProjectStateUnchanged(
            $projectRoot,
            $expectedManifestIdentity,
            $expectedLockIdentity,
        );

        $path = $projectRoot . \DIRECTORY_SEPARATOR . 'composer.json';
        $mode = @\fileperms($path);

        if (!\is_int($mode)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED);
        }

        $permission = $mode & 0777;

        try {
            self::atomicReplace($path, $candidateBytes, $permission);
        } catch (\Throwable) {
            try {
                $this->assertProjectStateUnchanged(
                    $projectRoot,
                    $expectedManifestIdentity,
                    $expectedLockIdentity,
                );
            } catch (\Throwable) {
                throw DependencySyncException::forCode(
                    DependencySyncErrorCodes::RECOVERY_REQUIRED,
                    ['causeCode' => DependencySyncErrorCodes::PROJECT_STATE_CHANGED],
                );
            }

            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED);
        }
    }

    public function writeRecoverySnapshot(
        string $projectRoot,
        string $recoveryReceiptId,
        string $originalManifestBytes,
        ?string $originalLockBytes,
    ): void {
        if (\preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $recoveryReceiptId) !== 1) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED);
        }

        $var = $projectRoot . \DIRECTORY_SEPARATOR . 'var';
        $dependencySync = $var . \DIRECTORY_SEPARATOR . 'dependency-sync';
        $recovery = $dependencySync . \DIRECTORY_SEPARATOR . 'recovery';
        $receipt = $recovery . \DIRECTORY_SEPARATOR . $recoveryReceiptId;

        try {
            self::ensureRecoveryDirectory($var, false);
            self::ensureRecoveryDirectory($dependencySync, true);
            self::ensureRecoveryDirectory($recovery, true);

            if (\file_exists($receipt) || \is_link($receipt) || !@\mkdir($receipt, 0700)) {
                throw new \RuntimeException('recovery-receipt-create-failed');
            }

            if (\DIRECTORY_SEPARATOR !== '\\' && !@\chmod($receipt, 0700)) {
                throw new \RuntimeException('recovery-receipt-permission-failed');
            }

            if (!self::isNonSymlinkDirectory($receipt)) {
                throw new \RuntimeException('recovery-receipt-invalid');
            }

            self::writeRecoveryFile(
                $receipt . \DIRECTORY_SEPARATOR . 'composer.json.original',
                $originalManifestBytes,
            );

            if ($originalLockBytes !== null) {
                self::writeRecoveryFile(
                    $receipt . \DIRECTORY_SEPARATOR . 'composer.lock.original',
                    $originalLockBytes,
                );
            }
        } catch (\RuntimeException) {
            self::cleanupIncompleteReceipt($receipt);
            throw DependencySyncException::forCode(DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED);
        }
    }

    public function discardRecoverySnapshot(
        string $projectRoot,
        string $recoveryReceiptId,
    ): void {
        if (\preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $recoveryReceiptId) !== 1) {
            self::recoveryFailure($recoveryReceiptId);
        }

        $var = $projectRoot . \DIRECTORY_SEPARATOR . 'var';
        $dependencySync = $var . \DIRECTORY_SEPARATOR . 'dependency-sync';
        $recovery = $dependencySync . \DIRECTORY_SEPARATOR . 'recovery';
        $receipt = $recovery . \DIRECTORY_SEPARATOR . $recoveryReceiptId;

        try {
            self::ensureRecoveryDirectory($var, false);
            self::ensureRecoveryDirectory($dependencySync, false);
            self::ensureRecoveryDirectory($recovery, false);

            if (!\is_dir($receipt) || \is_link($receipt)) {
                throw new \RuntimeException('recovery-receipt-invalid');
            }

            $entries = @\scandir($receipt);

            if (!\is_array($entries)) {
                throw new \RuntimeException('recovery-receipt-read-failed');
            }

            $entries = \array_values(\array_diff($entries, ['.', '..']));
            \sort($entries, \SORT_STRING);
            $allowed = [
                ['composer.json.original'],
                ['composer.json.original', 'composer.lock.original'],
            ];

            if (!\in_array($entries, $allowed, true)) {
                throw new \RuntimeException('recovery-receipt-entries-invalid');
            }

            foreach ($entries as $entry) {
                $path = $receipt . \DIRECTORY_SEPARATOR . $entry;

                if (!\is_file($path) || \is_link($path) || !@\unlink($path)) {
                    throw new \RuntimeException('recovery-receipt-delete-failed');
                }
            }

            if (!@\rmdir($receipt)) {
                throw new \RuntimeException('recovery-receipt-delete-failed');
            }
        } catch (\RuntimeException) {
            self::recoveryFailure($recoveryReceiptId);
        }
    }

    public function restorePreComposerState(
        string $projectRoot,
        string $originalManifestBytes,
        string $expectedCandidateIdentity,
        ?string $expectedLockIdentity,
    ): void {
        $this->assertProjectStateUnchanged(
            $projectRoot,
            $expectedCandidateIdentity,
            $expectedLockIdentity,
        );

        $path = $projectRoot . \DIRECTORY_SEPARATOR . 'composer.json';
        $mode = @\fileperms($path);

        if (!\is_int($mode)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED);
        }

        $permission = $mode & 0777;

        try {
            self::atomicReplace($path, $originalManifestBytes, $permission);
        } catch (\Throwable) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_MANIFEST_WRITE_FAILED);
        }
    }

    /**
     * @return array<string, array{
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     * }>
     */
    public function runtimeLockPackages(\stdClass $lockDocument): array
    {
        return self::lockPackageCollection($lockDocument, 'packages', true);
    }

    /**
     * @return array<string, array{
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     * }>
     */
    public function allLockPackages(\stdClass $lockDocument): array
    {
        $runtime = self::lockPackageCollection($lockDocument, 'packages', true);
        $development = self::lockPackageCollection($lockDocument, 'packages-dev', false);

        foreach ($development as $name => $record) {
            if (isset($runtime[$name])) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
            }

            $runtime[$name] = $record;
        }

        \ksort($runtime, \SORT_STRING);

        return $runtime;
    }

    /** @return array{bytes: string, document: \stdClass, identity: non-empty-string} */
    private static function readRequiredDocument(
        string $path,
        int $maximumBytes,
        string $documentKind,
    ): array {
        if (!\is_file($path) || !\is_readable($path) || \is_link($path)) {
            throw self::invalidJsonDocument($documentKind);
        }

        $size = @\filesize($path);

        if (!\is_int($size) || $size > $maximumBytes) {
            throw self::invalidJsonDocument($documentKind);
        }

        $bytes = @\file_get_contents($path);

        if (!\is_string($bytes) || \strlen($bytes) > $maximumBytes) {
            throw self::invalidJsonDocument($documentKind);
        }

        return [
            'bytes' => $bytes,
            'document' => self::decodeRootObject($bytes, $documentKind),
            'identity' => self::identity($bytes),
        ];
    }

    private static function decodeRootObject(
        string $bytes,
        string $documentKind,
    ): \stdClass {
        try {
            self::assertNoDuplicateObjectKeys($bytes);

            $decoded = \json_decode(
                $bytes,
                false,
                self::MAX_JSON_DEPTH,
                \JSON_THROW_ON_ERROR,
            );
            $bigIntegerAware = \json_decode(
                $bytes,
                false,
                self::MAX_JSON_DEPTH,
                \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING,
            );

            if (!$decoded instanceof \stdClass || !$bigIntegerAware instanceof \stdClass) {
                throw new \UnexpectedValueException('dependency-sync-json-root-must-be-object');
            }

            self::assertNativeJsonNumericSafety($decoded, $bigIntegerAware);
        } catch (\JsonException|\UnexpectedValueException) {
            throw self::invalidJsonDocument($documentKind);
        }

        return $decoded;
    }

    private static function assertNoDuplicateObjectKeys(string $bytes): void
    {
        $length = \strlen($bytes);
        $stack = [];

        for ($index = 0; $index < $length; $index++) {
            $char = $bytes[$index];

            if ($char === '{') {
                if (\count($stack) >= self::MAX_JSON_DEPTH) {
                    throw new \UnexpectedValueException('dependency-sync-json-depth-invalid');
                }

                $stack[] = ['type' => 'object', 'seen' => []];
                continue;
            }

            if ($char === '[') {
                if (\count($stack) >= self::MAX_JSON_DEPTH) {
                    throw new \UnexpectedValueException('dependency-sync-json-depth-invalid');
                }

                $stack[] = ['type' => 'array', 'seen' => []];
                continue;
            }

            if ($char === '}' || $char === ']') {
                if ($stack === []) {
                    throw new \UnexpectedValueException('dependency-sync-json-structure-invalid');
                }

                \array_pop($stack);
                continue;
            }

            if ($char !== '"') {
                continue;
            }

            $start = $index;
            $escaped = false;

            for ($index++; $index < $length; $index++) {
                $current = $bytes[$index];

                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($current === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($current === '"') {
                    break;
                }
            }

            if ($index >= $length) {
                throw new \UnexpectedValueException('dependency-sync-json-string-invalid');
            }

            $next = $index + 1;

            while ($next < $length && \str_contains(" \t\r\n", $bytes[$next])) {
                $next++;
            }

            if (
                $next >= $length
                || $bytes[$next] !== ':'
                || $stack === []
                || $stack[\array_key_last($stack)]['type'] !== 'object'
            ) {
                continue;
            }

            $token = \substr($bytes, $start, $index - $start + 1);

            try {
                $key = \json_decode($token, false, self::MAX_JSON_DEPTH, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new \UnexpectedValueException('dependency-sync-json-key-invalid');
            }

            if (!\is_string($key)) {
                throw new \UnexpectedValueException('dependency-sync-json-key-invalid');
            }

            $frameIndex = \array_key_last($stack);

            if (isset($stack[$frameIndex]['seen'][$key])) {
                throw new \UnexpectedValueException('dependency-sync-json-duplicate-key');
            }

            $stack[$frameIndex]['seen'][$key] = true;
        }
    }

    private static function assertNativeJsonNumericSafety(
        mixed $decoded,
        mixed $bigIntegerAware,
    ): void {
        if (\is_float($decoded)) {
            if (!\is_finite($decoded) || \is_string($bigIntegerAware)) {
                throw new \UnexpectedValueException('dependency-sync-json-number-invalid');
            }

            return;
        }

        if ($decoded instanceof \stdClass) {
            if (!$bigIntegerAware instanceof \stdClass) {
                throw new \UnexpectedValueException('dependency-sync-json-shape-invalid');
            }

            $left = \get_object_vars($decoded);
            $right = \get_object_vars($bigIntegerAware);

            if (\array_keys($left) !== \array_keys($right)) {
                throw new \UnexpectedValueException('dependency-sync-json-shape-invalid');
            }

            foreach ($left as $key => $value) {
                self::assertNativeJsonNumericSafety($value, $right[$key]);
            }

            return;
        }

        if (\is_array($decoded)) {
            if (!\is_array($bigIntegerAware) || \count($decoded) !== \count($bigIntegerAware)) {
                throw new \UnexpectedValueException('dependency-sync-json-shape-invalid');
            }

            foreach ($decoded as $index => $value) {
                self::assertNativeJsonNumericSafety($value, $bigIntegerAware[$index]);
            }
        }
    }

    private static function invalidJsonDocument(string $documentKind): DependencySyncException
    {
        return DependencySyncException::forCode(
            $documentKind === self::JSON_DOCUMENT_LOCK
                ? DependencySyncErrorCodes::COMPOSER_LOCK_STALE
                : DependencySyncErrorCodes::COMPOSER_MANIFEST_INVALID,
        );
    }

    private static function identity(string $bytes): string
    {
        return 'sha256:' . \hash('sha256', $bytes);
    }

    private static function atomicReplace(
        string $path,
        string $bytes,
        int $permission,
    ): void {
        $directory = \dirname($path);
        $temporary = $directory
            . \DIRECTORY_SEPARATOR
            . '.dependency-sync-'
            . \bin2hex(\random_bytes(16))
            . '.tmp';
        $handle = @\fopen($temporary, 'x+b');

        if (!\is_resource($handle)) {
            throw new \RuntimeException('manifest-temp-create-failed');
        }

        $closed = false;

        try {
            self::writeAll($handle, $bytes);

            if (!@\fflush($handle)) {
                throw new \RuntimeException('manifest-temp-flush-failed');
            }

            if (\function_exists('fsync') && !@\fsync($handle)) {
                throw new \RuntimeException('manifest-temp-sync-failed');
            }

            if (\DIRECTORY_SEPARATOR !== '\\' && !@\chmod($temporary, $permission)) {
                throw new \RuntimeException('manifest-temp-permission-failed');
            }

            if (!@\fclose($handle)) {
                throw new \RuntimeException('manifest-temp-close-failed');
            }
            $closed = true;

            if (!@\rename($temporary, $path)) {
                throw new \RuntimeException('manifest-replace-failed');
            }
        } finally {
            if (!$closed && \is_resource($handle)) {
                @\fclose($handle);
            }

            if (\file_exists($temporary) || \is_link($temporary)) {
                @\unlink($temporary);
            }
        }
    }

    private static function ensureRecoveryDirectory(string $path, bool $create): void
    {
        if (\is_link($path)) {
            throw new \RuntimeException('recovery-directory-symlink');
        }

        if (\is_dir($path)) {
            return;
        }

        if (!$create || \file_exists($path) || !@\mkdir($path, 0700)) {
            throw new \RuntimeException('recovery-directory-invalid');
        }

        if (\DIRECTORY_SEPARATOR !== '\\' && !@\chmod($path, 0700)) {
            throw new \RuntimeException('recovery-directory-permission-failed');
        }

        if (!self::isNonSymlinkDirectory($path)) {
            throw new \RuntimeException('recovery-directory-invalid');
        }
    }

    private static function isNonSymlinkDirectory(string $path): bool
    {
        return \is_dir($path) && !\is_link($path);
    }

    private static function writeRecoveryFile(string $path, string $bytes): void
    {
        $handle = @\fopen($path, 'x+b');

        if (!\is_resource($handle)) {
            throw new \RuntimeException('recovery-file-create-failed');
        }

        $closed = false;

        try {
            self::writeAll($handle, $bytes);

            if (!@\fflush($handle)) {
                throw new \RuntimeException('recovery-file-flush-failed');
            }

            if (!\function_exists('fsync') || !@\fsync($handle)) {
                throw new \RuntimeException('recovery-file-sync-failed');
            }

            if (\DIRECTORY_SEPARATOR !== '\\' && !@\chmod($path, 0600)) {
                throw new \RuntimeException('recovery-file-permission-failed');
            }

            if (!@\fclose($handle)) {
                throw new \RuntimeException('recovery-file-close-failed');
            }

            $closed = true;
        } finally {
            if (!$closed && \is_resource($handle)) {
                @\fclose($handle);
            }
        }
    }

    /** @param resource $handle */
    private static function writeAll($handle, string $bytes): void
    {
        $length = \strlen($bytes);
        $written = 0;

        while ($written < $length) {
            $chunk = @\fwrite($handle, \substr($bytes, $written));

            if (!\is_int($chunk) || $chunk <= 0) {
                throw new \RuntimeException('dependency-sync-write-failed');
            }

            $written += $chunk;
        }
    }

    private static function cleanupIncompleteReceipt(string $receipt): void
    {
        $recovery = \dirname($receipt);
        $dependencySync = \dirname($recovery);
        $var = \dirname($dependencySync);

        try {
            self::ensureRecoveryDirectory($var, false);
            self::ensureRecoveryDirectory($dependencySync, false);
            self::ensureRecoveryDirectory($recovery, false);
        } catch (\RuntimeException) {
            return;
        }

        if (!\is_dir($receipt) || \is_link($receipt)) {
            return;
        }

        foreach (['composer.json.original', 'composer.lock.original'] as $name) {
            $path = $receipt . \DIRECTORY_SEPARATOR . $name;

            if (\is_file($path) && !\is_link($path)) {
                @\unlink($path);
            }
        }

        @\rmdir($receipt);
    }

    private static function recoveryFailure(string $receiptId): never
    {
        $context = \preg_match('/\A[A-Za-z0-9_-]{1,128}\z/D', $receiptId) === 1
            ? ['recoveryReceiptId' => $receiptId]
            : [];

        throw DependencySyncException::forCode(
            DependencySyncErrorCodes::RECOVERY_STORAGE_FAILED,
            $context,
        );
    }

    /**
     * @return array<string, array{
     *     type: string|null,
     *     version: non-empty-string,
     *     sourceReference: non-empty-string|null,
     *     distReference: non-empty-string|null,
     * }>
     */
    private static function lockPackageCollection(
        \stdClass $lockDocument,
        string $property,
        bool $required,
    ): array {
        if (!\property_exists($lockDocument, $property)) {
            if ($required) {
                throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
            }

            return [];
        }

        $records = $lockDocument->{$property};

        if (!\is_array($records) || !\array_is_list($records)) {
            throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
        }

        $packages = [];

        foreach ($records as $record) {
            if (!$record instanceof \stdClass) {
                self::lockStale();
            }

            $name = $record->name ?? null;
            $version = $record->version ?? null;
            $type = $record->type ?? null;

            if (
                !\is_string($name)
                || $name === ''
                || isset($packages[$name])
                || !\is_string($version)
                || !self::isSafeSingleLine($version)
                || ($type !== null && !\is_string($type))
            ) {
                self::lockStale();
            }

            try {
                new ModulePlanEntry(
                    ModuleId::fromString('core.kernel'),
                    $name,
                );
            } catch (\InvalidArgumentException) {
                self::lockStale();
            }

            $packages[$name] = [
                'type' => $type,
                'version' => $version,
                'sourceReference' => self::nestedReference($record, 'source'),
                'distReference' => self::nestedReference($record, 'dist'),
            ];
        }

        \ksort($packages, \SORT_STRING);

        return $packages;
    }

    private static function nestedReference(\stdClass $record, string $property): ?string
    {
        if (!\property_exists($record, $property) || $record->{$property} === null) {
            return null;
        }

        $metadata = $record->{$property};

        if (!$metadata instanceof \stdClass) {
            self::lockStale();
        }

        if (!\property_exists($metadata, 'reference') || $metadata->reference === null) {
            return null;
        }

        $reference = $metadata->reference;

        if (!\is_string($reference) || !self::isSafeSingleLine($reference)) {
            self::lockStale();
        }

        return $reference;
    }

    private static function isSafeSingleLine(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && \preg_match('/[\x00-\x1F\x7F]/', $value) === 0;
    }

    private static function lockStale(): never
    {
        throw DependencySyncException::forCode(DependencySyncErrorCodes::COMPOSER_LOCK_STALE);
    }
}
