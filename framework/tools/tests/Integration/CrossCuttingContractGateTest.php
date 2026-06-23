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

namespace Coretsia\Tools\Tests\Integration;

use Coretsia\Tools\Tests\Contract\Support\ToolContractTestCase;

final class CrossCuttingContractGateTest extends ToolContractTestCase
{
    public function testStatefulServiceWithoutResetInterfaceFailsDeterministically(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeStatefulService($sandbox, implementsResetInterface: false);
        $this->writeStatefulProvider($sandbox, resetTagExpression: 'ReservedTags::KERNEL_RESET');

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('kernel-stateful-service-not-resettable', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testStatefulServiceWithoutEffectiveResetTagFailsDeterministically(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeStatefulService($sandbox, implementsResetInterface: true);
        $this->writeStatefulProvider($sandbox, resetTagExpression: null);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('kernel-stateful-service-missing-reset-tag', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testDefaultKernelResetTagIsUsedWhenFoundationConfigDoesNotOverride(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeStatefulService($sandbox, implementsResetInterface: true);
        $this->writeStatefulProvider($sandbox, resetTagExpression: 'ReservedTags::KERNEL_RESET');

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testCustomFoundationResetTagIsRespectedWhenConfigEvidenceExists(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox(foundationResetTag: 'app.reset');

        $this->writeStatefulService($sandbox, implementsResetInterface: true);
        $this->writeStatefulProvider($sandbox, resetTagExpression: "'app.reset'");

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testDefaultKernelResetTagDoesNotSatisfyCustomFoundationResetTag(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox(foundationResetTag: 'app.reset');

        $this->writeStatefulService($sandbox, implementsResetInterface: true);
        $this->writeStatefulProvider($sandbox, resetTagExpression: 'ReservedTags::KERNEL_RESET');

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('kernel-stateful-service-missing-reset-tag', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testGateIsDeterministicNoopWhenFoundationEvidenceIsAbsent(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox(includeFoundationEvidence: false);

        $this->writeStatefulService($sandbox, implementsResetInterface: false);
        $this->writeStatefulProvider($sandbox, resetTagExpression: null);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testInvalidFoundationResetTagFailsWithoutLeakingRawConfigPayload(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox(foundationResetTag: 'Invalid Tag With Spaces');

        $this->writeStatefulService($sandbox, implementsResetInterface: true);
        $this->writeStatefulProvider($sandbox, resetTagExpression: 'ReservedTags::KERNEL_RESET');

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString(
            'packages/core/foundation/config/foundation.php: foundation-reset-tag-invalid',
            $output
        );
        self::assertStringNotContainsString('Invalid Tag With Spaces', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testFoundationContextOwnerUsageAndEffectiveResetTagVariablePass(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeFoundationContextProvider($sandbox, verifiedEffectiveResetTagVariable: true);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testEffectiveResetTagVariableMustComeFromFoundationServiceFactory(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeFoundationContextProvider($sandbox, verifiedEffectiveResetTagVariable: false);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('kernel-stateful-service-missing-reset-tag', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testUnrelatedNonResettableClassNearStatefulTagDoesNotFailWhenTaggedServiceIsResettable(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeUnrelatedNonResettableFoundationClass($sandbox);
        $this->writeFoundationContextProviderWithNearbyUnrelatedClass($sandbox);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testDirectContextStoreUsageOutsideFoundationProviderFails(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeForbiddenContextStoreConsumer($sandbox);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('forbidden-context-store-usage', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    public function testContractsContextKeysUsageFromPlatformConsumerPasses(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeContractsContextKeysConsumer($sandbox);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertSame(0, $code, $output);
        self::assertSame('', trim($output));
    }

    public function testLegacyFoundationContextKeysUsageFails(): void
    {
        $sandbox = $this->createCrossCuttingGateSandbox();

        $this->writeFoundationContextSymbols($sandbox);
        $this->writeLegacyFoundationContextKeysConsumer($sandbox);

        [$code, $output] = $this->runCrossCuttingGate($sandbox);

        self::assertNotSame(0, $code, $output);
        self::assertStringContainsString('CORETSIA_CROSS_CUTTING_CONTRACT_DRIFT', $output);
        self::assertStringContainsString('forbidden-context-keys-usage', $output);
        $this->assertSafeGateOutput($sandbox, $output);
    }

    private function createCrossCuttingGateSandbox(
        bool $includeFoundationEvidence = true,
        ?string $foundationResetTag = null,
    ): string {
        $sandbox = $this->tempDir('coretsia-cross-cutting-gate');

        $this->ensureDir($sandbox . '/framework/tools/gates');
        $this->ensureDir($sandbox . '/framework/tools/spikes');
        $this->ensureDir($sandbox . '/framework/vendor');

        $this->writeBytesExact(
            $sandbox . '/framework/vendor/autoload.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return true;\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/tools/gates/cross_cutting_contract_gate.php',
            $this->readBytes($this->frameworkRoot() . '/tools/gates/cross_cutting_contract_gate.php'),
        );

        $this->copyDir(
            $this->frameworkRoot() . '/tools/spikes/_support',
            $sandbox . '/framework/tools/spikes/_support',
        );

        $this->writeResetInterface($sandbox);

        if ($includeFoundationEvidence) {
            $this->writeReservedTags($sandbox);

            if ($foundationResetTag !== null) {
                $this->writeFoundationConfig($sandbox, $foundationResetTag);
            }
        }

        return $sandbox;
    }

    /**
     * @return array{0:int,1:string}
     */
    private function runCrossCuttingGate(string $sandbox): array
    {
        return $this->runPhp(
            $sandbox . '/framework/tools/gates/cross_cutting_contract_gate.php',
            [],
            $sandbox,
        );
    }

    private function writeResetInterface(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/contracts/src/Runtime/ResetInterface.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Contracts\\Runtime;\n\n"
            . "interface ResetInterface\n"
            . "{\n"
            . "    public function reset(): void;\n"
            . "}\n",
        );
    }

    private function writeReservedTags(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Tag/ReservedTags.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Tag;\n\n"
            . "final class Tags\n"
            . "{\n"
            . "    public const string KERNEL_STATEFUL = 'kernel.stateful';\n"
            . "    public const string KERNEL_RESET = 'kernel.reset';\n"
            . "}\n",
        );
    }

    private function writeFoundationConfig(string $sandbox, string $resetTag): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/config/foundation.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "return [\n"
            . "    'reset' => [\n"
            . "        'tag' => '" . str_replace("'", "\\'", $resetTag) . "',\n"
            . "    ],\n"
            . "];\n",
        );
    }

    private function writeStatefulService(string $sandbox, bool $implementsResetInterface): void
    {
        $implements = $implementsResetInterface ? ' implements ResetInterface' : '';
        $use = $implementsResetInterface ? "use Coretsia\\Contracts\\Runtime\\ResetInterface;\n\n" : '';

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Fixture/StatefulService.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Fixture;\n\n"
            . $use
            . "final class StatefulService{$implements}\n"
            . "{\n"
            . "    public function reset(): void\n"
            . "    {\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeStatefulProvider(string $sandbox, ?string $resetTagExpression): void
    {
        $tags = [
            'ReservedTags::KERNEL_STATEFUL',
        ];

        if ($resetTagExpression !== null) {
            $tags[] = $resetTagExpression;
        }

        $tagLines = [];
        foreach ($tags as $tag) {
            $tagLines[] = '                    ' . $tag . ',';
        }

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/FixtureServiceProvider.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "use Coretsia\\Foundation\\Fixture\\StatefulService;\n\n"
            . "final class FixtureServiceProvider\n"
            . "{\n"
            . "    /**\n"
            . "     * @return array<string,mixed>\n"
            . "     */\n"
            . "    public function definitions(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            StatefulService::class => [\n"
            . "                'tags' => [\n"
            . implode("\n", $tagLines) . "\n"
            . "                ],\n"
            . "            ],\n"
            . "        ];\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function assertSafeGateOutput(string $sandbox, string $output): void
    {
        $normalizedSandbox = rtrim(str_replace('\\', '/', $sandbox), '/');

        self::assertStringNotContainsString($normalizedSandbox, str_replace('\\', '/', $output));
        self::assertStringNotContainsString('\\', $output);
        self::assertStringNotContainsString('StatefulService::class', $output);
        self::assertStringNotContainsString('return [', $output);
        self::assertStringNotContainsString('Invalid Tag With Spaces', $output);
    }

    private function writeFoundationContextSymbols(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/contracts/src/Context/ContextKeys.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Contracts\\Context;\n\n"
            . "final class ContextKeys\n"
            . "{\n"
            . "    public const string CORRELATION_ID = 'correlation_id';\n\n"
            . "    public static function isKnown(string \$key): bool\n"
            . "    {\n"
            . "        return \$key === self::CORRELATION_ID;\n"
            . "    }\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Context/ContextStore.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Context;\n\n"
            . "use Coretsia\\Contracts\\Runtime\\ResetInterface;\n\n"
            . "final class ContextStore implements ResetInterface\n"
            . "{\n"
            . "    public function reset(): void\n"
            . "    {\n"
            . "    }\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Context/ContextStorePolicy.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Context;\n\n"
            . "use Coretsia\\Contracts\\Context\\ContextKeys;\n\n"
            . "final class ContextStorePolicy\n"
            . "{\n"
            . "    public function assertKey(string \$key): void\n"
            . "    {\n"
            . "        ContextKeys::isKnown(\$key);\n"
            . "    }\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Observability/CorrelationIdProvider.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Observability;\n\n"
            . "use Coretsia\\Contracts\\Context\\ContextKeys;\n\n"
            . "final class CorrelationIdProvider\n"
            . "{\n"
            . "    public function key(): string\n"
            . "    {\n"
            . "        return ContextKeys::CORRELATION_ID;\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeFoundationContextProvider(string $sandbox, bool $verifiedEffectiveResetTagVariable): void
    {
        $assignment = $verifiedEffectiveResetTagVariable
            ? "        \$effectiveResetTag = FoundationServiceFactory::effectiveResetTag(\$foundationConfig);\n"
            : "        \$effectiveResetTag = 'kernel.reset';\n";

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "final class FoundationServiceFactory\n"
            . "{\n"
            . "    /**\n"
            . "     * @param array<string,mixed> \$foundationConfig\n"
            . "     */\n"
            . "    public static function effectiveResetTag(array \$foundationConfig): string\n"
            . "    {\n"
            . "        return 'kernel.reset';\n"
            . "    }\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "use Coretsia\\Foundation\\Context\\ContextStore;\n\n"
            . "final class FoundationServiceProvider\n"
            . "{\n"
            . "    /**\n"
            . "     * @param array<string,mixed> \$foundationConfig\n"
            . "     */\n"
            . "    public function register(object \$builder, array \$foundationConfig): void\n"
            . "    {\n"
            . $assignment
            . "        \$contextStore = new ContextStore();\n"
            . "        \$builder->instance(ContextStore::class, \$contextStore);\n"
            . "        \$builder->tag(\$effectiveResetTag, ContextStore::class);\n"
            . "        \$builder->tag(ReservedTags::KERNEL_STATEFUL, ContextStore::class);\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeUnrelatedNonResettableFoundationClass(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/UnrelatedService.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "final class UnrelatedService\n"
            . "{\n"
            . "}\n",
        );
    }

    private function writeFoundationContextProviderWithNearbyUnrelatedClass(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/FoundationServiceFactory.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "final class FoundationServiceFactory\n"
            . "{\n"
            . "    /**\n"
            . "     * @param array<string,mixed> \$foundationConfig\n"
            . "     */\n"
            . "    public static function effectiveResetTag(array \$foundationConfig): string\n"
            . "    {\n"
            . "        return 'kernel.reset';\n"
            . "    }\n"
            . "}\n",
        );

        $this->writeBytesExact(
            $sandbox . '/framework/packages/core/foundation/src/Provider/FoundationServiceProvider.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Foundation\\Provider;\n\n"
            . "use Coretsia\\Foundation\\Context\\ContextStore;\n\n"
            . "final class FoundationServiceProvider\n"
            . "{\n"
            . "    /**\n"
            . "     * @param array<string,mixed> \$foundationConfig\n"
            . "     */\n"
            . "    public function register(object \$builder, array \$foundationConfig): void\n"
            . "    {\n"
            . "        \$effectiveResetTag = FoundationServiceFactory::effectiveResetTag(\$foundationConfig);\n"
            . "        \$unrelated = new UnrelatedService();\n"
            . "        \$contextStore = new ContextStore();\n"
            . "        \$builder->instance(UnrelatedService::class, \$unrelated);\n"
            . "        \$builder->instance(ContextStore::class, \$contextStore);\n"
            . "        \$builder->tag(\$effectiveResetTag, ContextStore::class);\n"
            . "        \$builder->tag(ReservedTags::KERNEL_STATEFUL, ContextStore::class);\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeForbiddenContextStoreConsumer(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/platform/logging/src/BadContextStoreConsumer.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Platform\\Logging;\n\n"
            . "use Coretsia\\Foundation\\Context\\ContextStore;\n\n"
            . "final class BadContextStoreConsumer\n"
            . "{\n"
            . "    public function create(): ContextStore\n"
            . "    {\n"
            . "        return new ContextStore();\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeContractsContextKeysConsumer(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/platform/logging/src/ContextKeysConsumer.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Platform\\Logging;\n\n"
            . "use Coretsia\\Contracts\\Context\\ContextKeys;\n\n"
            . "final class ContextKeysConsumer\n"
            . "{\n"
            . "    public function key(): string\n"
            . "    {\n"
            . "        return ContextKeys::CORRELATION_ID;\n"
            . "    }\n"
            . "}\n",
        );
    }

    private function writeLegacyFoundationContextKeysConsumer(string $sandbox): void
    {
        $this->writeBytesExact(
            $sandbox . '/framework/packages/platform/logging/src/LegacyFoundationContextKeysConsumer.php',
            "<?php\n\n"
            . "declare(strict_types=1);\n\n"
            . "namespace Coretsia\\Platform\\Logging;\n\n"
            . "use Coretsia\\Foundation\\Context\\ContextKeys;\n\n"
            . "final class LegacyFoundationContextKeysConsumer\n"
            . "{\n"
            . "    public function key(): string\n"
            . "    {\n"
            . "        return ContextKeys::CORRELATION_ID;\n"
            . "    }\n"
            . "}\n",
        );
    }
}
