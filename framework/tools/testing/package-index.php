<?php

declare(strict_types=1);

/*
 * GENERATED FILE (tooling-only).
 * MUST NOT be used by runtime.
 * Regenerate: composer arch:package-index:generate
 */

return array(
  'generatedBy' => 'framework/tools/build/package_index.php',
  'packages' =>
  array(
    0 =>
    array(
      'composerName' => 'coretsia/core-contracts',
      'defaultsConfigPath' => null,
      'id' => 'core.contracts',
      'kind' => 'library',
      'layer' => 'core',
      'moduleClass' => null,
      'moduleId' => null,
      'path' => 'framework/packages/core/contracts',
      'providers' =>
      array(
      ),
      'psr4' => 'Coretsia\\Contracts\\',
      'slug' => 'contracts',
    ),
    1 =>
    array(
      'composerName' => 'coretsia/core-dto-attribute',
      'defaultsConfigPath' => null,
      'id' => 'core.dto-attribute',
      'kind' => 'library',
      'layer' => 'core',
      'moduleClass' => null,
      'moduleId' => null,
      'path' => 'framework/packages/core/dto-attribute',
      'providers' =>
      array(
      ),
      'psr4' => 'Coretsia\\Dto\\Attribute\\',
      'slug' => 'dto-attribute',
    ),
    2 =>
    array(
      'composerName' => 'coretsia/core-foundation',
      'defaultsConfigPath' => 'config/foundation.php',
      'id' => 'core.foundation',
      'kind' => 'runtime',
      'layer' => 'core',
      'moduleClass' => 'Coretsia\\Foundation\\Module\\FoundationModule',
      'moduleId' => 'core.foundation',
      'path' => 'framework/packages/core/foundation',
      'providers' =>
      array(
        0 => 'Coretsia\\Foundation\\Provider\\FoundationServiceProvider',
      ),
      'psr4' => 'Coretsia\\Foundation\\',
      'slug' => 'foundation',
    ),
    3 =>
    array(
      'composerName' => 'coretsia/devtools-cli-spikes',
      'defaultsConfigPath' => null,
      'id' => 'devtools.cli-spikes',
      'kind' => 'library',
      'layer' => 'devtools',
      'moduleClass' => null,
      'moduleId' => null,
      'path' => 'framework/packages/devtools/cli-spikes',
      'providers' =>
      array(
      ),
      'psr4' => 'Coretsia\\Devtools\\CliSpikes\\',
      'slug' => 'cli-spikes',
    ),
    4 =>
    array(
      'composerName' => 'coretsia/devtools-internal-toolkit',
      'defaultsConfigPath' => null,
      'id' => 'devtools.internal-toolkit',
      'kind' => 'library',
      'layer' => 'devtools',
      'moduleClass' => null,
      'moduleId' => null,
      'path' => 'framework/packages/devtools/internal-toolkit',
      'providers' =>
      array(
      ),
      'psr4' => 'Coretsia\\Devtools\\InternalToolkit\\',
      'slug' => 'internal-toolkit',
    ),
    5 =>
    array(
      'composerName' => 'coretsia/platform-cli',
      'defaultsConfigPath' => 'config/cli.php',
      'id' => 'platform.cli',
      'kind' => 'runtime',
      'layer' => 'platform',
      'moduleClass' => 'Coretsia\\Platform\\Cli\\Module\\CliModule',
      'moduleId' => 'platform.cli',
      'path' => 'framework/packages/platform/cli',
      'providers' =>
      array(
        0 => 'Coretsia\\Platform\\Cli\\Provider\\CliServiceProvider',
      ),
      'psr4' => 'Coretsia\\Platform\\Cli\\',
      'slug' => 'cli',
    ),
  ),
  'schemaVersion' => 1,
);
