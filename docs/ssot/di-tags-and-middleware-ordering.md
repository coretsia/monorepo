<!--
  Coretsia Framework (Monorepo)

  Project: Coretsia Framework (Monorepo)
  Authors: Vladyslav Mudrichenko and contributors
  Copyright (c) 2026 Vladyslav Mudrichenko

  SPDX-FileCopyrightText: 2026 Vladyslav Mudrichenko
  SPDX-License-Identifier: Apache-2.0

  For contributors list, see git history.
  See LICENSE and NOTICE in the project root for full license information.
-->

# DI Tags and Middleware Ordering SSoT

## Scope

This document is the Single Source of Truth for Coretsia DI tag discovery consumption rules, canonical discovery ordering, tag registration dedupe behavior, and consumer obligations.

This document is introduced by epic `1.200.0`.

It applies to every runtime consumer that obtains ordered service discovery lists from:

```text
Coretsia\Foundation\Tag\TagRegistry::all(string $tag)
```

Framework-reserved DI tag identifier strings are declared separately in:

```text
Coretsia\Foundation\Tag\ReservedTags
```

`ReservedTags` owns identifier strings only. `TagRegistry` owns runtime tagged-service discovery lists, ordering, and dedupe behavior.

This document does not introduce DI tags, config roots, config keys, generated artifacts, middleware classes, middleware stacks, kernel lifecycle behavior, or package-specific tag metadata schemas.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Runtime packages need one deterministic discovery protocol for tagged services.

The protocol must prevent every consumer from inventing its own ordering, sorting, dedupe, or middleware-list composition behavior.

This document defines:

- discovery-list consumption rules;
- canonical deterministic ordering;
- canonical dedupe behavior;
- consumer obligations;
- HTTP middleware slot references;
- priority guidance boundaries.

## Required source SSoTs

This document depends on the following canonical SSoTs:

```text
docs/ssot/tags.md
docs/ssot/http-middleware-catalog.md
```

The tag registry SSoT remains the canonical owner of reserved DI tag names, semantic owner rows, reserved prefixes, and tag naming rules.

The canonical code-level registry for framework-reserved DI tag identifier strings is:

```text
Coretsia\Foundation\Tag\ReservedTags
```

The HTTP middleware catalog remains the canonical owner of HTTP middleware slot taxonomy, slot contents, baseline middleware placement, and HTTP-specific ownership boundaries.

## Ownership boundary

This document MUST NOT redefine tag ownership or registry rows from:

```text
docs/ssot/tags.md
```

This document MUST NOT declare or duplicate framework-reserved DI tag identifier constants.

Runtime package source MUST use `Coretsia\Foundation\Tag\ReservedTags::*` as the only code-level identifier registry for framework-reserved DI tag identifiers.

This document MUST NOT redefine HTTP middleware catalog ownership, slot contents, middleware class placement, optional package participation, or HTTP middleware implementation rules from:

```text
docs/ssot/http-middleware-catalog.md
```

This document owns only:

- discovery consumption rules;
- deterministic ordering rule;
- dedupe rule;
- consumer obligations.

## Non-goals

This document does not define:

- new DI tags;
- new reserved tag prefixes;
- tag owner package IDs;
- reserved tag identifier constants;
- additional code-level registries for framework-reserved DI tag identifiers;
- tag metadata schemas;
- config roots;
- config keys;
- container build order;
- service provider ordering;
- HTTP middleware implementation;
- HTTP middleware class lists;
- HTTP middleware defaults;
- kernel hook execution;
- reset orchestration implementation;
- generated artifacts;
- compiled container format;
- compiled middleware format.

## Canonical discovery authority

`Coretsia\Foundation\Tag\TagRegistry` is the canonical runtime discovery registry for tagged services.

The canonical discovery API is:

```text
Coretsia\Foundation\Tag\TagRegistry::all(string $tag): list<Coretsia\Foundation\Tag\TaggedService>
```

Consumers MUST treat `TagRegistry->all($tag)` output as the complete canonical discovery list for that tag.

Consumers MUST NOT bypass `TagRegistry` by reading provider internals, container internals, generated diagnostics, module metadata, package manifests, config files, or static fixtures as an alternative runtime discovery source.

Consumers MUST NOT reconstruct a competing discovery list from service ids, class names, package metadata, filesystem scans, reflection, container service ids, or module descriptors.

## Tagged service model

A tagged service entry is represented by:

```text
Coretsia\Foundation\Tag\TaggedService
```

The semantic fields are:

```text
id: string
priority: int
meta: array<string, mixed>
```

The `id` is the PSR-11 service id.

