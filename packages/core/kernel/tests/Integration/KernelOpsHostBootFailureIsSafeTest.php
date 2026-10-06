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

namespace Coretsia\Kernel\Tests\Integration;

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;
use PHPUnit\Framework\TestCase;

final class KernelOpsHostBootFailureIsSafeTest extends TestCase
{
    public function testPublicBootFailureSurfaceIsDeterministicAndSilent(): void
    {
        $root = \sys_get_temp_dir() . '/coretsia-kernel-ops-host-failure-' . \bin2hex(\random_bytes(8));
        $rawValue = 'raw-invalid-console-config-value';
        $previousMessage = 'raw-provider-or-factory-failure';
        $absolutePath = $root . '/private/config.php';

        \mkdir($root . '/config', 0777, true);
        \file_put_contents(
            $root . '/config/app.php',
            "<?php\n\nreturn ['debug' => '" . $rawValue . "'];\n",
        );

        \ob_start();

        try {
            try {
                new KernelOpsHostBooter()->boot(new KernelOpsHostInput($root));
                self::fail('Expected source-host boot failure.');
            } catch (KernelOpsFailedException $exception) {
                self::assertSame(KernelOpsFailedException::REASON_HOST_BOOT_FAILED, $exception->reason());
                self::assertSame('CORETSIA_KERNEL_OPS_FAILED: host-boot-failed', $exception->getMessage());
                self::assertNull($exception->getPrevious());

                foreach ([$root, $absolutePath, $rawValue, $previousMessage, 'Warning'] as $forbidden) {
                    self::assertStringNotContainsString($forbidden, $exception->getMessage());
                }
            }
        } finally {
            $output = (string) \ob_get_clean();
            self::removeTree($root);
        }

        self::assertSame('', $output);

        $source = self::source();

        self::assertMatchesRegularExpression(
            '/\$validation->isFailure\(\).*?ConfigInvalidException::fromValidationResult\(\$validation\)/s',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/\$sourceProviders\s*=\s*self::sourceProviders\(\$providerPlan\).*?\$finalBuilder\s*=\s*new ContainerBuilder/s',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/\$container\s*=\s*\$finalBuilder->build\(\);\s*\$ops\s*=\s*\$container->get\(KernelOpsInterface::class\)/s',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/catch\s*\(\\\\Throwable\)\s*\{\s*throw new KernelOpsFailedException\(\s*KernelOpsFailedException::REASON_HOST_BOOT_FAILED\s*\)/s',
            $source,
        );
        foreach (['STDOUT', 'STDERR', 'fwrite(', 'var_dump(', 'print_r('] as $diagnosticWrite) {
            self::assertStringNotContainsString($diagnosticWrite, $source);
        }
    }

    public function testPublicBootFailureEmitsNoStdoutOrStderrDiagnostics(): void
    {
        self::assertTrue(
            \function_exists('proc_open'),
            'proc_open() is required to capture source-host stdout and stderr.',
        );

        $root = \sys_get_temp_dir()
            . '/coretsia-kernel-ops-host-diagnostics-'
            . \bin2hex(\random_bytes(8));

        $autoloadPath = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        $scriptPath = $root . '/boot-failure-child.php';

        \mkdir($root . '/config', 0777, true);

        \file_put_contents(
            $root . '/config/app.php',
            "<?php\n\nreturn ['debug' => 'raw-invalid-console-config-value'];\n",
        );

        $script = <<<'PHP'
<?php

declare(strict_types=1);

require $argv[1];

use Coretsia\Contracts\Kernel\Ops\Exception\KernelOpsFailedException;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Ops\KernelOpsHostInput;

try {
    new KernelOpsHostBooter()->boot(
        new KernelOpsHostInput($argv[2]),
    );

    exit(91);
} catch (KernelOpsFailedException $exception) {
    exit(
        $exception->reason() === KernelOpsFailedException::REASON_HOST_BOOT_FAILED
        && $exception->getPrevious() === null
            ? 0
            : 92,
    );
}
PHP;

        try {
            self::assertFileExists($autoloadPath);
            self::assertIsInt(
                \file_put_contents($scriptPath, $script),
            );

            $process = \proc_open(
                [
                    \PHP_BINARY,
                    $scriptPath,
                    $autoloadPath,
                    $root,
                ],
                [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ],
                $pipes,
            );

            self::assertIsResource($process);
            self::assertIsArray($pipes);

            \fclose($pipes[0]);

            $stdout = \stream_get_contents($pipes[1]);
            $stderr = \stream_get_contents($pipes[2]);

            \fclose($pipes[1]);
            \fclose($pipes[2]);

            $exitCode = \proc_close($process);

            self::assertSame(
                0,
                $exitCode,
                (string) $stderr . (string) $stdout,
            );
            self::assertSame('', $stdout);
            self::assertSame('', $stderr);
        } finally {
            self::removeTree($root);
        }
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');

        self::assertIsString($source);

        return $source;
    }

    private static function removeTree(string $path): void
    {
        if (!\is_dir($path)) {
            return;
        }

        foreach (\scandir($path) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;

            if (\is_dir($itemPath)) {
                self::removeTree($itemPath);
            } else {
                @\unlink($itemPath);
            }
        }

        @\rmdir($path);
    }
}
