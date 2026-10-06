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
use Coretsia\Foundation\Container\ContainerBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\ServiceProviderInterface;
use Coretsia\Foundation\Tag\TagRegistry;
use Coretsia\Kernel\Ops\KernelOpsFacade;
use Coretsia\Kernel\Ops\KernelOpsHostBooter;
use Coretsia\Kernel\Runtime\RuntimePathContext;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class KernelOpsHostRejectsTagsForHostOwnedWiringTest extends TestCase
{
    public function testHostOwnedTagTargetsAreRejectedAndExternalTagsRemainVisible(): void
    {
        $guard = new ReflectionMethod(
            KernelOpsHostBooter::class,
            'assertNoHostOwnedTagTargets',
        );

        foreach ([KernelOpsFacade::class, RuntimePathContext::class] as $forbiddenId) {
            $builder = new ContainerBuilder();
            $builder->registerProviders([
                new KernelOpsTaggedProviderFixture($forbiddenId),
            ]);

            try {
                $guard->invoke(null, $builder);
                self::fail('Expected host-owned tag target conflict.');
            } catch (\UnexpectedValueException $exception) {
                self::assertSame('kernel-ops-host-tag-target-conflict', $exception->getMessage());
            }
        }

        $builder = new ContainerBuilder();
        $builder->registerProviders([
            new KernelOpsTaggedProviderFixture(
                KernelOpsExternalTaggedService::class,
                17,
                ['source' => 'fixture'],
            ),
        ]);

        $guard->invoke(null, $builder);

        $container = $builder->build();
        $registry = $container->get(TagRegistry::class);

        self::assertInstanceOf(TagRegistry::class, $registry);

        $tagged = $registry->all('fixture.command');

        self::assertCount(1, $tagged);
        self::assertSame(KernelOpsExternalTaggedService::class, $tagged[0]->id());
        self::assertSame(17, $tagged[0]->priority());
        self::assertSame(['source' => 'fixture'], $tagged[0]->meta());

        $public = new KernelOpsFailedException(KernelOpsFailedException::REASON_HOST_BOOT_FAILED);

        self::assertSame('host-boot-failed', $public->reason());
        self::assertSame('CORETSIA_KERNEL_OPS_FAILED: host-boot-failed', $public->getMessage());
        self::assertNull($public->getPrevious());

        $source = self::source();

        self::assertMatchesRegularExpression(
            '/\$finalBuilder = new ContainerBuilder\(config: \$completeConfig\);.*?' .
            '\$finalBuilder->registerProviders\(\$sourceProviders\);.*?' .
            'self::assertNoHostOwnedTagTargets\(\$finalBuilder\);.*?' .
            'self::installHostOwnedFactories\(.*?' .
            '\$container = \$finalBuilder->build\(\);/s',
            $source,
        );
        self::assertMatchesRegularExpression(
            '/catch\s*\(\\\\Throwable\)\s*\{\s*throw new KernelOpsFailedException\(/s',
            $source,
        );
    }

    private static function source(): string
    {
        $source = \file_get_contents(__DIR__ . '/../../src/Ops/KernelOpsHostBooter.php');

        self::assertIsString($source);

        return $source;
    }
}

final readonly class KernelOpsTaggedProviderFixture implements
    ServiceProviderInterface,
    ContainerDefinitionProviderInterface
{
    /**
     * @param array<string,mixed> $meta
     */
    public function __construct(
        private string $taggedServiceId,
        private int $priority = 0,
        private array $meta = [],
    ) {
    }

    public function register(ContainerBuilder $builder): void
    {
        $builder->registerDefinitionProvider($this);
    }

    public function define(
        ContainerDefinitionBuilder $definitions,
        ContainerDefinitionContext $context,
    ): void {
        $definitions->tag(
            'fixture.command',
            $this->taggedServiceId,
            $this->priority,
            $this->meta,
        );
    }
}

final class KernelOpsExternalTaggedService
{
}
