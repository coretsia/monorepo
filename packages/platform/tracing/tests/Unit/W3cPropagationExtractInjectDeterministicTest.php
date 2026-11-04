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

namespace Coretsia\Platform\Tracing\Tests\Unit;

use Coretsia\Platform\Tracing\W3c\W3cTraceContextPropagation;
use PHPUnit\Framework\TestCase;

final class W3cPropagationExtractInjectDeterministicTest extends TestCase
{
    private const string TRACE_ID = '4bf92f3577b34da6a3ce929d0e0e4736';
    private const string OTHER_TRACE_ID = 'a4bf92f3577b34da6a3ce929d0e0e473';
    private const string PARENT_ID = '00f067aa0ba902b7';
    private const string OTHER_PARENT_ID = 'b7ad6b7169203331';

    public function testAbsentTraceparentAndEmptyContextNeverCreateIdentities(): void
    {
        $propagation = new W3cTraceContextPropagation();

        self::assertSame([], $propagation->extract([]));
        self::assertSame([], $propagation->extract(['x-request-id' => 'safe']));
        self::assertSame([], $propagation->inject([], []));
        self::assertSame([], $propagation->inject([]));
        self::assertSame(['x-request-id' => 'safe'], $propagation->inject(['x-request-id' => 'safe']));
        self::assertSame([], $propagation->extract([]));
    }

    public function testVersion00ExtractsExactlyThreeCanonicalFieldsForAllSupportedFlags(): void
    {
        $propagation = new W3cTraceContextPropagation();

        foreach (['00', '01', '02', '03'] as $flags) {
            $carrier = [
                'TrAcEpArEnT' => self::traceparent($flags),
                'tracestate' => 'vendor=opaque',
                'baggage' => 'account=private',
                'authorization' => 'secret',
            ];

            $extracted = $propagation->extract($carrier);

            self::assertSame(self::context($flags), $extracted);
            self::assertSame(['trace_id', 'parent_id', 'trace_flags'], \array_keys($extracted));
            self::assertCount(3, $extracted);
            self::assertArrayNotHasKey('tracestate', $extracted);
            self::assertArrayNotHasKey('baggage', $extracted);
            self::assertArrayNotHasKey('traceparent', $extracted);
        }
    }

