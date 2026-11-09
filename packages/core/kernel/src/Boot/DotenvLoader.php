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

use Coretsia\Contracts\Config\ConfigSourceType;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Kernel\Boot\Exception\BootstrapException;

/**
 * Loads dotenv files for Kernel Bootstrap Phase A.
 *
 * DotenvLoader consumes already resolved BootstrapConfig. It does not resolve
 * appEnv, read BootstrapInput, read config/app.php, apply package boot
 * defaults, apply system-env precedence, or use Coretsia\Contracts\Env\EnvPolicy.
 *
 * Dotenv file template expansion uses BootstrapConfig::appEnv().
 *
 * Missing dotenv files are skipped deterministically. Existing unreadable or
 * invalid dotenv files fail with BootstrapException. Exception messages are
 * stable and safe; raw dotenv values and absolute paths are never embedded in
 * diagnostics.
 *
 * @internal
 */
final readonly class DotenvLoader
{
    private const string KEY_ENV = 'env';
    private const string KEY_DOTENV = 'dotenv';
    private const string KEY_FILES = 'files';

    private const string ENV_TEMPLATE = '<env>';

    private const int SOURCE_PRECEDENCE = 0;

    /**
     * Loads normalized dotenv key/value pairs plus safe source metadata.
     *
     * The `$kernelConfig` argument is the `kernel` config subtree, not a
     * root-wrapped array.
     *
     * Later dotenv files override earlier dotenv files deterministically.
     *
     * @param array<string,mixed> $kernelConfig
     *
     * @return array{
     *     values: array<string,string>,
     *     sources: array<string,ConfigValueSource>,
     * }
     */
    public function load(
        BootstrapConfig $config,
        array $kernelConfig,
    ): array {
        return self::flattenLayers(
            $this->loadLayers(
                $config->applicationRoot(),
                $config->appEnv(),
                $kernelConfig,
            ),
        );
    }

    /**
     * Returns per-file ordered layers without flattening source provenance.
     *
     * @param array<string,mixed> $kernelConfig
     *
     * @return list<array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }>
     */
    public function loadLayers(
        string $applicationRoot,
        string $appEnv,
        array $kernelConfig,
    ): array {
        return self::readLayers($applicationRoot, $appEnv, $kernelConfig, null);
    }

    /**
     * Loads env-independent layers before appEnv is frozen. A caller can then
     * load only environment-specific layers, without rereading these files.
     *
     * @param array<string,mixed> $kernelConfig
     * @param null|callable(non-empty-string,string):void $validateEntry
     *
     * @return list<array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }>
     */
    public function loadBaseLayers(
        string $applicationRoot,
        array $kernelConfig,
        ?callable $validateEntry = null,
    ): array {
        return self::readLayers(
            $applicationRoot,
            null,
            $kernelConfig,
            false,
            [],
            $validateEntry,
        );
    }

    /**
     * @param array<string,mixed> $kernelConfig
     * @param array<string, array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }> $preloadedLayers
     * @param null|callable(non-empty-string,string):void $validateEntry
     *
     * @return list<array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }>
     */
    public function loadEnvironmentLayers(
        string $applicationRoot,
        string $appEnv,
        array $kernelConfig,
        array $preloadedLayers = [],
        ?callable $validateEntry = null,
    ): array {
        return self::readLayers(
            $applicationRoot,
            $appEnv,
            $kernelConfig,
            true,
            $preloadedLayers,
            $validateEntry,
        );
    }

    /**
     * @param list<array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }> $layers
     *
     * @return array{
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }
     */
    public static function flattenLayers(array $layers): array
    {
        $values = [];
        $sources = [];

        foreach ($layers as $layer) {
            foreach ($layer['values'] as $name => $value) {
                $values[$name] = $value;
                $sources[$name] = $layer['sources'][$name];
            }
        }

        \ksort($values, \SORT_STRING);
        \ksort($sources, \SORT_STRING);

        return ['values' => $values, 'sources' => $sources];
    }

    /**
     * @param array<string,mixed> $kernelConfig
     * @param array<string, array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }> $preloadedLayers
     * @param null|callable(non-empty-string,string):void $validateEntry
     *
     * @return list<array{
     *     fileName:string,
     *     values:array<string,string>,
     *     sources:array<string,ConfigValueSource>,
     * }>
     */
    private static function readLayers(
        string $applicationRoot,
        ?string $appEnv,
        array $kernelConfig,
        ?bool $environmentSpecific,
        array $preloadedLayers = [],
        ?callable $validateEntry = null,
    ): array {
        if (!self::isNonEmptySafeSingleLineString($applicationRoot)) {
            throw BootstrapException::withReason(BootstrapException::REASON_INVALID_APPLICATION_ROOT);
        }

        if ($appEnv !== null && !self::isNonEmptySafeSingleLineString($appEnv)) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $layers = [];

        foreach (self::dotenvFileNames($kernelConfig, $appEnv, $environmentSpecific) as $fileName) {
            if (isset($preloadedLayers[$fileName])) {
                $layers[] = $preloadedLayers[$fileName];
                continue;
            }

            $file = self::joinPath($applicationRoot, $fileName);

            if (!\file_exists($file)) {
                if (\is_link($file)) {
                    throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_LOAD_FAILED);
                }

                continue;
            }

            if (!\is_file($file) || !\is_readable($file)) {
                throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_LOAD_FAILED);
            }

            $values = self::parseFile($file, $validateEntry);
            $sources = [];

            foreach ($values as $name => $value) {
                $sources[$name] = self::sourceFor($fileName, $name);
            }

            $layers[] = [
                'fileName' => $fileName,
                'values' => $values,
                'sources' => $sources,
            ];
        }

        return $layers;
    }

    /**
     * @param array<string,mixed> $kernelConfig
     *
     * @return list<non-empty-string>
     */
    private static function dotenvFileNames(
        array $kernelConfig,
        ?string $appEnv,
        ?bool $environmentSpecific,
    ): array {
        $files = $kernelConfig[self::KEY_ENV][self::KEY_DOTENV][self::KEY_FILES] ?? null;

        if (!\is_array($files) || !\array_is_list($files)) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $out = [];

        foreach ($files as $template) {
            if (!\is_string($template)) {
                throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
            }

            self::assertSafeDotenvFileNameTemplate($template);
            $requiresEnv = \str_contains($template, self::ENV_TEMPLATE);

            if ($environmentSpecific !== null && $requiresEnv !== $environmentSpecific) {
                continue;
            }

            if ($requiresEnv && $appEnv === null) {
                throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
            }

            $fileName = $requiresEnv
                ? \str_replace(self::ENV_TEMPLATE, $appEnv, $template)
                : $template;

            self::assertSafeDotenvFileName($fileName);

            $out[] = $fileName;
        }

        return $out;
    }

    private static function joinPath(string $root, string $fileName): string
    {
        $originalRoot = $root;
        $root = \rtrim($root, '/\\');

        if ($root === '') {
            return $originalRoot === '' ? $fileName : $originalRoot[0] . $fileName;
        }

        return $root . '/' . $fileName;
    }

    /**
     * @param null|callable(non-empty-string,string):void $validateEntry
     *
     * @return array<string,string>
     */
    private static function parseFile(string $file, ?callable $validateEntry = null): array
    {
        \set_error_handler(
            static function (): never {
                throw new \ErrorException('dotenv-file-read-failed');
            },
        );

        try {
            $lines = \file($file, \FILE_IGNORE_NEW_LINES);
        } catch (\Throwable) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_LOAD_FAILED);
        } finally {
            \restore_error_handler();
        }

        if ($lines === false) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_LOAD_FAILED);
        }

        $out = [];

        foreach ($lines as $line) {
            $entry = self::parseLine($line);

            if ($entry === null) {
                continue;
            }

            if ($validateEntry !== null) {
                $validateEntry($entry['name'], $entry['value']);
            }

            $out[$entry['name']] = $entry['value'];
        }

        \ksort($out, \SORT_STRING);

        return $out;
    }

    /**
     * @return array{
     *     name: non-empty-string,
     *     value: string,
     * }|null
     */
    private static function parseLine(string $line): ?array
    {
        $line = self::stripUtf8Bom($line);
        $trimmed = \trim($line);

        if ($trimmed === '' || \str_starts_with($trimmed, '#')) {
            return null;
        }

        if (\str_starts_with($trimmed, 'export ')) {
            $trimmed = \ltrim(\substr($trimmed, 7));
        }

        $equalsPosition = \strpos($trimmed, '=');

        if ($equalsPosition === false) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $name = \trim(\substr($trimmed, 0, $equalsPosition));
        $value = \ltrim(\substr($trimmed, $equalsPosition + 1));

        self::assertValidEnvName($name);

        return [
            'name' => $name,
            'value' => self::parseValue($value),
        ];
    }

    private static function parseValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if ($value[0] === '"') {
            return self::parseDoubleQuotedValue($value);
        }

        if ($value[0] === "'") {
            return self::parseSingleQuotedValue($value);
        }

        return \rtrim(self::stripInlineComment($value));
    }

    private static function parseDoubleQuotedValue(string $value): string
    {
        $length = \strlen($value);

        if ($length < 2) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $escaped = false;
        $buffer = '';

        for ($index = 1; $index < $length; $index++) {
            $char = $value[$index];

            if ($escaped) {
                $buffer .= match ($char) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '\\' => '\\',
                    '"' => '"',
                    default => $char,
                };
                $escaped = false;

                continue;
            }

            if ($char === '\\') {
                $escaped = true;

                continue;
            }

            if ($char === '"') {
                $tail = \trim(\substr($value, $index + 1));

                if ($tail !== '' && !\str_starts_with($tail, '#')) {
                    throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
                }

                return $buffer;
            }

            $buffer .= $char;
        }

        throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
    }

    private static function parseSingleQuotedValue(string $value): string
    {
        $length = \strlen($value);

        if ($length < 2) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $end = \strpos($value, "'", 1);

        if ($end === false) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        $tail = \trim(\substr($value, $end + 1));

        if ($tail !== '' && !\str_starts_with($tail, '#')) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        return \substr($value, 1, $end - 1);
    }

    private static function stripInlineComment(string $value): string
    {
        $length = \strlen($value);

        for ($index = 0; $index < $length; $index++) {
            if ($value[$index] !== '#') {
                continue;
            }

            if ($index === 0 || \ctype_space($value[$index - 1])) {
                return \substr($value, 0, $index);
            }
        }

        return $value;
    }

    private static function stripUtf8Bom(string $line): string
    {
        if (\str_starts_with($line, "\xEF\xBB\xBF")) {
            return \substr($line, 3);
        }

        return $line;
    }

    private static function isNonEmptySafeSingleLineString(string $value): bool
    {
        return $value !== ''
            && \trim($value) === $value
            && !\str_contains($value, "\r")
            && !\str_contains($value, "\n")
            && \preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) !== 1;
    }

    private static function sourceFor(string $fileName, string $name): ConfigValueSource
    {
        return new ConfigValueSource(
            type: ConfigSourceType::Dotenv,
            root: 'env',
            sourceId: 'dotenv/' . $fileName,
            path: $fileName,
            keyPath: $name,
            precedence: self::SOURCE_PRECEDENCE,
            redacted: true,
            meta: [
                'name' => $fileName,
            ],
        );
    }

    private static function assertSafeDotenvFileNameTemplate(string $fileName): void
    {
        if ($fileName === '') {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\str_contains($fileName, self::ENV_TEMPLATE)) {
            $probe = \str_replace(self::ENV_TEMPLATE, 'env', $fileName);
            self::assertSafeDotenvFileName($probe);

            return;
        }

        self::assertSafeDotenvFileName($fileName);
    }

    private static function assertSafeDotenvFileName(string $fileName): void
    {
        if ($fileName === '') {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\preg_match('/^\s|\s$/', $fileName) === 1) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\str_contains($fileName, "\0")) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\str_contains($fileName, '/') || \str_contains($fileName, '\\')) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\str_contains($fileName, '..')) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\preg_match('/^[A-Za-z]:/', $fileName) === 1) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\str_contains($fileName, '://') || \str_contains($fileName, ':')) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }
    }

    /**
     * @phpstan-assert non-empty-string $name
     */
    private static function assertValidEnvName(string $name): void
    {
        if ($name === '') {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }

        if (\preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name) !== 1) {
            throw BootstrapException::withReason(BootstrapException::REASON_DOTENV_FILE_INVALID);
        }
    }
}
