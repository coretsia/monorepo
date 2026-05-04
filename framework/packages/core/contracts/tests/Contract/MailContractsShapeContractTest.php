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

use Coretsia\Contracts\Mail\MailerInterface;
use Coretsia\Contracts\Mail\MailException;
use Coretsia\Contracts\Mail\MailMessage;
use Coretsia\Contracts\Mail\MailTransportInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

final class MailContractsShapeContractTest extends TestCase
{
    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_PUBLIC_METHODS = [
        'allRecipients',
        'bodyRaw',
        'client',
        'config',
        'credentials',
        'debug',
        'dsn',
        'lastResponse',
        'password',
        'raw',
        'recipients',
        'token',
        'transport',
    ];

    /**
     * @var list<non-empty-string>
     */
    private const array FORBIDDEN_SOURCE_TOKENS = [
        'Coretsia\\Platform\\',
        'Coretsia\\Integrations\\',
        'Psr\\Http\\Message\\',
        'Symfony\\Component\\Mailer\\',
        'Symfony\\Component\\Mime\\',
        'PHPMailer',
        'Swift_',
        'GuzzleHttp\\',
        'PDO',
        'Redis',
    ];

    public function testMailerInterfaceShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(MailerInterface::class);

        self::assertTrue($reflection->isInterface());

        self::assertSame(
            ['send'],
            self::publicMethodNames($reflection)
        );

        self::assertSame([], self::publicConstantNames($reflection));

