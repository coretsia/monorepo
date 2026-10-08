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

namespace Coretsia\Contracts\Security;

/**
 * Stable contracts-level sensitive-data classification identifiers.
 *
 * This enum contains identifiers only. Key/value classification, hashing,
 * configuration, and presentation policy are outside this contract.
 */
enum RedactionKind: string
{
    case Unknown = 'unknown';
    case Secret = 'secret';
    case SecretReference = 'secret-reference';
    case Credential = 'credential';
    case Authorization = 'authorization';
    case Cookie = 'cookie';
    case SessionId = 'session-id';
    case Token = 'token';
    case Payload = 'payload';
    case Sql = 'sql';
    case Pii = 'pii';
    case EnvValue = 'env-value';
    case LocalPath = 'local-path';
}
