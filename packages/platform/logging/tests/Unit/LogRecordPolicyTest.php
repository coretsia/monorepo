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

namespace Coretsia\Platform\Logging\Tests\Unit;

use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Processor\ContextBagProcessor;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

final class LogRecordPolicyTest extends TestCase
{
    public function testSafeCoreOperationalSummariesSurviveCanonicalSinkAdmission(): void
    {
        [$policy] = self::fixture();
        $context = [
            'operation' => 'config:compile',
            'outcome' => 'success',
            'event' => 'boot',
            'reason' => 'cache_stale',
            'code' => 'CORETSIA_BOOT_READY',
            'status' => 'ready',
            'preset' => 'micro',
            'presetName' => 'express',
            'app_target' => 'console',
            'services_count' => 12,
            'groups_count' => 3,
            'duration_ms' => 0,
            'exit_code' => 0,
            'byte_count' => 64,
            'artifact_count' => 2,
            'moduleIds' => ['platform.logging', 'core.foundation', 'platform.redaction'],
            'artifact_basename' => 'container.php',
            'artifact_path' => 'var/cache/console/container.php',
            'artifact_paths' => ['var/cache/z.php', 'var/cache/a.php'],
            'artifact_reasons' => ['config.php:stale', 'container.php:missing'],
            'source_paths' => ['config/logging.php', 'src/Kernel.php'],
            'source_kinds' => ['runtime', 'package'],
            'source_layers' => ['platform', 'core'],
            'source_types' => ['config', 'source'],
            'directive_names' => ['modules', 'providers'],
            'bucket_names' => ['runtime', 'kernel'],
        ];
        $admitted = $policy->admitContext($context);
        self::assertIsArray($admitted);
        self::assertSame(\count($context), \count($admitted));
        foreach ($context as $key => $value) {
            self::assertArrayHasKey($key, $admitted);
            if (!\in_array(
                $key,
                [
                    'moduleIds',
                    'artifact_paths',
                    'artifact_reasons',
                    'source_paths',
                    'source_kinds',
                    'source_layers',
                    'source_types',
                    'directive_names',
                    'bucket_names',
                ],
                true,
            )) {
                self::assertSame($value, $admitted[$key]);
            }
        }
        self::assertSame(['core.foundation', 'platform.logging', 'platform.redaction'], $admitted['moduleIds']);
        self::assertSame(['var/cache/a.php', 'var/cache/z.php'], $admitted['artifact_paths']);
        self::assertSame(['config/logging.php', 'src/Kernel.php'], $admitted['source_paths']);
        self::assertSame(['core', 'platform'], $admitted['source_layers']);
        self::assertSame(['config.php:stale', 'container.php:missing'], $admitted['artifact_reasons']);
        self::assertSame(['kernel', 'runtime'], $admitted['bucket_names']);
        self::assertSame(['modules', 'providers'], $admitted['directive_names']);
        self::assertSame(['package', 'runtime'], $admitted['source_kinds']);
        self::assertSame(['config', 'source'], $admitted['source_types']);
        self::assertSame(
            [
                'app_target',
                'artifact_basename',
                'artifact_count',
                'artifact_path',
                'artifact_paths',
                'artifact_reasons',
                'bucket_names',
                'byte_count',
                'code',
                'directive_names',
                'duration_ms',
                'event',
                'exit_code',
                'groups_count',
                'moduleIds',
                'operation',
                'outcome',
                'preset',
                'presetName',
                'reason',
                'services_count',
                'source_kinds',
                'source_layers',
                'source_paths',
                'source_types',
                'status',
            ],
            \array_keys($admitted),
        );
    }

    public function testWorkerSummariesRespectOwnerApprovedTypesAndBounds(): void
    {
        [$policy] = self::fixture();
        self::assertSame(
            [
            'control_transport' => 'stdio',
            'driver' => 'process',
            'endpoint_hash' => \str_repeat('a', 64),
            'pid' => 3456,
            'ready_worker_count' => 2,
            'status' => 'ready',
            'worker_count' => 4,
        ],
            $policy->admitContext([
                'status' => 'ready',
                'driver' => 'process',
                'control_transport' => 'stdio',
                'pid' => 3456,
                'worker_count' => 4,
                'ready_worker_count' => 2,
                'endpoint_hash' => \str_repeat('a', 64),
            ])
        );
        self::assertNull($policy->admitContext(['pid' => 0, 'endpoint_hash' => \str_repeat('g', 64)]));
        self::assertNull($policy->admitContext(['worker_count' => -1, 'exit_code' => 256]));
    }

