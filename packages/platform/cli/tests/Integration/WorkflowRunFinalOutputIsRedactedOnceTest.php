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

namespace Coretsia\Platform\Cli\Tests\Integration;

use Coretsia\Contracts\Security\RedactedValue;
use Coretsia\Contracts\Security\RedactionContext;
use Coretsia\Contracts\Security\RedactionKind;
use Coretsia\Contracts\Security\SensitiveDataRedactorInterface;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowExecutionProbeCommand;
use Coretsia\Platform\Cli\Tests\Integration\Support\WorkflowIntegrationFixture;
use Coretsia\Platform\Redaction\Redaction\DefaultSensitiveDataRedactor;
use Coretsia\Platform\Redaction\Redaction\SensitiveKeyClassifier;
use Coretsia\Platform\Redaction\Redaction\SensitiveValueClassifier;
use Coretsia\Platform\Redaction\Redaction\StableRedactionHasher;
use PHPUnit\Framework\TestCase;

final class WorkflowRunFinalOutputIsRedactedOnceTest extends TestCase
{
    public function testTwoChildBatchesAreRedactedOnlyOnceAtTheSharedFinalBoundary(): void
    {
        WorkflowExecutionProbeCommand::reset();
        $delegate = new DefaultSensitiveDataRedactor(
            new SensitiveKeyClassifier(),
            new SensitiveValueClassifier(),
            new StableRedactionHasher(),
        );
        $spy = new class($delegate) implements SensitiveDataRedactorInterface {
            public int $calls = 0;
            /** @var list<mixed> */
            public array $received = [];

            public function __construct(private readonly SensitiveDataRedactorInterface $delegate)
            {
            }

            public function redactValue(string $value, RedactionKind $kind, RedactionContext $context): RedactedValue
            {
                return $this->delegate->redactValue($value, $kind, $context);
            }

            public function redactJsonLike(mixed $value, RedactionContext $context): mixed
            {
                ++$this->calls;
                $this->received[] = $value;
                return $this->delegate->redactJsonLike($value, $context);
            }
        };

        $fixture = new WorkflowIntegrationFixture([
            'verify' => [
                'summary' => 'Redact completed child output once.',
                'steps' => [
                    ['command' => WorkflowExecutionProbeCommand::NAME, 'arguments' => ['one'], 'options' => []],
                    ['command' => WorkflowExecutionProbeCommand::NAME, 'arguments' => ['two'], 'options' => []],
                ],
            ],
        ], redactor: $spy, withProbe: true);

        $canonical = $fixture->workflowRunner->run($fixture->workflows->require('verify'), 'json');
        self::assertSame(0, $spy->calls, 'Workflow and child buffers must not invoke shared redaction.');
        foreach ($canonical->steps() as $index => $step) {
            $subject = $index === 0 ? 'one' : 'two';
            self::assertSame(['text', 'json', 'error'], \array_column($step->records(), 'type'));
            self::assertSame(['payload', 'type'], \array_keys($step->records()[1]));
            self::assertSame('PRIVATE_PASSWORD_' . $subject, $step->records()[1]['payload']['nested']['password']);
            self::assertSame('visible-' . $subject, $step->records()[1]['payload']['nested']['safe']);
            self::assertArrayNotHasKey('payload', $canonical->toArray()['steps'][$index]['records'][1]);
            self::assertSame(
                'PRIVATE_PASSWORD_' . $subject,
                $canonical->toArray()['steps'][$index]['records'][1]['data']['nested']['password'],
            );
        }

        WorkflowExecutionProbeCommand::reset();
        $result = $fixture->run(['workflow:run', 'verify', '--format=json']);
        self::assertSame(0, $result['exit'], $result['stderr']);
        self::assertSame(1, $spy->calls);
        self::assertCount(1, $spy->received);
        self::assertSame(
            'PRIVATE_PASSWORD_one',
            $spy->received[0]['steps'][0]['records'][1]['data']['nested']['password'],
        );
        self::assertSame(
            'PRIVATE_PASSWORD_two',
            $spy->received[0]['steps'][1]['records'][1]['data']['nested']['password'],
        );
        self::assertArrayNotHasKey('payload', $spy->received[0]['steps'][0]['records'][1]);

        $document = WorkflowIntegrationFixture::document($result);
        self::assertSame('coretsia.cli-output@1', $document['schema']);
        self::assertCount(1, $document['data']['records']);
        $payload = $document['data']['records'][0]['payload'];
        self::assertSame(['text', 'json', 'error'], \array_column($payload['steps'][0]['records'], 'type'));
        self::assertSame(['text', 'json', 'error'], \array_column($payload['steps'][1]['records'], 'type'));
        foreach (['one', 'two'] as $index => $subject) {
            $child = $payload['steps'][$index]['records'][1];
            self::assertSame('visible-' . $subject, $child['data']['nested']['safe']);
            self::assertSame($subject, $child['data']['label']);
            self::assertTrue($child['data']['nested']['password']['redacted']);
            self::assertArrayNotHasKey('payload', $child);
            self::assertStringNotContainsString('PRIVATE_PASSWORD_' . $subject, $result['stdout']);
        }
    }
}
