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

namespace Coretsia\Tools\Tests\Contract;

use Coretsia\Tools\Spikes\_support\ErrorCodes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ObservabilitySpanNamingGateTest extends TestCase
{
    #[DataProvider('cases')]
    public function testObservabilitySpanNamingGate(
        callable $arrange,
        bool $shouldPass,
        array $expectedDiagnostics
    ): void {
        $repoRoot = self::createTempDir('coretsia_observability_span_naming_gate_');

        try {
            self::writeCanonicalSpanPolicy($repoRoot);
            self::mkdirp($repoRoot . '/framework/packages/core/foundation/src');

            $arrange($repoRoot);

            [$exitCode, $stdout, $stderr] = self::runGate($repoRoot);

            if ($shouldPass) {
                self::assertSame(0, $exitCode, 'Gate must exit 0 on pass.');
                self::assertSame('', self::normalizeRaw($stdout . $stderr), 'Gate must be silent on pass.');
                return;
            }

            self::assertSame(1, $exitCode, 'Gate must exit 1 on deterministic policy failure.');

            $lines = self::normalizeLines($stdout, $stderr);
            self::assertNotSame([], $lines, 'Gate must print a deterministic CODE line on fail.');
            self::assertSame(ErrorCodes::CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT, $lines[0]);
            self::assertSame($expectedDiagnostics, array_slice($lines, 1));
        } finally {
            self::rmTree($repoRoot);
        }
    }

    public static function cases(): iterable
    {
        yield 'accepts-valid-singular-span-name-direct-string-literal' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedLiteral.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class AcceptedLiteral
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan('foundation.reset');
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-valid-singular-span-name-in-span' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedInSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class AcceptedInSpan
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->inSpan('foundation.reset', static fn () => null);
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-same-class-private-const-string-span-name' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedPrivateConst.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class AcceptedPrivateConst
                {
                    private const string RESET_SPAN = 'foundation.reset';

                    public function __construct(private TracerPortInterface $tracer)
                    {
                    }

                    public function run(): void
                    {
                        $this->tracer->startSpan(self::RESET_SPAN);
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-current-span-because-it-does-not-emit-span-name' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedCurrentSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class AcceptedCurrentSpan
                {
                    public function __construct(private TracerPortInterface $tracer)
                    {
                    }

                    public function run(): void
                    {
                        $this->tracer->currentSpan();
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-span-name-without-canonical-metrics-catalog' => [
            static function (string $root): void {
                self::writeObservabilityMarkdown(
                    $root,
                    <<<'MD'
                # Observability Naming, Metrics Catalog, and Labels Allowlist (SSoT)

                ## Naming Rules (MUST)

                ### Spans

                Span names **MUST** use this shape:

                ```text
                <domain>.<singular_operation>
                ```

                Rules:

                - `singular_operation` **MUST** be singular and describe the operation kind, not raw request data.
                - Span names **MUST NOT** be added to the canonical metrics catalog.

                ## Label Allowlist (MUST)

                - `outcome`
                MD
                );

                self::writeRuntimePhp(
                    $root,
                    'AcceptedWithoutMetricCatalog.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class AcceptedWithoutMetricCatalog
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan('foundation.reset');
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'rejects-malformed-span-name-with-double-dot' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'MalformedDoubleDot.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class MalformedDoubleDot
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan('foundation..reset');
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/MalformedDoubleDot.php: span-name-malformed',
            ],
        ];

        yield 'rejects-malformed-span-name-with-three-segments' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'MalformedThreeSegments.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class MalformedThreeSegments
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan('foundation.reset.total');
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/MalformedThreeSegments.php: span-name-malformed',
            ],
        ];

        yield 'rejects-plural-span-operation' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'PluralSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class PluralSpan
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan('foundation.resets');
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/PluralSpan.php: span-name-plural-operation',
            ],
        ];

        yield 'rejects-plural-span-operation-in-span' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'PluralInSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class PluralInSpan
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->inSpan('foundation.resets', static fn () => null);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/PluralInSpan.php: span-name-plural-operation',
            ],
        ];

        yield 'rejects-non-resolvable-dynamic-span-name' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'DynamicSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class DynamicSpan
                {
                    public function run(TracerPortInterface $tracer, string $spanName): void
                    {
                        $tracer->startSpan($spanName);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/DynamicSpan.php: span-name-unresolvable',
            ],
        ];

        yield 'rejects-concatenated-or-computed-span-name' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'ComputedSpan.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class ComputedSpan
                {
                    public function run(TracerPortInterface $tracer, string $operation): void
                    {
                        $tracer->startSpan('foundation.' . $operation);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/ComputedSpan.php: span-name-unresolvable',
            ],
        ];

        yield 'rejects-named-span-arguments-as-unparseable' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'NamedArguments.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Tracing\TracerPortInterface;

                final class NamedArguments
                {
                    public function run(TracerPortInterface $tracer): void
                    {
                        $tracer->startSpan(name: 'foundation.reset');
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/NamedArguments.php: span-call-arguments-unparseable',
            ],
        ];

        yield 'ignores-start-span-call-when-receiver-is-not-tracer-port' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'IgnoredNonTracer.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                final class IgnoredNonTracer
                {
                    public function run(object $notTracer): void
                    {
                        $notTracer->startSpan('foundation.resets');
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'rejects-missing-canonical-span-naming-policy' => [
            static function (string $root): void {
                self::writeObservabilityMarkdown(
                    $root,
                    <<<'MD'
                # Observability Naming, Metrics Catalog, and Labels Allowlist (SSoT)

                ## Naming Rules (MUST)

                ### Metrics

                Metric names **MUST** use this shape:

                ```text
                <domain>.<singular_operation>_<measure>
                ```
                MD
                );
            },
            false,
            [
                'docs/ssot/observability.md: canonical-span-naming-policy-missing',
            ],
        ];

        yield 'rejects-unparseable-canonical-span-naming-policy' => [
            static function (string $root): void {
                self::writeObservabilityMarkdown(
                    $root,
                    <<<'MD'
                # Observability Naming, Metrics Catalog, and Labels Allowlist (SSoT)

                ## Naming Rules (MUST)

                ### Spans

                Span names **MUST** use this shape:

                ```text
                <domain>.<operation>
                ```

                Rules:

                - `operation` describes the operation kind.
                MD
                );
            },
            false,
            [
                'docs/ssot/observability.md: canonical-span-naming-policy-unparseable',
            ],
        ];
    }

    private static function writeCanonicalSpanPolicy(string $root): void
    {
        self::writeObservabilityMarkdown(
            $root,
            <<<'MD'
        # Observability Naming, Metrics Catalog, and Labels Allowlist (SSoT)

        ## Naming Rules (MUST)

        ### Spans

        Span names **MUST** use this shape:

        ```text
        <domain>.<singular_operation>
        ```

        Rules:

        - `domain` **MUST** be stable and technology-neutral at the observability boundary.
        - `singular_operation` **MUST** be singular and describe the operation kind, not raw request data.
        - Span names **MUST NOT** be added to the canonical metrics catalog.
        - Span names **MUST NOT** embed raw path fragments, identifiers, query strings, SQL, or user-controlled bytes.

        ### Metrics

        Metric names **MUST** use this shape:

        ```text
        <domain>.<singular_operation>_<measure>
        ```

        ## Label Allowlist (MUST)

        The reserved baseline allowlist is single-choice:

        - `method`
        - `status`
        - `driver`
        - `operation`
        - `table`
        - `outcome`
        MD
        );
    }

    private static function writeObservabilityMarkdown(string $root, string $markdown): void
    {
        self::mkdirp($root . '/docs/ssot');
        self::writeFile($root . '/docs/ssot/observability.md', self::outdent($markdown) . "\n");
    }

    private static function writeRuntimePhp(string $root, string $file, string $code): void
    {
        self::writeFile(
            $root . '/framework/packages/core/foundation/src/' . $file,
            self::outdent($code) . "\n"
        );
    }

    /**
     * @return array{0:int,1:string,2:string}
     */
    private static function runGate(string $repoRoot): array
    {
        $gate = self::installGateHarness($repoRoot);

        $cmd = [PHP_BINARY, $gate];
        $spec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($cmd, $spec, $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            self::fail('Failed to start gate process.');
        }

        fclose($pipes[0]);
        $stdout = (string)stream_get_contents($pipes[1]);
        $stderr = (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [(int)proc_close($proc), $stdout, $stderr];
    }

    private static function gatePath(): string
    {
        $path = realpath(__DIR__ . '/../../gates/observability_span_naming_gate.php');
        if (!is_string($path) || $path === '') {
            self::fail('Gate file path cannot be resolved.');
        }

        return $path;
    }

    private static function installGateHarness(string $repoRoot): string
    {
        $gateTarget = $repoRoot . '/framework/tools/gates/observability_span_naming_gate.php';
        self::writeFile($gateTarget, (string)file_get_contents(self::gatePath()));

        self::writeFile(
            $repoRoot . '/framework/tools/spikes/_support/bootstrap.php',
            "<?php\ndeclare(strict_types=1);\n"
        );

        self::writeFile(
            $repoRoot . '/framework/tools/spikes/_support/ConsoleOutput.php',
            <<<'PHP'
            <?php
            declare(strict_types=1);

            namespace Coretsia\Tools\Spikes\_support;

            final class ConsoleOutput
            {
                public static function codeWithDiagnostics(string $code, array $diagnostics = [], bool $toStderr = true): void
                {
                    $stream = $toStderr ? STDERR : STDOUT;
                    fwrite($stream, $code . "\n");
                    foreach ($diagnostics as $diagnostic) {
                        fwrite($stream, $diagnostic . "\n");
                    }
                }
            }
            PHP
        );

        self::writeFile(
            $repoRoot . '/framework/tools/spikes/_support/ErrorCodes.php',
            <<<'PHP'
            <?php
            declare(strict_types=1);

            namespace Coretsia\Tools\Spikes\_support;

            final class ErrorCodes
            {
                public const string CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT = 'CORETSIA_OBSERVABILITY_SPAN_NAMING_DRIFT';
                public const string CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED = 'CORETSIA_OBSERVABILITY_SPAN_NAMING_GATE_FAILED';
            }
            PHP
        );

        $real = realpath($gateTarget);
        if (!is_string($real) || $real === '') {
            self::fail('Temp gate path cannot be resolved.');
        }

        return $real;
    }

    private static function normalizeLines(string $stdout, string $stderr): array
    {
        $raw = self::normalizeRaw($stderr . $stdout);
        return $raw === '' ? [] : explode("\n", $raw);
    }

    private static function normalizeRaw(string $raw): string
    {
        return rtrim(str_replace(["\r\n", "\r"], "\n", $raw), "\n");
    }

    private static function outdent(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = trim($text, "\n");
        $lines = explode("\n", $text);
        $indent = null;

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            preg_match('/^ */', $line, $m);
            $len = strlen($m[0] ?? '');
            $indent = $indent === null ? $len : min($indent, $len);
        }

        if ($indent === null || $indent === 0) {
            return implode("\n", $lines);
        }

        return implode("\n", array_map(static fn (string $line): string => substr($line, $indent), $lines));
    }

    private static function writeFile(string $path, string $content): void
    {
        self::mkdirp(dirname($path));
        $bytes = file_put_contents($path, $content);
        if (!is_int($bytes) || $bytes <= 0) {
            self::fail('Failed to write file: ' . $path);
        }
    }

    private static function mkdirp(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, 0777, true) && !is_dir($dir)) {
            self::fail('Failed to create directory: ' . $dir);
        }
    }

    private static function createTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . bin2hex(random_bytes(8));
        self::mkdirp($dir);

        $real = realpath($dir);
        if (!is_string($real) || $real === '') {
            self::fail('Temp dir realpath failed.');
        }

        return $real;
    }

    private static function rmTree(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $fi) {
            if (!$fi instanceof \SplFileInfo) {
                continue;
            }
            $path = $fi->getPathname();
            if ($fi->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}
