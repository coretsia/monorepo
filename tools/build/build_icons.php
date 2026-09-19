#!/usr/bin/env php
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

use Coretsia\Tools\Support\ConsoleOutput;
use Coretsia\Tools\Support\DeterministicFile;
use Coretsia\Tools\Support\ErrorCodes;
use Coretsia\Tools\Support\RepositoryContext;

require_once __DIR__ . '/../support/ConsoleOutput.php';
require_once __DIR__ . '/../support/ErrorCodes.php';
require_once __DIR__ . '/../support/DeterministicException.php';
require_once __DIR__ . '/../support/DeterministicFile.php';
require_once __DIR__ . '/../support/RepositoryContext.php';

final class BuildIconsTool
{
    private const string BRANDING_DIR = 'docs/assets/branding/favicon';

    public static function main(array $argv): int
    {
        $repository = self::resolveRepository($argv);

        $check = self::argFlag($argv, '--check');
        $apply = self::argFlag($argv, '--apply') || !$check;

        $brandingDir = $repository->resolveExistingDirectory(self::BRANDING_DIR);
        $faviconSvg = $repository->resolveExistingFile($brandingDir . '/favicon.svg');
        $appleTouchSvg = $brandingDir . '/apple-touch-icon.svg';
        $microFaviconSvg = $brandingDir . '/coretsia-favicon-micro.svg';

        $microSourceSvg = is_file($microFaviconSvg)
            ? $repository->resolveExistingFile($microFaviconSvg)
            : $faviconSvg;

        $appleSourceSvg = is_file($appleTouchSvg)
            ? $repository->resolveExistingFile($appleTouchSvg)
            : $faviconSvg;

        $pngJobs = [
            [
                'target' => 'favicon-16x16.png',
                'source' => $microSourceSvg,
                'width' => 16,
                'height' => 16,
            ],
            [
                'target' => 'favicon-32x32.png',
                'source' => $microSourceSvg,
                'width' => 32,
                'height' => 32,
            ],
            [
                'target' => 'favicon-48x48.png',
                'source' => $microSourceSvg,
                'width' => 48,
                'height' => 48,
            ],
            [
                'target' => 'favicon-64x64.png',
                'source' => $microSourceSvg,
                'width' => 64,
                'height' => 64,
            ],
            [
                'target' => 'apple-touch-icon.png',
                'source' => $appleSourceSvg,
                'width' => 180,
                'height' => 180,
            ],
            [
                'target' => 'android-chrome-192x192.png',
                'source' => $faviconSvg,
                'width' => 192,
                'height' => 192,
            ],
            [
                'target' => 'android-chrome-512x512.png',
                'source' => $faviconSvg,
                'width' => 512,
                'height' => 512,
            ],
        ];

        /** @var array<string,string> $desiredFiles */
        $desiredFiles = [];

        /** @var array<string,string> $pngBytesByName */
        $pngBytesByName = [];

        foreach ($pngJobs as $job) {
            $targetName = (string) $job['target'];
            $source = (string) $job['source'];
            $width = (int) $job['width'];
            $height = (int) $job['height'];

            $pngBytes = self::renderSvgToPngBytes($source, $width, $height);

            $targetPath = $brandingDir . '/' . $targetName;
            $desiredFiles[$targetPath] = $pngBytes;
            $pngBytesByName[$targetName] = $pngBytes;
        }

        $icoBytes = self::buildIcoFromPngBytes([
            $pngBytesByName['favicon-16x16.png'],
            $pngBytesByName['favicon-32x32.png'],
            $pngBytesByName['favicon-48x48.png'],
            $pngBytesByName['favicon-64x64.png'],
        ]);

        $desiredFiles[$brandingDir . '/favicon.ico'] = $icoBytes;

        $changedFiles = [];

        foreach ($desiredFiles as $absPath => $bytes) {
            if (self::isDifferentBinaryFile($absPath, $bytes)) {
                $changedFiles[] = $repository->relativeToRepo($absPath);
            }
        }

        sort($changedFiles, SORT_STRING);

        if ($check) {
            if ($changedFiles !== []) {
                ConsoleOutput::codeWithDiagnostics(
                    ErrorCodes::CORETSIA_BUILD_ICONS_OUT_OF_DATE,
                    $changedFiles,
                );
                return 1;
            }

            ConsoleOutput::line('OK', false);
            return 0;
        }

        if ($apply) {
            foreach ($desiredFiles as $absPath => $bytes) {
                if (self::isDifferentBinaryFile($absPath, $bytes)) {
                    DeterministicFile::writeBytesExact($absPath, $bytes);
                }
            }
        }

        ConsoleOutput::line('OK', false);
        ConsoleOutput::lines($changedFiles, false);

        return 0;
    }

    private static function renderSvgToPngBytes(string $svgPath, int $width, int $height): string
    {
        $imagickBytes = self::renderViaImagick($svgPath, $width, $height);
        if ($imagickBytes !== null) {
            return $imagickBytes;
        }

        $rsvg = self::findExecutable('rsvg-convert');
        if ($rsvg !== null) {
            $bytes = self::renderViaRsvgConvert($rsvg, $svgPath, $width, $height);
            if ($bytes !== '') {
                return $bytes;
            }
        }

        throw new RuntimeException('Cannot render PNG: install Imagick or rsvg-convert');
    }

