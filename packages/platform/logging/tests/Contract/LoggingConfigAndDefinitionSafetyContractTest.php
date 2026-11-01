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

namespace Coretsia\Platform\Logging\Tests\Contract;

use Coretsia\Contracts\Config\ConfigRepositoryInterface;
use Coretsia\Contracts\Config\ConfigRuleset;
use Coretsia\Contracts\Config\ConfigValueSource;
use Coretsia\Contracts\Context\ContextAccessorInterface;
use Coretsia\Contracts\Observability\CorrelationIdProviderInterface;
use Coretsia\Contracts\Runtime\AppTargetProviderInterface;
use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Contracts\Security\SensitiveKeyClassifierInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Logging\NoopLogger;
use Coretsia\Kernel\Config\ConfigValidator;
use Coretsia\Platform\Logging\Formatter\JsonLineFormatter;
use Coretsia\Platform\Logging\Formatter\LogfmtLineFormatter;
use Coretsia\Platform\Logging\Logger\StreamLogger;
use Coretsia\Platform\Logging\Provider\LoggingServiceFactory;
use Coretsia\Platform\Logging\Provider\LoggingServiceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

final class LoggingConfigAndDefinitionSafetyContractTest extends TestCase
{
    public function testCanonicalDefaultsAndDeclarativeRulesUseOnlyExistingValidatorTypes(): void
    {
        $config = self::defaults();
        $rules = self::rules();
        self::assertSame([
            'driver',
            'file',
            'level',
            'max_record_bytes',
            'service_name',
            'format',
        ], \array_keys($config));
        self::assertSame('stream', $config['driver']);
        self::assertSame(['path' => '', 'name' => 'app'], $config['file']);
        self::assertSame('auto', $config['service_name']);
        self::assertSame('jsonl', $config['format']);
        self::assertSame(1, $rules['schemaVersion']);
        self::assertSame('logging', $rules['configRoot']);
        self::assertFalse($rules['additionalKeys']);
        self::assertSame('string', $rules['keys']['driver']['type']);
        self::assertSame(['stream', 'file', 'null'], $rules['keys']['driver']['allowedValues']);
        self::assertSame(['jsonl', 'logfmt'], $rules['keys']['format']['allowedValues']);
        self::assertSame('map', $rules['keys']['file']['type']);
        self::assertFalse($rules['keys']['file']['additionalKeys']);
        self::assertSame(['path', 'name'], \array_keys($rules['keys']['file']['keys']));
        self::assertSame('string', $rules['keys']['file']['keys']['path']['type']);
        self::assertSame('reset-group-id', $rules['keys']['file']['keys']['name']['type']);
        self::assertSame('reset-group-id', $rules['keys']['service_name']['type']);
        foreach ($rules['keys'] as $rule) {
            self::assertTrue($rule['required']);
        }
        foreach ($rules['keys']['file']['keys'] as $rule) {
            self::assertTrue($rule['required']);
        }
        self::assertSame([], \array_diff(\array_keys($rules['keys']), \array_keys($config)));
        self::assertTrue(self::valid($config));
    }

    public function testConfigValidatorRejectsUnknownNestedReservedAndRedactionConfiguration(): void
    {
        foreach (
            [
                ['stream' => 'php://stderr'],
                ['redaction' => ['enabled' => false]],
                ['@internal' => true],
                ['file' => ['path' => '', 'name' => 'app', 'stream' => 'php://stderr']],
                ['file' => ['path' => '', 'name' => 'app', '@hidden' => true]],
                ['file' => ['name' => 'app']],
                ['file' => ['path' => '']],
                ['file' => null],
                ['driver' => null],
                ['driver' => false],
                ['driver' => 'udp'],
                ['format' => 'xml'],
                ['format' => null],
            ] as $change
        ) {
            $config = \array_replace(self::defaults(), $change);
            self::assertFalse(self::valid($config), \json_encode($change, \JSON_THROW_ON_ERROR));
        }
        foreach (['stream', 'file', 'null'] as $driver) {
            self::assertTrue(self::valid(\array_replace(self::defaults(), ['driver' => $driver])));
        }
        foreach (['jsonl', 'logfmt'] as $format) {
            self::assertTrue(self::valid(\array_replace(self::defaults(), ['format' => $format])));
        }
    }

    public function testServiceNameValidationMatchesResetGroupIdAndFailsBeforeResolution(): void
    {
        foreach (['auto', 'web', 'api', 'console', 'worker', 'app_name-9', 'a', \str_repeat('z', 64)] as $name) {
            self::assertTrue(self::valid(\array_replace(self::defaults(), ['service_name' => $name])));
        }
        foreach (
            [
                'api.web',
                '/tmp/service',
                'https://service',
                'white space',
                "line\nfeed",
                'Upper',
                '',
                \str_repeat('a', 65),
            ] as $name
        ) {
            $config = \array_replace(self::defaults(), ['service_name' => $name]);
            self::assertFalse(self::valid($config));
            self::assertFalse(self::directFactoryAccepts($config));
        }
    }

