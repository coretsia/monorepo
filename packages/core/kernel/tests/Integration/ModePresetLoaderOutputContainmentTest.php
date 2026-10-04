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

use Coretsia\Contracts\Module\ModePresetLoaderInterface;
use Coretsia\Kernel\Module\Exception\ModePresetInvalidException;
use Coretsia\Kernel\Module\Exception\ModuleErrorCodes;
use Coretsia\Kernel\Module\Preset\PresetNamespace;
use Coretsia\Kernel\Tests\Support\ModeInfrastructureTestSupport;
use PHPUnit\Framework\TestCase;

final class ModePresetLoaderOutputContainmentTest extends TestCase
{
    private const string PRESET_NAME = 'worker-only';

    public function testSilentValidSourcePreservesSuccessfulLoadingAndCallerState(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        $callerHandlerInvocations = 0;

        \set_error_handler(static function () use (&$callerHandlerInvocations): bool {
            ++$callerHandlerInvocations;
            return true;
        });
        \ob_start();

        try {
            ModeInfrastructureTestSupport::writePreset(
                self::presetFile($root),
                self::PRESET_NAME,
            );

            echo 'caller-before';

            $preset = self::loader($root)->load(self::PRESET_NAME);

            self::assertSame(self::PRESET_NAME, $preset->name());
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
            self::assertSame(0, $callerHandlerInvocations);

            \trigger_error('caller-handler-after-silent-load', E_USER_WARNING);

            self::assertSame(1, $callerHandlerInvocations);
        } finally {
            \restore_error_handler();
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testOrdinaryOutputIsDiscardedWithoutChangingSuccessfulLoad(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo "unexpected-output"; return ' . self::payloadExport() . ';',
            );

            echo 'caller-before';

            $preset = self::loader($root)->load(self::PRESET_NAME);

            self::assertSame(self::PRESET_NAME, $preset->name());
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testOrdinaryOutputIsDiscardedIncrementallyAcrossMultipleChunks(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo str_repeat("A", 8192); '
                . 'echo str_repeat("B", 8192); '
                . 'echo str_repeat("C", 8192); '
                . 'return ' . self::payloadExport() . ';',
            );

            echo 'caller-before';

            $preset = self::loader($root)->load(self::PRESET_NAME);

            self::assertSame(self::PRESET_NAME, $preset->name());
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testWhitespaceOnlyOutputIsDiscardedWithoutChangingSuccessfulLoad(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo " \\n\\t"; return ' . self::payloadExport() . ';',
            );

            echo 'caller-before';

            $preset = self::loader($root)->load(self::PRESET_NAME);

            self::assertSame(self::PRESET_NAME, $preset->name());
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testBufferedOutputDoesNotChangeValidatorSpecificFailureReasonOrDiagnostics(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        $outputMarker = 'SENSITIVE-OUTPUT-MARKER';
        $payload = ModeInfrastructureTestSupport::payload(self::PRESET_NAME);
        $payload['schemaVersion'] = 2;
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo ' . \var_export($outputMarker, true) . '; return ' . \var_export($payload, true) . ';',
            );

            echo 'caller-before';

            try {
                self::loader($root)->load(self::PRESET_NAME);
                self::fail('Expected invalid preset schema version.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID, $exception->errorCode());
                self::assertSame(
                    ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID,
                    $exception->reason(),
                );
                self::assertSame(['preset' => self::PRESET_NAME], $exception->context());
                self::assertSame(
                    ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID
                    . ': '
                    . ModePresetInvalidException::REASON_SCHEMA_VERSION_INVALID,
                    $exception->getMessage(),
                );
                self::assertNull($exception->getPrevious());
                self::assertMarkerAbsentFromDiagnostics($exception, $outputMarker);
            }

            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testOutputBeforePhpDiagnosticDoesNotEscapeOrDelegateToCallerHandler(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        $outputMarker = 'SOURCE-OUTPUT-MARKER';
        $diagnosticMarker = 'SOURCE-DIAGNOSTIC-MARKER';
        $callerHandlerMessages = [];

        \set_error_handler(static function (int $_severity, string $message) use (&$callerHandlerMessages): bool {
            $callerHandlerMessages[] = $message;
            return true;
        });
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo ' . \var_export($outputMarker, true) . '; '
                . 'trigger_error(' . \var_export($diagnosticMarker, true) . ', E_USER_WARNING); '
                . 'return ' . self::payloadExport() . ';',
            );

            echo 'caller-before';

            try {
                self::loader($root)->load(self::PRESET_NAME);
                self::fail('Expected callback-handled PHP diagnostic to invalidate the preset source.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID, $exception->errorCode());
                self::assertSame(ModePresetInvalidException::REASON_PRESET_INVALID, $exception->reason());
                self::assertSame(['preset' => self::PRESET_NAME], $exception->context());
                self::assertNull($exception->getPrevious());
                self::assertMarkerAbsentFromDiagnostics($exception, $outputMarker);
                self::assertMarkerAbsentFromDiagnostics($exception, $diagnosticMarker);
            }

            self::assertSame([], $callerHandlerMessages);
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());

            \trigger_error('caller-handler-after-loader', E_USER_WARNING);

            self::assertSame(['caller-handler-after-loader'], $callerHandlerMessages);
        } finally {
            \restore_error_handler();
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testOutputBeforeThrowableDoesNotEscapeOrChangeGenericInvalidSourceSemantics(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        $outputMarker = 'SOURCE-OUTPUT-MARKER';
        $throwableMarker = 'SOURCE-THROWABLE-MARKER';
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo ' . \var_export($outputMarker, true) . '; '
                . 'throw new \\RuntimeException(' . \var_export($throwableMarker, true) . ');',
            );

            echo 'caller-before';

            try {
                self::loader($root)->load(self::PRESET_NAME);
                self::fail('Expected source Throwable to invalidate the preset source.');
            } catch (ModePresetInvalidException $exception) {
                self::assertSame(ModuleErrorCodes::CORETSIA_MODE_PRESET_INVALID, $exception->errorCode());
                self::assertSame(ModePresetInvalidException::REASON_PRESET_INVALID, $exception->reason());
                self::assertSame(['preset' => self::PRESET_NAME], $exception->context());
                self::assertNull($exception->getPrevious());
                self::assertMarkerAbsentFromDiagnostics($exception, $outputMarker);
                self::assertMarkerAbsentFromDiagnostics($exception, $throwableMarker);
            }

            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    public function testTryLoadContainsOutputAndReturnsExistingValidPreset(): void
    {
        $root = ModeInfrastructureTestSupport::root();
        $baseOutputBufferLevel = \ob_get_level();
        \ob_start();

        try {
            self::writeSource(
                $root,
                'echo "try-load-output"; return ' . self::payloadExport() . ';',
            );

            echo 'caller-before';

            $preset = self::loader($root)->tryLoad(self::PRESET_NAME);

            self::assertNotNull($preset);
            self::assertSame(self::PRESET_NAME, $preset->name());
            self::assertSame($baseOutputBufferLevel + 1, \ob_get_level());
            self::assertSame('caller-before', \ob_get_contents());
        } finally {
            self::discardTestOutputBuffersAbove($baseOutputBufferLevel);
            ModeInfrastructureTestSupport::remove($root);
        }
    }

    private static function loader(string $root): ModePresetLoaderInterface
    {
        $package = $root . '/kernel';
        $application = $root . '/application';

        if (!\is_dir($package)) {
            \mkdir($package, 0777, true);
        }
        if (!\is_dir($application)) {
            \mkdir($application, 0777, true);
        }

        return ModeInfrastructureTestSupport::factory($package)->createFor(
            ModeInfrastructureTestSupport::bootstrap($application, self::PRESET_NAME),
            PresetNamespace::Custom,
        );
    }

    private static function presetFile(string $root): string
    {
        return $root . '/application/config/modes/' . self::PRESET_NAME . '.php';
    }

    private static function writeSource(string $root, string $body): void
    {
        ModeInfrastructureTestSupport::write(
            self::presetFile($root),
            '<?php ' . $body,
        );
    }

    private static function payloadExport(): string
    {
        return \var_export(ModeInfrastructureTestSupport::payload(self::PRESET_NAME), true);
    }

    private static function assertMarkerAbsentFromDiagnostics(
        ModePresetInvalidException $exception,
        string $marker,
    ): void {
        self::assertStringNotContainsString($marker, $exception->errorCode());
        self::assertStringNotContainsString($marker, $exception->reason());
        self::assertStringNotContainsString($marker, $exception->getMessage());
        self::assertStringNotContainsString($marker, \json_encode($exception->context(), JSON_THROW_ON_ERROR));
    }

    private static function discardTestOutputBuffersAbove(int $baseOutputBufferLevel): void
    {
        while (\ob_get_level() > $baseOutputBufferLevel) {
            if (!@\ob_end_clean()) {
                break;
            }
        }
    }
}
