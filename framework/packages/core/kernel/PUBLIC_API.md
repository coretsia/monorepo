<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# Kernel Public API

This document is the package-local public API evidence file for `core/kernel`.

The `kernel-public-api:gate` uses this file to lock which non-internal kernel symbols are intentionally public.

## Public symbols

- `Coretsia\Kernel\Module\KernelModule`
- `Coretsia\Kernel\Provider\KernelServiceProvider`
- `Coretsia\Kernel\Provider\Tags`
- `Coretsia\Kernel\Runtime\Outcome`
- `Coretsia\Kernel\Runtime\UnitOfWorkType`
- `Coretsia\Kernel\Runtime\UnitOfWorkContext`
- `Coretsia\Kernel\Runtime\UnitOfWorkResult`
- `Coretsia\Kernel\Runtime\Exception\KernelRuntimeException`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkContextInvalidException`
- `Coretsia\Kernel\Runtime\Exception\UnitOfWorkResultInvalidException`
