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

# ADR-0012: Mail port

## Status

Accepted.

## Context

Epic `1.170.0` introduces stable mail contracts under:

```text
framework/packages/core/contracts/src/Mail/
```

Runtime packages need a shared mail boundary that allows application code to send mail through stable contracts while concrete delivery behavior remains swappable.

Mail delivery must support future transports without API changes.

Expected future transport implementations may include:

```text
smtp
api
memory
null
log-safe
queue-backed
external-service-backed
```

These implementations have different operational characteristics, delivery semantics, retry behavior, credential requirements, provider payloads, queue behavior, and failure modes. Those differences must not leak into `core/contracts`.

Mail recipients, subject, body, headers, reply-to values, and transport credentials are sensitive by default.

The contracts package must remain a pure library boundary.

It must not depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- Symfony Mailer concrete APIs
- Symfony Mime concrete APIs
- PHPMailer concrete APIs
- SwiftMailer concrete APIs
- Guzzle concrete APIs
- HTTP client concrete APIs
- SMTP concrete clients
- queue concrete APIs
- database concrete APIs
- Redis concrete APIs
- cache concrete APIs
- lock concrete APIs
- clock concrete APIs
- vendor concrete clients
- concrete service container implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- generated architecture artifacts

The detailed normative policy for this ADR is defined by:

```text
docs/ssot/mail-contracts.md
```

