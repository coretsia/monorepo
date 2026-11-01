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

namespace Coretsia\Platform\Logging\Logger;

use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use Psr\Clock\ClockInterface;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * One PSR-3 logger and admission pipeline for fixed STDERR and daily UTC files.
 *
 * The factory supplies validated immutable configuration and selects exactly
 * one formatter at construction. File destinations must be absolute local
 * directories controlled by the trusted deployment administrator; the factory
 * validates their syntax without requiring filesystem availability.
 *
 * Every eligible file emission resolves the directory and checks the generated
 * destination before opening it. Symlink checks and fstat are defense in depth,
 * not race-free protection against concurrent adversarial filesystem changes.
 * Untrusted modification of the deployment-owned directory is unsupported.
 *
 * A failed or short write is discarded without retries, diagnostics, or raw
 * fallback. A prior partial write cannot be rolled back; no cross-filesystem
 * atomicity or fsync guarantee is claimed. No UoW state is retained.
 */
final readonly class StreamLogger implements LoggerInterface
{
    public const int MAX_RECORD_BYTES = 65536;

    /** @var array<string, int> */
    private const array LEVELS = [
        'debug' => 0,
        'info' => 1,
        'notice' => 2,
        'warning' => 3,
        'error' => 4,
        'critical' => 5,
        'alert' => 6,
        'emergency' => 7,
    ];

    private string $fileExtension;
    private mixed $stream;

    public function __construct(
        private LogRecordPolicy $policy,
        private ContextBagProcessor $processor,
        private JsonLineFormatter|LogfmtLineFormatter $formatter,
        private ClockInterface $clock,
        private string $minimumLevel,
        private int $maxRecordBytes,
        private string $serviceName,
        private string $appTarget,
        private string $driver = 'stream',
        private ?string $fileDirectory = null,
        private ?string $fileName = null,
    ) {
        if (
            !isset(self::LEVELS[$this->minimumLevel])
            || $this->maxRecordBytes < 1024
            || $this->maxRecordBytes > self::MAX_RECORD_BYTES
            || \preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $this->serviceName) !== 1
            || !\in_array($this->appTarget, ['web', 'api', 'console', 'worker'], true)
            || !\in_array($this->driver, ['stream', 'file'], true)
        ) {
            throw new \InvalidArgumentException('logging-runtime-settings-invalid');
        }

        $this->fileExtension = $this->formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';

        if ($this->driver === 'stream') {
            // Only the fixed internal STDERR destination may be opened here.
            $handle = @\fopen('php://stderr', 'ab');

            if (!\is_resource($handle)) {
                throw new \RuntimeException('logging-stderr-unavailable');
            }

            $this->stream = $handle;

            return;
        }

        if (
            $this->fileDirectory === null
            || $this->fileDirectory === ''
            || $this->fileName === null
            || \preg_match('/\A[a-z][a-z0-9_-]{0,63}\z/', $this->fileName) !== 1
        ) {
            throw new \InvalidArgumentException('logging-file-settings-invalid');
        }

        $this->stream = null;
    }

    /** @param array<string, mixed> $context */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log('emergency', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log('alert', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log('critical', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log('notice', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /** @param array<string, mixed> $context */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        // Invalid PSR-3 levels are caller errors, not internal I/O failures.
        if (!\is_string($level) || !isset(self::LEVELS[$level])) {
            throw new InvalidArgumentException('logging-level-invalid');
        }

        if (self::LEVELS[$level] < self::LEVELS[$this->minimumLevel]) {
            return;
        }

        try {
            $admittedMessage = $this->policy->admitMessage($message);
            $admittedContext = $this->policy->admitContext($context);
            $enriched = $this->processor->process($admittedContext);
            $record = [
                'ts_utc' => $this->timestamp(),
                'level' => $level,
                'message' => $admittedMessage,
                'service_name' => $this->serviceName,
                'app_target' => $this->appTarget,
                'correlation_id' => $enriched['correlation_id'],
                'uow_id' => $enriched['uow_id'],
                'uow_type' => $enriched['uow_type'],
                'context' => $enriched['context'],
            ];

            $redacted = $this->policy->redactRecord($record);

            if ($redacted === null) {
                return;
            }

            $serialized = $this->formatter->format($redacted);
            $bytes = \strlen($serialized);

            if ($bytes > $this->maxRecordBytes) {
                return;
            }

            if ($this->driver === 'stream') {
                // A single bounded attempt; short writes never trigger retry.
                $written = @\fwrite($this->stream, $serialized);

                if ($written !== $bytes) {
                    return;
                }

                return;
            }

            $date = $this->fileDate($redacted['ts_utc']);

            if ($date !== null) {
                $this->writeDailyFile($date, $serialized);
            }
        } catch (\Throwable) {
            // Classifier, redactor, formatter and I/O failures are no-op-safe.
            // No raw exception, path or original record is emitted or retained.
        }
    }

    private function timestamp(): ?string
    {
        try {
            return $this->clock->now()
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s.u\Z');
        } catch (\Throwable) {
            return null;
        }
    }

    private function fileDate(?string $timestamp): ?string
    {
        // The supplied timestamp has passed the canonical post-redaction schema.
        $date = $timestamp === null ? \gmdate('Y-m-d') : \substr($timestamp, 0, 10);

        if (
            \preg_match('/\A([0-9]{4})-(0[1-9]|1[0-2])-([0-2][0-9]|3[01])\z/', $date, $parts) !== 1
            || !\checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1])
        ) {
            return null;
        }

        return $date;
    }

    private function writeDailyFile(string $date, string $serialized): void
    {
        // File paths are validated by LoggingServiceFactory before construction.
        // Resolve availability afresh for each admitted, bounded emission.
        $directory = $this->fileDirectory;

        if ($directory === null || @\is_link($directory)) {
            return;
        }

        $canonical = @\realpath($directory);

        if ($canonical === false || !@\is_dir($canonical) || !@\is_writable($canonical)) {
            return;
        }

        $destination = \rtrim($canonical, '/\\')
            . \DIRECTORY_SEPARATOR
            . $this->appTarget . '-' . $this->fileName . '-' . $date . '.' . $this->fileExtension;

        // A symlink, directory, device, pipe or socket must not be opened.
        // PHP-level checks do not protect against an adversarial rename race.
        $existing = @\lstat($destination);

        if ($existing !== false && ($existing['mode'] & 0170000) !== 0100000) {
            return;
        }

        $handle = @\fopen($destination, 'ab');

        if (!\is_resource($handle)) {
            return;
        }

        $locked = false;

        try {
            $opened = @\fstat($handle);
            $current = @\lstat($destination);

            if (
                $opened === false
                || ($opened['mode'] & 0170000) !== 0100000
                || $current === false
                || ($current['mode'] & 0170000) !== 0100000
            ) {
                return;
            }

            $locked = @\flock($handle, \LOCK_EX | \LOCK_NB);

            if (!$locked) {
                return;
            }

            // No second write, truncation, retry, alternate sink or file rotation.
            $written = @\fwrite($handle, $serialized);

            if ($written !== \strlen($serialized)) {
                return;
            }
        } finally {
            if ($locked) {
                @\flock($handle, \LOCK_UN);
            }

            @\fclose($handle);
        }
    }
}