The `priority` participates in canonical deterministic ordering.

The `meta` map is owner-defined extension data. Consumers MAY read metadata only when the tag owner has documented a stable metadata schema for that specific tag.

Consumers MUST NOT infer generic metadata semantics for all tags.

## Canonical ordering rule

The canonical discovery ordering rule is single-choice:

```text
priority DESC, id ASC
```

The rule is implemented by:

```text
Coretsia\Foundation\Discovery\DeterministicOrder
```

String comparison MUST be byte-order and locale-independent.

Implementations MUST use `strcmp` semantics for service id comparison.

Ordering MUST NOT depend on:

- `setlocale(...)`;
- `LC_ALL`;
- ICU collation;
- filesystem traversal order;
- provider class-name sorting by consumers;
- insertion order after canonical discovery output is requested;
- PHP array hash iteration side effects outside Foundation-owned normalization.

## DeterministicOrder service boundary

`Coretsia\Foundation\Discovery\DeterministicOrder` is a stateless static canonical ordering primitive.

It is not a runtime service, not a strategy extension point, and not a replaceable DI dependency.

Foundation service providers MUST NOT register `DeterministicOrder::class` as a container service.

The ordering behavior MUST be locked by direct unit/contract tests and by integration tests proving that `TagRegistry->all($tag)` returns the canonical order.

## Dedupe policy

The canonical dedupe policy is single-choice:

```text
first wins
```

For the same `(tag, serviceId)` pair, the first registration MUST be retained.

Later duplicate registrations for the same `(tag, serviceId)` pair MUST be ignored deterministically.

This behavior is implemented by:

```text
Coretsia\Foundation\Tag\TagRegistry
```

Dedupe applies per tag.

The same service id MAY appear under different tags, subject to each tag owner's semantics.

## Consumer obligations

Consumers of `TagRegistry->all($tag)` MUST:

- treat the returned list as canonical;
- preserve the returned order;
- preserve Foundation-owned dedupe results;
- resolve services by PSR-11 service id only when execution needs an instance;
- keep tag-specific metadata interpretation within owner-documented semantics.

Consumers MUST NOT:

- re-sort the list;
- apply a different dedupe rule;
- re-dedupe by class name;
- re-dedupe by instance identity;
- re-dedupe by interface;
- re-dedupe by metadata values;
- append implicit package defaults after discovery;
- prepend implicit package defaults before discovery;
- use reflection to discover additional services for the same tag;
- treat diagnostics output as a runtime discovery source.

If a consumer needs a different order, that consumer MUST use a distinct owner-approved tag or wait for the owning epic to define an explicit owner-approved planning layer.

## Provider registration rule

Service providers MAY register tagged services through Foundation-owned builder APIs.

Provider order remains caller-supplied and significant.

`ContainerBuilder` MUST preserve the caller-supplied provider order exactly and MUST NOT globally re-sort providers by FQCN.

Container definition collision policy and tag dedupe policy are separate:

- container definitions: later provider binding overrides earlier binding deterministically;
- tag registrations: first `(tag, serviceId)` occurrence wins deterministically.

Consumers MUST NOT attempt to recover provider ordering after reading `TagRegistry->all($tag)`.

## Container definition lifecycle rule

Foundation container definitions have explicit lifecycle semantics.

`ContainerBuilder::set(...)`, `ContainerBuilder::bind(...)`, and `ContainerBuilder::factory(...)` accept a `shared` flag.

The default lifecycle is:

```text
shared = true
```

A shared definition MUST be resolved at most once per container instance. After the first successful resolution, the resolved value is cached by service id and subsequent `Container::get($id)` calls MUST return the same resolved value.

A non-shared definition is represented by:

```text
shared = false
```

A non-shared definition MUST be resolved on every `Container::get($id)` call. The resolved value MUST NOT be stored in the container resolved-instance cache.

`ContainerBuilder::instance(...)` always registers an already-created shared runtime instance. Instances are not non-shared definitions.

Definition lifecycle does not alter provider ordering or collision rules.

For the same service id:

- later definitions still override earlier definitions deterministically;
- later instances still override earlier definitions or instances deterministically;
- overriding a definition MUST replace the previous lifecycle flag for that service id;
- registering an instance MUST remove any previous definition lifecycle flag for that service id.

Tag registration lifecycle is independent from container definition lifecycle.

The `shared` flag applies only to container definitions. It MUST NOT alter `TagRegistry` dedupe behavior, tag priority ordering, or discovery-list semantics.

Alias-like definitions that delegate to another service SHOULD be non-shared wrappers unless the alias owner intentionally wants the alias itself to cache the resolved target. Compiled-container runtime aliases MUST be non-shared delegation wrappers so that aliases do not accidentally turn non-shared target services into shared services.