Mail observability, diagnostics, error, DTO, config, tag, and artifact behavior must also follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/dto-policy.md
docs/ssot/config-roots.md
docs/ssot/tags.md
docs/ssot/artifacts.md
```

## Decision

Coretsia will introduce vendor-agnostic mail contracts as format-neutral contracts in `core/contracts`.

The contracts introduced by epic `1.170.0` define:

- `MailerInterface`
- `MailTransportInterface`
- `MailMessage`
- `MailException`

The contracts package defines only:

- the stable application-facing mailer port;
- the stable swappable transport port;
- the immutable mail message model;
- the deterministic safe exception boundary;
- the deterministic generic mail delivery failure code;
- transport-swap boundary policy;
- recipient, subject, body, credential, and provider-payload redaction constraints;
- async-safe message shape policy for future queueing;
- observability constraints for future runtime owners.

The contracts package does not implement:

- concrete mail delivery;
- SMTP behavior;
- API-backed delivery;
- queue publishing;
- queue consuming;
- retry policy;
- template rendering;
- markdown rendering;
- HTML rendering or sanitization;
- MIME compilation;
- attachment handling;
- credential resolution;
- secret provider integration;
- DSN parsing;
- transport selection;
- transport discovery;
- config loading;
- logger integration;
- metrics integration;
- tracing integration;
- health checks;
- DI registration;
- generated artifacts.

## Mailer port decision

`MailerInterface` is the canonical contracts-level application-facing mail port.

The canonical interface shape is:

```text
send(MailMessage $message): void
```

`send()` accepts only the contracts-level `MailMessage`.

The interface intentionally does not prescribe whether a future implementation sends mail synchronously, schedules the message, delegates to a transport, or hands the message to a queue.

That behavior is runtime-owned.

`send()` returns `void`.

It must not expose transport-specific response objects, provider payloads, message ids, queue ids, HTTP responses, SMTP responses, or vendor diagnostics through the contracts surface.

`send()` may throw `MailException` for contracts-level mail failure boundaries.

`MailException` exposes only the canonical generic mail delivery failure code and fixed safe message. It does not expose transport-specific, provider-specific, queue-specific, credential-specific, recipient-specific, subject-specific, or body-specific details.

Concrete runtime implementations may throw separate owner-defined exceptions only when those exceptions obey the redaction policy defined by the Mail Contracts SSoT.

`MailerInterface` must not expose:

- transport clients;
- SMTP clients;
- HTTP clients;
- queue clients;
- service containers;
- config repositories;
- credentials;
- DSNs;
- vendor response objects;
- raw provider errors;
- logger objects;
- tracer objects;
- metric objects.

## Transport port decision

`MailTransportInterface` is the canonical contracts-level swappable mail transport port.

The canonical interface shape is:

```text
name(): string
send(MailMessage $message): void
```

`name()` returns a stable safe bounded transport implementation name such as:

```text
smtp
api
memory
null
queue
external
```

It may be used by runtime owners as a safe `driver` value only when observability policy allows it.

It must not expose recipients, subject, body, tenant ids, user ids, request ids, correlation ids, hostnames, DSNs, connection strings, credentials, tokens, usernames, passwords, API keys, provider message ids, environment-specific identifiers, or backend object metadata.

`send()` sends the supplied `MailMessage` according to implementation-owned transport semantics.

It returns `void`.

The transport interface must not expose backend-specific concepts such as:

- SMTP clients;
- HTTP clients;
- queue clients;
- vendor clients;
- provider request objects;
- provider response objects;
- request objects;
- response objects;
- PSR-7 objects;
- framework context objects;
- middleware objects;
- database connections;
- cache pool objects;
- lock objects;
- clock objects;
- service container objects;
- runtime wiring objects;
- streams;
- resources;
- closures;
- iterators;
- generators.

This keeps the transport surface stable while allowing future runtime owners and integrations to implement different delivery backends.

## Message model decision

`MailMessage` is the canonical immutable contracts model for a mail message passed across the contracts boundary.

It is a contracts transport model.

It may carry raw delivery data because transports require the actual recipient, subject, body, headers, and reply-to data to send mail.

Raw delivery data must remain inside the runtime call path.

Raw delivery data must not become diagnostics or exported public safe shape.

The canonical logical fields are:

```text
schemaVersion
to
cc
bcc
replyTo
subject
body
headers
metadata
```

The canonical mail message schema version is:

```text
1
```

`to` must contain at least one recipient string.

`cc`, `bcc`, and `replyTo` may be empty lists.

`subject` must be a non-empty safe single-line string.

`body` must be a non-empty safe string.

`headers` and `metadata` must be deterministic json-like maps.

`MailMessage` must not use floats in headers or metadata.

`MailMessage` must validate textual input exactly as supplied.

It must not trim, collapse, lowercase, uppercase, normalize Unicode, rewrite newlines, parse display names, canonicalize addresses, MIME-encode, decode, sanitize, render, or otherwise change textual input before validation.

The full email address syntax, IDN policy, display-name support, provider-specific restrictions, bounce handling, sender policy, HTML vs text semantics, multipart behavior, template rendering, and attachment behavior are runtime-owned.

`MailMessage::toArray()` returns a deterministic safe diagnostic shape.

It must not expose raw recipients, raw subject, or raw body.

The canonical exported safe shape contains:

```text
bccCount
bodyLength
ccCount
headers
metadata
replyToCount
schemaVersion
subjectLength
toCount
```

The exported count and length fields are derived from raw message fields without exposing raw values.

The exported `headers` and `metadata` values are the safe normalized maps.

## Recipient/body redaction decision

Mail recipients, subject, body, headers, reply-to values, provider payloads, and transport credentials are sensitive by default.

Raw mail recipients must never be logged, printed, traced, exported, rendered, copied into error descriptor extensions, emitted as metric labels, exposed through health output, rendered in CLI output, or included in exception messages.

Raw mail subject must never be logged, printed, traced, exported, rendered, copied into error descriptor extensions, emitted as metric labels, exposed through health output, rendered in CLI output, or included in exception messages.

Raw mail body must never be logged, printed, traced, exported, rendered, copied into error descriptor extensions, emitted as metric labels, exposed through health output, rendered in CLI output, or included in exception messages.

Mail credentials must not appear in mail contracts.

Safe diagnostics may expose only derived information and stable generic machine codes such as:

```text
recipientCount
toCount
ccCount
bccCount
replyToCount
subjectLength
bodyLength
hash(value)
len(value)
operation
outcome
driver
CORETSIA_MAIL_DELIVERY_FAILED
```

Safe derivations must not expose raw recipients, raw subject, raw body, credentials, private customer data, provider payloads, or allow reconstruction of mail content.

Runtime owners must prefer omission over unsafe emission.

## Async-safe shape decision

`MailMessage` must be safe to hand to a future queueing layer without changing the public contract shape.

Async-safe means:

- the message model is immutable;
- the message model is final;
- the message model is readonly;
- the message model does not contain service instances;
- the message model does not contain vendor objects;
- the message model does not contain closures;
- the message model does not contain resources;
- the message model does not contain streams;
- the message model does not contain filesystem handles;
- the message model does not contain transport clients;
- the message model does not contain credentials;
- headers and metadata are json-like and deterministic.

Async-safe does not mean that `core/contracts` implements queue behavior.

Queue publishing, queue serialization, encryption at rest, retry policy, dead-letter policy, worker execution, queue transport selection, and queue failure handling are runtime-owned.

If a future queue owner serializes `MailMessage`, that owner must preserve the redaction policy and must not log serialized recipients, subjects, bodies, credentials, or provider payloads.

## Exception decision

`MailException` is the canonical contracts-level exception for mail sending failures.

It is a deterministic safe exception boundary.

It must expose this deterministic generic mail delivery failure code:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The deterministic mail delivery failure code is a stable string code, not the PHP native integer exception code.

The canonical code must be exposed as:

```text
MailException::CODE
```

The exception should also expose:

```text
errorCode(): string
```

`errorCode()` returns:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The exception message must remain fixed, safe, and generic.

The canonical message is:

```text
Mail delivery failed.
```

The canonical message must be exposed as:

```text
MailException::MESSAGE
```

The exception constructor may accept `?\Throwable $previous` solely for native PHP exception chaining.

Runtime owners must not pass unsafe provider, transport, queue, credential, recipient, subject, body, or backend exceptions as `$previous` unless those exceptions have already been redacted and are safe to retain.

`?\Throwable $previous` does not introduce runtime ownership, provider ownership, transport ownership, or error mapping ownership into `core/contracts`.

`MailException` is intentionally payload-free.

It must not contain recipients, subject, body, headers, credentials, DSNs, provider payloads, provider responses, request payloads, response payloads, private customer data, service instances, vendor objects, streams, resources, closures, or filesystem handles.

`MailException` messages must not include:

- raw recipients;
- raw subject;
- raw body;
- raw headers;
- raw credentials;
- tokens;
- API keys;
- passwords;
- private keys;
- DSNs;
- provider request payloads;
- provider response payloads;
- connection strings;
- hostnames when environment-specific;
- tenant ids;
- user ids;
- request ids;
- correlation ids;
- private customer data;
- absolute local paths;
- stack traces by default.

Concrete runtime implementations may wrap provider exceptions.

When wrapping provider exceptions, implementations must redact unsafe provider data before creating or reporting a `MailException`.

Runtime owner packages may define more specific platform-owned or integration-owned mail failure codes later.

Those owner-defined codes must not replace the generic contracts-level code defined by `MailException::CODE`.

Provider-specific, transport-specific, queue-specific, credential-specific, and policy-specific mail failure codes are runtime-owned and are not introduced by epic `1.170.0`.

If mail errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

A future runtime mapper may map `MailException` to `ErrorDescriptor` using:

```text
code: CORETSIA_MAIL_DELIVERY_FAILED
message: Mail delivery failed.
```

HTTP status selection, problem-details rendering, CLI rendering, worker failure rendering, mapper discovery, mapper ordering, fallback policy, and provider-specific failure classification are runtime-owned.

The contracts package must not implement mail exception mapping.

The contracts package must not require `ErrorDescriptor` construction inside mail contracts.

## Runtime ownership decision

Mail runtime behavior is owned by future runtime packages.

`platform/mail` is expected to implement mail orchestration using these contracts.

Transport implementations are expected to live in owner packages outside `core/contracts`, including future packages under:

```text
integrations/*
```

A future `platform/mail` package is expected to own:

- selecting the effective mailer;
- selecting a default transport;
- composing transport chains;
- applying retry policy;
- adapting mail sending to queues;
- resolving templates;
- rendering text or HTML bodies;
- resolving secrets;
- loading runtime configuration;
- mapping provider failures;
- emitting safe logs, metrics, and spans;
- providing test transports;
- registering DI services.

This ADR records policy intent only.

It does not create or modify `platform/mail`.

## DI/config/artifact decision

Epic `1.170.0` introduces no DI tags.

The contracts package must not declare public mail tag constants.

The contracts package must not define package-local mirror constants for mail tags.

The contracts package must not define:

- mail tag metadata keys;
- mailer discovery semantics;
- transport discovery semantics;
- transport priority semantics;
- policy discovery semantics;
- queue-mail discovery semantics.

If a future runtime owner needs mail DI tags, that owner must introduce them through:

```text
docs/ssot/tags.md
```

according to the tag registry rules.

Epic `1.170.0` introduces no config roots and no config keys.

The contracts package must not add package config defaults or config rules for mail contracts.

No files under package `config/` are introduced by this epic.

Transport selection, sender defaults, retry policy, queue policy, template policy, provider credentials, backend configuration, and fail-open or fail-closed behavior are runtime configuration concerns.

Future runtime owner packages may introduce mail config only through their own owner epics and the config roots registry process.

Epic `1.170.0` introduces no artifacts.

The contracts package must not generate:

- mail artifacts;
- mail transport artifacts;
- mail policy artifacts;
- mail template artifacts;
- mail queue artifacts;
- mail provider artifacts;
- mail routing artifacts;
- runtime lifecycle artifacts.

Future runtime owners may introduce generated mail artifacts only through their own owner epics and the artifact registry process.

Any future artifact must follow:

```text
docs/ssot/artifacts.md
```

## DTO boundary decision

`MailMessage` is an immutable contracts transport model.

It is not a DTO-marker class by default.

`MailerInterface` and `MailTransportInterface` are contracts interfaces.

They are not DTO-marker classes.

DTO policy remains explicit opt-in only through:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Contract tests enforce the mail contracts surface directly.

DTO gates do not own these models unless a future epic explicitly opts them into DTO policy.

## Observability decision

Mail contracts introduce no observability signals and no metric label keys.

Future runtime implementations may emit logs, spans, metrics, or profiling signals only when they follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels must remain within the canonical allowlist:

```text
method
status
driver
operation
table
outcome
```

For mail, reasonable safe label values may include bounded values such as:

```text
operation=send
operation=transport_send
outcome=sent
outcome=failed
outcome=queued
outcome=error
driver=smtp
driver=api
driver=memory
driver=null
driver=queue
```

only when those values are safe, stable, bounded, and owner-approved.

Mail implementations must not use the following as metric labels:

- recipient;
- recipient domain;
- sender;
- subject;
- body;
- message id when provider-owned or high-cardinality;
- provider response id;
- tenant id;
- user id;
- request id;
- correlation id;
- route parameter;
- raw path;
- raw query;
- header value;
- cookie value;
- token;
- session identifier;
- user-controlled policy value.

The forbidden baseline label keys remain forbidden:

```text
request_id
correlation_id
tenant_id
user_id
```

Recipient-domain labels are not introduced by this epic.

If a future owner wants recipient-domain aggregation, that owner must update the observability SSoT explicitly and prove bounded-cardinality and privacy safety.

## Consequences

Positive consequences:

- Application code can depend on one stable mailer API.
- Mail transports can be replaced without public API changes.
- `core/contracts` remains independent of SMTP, HTTP, queue, provider SDK, platform, and integration implementations.
- Mail messages are async-safe for future queue adaptation.
- Mail failures expose a deterministic generic machine-readable code.
- Raw recipients, subjects, bodies, credentials, and provider payloads are redacted by design.
- Runtime owners retain control over delivery behavior, retry behavior, template rendering, MIME handling, queue integration, provider mapping, config, DI tags, and artifacts.
- Runtime owners retain control over provider-specific, transport-specific, queue-specific, credential-specific, and policy-specific failure classification.
- Observability labels remain low-cardinality and policy-compliant.
- Future test, memory, null, SMTP, API, and queue-backed transports can implement the same transport contract.

Trade-offs:

- Contracts do not define a concrete mailer implementation.
- Contracts do not define concrete transport behavior.
- Contracts do not define MIME generation.
- Contracts do not define template rendering.
- Contracts do not define attachment handling.
- Contracts do not define strict email address syntax validation.
- Contracts do not define queue serialization or queue publishing.
- Contracts define only the generic mail delivery failure code, not detailed provider or transport failure taxonomies.
- Runtime owners must implement delivery orchestration, configuration, credential resolution, provider adapters, diagnostics, and failure handling later.

## Rejected alternatives

### Put Symfony Mailer in contracts

Rejected.

Symfony Mailer is a possible implementation choice, not the contracts boundary.

Putting Symfony Mailer or Symfony Mime concrete APIs into `core/contracts` would make the contracts package depend on vendor implementation details and would make non-Symfony, in-memory, API-backed, queue-backed, or custom transports second-class.

### Put PSR-7 or HTTP request objects in contracts

Rejected.

PSR-7 and HTTP request objects would make mail contracts HTTP-specific and would leak transport concerns into non-HTTP runtimes.

Mail must be usable from HTTP, CLI, worker, scheduler, queue consumer, and custom runtime boundaries.

Transport-specific request adaptation belongs to runtime owners.

### Log recipient or body on failure

Rejected.

Recipients, subjects, bodies, headers, and provider payloads may contain sensitive data, credentials, private customer data, or high-cardinality values.

Failure diagnostics must use safe derivations such as counts, lengths, or owner-approved non-reversible hashes.

Raw recipient and body values must not be logged, printed, traced, emitted as metric labels, copied into `ErrorDescriptor.extensions`, or exposed through health or CLI output.

### Put provider-specific error codes in contracts

Rejected.

Provider-specific, transport-specific, queue-specific, credential-specific, and policy-specific mail failure codes are runtime-owned.

Putting detailed provider or backend failure codes into `core/contracts` would leak runtime classification policy into the stable contracts package and would make future transports less swappable.

The contracts package exposes only the generic deterministic mail delivery failure code:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

Runtime owners may map provider failures to more specific runtime-owned diagnostics only when those diagnostics obey the mail redaction policy.

### Put SMTP config in contracts

Rejected.

SMTP hostnames, ports, auth modes, TLS policy, usernames, passwords, DSNs, provider credentials, connection strings, and retry tuning are runtime configuration concerns.

The contracts package introduces no mail config roots, no config keys, no package config files, and no config rules.

Future runtime owners may introduce mail configuration through the config roots registry process.

### Put queue implementation in contracts

Rejected.

Queue publishing, serialization, encryption at rest, retry policy, dead-letter behavior, worker execution, queue transport selection, and queue failure handling are runtime-owned.

`MailMessage` is async-safe so a future queue owner can use it without changing the public contract shape, but `core/contracts` must not implement queue behavior.

## Non-goals

This ADR does not implement:

- concrete mailer implementation;
- concrete transport implementation;
- SMTP behavior;
- API provider behavior;
- queue publishing;
- queue consuming;
- queue serialization;
- retry policy;
- fail-open or fail-closed policy;
- template rendering;
- markdown rendering;
- HTML rendering;
- HTML sanitization;
- MIME generation;
- attachment handling;
- inline attachment handling;
- bounce handling;
- delivery receipt handling;
- provider webhooks;
- unsubscribe handling;
- address validation beyond structural safety;
- sender identity policy;
- mail config roots;
- mail config defaults;
- mail config rules;
- mail DI tags;
- generated artifacts;
- exception mapper;
- provider-specific mail error codes;
- transport-specific mail error codes;
- queue-specific mail error codes;
- credential-specific mail error codes;
- metrics schema;
- tracing schema;
- logging backend behavior;
- `platform/mail` implementation;
- `platform/queue` integration;
- `integrations/*` mail transport implementation.

## Related SSoT

- `docs/ssot/mail-contracts.md`
- `docs/ssot/observability.md`
- `docs/ssot/observability-and-errors.md`
- `docs/ssot/error-descriptor.md`
- `docs/ssot/dto-policy.md`
- `docs/ssot/config-roots.md`
- `docs/ssot/tags.md`
- `docs/ssot/artifacts.md`

## Related epic

- `1.170.0 Contracts: Mail port`