    public function testFilePathSemanticValidationBelongsToFactoryNotConfigValidator(): void
    {
        $path = \rtrim(\sys_get_temp_dir(), '/\\') . \DIRECTORY_SEPARATOR . 'coretsia-contract-unavailable-' . \bin2hex(
            \random_bytes(8),
        );
        $missing = \array_replace(self::defaults(), ['driver' => 'file', 'file' => ['path' => $path, 'name' => 'app']]);
        self::assertTrue(self::valid($missing));
        self::assertInstanceOf(StreamLogger::class, self::create($missing));
        self::assertFalse(\is_dir($path));

        $empty = \array_replace(self::defaults(), ['driver' => 'file']);
        self::assertTrue(self::valid($empty));
        self::assertFalse(self::directFactoryAccepts($empty));
        foreach (['stream', 'null'] as $driver) {
            $config = \array_replace(self::defaults(), ['driver' => $driver]);
            self::assertTrue(self::valid($config));
            self::assertInstanceOf($driver === 'null' ? NoopLogger::class : StreamLogger::class, self::create($config));
        }
        foreach (
            [
                'relative/path',
                '../traversal',
                'php://stderr',
                'https://example.org/logs',
                '/tmp/../escape',
                "bad\0path",
            ] as $path
        ) {
            $config = \array_replace(
                self::defaults(),
                ['driver' => 'file', 'file' => ['path' => $path, 'name' => 'app']],
            );
            self::assertTrue(self::valid($config), 'Declarative validator checks string type only.');
            self::assertFalse(self::directFactoryAccepts($config));
        }
    }

    public function testDefinitionsNeverContainRuntimePathsStreamsOrAlternativeSinkValidators(): void
    {
        $builder = new ContainerDefinitionBuilder();
        new LoggingServiceProvider()->define($builder, new ContainerDefinitionContext(['logging' => self::defaults()]));
        $set = $builder->build();
        $operations = $set->toDescriptorStream();
        self::assertCount(1, $operations);
        self::assertContains(ConfigRepositoryInterface::class, $set->requiredServiceIds());
        self::assertSame('service.factory.class-method', $operations[0]['kind']);
        $encoded = \json_encode($operations, \JSON_THROW_ON_ERROR);
        foreach (
            [
                'logging.config',
                'php://stderr',
                'file.path',
                'file.name',
                'fileDirectory',
                'flock',
                'ScopedFileLock',
            ] as $forbidden
        ) {
            self::assertStringNotContainsString($forbidden, $encoded);
        }
        $same = new ContainerDefinitionBuilder();
        new LoggingServiceProvider()->define(
            $same,
            new ContainerDefinitionContext(['logging' => ['driver' => 'file']]),
        );
        self::assertSame($operations, $same->build()->toDescriptorStream());

        foreach ([JsonLineFormatter::class, LogfmtLineFormatter::class] as $class) {
            $source = (string) \file_get_contents(new \ReflectionClass($class)->getFileName());
            self::assertStringContainsString('LogRecordPolicy::isValidCanonicalRecord($record)', $source);
            self::assertStringNotContainsString('ROOT_FIELDS', $source);
            self::assertStringNotContainsString('new LogRecordPolicy(', $source);
        }
        $factorySource = (string) \file_get_contents(new \ReflectionClass(LoggingServiceFactory::class)->getFileName());
        self::assertStringNotContainsString('Coretsia\\Kernel\\', $factorySource);
        self::assertStringNotContainsString('Coretsia\\Platform\\Redaction\\', $factorySource);
        self::assertStringNotContainsString('ScopedFileLock', $factorySource);
    }

    /** @return array<string, mixed> */
    private static function defaults(): array
    {
        return require \dirname(__DIR__, 2) . '/config/logging.php';
    }

    /** @return array<string, mixed> */
    private static function rules(): array
    {
        return require \dirname(__DIR__, 2) . '/config/rules.php';
    }

    /** @param array<string,mixed> $config */
    private static function valid(array $config): bool
    {
        return new ConfigValidator()->validate(
            ['logging' => $config],
            [ConfigRuleset::fromArray('logging', self::rules())]
        )->isSuccess();
    }

    /** @param array<string,mixed> $config */
    private static function directFactoryAccepts(array $config): bool
    {
        try {
            self::create($config);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /** @param array<string,mixed> $config */
    private static function create(array $config): LoggerInterface
    {
        $repository = new class($config) implements ConfigRepositoryInterface {
            public function __construct(private array $data)
            {
            }

            public function has(string $keyPath): bool
            {
                return \array_key_exists($keyPath, $this->flat());
            }

            public function get(string $keyPath, mixed $default = null): mixed
            {
                return $this->flat()[$keyPath] ?? $default;
            }

            public function all(): array
            {
                return ['logging' => $this->data];
            }

            public function sourceOf(string $keyPath): ?ConfigValueSource
            {
                return null;
            }

            public function explain(): array
            {
                return [];
            }

            private function flat(): array
            {
                $values = [];
                foreach ($this->data as $key => $value) {
                    $values['logging.' . $key] = $value;
                    if ($key === 'file' && \is_array($value)) {
                        foreach ($value as $nested => $item) {
                            $values['logging.file.' . $nested] = $item;
                        }
                    }
                }
                return $values;
            }
        };
        return LoggingServiceFactory::createLogger(
            $repository,
            new class() implements ContextAccessorInterface {
                public function has(string $key): bool
                {
                    return false;
                }

                public function get(string $key): mixed
                {
                    throw new \LogicException('no-context');
                }
            },
            new class() implements CorrelationIdProviderInterface {
                public function correlationId(): ?string
                {
                    return null;
                }
            },
            new class() implements SensitiveDataRedactorInterface {
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
            new class() implements SensitiveKeyClassifierInterface {
                public function classify(string $key): ?RedactionKind
                {
                    return null;
                }
            },
            new class() implements ClockInterface {
                public function now(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-10-31T12:00:00Z');
                }
            },
            new class() implements AppTargetProviderInterface {
                public function app(): string
                {
                    return 'web';
                }
            },
        );
    }
}