## HTTP middleware slot references

The canonical HTTP middleware slot taxonomy is owned by:

```text
docs/ssot/http-middleware-catalog.md
```

This document references the canonical slot tags only to define discovery consumption behavior.

The canonical HTTP middleware slot tags are:

```text
http.middleware.system_pre
http.middleware.system
http.middleware.system_post
http.middleware.app_pre
http.middleware.app
http.middleware.app_post
http.middleware.route_pre
http.middleware.route
http.middleware.route_post
```

HTTP middleware stack composition MUST consume each slot through:

```text
TagRegistry->all(<slotTag>)
```

HTTP middleware stack consumers MUST NOT re-sort or re-dedupe the returned slot lists.

This document does not define which middleware classes belong in each slot.

Middleware class placement, optional owner participation, rate-limit placement, observability middleware placement, and baseline HTTP catalog entries are owned by:

```text
docs/ssot/http-middleware-catalog.md
```

## Priority bands guidance

Priority values are integers.

Higher priority executes earlier in discovery-list order for consumers that execute the list from first to last.

The canonical ordering rule remains:

```text
priority DESC, id ASC
```

Package owners SHOULD leave numeric gaps between baseline priorities so later owner epics can insert package-owned services without changing existing priority values.

HTTP middleware priority bands and concrete placement guidance MUST be read from:

```text
docs/ssot/http-middleware-catalog.md
```

This document does not own HTTP middleware class priorities.

For non-HTTP tags, the tag owner MAY define tag-specific priority bands in the owner package SSoT or owner epic.

If no tag-specific priority bands are defined, consumers MUST still use the canonical ordering rule.

## Reset discovery relationship

Reset discovery is Foundation-owned through the effective reset discovery tag configured by:

```text
foundation.reset.tag
```

The reserved default value is:

```text
kernel.reset
```

The canonical code-level identifier for this framework-reserved DI tag is:

```text
Coretsia\Foundation\Tag\ReservedTags::KERNEL_RESET
```

Reset execution MUST obtain the reset list through:

```text
TagRegistry->all($effectiveResetTag)
```

In base mode, reset execution MUST use the exact order returned by `TagRegistry->all($effectiveResetTag)` and MUST NOT apply additional sorting.

Enhanced reset planning from epic `1.250.0` MAY define a reset-specific planned order, but when enhanced mode is disabled, reset execution MUST remain exact base mode.

This document does not define reset exception taxonomy or reset plan metadata.

## Diagnostics boundary

Diagnostics MAY report tag names, safe service ids, and priorities when safe.

Diagnostics MUST NOT become a runtime discovery source.

Diagnostics MUST NOT serialize tag metadata unless the caller has explicitly redacted and owner-approved it.

Diagnostics MUST NOT dump service instances, constructor arguments, reflection data, raw config payloads, environment values, tokens, credentials, cookies, authorization headers, private customer data, or absolute local paths.

Container diagnostics MUST sanitize service ids before exporting diagnostic snapshots.

Readable service ids MAY remain visible only when they are normal class-like ids or conservative safe aliases.

Suspicious, sensitive, unsafe, control-character-containing, URL-like, token-like, credential-like, password-like, secret-like, cookie-like, authorization-like, SQL-like, path-like, absolute-path-like, overlong, or otherwise non-readable service ids MUST NOT appear raw in diagnostics.

Unsafe service ids MAY be replaced with deterministic hash diagnostics using the canonical format:

```text
hash:sha256:<hash>;len:<len>
```

The `<hash>` value is the lowercase hexadecimal SHA-256 hash of the original service id bytes.

The `<len>` value is the byte length of the original service id.

Suspicious or sensitive service-id detection MUST take precedence over readable alias allowlisting.

For example, aliases such as the following MUST be hashed even if they match a conservative alias character pattern:

```text
token:abc
secret.value
password:raw
credential.token
```

Container diagnostics MAY include only:

```text
schema version
safe service id diagnostics
tag names
tag priorities
```

Container diagnostics MUST NOT introduce or alter:

- tag ownership;
- tag names;
- reserved tag prefixes;
- tag metadata schemas;
- discovery ordering;
- discovery dedupe behavior;
- reset discovery semantics;
- middleware discovery semantics.

Diagnostics output remains introspection-only and MUST NOT be consumed as the canonical runtime discovery source.

## Static fixture boundary

The Phase 0 fixture:

```text
framework/tools/spikes/fixtures/http_middleware_catalog.php
```