    public function testUnknownRawAndUnsupportedValuesAreOmittedWithoutEvaluatingObjects(): void
    {
        [$policy] = self::fixture();
        $evaluations = 0;
        $stringable = new class($evaluations) implements \Stringable {
            public function __construct(private int &$evaluations)
            {
            }

            public function __toString(): string
            {
                ++$this->evaluations;
                throw new \RuntimeException('private-credential');
            }
        };
        $resource = \fopen('php://memory', 'rb');
        self::assertIsResource($resource);

        try {
            $admitted = $policy->admitContext([
                'operation' => 'boot',
                'password' => 'private-credential',
                'raw_path' => '/private/user/42',
                'authorization' => 'Bearer private-credential',
                'artifact_path' => '/var/private/secret',
                'source_paths' => ['../private/secret'],
                'path_template' => '/user/42',
                'duration_ms' => 2.25,
                'worker_count' => $stringable,
                'driver' => $resource,
                'status' => new \stdClass(),
                'code' => new \RuntimeException('private-credential'),
                'reason' => ['nested' => 'untrusted'],
            ]);
        } finally {
            \fclose($resource);
        }

        self::assertSame(['operation' => 'boot'], $admitted);
        self::assertSame(0, $evaluations);
        self::assertSame('logging.message_rejected', $policy->admitMessage($stringable));
        self::assertSame(0, $evaluations);
        self::assertNull($policy->admitContext(['unknown' => 'unsafe']));
        self::assertNull($policy->admitContext([]));
    }

    public function testStaticEventGrammarAndProducerOwnedCategoryTokensRemainDistinct(): void
    {
        [$policy] = self::fixture();
        foreach (['kernel.boot', 'worker.output_format', 'foundation-reset', 'cli.command'] as $event) {
            self::assertSame($event, $policy->admitMessage($event));
        }
        foreach (
            [
                '',
                'bad event',
                'invalid:event',
                'Bad.Event',
                'a..b',
                \str_repeat('a', 129),
                "bad\nrecord",
            ] as $message
        ) {
            self::assertSame('logging.message_rejected', $policy->admitMessage($message));
        }
        self::assertSame(['operation' => 'worker:restart'], $policy->admitContext(['operation' => 'worker:restart']));
        foreach (
            [
                'curl https://host',
                'user@example.org',
                'user/../secret',
                \str_repeat('a', 97),
                'UPPER',
                '',
            ] as $category
        ) {
            self::assertNull($policy->admitContext(['operation' => $category]));
        }
        // Lexical validity is not evidence of static producer ownership.
        self::assertSame('public_token', $policy->admitMessage('public_token'));
    }

    public function testCompletePathSegmentIndexAndPlaceholderClassificationUseInjectedPort(): void
    {
        [$policy, , $classifier] = self::fixture(classify: static fn (string $key): ?RedactionKind => match ($key) {
            'password', 'client_secret', 'access_token', 'email_address' => RedactionKind::Secret,
            default => null,
        });
        foreach (
            [
                'db.password',
                'db.client_secret',
                'db.access_token',
                'user.email_address',
                'db.password[0]',
                'db.client_secret[3]',
                'user.<password>',
                'db.@access_token',
            ] as $path
        ) {
            $result = $policy->admitContext(['key_paths' => ['system.logging', $path], 'status' => 'ready']);
            self::assertSame(['status' => 'ready'], $result);
            self::assertNotContains($path, $result['key_paths'] ?? []);
        }
        self::assertContains('db.password', $classifier->keys);
        self::assertContains('password', $classifier->keys);
        self::assertContains('password[0]', $classifier->keys);
        self::assertContains('password', $classifier->keys);
        self::assertContains('<password>', $classifier->keys);
        self::assertContains('client_secret[3]', $classifier->keys);
    }

    public function testClassifierReceivesCompletePathBeforeSegmentsAndStructuralComponents(): void
    {
        [$policy, , $classifier] = self::fixture();
        $admitted = $policy->admitContext(['key_paths' => ['db.table[0].<field>']]);
        self::assertSame(['key_paths' => ['db.table[0].<field>']], $admitted);
        self::assertSame(
            ['db.table[0].<field>', 'db', 'table[0]', 'table', '0', '<field>', 'field'],
            $classifier->keys,
        );
    }

