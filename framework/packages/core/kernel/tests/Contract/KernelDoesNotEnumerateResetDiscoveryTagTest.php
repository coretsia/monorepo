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

namespace Coretsia\Kernel\Tests\Contract;

use Coretsia\Contracts\Runtime\ResetInterface;
use Coretsia\Foundation\Provider\Tags as FoundationTags;
use Coretsia\Foundation\Runtime\Reset\ResetOrchestrator;
use Coretsia\Kernel\Runtime\KernelRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class KernelDoesNotEnumerateResetDiscoveryTagTest extends TestCase
{
    private const string RESET_TAG_LITERAL = 'kernel.reset';
    private const string RESET_CONFIG_KEY_LITERAL = 'foundation.reset.tag';
    private const string RESET_TAG_CONSTANT = 'KERNEL_RESET';
    private const string RESET_INTERFACE_SHORT_NAME = 'ResetInterface';

    public function testKernelSourceContainsNoResetDiscoveryTagLiteralOrConfigKey(): void
    {
        $violations = [];

        foreach ($this->kernelSourceFiles() as $file) {
            $source = self::readSource($file);

            $violations = [
                ...$violations,
                ...self::forbiddenStringLiteralViolations(
                    source: $source,
                    file: $file,
                    forbiddenLiterals: [
                        self::RESET_TAG_LITERAL,
                        self::RESET_CONFIG_KEY_LITERAL,
                    ],
                ),
            ];
        }

        self::assertSame(
            [],
            $violations,
            "core/kernel/src/** must not contain reset-discovery string literals.\n"
            . self::formatViolations($violations),
        );
    }

    public function testKernelSourceDoesNotReferenceFoundationKernelResetTagConstant(): void
    {
        $violations = [];

        foreach ($this->kernelSourceFiles() as $file) {
            $source = self::readSource($file);

            $violations = [
                ...$violations,
                ...self::forbiddenIdentifierViolations(
                    source: $source,
                    file: $file,
                    forbiddenIdentifiers: [
                        self::RESET_TAG_CONSTANT,
                    ],
                ),
            ];
        }

        self::assertSame(
            [],
            $violations,
            "core/kernel/src/** must not reference Foundation Tags::KERNEL_RESET and must not define a competing KERNEL_RESET constant.\n"
            . self::formatViolations($violations),
        );
    }

    public function testKernelRuntimeSourceDoesNotImportResetInterface(): void
    {
        $violations = [];

        foreach ($this->kernelRuntimeSourceFiles() as $file) {
            $source = self::readSource($file);

            $violations = [
                ...$violations,
                ...self::resetInterfaceReferenceViolations($source, $file),
            ];
        }

        self::assertSame(
            [],
            $violations,
            "core/kernel/src/Runtime/** must not import or reference Coretsia\\Contracts\\Runtime\\ResetInterface.\n"
            . self::formatViolations($violations),
        );
    }

    public function testKernelRuntimeDoesNotCallResetInterfaceResetDirectly(): void
    {
        $runtimeFile = self::kernelRuntimeFile();
        $source = self::readSource($runtimeFile);

        $violations = self::directResetCallViolations($source, $runtimeFile);

        self::assertSame(
            [],
            $violations,
            "KernelRuntime must call ResetOrchestrator::resetAll() only; it must not call ResetInterface::reset() directly.\n"
            . self::formatViolations($violations),
        );
    }

    public function testKernelSourceDoesNotEnumerateResetServicesThroughTagRegistry(): void
    {
        $violations = [];

        foreach ($this->kernelSourceFiles() as $file) {
            $source = self::readSource($file);

            $violations = [
                ...$violations,
                ...self::resetTagRegistryEnumerationViolations($source, $file),
            ];
        }

        self::assertSame(
            [],
            $violations,
            "core/kernel/src/** must not enumerate reset services through TagRegistry; reset discovery is owned by Foundation ResetOrchestrator.\n"
            . self::formatViolations($violations),
        );
    }

    public function testKernelRuntimeDependsOnResetOrchestratorBoundaryOnly(): void
    {
        $runtimeReflection = new ReflectionClass(KernelRuntime::class);
        $constructor = $runtimeReflection->getConstructor();

        self::assertNotNull($constructor);

        $parameterTypes = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                continue;
            }

            self::assertTrue($type instanceof \ReflectionNamedType);

            $parameterTypes[] = $type->getName();
        }

        self::assertContains(ResetOrchestrator::class, $parameterTypes);
        self::assertNotContains(ResetInterface::class, $parameterTypes);
    }

    public function testResetTagOwnershipRemainsInFoundation(): void
    {
        self::assertSame(self::RESET_TAG_LITERAL, FoundationTags::KERNEL_RESET);

        $foundationTagsReflection = new ReflectionClass(FoundationTags::class);

        self::assertTrue($foundationTagsReflection->hasConstant(self::RESET_TAG_CONSTANT));
    }

    /**
     * @return list<non-empty-string>
     */
    private function kernelSourceFiles(): array
    {
        return self::phpFilesUnder($this->kernelPackageRoot() . '/src');
    }

    /**
     * @return list<non-empty-string>
     */
    private function kernelRuntimeSourceFiles(): array
    {
        return self::phpFilesUnder($this->kernelPackageRoot() . '/src/Runtime');
    }

    /**
     * @return list<non-empty-string>
     */
    private static function phpFilesUnder(string $root): array
    {
        self::assertDirectoryExists($root);

        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if ($fileInfo->getExtension() !== 'php') {
                continue;
            }

            $path = $fileInfo->getPathname();

            if ($path === '') {
                continue;
            }

            $normalized = self::normalizePath($path);

            if (
                \str_contains($normalized, '/tests/')
                || \str_contains($normalized, '/fixtures/')
                || \str_contains($normalized, '/Fixtures/')
            ) {
                continue;
            }

            $files[] = $path;
        }

        \usort(
            $files,
            static fn (string $left, string $right): int => \strcmp($left, $right),
        );

        self::assertNotSame([], $files);

        /** @var list<non-empty-string> $files */
        return $files;
    }

    /**
     * @param list<non-empty-string> $forbiddenLiterals
     *
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function forbiddenStringLiteralViolations(
        string $source,
        string $file,
        array $forbiddenLiterals,
    ): array {
        $violations = [];

        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if ($tokenId !== \T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            foreach ($forbiddenLiterals as $literal) {
                if (!\str_contains($text, $literal)) {
                    continue;
                }

                $violations[] = self::violation(
                    file: $file,
                    line: $line,
                    token: $literal,
                    message: \sprintf(
                        'Forbidden reset-discovery string literal "%s".',
                        $literal,
                    ),
                );
            }
        }

        return $violations;
    }

    /**
     * @param list<non-empty-string> $forbiddenIdentifiers
     *
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function forbiddenIdentifierViolations(
        string $source,
        string $file,
        array $forbiddenIdentifiers,
    ): array {
        $violations = [];

        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if ($tokenId !== \T_STRING) {
                continue;
            }

            if (!\in_array($text, $forbiddenIdentifiers, true)) {
                continue;
            }

            $violations[] = self::violation(
                file: $file,
                line: $line,
                token: $text,
                message: \sprintf(
                    'Forbidden reset-discovery identifier "%s".',
                    $text,
                ),
            );
        }

        return $violations;
    }

    /**
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function resetInterfaceReferenceViolations(string $source, string $file): array
    {
        $violations = [];

        foreach (\token_get_all($source) as $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if (
                \in_array(
                    $tokenId,
                    [
                        \T_STRING,
                        \T_NAME_QUALIFIED,
                        \T_NAME_FULLY_QUALIFIED,
                    ],
                    true,
                )
                && (
                    $text === self::RESET_INTERFACE_SHORT_NAME
                    || $text === ResetInterface::class
                    || $text === '\\' . ResetInterface::class
                )
            ) {
                $violations[] = self::violation(
                    file: $file,
                    line: $line,
                    token: $text,
                    message: 'Kernel Runtime source must not import or reference ResetInterface.',
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function directResetCallViolations(string $source, string $file): array
    {
        $tokens = \token_get_all($source);
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if ($tokenId !== \T_STRING || $text !== 'reset') {
                continue;
            }

            $previous = self::previousMeaningfulToken($tokens, $index - 1);
            $next = self::nextMeaningfulToken($tokens, $index + 1);

            if (
                $next === '('
                && self::isObjectOrStaticAccessToken($previous)
            ) {
                $violations[] = self::violation(
                    file: $file,
                    line: $line,
                    token: 'reset',
                    message: 'KernelRuntime must not call reset() directly; use ResetOrchestrator::resetAll().',
                );
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file:string,line:int,token:string,message:string}>
     */
    private static function resetTagRegistryEnumerationViolations(string $source, string $file): array
    {
        $tokens = \token_get_all($source);
        $violations = [];

        foreach ($tokens as $index => $token) {
            if (!\is_array($token)) {
                continue;
            }

            [$tokenId, $text, $line] = $token;

            if ($tokenId !== \T_STRING || $text !== 'all') {
                continue;
            }

            $previous = self::previousMeaningfulToken($tokens, $index - 1);
            $next = self::nextMeaningfulToken($tokens, $index + 1);

            if (
                $next !== '('
                || !self::isObjectOrStaticAccessToken($previous)
            ) {
                continue;
            }

            $nearbySource = self::nearbyTokenText($tokens, $index, radius: 16);

            if (!self::looksLikeResetDiscoveryContext($nearbySource)) {
                continue;
            }

            $violations[] = self::violation(
                file: $file,
                line: $line,
                token: 'all',
                message: 'Kernel source must not enumerate reset services through TagRegistry::all().',
            );
        }

        return $violations;
    }

    private static function looksLikeResetDiscoveryContext(string $nearbySource): bool
    {
        $normalized = \strtolower($nearbySource);

        return \str_contains($normalized, 'reset')
            || \str_contains($nearbySource, self::RESET_TAG_CONSTANT)
            || \str_contains($nearbySource, self::RESET_TAG_LITERAL)
            || \str_contains($nearbySource, self::RESET_CONFIG_KEY_LITERAL);
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     */
    private static function nearbyTokenText(array $tokens, int $index, int $radius): string
    {
        $from = \max(0, $index - $radius);
        $to = \min(\count($tokens) - 1, $index + $radius);

        $text = '';

        for ($i = $from; $i <= $to; ++$i) {
            $token = $tokens[$i];

            $text .= \is_array($token) ? $token[1] : $token;
        }

        return $text;
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     *
     * @return string|array{0:int,1:string,2:int}|null
     */
    private static function nextMeaningfulToken(array $tokens, int $index): string|array|null
    {
        $count = \count($tokens);

        for ($i = $index; $i < $count; ++$i) {
            $token = $tokens[$i];

            if (self::isIgnorableToken($token)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * @param list<int|string|array{0:int,1:string,2:int}> $tokens
     *
     * @return string|array{0:int,1:string,2:int}|null
     */
    private static function previousMeaningfulToken(array $tokens, int $index): string|array|null
    {
        for ($i = $index; $i >= 0; --$i) {
            $token = $tokens[$i];

            if (self::isIgnorableToken($token)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    private static function isIgnorableToken(mixed $token): bool
    {
        return \is_array($token)
            && \in_array(
                $token[0],
                [
                    \T_WHITESPACE,
                    \T_COMMENT,
                    \T_DOC_COMMENT,
                ],
                true,
            );
    }

    private static function isObjectOrStaticAccessToken(mixed $token): bool
    {
        return \is_array($token)
            && \in_array(
                $token[0],
                [
                    \T_OBJECT_OPERATOR,
                    \T_DOUBLE_COLON,
                ],
                true,
            );
    }

    private static function readSource(string $file): string
    {
        $source = \file_get_contents($file);

        self::assertIsString($source);

        return $source;
    }

    private function kernelPackageRoot(): string
    {
        $runtimeFile = self::kernelRuntimeFile();

        return \dirname($runtimeFile, 3);
    }

    private static function kernelRuntimeFile(): string
    {
        $runtimeFile = new ReflectionClass(KernelRuntime::class)->getFileName();

        self::assertIsString($runtimeFile);

        return $runtimeFile;
    }

    /**
     * @return array{file:string,line:int,token:string,message:string}
     */
    private static function violation(
        string $file,
        int $line,
        string $token,
        string $message,
    ): array {
        return [
            'file' => self::normalizePath($file),
            'line' => $line,
            'token' => $token,
            'message' => $message,
        ];
    }

    /**
     * @param list<array{file:string,line:int,token:string,message:string}> $violations
     */
    private static function formatViolations(array $violations): string
    {
        if ($violations === []) {
            return '';
        }

        $lines = [];

        foreach ($violations as $violation) {
            $lines[] = \sprintf(
                '- %s:%d [%s] %s',
                $violation['file'],
                $violation['line'],
                $violation['token'],
                $violation['message'],
            );
        }

        return \implode("\n", $lines);
    }

    private static function normalizePath(string $path): string
    {
        return \str_replace('\\', '/', $path);
    }
}
