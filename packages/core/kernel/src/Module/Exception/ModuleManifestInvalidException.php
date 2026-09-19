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

namespace Coretsia\Kernel\Module\Exception;

use Coretsia\Contracts\Module\ModuleId;

/**
 * Deterministic module manifest metadata failure.
 *
 * Used for invalid Composer installed metadata, invalid `extra.coretsia`
 * metadata, invalid dependency metadata, or duplicate module ids.
 *
 * Diagnostics intentionally expose only safe module ids and stable reason
 * tokens. They must not expose Composer raw payloads, Composer install paths,
 * composer package names, filesystem paths, raw metadata arrays, or previous
 * throwable messages.
 *
 * @internal
 */
final class ModuleManifestInvalidException extends ModuleResolutionException
{
    public const string REASON_INSTALLED_METADATA_INVALID = 'module-manifest-installed-metadata-invalid';
    public const string REASON_CORETSIA_METADATA_INVALID = 'module-manifest-coretsia-metadata-invalid';
    public const string REASON_MODULE_ID_DUPLICATE = 'module-manifest-module-id-duplicate';
    public const string REASON_DEPENDENCY_METADATA_INVALID = 'module-manifest-dependency-metadata-invalid';
    public const string REASON_KIND_INVALID = 'module-manifest-kind-invalid';
    public const string REASON_MODULE_CLASS_INVALID = 'module-manifest-module-class-invalid';
    public const string REASON_PROVIDERS_INVALID = 'module-manifest-providers-invalid';

    private function __construct(
        string $reason,
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            ModuleErrorCodes::CORETSIA_MODULE_MANIFEST_INVALID,
            $reason,
            $context,
            $previous,
        );
    }

    public static function installedMetadataInvalid(
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_INSTALLED_METADATA_INVALID,
            [],
            $previous,
        );
    }

    public static function coretsiaMetadataInvalid(
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_CORETSIA_METADATA_INVALID,
            [],
            $previous,
        );
    }

    public static function duplicateModuleId(
        ModuleId $moduleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_MODULE_ID_DUPLICATE,
            [
                'moduleId' => $moduleId->value(),
            ],
            $previous,
        );
    }

    public static function dependencyMetadataInvalid(
        ModuleId $moduleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_DEPENDENCY_METADATA_INVALID,
            [
                'moduleId' => $moduleId->value(),
            ],
            $previous,
        );
    }

    public static function kindInvalid(
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_KIND_INVALID,
            [],
            $previous,
        );
    }

    public static function moduleClassInvalid(
        ModuleId $moduleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_MODULE_CLASS_INVALID,
            [
                'moduleId' => $moduleId->value(),
            ],
            $previous,
        );
    }

    public static function providersInvalid(
        ModuleId $moduleId,
        ?\Throwable $previous = null,
    ): self {
        return new self(
            self::REASON_PROVIDERS_INVALID,
            [
                'moduleId' => $moduleId->value(),
            ],
            $previous,
        );
    }
}
