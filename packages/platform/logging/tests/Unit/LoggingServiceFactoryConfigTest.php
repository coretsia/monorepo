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

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use Coretsia\Platform\Logging\Record\LogRecordPolicy;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class LoggingServiceFactoryConfigTest extends TestCase
{
    public function testExactSevenFactoryPortsAndProviderReferenceOrder(): void
    {
        $method = new \ReflectionMethod(LoggingServiceFactory::class, 'createLogger');
        self::assertTrue($method->isPublic());
        self::assertTrue($method->isStatic());
        self::assertSame(LoggerInterface::class, $method->getReturnType()->getName());
        $expected = [
            ['config', ConfigRepositoryInterface::class],
            ['context', ContextAccessorInterface::class],
            ['correlations', CorrelationIdProviderInterface::class],
            ['redactor', SensitiveDataRedactorInterface::class],
            ['keyClassifier', SensitiveKeyClassifierInterface::class],
            ['clock', ClockInterface::class],
            ['appTarget', AppTargetProviderInterface::class],
        ];
        self::assertCount(7, $method->getParameters());
        foreach ($expected as $i => [$name, $type]) {
            self::assertSame($name, $method->getParameters()[$i]->getName());
            self::assertSame($type, $method->getParameters()[$i]->getType()->getName());
        }
        $source = (string) \file_get_contents(new \ReflectionClass(LoggingServiceProvider::class)->getFileName());
        self::assertStringContainsString('factoryClass: LoggingServiceFactory::class', $source);
        self::assertStringContainsString("method: 'createLogger'", $source);
        $position = -1;
        foreach ($expected as [, $type]) {
            $token = 'ContainerValueReference::service(' . new \ReflectionClass($type)->getShortName() . '::class)';
            $next = \strpos($source, $token, $position + 1);
            self::assertNotFalse($next);
            self::assertGreaterThan($position, $next);
            $position = $next;
        }
    }

    public function testAllRequiredConfigReadsAndNoOptionalStreamDestination(): void
    {
        foreach (['stream', 'file', 'null'] as $driver) {
            [$config, $deps] = self::fixture(
                [
                    'logging.driver' => $driver,
                    'logging.file.path' => \rtrim(
                        \sys_get_temp_dir(),
                        '/\\',
                    ) . \DIRECTORY_SEPARATOR . 'coretsia-factory-uncreated',
                ],
            );
            $logger = self::create($config, $deps);
            self::assertInstanceOf($driver === 'null' ? NoopLogger::class : StreamLogger::class, $logger);
            self::assertSame([
                'logging.driver',
                'logging.file',
                'logging.file.path',
                'logging.file.name',
                'logging.level',
                'logging.max_record_bytes',
                'logging.service_name',
                'logging.format',
            ], $config->hasReads);
            self::assertSame($config->hasReads, $config->getReads);
            self::assertNotContains('logging.stream', $config->hasReads);
        }
        $source = (string) \file_get_contents(new \ReflectionClass(LoggingServiceFactory::class)->getFileName());
        foreach (
            [
                "'logging.stream'",
                'Coretsia\\Kernel\\',
                'Coretsia\\Platform\\Redaction\\',
                'new SensitiveKeyClassifier(',
                'new DefaultSensitiveDataRedactor(',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }

    public function testFactoryPassesTheInjectedClassifierAndRedactorToSamePolicy(): void
    {
        [$config, $deps] = self::fixture();
        $logger = self::create($config, $deps);
        $policy = new \ReflectionProperty(StreamLogger::class, 'policy')->getValue($logger);
        self::assertInstanceOf(LogRecordPolicy::class, $policy);
        self::assertSame(
            $deps['redactor'],
            new \ReflectionProperty(LogRecordPolicy::class, 'redactor')->getValue($policy),
        );
        self::assertSame(
            $deps['classifier'],
            new \ReflectionProperty(LogRecordPolicy::class, 'keyClassifier')->getValue($policy),
        );
    }

    public function testFormatSelectsExactlyOneFormatterWithIdenticalPipeline(): void
    {
        $pipelines = [];
        foreach (['jsonl', 'logfmt'] as $format) {
            [$config, $deps] = self::fixture(['logging.format' => $format]);
            $logger = self::create($config, $deps);
            self::assertInstanceOf(
                $format === 'jsonl' ? JsonLineFormatter::class : LogfmtLineFormatter::class,
                new \ReflectionProperty(StreamLogger::class, 'formatter')->getValue($logger),
            );
            $pipelines[] = [
                \get_class(new \ReflectionProperty(StreamLogger::class, 'policy')->getValue($logger)),
                \get_class(new \ReflectionProperty(StreamLogger::class, 'processor')->getValue($logger)),
                new \ReflectionProperty(StreamLogger::class, 'minimumLevel')->getValue($logger),
                new \ReflectionProperty(StreamLogger::class, 'maxRecordBytes')->getValue($logger),
            ];
        }
        self::assertSame($pipelines[0], $pipelines[1]);
    }

    public function testAutoAndExplicitServiceIdentityRemainIndependentOfTarget(): void
    {
        foreach (['web', 'api', 'console', 'worker'] as $target) {
            [$config, $deps] = self::fixture(target: $target);
            $logger = self::create($config, $deps);
            self::assertSame($target, new \ReflectionProperty(StreamLogger::class, 'serviceName')->getValue($logger));
            self::assertSame($target, new \ReflectionProperty(StreamLogger::class, 'appTarget')->getValue($logger));
            self::assertSame(1, $deps['target']->calls);
        }
        [$config, $deps] = self::fixture(['logging.service_name' => 'audit_service'], 'worker');
        $logger = self::create($config, $deps);
        self::assertSame(
            'audit_service',
            new \ReflectionProperty(StreamLogger::class, 'serviceName')->getValue($logger),
        );
        self::assertSame('worker', new \ReflectionProperty(StreamLogger::class, 'appTarget')->getValue($logger));
    }

    public function testSupportedByteBoundariesAndValidNullDriver(): void
    {
        foreach ([1024, 16384, 65536] as $bytes) {
            foreach (['jsonl', 'logfmt'] as $format) {
                [$config, $deps] = self::fixture(['logging.max_record_bytes' => $bytes, 'logging.format' => $format]);
                $logger = self::create($config, $deps);
                self::assertSame(
                    $bytes,
                    new \ReflectionProperty(StreamLogger::class, 'maxRecordBytes')->getValue($logger),
                );
            }
        }
        [$config, $deps] = self::fixture(['logging.driver' => 'null']);
        self::assertInstanceOf(NoopLogger::class, self::create($config, $deps));
    }

    public function testEverySharedAndFileShapeFieldIsValidatedBeforeNullFallback(): void
    {
        $invalid = [
            'logging.driver' => [null, false, 'invalid'],
            'logging.level' => [null, false, 'INFO', 'off'],
            'logging.max_record_bytes' => [null, false, 1023, 65537, '16384', 16384.5],
            'logging.service_name' => [null, false, '', 'wrong.name', \str_repeat('a', 65)],
            'logging.format' => [null, false, 'text', 'JSONL'],
            'logging.file.path' => [null, false, []],
            'logging.file.name' => [null, false, 'app.jsonl', '../app', 'UPPER', \str_repeat('a', 65)],
            'logging.file' => [[], ['path' => ''], ['path' => '', 'name' => 'app', 'extra' => 'invalid']],
        ];
        foreach (['stream', 'null'] as $driver) {
            foreach ($invalid as $key => $values) {
                if ($driver === 'null' && $key === 'logging.driver') {
                    continue;
                }
                foreach ($values as $value) {
                    [$config, $deps] = self::fixture(['logging.driver' => $driver, $key => $value]);
                    try {
                        self::create($config, $deps);
                        self::fail('Malformed configuration must never select NoopLogger.');
                    } catch (\InvalidArgumentException $exception) {
                        self::assertSame('logging-config-invalid', $exception->getMessage());
                    }
                }
            }
        }
    }

    public function testMissingKeysAndThrowingConfigurationAreSafeDeterministicFailures(): void
    {
        foreach (\array_keys(self::defaults()) as $key) {
            [$config, $deps] = self::fixture();
            unset($config->values[$key]);
            try {
                self::create($config, $deps);
                self::fail('Required configuration key missing: ' . $key);
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-config-missing', $exception->getMessage());
            }
        }
        [$config, $deps] = self::fixture();
        $config->throwOnGet = 'logging.level';
        try {
            self::create($config, $deps);
            self::fail('Thrown configuration access must fail closed.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('logging-config-invalid', $exception->getMessage());
        }
    }

    public function testFilePathSyntaxIsValidatedWithoutFilesystemProbing(): void
    {
        foreach (
            [
                'relative/path',
                '../logs',
                '/tmp/../logs',
                '/tmp//logs',
                '//server/share',
                'php://stderr',
                'https://example.test/logs',
                "\0",
                "\n",
                \str_repeat('a', 1025),
                'C:relative',
            ] as $path
        ) {
            [$config, $deps] = self::fixture(['logging.driver' => 'file', 'logging.file.path' => $path]);
            try {
                self::create($config, $deps);
                self::fail('Unsafe file path must be rejected.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-file-path-invalid', $exception->getMessage());
            }
        }
        $missing = \sys_get_temp_dir() . '/coretsia-config-missing-' . \bin2hex(\random_bytes(9));
        [$config, $deps] = self::fixture(['logging.driver' => 'file', 'logging.file.path' => $missing]);
        self::assertDirectoryDoesNotExist($missing);
        $logger = self::create($config, $deps);
        self::assertInstanceOf(StreamLogger::class, $logger);
        self::assertSame('file', new \ReflectionProperty(StreamLogger::class, 'driver')->getValue($logger));
        self::assertDirectoryDoesNotExist($missing);
        $logger->info('kernel.boot');
        self::assertDirectoryDoesNotExist($missing);
        if (\PHP_OS_FAMILY === 'Windows') {
            foreach (['C:\\logs', 'D:/logs'] as $path) {
                [$config, $deps] = self::fixture(['logging.driver' => 'file', 'logging.file.path' => $path]);
                self::assertInstanceOf(StreamLogger::class, self::create($config, $deps));
            }
        }
    }

    public function testFactoryHasNoFilesystemProbeOrCreationOperations(): void
    {
        $source = \file_get_contents(new \ReflectionClass(LoggingServiceFactory::class)->getFileName());
        self::assertIsString($source);

        $forbidden = [
            'realpath',
            'file_exists',
            'is_file',
            'is_dir',
            'is_link',
            'is_readable',
            'is_writable',
            'stat',
            'lstat',
            'fstat',
            'fopen',
            'file_put_contents',
            'mkdir',
            'touch',
            'opendir',
            'scandir',
            'glob',
            'readlink',
        ];

        foreach (\token_get_all($source) as $token) {
            if (
                !\is_array($token)
                || !\in_array($token[0], [\T_STRING, \T_NAME_FULLY_QUALIFIED, \T_NAME_QUALIFIED], true)
            ) {
                continue;
            }

            self::assertNotContains(\strtolower(\ltrim($token[1], '\\')), $forbidden);
        }
    }

    public function testMalformedAppTargetIsNotConvertedIntoNoop(): void
    {
        foreach (['invalid', '', 'WEB'] as $app) {
            [$config, $deps] = self::fixture(target: $app);
            try {
                self::create($config, $deps);
                self::fail('Invalid canonical target must fail.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('logging-app-target-invalid', $exception->getMessage());
            }
        }
        [$config, $deps] = self::fixture();
        $deps['target']->throw = true;
        try {
            self::create($config, $deps);
            self::fail('Target-provider failure must be isolated.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('logging-app-target-invalid', $exception->getMessage());
        }
    }

    public function testRuntimeConfigurationIsCapturedOnceForBothActiveDrivers(): void
    {
        foreach (['stream', 'file'] as $driver) {
            [$config, $deps] = self::fixture([
                'logging.driver' => $driver,
                'logging.file.path' => \rtrim(
                    \sys_get_temp_dir(),
                    '/\\',
                ) . \DIRECTORY_SEPARATOR . 'coretsia-config-never-created',
                'logging.file.name' => 'audit',
                'logging.format' => 'logfmt',
            ]);
            $logger = self::create($config, $deps);
            $reads = [$config->hasReads, $config->getReads];
            $config->values['logging.format'] = 'jsonl';
            $config->values['logging.file.name'] = 'changed';
            $config->values['logging.level'] = 'emergency';
            if ($driver === 'file') {
                $logger->info('kernel.boot');
            }
            self::assertSame($reads, [$config->hasReads, $config->getReads]);
            self::assertInstanceOf(
                LogfmtLineFormatter::class,
                new \ReflectionProperty(StreamLogger::class, 'formatter')->getValue($logger),
            );
            self::assertSame('info', new \ReflectionProperty(StreamLogger::class, 'minimumLevel')->getValue($logger));
            if ($driver === 'file') {
                self::assertSame(
                    'audit',
                    new \ReflectionProperty(StreamLogger::class, 'fileName')->getValue($logger),
                );
            }
        }
    }

    public function testStderrOpenFailureSelectsNoopInIsolatedProcessWithoutRetry(): void
    {
        $autoload = \dirname(__DIR__, 5) . '/vendor/autoload.php';
        self::assertFileExists($autoload);
        $wrappers = \stream_get_wrappers();
        $script = <<<'PHP_CODE'
require $argv[1];
class RejectStderrWrapper {
    public $context;
    public static array $opens = [];
    public function stream_open($path, $mode, $options, &$opened_path): bool {
        self::$opens[] = [$path, $mode];
        return false;
    }
}
$config = new class implements \Coretsia\Contracts\Config\ConfigRepositoryInterface {
    private array $settings = [
        'logging.driver' => 'stream',
        'logging.file' => ['path' => '', 'name' => 'app'],
        'logging.file.path' => '',
        'logging.file.name' => 'app',
        'logging.level' => 'info',
        'logging.max_record_bytes' => 65536,
        'logging.service_name' => 'auto',
        'logging.format' => 'jsonl',
    ];
    public function has(string $keyPath): bool
    {
        return array_key_exists($keyPath, $this->settings);
    }
    public function get(string $keyPath, mixed $default = null): mixed
    {
        return $this->settings[$keyPath] ?? $default;
    }
    public function all(): array
    {
        return [];
    }
    public function sourceOf(string $keyPath): ?\Coretsia\Contracts\Config\ConfigValueSource
    {
        return null;
    }
    public function explain(): array
    {
        return [];
    }
};
$context = new class implements \Coretsia\Contracts\Context\ContextAccessorInterface {
    public function has(string $key): bool
    {
        return false;
    }
    public function get(string $key): mixed
    {
        throw new \LogicException('unused');
    }
};
$correlations = new class implements \Coretsia\Contracts\Observability\CorrelationIdProviderInterface {
    public function correlationId(): ?string
    {
        return null;
    }
};
$redactor = new class implements \Coretsia\Contracts\Security\SensitiveDataRedactorInterface {
    public function redactValue(string $value, \Coretsia\Contracts\Security\RedactionKind $kind, \Coretsia\Contracts\Security\RedactionContext $context): \Coretsia\Contracts\Security\RedactedValue
    {
        throw new \LogicException('unused');
    }
    public function redactJsonLike(mixed $value, \Coretsia\Contracts\Security\RedactionContext $context): mixed
    {
        return $value;
    }
};
$classifier = new class implements \Coretsia\Contracts\Security\SensitiveKeyClassifierInterface {
    public function classify(string $key): ?\Coretsia\Contracts\Security\RedactionKind
    {
        return null;
    }
};
$clock = new class implements \Psr\Clock\ClockInterface {
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-31T12:00:00Z');
    }
};
$app = new class implements \Coretsia\Contracts\Runtime\AppTargetProviderInterface {
    public function app(): string
    {
        return 'web';
    }
};
stream_wrapper_unregister('php');
stream_wrapper_register('php', RejectStderrWrapper::class);
$logger = \Coretsia\Platform\Logging\Provider\LoggingServiceFactory::createLogger(
    $config, $context, $correlations, $redactor, $classifier, $clock, $app,
);
stream_wrapper_restore('php');
echo json_encode(['logger' => get_class($logger), 'opens' => RejectStderrWrapper::$opens], JSON_THROW_ON_ERROR);
PHP_CODE;
        $process = \proc_open(
            [\PHP_BINARY, '-r', $script, $autoload],
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
        self::assertSame('', $stderr);
        self::assertSame(
            ['logger' => NoopLogger::class, 'opens' => [['php://stderr', 'ab']]],
            \json_decode($stdout, true, 512, \JSON_THROW_ON_ERROR),
        );
        self::assertSame($wrappers, \stream_get_wrappers());
    }

    /** @return array{object, array<string, object>} */
    private static function fixture(array $overrides = [], string $target = 'web'): array
    {
        $config = new class(self::defaults()) implements ConfigRepositoryInterface {
            public array $hasReads = [];
            public array $getReads = [];
            public ?string $throwOnGet = null;

            public function __construct(public array $values)
            {
            }

            public function has(string $keyPath): bool
            {
                $this->hasReads[] = $keyPath;
                return \array_key_exists($keyPath, $this->values);
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                $this->getReads[] = $keyPath;
                if ($this->throwOnGet === $keyPath) {
                    throw new \RuntimeException('private-config');
                }
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
        foreach ($overrides as $key => $value) {
            $config->values[$key] = $value;
        }
        if (!\array_key_exists('logging.file', $overrides)) {
            $config->values['logging.file'] = [
                'path' => $config->values['logging.file.path'],
                'name' => $config->values['logging.file.name'],
            ];
        }
        return [
            $config,
            [
                'context' => new class() implements ContextAccessorInterface {
                    public function has(string $key): bool
                    {
                        return false;
                    }

                    public function get(string $key): mixed
                    {
                        throw new \LogicException('no-uow');
                    }
                },
                'correlations' => new class() implements CorrelationIdProviderInterface {
                    public function correlationId(): ?string
                    {
                        return null;
                    }
                },
                'redactor' => new class() implements SensitiveDataRedactorInterface {
                    public function redactValue(
                        string $value,
                        RedactionKind $kind,
                        RedactionContext $context,
                    ): RedactedValue {
                        throw new \LogicException('unused');
                    }

                    public function redactJsonLike(mixed $value, RedactionContext $context): mixed
                    {
                        return $value;
                    }
                },
                'classifier' => new class() implements SensitiveKeyClassifierInterface {
                    public function classify(string $key): ?RedactionKind
                    {
                        return null;
                    }
                },
                'clock' => new class() implements ClockInterface {
                    public function now(): \DateTimeImmutable
                    {
                        return new \DateTimeImmutable('2026-10-31T12:00:00Z');
                    }
                },
                'target' => new class($target) implements AppTargetProviderInterface {
                    public int $calls = 0;
                    public bool $throw = false;

                    public function __construct(private string $value)
                    {
                    }

                    public function app(): string
                    {
                        ++$this->calls;
                        if ($this->throw) {
                            throw new \RuntimeException('private-target');
                        }
                        return $this->value;
                    }
                },
            ],
        ];
    }

    private static function defaults(): array
    {
        return [
            'logging.driver' => 'stream',
            'logging.file' => ['path' => '', 'name' => 'app'],
            'logging.file.path' => '',
            'logging.file.name' => 'app',
            'logging.level' => 'info',
            'logging.max_record_bytes' => 65536,
            'logging.service_name' => 'auto',
            'logging.format' => 'jsonl',
        ];
    }

    /** @param array<string,object> $deps */
    private static function create(ConfigRepositoryInterface $config, array $deps): LoggerInterface
    {
        return LoggingServiceFactory::createLogger(
            $config,
            $deps['context'],
            $deps['correlations'],
            $deps['redactor'],
            $deps['classifier'],
            $deps['clock'],
            $deps['target'],
        );
    }
}
