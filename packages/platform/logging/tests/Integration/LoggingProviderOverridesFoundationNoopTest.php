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

namespace Coretsia\Platform\Logging\Tests\Integration;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\RedactionMode;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Foundation\Provider\FoundationServiceProvider;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Module\LoggingModule;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class LoggingProviderOverridesFoundationNoopTest extends TestCase
{
    public function testExplicitProviderSelectionOverridesFoundationBindingInOneDeclarativeBatch(): void
    {
        $foundation = new ContainerBuilder(['foundation' => []]);
        $foundation->register(new FoundationServiceProvider());
        self::assertInstanceOf(NoopLogger::class, $foundation->build()->get(LoggerInterface::class));

        foreach (['jsonl', 'logfmt'] as $format) {
            [$builder, $config, $redactor] = self::builder('stream', $format);
            $builder->register(new FoundationServiceProvider(), new LoggingServiceProvider());
            self::pinClock($builder);
            $container = $builder->build();
            $logger = $container->get(LoggerInterface::class);
            self::assertInstanceOf(StreamLogger::class, $logger);
            self::assertSame($logger, $container->get(LoggerInterface::class));
            self::assertSame(0, $redactor->calls);
            self::assertSame(8, \count($config->reads));
            self::assertNotContains('logging.stream', $config->reads);
        }
    }

    public function testReverseProviderOrderRestoresTheFoundationNoopBinding(): void
    {
        foreach (['jsonl', 'logfmt'] as $format) {
            [$builder, $config, $redactor] = self::builder('file', $format);
            $builder->register(new LoggingServiceProvider(), new FoundationServiceProvider());
            $container = $builder->build();
            $logger = $container->get(LoggerInterface::class);

            self::assertInstanceOf(NoopLogger::class, $logger);
            self::assertSame($logger, $container->get(LoggerInterface::class));
            self::assertSame([], $config->reads);
            self::assertSame(0, $redactor->calls);
        }
    }

    public function testFileAndStreamModesKeepTheSameProviderBindingAndSevenPortReferences(): void
    {
        $ports = [
            ConfigRepositoryInterface::class,
            ContextAccessorInterface::class,
            CorrelationIdProviderInterface::class,
            SensitiveDataRedactorInterface::class,
            SensitiveKeyClassifierInterface::class,
            ClockInterface::class,
            AppTargetProviderInterface::class,
        ];
        $source = null;
        foreach (['stream', 'file'] as $driver) {
            foreach (['jsonl', 'logfmt'] as $format) {
                $definitions = new ContainerDefinitionBuilder();
                new LoggingServiceProvider()->define(
                    $definitions,
                    new ContainerDefinitionContext(['logging' => ['driver' => $driver, 'format' => $format]]),
                );
                $set = $definitions->build();
                $descriptor = $set->toDescriptorStream();
                self::assertCount(1, $descriptor);
                self::assertSame('service.factory.class-method', $descriptor[0]['kind']);
                self::assertSame(LoggerInterface::class, $descriptor[0]['id']);
                self::assertSame($ports, \array_column($descriptor[0]['arguments'], 'id'));
                self::assertSame($ports, \array_values(\array_intersect($ports, $set->requiredServiceIds())));
                if ($source === null) {
                    $source = $descriptor;
                } else {
                    self::assertSame($source, $descriptor);
                }
            }
        }
        self::assertSame(
            [LoggingServiceProvider::class],
            new LoggingModule()->providers()
        );
        $manifest = \json_decode(
            (string) \file_get_contents(\dirname(__DIR__, 2) . '/composer.json'),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );
        self::assertSame(
            ['core.foundation', 'core.kernel', 'platform.redaction'],
            $manifest['extra']['coretsia']['requires'],
        );
    }

