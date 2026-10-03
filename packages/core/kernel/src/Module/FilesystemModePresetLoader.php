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

namespace Coretsia\Kernel\Module;

use Coretsia\Contracts\Module\ModePresetInterface;
use Coretsia\Contracts\Module\ModePresetLoaderInterface;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModePresetNotFoundException;
use Coretsia\Kernel\Module\Preset\PresetSourceInterface;

/**
 * One source-bound loader. Never searches alternative preset namespaces.
 *
 * @internal
 */
final readonly class FilesystemModePresetLoader implements ModePresetLoaderInterface
{
    public function __construct(
        private PresetSourceInterface $source,
        private ModePresetSchemaValidator $schemaValidator,
    ) {
    }

    public function listNames(): array
    {
        return $this->source->listNames();
    }

    public function has(string $name): bool
    {
        return self::isSafeName($name) && $this->source->has($name);
    }

    public function load(string $name): ModePresetInterface
    {
        if (!self::isSafeName($name)) {
            throw ModePresetNotFoundException::invalidPresetName();
        }
        $file = $this->source->resolveFile($name);
        if ($file === null) {
            throw ModePresetNotFoundException::forPreset($name);
        }
        return $this->loadFile($name, $file);
    }

    public function tryLoad(string $name): ?ModePresetInterface
    {
        if (!self::isSafeName($name)) {
            return null;
        }
        $file = $this->source->resolveFile($name);
        return $file === null ? null : $this->loadFile($name, $file);
    }

    private function loadFile(string $name, string $file): ModePresetInterface
    {
        if (!\is_readable($file)) {
            throw ModePresetInvalidException::forPreset($name);
        }
        \set_error_handler(static function (): never {
            throw new \RuntimeException('mode-preset-php-execution-invalid');
        });
        try {
            $payload = (static fn (string $path): mixed => require $path)($file);
        } catch (\Throwable) {
            throw ModePresetInvalidException::forPreset($name);
        } finally {
            \restore_error_handler();
        }
        return $this->schemaValidator->validate($name, $payload);
    }

    private static function isSafeName(string $name): bool
    {
        return \preg_match('/\A[a-z][a-z0-9-]{0,63}\z/D', $name) === 1;
    }
}
