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

namespace Coretsia\Foundation\Container\Definition;

use Coretsia\Foundation\Container\Container;
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Exception\ContainerException;
use Coretsia\Foundation\Container\Exception\NotFoundException;
use Coretsia\Foundation\Tag\TagRegistry;
use Psr\Container\ContainerInterface;

/**
 * Applies one canonical declarative definition set to the Foundation source
 * runtime container builder.
 *
 * Runtime closures created here are adapter internals. They are never stored in
 * ContainerDefinitionSet and never enter compiled-container descriptor streams.
 *
 * @internal
 */
final class ContainerDefinitionApplier
{
    private const int MAX_RUNTIME_DEPTH = 16;

    public function apply(
        ContainerBuilder $builder,
        ContainerDefinitionSet $definitions,
    ): void {
        $operations = $definitions->toDescriptorStream();
        $parameters = self::finalParameters($operations);
        $tagRegistry = $builder->tagRegistry();

        foreach ($operations as $operation) {
            $kind = $operation['kind'] ?? null;

            if (!\is_string($kind)) {
                throw new ContainerException('container-definition-operation-invalid');
            }

            switch ($kind) {
                case ContainerDefinitionKind::SERVICE_CLASS->value:
                case ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD->value:
                case ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD->value:
                    self::applyService(
                        builder: $builder,
                        definition: $operation,
                        parameters: $parameters,
                        tagRegistry: $tagRegistry,
                    );
                    break;

                case ContainerDefinitionKind::ALIAS->value:
                    self::applyAlias(
                        builder: $builder,
                        definition: $operation,
                    );
                    break;

                case ContainerDefinitionKind::PARAMETER->value:
                    break;

                case ContainerDefinitionKind::TAG->value:
                    self::applyTag(
                        builder: $builder,
                        definition: $operation,
                    );
                    break;

                default:
                    throw new ContainerException('container-definition-operation-invalid');
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $operations
     *
     * @return array<string, mixed>
     */
    private static function finalParameters(array $operations): array
    {
        $parameters = [];

        foreach ($operations as $operation) {
            if (
                ($operation['kind'] ?? null)
                !== ContainerDefinitionKind::PARAMETER->value
            ) {
                continue;
            }

            $name = $operation['name'] ?? null;

            if (
                !\is_string($name)
                || !\array_key_exists('value', $operation)
            ) {
                throw new ContainerException('container-definition-parameter-invalid');
            }

            $parameters[$name] = $operation['value'];
        }

        return $parameters;
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $parameters
     */
    private static function applyService(
        ContainerBuilder $builder,
        array $definition,
        array $parameters,
        TagRegistry $tagRegistry,
    ): void {
        $id = $definition['id'] ?? null;
        $shared = $definition['shared'] ?? null;

        if (!\is_string($id) || !\is_bool($shared)) {
            throw new ContainerException('container-definition-service-invalid');
        }

        $builder->factory(
            id: $id,
            factory: self::runtimeFactory(
                definition: $definition,
                parameters: $parameters,
                tagRegistry: $tagRegistry,
            ),
            shared: $shared,
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function applyAlias(
        ContainerBuilder $builder,
        array $definition,
    ): void {
        $alias = $definition['alias'] ?? null;
        $serviceId = $definition['serviceId'] ?? null;

        if (!\is_string($alias) || !\is_string($serviceId)) {
            throw new ContainerException('container-definition-alias-invalid');
        }

        $builder->factory(
            id: $alias,
            factory: static fn (
                Container $container,
            ): mixed => $container->get($serviceId),
            shared: false,
        );
    }

    /**
     * @param array<string, mixed> $definition
     */
    private static function applyTag(
        ContainerBuilder $builder,
        array $definition,
    ): void {
        $tag = $definition['tag'] ?? null;
        $serviceId = $definition['serviceId'] ?? null;
        $priority = $definition['priority'] ?? null;
        $meta = $definition['meta'] ?? null;

        if (
            !\is_string($tag)
            || !\is_string($serviceId)
            || !\is_int($priority)
            || !\is_array($meta)
            || ($meta !== [] && \array_is_list($meta))
        ) {
            throw new ContainerException('container-definition-tag-invalid');
        }

        /** @var array<string, mixed> $meta */
        $builder->tag(
            tag: $tag,
            serviceId: $serviceId,
            priority: $priority,
            meta: $meta,
        );
    }

    /**
     * @param array<string, mixed> $definition
     * @param array<string, mixed> $parameters
     *
     * @return callable(Container): mixed
     */
    private static function runtimeFactory(
        array $definition,
        array $parameters,
        TagRegistry $tagRegistry,
    ): callable {
        return static function (
            Container $container,
        ) use (
            $definition,
            $parameters,
            $tagRegistry,
        ): mixed {
            $kind = $definition['kind'] ?? null;
            $values = $definition['arguments'] ?? null;

            if (
                !\is_string($kind)
                || !\is_array($values)
                || !\array_is_list($values)
            ) {
                throw new ContainerException('container-definition-service-invalid');
            }

            $arguments = self::resolveArguments(
                container: $container,
                values: $values,
                parameters: $parameters,
                tagRegistry: $tagRegistry,
            );

            return match ($kind) {
                ContainerDefinitionKind::SERVICE_CLASS->value =>
                self::instantiateClass(
                    definition: $definition,
                    arguments: $arguments,
                ),

                ContainerDefinitionKind::SERVICE_FACTORY_CLASS_METHOD->value =>
                self::invokeClassMethodFactory(
                    definition: $definition,
                    arguments: $arguments,
                ),

                ContainerDefinitionKind::SERVICE_FACTORY_SERVICE_METHOD->value =>
                self::invokeServiceMethodFactory(
                    container: $container,
                    definition: $definition,
                    arguments: $arguments,
                ),

                default => throw new ContainerException('container-definition-service-kind-invalid'),
            };
        };
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<mixed> $arguments
     */
    private static function instantiateClass(
        array $definition,
        array $arguments,
    ): object {
        $class = $definition['class'] ?? null;

        if (!\is_string($class)) {
            throw new ContainerException('container-definition-class-invalid');
        }

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-class-invalid', $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException('container-definition-class-not-instantiable');
        }

        try {
            return $reflection->newInstanceArgs(
                $arguments,
            );
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-class-instantiation-failed', $exception);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<mixed> $arguments
     */
    private static function invokeClassMethodFactory(
        array $definition,
        array $arguments,
    ): mixed {
        $factoryClass = $definition['factoryClass'] ?? null;
        $method = $definition['method'] ?? null;

        if (
            !\is_string($factoryClass)
            || !\is_string($method)
        ) {
            throw new ContainerException('container-definition-factory-invalid');
        }

        try {
            $reflection = new \ReflectionMethod(
                $factoryClass,
                $method,
            );
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-factory-invalid', $exception);
        }

        if (
            !$reflection->isPublic()
            || !$reflection->isStatic()
            || $reflection->isAbstract()
        ) {
            throw new ContainerException('container-definition-factory-invalid');
        }

        try {
            return $reflection->invokeArgs(
                null,
                $arguments,
            );
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-factory-failed', $exception);
        }
    }

    /**
     * @param array<string, mixed> $definition
     * @param list<mixed> $arguments
     */
    private static function invokeServiceMethodFactory(
        Container $container,
        array $definition,
        array $arguments,
    ): mixed {
        $factoryServiceId = $definition['factoryServiceId'] ?? null;
        $method = $definition['method'] ?? null;

        if (
            !\is_string($factoryServiceId)
            || !\is_string($method)
        ) {
            throw new ContainerException('container-definition-factory-invalid');
        }

        try {
            $factoryService = $container->get($factoryServiceId);
        } catch (NotFoundException $exception) {
            $reason = $exception->serviceId() === $factoryServiceId
                ? 'container-definition-factory-service-missing'
                : 'container-definition-factory-service-resolution-failed';

            throw new ContainerException($reason, $exception);
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-factory-service-resolution-failed', $exception);
        }

        if (!\is_object($factoryService)) {
            throw new ContainerException('container-definition-factory-invalid');
        }

        try {
            $reflection = new \ReflectionMethod(
                $factoryService,
                $method,
            );
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-factory-invalid', $exception);
        }

        if (
            !$reflection->isPublic()
            || $reflection->isStatic()
            || $reflection->isAbstract()
        ) {
            throw new ContainerException('container-definition-factory-invalid');
        }

        try {
            return $reflection->invokeArgs(
                $factoryService,
                $arguments,
            );
        } catch (ContainerException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new ContainerException('container-definition-factory-failed', $exception);
        }
    }

    /**
     * @param list<mixed> $values
     * @param array<string, mixed> $parameters
     *
     * @return list<mixed>
     */
    private static function resolveArguments(
        Container $container,
        array $values,
        array $parameters,
        TagRegistry $tagRegistry,
    ): array {
        $resolved = [];

        foreach ($values as $value) {
            $resolved[] = self::resolveValue(
                container: $container,
                value: $value,
                parameters: $parameters,
                tagRegistry: $tagRegistry,
                depth: 0,
            );
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private static function resolveValue(
        Container $container,
        mixed $value,
        array $parameters,
        TagRegistry $tagRegistry,
        int $depth,
    ): mixed {
        if ($depth > self::MAX_RUNTIME_DEPTH) {
            throw new ContainerException('container-definition-value-depth-exceeded');
        }

        if (
            $value === null
            || \is_bool($value)
            || \is_int($value)
            || \is_string($value)
        ) {
            return $value;
        }

        if (!\is_array($value)) {
            throw new ContainerException('container-definition-value-invalid');
        }

        if (self::isServiceReference($value)) {
            $serviceId = $value['id'];

            if (
                $serviceId === Container::class
                || $serviceId === ContainerInterface::class
            ) {
                return $container;
            }

            if ($serviceId === TagRegistry::class) {
                return $tagRegistry;
            }

            try {
                return $container->get($serviceId);
            } catch (NotFoundException $exception) {
                if ($exception->serviceId() !== $serviceId) {
                    throw $exception;
                }

                throw new ContainerException('container-definition-service-reference-failed', $exception);
            } catch (ContainerException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                throw new ContainerException('container-definition-service-reference-failed', $exception);
            }
        }

        if (self::isParameterReference($value)) {
            $parameterName = $value['name'];

            if (!\array_key_exists($parameterName, $parameters)) {
                throw new ContainerException('container-definition-parameter-missing');
            }

            return $parameters[$parameterName];
        }

        if (self::isClassReference($value)) {
            return $value['class'];
        }

        if (\array_is_list($value)) {
            $resolved = [];

            foreach ($value as $item) {
                $resolved[] = self::resolveValue(
                    container: $container,
                    value: $item,
                    parameters: $parameters,
                    tagRegistry: $tagRegistry,
                    depth: $depth + 1,
                );
            }

            return $resolved;
        }

        $resolved = [];

        foreach ($value as $key => $item) {
            if (!\is_string($key)) {
                throw new ContainerException('container-definition-map-key-invalid');
            }

            $resolved[$key] = self::resolveValue(
                container: $container,
                value: $item,
                parameters: $parameters,
                tagRegistry: $tagRegistry,
                depth: $depth + 1,
            );
        }

        return $resolved;
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{id: string, type: 'service'} $value
     */
    private static function isServiceReference(array $value): bool
    {
        return \array_keys($value) === ['id', 'type']
            && ($value['type'] ?? null)
            === ContainerValueReference::TYPE_SERVICE
            && \is_string($value['id'] ?? null)
            && $value['id'] !== '';
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{name: string, type: 'parameter'} $value
     */
    private static function isParameterReference(
        array $value,
    ): bool {
        return \array_keys($value) === ['name', 'type']
            && ($value['type'] ?? null)
            === ContainerValueReference::TYPE_PARAMETER
            && \is_string($value['name'] ?? null)
            && $value['name'] !== '';
    }

    /**
     * @param array<array-key, mixed> $value
     *
     * @phpstan-assert-if-true array{class: string, type: 'class'} $value
     */
    private static function isClassReference(array $value): bool
    {
        return \array_keys($value) === ['class', 'type']
            && ($value['type'] ?? null)
            === ContainerValueReference::TYPE_CLASS
            && \is_string($value['class'] ?? null)
            && $value['class'] !== '';
    }
}