    public function testMissingEitherSecurityPortCannotSilentlyResolveFoundationNoop(): void
    {
        foreach ([SensitiveDataRedactorInterface::class, SensitiveKeyClassifierInterface::class] as $missing) {
            // Deliberately omit one required implementation, not its definition.
            // Foundation's NoopLogger must not mask the missing security port.
            [$incomplete] = self::builder('file', 'jsonl', 'web', 'auto', '', $missing);
            $incomplete->register(new FoundationServiceProvider(), new LoggingServiceProvider());
            self::pinClock($incomplete);
            try {
                $incomplete->build()->get(LoggerInterface::class);
                self::fail('Missing mandatory security port must not resolve a NoopLogger.');
            } catch (ContainerException $exception) {
                self::assertSame('container-definition-service-reference-failed', $exception->reason());
                self::assertInstanceOf(NotFoundException::class, $exception->getPrevious());
                self::assertSame($missing, $exception->getPrevious()->serviceId());
            }
        }
    }

    public function testFixedStderrEmitsBothCanonicalFormatsWithTheSameProviderPipeline(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $script = <<<'PHP_CODE'
require $argv[1];
require $argv[2];
$class = \Coretsia\Platform\Logging\Tests\Integration\LoggingProviderOverridesFoundationNoopTest::class;
$builderMethod = new \ReflectionMethod($class, 'builder');
[$builder, $config, $redactor] = $builderMethod->invoke(null, 'stream', $argv[3], $argv[4]);
$builder->register(
    new \Coretsia\Foundation\Provider\FoundationServiceProvider(),
    new \Coretsia\Platform\Logging\Provider\LoggingServiceProvider(),
);
$pinClock = new \ReflectionMethod($class, 'pinClock');
$pinClock->invoke(null, $builder);
$logger = $builder->build()->get(\Psr\Log\LoggerInterface::class);
$logger->info('kernel.boot', ['operation' => 'boot']);
echo json_encode([
    'stream_logger' => $logger instanceof \Coretsia\Platform\Logging\Logger\StreamLogger,
    'redactions' => $redactor->calls,
    'config_reads' => count($config->reads),
], JSON_THROW_ON_ERROR);
PHP_CODE;
        foreach (['jsonl', 'logfmt'] as $format) {
            foreach (['web', 'api', 'console', 'worker'] as $target) {
                $process = \proc_open(
                    [\PHP_BINARY, '-d', 'display_errors=1', '-r', $script, $autoload, __FILE__, $format, $target],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
                self::assertIsResource($process);
                \fclose($pipes[0]);
                $stdout = (string) \stream_get_contents($pipes[1]);
                $stderr = (string) \stream_get_contents($pipes[2]);
                \fclose($pipes[1]);
                \fclose($pipes[2]);
                self::assertSame(0, \proc_close($process), $stderr);
                self::assertSame(
                    ['stream_logger' => true, 'redactions' => 1, 'config_reads' => 8],
                    \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR),
                );
                self::assertSame(1, \substr_count($stderr, "\n"));
                if ($format === 'jsonl') {
                    $record = \json_decode($stderr, true, 512, \JSON_THROW_ON_ERROR);
                    self::assertSame($target, $record['service_name']);
                    self::assertSame($target, $record['app_target']);
                    self::assertSame('kernel.boot', $record['message']);
                    self::assertSame(['operation' => 'boot'], $record['context']);
                } else {
                    self::assertStringContainsString('service_name="' . $target . '"', $stderr);
                    self::assertStringContainsString('app_target="' . $target . '"', $stderr);
                    self::assertStringContainsString('message="kernel.boot"', $stderr);
                    self::assertStringContainsString('operation', $stderr);
                }
            }
        }
    }

