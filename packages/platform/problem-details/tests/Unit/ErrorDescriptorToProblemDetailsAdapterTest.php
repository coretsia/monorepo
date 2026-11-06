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

namespace Coretsia\Platform\ProblemDetails\Tests\Unit;

use Coretsia\Contracts\Observability\Errors\ErrorDescriptor;
use Coretsia\Contracts\Observability\Errors\ErrorSeverity;
use Coretsia\Platform\ProblemDetails\Adapter\ErrorDescriptorToProblemDetailsAdapter;
use Coretsia\Platform\ProblemDetails\ProblemDetails;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ErrorDescriptorToProblemDetailsAdapterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function standardStatuses(): iterable
    {
        yield 'bad request' => [400, 'Bad Request'];
        yield 'unauthorized' => [401, 'Unauthorized'];
        yield 'payment required' => [402, 'Payment Required'];
        yield 'forbidden' => [403, 'Forbidden'];
        yield 'not found' => [404, 'Not Found'];
        yield 'method not allowed' => [405, 'Method Not Allowed'];
        yield 'not acceptable' => [406, 'Not Acceptable'];
        yield 'proxy authentication required' => [407, 'Proxy Authentication Required'];
        yield 'request timeout' => [408, 'Request Timeout'];
        yield 'conflict' => [409, 'Conflict'];
        yield 'gone' => [410, 'Gone'];
        yield 'length required' => [411, 'Length Required'];
        yield 'precondition failed' => [412, 'Precondition Failed'];
        yield 'content too large' => [413, 'Content Too Large'];
        yield 'uri too long' => [414, 'URI Too Long'];
        yield 'unsupported media type' => [415, 'Unsupported Media Type'];
        yield 'range not satisfiable' => [416, 'Range Not Satisfiable'];
        yield 'expectation failed' => [417, 'Expectation Failed'];
        yield 'misdirected request' => [421, 'Misdirected Request'];
        yield 'unprocessable content' => [422, 'Unprocessable Content'];
        yield 'locked' => [423, 'Locked'];
        yield 'failed dependency' => [424, 'Failed Dependency'];
        yield 'too early' => [425, 'Too Early'];
        yield 'upgrade required' => [426, 'Upgrade Required'];
        yield 'precondition required' => [428, 'Precondition Required'];
        yield 'too many requests' => [429, 'Too Many Requests'];
        yield 'request header fields too large' => [431, 'Request Header Fields Too Large'];
        yield 'unavailable for legal reasons' => [451, 'Unavailable For Legal Reasons'];
        yield 'internal server error' => [500, 'Internal Server Error'];
        yield 'not implemented' => [501, 'Not Implemented'];
        yield 'bad gateway' => [502, 'Bad Gateway'];
        yield 'service unavailable' => [503, 'Service Unavailable'];
        yield 'gateway timeout' => [504, 'Gateway Timeout'];
        yield 'http version not supported' => [505, 'HTTP Version Not Supported'];
        yield 'variant also negotiates' => [506, 'Variant Also Negotiates'];
        yield 'insufficient storage' => [507, 'Insufficient Storage'];
        yield 'loop detected' => [508, 'Loop Detected'];
        yield 'not extended' => [510, 'Not Extended'];
        yield 'network authentication required' => [511, 'Network Authentication Required'];
    }

    #[DataProvider('standardStatuses')]
    public function testStandardHttpErrorStatusesUseFixedCanonicalTitles(int $status, string $title): void
    {
        $descriptor = new ErrorDescriptor(
            code: 'SYNTHETIC_OWNER_ERROR',
            message: 'SYNTHETIC_PRIVATE_DESCRIPTION',
            severity: ErrorSeverity::Critical,
            httpStatus: $status,
        );
        $problem = new ErrorDescriptorToProblemDetailsAdapter()->map($descriptor);

        self::assertSame($status, $problem->status());
        self::assertSame($title, $problem->title());
        self::assertSame('about:blank', $problem->type());
        self::assertSame(['status' => $status, 'title' => $title, 'type' => 'about:blank'], $problem->toArray());
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function nonStandardStatuses(): iterable
    {
        yield 'teapot' => [418];
        yield 'nonstandard 4xx' => [430];
        yield 'nonstandard 5xx' => [512];
        yield 'upper bound' => [599];
    }

    #[DataProvider('nonStandardStatuses')]
    public function testNonStandardErrorStatusUsesFixedHttpErrorTitle(int $status): void
    {
        $problem = new ErrorDescriptorToProblemDetailsAdapter()->map(
            new ErrorDescriptor('SYNTHETIC_OWNER_ERROR', 'Private message.', httpStatus: $status),
        );

        self::assertSame(['status' => $status, 'title' => 'HTTP Error', 'type' => 'about:blank'], $problem->toArray());
    }

    /**
     * @return iterable<string, array{?int}>
     */
    public static function nonErrorHints(): iterable
    {
        yield 'absent' => [null];
        yield 'informational' => [100];
        yield 'switching protocols' => [101];
        yield 'successful' => [200];
        yield 'no content' => [204];
        yield 'redirect' => [302];
        yield 'not modified' => [304];
        yield 'highest redirect' => [399];
    }

    #[DataProvider('nonErrorHints')]
    public function testAbsentAndNonErrorHttpHintsFailClosedTo500(?int $status): void
    {
        $descriptor = new ErrorDescriptor('SYNTHETIC_OWNER_ERROR', 'Private message.', httpStatus: $status);
        $problem = new ErrorDescriptorToProblemDetailsAdapter()->map($descriptor);

        self::assertSame(
            ['status' => 500, 'title' => 'Internal Server Error', 'type' => 'about:blank'],
            $problem->toArray(),
        );
    }

    public function testOnlyHttpStatusCanInfluencePublicOutput(): void
    {
        $adapter = new ErrorDescriptorToProblemDetailsAdapter();
        $first = new ErrorDescriptor(
            code: 'SYNTHETIC_FIRST_CODE',
            message: 'SYNTHETIC_PRIVATE_MESSAGE_FIRST',
            severity: ErrorSeverity::Info,
            httpStatus: 403,
            extensions: ['safe_note' => 'SYNTHETIC_PRIVATE_EXTENSION_FIRST'],
        );
        $second = new ErrorDescriptor(
            code: 'SYNTHETIC_SECOND_CODE',
            message: 'SYNTHETIC_PRIVATE_MESSAGE_SECOND',
            severity: ErrorSeverity::Critical,
            httpStatus: 403,
            extensions: ['other_note' => ['note' => 'SYNTHETIC_PRIVATE_EXTENSION_SECOND']],
        );

        for ($attempt = 0; $attempt < 16; ++$attempt) {
            $result = $adapter->map($attempt % 2 === 0 ? $first : $second)->toArray();
            self::assertSame(['status' => 403, 'title' => 'Forbidden', 'type' => 'about:blank'], $result);
            self::assertCount(3, $result);
            $encoded = \json_encode($result, \JSON_THROW_ON_ERROR);
            self::assertStringNotContainsString('SYNTHETIC_PRIVATE_', $encoded);
            self::assertStringNotContainsString('SYNTHETIC_FIRST_CODE', $encoded);
            self::assertStringNotContainsString('SYNTHETIC_SECOND_CODE', $encoded);
            self::assertArrayNotHasKey('detail', $result);
            self::assertArrayNotHasKey('instance', $result);
            self::assertArrayNotHasKey('extensions', $result);
        }
    }

    public function testAdapterAndPublicModelDoNotCreateASecondErrorMapper(): void
    {
        $adapter = new \ReflectionClass(ErrorDescriptorToProblemDetailsAdapter::class);
        $model = new \ReflectionClass(ProblemDetails::class);
        $constructor = $model->getConstructor();

        self::assertTrue($adapter->isFinal());
        self::assertNull($adapter->getConstructor());
        self::assertSame([], $adapter->getProperties());
        self::assertTrue($model->isFinal());
        self::assertTrue($model->isReadOnly());
        self::assertNotNull($constructor);
        self::assertCount(1, $constructor->getParameters());
        self::assertSame('status', $constructor->getParameters()[0]->getName());
        self::assertSame(
            ['type', 'title', 'status'],
            \array_map(
                static fn (\ReflectionProperty $property): string => $property->getName(),
                $model->getProperties(),
            )
        );
    }
}
