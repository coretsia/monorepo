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

namespace Coretsia\Contracts\Tests\Contract;

use Coretsia\Contracts\HttpApp\ActionInvokerInterface;
use Coretsia\Contracts\HttpApp\ArgumentResolverInterface;
use Coretsia\Contracts\Routing\RouteMatch;
use PHPUnit\Framework\TestCase;
use ReflectionNamedType;

final class HttpAppContractsAreFormatNeutralTest extends TestCase
{
    public function testArgumentResolverInterfaceShapeIsFormatNeutral(): void
    {
        $method = new \ReflectionMethod(ArgumentResolverInterface::class, 'resolve');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(3, $method->getNumberOfParameters());
        self::assertSame(2, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('actionId', $parameters[0]->getName());
        self::assertSame('match', $parameters[1]->getName());
        self::assertSame('context', $parameters[2]->getName());

        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($parameters[1]->getType(), RouteMatch::class, false);
        self::assertNamedType($parameters[2]->getType(), 'array', false);
        self::assertSame([], $parameters[2]->getDefaultValue());

        self::assertNamedType($method->getReturnType(), 'array', false);

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@param non-empty-string $actionId', $docComment);
        self::assertStringContainsString('@param array<string,mixed> $context', $docComment);
        self::assertStringContainsString('@return array<string,mixed>', $docComment);
    }

    public function testActionInvokerInterfaceShapeIsFormatNeutral(): void
    {
        $method = new \ReflectionMethod(ActionInvokerInterface::class, 'invoke');

        self::assertTrue($method->isPublic());
        self::assertFalse($method->isStatic());
        self::assertSame(2, $method->getNumberOfParameters());
        self::assertSame(1, $method->getNumberOfRequiredParameters());

        $parameters = $method->getParameters();

        self::assertSame('actionId', $parameters[0]->getName());
        self::assertSame('arguments', $parameters[1]->getName());

        self::assertNamedType($parameters[0]->getType(), 'string', false);
        self::assertNamedType($parameters[1]->getType(), 'array', false);
        self::assertSame([], $parameters[1]->getDefaultValue());

        self::assertSame('mixed', (string)$method->getReturnType());

        $docComment = $method->getDocComment();

        self::assertIsString($docComment);
        self::assertStringContainsString('@param non-empty-string $actionId', $docComment);
        self::assertStringContainsString('@param array<string,mixed> $arguments', $docComment);
    }

    public function testHttpAppContractsDoNotReferencePsr7PlatformOrConcreteHttpPackages(): void
    {
        $files = self::phpFiles(self::contractsRoot() . '/src/HttpApp');

        self::assertNotEmpty($files);

        $forbiddenTokens = [
            'Psr\\Http\\Message\\',
            'Psr\Http\Message',
            'ServerRequestInterface',
            'RequestInterface',
            'ResponseInterface',
            'StreamInterface',
            'UploadedFileInterface',
            'UriInterface',
            'Coretsia\\Platform\\',
            'Coretsia\Platform\\',
            'Coretsia\\Integrations\\',
            'Coretsia\Integrations\\',
            'Symfony\\Component\\HttpFoundation\\',
            'Symfony\Component\HttpFoundation',
            'Illuminate\\Http\\',
            'Illuminate\Http',
            'Nyholm\\Psr7\\',
            'Nyholm\Psr7',
            'GuzzleHttp\\Psr7\\',
            'GuzzleHttp\Psr7',
            'Laminas\\Diactoros\\',
            'Laminas\Diactoros',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            self::assertIsString($contents);

            foreach ($forbiddenTokens as $token) {
                self::assertStringNotContainsString(
                    $token,
                    $contents,
                    sprintf('HttpApp contract file "%s" must not reference forbidden token "%s".', $file, $token),
                );
            }
        }
    }

    public function testHttpAppContractsDoNotUseRawTransportGlobals(): void
    {
        $files = self::phpFiles(self::contractsRoot() . '/src/HttpApp');

        self::assertNotEmpty($files);

        $forbiddenTokens = [
            '$_GET',
            '$_POST',
            '$_REQUEST',
            '$_COOKIE',
            '$_SERVER',
            '$_FILES',
            'getallheaders(',
            'headers_list(',
            'php://input',
        ];

        foreach ($files as $file) {
            $contents = file_get_contents($file);

            self::assertIsString($contents);

            foreach ($forbiddenTokens as $token) {
                self::assertStringNotContainsString(
                    $token,
                    $contents,
                    sprintf('HttpApp contract file "%s" must not use transport/global token "%s".', $file, $token),
                );
            }
        }
    }

    private static function assertNamedType(
        ?\ReflectionType $type,
        string $expectedName,
        bool $allowsNull,
    ): void {
        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($allowsNull, $type->allowsNull());
    }

    private static function contractsRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        self::assertDirectoryExists($directory);

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if (!$file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files, \SORT_STRING);

        return $files;
    }
}
