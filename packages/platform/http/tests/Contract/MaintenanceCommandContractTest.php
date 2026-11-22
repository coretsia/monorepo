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

namespace Coretsia\Platform\Http\Tests\Contract;

use Coretsia\Contracts\Cli\Command\CommandInterface;
use Coretsia\Contracts\Cli\Input\InputInterface;
use Coretsia\Contracts\Cli\Output\OutputInterface;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Filesystem\ScopedFileLock;
use Coretsia\Foundation\Tag\ReservedTags;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use Coretsia\Platform\Http\Console\MaintenanceDisableCommand;
use Coretsia\Platform\Http\Console\MaintenanceEnableCommand;
use Coretsia\Platform\Http\Console\MaintenanceStatusCommand;
use Coretsia\Platform\Http\Maintenance\MaintenanceMessage;
use Coretsia\Platform\Http\Maintenance\MaintenanceStateStore;
use Coretsia\Platform\Http\Provider\HttpServiceProvider;
use Coretsia\Platform\Redaction\Provider\RedactionServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MaintenanceCommandContractTest extends TestCase
{
    private const string COMMAND_INVALID = 'CORETSIA_MAINTENANCE_COMMAND_INVALID';
    private const string MESSAGE_INVALID = 'CORETSIA_MAINTENANCE_MESSAGE_INVALID';
    private const string STATE_UNAVAILABLE = 'CORETSIA_MAINTENANCE_STATE_UNAVAILABLE';

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $base = \realpath(\sys_get_temp_dir());
        self::assertIsString($base);
        $this->root = $base . '/coretsia-http-maintenance-command-' . \bin2hex(\random_bytes(8));
        self::assertTrue(\mkdir($this->root, 0700));
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            self::remove($this->root);
        }
        parent::tearDown();
    }

    public function testExactTypedCommandMetadataMatchesProviderRegistration(): void
    {
        $options = [
            [
                'name' => 'message',
                'summary' => 'Public maintenance message.',
                'value' => 'required',
                'repeatable' => false,
            ],
            [
                'name' => 'retry-after',
                'summary' => 'Retry delay in seconds.',
                'value' => 'required',
                'repeatable' => false,
            ],
        ];
        $expected = [
            MaintenanceEnableCommand::class => [
                'maintenance:enable',
                'Enable maintenance mode.',
                'maintenance',
                false,
                [],
                $options,
            ],
            MaintenanceDisableCommand::class => [
                'maintenance:disable',
                'Disable maintenance mode.',
                'maintenance',
                false,
                [],
                [],
            ],
            MaintenanceStatusCommand::class => [
                'maintenance:status',
                'Show maintenance mode status.',
                'maintenance',
                false,
                [],
                [],
            ],
        ];
        $constantNames = ['NAME', 'SUMMARY', 'GROUP', 'HIDDEN', 'ARGUMENTS', 'OPTIONS'];
        $types = ['string', 'string', 'string', 'bool', 'array', 'array'];
        $metadataKeys = ['name', 'summary', 'group', 'hidden', 'arguments', 'options'];
        $normalizedKeys = ['arguments', 'group', 'hidden', 'name', 'options', 'summary'];
        $store = $this->store();
        $expectedTags = [];
        foreach ($expected as $class => $values) {
            $reflection = new \ReflectionClass($class);
            self::assertTrue($reflection->implementsInterface(CommandInterface::class));
            self::assertTrue($reflection->isFinal());
            self::assertTrue($reflection->isReadOnly());
            $public = \array_values(
                \array_filter(
                    $reflection->getReflectionConstants(\ReflectionClassConstant::IS_PUBLIC),
                    static fn (
                        \ReflectionClassConstant $constant,
                    ): bool => $constant->getDeclaringClass()->getName() === $class,
                ),
            );
            self::assertSame(
                $constantNames,
                \array_map(
                    static fn (\ReflectionClassConstant $constant): string => $constant->getName(),
                    $public,
                ),
            );
            foreach ($public as $index => $constant) {
                self::assertSame($types[$index], (string) $constant->getType());
                self::assertSame($values[$index], $constant->getValue());
            }
            $constructor = $reflection->getConstructor();
            self::assertNotNull($constructor);
            self::assertSame(1, $constructor->getNumberOfRequiredParameters());
            self::assertSame(1, $constructor->getNumberOfParameters());
            $parameter = $constructor->getParameters()[0];
            self::assertSame(MaintenanceStateStore::class, (string) $parameter->getType());
            self::assertFalse($parameter->isDefaultValueAvailable());
            self::assertFalse($parameter->allowsNull());
            $command = new $class($store);
            self::assertSame($values[0], $command->name());
            $meta = \array_combine($metadataKeys, $values);
            foreach ($meta['options'] as &$option) {
                \ksort($option, \SORT_STRING);
            }
            unset($option);
            \ksort($meta, \SORT_STRING);
            $expectedTags[$class] = $meta;
        }

        foreach ([true, false] as $enabled) {
            $http = require __DIR__ . '/../../config/http.php';
            $http['maintenance']['enabled'] = $enabled;
            $definitions = new ContainerDefinitionBuilder();
            $context = new ContainerDefinitionContext(['http' => $http]);
            new RedactionServiceProvider()->define($definitions, $context);
            new HttpServiceProvider()->define($definitions, $context);
            $actual = [];
            foreach ($definitions->build()->toDescriptorStream() as $operation) {
                if (($operation['kind'] ?? null) !== 'tag' || ($operation['tag'] ?? null) !== ReservedTags::CLI_COMMAND) {
                    continue;
                }
                $id = $operation['serviceId'];
                self::assertArrayNotHasKey($id, $actual);
                self::assertSame(0, $operation['priority']);
                self::assertSame($normalizedKeys, \array_keys($operation['meta']));
                $actual[$id] = $operation['meta'];
            }
            self::assertSame($expectedTags, $actual);
        }
        self::assertDirectoryDoesNotExist($this->root . '/var');
    }

    /** @param array<string, string> $options */
    #[DataProvider('acceptedEnableOptions')]
    public function testEnableAndStatusReturnExactSafePayloads(array $options, ?int $retry): void
    {
        $store = $this->store();
        $enable = new MaintenanceEnableCommand($store);
        $status = new MaintenanceStatusCommand($store);
        $disable = new MaintenanceDisableCommand($store);
        $this->assertCommandJson($status, [], ['enabled' => false, 'message_state' => 'ignored']);
        $this->assertCommandJson($enable, $options, ['enabled' => true]);
        $snapshot = $store->read();
        $expected = ['enabled' => true, 'message_state' => $options === [] ? 'absent' : 'valid'];
        if ($retry !== null) {
            $expected['retry_after_seconds'] = $retry;
        }
        $this->assertCommandJson($status, [], $expected);
        self::assertSame($options['message'] ?? null, $snapshot['message']?->message());
        self::assertSame($retry, $snapshot['message']?->retryAfterSeconds());
        $this->assertCommandJson($enable, $options, ['enabled' => true]);
        $this->assertCommandJson($disable, [], ['enabled' => false]);
        $this->assertCommandJson($disable, [], ['enabled' => false]);
        $this->assertCommandJson($status, [], ['enabled' => false, 'message_state' => 'ignored']);
        self::assertFileDoesNotExist($this->root . '/var/maintenance/enabled');
    }

    /** @return iterable<string, array{array<string, string>, ?int}> */
    public static function acceptedEnableOptions(): iterable
    {
        yield 'generic' => [[], null];
        yield 'message-only' => [['message' => 'Synthetic public notice'], null];
        yield 'minimum' => [['message' => 'Synthetic public notice', 'retry-after' => '1'], 1];
        yield 'maximum' => [['message' => 'Synthetic public notice', 'retry-after' => '86400'], 86400];
        yield 'utf8-notice' => [['message' => 'Maintenance café', 'retry-after' => '60'], 60];
    }

    /**
     * @param array<string, string|bool|list<string>|null> $options
     * @param list<string> $arguments
     */
    #[DataProvider('invalidEnableInput')]
    public function testEnableRejectsInvalidInputBeforeAnyStateMutation(
        array $options,
        array $arguments,
        string $code,
        string $reason,
    ): void {
        $store = $this->store();
        $command = new MaintenanceEnableCommand($store);
        $this->assertError($command, $command->name(), $arguments, $options, $code, $reason);
        self::assertDirectoryDoesNotExist($this->root . '/var');
        $store->enable(new MaintenanceMessage(1, 'Original public notice', 60));
        $directory = $this->root . '/var/maintenance';
        $bytes = \file_get_contents($directory . '/message.json');
        self::assertIsString($bytes);
        self::assertSame(8, \file_put_contents($directory . '/enabled', 'sentinel'));
        $this->assertError($command, $command->name(), $arguments, $options, $code, $reason);
        self::assertSame('sentinel', \file_get_contents($directory . '/enabled'));
        self::assertSame($bytes, \file_get_contents($directory . '/message.json'));
        self::assertSame(['.', '..', 'enabled', 'maintenance.lock', 'message.json'], \scandir($directory));
    }

    /** @return iterable<string, array{array<string, string|bool|list<string>|null>, list<string>, string, string}> */
    public static function invalidEnableInput(): iterable
    {
        $command = [self::COMMAND_INVALID, 'http-maintenance-command-invalid'];
        $message = [self::MESSAGE_INVALID, 'http-maintenance-message-invalid'];
        yield 'positional' => [[], ['synthetic'], ...$command];
        yield 'unknown' => [['unknown' => 'synthetic'], [], ...$command];
        yield 'target' => [['target' => 'web'], [], ...$command];
        yield 'retry-without-message' => [['retry-after' => '1'], [], ...$command];
        foreach ([null, true, false, ['first', 'second'], []] as $index => $value) {
            yield 'message-option-type-' . $index => [['message' => $value], [], ...$command];
            yield 'retry-option-type-' . $index => [
                ['message' => 'Synthetic', 'retry-after' => $value],
                [],
                ...$command,
            ];
        }
        $invalidDecimals = [
            '',
            '0',
            '00',
            '01',
            '+1',
            '-1',
            '1.0',
            '1e2',
            ' 1',
            '1 ',
            "1\n",
            '86401',
            '99999',
            '100000',
            \str_repeat('9', 100),
            '１２',
        ];
        foreach ($invalidDecimals as $index => $value) {
            yield 'retry-decimal-' . $index => [['message' => 'Synthetic', 'retry-after' => $value], [], ...$message];
        }
        foreach (
            [
                '',
                ' ',
                ' Synthetic',
                "Synthetic\n",
                "Synthetic\xff",
                "\u{00A0}",
                \str_repeat('x', 1025),
            ] as $index => $value
        ) {
            yield 'invalid-message-' . $index => [['message' => $value], [], ...$message];
        }
    }

    public function testAllCommandsRejectNameMismatchAndUnsupportedInputWithoutStateAccess(): void
    {
        $missing = $this->root . '/missing';
        foreach ([$this->store(), $this->store($missing)] as $store) {
            foreach ($this->commands($store) as $command) {
                foreach (
                    [
                        ['maintenance:other', [], []],
                        [$command->name(), ['synthetic'], []],
                        [$command->name(), [], ['target' => 'web']],
                        [$command->name(), [], ['unknown' => null]],
                    ] as [$name, $arguments, $options]
                ) {
                    $this->assertError(
                        $command,
                        $name,
                        $arguments,
                        $options,
                        self::COMMAND_INVALID,
                        'http-maintenance-command-invalid',
                    );
                }
                if (!$command instanceof MaintenanceEnableCommand) {
                    $this->assertError(
                        $command,
                        $command->name(),
                        [],
                        ['message' => 'Synthetic'],
                        self::COMMAND_INVALID,
                        'http-maintenance-command-invalid',
                    );
                    $this->assertError(
                        $command,
                        $command->name(),
                        [],
                        ['retry-after' => '1'],
                        self::COMMAND_INVALID,
                        'http-maintenance-command-invalid',
                    );
                }
            }
        }
        self::assertDirectoryDoesNotExist($missing);
        self::assertDirectoryDoesNotExist($this->root . '/var');
        $store = $this->store();
        $store->enable(new MaintenanceMessage(1, 'Original', null));
        $directory = $this->root . '/var/maintenance';
        $bytes = \file_get_contents($directory . '/message.json');
        foreach ($this->commands($store) as $command) {
            $this->assertError(
                $command,
                'maintenance:other',
                [],
                [],
                self::COMMAND_INVALID,
                'http-maintenance-command-invalid',
            );
            $this->assertError(
                $command,
                $command->name(),
                ['synthetic'],
                [],
                self::COMMAND_INVALID,
                'http-maintenance-command-invalid',
            );
        }
        self::assertSame('', \file_get_contents($directory . '/enabled'));
        self::assertSame($bytes, \file_get_contents($directory . '/message.json'));
    }

    public function testInvalidActiveMessageReportsFailureWithExactSnapshotAndNoRawContent(): void
    {
        $store = $this->store();
        $store->enable(null);
        $status = new MaintenanceStatusCommand($store);
        $path = $this->root . '/var/maintenance/message.json';
        $invalid = 'synthetic-invalid-json';
        self::assertSame(\strlen($invalid), \file_put_contents($path, $invalid));
        $this->assertCommandJson($status, [], ['enabled' => true, 'message_state' => 'invalid'], 1);
        self::assertSame($invalid, \file_get_contents($path));
        self::assertFileExists($this->root . '/var/maintenance/enabled');
        $this->assertCommandJson(new MaintenanceDisableCommand($store), [], ['enabled' => false]);
        $this->assertCommandJson($status, [], ['enabled' => false, 'message_state' => 'ignored']);
        self::assertSame($invalid, \file_get_contents($path));
    }

    public function testUnavailableStateNeverReportsSuccessfulToggleOrFabricatedStatus(): void
    {
        $missing = $this->root . '/missing';
        foreach ($this->commands($this->store($missing)) as $command) {
            $this->assertError(
                $command,
                $command->name(),
                [],
                [],
                self::STATE_UNAVAILABLE,
                'http-maintenance-state-unavailable',
            );
        }
        self::assertDirectoryDoesNotExist($missing);
        $store = $this->store();
        $directory = $this->root . '/var/maintenance';
        self::assertTrue(\mkdir($directory . '/enabled', 0700, true));
        foreach ($this->commands($store) as $command) {
            $this->assertError(
                $command,
                $command->name(),
                [],
                [],
                self::STATE_UNAVAILABLE,
                'http-maintenance-state-unavailable',
            );
        }
        self::assertDirectoryExists($directory . '/enabled');
        self::assertFileDoesNotExist($directory . '/message.json');
    }

    public function testCommandsUseOnlyCliPortsWithoutDirectIoOrLifecycleOrchestration(): void
    {
        foreach ($this->commands($this->store()) as $command) {
            $reflection = new \ReflectionClass($command);
            $file = $reflection->getFileName();
            self::assertIsString($file);
            $source = \file_get_contents($file);
            self::assertIsString($source);
            $code = '';
            foreach (\token_get_all($source) as $token) {
                if (\is_array($token) && \in_array($token[0], [\T_COMMENT, \T_DOC_COMMENT], true)) {
                    continue;
                }
                $code .= \is_array($token) ? $token[1] : $token;
            }
            foreach (
                [
                    'Coretsia\\Platform\\Cli\\',
                    'KernelRuntime',
                    'ContextStore',
                    'ResetInterface',
                    'Lifecycle',
                    'ConfigRepository',
                    'HttpKernel',
                    'ResponseFactoryInterface',
                    'STDOUT',
                    'STDERR',
                    'echo ',
                    'print ',
                    'printf(',
                    'fwrite(',
                    'var_dump(',
                    'print_r(',
                    'error_log(',
                    'file_get_contents(',
                    'file_put_contents(',
                    'fopen(',
                    'mkdir(',
                    'unlink(',
                    'rename(',
                    'flock(',
                    '->tokens(',
                ] as $forbidden
            ) {
                self::assertStringNotContainsString($forbidden, $code);
            }
        }
    }

    /**
     * @param array<string, string|bool|list<string>|null> $options
     * @param array<string, mixed> $payload
     */
    private function assertCommandJson(CommandInterface $command, array $options, array $payload, int $exit = 0): void
    {
        $input = $this->input($command->name(), [], $options);
        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::never())->method('text');
        $output->expects(self::never())->method('error');
        $output->expects(self::once())->method('json')->with(self::identicalTo($payload));
        \ob_start();
        try {
            self::assertSame($exit, $command->run($input, $output));
            self::assertSame('', \ob_get_contents());
        } finally {
            \ob_end_clean();
        }
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string|bool|list<string>|null> $options
     */
    private function assertError(
        CommandInterface $command,
        string $name,
        array $arguments,
        array $options,
        string $code,
        string $reason,
    ): void {
        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::never())->method('text');
        $output->expects(self::never())->method('json');
        $output->expects(self::once())->method('error')->with(self::identicalTo($code), self::identicalTo($reason));
        \ob_start();
        try {
            self::assertSame(1, $command->run($this->input($name, $arguments, $options), $output));
            self::assertSame('', \ob_get_contents());
        } finally {
            \ob_end_clean();
        }
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string|bool|list<string>|null> $options
     */
    private function input(string $name, array $arguments, array $options): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        $input->expects(self::never())->method('tokens');
        $input->method('commandName')->willReturn($name);
        $input->method('arguments')->willReturn($arguments);
        $input->method('options')->willReturn($options);
        $input->method('hasOption')->willReturnCallback(
            static fn (string $key): bool => \array_key_exists($key, $options),
        );
        $input->method('option')->willReturnCallback(
            static fn (string $key): string|bool|array|null => $options[$key] ?? null,
        );
        return $input;
    }

    /** @return list<CommandInterface> */
    private function commands(MaintenanceStateStore $store): array
    {
        return [
            new MaintenanceEnableCommand($store),
            new MaintenanceDisableCommand($store),
            new MaintenanceStatusCommand($store),
        ];
    }

    private function store(?string $root = null): MaintenanceStateStore
    {
        return new MaintenanceStateStore(
            new RuntimePathContext($root ?? $this->root, $this->root . '/artifacts'),
            new ScopedFileLock(),
        );
    }

    private static function remove(string $path): void
    {
        if (\is_link($path) || !\is_dir($path)) {
            \unlink($path);
            return;
        }
        $entries = \scandir($path);
        self::assertIsArray($entries);
        foreach ($entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::remove($path . '/' . $entry);
            }
        }
        \rmdir($path);
    }
}
