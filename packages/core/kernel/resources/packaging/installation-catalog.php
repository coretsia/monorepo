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

/*
 * GENERATED FILE (consumer installation catalog).
 * Regenerate: composer arch:installation-catalog:generate
 */

return array(
  'modules' =>
  array(
    'core.foundation' =>
    array(
      'composerName' => 'coretsia/core-foundation',
      'conflicts' =>
      array(
      ),
      'requires' =>
      array(
      ),
    ),
    'core.kernel' =>
    array(
      'composerName' => 'coretsia/core-kernel',
      'conflicts' =>
      array(
      ),
      'requires' =>
      array(
        0 => 'core.foundation',
      ),
    ),
    'platform.cli' =>
    array(
      'composerName' => 'coretsia/platform-cli',
      'conflicts' =>
      array(
      ),
      'requires' =>
      array(
      ),
    ),
    'platform.worker' =>
    array(
      'composerName' => 'coretsia/platform-worker',
      'conflicts' =>
      array(
      ),
      'requires' =>
      array(
        0 => 'core.kernel',
      ),
    ),
  ),
  'publicConstraint' => '^0.7.0',
  'releaseLine' => '0.7',
  'schemaVersion' => 1,
);