    public function testFormatsAndTargetsEmitStableServiceIdentityViaTheSameLogger(): void
    {
        $root = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR
            . 'coretsia-provider-integration-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($root, 0700));
        try {
            foreach (['web', 'api', 'console', 'worker'] as $target) {
                foreach (['jsonl', 'logfmt'] as $format) {
                    foreach (['auto', 'custom_service'] as $configuredName) {
                        [$builder, $config, $redactor] = self::builder(
                            'file',
                            $format,
                            $target,
                            $configuredName,
                            $root,
                        );
                        $builder->register(new FoundationServiceProvider(), new LoggingServiceProvider());
                        self::pinClock($builder);
                        $container = $builder->build();
                        $logger = $container->get(LoggerInterface::class);
                        self::assertInstanceOf(StreamLogger::class, $logger);
                        $logger->info('kernel.boot', ['operation' => 'boot']);
                        self::assertSame(1, $redactor->calls);
                        self::assertSame('logging.record', $redactor->contexts[0]->scope());
                        self::assertSame(RedactionMode::Placeholder, $redactor->contexts[0]->mode());
                        self::assertSame($target, $redactor->records[0]['app_target']);
                        self::assertSame(
                            $configuredName === 'auto' ? $target : $configuredName,
                            $redactor->records[0]['service_name'],
                        );
                        self::assertSame(8, \count($config->reads));
                        $path = $root . \DIRECTORY_SEPARATOR . $target . '-app-2026-10-31.' . $format;
                        self::assertFileExists($path);
                        $contents = (string) \file_get_contents($path);
                        self::assertSame(1, \substr_count($contents, "\n"));
                        if ($format === 'jsonl') {
                            $record = \json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
                            self::assertSame($target, $record['app_target']);
                            self::assertSame(
                                $configuredName === 'auto' ? $target : $configuredName,
                                $record['service_name'],
                            );
                            self::assertNull($record['correlation_id']);
                            self::assertNull($record['uow_id']);
                            self::assertNull($record['uow_type']);
                        } else {
                            self::assertStringContainsString('app_target="' . $target . '"', $contents);
                            self::assertStringContainsString(
                                'service_name="' . ($configuredName === 'auto' ? $target : $configuredName) . '"',
                                $contents,
                            );
                            self::assertStringContainsString('correlation_id=null', $contents);
                            self::assertStringContainsString('uow_id=null', $contents);
                            self::assertStringContainsString('uow_type=null', $contents);
                        }
                        \unlink($path);
                    }
                }
            }
        } finally {
            foreach (\glob($root . '/*') ?: [] as $file) {
                \unlink($file);
            }
            \rmdir($root);
        }
    }

    /** @return array{ContainerBuilder, object, object} */
    private static function builder(
        string $driver,
        string $format,
        string $target = 'web',
        string $serviceName = 'auto',
        string $directory = '',
        ?string $omit = null,
    ): array {
        $settings = [
            'logging.driver' => $driver,
            'logging.file.path' => $directory === '' ? \rtrim(
                \sys_get_temp_dir(),
                '/\\',
            ) . \DIRECTORY_SEPARATOR . 'coretsia-absent' : $directory,
            'logging.file.name' => 'app',
            'logging.level' => 'info',
            'logging.max_record_bytes' => 65536,
            'logging.service_name' => $serviceName,
            'logging.format' => $format,
        ];
        $settings['logging.file'] = ['path' => $settings['logging.file.path'], 'name' => 'app'];
        $config = new class($settings) implements ConfigRepositoryInterface {
            public array $reads = [];

            public function __construct(private array $values)
            {
            }

            public function has(string $keyPath): bool
            {
                $this->reads[] = $keyPath;
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                return $this->values[$keyPath] ?? $default;
            }

            public function all(): array
            {
                return [];
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                return null;
            }

            public function explain(): array
            {
                return [];
            }
        };
        $redactor = new class() implements SensitiveDataRedactorInterface {
            public int $calls = 0;
            public array $records = [];
            public array $contexts = [];

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                throw new \LogicException('unused');
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                $this->records[] = $value;
                $this->contexts[] = $context;
                return $value;
            }
        };
        $builder = new ContainerBuilder(['foundation' => []]);
        $builder->instance(ConfigRepositoryInterface::class, $config);
        $builder->instance(
            AppTargetProviderInterface::class,
            new class($target) implements AppTargetProviderInterface {
                public function __construct(private string $value)
                {
                }

                public function app(): string
                {
                    return $this->value;
                }
            },
        );
        if ($omit !== SensitiveDataRedactorInterface::class) {
            $builder->instance(SensitiveDataRedactorInterface::class, $redactor);
        }
        if ($omit !== SensitiveKeyClassifierInterface::class) {
            $builder->instance(
                SensitiveKeyClassifierInterface::class,
                new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        return null;
                    }
                },
            );
        }
        return [$builder, $config, $redactor];
    }

    private static function pinClock(ContainerBuilder $builder): void
    {
        // Foundation declares ClockInterface in the shared definition batch.
        // Override the resolved port only after applying that batch.
        $builder->instance(
            ClockInterface::class,
            new class() implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-10-31T12:00:00Z');
                }
            },
        );
    }
}
