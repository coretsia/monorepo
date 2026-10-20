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

namespace Coretsia\Platform\Cli\Tests\Unit;

use Coretsia\Foundation\Serialization\JsonLikeNormalizer;
use Coretsia\Platform\Cli\Catalog\CommandTagSchema;
use Coretsia\Platform\Cli\Exception\WorkflowDefinitionInvalidException;
use Coretsia\Platform\Cli\Workflow\WorkflowSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WorkflowSchemaTest extends TestCase
{
    public function testExactCanonicalShapePreservesByteOrderNamesAndAllDeclaredLists(): void
    {
        $definitions = [
            'zeta' => self::definition('Last workflow.'),
            'alpha-two' => self::definition('Hyphenated workflow.', [
                self::step('config:validate', ['second', 'first'], [
                    'z-option' => ['third', 'first', 'second'],
                    'a-flag' => true,
                    'b-option' => 'single',
                ]),
                self::step('cache:verify', ['--target=web', 'literal'], ['target' => 'web']),
            ]),
            'alpha' => self::definition('First workflow.'),
        ];

        $normalized = new WorkflowSchema()->normalize($definitions);

        self::assertSame(['alpha', 'alpha-two', 'zeta'], \array_keys($normalized));
        self::assertSame('alpha-two', $normalized['alpha-two']->name());
        self::assertSame('Hyphenated workflow.', $normalized['alpha-two']->summary());
        self::assertSame(
            ['config:validate', 'cache:verify'],
            \array_map(
                static fn ($step): string => $step->command(),
                $normalized['alpha-two']->steps(),
            )
        );
        self::assertSame(['second', 'first'], $normalized['alpha-two']->steps()[0]->arguments());
        self::assertSame(['--target=web', 'literal'], $normalized['alpha-two']->steps()[1]->arguments());
        self::assertSame(
            ['a-flag', 'b-option', 'z-option'],
            \array_keys(
                $normalized['alpha-two']->steps()[0]->options(),
            )
        );
        self::assertSame(['third', 'first', 'second'], $normalized['alpha-two']->steps()[0]->options()['z-option']);
        self::assertSame('single', $normalized['alpha-two']->steps()[0]->options()['b-option']);
        self::assertSame(true, $normalized['alpha-two']->steps()[0]->options()['a-flag']);
        self::assertSame(\array_keys($normalized), \array_keys(new WorkflowSchema()->normalize($definitions)));
        self::assertSame(['zeta', 'alpha-two', 'alpha'], \array_keys($definitions));
    }

    public function testFoundationNormalizesMapKeysWhileWorkflowSchemaAddsSpecificShapeChecks(): void
    {
        $value = self::definition('Description.', [
            self::step('external:mode', [], [
                'z-option' => 'second',
                'a-option' => 'first',
            ]),
        ]);
        $canonical = JsonLikeNormalizer::normalize(['zeta' => $value, 'alpha-one' => $value]);
        $workflows = new WorkflowSchema()->normalize(['zeta' => $value, 'alpha-one' => $value]);

        self::assertSame(\array_keys($canonical), \array_keys($workflows));
        self::assertSame(
            \array_keys($canonical['alpha-one']['steps'][0]['options']),
            \array_keys(
                $workflows['alpha-one']->steps()[0]->options(),
            ),
        );
        self::assertSame(['a-option', 'z-option'], \array_keys($workflows['alpha-one']->steps()[0]->options()));
        self::assertSame([], new WorkflowSchema()->normalize([]));
    }

    public function testExactLocalBoundsAreAcceptedWithinTheSharedAggregateBudget(): void
    {
        $workflowName = 'w' . \str_repeat('a', 63);
        $command = 'c' . \str_repeat('a', 511);
        $optionName = 'o' . \str_repeat('a', 511);
        $arguments = \array_fill(0, 32, \str_repeat('a', 512));
        $repeatables = \array_fill(0, 32, \str_repeat('v', 512));
        $boundaryStep = self::step($command, $arguments, [$optionName => $repeatables]);
        $definitions = [
            $workflowName => self::definition(
                \str_repeat('s', 256),
                \array_fill(0, 64, $boundaryStep),
            ),
        ];
        for ($index = 0; $index < 127; ++$index) {
            $definitions['flow-' . $index] = self::definition();
        }

        $normalized = new WorkflowSchema()->normalize($definitions);
        self::assertCount(128, $normalized);
        self::assertSame(64, \strlen($normalized[$workflowName]->name()));
        self::assertSame(256, \strlen($normalized[$workflowName]->summary()));
        self::assertCount(64, $normalized[$workflowName]->steps());
        self::assertSame($command, $normalized[$workflowName]->steps()[0]->command());
        self::assertSame($arguments, $normalized[$workflowName]->steps()[0]->arguments());
        self::assertSame($repeatables, $normalized[$workflowName]->steps()[0]->options()[$optionName]);
        self::assertSame(512, \strlen($optionName));
    }

    public function testAggregateNodeBudgetAppliesToCompleteMapRatherThanEveryWorkflowSeparately(): void
    {
        $options = [];
        for ($index = 0; $index < 32; ++$index) {
            $options['option-' . $index] = 'x';
        }
        $definition = self::definition(
            'Within local bounds.',
            \array_fill(
                0,
                64,
                self::step(
                    'external:mode',
                    [],
                    $options,
                ),
            ),
        );
        self::assertCount(64, new WorkflowSchema()->normalize(['one' => $definition])['one']->steps());

        $definitions = [];
        for ($index = 0; $index < 80; ++$index) {
            $definitions['workflow-' . $index] = $definition;
        }
        self::assertCount(80, $definitions);
        self::assertInvalid($definitions, ['workflow-79', 'option-31', 'cli.workflows.definitions']);
    }

    public function testAggregateStringByteBudgetAppliesAcrossAllDefinitions(): void
    {
        $options = [];
        for ($index = 0; $index < 32; ++$index) {
            $options['option-' . $index] = \str_repeat('x', 480);
        }
        $definition = self::definition(
            'Within local bounds.',
            \array_fill(
                0,
                20,
                self::step(
                    'external:mode',
                    [],
                    $options,
                ),
            ),
        );
        self::assertCount(20, new WorkflowSchema()->normalize(['one' => $definition])['one']->steps());

        $definitions = [];
        for ($index = 0; $index < 32; ++$index) {
            $definitions['workflow-' . $index] = $definition;
        }
        self::assertInvalid($definitions, ['workflow-31', 'option-31', \str_repeat('x', 480)]);
    }

    public function testValidDefinitionsNearAggregateBoundsRetainCanonicalAndDeclaredOrder(): void
    {
        $steps = \array_fill(0, 60, self::step('external:mode', ['later', 'earlier'], [
            'z-option' => ['third', 'first', 'second'],
            'a-option' => 'value',
        ]));
        $definitions = [];
        for ($index = 12; $index >= 0; --$index) {
            $definitions['batch-' . $index] = self::definition('Valid aggregated workflow.', $steps);
        }

        $normalized = new WorkflowSchema()->normalize($definitions);
        self::assertSame('batch-0', \array_key_first($normalized));
        self::assertSame('batch-9', \array_key_last($normalized));
        self::assertCount(13, $normalized);
        self::assertCount(60, $normalized['batch-0']->steps());
        self::assertSame(['later', 'earlier'], $normalized['batch-0']->steps()[59]->arguments());
        self::assertSame(['a-option', 'z-option'], \array_keys($normalized['batch-0']->steps()[59]->options()));
        self::assertSame(['third', 'first', 'second'], $normalized['batch-0']->steps()[59]->options()['z-option']);
    }

    public function testExcessiveNestedDepthFailsClosedBeforeImmutableValuesAreConstructed(): void
    {
        $deep = 'PRIVATE_NESTED_VALUE';
        for ($depth = 0; $depth < 256; ++$depth) {
            $deep = ['nested' => $deep];
        }
        $definitions = [
            'private-path' => self::definition('Bad nested value.', [
                self::step(
                    'external:mode',
                    [],
                    ['unsafe' => $deep],
                ),
            ]),
        ];

        self::assertInvalid($definitions, ['private-path', 'PRIVATE_NESTED_VALUE', 'nested']);
        self::assertInvalid($definitions, ['private-path', 'PRIVATE_NESTED_VALUE', 'nested']);
    }

    #[DataProvider('invalidDefinitionCases')]
    public function testRejectsInvalidShapesTypesKeysStringsAndLocalBounds(array $definitions): void
    {
        self::assertInvalid($definitions);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidDefinitionCases(): iterable
    {
        yield 'list-root' => [[[self::definition()]]];
        yield 'too-many-workflows' => [
            \array_fill_keys(
                \array_map(
                    static fn (int $index): string => 'wf-' . $index,
                    \range(0, 128),
                ),
                self::definition(),
            ),
        ];
        yield 'invalid-workflow-token' => [['Bad Workflow' => self::definition()]];
        yield 'workflow-token-too-long' => [[\str_repeat('a', 65) => self::definition()]];
        yield 'numeric-workflow-name' => [[1 => self::definition()]];
        yield 'unknown-definition-key' => [
            [
                'private-name' => self::definition(
                    'Valid summary.',
                    extra: ['macro' => 'PRIVATE_VALUE'],
                ),
            ],
        ];
        yield 'template-definition-key' => [['verify' => self::definition(extra: ['template' => 'unsafe'])]];
        yield 'missing-summary' => [['verify' => ['steps' => [self::step()]]]];
        yield 'missing-steps' => [['verify' => ['summary' => 'Summary.']]];
        yield 'empty-summary' => [['verify' => self::definition('')]];
        yield 'trimmed-summary' => [['verify' => self::definition(' Leading summary. ')]];
        yield 'summary-too-long' => [['verify' => self::definition(\str_repeat('a', 257))]];
        yield 'empty-steps' => [['verify' => self::definition(steps: [])]];
        yield 'too-many-steps' => [['verify' => self::definition(steps: \array_fill(0, 65, self::step()))]];
        yield 'step-map-not-list' => [['verify' => self::definition(steps: ['step' => self::step()])]];
        yield 'invalid-step-type' => [['verify' => self::definition(steps: ['bad-step'])]];
        yield 'unexpected-step-key' => [['verify' => self::definition(steps: [self::step(extra: ['retry' => 2])])]];
        yield 'nested-macro' => [['verify' => self::definition(steps: [self::step(extra: ['macro' => 'name'])])]];
        yield 'command-uppercase' => [['verify' => self::definition(steps: [self::step('NotValid')])]];
        yield 'command-too-long' => [['verify' => self::definition(steps: [self::step(\str_repeat('a', 513))])]];
        yield 'too-many-arguments' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        arguments: \array_fill(
                            0,
                            33,
                            'v',
                        ),
                    ),
                ]),
            ],
        ];
        yield 'arguments-not-list' => [['verify' => self::definition(steps: [self::step(arguments: ['key' => 'v'])])]];
        yield 'invalid-argument-type' => [['verify' => self::definition(steps: [self::step(arguments: [null])])]];
        yield 'argument-too-long' => [
            [
                'verify' => self::definition(steps: [
                    self::step(arguments: [
                        \str_repeat(
                            'a',
                            513,
                        ),
                    ]),
                ]),
            ],
        ];
        yield 'unsafe-argument' => [
            [
                'verify' => self::definition(
                    steps: [self::step(arguments: ["SECRET\x00VALUE"])],
                ),
            ],
        ];
        yield 'argument-float' => [['verify' => self::definition(steps: [self::step(arguments: [1.25])])]];
        yield 'options-not-map' => [['verify' => self::definition(steps: [self::step(options: ['value'])])]];
        yield 'too-many-options' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: \array_fill_keys(
                            \array_map(
                                static fn (int $index): string => 'opt-' . $index,
                                \range(0, 32),
                            ),
                            'v',
                        ),
                    ),
                ]),
            ],
        ];
        yield 'option-name-too-long' => [
            [
                'verify' => self::definition(steps: [
                    self::step(options: [
                        \str_repeat(
                            'a',
                            513,
                        ) => 'v',
                    ]),
                ]),
            ],
        ];
        yield 'invalid-option-name' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['Bad Option' => 'v'],
                    ),
                ]),
            ],
        ];
        yield 'reserved-format' => [['verify' => self::definition(steps: [self::step(options: ['format' => 'json'])])]];
        yield 'reserved-color' => [['verify' => self::definition(steps: [self::step(options: ['color' => 'never'])])]];
        yield 'option-false' => [['verify' => self::definition(steps: [self::step(options: ['flag' => false])])]];
        yield 'option-null' => [['verify' => self::definition(steps: [self::step(options: ['flag' => null])])]];
        yield 'option-float' => [['verify' => self::definition(steps: [self::step(options: ['value' => 1.25])])]];
        yield 'option-too-long' => [
            [
                'verify' => self::definition(steps: [
                    self::step(options: [
                        'value' => \str_repeat(
                            'a',
                            513,
                        ),
                    ]),
                ]),
            ],
        ];
        yield 'option-unsafe' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => "SECRET\rVALUE"],
                    ),
                ]),
            ],
        ];
        yield 'option-empty-repeatables' => [
            [
                'verify' => self::definition(
                    steps: [self::step(options: ['value' => []])],
                ),
            ],
        ];
        yield 'option-too-many-repeatables' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => \array_fill(0, 33, 'v')],
                    ),
                ]),
            ],
        ];
        yield 'option-empty-repeatable-item' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => ['ok', '']],
                    ),
                ]),
            ],
        ];
        yield 'option-repeatables-not-list' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => ['one' => 'v']],
                    ),
                ]),
            ],
        ];
        yield 'option-repeatables-invalid-value' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => ['v', false]],
                    ),
                ]),
            ],
        ];
        yield 'closure' => [
            [
                'verify' => self::definition(steps: [
                    self::step(options: [
                        'value' => static fn (): int => 1,
                    ]),
                ]),
            ],
        ];
        yield 'object' => [['verify' => self::definition(steps: [self::step(options: ['value' => new \stdClass()])])]];
        yield 'invalid-utf8-option' => [
            [
                'verify' => self::definition(steps: [
                    self::step(
                        options: ['value' => "\xC3\x28"],
                    ),
                ]),
            ],
        ];
        yield 'invalid-utf8-summary' => [['verify' => self::definition("Invalid \xC3\x28 summary.")]];
    }

    #[DataProvider('invalidSummaryCases')]
    public function testRejectsUnsafeWorkflowSummariesEvenIfFoundationAcceptsTheirValue(string $summary): void
    {
        $normalized = JsonLikeNormalizer::normalize(['summary' => $summary]);
        self::assertSame($summary, $normalized['summary']);
        self::assertInvalid(['verify' => self::definition($summary)]);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidSummaryCases(): iterable
    {
        yield 'nul-Cc' => ["Before\0After"];
        yield 'tab-Cc' => ["Before\tAfter"];
        yield 'newline-Cc' => ["Before\nAfter"];
        yield 'nel-U+0085-Cc' => ["Before\u{0085}After"];
        yield 'line-separator-U+2028-Zl' => ["Before\u{2028}After"];
        yield 'paragraph-separator-U+2029-Zp' => ["Before\u{2029}After"];
    }

    public function testStaticTagSummaryValidationIsNotWorkflowSummaryValidation(): void
    {
        $value = ' Valid command summary. ';
        self::assertTrue(CommandTagSchema::isSafeSummary($value));
        self::assertInvalid(['verify' => self::definition($value)]);
        self::assertSame(
            'Valid command summary.',
            new WorkflowSchema()->normalize([
                'verify' => self::definition('Valid command summary.'),
            ])['verify']->summary(),
        );
    }

    public function testMalformedUtf8IsRejectedThroughTheExistingStableEncoder(): void
    {
        $invalidUtf8 = "Summary \xC3\x28 value";
        self::assertSame($invalidUtf8, JsonLikeNormalizer::normalize(['summary' => $invalidUtf8])['summary']);
        self::assertInvalid(['verify' => self::definition($invalidUtf8)], ['cli.workflows.definitions']);
    }

    public function testResourcesAndDeepFailuresNeverExposeFoundationPathsOrRawValues(): void
    {
        $resource = \fopen('php://memory', 'r+');
        self::assertIsResource($resource);
        try {
            self::assertInvalid([
                'private-workflow' => self::definition(
                    'Valid summary.',
                    [self::step('private:command', [], ['private-option' => $resource])],
                ),
            ], ['private-workflow', 'private:command', 'private-option', 'php://memory', 'cli.workflows.definitions']);
        } finally {
            \fclose($resource);
        }
    }

    public function testSchemaUsesOneFoundationNormalizationBoundaryWithoutIndependentSortOrWalker(): void
    {
        $method = new \ReflectionMethod(WorkflowSchema::class, 'normalize');
        $filename = $method->getFileName();
        self::assertIsString($filename);
        $lines = \file($filename);
        self::assertIsArray($lines);
        $source = \implode(
            '',
            \array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ),
        );

        self::assertSame(1, \preg_match_all('/JsonLikeNormalizer::normalize\s*\(/', $source));
        self::assertSame(1, \preg_match_all('/StableJsonEncoder::encodeStable\s*\(\s*\$summary\s*\)/', $source));
        self::assertStringContainsString('new JsonLikeNormalizationLimits(', $source);
        self::assertStringContainsString('maxDepth: 8', $source);
        self::assertStringContainsString('maxNodes: 131072', $source);
        self::assertStringContainsString('maxStringBytes: 512', $source);
        self::assertStringContainsString('maxTotalStringBytes: 8388608', $source);
        self::assertSame(0, \preg_match_all('/\b(?:sort|ksort|uksort|usort|array_multisort)\s*\(/', $source));
        self::assertSame(0, \preg_match_all('/\$this\s*->\s*normalize\s*\(/', $source));
    }

    public function testCommandReferenceValidationRemainsExclusiveToWorkflowCatalog(): void
    {
        $definitions = new WorkflowSchema()->normalize([
            'verify' => self::definition('Defer command-reference validation.', [
                self::step('not-registered:command'),
                self::step('workflow:run'),
            ]),
        ]);

        self::assertSame('not-registered:command', $definitions['verify']->steps()[0]->command());
        self::assertSame('workflow:run', $definitions['verify']->steps()[1]->command());
    }

    public function testSchemaReservesCommandReferenceReasonsAndRejectsNonAllowlistedReasons(): void
    {
        foreach (
            [
                WorkflowDefinitionInvalidException::REASON_COMMAND_UNKNOWN,
                WorkflowDefinitionInvalidException::REASON_COMMAND_COMPOSITE,
            ] as $reason
        ) {
            $referenceError = WorkflowDefinitionInvalidException::withReason($reason);
            self::assertSame($reason, $referenceError->reason());
            self::assertSame(
                WorkflowDefinitionInvalidException::ERROR_CODE . ': ' . $reason,
                $referenceError->getMessage(),
            );
        }

        try {
            WorkflowDefinitionInvalidException::withReason('PRIVATE_INVALID_REASON');
            self::fail('Unknown reason tokens must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('cli-workflow-definition-reason-invalid', $exception->getMessage());
        }

        $definitions = [
            'private-workflow' => self::definition('Unsafe summary.', [
                self::step('missing:command', [], ['private-option' => false]),
            ]),
        ];
        for ($iteration = 0; $iteration < 3; ++$iteration) {
            self::assertInvalid($definitions, ['private-workflow', 'missing:command', 'private-option']);
        }
    }

    /**
     * @param list<mixed>|array<string, mixed> $steps
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function definition(
        mixed $summary = 'Valid workflow summary.',
        array $steps = [['command' => 'external:mode', 'arguments' => [], 'options' => []]],
        array $extra = [],
    ): array {
        return \array_merge(['summary' => $summary, 'steps' => $steps], $extra);
    }

    /**
     * @param array<array-key, mixed> $arguments
     * @param array<array-key, mixed> $options
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private static function step(
        mixed $command = 'external:mode',
        array $arguments = [],
        array $options = [],
        array $extra = [],
    ): array {
        return \array_merge(['command' => $command, 'arguments' => $arguments, 'options' => $options], $extra);
    }

    /** @param array<array-key, mixed> $definitions @param list<string> $secrets */
    private static function assertInvalid(array $definitions, array $secrets = []): void
    {
        try {
            new WorkflowSchema()->normalize($definitions);
            self::fail('Invalid workflow definitions must fail closed.');
        } catch (WorkflowDefinitionInvalidException $exception) {
            self::assertSame(WorkflowDefinitionInvalidException::ERROR_CODE, $exception->errorCode());
            self::assertSame(WorkflowDefinitionInvalidException::REASON_DEFINITION_INVALID, $exception->reason());
            self::assertSame(
                WorkflowDefinitionInvalidException::ERROR_CODE . ': '
                . WorkflowDefinitionInvalidException::REASON_DEFINITION_INVALID,
                $exception->getMessage(),
            );
            self::assertNull($exception->getPrevious());
            foreach ($secrets as $secret) {
                self::assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }
}