    public function testSingleStringListTraceparentHasTheSameExtractionPolicy(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $traceparent = self::traceparent('03');

        self::assertSame(
            $propagation->extract(['traceparent' => $traceparent]),
            $propagation->extract(['TRACEPARENT' => [$traceparent]]),
        );
        self::assertSame(self::context('03'), $propagation->extract(['traceparent' => [$traceparent]]));

        foreach (
            [
                'invalid',
                \strtoupper(self::traceparent('03')),
                self::traceparent('04'),
                self::traceparent('03', '01') . 'X',
                self::traceparent('03') . '-extension',
            ] as $invalid
        ) {
            self::assertSame([], $propagation->extract(['traceparent' => $invalid]));
            self::assertSame([], $propagation->extract(['traceparent' => [$invalid]]));
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    ['traceparent' => [$invalid], 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                    self::context('03'),
                ),
            );
        }
    }

    public function testVersion00RejectsInvalidVersionSeparatorsHexAndExactLength(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $valid = self::traceparent();

        $invalid = [
            'ff-' . \substr($valid, 3),
            'FF-' . \substr($valid, 3),
            'zz-' . \substr($valid, 3),
            '0-' . \substr($valid, 3),
            '00_' . \substr($valid, 3),
            \substr_replace($valid, '_', 35, 1),
            \substr_replace($valid, '_', 52, 1),
            \substr_replace($valid, 'G', 3, 1),
            \substr_replace($valid, 'A', 3, 1),
            \substr_replace($valid, 'G', 36, 1),
            \substr_replace($valid, 'A', 36, 1),
            \substr_replace($valid, 'G', 53, 1),
            \substr($valid, 0, 54),
            $valid . '0',
            $valid . '-future',
            $valid . "\r\n",
            \substr_replace($valid, "\0", 8, 1),
            \substr_replace($valid, "\x7f", 8, 1),
        ];

        self::assertSame(55, \strlen($valid));
        self::assertSame(self::context(), $propagation->extract(['traceparent' => $valid]));

        foreach ($invalid as $candidate) {
            self::assertSame([], $propagation->extract(['traceparent' => $candidate]));
        }
    }

    public function testZeroTraceIdOrParentIdAlwaysFailsClosed(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $zeroTrace = self::traceparent('01', '00', '00000000000000000000000000000000');
        $zeroParent = self::traceparent('01', '00', self::TRACE_ID, '0000000000000000');

        foreach ([$zeroTrace, $zeroParent] as $invalid) {
            self::assertSame([], $propagation->extract(['traceparent' => $invalid]));
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    ['traceparent' => $invalid, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                    self::context(),
                ),
            );
        }
    }

    public function testVersion00RejectsAllReservedTraceFlagBitsWithoutNormalization(): void
    {
        $propagation = new W3cTraceContextPropagation();

        foreach (['04', '05', '80', 'ff'] as $flags) {
            $invalid = self::traceparent($flags);

            self::assertSame([], $propagation->extract(['traceparent' => $invalid]));
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    ['traceparent' => $invalid, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                    self::context($flags === '05' ? '01' : '00'),
                ),
            );
        }
    }

    public function testForwardVersionsNormalizeOnlySupportedBits(): void
    {
        $propagation = new W3cTraceContextPropagation();

        foreach (['01', 'ab', 'fe'] as $version) {
            $flagCases = [
                ['00', '00'],
                ['01', '01'],
                ['02', '02'],
                ['03', '03'],
                ['04', '00'],
                ['05', '01'],
                ['80', '00'],
                ['ff', '03'],
            ];

            foreach ($flagCases as [$incomingFlags, $normalizedFlags]) {
                $traceparent = self::traceparent($incomingFlags, $version) . '-future-extension';

                self::assertSame(
                    self::context($normalizedFlags),
                    $propagation->extract(['traceparent' => $traceparent]),
                );
                self::assertSame(
                    ['traceparent' => self::traceparent($normalizedFlags)],
                    $propagation->inject(['traceparent' => $traceparent], self::context($normalizedFlags)),
                );
            }
        }
    }

    public function testForwardVersionRequiresDelimiterAfterTraceFlags(): void
    {
        $propagation = new W3cTraceContextPropagation();

        $traceId = '4bf92f3577b34da6a3ce929d0e0e4736';
        $parentId = '00f067aa0ba902b7';

        $forward = '01-' . $traceId . '-' . $parentId . '-03';

        $context = [
            'trace_id' => $traceId,
            'parent_id' => $parentId,
            'trace_flags' => '03',
        ];

        self::assertSame(
            $context,
            $propagation->extract(['traceparent' => $forward]),
        );

        self::assertSame(
            $context,
            $propagation->extract(['traceparent' => $forward . '-future']),
        );

        foreach (['X', 'Z', '0', 'abc'] as $suffix) {
            self::assertSame(
                [],
                $propagation->extract(['traceparent' => $forward . $suffix]),
            );

            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    [
                        'traceparent' => $forward . $suffix,
                        'tracestate' => 'vendor=opaque',
                        'x-request-id' => 'safe',
                    ],
                    $context,
                ),
            );
        }
    }

    public function testForwardVersionRequiresExactFirst55BytesAndExtensionDelimiter(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $forward = self::traceparent('03', '01');

        self::assertSame(self::context('03'), $propagation->extract(['traceparent' => $forward]));
        self::assertSame(self::context('03'), $propagation->extract(['traceparent' => $forward . '-future']));

        foreach (['X', 'Z', '0', 'abc', "\0", "\r"] as $suffix) {
            $invalid = $forward . $suffix;

            self::assertSame([], $propagation->extract(['traceparent' => $invalid]));
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    ['traceparent' => $invalid, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                    self::context('03'),
                ),
            );
        }

        foreach (
            [
                \substr($forward, 0, 54),
                \substr_replace($forward, '_', 2, 1),
                \substr_replace($forward, '_', 35, 1),
                \substr_replace($forward, '_', 52, 1),
                \substr_replace($forward, 'G', 3, 1),
                \substr_replace($forward, 'G', 36, 1),
                \substr_replace($forward, 'G', 53, 1),
                self::traceparent('03', 'ff'),
            ] as $invalid
        ) {
            self::assertSame([], $propagation->extract(['traceparent' => $invalid]));
        }
    }

    public function testTraceparentByteLimitAccepts512ButRejects513(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $prefix = self::traceparent('03', '01') . '-';
        $maximum = $prefix . \str_repeat('a', 512 - \strlen($prefix));
        $oversized = $maximum . 'a';

        self::assertSame(512, \strlen($maximum));
        self::assertSame(513, \strlen($oversized));
        self::assertSame(self::context('03'), $propagation->extract(['traceparent' => $maximum]));
        self::assertSame(self::context('03'), $propagation->extract(['traceparent' => [$maximum]]));
        self::assertSame([], $propagation->extract(['traceparent' => $oversized]));
        self::assertSame([], $propagation->extract(['traceparent' => [$oversized]]));
        self::assertSame(
            ['x-request-id' => 'safe'],
            $propagation->inject(
                ['traceparent' => $oversized, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                self::context('03'),
            ),
        );
    }

    public function testDuplicateTraceparentNamesIncludingCaseVariantsAreRejected(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [
            'traceparent' => self::traceparent(),
            'TRACEPARENT' => self::traceparent(),
            'tracestate' => 'vendor=opaque',
            'x-request-id' => 'safe',
        ];

        self::assertSame([], $propagation->extract($carrier));
        self::assertSame(['x-request-id' => 'safe'], $propagation->inject($carrier, self::context()));

        $carrier['TRACEPARENT'] = self::traceparent('03');
        self::assertSame([], $propagation->extract($carrier));
        self::assertSame(['x-request-id' => 'safe'], $propagation->inject($carrier, self::context()));
    }

    public function testMalformedTraceparentListsFailWithoutImplicitConversion(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $valid = self::traceparent();
        $invalidLists = [
            [],
            [$valid, $valid],
            ['value' => $valid],
            [1 => $valid],
            [false],
            [17],
            [[$valid]],
        ];

        foreach ($invalidLists as $value) {
            self::assertSame([], $propagation->extract(['traceparent' => $value]));
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    ['traceparent' => $value, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                    self::context(),
                ),
            );
        }
    }

    public function testInvalidTraceparentScalarObjectAndResourceTypesDoNotThrowOrConvert(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $stringable = new class() {
            public function __toString(): string
            {
                throw new \LogicException('Stringable conversion is forbidden');
            }

            public function __serialize(): array
            {
                throw new \LogicException('Serialization is forbidden');
            }
        };
        $resource = \fopen('php://memory', 'r+');
        self::assertIsResource($resource);

        try {
            foreach ([17, false, true, 3.5, $stringable, $resource] as $value) {
                self::assertSame([], $propagation->extract(['traceparent' => $value]));
                self::assertSame(
                    ['x-request-id' => 'safe'],
                    $propagation->inject(
                        ['traceparent' => $value, 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                        self::context(),
                    ),
                );
            }
        } finally {
            \fclose($resource);
        }
    }

    public function testInvalidCarrierKeysRejectExtractionWithoutTouchingUnrelatedValues(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [0 => 'unrelated', 'traceparent' => self::traceparent()];

        self::assertSame([], $propagation->extract($carrier));
        self::assertSame([0 => 'unrelated'], $propagation->inject($carrier, self::context()));
    }

    public function testOutOfContractUnrelatedValuesAreNotTreatedAsTracingValues(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $stringable = new class() {
            public function __toString(): string
            {
                throw new \LogicException('Do not inspect unrelated fields');
            }

            public function __serialize(): array
            {
                throw new \LogicException('Do not serialize unrelated fields');
            }
        };

        // The unrelated object is outside the port's carrier contract. This
        // test does not assert that an out-of-contract carrier is normalized.
        self::assertSame(
            self::context(),
            $propagation->extract(['x-out-of-contract' => $stringable, 'traceparent' => self::traceparent()]),
        );
    }

    public function testValidUnrelatedCarrierFieldsArePreservedWithoutNormalization(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $unrelated = [
            'Authorization' => 'caller-owned',
            'x-sequence' => ['a', 'B'],
            'baggage' => ['one=value', 'two=value'],
            'x-empty' => [],
        ];

        self::assertSame(
            $unrelated + ['traceparent' => self::traceparent()],
            $propagation->inject($unrelated, self::context()),
        );
        self::assertSame(self::context(), $propagation->extract($unrelated + ['traceparent' => self::traceparent()]));
        self::assertSame($unrelated, $propagation->inject($unrelated + ['traceparent' => self::traceparent()], []));
    }

    public function testLongAndNonAsciiUnrelatedNamesAreNeverNormalized(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $longName = \str_repeat('Z', 65536) . 'traceparent';
        $unrelated = [
            $longName => ['raw', 'Unchanged'],
            'tracéparent' => 'caller-owned',
            'traceparent-extra' => 'not-tracing',
            'x-request-id' => 'safe',
        ];

        self::assertSame(self::context(), $propagation->extract($unrelated + ['TRACEPARENT' => self::traceparent()]));
        self::assertSame(
            $unrelated + ['traceparent' => self::traceparent()],
            $propagation->inject($unrelated, self::context()),
        );
    }

    public function testHeaderMatchingUsesAsciiCaseIndependentOfCurrentLocale(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $originalLocale = \setlocale(LC_CTYPE, 0);
        self::assertIsString($originalLocale);

        try {
            foreach (['C', 'C.UTF-8', 'tr_TR.UTF-8'] as $locale) {
                if (\setlocale(LC_CTYPE, $locale) === false) {
                    continue;
                }

                self::assertSame(
                    self::context(),
                    $propagation->extract(['TRacePARent' => self::traceparent()]),
                );
                self::assertSame(
                    ['traceparent' => self::traceparent()],
                    $propagation->inject(['tRaCePaReNt' => self::traceparent()], self::context()),
                );
            }
        } finally {
            \setlocale(LC_CTYPE, $originalLocale);
        }
    }

    public function testEmptyOrInvalidContextStripsAllExistingTracingFields(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [
            'TRACESTATE' => 'vendor=opaque',
            'TrAcEpArEnT' => self::traceparent(),
            'x-request-id' => 'safe',
        ];
        $invalid = [
            [],
            ['trace_id' => self::TRACE_ID],
            ['trace_id' => self::TRACE_ID, 'parent_id' => self::PARENT_ID],
            self::context() + ['tracestate' => 'untrusted'],
            ['trace_id' => self::TRACE_ID, 'parent_id' => self::PARENT_ID, 'trace_flags' => 1],
            ['trace_id' => self::TRACE_ID, 'parent_id' => self::PARENT_ID, 'trace_flags' => '04'],
            ['trace_id' => self::TRACE_ID, 'parent_id' => self::PARENT_ID, 'trace_flags' => 'ff'],
            ['trace_id' => self::TRACE_ID, 'parent_id' => self::PARENT_ID, 'trace_flags' => '0A'],
            ['trace_id' => \str_repeat('0', 32), 'parent_id' => self::PARENT_ID, 'trace_flags' => '01'],
            ['trace_id' => self::TRACE_ID, 'parent_id' => \str_repeat('0', 16), 'trace_flags' => '01'],
            ['trace_id' => \strtoupper(self::TRACE_ID), 'parent_id' => self::PARENT_ID, 'trace_flags' => '01'],
            ['trace_id' => self::TRACE_ID, 'parent_id' => 'invalid', 'trace_flags' => '01'],
        ];

        foreach ($invalid as $context) {
            self::assertSame(['x-request-id' => 'safe'], $propagation->inject($carrier, $context));
        }
    }

    public function testInvalidContextValuesNeverInvokeStringableOrImplicitConversions(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $stringable = new class() {
            public function __toString(): string
            {
                throw new \LogicException('Stringable conversion is forbidden');
            }

            public function __serialize(): array
            {
                throw new \LogicException('Serialization is forbidden');
            }
        };
        $resource = \fopen('php://memory', 'r+');
        self::assertIsResource($resource);

        try {
            foreach (['trace_id', 'parent_id', 'trace_flags'] as $key) {
                foreach ([17, 3.5, false, $stringable, $resource] as $invalidValue) {
                    $context = self::context();
                    $context[$key] = $invalidValue;

                    self::assertSame([], $propagation->inject([], $context));
                }
            }
        } finally {
            \fclose($resource);
        }
    }

    public function testContextKeyInsertionOrderDoesNotAffectInjection(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $reordered = [
            'trace_flags' => '03',
            'parent_id' => self::PARENT_ID,
            'trace_id' => self::TRACE_ID,
        ];

        self::assertSame(
            $propagation->inject([], self::context('03')),
            $propagation->inject([], $reordered),
        );
        self::assertSame(['traceparent' => self::traceparent('03')], $propagation->inject([], $reordered));
    }

    public function testAbsentExistingTraceparentPermitsExplicitValidatedContext(): void
    {
        $propagation = new W3cTraceContextPropagation();

        self::assertSame(
            ['x-request-id' => 'safe', 'traceparent' => self::traceparent('02')],
            $propagation->inject(['x-request-id' => 'safe'], self::context('02')),
        );
        self::assertSame(self::context('02'), $propagation->extract(['traceparent' => self::traceparent('02')]));
        self::assertSame(
            ['x-request-id' => 'safe', 'traceparent' => self::traceparent('02')],
            $propagation->inject(
                ['tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
                self::context('02'),
            ),
        );
    }

    public function testInjectAndExtractDoNotMutateCallerOwnedCarrier(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [
            'TRACEPARENT' => self::traceparent(),
            'TRACESTATE' => ['vendor=opaque'],
            'x-request-id' => ['first', 'second'],
        ];
        $before = $carrier;

        self::assertSame(self::context(), $propagation->extract($carrier));
        self::assertSame($before, $carrier);
        self::assertSame(
            [
                'x-request-id' => ['first', 'second'],
                'traceparent' => self::traceparent('01', '00', self::OTHER_TRACE_ID),
            ],
            $propagation->inject($carrier, self::context('01', self::OTHER_TRACE_ID)),
        );
        self::assertSame($before, $carrier);
    }

    public function testByteIdenticalTraceparentPreservesTracestateForStringAndList(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $traceparent = self::traceparent('03');

        foreach ([$traceparent, [$traceparent]] as $existing) {
            self::assertSame(
                ['tracestate' => 'vendor=opaque', 'x-request-id' => ['safe'], 'traceparent' => $traceparent],
                $propagation->inject(
                    ['TrAcEpArEnT' => $existing, 'tracestate' => 'vendor=opaque', 'x-request-id' => ['safe']],
                    self::context('03'),
                ),
            );
        }
    }

    public function testDifferentTraceparentOrForwardVersionDowngradeRemovesTracestate(): void
    {
        $propagation = new W3cTraceContextPropagation();

        $replacement = $propagation->inject(
            ['traceparent' => self::traceparent(), 'tracestate' => 'vendor=opaque', 'x-request-id' => 'safe'],
            self::context('01', self::OTHER_TRACE_ID),
        );
        self::assertSame(
            ['x-request-id' => 'safe', 'traceparent' => self::traceparent('01', '00', self::OTHER_TRACE_ID)],
            $replacement,
        );

        $downgraded = $propagation->inject(
            ['traceparent' => self::traceparent('ff', '01'), 'tracestate' => 'vendor=opaque'],
            self::context('03'),
        );
        self::assertSame(['traceparent' => self::traceparent('03')], $downgraded);
        self::assertSame(self::context('03'), $propagation->extract($downgraded));
    }

    public function testSameTraceIdRejectsRandomFlagMutationEvenWithChangedParentId(): void
    {
        $propagation = new W3cTraceContextPropagation();

        foreach ([['02', '00'], ['03', '01'], ['00', '02'], ['01', '03']] as [$oldFlags, $newFlags]) {
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    [
                        'traceparent' => self::traceparent($oldFlags),
                        'tracestate' => 'vendor=opaque',
                        'x-request-id' => 'safe',
                    ],
                    self::context($newFlags, self::TRACE_ID, self::OTHER_PARENT_ID),
                ),
            );
        }
    }

    public function testSameParentIdRejectsSampledFlagMutationEvenWithChangedTraceId(): void
    {
        $propagation = new W3cTraceContextPropagation();

        foreach ([['00', '01'], ['01', '00'], ['02', '03'], ['03', '02']] as [$oldFlags, $newFlags]) {
            self::assertSame(
                ['x-request-id' => 'safe'],
                $propagation->inject(
                    [
                        'traceparent' => self::traceparent($oldFlags),
                        'tracestate' => 'vendor=opaque',
                        'x-request-id' => 'safe',
                    ],
                    self::context($newFlags, self::OTHER_TRACE_ID),
                ),
            );
        }
    }

    public function testCallerSuppliedNewParentMayChangeSampledButNotRandomFlag(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = [
            'traceparent' => self::traceparent('03'),
            'tracestate' => 'vendor=opaque',
            'x-request-id' => 'safe',
        ];

        self::assertSame(
            [
                'x-request-id' => 'safe',
                'traceparent' => self::traceparent('02', '00', self::TRACE_ID, self::OTHER_PARENT_ID),
            ],
            $propagation->inject($carrier, self::context('02', self::TRACE_ID, self::OTHER_PARENT_ID)),
        );
        self::assertSame(
            ['tracestate' => 'vendor=opaque', 'x-request-id' => 'safe', 'traceparent' => self::traceparent('03')],
            $propagation->inject($carrier, self::context('03')),
        );
    }

    public function testInvalidExistingTraceparentNeverAuthorizesNewTracingFields(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $invalid = [
            'traceparent' => self::traceparent('04'),
            'TRACESTATE' => 'vendor=opaque',
            'x-request-id' => 'safe',
        ];

        self::assertSame(['x-request-id' => 'safe'], $propagation->inject($invalid, self::context()));
        self::assertSame(['x-request-id' => 'safe'], $propagation->inject($invalid, []));
        self::assertSame(
            ['x-request-id' => 'safe'],
            $propagation->inject(
                [
                    'traceparent' => [self::traceparent(), self::traceparent()],
                    'tracestate' => 'vendor=opaque',
                    'x-request-id' => 'safe',
                ],
                self::context(),
            ),
        );
    }

    public function testCarrierLimitAcceptsExactly128EntriesForExtraction(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = self::unrelatedCarrier(127);
        $carrier['traceparent'] = self::traceparent();

        self::assertCount(128, $carrier);
        self::assertSame(self::context(), $propagation->extract($carrier));
        self::assertSame($carrier, $propagation->inject($carrier, self::context()));
    }

    public function testCarrierLimitRejects129EntriesBeforeInspectingTraceparent(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = self::unrelatedCarrier(128);
        $carrier['traceparent'] = self::traceparent();

        self::assertCount(129, $carrier);
        self::assertSame([], $propagation->extract($carrier));
        self::assertSame(self::unrelatedCarrier(128), $propagation->inject($carrier, self::context()));

        $carrier['tracestate'] = 'vendor=opaque';
        self::assertSame([], $propagation->extract($carrier));
        self::assertSame(self::unrelatedCarrier(128), $propagation->inject($carrier, self::context()));
    }

    public function testOverLimitInjectionOnlyRemovesTracingKeysWithoutInspectingValues(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $opaque = new class() {
            public function __toString(): string
            {
                throw new \LogicException('Over-limit unrelated value must not be converted');
            }

            public function __serialize(): array
            {
                throw new \LogicException('Over-limit unrelated value must not be serialized');
            }
        };
        $carrier = self::unrelatedCarrier(127);
        $carrier['x-opaque'] = $opaque;
        $carrier['TrAcEpArEnT'] = $opaque;
        $carrier['TRACESTATE'] = $opaque;

        // Values outside the carrier contract are deliberate here: the
        // over-limit removal path must inspect tracing-field keys only.
        self::assertCount(130, $carrier);

        $result = $propagation->inject($carrier, self::context());

        self::assertCount(128, $result);
        self::assertSame($opaque, $result['x-opaque']);
        self::assertArrayNotHasKey('TrAcEpArEnT', $result);
        self::assertArrayNotHasKey('TRACESTATE', $result);
        self::assertArrayNotHasKey('traceparent', $result);
    }

    public function testExtractionAndInjectionProduceNoLogsOutputOrWarnings(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $warnings = [];
        $previousLevel = \error_reporting();
        \error_reporting(E_ALL);
        \set_error_handler(static function (int $severity) use (&$warnings): bool {
            $warnings[] = $severity;

            return true;
        });
        \ob_start();

        try {
            self::assertSame([], $propagation->extract(['traceparent' => 'invalid']));
            self::assertSame(self::context(), $propagation->extract(['traceparent' => self::traceparent()]));
            self::assertSame(
                ['traceparent' => self::traceparent()],
                $propagation->inject([], self::context()),
            );
            self::assertSame([], $propagation->inject(['traceparent' => self::traceparent('04')], self::context()));
        } finally {
            $output = \ob_get_clean();
            \restore_error_handler();
            \error_reporting($previousLevel);
        }

        self::assertSame('', $output);
        self::assertSame([], $warnings);
    }

    public function testRepeatedCallsRemainDeterministicAndStateFree(): void
    {
        $propagation = new W3cTraceContextPropagation();
        $carrier = ['traceparent' => self::traceparent('02'), 'x-request-id' => 'safe'];
        $context = self::context('02');
        $extracted = $propagation->extract($carrier);
        $injected = $propagation->inject($carrier, $context);

        for ($i = 0; $i < 10; $i++) {
            self::assertSame($extracted, $propagation->extract($carrier));
            self::assertSame($injected, $propagation->inject($carrier, $context));
            self::assertSame([], $propagation->extract([]));
        }

        self::assertSame(self::context('02'), $extracted);
        self::assertSame(['x-request-id' => 'safe', 'traceparent' => self::traceparent('02')], $injected);
    }

    /**
     * @return array{trace_id:string,parent_id:string,trace_flags:string}
     */
    private static function context(
        string $flags = '01',
        string $traceId = self::TRACE_ID,
        string $parentId = self::PARENT_ID,
    ): array {
        return [
            'trace_id' => $traceId,
            'parent_id' => $parentId,
            'trace_flags' => $flags,
        ];
    }

    private static function traceparent(
        string $flags = '01',
        string $version = '00',
        string $traceId = self::TRACE_ID,
        string $parentId = self::PARENT_ID,
    ): string {
        return $version . '-' . $traceId . '-' . $parentId . '-' . $flags;
    }

    /**
     * @return array<string,string>
     */
    private static function unrelatedCarrier(int $count): array
    {
        $carrier = [];

        for ($i = 0; $i < $count; $i++) {
            $carrier['x-' . $i] = 'safe';
        }

        return $carrier;
    }
}