    public function testSafePathsAndPlaceholdersSurviveWithoutDuplicatedAliasKnowledge(): void
    {
        [$policy] = self::fixture();
        self::assertSame(
            [
            'key_paths' => ['db.rows[0].<column>', 'logging.driver', 'user.<name>', 'worker.output.format'],
        ],
            $policy->admitContext([
                'key_paths' => [
                    'worker.output.format',
                    'logging.driver',
                    'db.rows[0].<column>',
                    'user.<name>',
                    'logging.driver',
                ],
            ])
        );
        foreach (
            [
                'db..password',
                '.db',
                'db.',
                'db.[[]',
                'db/safe',
                'db.secret:value',
                \str_repeat('x', 161),
            ] as $path
        ) {
            self::assertNull($policy->admitContext(['key_paths' => [$path]]));
        }
        self::assertNull($policy->admitContext(['key_paths' => ['db..broken', 'logging.driver']]));
    }

    public function testClassifierNullNeverOverridesLoggingStructuralLimits(): void
    {
        [$policy] = self::fixture(classify: static fn (string $key): ?RedactionKind => null);
        self::assertNull($policy->admitContext(['key_paths' => ['../../secret']]));
        self::assertNull($policy->admitContext(['key_paths' => ['db..field']]));
        self::assertNull($policy->admitContext(['key_paths' => \array_fill(0, 21, 'safe.value')]));
        self::assertNull($policy->admitContext(['source_paths' => ['/etc/passwd']]));
        self::assertNull($policy->admitContext(['artifact_path' => '../private.php']));
        self::assertNull($policy->admitContext(['path_template' => '/users/1234']));
    }

    public function testBoundariesCardinalitySortingAndUniqueLists(): void
    {
        [$policy] = self::fixture();
        self::assertSame(['duration_ms' => 4_294_967_295, 'worker_count' => 1_000_000_000], $policy->admitContext([
            'duration_ms' => 4_294_967_295,
            'worker_count' => 1_000_000_000,
        ]));
        self::assertNull($policy->admitContext(['duration_ms' => 4_294_967_296]));
        self::assertNull($policy->admitContext(['worker_count' => 1_000_000_001]));
        self::assertNull($policy->admitContext(['moduleIds' => \array_fill(0, 25, 'core.kernel')]));
        self::assertNull($policy->admitContext(['key_paths' => \array_fill(0, 21, 'kernel.boot')]));
        self::assertNull($policy->admitContext(['operation' => \str_repeat('a', 97)]));
        self::assertNull($policy->admitContext(['artifact_path' => \str_repeat('a', 513)]));
        self::assertNull($policy->admitContext(\array_fill_keys(\range(1, 49), 'ignored')));
        self::assertSame(['moduleIds' => ['core.foundation', 'core.kernel']], $policy->admitContext([
            'moduleIds' => ['core.kernel', 'core.foundation', 'core.kernel'],
        ]));
        self::assertTrue(
            LogRecordPolicy::isValidCanonicalRecord(
                self::record(['context' => ['moduleIds' => ['core.foundation', 'core.kernel']]]),
            ),
        );
        self::assertFalse(
            LogRecordPolicy::isValidCanonicalRecord(
                self::record(['context' => ['moduleIds' => ['core.kernel', 'core.foundation']]]),
            ),
        );
    }

    public function testRedactionRunsOnceOnWholeRecordWithMandatoryPlaceholderMode(): void
    {
        [$policy, $redactor] = self::fixture(transform: static function (array $record): array {
            if ($record['message'] === 'token.sample') {
                $record['message'] = 'logging.message_rejected';
            }

            return $record;
        });
        $admitted = self::record(['message' => 'token.sample', 'context' => ['operation' => 'cli:list']]);
        $result = $policy->redactRecord($admitted);
        self::assertIsArray($result);
        self::assertSame('logging.message_rejected', $result['message']);
        self::assertSame(['operation' => 'cli:list'], $result['context']);
        self::assertSame(1, $redactor->calls);
        self::assertSame([$admitted], $redactor->inputs);
        self::assertCount(1, $redactor->contexts);
        self::assertSame('logging.record', $redactor->contexts[0]->scope());
        self::assertSame(RedactionMode::Placeholder, $redactor->contexts[0]->mode());
        self::assertSame(1, $redactor->contexts[0]->schemaVersion());
    }