MAY be cited only as a Phase 0 lock-source or alignment input.

It is not the SSoT for HTTP middleware slot ownership, slot contents, runtime discovery, or middleware placement.

The canonical HTTP middleware SSoT is:

```text
docs/ssot/http-middleware-catalog.md
```

## Correct usage examples

### Discovering HTTP middleware for a slot

Documentation examples may show raw tag strings for readability.

Runtime package source MUST use the corresponding `ReservedTags::*` constant for framework-reserved DI tags.

```php
$middleware = $tagRegistry->all('http.middleware.app_pre');

foreach ($middleware as $taggedService) {
    $service = $container->get($taggedService->id());
    // Execute according to the owning HTTP pipeline semantics.
}
```

The consumer preserves the exact `TagRegistry->all(...)` order.

### Discovering resettable services in base mode

```php
foreach ($tagRegistry->all($effectiveResetTag) as $taggedService) {
    $service = $container->get($taggedService->id());

    if (!$service instanceof ResetInterface) {
        throw new RuntimeException('reset-not-resettable');
    }

    $service->reset();
}
```

The reset executor preserves the exact `TagRegistry->all(...)` order in base mode.

## Incorrect usage examples

### Re-sorting a discovery list

```php
$services = $tagRegistry->all('http.middleware.app_pre');

usort($services, static fn($left, $right): int => $left->id() <=> $right->id());
```

This is forbidden.

Consumers MUST NOT re-sort `TagRegistry->all($tag)` output.

### Applying a competing dedupe rule

```php
$services = [];

foreach ($tagRegistry->all('http.middleware.app_pre') as $taggedService) {
    $className = $container->get($taggedService->id())::class;
    $services[$className] = $taggedService;
}
```

This is forbidden.

Consumers MUST NOT apply class-name dedupe, instance dedupe, interface dedupe, or metadata-based dedupe.

### Reconstructing discovery through reflection

```php
foreach ($allKnownClasses as $className) {
    if (is_subclass_of($className, MiddlewareInterface::class)) {
        $middleware[] = $className;
    }
}
```

This is forbidden for tag-based runtime discovery.

Consumers MUST use `TagRegistry->all($tag)`.

## Test evidence

Foundation ordering behavior SHOULD be locked by tests covering:

```text
framework/packages/core/foundation/tests/Unit/DeterministicOrderSortRuleTest.php
framework/packages/core/foundation/tests/Contract/DeterministicOrderSortContractTest.php
framework/packages/core/foundation/tests/Integration/Container/ContainerFactoryDefinitionsCanBeNonSharedTest.php
framework/packages/core/foundation/tests/Integration/TagRegistryReturnsDeterministicOrderTest.php
framework/packages/core/foundation/tests/Integration/TagRegistryDedupeFirstWinsTest.php
```

Container diagnostics safety SHOULD be locked by tests covering:

```text
framework/packages/core/foundation/tests/Contract/ContainerDiagnosticsDoesNotLeakSensitiveServiceIdsContractTest.php
```

These tests are expected to verify that container diagnostics keep normal FQCN service ids and safe aliases readable, hash unsafe or suspicious service ids deterministically, do not leak raw unsafe service ids in JSON diagnostics, and do not turn diagnostics output into a runtime discovery source.

Reset discovery behavior SHOULD be locked by tests covering:

```text
framework/packages/core/foundation/tests/Integration/ResetOrchestratorInvokesResetExactlyOncePerServiceTest.php
framework/packages/core/foundation/tests/Integration/ResetOrchestratorRejectsTaggedNonResettableServiceTest.php
framework/packages/core/foundation/tests/Integration/ResetOrchestratorUsesConfiguredResetTagTest.php
```

HTTP middleware stack tests are owned by the HTTP implementation package epics.

## Runtime acceptance scenario

When a runtime consumer needs services registered under a DI tag:

1. the consumer requests the list from `TagRegistry->all($tag)`;
2. Foundation returns the list in canonical order:
   - `priority DESC`;
   - `id ASC` by byte-order `strcmp`;
3. duplicate `(tag, serviceId)` registrations have already been deduped by first-wins policy;
4. the consumer preserves the returned list exactly;
5. the consumer resolves services by PSR-11 id only when instances are needed;
6. the consumer does not re-sort, re-dedupe, or reconstruct the list from another source.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Tag Registry](./tags.md) — canonical tag names, reserved prefixes, ownership, and registry rows.
- [HTTP Middleware Catalog SSoT](./http-middleware-catalog.md)
- [Config Roots Registry](./config-roots.md)
- [UoW and Reset Contracts SSoT](./uow-and-reset-contracts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
