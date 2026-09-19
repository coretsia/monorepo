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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Support\RepositoryContext;
use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class InternalToolkitNoDupGateDetectsDuplicationTest extends ToolContractTestCase
{
    public function testGateFailsOnForbiddenSymbolDeclaration(): void
    {
        $scanRoot = $this->createGateSandbox('toolkit-no-dup-dup-');

        $this->writeBytesExact(
            $scanRoot . '/Dup.php',
            <<<'PHP'
<?php

declare(strict_types=1);

final class Dup
{
    public function toSnake(): void
    {
    }
}

PHP,
        );

        $result = $this->runGate($scanRoot);

        self::assertSame(1, $result['exit_code'], 'gate must fail');
        self::assertSame(
            'CORETSIA_TOOLKIT_DUPLICATION_DETECTED',
            $result['code_line'],
            'must emit duplication code',
        );
    }

    public function testGateFailsOnJsonEncodeCall(): void
    {
        $scanRoot = $this->createGateSandbox('toolkit-no-dup-json-');

        $this->writeBytesExact(
            $scanRoot . '/JsonEncode.php',
            <<<'PHP'
<?php

declare(strict_types=1);

$x = \json_encode(['a' => 1]);

PHP,
        );

        $result = $this->runGate($scanRoot);

        self::assertSame(1, $result['exit_code'], 'gate must fail');
        self::assertSame(
            'CORETSIA_TOOLKIT_JSON_ENCODE_FORBIDDEN',
            $result['code_line'],
            'must emit json-forbidden code',
        );
    }

    private function createGateSandbox(string $prefix): string
    {
        $repoRoot = $this->tempDir($prefix);

        $this->ensureDir($repoRoot . '/packages');
        $this->ensureDir($repoRoot . '/tools/gates');
        $this->ensureDir($repoRoot . '/tools/runtime-fixture');

        $this->writeBytesExact(
            $repoRoot . '/composer.json',
            "{\n"
            . "  \"name\": \"coretsia/toolkit-no-dup-fixture\",\n"
            . "  \"type\": \"project\"\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $repoRoot . '/tools/gates/internal_toolkit_no_dup_gate.php',
            $this->readBytes(
                $this->repoRoot()
                . '/tools/gates/internal_toolkit_no_dup_gate.php',
            ),
        );

        $this->copyDir(
            $this->repoRoot() . '/tools/support',
            $repoRoot . '/tools/support',
        );

        $this->writeBytesExact(
            $repoRoot . '/vendor/autoload.php',
            <<<'PHP'
<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../tools/support/*.php') ?: [] as $path) {
    if (basename($path) === 'bootstrap.php') {
        continue;
    }

    require_once $path;
}

PHP,
        );

        return $repoRoot . '/tools/runtime-fixture';
    }

    /**
     * @return array{exit_code:int, raw_output:string, code_line:string}
     */
    private function runGate(string $scanRoot): array
    {
        $repository = RepositoryContext::discoverFrom($scanRoot);

        [$exitCode, $output] = $this->runPhp(
            $repository->resolveExistingFile('tools/gates/internal_toolkit_no_dup_gate.php'),
            [
                '--path=' . $scanRoot,
            ],
            $repository->repoRoot(),
        );

        $output = \trim(
            \str_replace(["\r\n", "\r"], "\n", $output),
        );

        $lines = $output === ''
            ? []
            : \explode("\n", $output);

        return [
            'exit_code' => $exitCode,
            'raw_output' => $output,
            'code_line' => \trim((string) ($lines[0] ?? '')),
        ];
    }
}
