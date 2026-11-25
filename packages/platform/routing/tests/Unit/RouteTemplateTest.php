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

namespace Coretsia\Platform\Routing\Tests\Unit;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Platform\Routing\Compile\RouteTemplate;
use Coretsia\Platform\Routing\Definition\RouteGroup;
use Coretsia\Platform\Routing\Exception\RouteDefinitionInvalidException;
use PHPUnit\Framework\TestCase;

final class RouteTemplateTest extends TestCase
{
    public function testCanonicalTemplateGrammarAndByteBoundary(): void
    {
        foreach (['/', '/a', '/_/-/a0-b_c', '/{Name_1}/{_id}', '/' . \str_repeat('a', 255)] as $path) {
            $template = RouteTemplate::parse($path, []);
            self::assertSame($path, $template->template());
            self::assertSame($path === '/' ? 0 : \substr_count($path, '/'), $template->segmentCount());
        }
        self::assertSame([], RouteTemplate::parse('/', [])->placeholderNames());
        self::assertSame(['Name_1', '_id'], RouteTemplate::parse('/{Name_1}/{_id}', [])->placeholderNames());
        self::assertSame(['id', 'ID'], RouteTemplate::parse('/{id}/{ID}', [])->placeholderNames());
    }

    public function testMalformedAndUnsupportedTemplatesAreRejected(): void
    {
        foreach (
            [
                '',
                'relative',
                '//',
                '/a/',
                '/a//b',
                '/.',
                '/..',
                '/a/../b',
                '/A',
                '/1a',
                '/a.b',
                '/a b',
                '/a%20b',
                '/a?b',
                '/a#b',
                '/a\\b',
                '/é',
                "/a\n",
                '/{}',
                '/{1id}',
                '/{id-name}',
                '/{id?}',
                '/{id*}',
                '/{id}/{id}',
                '/x{id}',
                '/{id}x',
                '/{a}{b}',
                '/*',
                '/' . \str_repeat('a', 256),
            ] as $path
        ) {
            self::assertRejected($path, [], RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID);
        }
    }

    public function testOnlySupportedRequirementsForDeclaredPlaceholdersAreAccepted(): void
    {
        foreach (['[0-9]+', '[A-Za-z]+', '[A-Za-z0-9_-]+'] as $expression) {
            self::assertSame(['id'], RouteTemplate::parse('/{id}', ['id' => $expression])->placeholderNames());
        }
        foreach (
            [
                ['other' => '[0-9]+'],
                ['[0-9]+'],
                ['id' => null],
                ['id' => 1],
                ['id' => ''],
                ['id' => '.*'],
                ['id' => '\d+'],
                ['id' => '[0-9]*'],
                ['id' => '^[0-9]+$'],
                ['id' => '[a-z]+'],
            ] as $requirements
        ) {
            self::assertRejected('/{id}', $requirements, RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID);
        }
        self::assertRejected('/', ['id' => '[0-9]+'], RouteDefinitionInvalidException::REASON_REQUIREMENT_INVALID);
    }

    public function testRepresentativesAndDomainSignaturesAreDeterministic(): void
    {
        $template = RouteTemplate::parse('/fixed/{num}/{alpha}/{word}/{free}', [
            'num' => '[0-9]+',
            'alpha' => '[A-Za-z]+',
            'word' => '[A-Za-z0-9_-]+',
        ]);
        foreach (['fixed', '0', 'a', 'a', 'a'] as $index => $expected) {
            self::assertSame($expected, $template->representativeSegment($index));
            self::assertSame($expected, $template->representativeSegment($index));
            self::assertTrue($template->segmentMatches($index, $expected));
        }
        self::assertFalse($template->segmentMatches(0, 'Fixed'));
        self::assertFalse($template->segmentMatches(5, 'a'));
        self::assertFalse($template->segmentMatches(4, ''));
        self::assertSame(
            RouteTemplate::parse('/items/{id}', ['id' => '[0-9]+'])->domainSignature(),
            RouteTemplate::parse('/items/{number}', ['number' => '[0-9]+'])->domainSignature(),
        );
        self::assertNotSame(
            RouteTemplate::parse('/items/{id}', ['id' => '[0-9]+'])->domainSignature(),
            RouteTemplate::parse('/items/{id}', [])->domainSignature(),
        );
        try {
            $template->representativeSegment(5);
            self::fail('Expected invalid segment index.');
        } catch (RouteDefinitionInvalidException $exception) {
            self::assertSame(RouteDefinitionInvalidException::REASON_TEMPLATE_INVALID, $exception->reason());
        }
    }

    public function testFlattenedInheritedPlaceholdersUseTheSameTemplateGrammar(): void
    {
        $route = RouteGroup::root()
            ->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], [])
            ->group('/members/{member}', 'members', ['member' => '[A-Za-z]+'], [])
            ->route(new RouteDefinition('show', ['GET'], '/{item}', 'Handler::show', ['item' => '[A-Za-z0-9_-]+']));
        $template = RouteTemplate::parse($route->pathTemplate(), $route->requirements());
        self::assertSame('/teams/{team}/members/{member}/{item}', $template->template());
        self::assertSame(['team', 'member', 'item'], $template->placeholderNames());
        self::assertSame(
            ['team' => '42', 'member' => 'Alice', 'item' => 'x-2'],
            $template->match('/teams/42/members/Alice/x-2'),
        );
        self::assertNull($template->match('/teams/a/members/Alice/x-2'));
        self::assertNull($template->match('/teams/42/members/Alice1/x-2'));
    }

    /** @param array<mixed> $requirements */
    private static function assertRejected(string $path, array $requirements, string $reason): void
    {
        try {
            RouteTemplate::parse($path, $requirements);
            self::fail('Expected route template rejection: ' . $path);
        } catch (RouteDefinitionInvalidException $exception) {
            self::assertSame($reason, $exception->reason());
            self::assertSame(RouteDefinitionInvalidException::ERROR_CODE . ': ' . $reason, $exception->getMessage());
        }
    }
}