    private static function renderViaImagick(string $svgPath, int $width, int $height): ?string
    {
        if (!class_exists(\Imagick::class)) {
            return null;
        }

        $imagick = null;

        try {
            $imagick = new \Imagick();
            $imagick->setBackgroundColor(new \ImagickPixel('transparent'));
            $imagick->readImage($svgPath);

            // Canonical single-image output for each target artifact.
            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }

            $imagick->setImageFormat('png32');
            $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1, true);

            // Determinize PNG metadata/chunks.
            $imagick->stripImage();
            $imagick->setOption('png:exclude-chunk', 'date');

            $blob = $imagick->getImageBlob();
            if (!is_string($blob) || $blob === '') {
                return null;
            }

            $imagick->clear();

            return $blob;
        } catch (Throwable) {
            if ($imagick instanceof \Imagick) {
                $imagick->clear();
            }

            return null;
        }
    }

    private static function renderViaRsvgConvert(
        string $rsvgConvert,
        string $svgPath,
        int $width,
        int $height,
    ): string {
        $cmd = escapeshellarg($rsvgConvert)
            . ' -w '
            . (string) $width
            . ' -h '
            . (string) $height
            . ' '
            . escapeshellarg($svgPath);

        $descriptorSpec = [
            0 => ['pipe', 'rb'],
            1 => ['pipe', 'wb'],
            2 => ['pipe', 'wb'],
        ];

        $process = proc_open($cmd, $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Failed to start rsvg-convert');
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if (!is_string($stdout)) {
            $stdout = '';
        }
        if (!is_string($stderr)) {
            $stderr = '';
        }

        if ($exitCode !== 0 || $stdout === '') {
            throw new RuntimeException('rsvg-convert failed');
        }

        return $stdout;
    }

    /**
     * @param list<string> $pngImages
     */
    private static function buildIcoFromPngBytes(array $pngImages): string
    {
        if ($pngImages === []) {
            throw new RuntimeException('ICO requires at least one PNG image');
        }

        $count = count($pngImages);
        $iconDir = pack('vvv', 0, 1, $count);

        $entries = '';
        $images = '';
        $offset = 6 + ($count * 16);

        foreach ($pngImages as $pngBytes) {
            [$width, $height] = self::detectPngDimensions($pngBytes);

            if ($width !== $height) {
                throw new RuntimeException('ICO source PNG must be square');
            }

            $imageSize = strlen($pngBytes);

            $entries .= pack(
                'CCCCvvVV',
                $width >= 256 ? 0 : $width,
                $height >= 256 ? 0 : $height,
                0,
                0,
                1,
                32,
                $imageSize,
                $offset,
            );

            $images .= $pngBytes;
            $offset += $imageSize;
        }

        return $iconDir . $entries . $images;
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function detectPngDimensions(string $pngBytes): array
    {
        $size = getimagesizefromstring($pngBytes);
        if ($size === false) {
            throw new RuntimeException('Invalid PNG generated for icon');
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private static function isDifferentBinaryFile(string $path, string $bytes): bool
    {
        if (is_link($path) || !is_file($path)) {
            return true;
        }

        return DeterministicFile::readBytesExact($path) !== $bytes;
    }

    private static function findExecutable(string $name): ?string
    {
        $pathEnv = getenv('PATH');
        if (!is_string($pathEnv) || trim($pathEnv) === '') {
            return null;
        }

        $dirs = explode(PATH_SEPARATOR, $pathEnv);
        $isWindows = DIRECTORY_SEPARATOR === '\\';

        $candidates = [$name];

        if ($isWindows && pathinfo($name, PATHINFO_EXTENSION) === '') {
            $pathext = getenv('PATHEXT');
            $extensions = is_string($pathext) && trim($pathext) !== ''
                ? explode(';', $pathext)
                : ['.EXE', '.BAT', '.CMD'];

            $candidates = [];
            foreach ($extensions as $ext) {
                $ext = trim($ext);
                if ($ext === '') {
                    continue;
                }
                $candidates[] = $name . $ext;
            }
        }

        foreach ($dirs as $dir) {
            $dir = trim($dir, "\" \t\n\r\0\x0B");
            if ($dir === '') {
                continue;
            }

            foreach ($candidates as $candidate) {
                $path = rtrim(str_replace('\\', '/', $dir), '/') . '/' . $candidate;
                $path = str_replace('\\', '/', $path);

                if (!is_file($path)) {
                    continue;
                }

                if ($isWindows || is_executable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }

    private static function argFlag(array $argv, string $flag): bool
    {
        return in_array($flag, $argv, true);
    }

    private static function argRepoRoot(array $argv): ?string
    {
        $n = count($argv);

        for ($i = 0; $i < $n; $i++) {
            $a = (string) $argv[$i];

            if (str_starts_with($a, '--repo-root=')) {
                $v = trim(substr($a, strlen('--repo-root=')));
                return $v !== '' ? $v : null;
            }

            if ($a === '--repo-root') {
                $next = ($i + 1 < $n) ? trim((string) $argv[$i + 1]) : '';
                return $next !== '' ? $next : null;
            }
        }

        return null;
    }

    private static function resolveRepository(array $argv): RepositoryContext
    {
        $repoRoot = self::argRepoRoot($argv);

        return $repoRoot === null
            ? RepositoryContext::discoverFrom(__DIR__)
            : RepositoryContext::fromRepoRoot($repoRoot);
    }
}

try {
    exit(BuildIconsTool::main($argv));
} catch (Throwable) {
    ConsoleOutput::codeWithDiagnostics(
        ErrorCodes::CORETSIA_BUILD_ICONS_FAILED,
    );

    exit(1);
}