        self::assertParameterShape(MailerInterface::class, 'send', 0, 'message', MailMessage::class);
        self::assertReturnType(MailerInterface::class, 'send', 'void');
        self::assertMethodDocContains(MailerInterface::class, 'send', '@throws MailException');

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
        self::assertPublicMethodsAreAbsent($reflection, ['later', 'name', 'queue', 'sendAsync']);
    }

    public function testMailTransportInterfaceShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(MailTransportInterface::class);

        self::assertTrue($reflection->isInterface());

        self::assertSame(
            ['name', 'send'],
            self::publicMethodNames($reflection)
        );

        self::assertSame([], self::publicConstantNames($reflection));

        self::assertMethodHasNoParameters(MailTransportInterface::class, 'name');
        self::assertReturnType(MailTransportInterface::class, 'name', 'string');
        self::assertMethodDocContains(
            MailTransportInterface::class,
            'name',
            '@return non-empty-string'
        );

        self::assertParameterShape(MailTransportInterface::class, 'send', 0, 'message', MailMessage::class);
        self::assertReturnType(MailTransportInterface::class, 'send', 'void');
        self::assertMethodDocContains(MailTransportInterface::class, 'send', '@throws MailException');

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
        self::assertPublicMethodsAreAbsent($reflection, ['deliver', 'later', 'queue', 'sendAsync']);
    }

    public function testMailMessageClassShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(MailMessage::class);

        self::assertFalse($reflection->isInterface());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isReadOnly());

        self::assertSame(
            [
                '__construct',
                'bcc',
                'body',
                'cc',
                'headers',
                'metadata',
                'replyTo',
                'schemaVersion',
                'subject',
                'to',
                'toArray'
            ],
            self::declaredPublicMethodNames($reflection)
        );

        self::assertSame([], self::publicConstantNames($reflection));

        $schemaVersion = $reflection->getReflectionConstant('SCHEMA_VERSION');

        self::assertInstanceOf(ReflectionClassConstant::class, $schemaVersion);
        self::assertTrue($schemaVersion->isPrivate());
        self::assertSame(1, $schemaVersion->getValue());

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
        self::assertHasNoDtoMarkerAttribute($reflection);
    }

    public function testMailMessageConstructorShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(MailMessage::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(8, $parameters);
        self::assertSame(3, $constructor->getNumberOfRequiredParameters());

        self::assertParameterNamedType($parameters[0], 'array', false);
        self::assertSame('to', $parameters[0]->getName());
        self::assertFalse($parameters[0]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[1], 'string', false);
        self::assertSame('subject', $parameters[1]->getName());
        self::assertFalse($parameters[1]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[2], 'string', false);
        self::assertSame('body', $parameters[2]->getName());
        self::assertFalse($parameters[2]->isDefaultValueAvailable());

        self::assertParameterNamedType($parameters[3], 'array', false);
        self::assertSame('cc', $parameters[3]->getName());
        self::assertTrue($parameters[3]->isDefaultValueAvailable());
        self::assertSame([], $parameters[3]->getDefaultValue());

        self::assertParameterNamedType($parameters[4], 'array', false);
        self::assertSame('bcc', $parameters[4]->getName());
        self::assertTrue($parameters[4]->isDefaultValueAvailable());
        self::assertSame([], $parameters[4]->getDefaultValue());

        self::assertParameterNamedType($parameters[5], 'array', false);
        self::assertSame('replyTo', $parameters[5]->getName());
        self::assertTrue($parameters[5]->isDefaultValueAvailable());
        self::assertSame([], $parameters[5]->getDefaultValue());

        self::assertParameterNamedType($parameters[6], 'array', false);
        self::assertSame('headers', $parameters[6]->getName());
        self::assertTrue($parameters[6]->isDefaultValueAvailable());
        self::assertSame([], $parameters[6]->getDefaultValue());

        self::assertParameterNamedType($parameters[7], 'array', false);
        self::assertSame('metadata', $parameters[7]->getName());
        self::assertTrue($parameters[7]->isDefaultValueAvailable());
        self::assertSame([], $parameters[7]->getDefaultValue());

        self::assertMethodDocContains(
            MailMessage::class,
            '__construct',
            '@param non-empty-list<non-empty-string> $to',
            '@param non-empty-string $subject',
            '@param non-empty-string $body',
            '@param list<non-empty-string> $cc',
            '@param list<non-empty-string> $bcc',
            '@param list<non-empty-string> $replyTo',
            '@param array<string,mixed> $headers',
            '@param array<string,mixed> $metadata'
        );
    }

    public function testMailMessageAccessorShapeIsLocked(): void
    {
        self::assertMethodHasNoParameters(MailMessage::class, 'schemaVersion');
        self::assertReturnType(MailMessage::class, 'schemaVersion', 'int');

        self::assertMethodHasNoParameters(MailMessage::class, 'to');
        self::assertReturnType(MailMessage::class, 'to', 'array');
        self::assertMethodDocContains(MailMessage::class, 'to', '@return non-empty-list<non-empty-string>');

        self::assertMethodHasNoParameters(MailMessage::class, 'cc');
        self::assertReturnType(MailMessage::class, 'cc', 'array');
        self::assertMethodDocContains(MailMessage::class, 'cc', '@return list<non-empty-string>');

        self::assertMethodHasNoParameters(MailMessage::class, 'bcc');
        self::assertReturnType(MailMessage::class, 'bcc', 'array');
        self::assertMethodDocContains(MailMessage::class, 'bcc', '@return list<non-empty-string>');

        self::assertMethodHasNoParameters(MailMessage::class, 'replyTo');
        self::assertReturnType(MailMessage::class, 'replyTo', 'array');
        self::assertMethodDocContains(MailMessage::class, 'replyTo', '@return list<non-empty-string>');

        self::assertMethodHasNoParameters(MailMessage::class, 'subject');
        self::assertReturnType(MailMessage::class, 'subject', 'string');
        self::assertMethodDocContains(MailMessage::class, 'subject', '@return non-empty-string');

        self::assertMethodHasNoParameters(MailMessage::class, 'body');
        self::assertReturnType(MailMessage::class, 'body', 'string');
        self::assertMethodDocContains(MailMessage::class, 'body', '@return non-empty-string');

        self::assertMethodHasNoParameters(MailMessage::class, 'headers');
        self::assertReturnType(MailMessage::class, 'headers', 'array');
        self::assertMethodDocContains(MailMessage::class, 'headers', '@return array<string,mixed>');

        self::assertMethodHasNoParameters(MailMessage::class, 'metadata');
        self::assertReturnType(MailMessage::class, 'metadata', 'array');
        self::assertMethodDocContains(MailMessage::class, 'metadata', '@return array<string,mixed>');

        self::assertMethodHasNoParameters(MailMessage::class, 'toArray');
        self::assertReturnType(MailMessage::class, 'toArray', 'array');
        self::assertMethodDocContains(
            MailMessage::class,
            'toArray',
            'bccCount:int<0,max>',
            'bodyLength:int<1,max>',
            'ccCount:int<0,max>',
            'headers:array<string,mixed>',
            'metadata:array<string,mixed>',
            'replyToCount:int<0,max>',
            'schemaVersion:int',
            'subjectLength:int<1,max>',
            'toCount:int<1,max>'
        );
    }

    public function testMailMessagePreservesRawDeliveryAccessorsExactly(): void
    {
        $body = "Hello\tline\r\nSecond line";

        $message = new MailMessage(
            to: ['USER@example.test'],
            subject: 'Sensitive Subject',
            body: $body,
            cc: ['cc@example.test'],
            bcc: ['bcc@example.test'],
            replyTo: ['reply-to@example.test'],
            headers: [
                'x-safe-header' => 'safe-header-value',
            ],
            metadata: [
                'safeKey' => 'safe-value',
            ],
        );

        self::assertSame(1, $message->schemaVersion());
        self::assertSame(['USER@example.test'], $message->to());
        self::assertSame(['cc@example.test'], $message->cc());
        self::assertSame(['bcc@example.test'], $message->bcc());
        self::assertSame(['reply-to@example.test'], $message->replyTo());
        self::assertSame('Sensitive Subject', $message->subject());
        self::assertSame($body, $message->body());
        self::assertSame(['x-safe-header' => 'safe-header-value'], $message->headers());
        self::assertSame(['safeKey' => 'safe-value'], $message->metadata());
    }

    public function testMailMessageExportedSafeShapeIsLocked(): void
    {
        $message = new MailMessage(
            to: ['to@example.test'],
            subject: 'Sensitive Subject',
            body: "Sensitive body\nline",
            cc: ['cc@example.test'],
            bcc: ['bcc@example.test'],
            replyTo: ['reply-to@example.test'],
            headers: [
                'z' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'a' => 'header',
            ],
            metadata: [
                'z' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'a' => 'meta',
            ],
        );

        self::assertSame(
            [
                'bccCount' => 1,
                'bodyLength' => \strlen("Sensitive body\nline"),
                'ccCount' => 1,
                'headers' => [
                    'a' => 'header',
                    'z' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
                'metadata' => [
                    'a' => 'meta',
                    'z' => [
                        'a' => 1,
                        'b' => 2,
                    ],
                ],
                'replyToCount' => 1,
                'schemaVersion' => 1,
                'subjectLength' => \strlen('Sensitive Subject'),
                'toCount' => 1,
            ],
            $message->toArray(),
        );

        self::assertSame(
            [
                'bccCount',
                'bodyLength',
                'ccCount',
                'headers',
                'metadata',
                'replyToCount',
                'schemaVersion',
                'subjectLength',
                'toCount',
            ],
            \array_keys($message->toArray()),
        );

        $encoded = self::encodeForLeakAssertion($message->toArray());

        self::assertStringNotContainsString('to@example.test', $encoded);
        self::assertStringNotContainsString('cc@example.test', $encoded);
        self::assertStringNotContainsString('bcc@example.test', $encoded);
        self::assertStringNotContainsString('reply-to@example.test', $encoded);
        self::assertStringNotContainsString('Sensitive Subject', $encoded);
        self::assertStringNotContainsString("Sensitive body\nline", $encoded);
    }

    public function testMailMessageRequiresNonEmptyToRecipientList(): void
    {
        self::assertInvalidArgument(
            static fn (): MailMessage => new MailMessage(
                to: [],
                subject: 'Subject',
                body: 'Body',
            )
        );
    }

    public function testMailMessageRejectsInvalidRecipientStrings(): void
    {
        $invalidToCases = [
            'non-list' => [
                'valid@example.test' => 'invalid@example.test',
            ],
            'empty-string' => [''],
            'leading-whitespace' => [' user@example.test'],
            'trailing-whitespace' => ['user@example.test '],
            'inner-tab' => ["user\t@example.test"],
            'cr' => ["user\r@example.test"],
            'lf' => ["user\n@example.test"],
            'nul' => ["user\0@example.test"],
            'delete' => ["user\x7F@example.test"],
        ];

        foreach ($invalidToCases as $label => $to) {
            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: $to,
                    subject: 'Subject',
                    body: 'Body',
                ),
                $label
            );
        }

        self::assertInvalidArgument(
            static fn (): MailMessage => new MailMessage(
                to: ['to@example.test'],
                subject: 'Subject',
                body: 'Body',
                cc: [' cc@example.test'],
            ),
            'invalid cc'
        );

        self::assertInvalidArgument(
            static fn (): MailMessage => new MailMessage(
                to: ['to@example.test'],
                subject: 'Subject',
                body: 'Body',
                bcc: ['bcc@example.test '],
            ),
            'invalid bcc'
        );

        self::assertInvalidArgument(
            static fn (): MailMessage => new MailMessage(
                to: ['to@example.test'],
                subject: 'Subject',
                body: 'Body',
                replyTo: ["reply\n@example.test"],
            ),
            'invalid replyTo'
        );
    }

    public function testMailMessageRejectsInvalidSubjectStringsWithoutTrimming(): void
    {
        $invalidSubjects = [
            'empty' => '',
            'leading-whitespace' => ' Subject',
            'trailing-whitespace' => 'Subject ',
            'tab' => "Sub\tject",
            'cr' => "Sub\rject",
            'lf' => "Sub\nject",
            'nul' => "Sub\0ject",
            'delete' => "Sub\x7Fject",
        ];

        foreach ($invalidSubjects as $label => $subject) {
            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: ['to@example.test'],
                    subject: $subject,
                    body: 'Body',
                ),
                $label
            );
        }
    }

    public function testMailMessageBodyAllowsOrdinaryWhitespaceButRejectsUnsafeControls(): void
    {
        $message = new MailMessage(
            to: ['to@example.test'],
            subject: 'Subject',
            body: "Body\twith\r\nordinary whitespace",
        );

        self::assertSame("Body\twith\r\nordinary whitespace", $message->body());

        $invalidBodies = [
            'empty' => '',
            'nul' => "Body\0",
            'bell' => "Body\x07",
            'vertical-tab' => "Body\x0B",
            'form-feed' => "Body\x0C",
            'unit-separator' => "Body\x1F",
            'delete' => "Body\x7F",
        ];

        foreach ($invalidBodies as $label => $body) {
            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: ['to@example.test'],
                    subject: 'Subject',
                    body: $body,
                ),
                $label
            );
        }
    }

    public function testMailMessageHeadersAndMetadataAreJsonLikeAndDeterministic(): void
    {
        $message = new MailMessage(
            to: ['to@example.test'],
            subject: 'Subject',
            body: 'Body',
            headers: [
                'z' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'a' => [
                    [
                        'z' => 2,
                        'a' => 1,
                    ],
                    'literal',
                ],
            ],
            metadata: [
                'z' => [
                    'b' => 2,
                    'a' => 1,
                ],
                'a' => [
                    [
                        'z' => 2,
                        'a' => 1,
                    ],
                    'literal',
                ],
            ],
        );

        $expected = [
            'a' => [
                [
                    'a' => 1,
                    'z' => 2,
                ],
                'literal',
            ],
            'z' => [
                'a' => 1,
                'b' => 2,
            ],
        ];

        self::assertSame($expected, $message->headers());
        self::assertSame($expected, $message->metadata());
    }

    public function testMailMessageRejectsInvalidHeadersAndMetadata(): void
    {
        $invalidMaps = [
            'root-non-empty-list' => ['root-list-value'],
            'empty-key' => [
                '' => 'value',
            ],
            'leading-whitespace-key' => [
                ' key' => 'value',
            ],
            'trailing-whitespace-key' => [
                'key ' => 'value',
            ],
            'control-key' => [
                "bad\nkey" => 'value',
            ],
            'float' => [
                'value' => 1.5,
            ],
            'nan' => [
                'value' => \NAN,
            ],
            'inf' => [
                'value' => \INF,
            ],
            'object' => [
                'value' => new \stdClass(),
            ],
            'closure' => [
                'value' => static fn (): string => 'invalid',
            ],
            'unsafe-string' => [
                'value' => "bad\0value",
            ],
            'nested-float' => [
                'nested' => [
                    'value' => 1.5,
                ],
            ],
        ];

        foreach ($invalidMaps as $label => $map) {
            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: ['to@example.test'],
                    subject: 'Subject',
                    body: 'Body',
                    headers: $map,
                ),
                'headers ' . $label
            );

            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: ['to@example.test'],
                    subject: 'Subject',
                    body: 'Body',
                    metadata: $map,
                ),
                'metadata ' . $label
            );
        }

        $resource = \fopen('php://memory', 'rb');

        self::assertIsResource($resource);

        try {
            self::assertInvalidArgument(
                static fn (): MailMessage => new MailMessage(
                    to: ['to@example.test'],
                    subject: 'Subject',
                    body: 'Body',
                    metadata: [
                        'value' => $resource,
                    ],
                ),
                'metadata resource'
            );
        } finally {
            \fclose($resource);
        }
    }

    public function testMailExceptionShapeIsLocked(): void
    {
        $reflection = new ReflectionClass(MailException::class);

        self::assertFalse($reflection->isInterface());
        self::assertTrue($reflection->isFinal());
        self::assertTrue($reflection->isSubclassOf(\RuntimeException::class));

        self::assertSame(
            ['__construct', 'errorCode'],
            self::declaredPublicMethodNames($reflection)
        );

        self::assertSame(['CODE', 'MESSAGE'], self::publicConstantNames($reflection));
        self::assertSame('CORETSIA_MAIL_DELIVERY_FAILED', $reflection->getConstant('CODE'));
        self::assertSame('Mail delivery failed.', $reflection->getConstant('MESSAGE'));

        self::assertSame([], self::declaredPropertyNames($reflection));

        self::assertReturnType(MailException::class, 'errorCode', 'string');
        self::assertMethodHasNoParameters(MailException::class, 'errorCode');
        self::assertMethodDocContains(MailException::class, 'errorCode', '@return non-empty-string');

        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);

        $parameters = $constructor->getParameters();

        self::assertCount(1, $parameters);
        self::assertSame(0, $constructor->getNumberOfRequiredParameters());
        self::assertSame('previous', $parameters[0]->getName());
        self::assertParameterNamedType($parameters[0], 'Throwable', true);
        self::assertTrue($parameters[0]->isDefaultValueAvailable());
        self::assertNull($parameters[0]->getDefaultValue());

        $previous = new \RuntimeException('Redacted previous failure.');
        $exception = new MailException($previous);

        self::assertSame('Mail delivery failed.', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame('CORETSIA_MAIL_DELIVERY_FAILED', $exception->errorCode());
        self::assertSame($previous, $exception->getPrevious());

        self::assertForbiddenPublicMethodsAreAbsent($reflection);
    }

    public function testMailContractsDoNotContainForbiddenSourceTokens(): void
    {
        foreach (
            [
                MailerInterface::class,
                MailTransportInterface::class,
                MailMessage::class,
                MailException::class,
            ] as $className
        ) {
            $reflection = new ReflectionClass($className);
            $fileName = $reflection->getFileName();

            self::assertIsString($fileName);

            $source = \file_get_contents($fileName);

            self::assertIsString($source);

            foreach (self::FORBIDDEN_SOURCE_TOKENS as $token) {
                self::assertStringNotContainsString(
                    $token,
                    $source,
                    \sprintf('Forbidden source token "%s" found in %s.', $token, $fileName)
                );
            }
        }
    }

    private static function assertMethodHasNoParameters(string $className, string $methodName): void
    {
        $method = new ReflectionMethod($className, $methodName);

        self::assertSame([], $method->getParameters());
    }

    private static function assertReturnType(string $className, string $methodName, string $expectedName): void
    {
        $method = new ReflectionMethod($className, $methodName);
        $type = $method->getReturnType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
    }

    private static function assertParameterShape(
        string $className,
        string $methodName,
        int $index,
        string $expectedName,
        string $expectedType,
        bool $hasDefaultValue = false,
        mixed $expectedDefaultValue = null,
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $parameters = $method->getParameters();

        self::assertArrayHasKey($index, $parameters);

        $parameter = $parameters[$index];

        self::assertSame($expectedName, $parameter->getName());
        self::assertParameterNamedType($parameter, $expectedType, false);

        if ($hasDefaultValue) {
            self::assertTrue($parameter->isDefaultValueAvailable());
            self::assertSame($expectedDefaultValue, $parameter->getDefaultValue());
        } else {
            self::assertFalse($parameter->isDefaultValueAvailable());
        }
    }

    private static function assertParameterNamedType(
        ReflectionParameter $parameter,
        string $expectedName,
        bool $expectedAllowsNull,
    ): void {
        $type = $parameter->getType();

        self::assertInstanceOf(ReflectionNamedType::class, $type);
        self::assertSame($expectedName, $type->getName());
        self::assertSame($expectedAllowsNull, $type->allowsNull());
    }

    private static function assertMethodDocContains(
        string $className,
        string $methodName,
        string ...$expectedParts
    ): void {
        $method = new ReflectionMethod($className, $methodName);
        $docComment = $method->getDocComment();

        self::assertIsString($docComment);

        foreach ($expectedParts as $expectedPart) {
            self::assertStringContainsString($expectedPart, $docComment);
        }
    }

    /**
     * @return list<string>
     */
    private static function publicMethodNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $names[] = $method->getName();
        }

        \sort($names, \SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function declaredPublicMethodNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $method->getName();
        }

        \sort($names, \SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function publicConstantNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getReflectionConstants(ReflectionClassConstant::IS_PUBLIC) as $constant) {
            $names[] = $constant->getName();
        }

        \sort($names, \SORT_STRING);

        return $names;
    }

    /**
     * @return list<string>
     */
    private static function declaredPropertyNames(ReflectionClass $reflection): array
    {
        $names = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $names[] = $property->getName();
        }

        \sort($names, \SORT_STRING);

        return $names;
    }

    private static function assertForbiddenPublicMethodsAreAbsent(ReflectionClass $reflection): void
    {
        self::assertPublicMethodsAreAbsent($reflection, self::FORBIDDEN_PUBLIC_METHODS);
    }

    /**
     * @param list<non-empty-string> $forbiddenNames
     */
    private static function assertPublicMethodsAreAbsent(ReflectionClass $reflection, array $forbiddenNames): void
    {
        $publicMethodNames = \array_map(
            static fn (string $name): string => \strtolower($name),
            self::publicMethodNames($reflection)
        );

        foreach ($forbiddenNames as $forbiddenName) {
            self::assertNotContains(
                \strtolower($forbiddenName),
                $publicMethodNames,
                \sprintf('Forbidden public method "%s" found on %s.', $forbiddenName, $reflection->getName())
            );
        }
    }

    private static function assertHasNoDtoMarkerAttribute(ReflectionClass $reflection): void
    {
        foreach ($reflection->getAttributes() as $attribute) {
            self::assertNotSame(
                'Coretsia\\Dto\\Attribute\\Dto',
                $attribute->getName()
            );
        }
    }

    private static function assertInvalidArgument(callable $callback, string $label = 'invalid argument'): void
    {
        try {
            $callback();

            self::fail(\sprintf('Expected InvalidArgumentException for %s.', $label));
        } catch (\InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    private static function encodeForLeakAssertion(array $value): string
    {
        $encoded = \json_encode($value, \JSON_THROW_ON_ERROR);

        self::assertIsString($encoded);

        return $encoded;
    }
}
