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

final class ObservabilityMetricCatalogGateTest extends TestCase
{
    #[DataProvider('cases')]
    public function testObservabilityCatalogGate(callable $arrange, bool $shouldPass, array $expectedDiagnostics): void
    {
        $repoRoot = self::createTempDir('coretsia_observability_metric_catalog_gate_');

        try {
            self::writeCanonicalCatalog($repoRoot);
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
            self::assertSame(ErrorCodes::CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT, $lines[0]);
            self::assertSame($expectedDiagnostics, array_slice($lines, 1));
        } finally {
            self::rmTree($repoRoot);
        }
    }

    public static function cases(): iterable
    {
        yield 'accepts-reset-metrics-literals-and-private-constants' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'Accepted.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class Accepted
                {
                    private const string RESET_TOTAL = 'foundation.reset_total';
                    private const string RESET_DURATION = 'foundation.reset_duration_ms';

                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function reset(string $outcome, int $durationMs): void
                    {
                        $labels = ['outcome' => $outcome];

                        $this->meter->increment(self::RESET_TOTAL, 1, $labels);
                        $this->meter->observe(self::RESET_DURATION, $durationMs, ['outcome' => $outcome]);
                    }
                }
                PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-reset-metrics-direct-string-literals' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedLiterals.php',
                    <<<'PHP'
        <?php
        declare(strict_types=1);

        namespace App\Runtime;

        use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

        final class AcceptedLiterals
        {
            public function run(MeterPortInterface $meter, string $outcome, int $durationMs): void
            {
                $meter->increment('foundation.reset_total', 1, ['outcome' => $outcome]);
                $meter->observe('foundation.reset_duration_ms', $durationMs, ['outcome' => $outcome]);
            }
        }
        PHP
                );
            },
            true,
            [],
        ];

        yield 'accepts-valid-metrics-with-omitted-labels' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'AcceptedOmittedLabels.php',
                    <<<'PHP'
        <?php
        declare(strict_types=1);

        namespace App\Runtime;

        use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

        final class AcceptedOmittedLabels
        {
            public function run(MeterPortInterface $meter, int $durationMs): void
            {
                $meter->increment('foundation.reset_total');
                $meter->observe('foundation.reset_duration_ms', $durationMs);
            }
        }
        PHP
                );
            },
            true,
            [],
        ];

        foreach (
            [
                'foundation.resets_total',
                'foundation.resets_duration_ms',
                'foundation.reset.total',
                'foundation..reset_total',
                'foundation.cache_total',
            ] as $metric
        ) {
            yield 'rejects-unknown-metric-' . $metric => [
                static function (string $root) use ($metric): void {
                    self::writeRuntimePhp(
                        $root,
                        'UnknownMetric.php',
                        <<<PHP
                    <?php
                    declare(strict_types=1);

                    namespace App\\Runtime;

                    use Coretsia\\Contracts\\Observability\\Metrics\\MeterPortInterface;

                    final class UnknownMetric
                    {
                        public function __construct(private MeterPortInterface \$meter)
                        {
                        }

                        public function run(): void
                        {
                            \$this->meter->increment('{$metric}');
                        }
                    }
                    PHP
                    );
                },
                false,
                [
                    'packages/core/foundation/src/UnknownMetric.php: metric-name-not-in-catalog',
                ],
            ];
        }

        yield 'rejects-unknown-label-for-known-metric' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'UnknownLabel.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class UnknownLabel
                {
                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function run(string $outcome): void
                    {
                        $this->meter->increment('foundation.reset_total', 1, ['driver' => 'x', 'outcome' => $outcome]);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/UnknownLabel.php: metric-label-key-not-in-catalog',
            ],
        ];

        yield 'rejects-increment-observe-mismatch' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'IncrementMismatch.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class IncrementMismatch
                {
                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function run(): void
                    {
                        $this->meter->increment('foundation.reset_duration_ms');
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/IncrementMismatch.php: metric-method-type-mismatch',
            ],
        ];

        yield 'rejects-observe-counter-mismatch' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'ObserveMismatch.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class ObserveMismatch
                {
                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function run(): void
                    {
                        $this->meter->observe('foundation.reset_total', 1);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/ObserveMismatch.php: metric-method-type-mismatch',
            ],
        ];

        yield 'rejects-dynamic-metric-name' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'DynamicMetric.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class DynamicMetric
                {
                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function run(string $suffix): void
                    {
                        $this->meter->increment('foundation.reset_' . $suffix);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/DynamicMetric.php: metric-name-unresolvable',
            ],
        ];

        yield 'rejects-non-resolvable-label-map' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'DynamicLabels.php',
                    <<<'PHP'
                <?php
                declare(strict_types=1);

                namespace App\Runtime;

                use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

                final class DynamicLabels
                {
                    public function __construct(private MeterPortInterface $meter)
                    {
                    }

                    public function run(array $labels): void
                    {
                        $this->meter->increment('foundation.reset_total', 1, $labels);
                    }
                }
                PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/DynamicLabels.php: metric-label-map-unresolvable',
            ],
        ];

        yield 'rejects-label-map-assigned-after-meter-call' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'LabelsAssignedAfterCall.php',
                    <<<'PHP'
        <?php
        declare(strict_types=1);

        namespace App\Runtime;

        use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

        final class LabelsAssignedAfterCall
        {
            public function __construct(private MeterPortInterface $meter)
            {
            }

            public function run(string $outcome): void
            {
                $this->meter->increment('foundation.reset_total', 1, $labels);

                $labels = ['outcome' => $outcome];
            }
        }
        PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/LabelsAssignedAfterCall.php: metric-label-map-unresolvable',
            ],
        ];

        yield 'rejects-named-meter-arguments' => [
            static function (string $root): void {
                self::writeRuntimePhp(
                    $root,
                    'NamedArguments.php',
                    <<<'PHP'
        <?php
        declare(strict_types=1);

        namespace App\Runtime;

        use Coretsia\Contracts\Observability\Metrics\MeterPortInterface;

        final class NamedArguments
        {
            public function __construct(private MeterPortInterface $meter)
            {
            }

            public function run(string $outcome): void
            {
                $this->meter->increment(
                    name: 'foundation.reset_total',
                    delta: 1,
                    labels: ['outcome' => $outcome],
                );
            }
        }
        PHP
                );
            },
            false,
            [
                'packages/core/foundation/src/NamedArguments.php: meter-call-arguments-unparseable',
            ],
        ];

        yield 'rejects-duplicate-catalog-metric-row' => [
            static function (string $root): void {
                self::appendCatalogRow($root, '| foundation.reset_total | `core/foundation` | counter | `outcome` |');
            },
            false,
            [
                'docs/ssot/observability.md: canonical-metrics-catalog-duplicate-metric',
            ],
        ];

        yield 'rejects-unsupported-catalog-type' => [
            static function (string $root): void {
                self::appendCatalogRow($root, '| foundation.bad_gauge | `core/foundation` | gauge | `outcome` |');
            },
            false,
            [
                'docs/ssot/observability.md: canonical-metrics-catalog-unsupported-type',
            ],
        ];

        yield 'rejects-catalog-label-outside-global-allowlist' => [
            static function (string $root): void {
                self::appendCatalogRow($root, '| foundation.bad_total | `core/foundation` | counter | `tenant_id` |');
            },
            false,
            [
                'docs/ssot/observability.md: catalog-label-not-allowlisted',
            ],
        ];

        yield 'rejects-missing-canonical-catalog-section' => [
            static function (string $root): void {
                self::writeObservabilityMarkdown(
                    $root,
                    <<<'MD'
                # Observability Naming and Labels Allowlist (SSoT)

                ## Label Allowlist (MUST)

                The reserved baseline allowlist is single-choice:

                - `method`
                - `status`
                - `driver`
                - `operation`
                - `table`
                - `outcome`

                ### Label Rules (MUST)

                x
                MD
                );
            },
            false,
            [
                'docs/ssot/observability.md: canonical-metrics-catalog-missing',
            ],
        ];

        yield 'rejects-unparseable-canonical-catalog-section' => [
            static function (string $root): void {
                self::writeObservabilityMarkdown(
                    $root,
                    <<<'MD'
        # Observability Naming and Labels Allowlist (SSoT)

        ## Label Allowlist (MUST)

        The reserved baseline allowlist is single-choice:

        - `method`
        - `status`
        - `driver`
        - `operation`
        - `table`
        - `outcome`

        ### Label Rules (MUST)

        x

        ## Canonical metrics catalog

        This section intentionally has no parseable catalog table.
        MD
                );
            },
            false,
            [
                'docs/ssot/observability.md: canonical-metrics-catalog-unparseable',
            ],
        ];
    }

    private static function writeCanonicalCatalog(string $root): void
    {
        self::writeObservabilityMarkdown(
            $root,
            <<<'MD'
        # Observability Naming and Labels Allowlist (SSoT)

        ## Label Allowlist (MUST)

        The reserved baseline allowlist is single-choice:

        - `method`
        - `status`
        - `driver`
        - `operation`
        - `table`
        - `outcome`

        ### Label Rules (MUST)

        x

        ## Canonical metrics catalog

        Metric names MUST use:

        ```text
        <domain>.<singular_operation>_<measure>
        ```

        | Metric name                  | Owner             | Type    | Labels    |
        |------------------------------|-------------------|---------|-----------|
        | foundation.reset_total       | `core/foundation` | counter | `outcome` |
        | foundation.reset_duration_ms | `core/foundation` | observe | `outcome` |
        MD
        );
    }

    private static function writeObservabilityMarkdown(string $root, string $markdown): void
    {
        self::mkdirp($root . '/docs/ssot');
        self::writeFile($root . '/docs/ssot/observability.md', self::outdent($markdown) . "\n");
    }

    private static function appendCatalogRow(string $root, string $row): void
    {
        $path = $root . '/docs/ssot/observability.md';
        $content = (string)file_get_contents($path);
        self::writeFile($path, rtrim($content, "\n") . "\n" . $row . "\n");
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
        $path = realpath(__DIR__ . '/../../gates/observability_metric_catalog_gate.php');
        if (!is_string($path) || $path === '') {
            self::fail('Gate file path cannot be resolved.');
        }

        return $path;
    }

    private static function installGateHarness(string $repoRoot): string
    {
        $gateTarget = $repoRoot . '/framework/tools/gates/observability_metric_catalog_gate.php';
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
                public const string CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT = 'CORETSIA_OBSERVABILITY_METRIC_CATALOG_DRIFT';
                public const string CORETSIA_OBSERVABILITY_METRIC_CATALOG_GATE_FAILED = 'CORETSIA_OBSERVABILITY_METRIC_CATALOG_GATE_FAILED';
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