    public function testRedactionFailureAndMalformedResultFailClosedWithoutFallback(): void
    {
        [$policy, $redactor] = self::fixture(transform: static function (): never {
            throw new \RuntimeException('private-credential');
        });
        self::assertNull($policy->redactRecord(self::record()));
        self::assertSame(1, $redactor->calls);

        foreach (
            [
                null,
                'private-credential',
                ['message' => 'partial'],
                self::record(['message' => 'unsafe text']),
            ] as $unsafe
        ) {
            [$instance, $spy] = self::fixture(transform: static fn (): mixed => $unsafe);
            self::assertNull($instance->redactRecord(self::record()));
            self::assertSame(1, $spy->calls);
        }
    }

    public function testDirectRedactRecordCannotBypassInjectedSensitivePathAdmission(): void
    {
        [$policy, $redactor] = self::fixture(classify: static fn (
            string $key,
        ): ?RedactionKind => $key === 'password' ? RedactionKind::Secret : null);
        $record = self::record(['context' => ['key_paths' => ['db.password']]]);

        self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($record));
        self::assertNull($policy->redactRecord($record));
        self::assertSame(0, $redactor->calls);
    }

    public function testPostRedactionSensitivePathIsRejectedWithoutASecondRedaction(): void
    {
        [$policy, $redactor] = self::fixture(
            transform: static function (array $record): array {
                $record['context']['key_paths'] = ['db.password[0]'];
                return $record;
            },
            classify: static fn (string $key): ?RedactionKind => $key === 'password' ? RedactionKind::Secret : null,
        );
        self::assertNull($policy->redactRecord(self::record(['context' => ['key_paths' => ['db.table']]])));
        self::assertSame(1, $redactor->calls);
    }

    public function testPostRedactionValidationRejectsInvalidServiceIdentity(): void
    {
        foreach (
            [
                ['service_name' => 'invalid.name'],
                ['app_target' => 'unselected'],
                ['service_name' => null],
            ] as $change
        ) {
            [$policy, $redactor] = self::fixture(
                transform: static fn (array $record): array => \array_replace($record, $change),
            );

            self::assertNull($policy->redactRecord(self::record()));
            self::assertSame(1, $redactor->calls);
        }
    }

    public function testClassifierThrowIsNotConfusedWithOrdinaryContextOmission(): void
    {
        [$policy, $redactor] = self::fixture(classify: static function (): never {
            throw new \RuntimeException('classified-private-path');
        });
        self::assertNull($policy->admitContext([]));
        self::assertNull($policy->admitContext(['unknown' => 'value']));

        try {
            $policy->admitContext(['key_paths' => ['db.table']]);
            self::fail('Classifier failure must propagate to the logger no-throw boundary.');
        } catch (\RuntimeException $exception) {
            self::assertSame('classified-private-path', $exception->getMessage());
        }
        self::assertSame(0, $redactor->calls);
    }

    public function testInitialClassifierFailureDoesNotRedactOrWriteOrCaptureTimestamp(): void
    {
        [$policy, $redactor] = self::fixture(classify: static function (): never {
            throw new \RuntimeException('must-not-be-emitted');
        });
        self::assertFailedLoggerEmission($policy, $redactor, ['key_paths' => ['db.table']], 0);
    }

    public function testPostRedactionClassifierFailureDoesNotWriteOrRetryRedaction(): void
    {
        [$policy, $redactor] = self::fixture(
            transform: static function (array $record): array {
                $record['context']['key_paths'] = ['db.secret[0]'];
                return $record;
            },
            classify: static function (string $key): ?RedactionKind {
                if ($key === 'secret') {
                    throw new \RuntimeException('must-not-be-emitted');
                }

                return null;
            },
        );
        self::assertFailedLoggerEmission($policy, $redactor, ['key_paths' => ['db.table']], 1);
    }

    public function testSharedRedactionControlsBothFormatterOutputs(): void
    {
        $directory = \sys_get_temp_dir() . '/coretsia-logging-redacted-message-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($directory, 0700));

        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $formatter) {
                [$policy, $redactor] = self::fixture(transform: static function (array $record): array {
                    if ($record['message'] === 'token.sample') {
                        $record['message'] = 'logging.message_rejected';
                    }

                    return $record;
                });
                $clock = new class() implements ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2026-10-31T12:30:40Z');
                    }
                };
                $logger = new StreamLogger(
                    $policy,
                    self::processor(),
                    $formatter,
                    $clock,
                    'debug',
                    65536,
                    'web',
                    'web',
                    'file',
                    $directory,
                    'app',
                );
                $extension = $formatter instanceof JsonLineFormatter ? 'jsonl' : 'logfmt';
                $path = $directory . '/web-app-2026-10-31.' . $extension;

                $logger->info('token.sample', ['operation' => 'boot']);
                self::assertFileExists($path);
                $bytes = \file_get_contents($path);
                self::assertIsString($bytes);
                self::assertSame(1, \substr_count($bytes, "\n"));
                self::assertStringContainsString('logging.message_rejected', $bytes);
                self::assertStringNotContainsString('token.sample', $bytes);
                self::assertSame(1, $redactor->calls);
            }
        } finally {
            foreach (\glob($directory . '/*') ?: [] as $path) {
                \unlink($path);
            }
            \rmdir($directory);
        }
    }

    public function testClassifierFailuresNeverLeakIntoTheFixedStderrStream(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        if (!\is_file($autoload)) {
            $autoload = \dirname(__DIR__, 2) . '/vendor/autoload.php';
        }
        self::assertFileExists($autoload);

        $script = <<<'PHP'
require $argv[1];

$phase = $argv[2];
$format = $argv[3];
$redactor = new class($phase) implements \Coretsia\Contracts\Security\SensitiveDataRedactorInterface {
    public int $calls = 0;

    public function __construct(private string $phase)
    {
    }

    public function redactValue(
        string $value,
        \Coretsia\Contracts\Security\RedactionKind $kind,
        \Coretsia\Contracts\Security\RedactionContext $context,
    ): \Coretsia\Contracts\Security\RedactedValue {
        throw new \LogicException('redactValue-not-used');
    }

    public function redactJsonLike(
        mixed $value,
        \Coretsia\Contracts\Security\RedactionContext $context,
    ): mixed {
        ++$this->calls;

        if ($this->phase === 'redaction') {
            throw new \RuntimeException('private-redactor-error');
        }

        if ($this->phase === 'post') {
            $value['context']['key_paths'] = ['db.secret[0]'];
        }

        return $value;
    }
};
$classifier = new class($phase) implements \Coretsia\Contracts\Security\SensitiveKeyClassifierInterface {
    public function __construct(private string $phase)
    {
    }

    public function classify(string $key): ?\Coretsia\Contracts\Security\RedactionKind
    {
        if (($this->phase === 'initial' && $key === 'db.table')
            || ($this->phase === 'post' && $key === 'secret')) {
            throw new \RuntimeException('private-classifier-error');
        }

        return null;
    }
};
$clock = new class implements \Psr\Clock\ClockInterface {
    public int $calls = 0;

    public function now(): \DateTimeImmutable
    {
        ++$this->calls;
        return new \DateTimeImmutable('2026-10-31T12:30:40Z');
    }
};
$processor = new \Coretsia\Platform\Logging\Processor\ContextBagProcessor(
    new class implements \Coretsia\Contracts\Context\ContextAccessorInterface {
        public function has(string $key): bool { return false; }
        public function get(string $key): mixed { throw new \LogicException('no-context'); }
    },
    new class implements \Coretsia\Contracts\Observability\CorrelationIdProviderInterface {
        public function correlationId(): ?string { return null; }
    },
);
$formatter = $format === 'jsonl'
    ? new \Coretsia\Platform\Logging\Formatter\JsonLineFormatter()
    : new \Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter();
$logger = new \Coretsia\Platform\Logging\Logger\StreamLogger(
    new \Coretsia\Platform\Logging\Record\LogRecordPolicy($redactor, $classifier),
    $processor,
    $formatter,
    $clock,
    'debug',
    65536,
    'web',
    'web',
);
$logger->info('kernel.boot', ['key_paths' => ['db.table']]);
echo \json_encode(['redactions' => $redactor->calls, 'clock_reads' => $clock->calls], \JSON_THROW_ON_ERROR);
PHP;

        foreach (['initial' => 0, 'post' => 1, 'redaction' => 1] as $phase => $expectedRedactions) {
            foreach (['jsonl', 'logfmt'] as $format) {
                $process = \proc_open(
                    [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, '--', $autoload, $phase, $format],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
                self::assertIsResource($process);
                \fclose($pipes[0]);
                $stdout = \stream_get_contents($pipes[1]);
                $stderr = \stream_get_contents($pipes[2]);
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                $exit = \proc_close($process);

                self::assertSame(0, $exit, (string) $stderr);
                self::assertSame('', $stderr, $phase . '/' . $format);
                self::assertSame(
                    ['redactions' => $expectedRedactions, 'clock_reads' => $expectedRedactions],
                    \json_decode((string) $stdout, true, 512, \JSON_THROW_ON_ERROR),
                );
            }
        }
    }

    public function testCallerIdentifiersRemainNonAuthoritativeAndRootIdentitySurvivesOutsideUow(): void
    {
        [$policy] = self::fixture();
        $callerId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $context = $policy->admitContext([
            'correlation_id' => $callerId,
            'uow_id' => 'caller:123',
            'uow_type' => 'cli',
            'app_target' => 'api',
            'operation' => 'list',
            'service_name' => 'spoof',
        ]);
        self::assertSame([
            'app_target' => 'api',
            'correlation_id' => $callerId,
            'operation' => 'list',
            'uow_id' => 'caller:123',
            'uow_type' => 'cli',
        ], $context);
        $processor = self::processor();
        $processed = $processor->process($context);
        self::assertNull($processed['correlation_id']);
        self::assertNull($processed['uow_id']);
        self::assertNull($processed['uow_type']);
        self::assertSame($context, $processed['context']);

        $record = self::record([
            'context' => $processed['context'],
            'correlation_id' => $processed['correlation_id'],
            'uow_id' => $processed['uow_id'],
            'uow_type' => $processed['uow_type'],
        ]);
        self::assertSame('web', $policy->redactRecord($record)['service_name']);
        self::assertSame('web', $policy->redactRecord($record)['app_target']);
        self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($record));
    }

    public function testActualLoggerKeepsCallerIdentifiersNestedAndServiceIdentityCanonical(): void
    {
        [$policy, $redactor] = self::fixture();
        $directory = \sys_get_temp_dir() . '/coretsia-logging-identity-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($directory, 0700));
        $clock = new class() implements ClockInterface {
            public function now(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-10-31T12:30:40.123456Z');
            }
        };
        $logger = new StreamLogger(
            $policy,
            self::processor(),
            new JsonLineFormatter(),
            $clock,
            'info',
            65536,
            'web',
            'web',
            'file',
            $directory,
            'app',
        );
        $path = $directory . '/web-app-2026-10-31.jsonl';

        try {
            $logger->info('kernel.boot', [
                'operation' => 'boot',
                'app_target' => 'api',
                'service_name' => 'spoof',
                'correlation_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                'uow_id' => 'caller:123',
                'uow_type' => 'cli',
            ]);
            self::assertFileExists($path);
            $bytes = \file_get_contents($path);
            self::assertIsString($bytes);
            self::assertSame(1, \substr_count($bytes, "\n"));
            $record = \json_decode($bytes, true, 512, \JSON_THROW_ON_ERROR);
            self::assertSame('web', $record['service_name']);
            self::assertSame('web', $record['app_target']);
            self::assertNull($record['correlation_id']);
            self::assertNull($record['uow_id']);
            self::assertNull($record['uow_type']);
            self::assertSame('api', $record['context']['app_target']);
            self::assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', $record['context']['correlation_id']);
            self::assertSame('caller:123', $record['context']['uow_id']);
            self::assertSame('cli', $record['context']['uow_type']);
            self::assertArrayNotHasKey('service_name', $record['context']);
            self::assertSame(1, $redactor->calls);
            self::assertTrue(LogRecordPolicy::isValidCanonicalRecord($record));
        } finally {
            if (\is_file($path)) {
                \unlink($path);
            }
            \rmdir($directory);
        }
    }

    public function testSinkDecisionsDoNotDependOnProducerOrEventIdentifier(): void
    {
        [$policy] = self::fixture();
        $context = ['operation' => 'worker:poll', 'status' => 'ready', 'moduleIds' => ['core.kernel']];
        $one = self::record(['message' => 'kernel.boot', 'context' => $context]);
        $two = self::record(['message' => 'worker.iteration', 'context' => $context]);
        self::assertSame($policy->admitContext($context), $policy->redactRecord($one)['context']);
        self::assertSame($policy->admitContext($context), $policy->redactRecord($two)['context']);
        self::assertSame($policy->admitContext($context), $policy->admitContext($context));
        self::assertSame(1, \substr_count(new JsonLineFormatter()->format($one), "\n"));
        self::assertSame(1, \substr_count(new LogfmtLineFormatter()->format($two), "\n"));
    }

    public function testPolicyHasNoPlatformRedactionImplementationOrKernelRegexDependency(): void
    {
        $source = \file_get_contents(new \ReflectionClass(LogRecordPolicy::class)->getFileName());
        self::assertIsString($source);
        foreach (
            [
                'Coretsia\\Platform\\Redaction\\',
                'Coretsia\\Kernel\\',
                'DefaultSensitiveDataRedactor',
                'SensitiveValueClassifier',
                'StableRedactionHasher',
                'ConfigKernel',
                'new SensitiveKeyClassifier',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
        self::assertStringContainsString('SensitiveKeyClassifierInterface', $source);
        self::assertStringContainsString('$this->keyClassifier->classify($path)', $source);
        self::assertStringContainsString('$this->keyClassifier->classify($segment)', $source);
        self::assertStringContainsString('$this->keyClassifier->classify($component)', $source);
    }

    /** @param array<string, mixed> $context */
    private static function assertFailedLoggerEmission(
        LogRecordPolicy $policy,
        SensitiveDataRedactorInterface $redactor,
        array $context,
        int $expectedRedactions,
    ): void {
        $directory = \sys_get_temp_dir() . '/coretsia-logging-policy-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($directory, 0700));
        try {
            foreach ([new JsonLineFormatter(), new LogfmtLineFormatter()] as $index => $formatter) {
                $clock = new class() implements ClockInterface {
                    public int $calls = 0;

                    public function now(): \DateTimeImmutable
                    {
                        ++$this->calls;
                        return new \DateTimeImmutable('2026-10-31T12:30:40Z');
                    }
                };
                $logger = new StreamLogger(
                    $policy,
                    self::processor(),
                    $formatter,
                    $clock,
                    'debug',
                    65536,
                    'web',
                    'web',
                    'file',
                    $directory,
                    'app',
                );

                $logger->info('kernel.boot', $context);
                self::assertSame($expectedRedactions * ($index + 1), $redactor->calls);
                self::assertSame($expectedRedactions === 0 ? 0 : 1, $clock->calls);
                self::assertSame([], \glob($directory . '/*'));
            }
        } finally {
            foreach (\glob($directory . '/*') ?: [] as $path) {
                \unlink($path);
            }
            \rmdir($directory);
        }
    }

    private static function processor(): ContextBagProcessor
    {
        return new ContextBagProcessor(
            new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    throw new \LogicException('no-runtime-context');
                }
            },
            new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
        );
    }

    /**
     * @return array{LogRecordPolicy, SensitiveDataRedactorInterface, SensitiveKeyClassifierInterface}
     */
    private static function fixture(?\Closure $transform = null, ?\Closure $classify = null): array
    {
        $redactor = new class($transform) implements SensitiveDataRedactorInterface {
            public int $calls = 0;
            public array $inputs = [];
            public array $contexts = [];

            public function __construct(private ?\Closure $transform)
            {
            }

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('redactValue-not-used');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                $this->inputs[] = $value;
                $this->contexts[] = $context;
                return $this->transform === null ? $value : ($this->transform)($value, $context);
            }
        };
        $classifier = new class($classify) implements SensitiveKeyClassifierInterface {
            public array $keys = [];

            public function __construct(private ?\Closure $classify)
            {
            }

            public function classify(string $key): ?RedactionKind
            {
                $this->keys[] = $key;
                return $this->classify === null ? null : ($this->classify)($key);
            }
        };

        return [new LogRecordPolicy($redactor, $classifier), $redactor, $classifier];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function record(array $overrides = []): array
    {
        return \array_replace([
            'ts_utc' => null,
            'level' => 'info',
            'message' => 'kernel.boot',
            'service_name' => 'web',
            'app_target' => 'web',
            'correlation_id' => null,
            'uow_id' => null,
            'uow_type' => null,
            'context' => null,
        ], $overrides);
    }
}
