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

# Mail Contracts SSoT

## Scope

This document is the Single Source of Truth for Coretsia mail contracts, mailer port semantics, transport port semantics, mail message shape policy, redaction invariants, async-safe message boundaries, and runtime ownership rules.

This document governs contracts introduced by epic `1.170.0` under:

```text
framework/packages/core/contracts/src/Mail/
```

The canonical mail contracts introduced by this epic are:

```text
Coretsia\Contracts\Mail\MailerInterface
Coretsia\Contracts\Mail\MailTransportInterface
Coretsia\Contracts\Mail\MailMessage
Coretsia\Contracts\Mail\MailException
```

The implementation paths are:

```text
framework/packages/core/contracts/src/Mail/MailerInterface.php
framework/packages/core/contracts/src/Mail/MailTransportInterface.php
framework/packages/core/contracts/src/Mail/MailMessage.php
framework/packages/core/contracts/src/Mail/MailException.php
```

It complements:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/dto-policy.md
docs/ssot/config-roots.md
docs/ssot/tags.md
docs/ssot/artifacts.md
```

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

Mail delivery must be expressible through stable contracts-level ports and safe message models so future runtime packages can send mail through swappable transports without changing application code.

The contracts introduced by this epic define only:

- a mailer port;
- a mail transport port;
- a safe mail message model;
- a deterministic safe mail exception boundary;
- a deterministic generic mail delivery error code for runtime error mapping;
- transport-swap safety between future SMTP, API-backed, in-memory, test, queue-backed, or external-service-backed implementations;
- hard redaction policy for recipients, subject, body, credentials, and transport diagnostics;
- async-safe message shape policy for future queueing without contract changes;
- dependency and ownership boundaries for future runtime packages.

The contracts package MUST NOT implement mail delivery behavior, SMTP behavior, API transport behavior, queue behavior, template rendering, MIME building, attachment streaming, credentials resolution, config loading, DI registration, transport discovery, generated artifacts, observability emission, or error mapping.

## Phase 0 lock-source alignment

This SSoT preserves the following Phase 0 invariants:

- `0.20.0` no-secrets output policy applies to mail recipients, message content, credentials, diagnostics, logs, spans, metrics, CLI output, health output, worker output, and transport errors.
- `0.60.0` missing vs empty MUST remain distinguishable when runtime owners build message metadata, policy inputs, or diagnostics.
- `0.70.0` json-like payloads forbid floats, including `NaN`, `INF`, and `-INF`.
- `0.70.0` lists preserve order and maps use deterministic key ordering when json-like metadata is exposed.
- `0.90.0` safe diagnostics MUST NOT expose raw values and MAY expose only safe derivations such as `hash(value)` or `len(value)`.

Epic `1.170.0` itself introduces no mail implementation, no SMTP implementation, no API transport implementation, no queue implementation, no config root, no generated artifact, and no DI tag.

## Contract boundary

Mail contracts are format-neutral and transport-neutral.

They define stable ports, value objects, deterministic exception semantics, and boundary policy only.

The contracts package MUST NOT implement:

- SMTP delivery;
- API-backed delivery;
- queue publishing;
- queue consuming;
- retry policy;
- template rendering;
- markdown rendering;
- HTML sanitization;
- MIME compilation;
- attachment reading;
- inline attachment reading;
- filesystem access;
- remote file access;
- credentials resolution;
- secret provider integration;
- DSN parsing;
- transport selection;
- transport discovery;
- logger integration;
- metrics integration;
- tracing integration;
- health checks;
- config defaults;
- config rules;
- DI registration;
- generated artifacts;
- package providers.

Runtime owner packages implement concrete behavior later.

## Contract package dependency policy

Mail contracts MUST remain dependency-free beyond PHP itself.

They MUST NOT depend on:

- `platform/*`
- `integrations/*`
- `Psr\Http\Message\*`
- `Psr\Http\Message\RequestInterface`
- `Psr\Http\Message\ResponseInterface`
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
- concrete service container implementations
- concrete logger implementations
- concrete tracing implementations
- concrete metrics implementations
- framework tooling packages
- generated architecture artifacts
- vendor-specific runtime clients

Runtime packages MAY depend on `core/contracts`.

`core/contracts` MUST NOT depend back on runtime packages.

Allowed direction:

```text
platform/mail → core/contracts
platform/queue → core/contracts
platform/http → core/contracts
integrations/* mail transports → core/contracts
application runtime packages → core/contracts
```

Forbidden direction:

```text
core/contracts → platform/mail
core/contracts → platform/queue
core/contracts → platform/http
core/contracts → integrations/*
```

## DTO terminology boundary

This document uses the terms `contract`, `port`, `value object`, `message`, `shape`, `transport model`, and `runtime boundary` according to:

```text
docs/ssot/dto-policy.md
```

`MailMessage` is an immutable contracts transport model.

It is not a DTO-marker class by default.

Mail interfaces introduced by epic `1.170.0` are contracts interfaces.

They are not DTO-marker classes.

DTO gates apply only to classes explicitly marked with:

```php
#[Coretsia\Dto\Attribute\Dto]
```

Mail contracts introduced by epic `1.170.0` MUST NOT be treated as DTOs unless a future owner epic explicitly opts them into DTO policy.

## Mail data sensitivity

Mail recipients, subject, body, headers, reply-to values, and transport credentials are sensitive by default.

Mail data MUST NOT be treated as public diagnostics.

Mail data MUST NOT be used as:

- metric labels;
- span name fragments;
- raw log messages;
- error descriptor extensions;
- health output fields;
- CLI output fields;
- generated artifact identities;
- public debug output;
- exception messages;
- transport names.

Raw mail recipients MUST NOT be logged, printed, traced, exported, rendered, or copied into diagnostics.

Raw mail subject MUST NOT be logged, printed, traced, exported, rendered, or copied into diagnostics.

Raw mail body MUST NOT be logged, printed, traced, exported, rendered, or copied into diagnostics.

Mail credentials MUST NOT appear in mail contracts.

Safe diagnostics MAY expose only derived information and stable generic machine codes such as:

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
CORETSIA_MAIL_DELIVERY_FAILED
```

Safe derivations MUST NOT expose raw recipients, raw subject, raw body, credentials, private customer data, or allow reconstruction of mail content.

## Mailer interface

`MailerInterface` is the canonical contracts-level application-facing mail port.

The implementation path is:

```text
framework/packages/core/contracts/src/Mail/MailerInterface.php
```

The canonical interface shape is:

```text
send(MailMessage $message): void
```

`send()` sends or schedules a mail message according to runtime owner policy.

The interface does not prescribe whether a future implementation sends synchronously, hands the message to a queue, delegates to a transport, or applies a policy-owned retry mechanism.

`send()` MUST accept only the contracts-level `MailMessage`.

`send()` MUST NOT expose transport-specific response objects.

`send()` MUST NOT expose raw recipients, subject, body, credentials, connection strings, provider responses, or vendor debug payloads through return values, exception messages, logs, metrics, spans, or diagnostics.

`send()` MAY throw `MailException` for contracts-level mail failure boundaries.

When a `MailException` is thrown, the exception MUST expose only the canonical generic mail delivery failure code and a fixed safe message. It MUST NOT expose transport-specific, provider-specific, queue-specific, credential-specific, recipient-specific, subject-specific, or body-specific details.

Concrete runtime implementations MAY throw separate owner-defined exceptions only when those exceptions obey the redaction policy defined by this SSoT.

`MailerInterface` MUST NOT expose:

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

## Mail transport interface

`MailTransportInterface` is the canonical contracts-level swappable mail transport port.

The implementation path is:

```text
framework/packages/core/contracts/src/Mail/MailTransportInterface.php
```

The transport port exists so runtime owners can swap backing implementations without changing public APIs.

Possible future transport implementations include:

```text
smtp
api
memory
null
log-safe
queue-backed
external-service-backed
```

Those implementations are runtime-owned and outside `core/contracts`.

The canonical interface shape is:

```text
name(): string
send(MailMessage $message): void
```

### Transport name

`name()` returns the stable safe transport implementation name.

The PHPDoc shape for `name()` output MUST be:

```php
@return non-empty-string
```

The returned name MUST be stable, safe, non-empty, and bounded-cardinality.

The returned name MAY be used by runtime owners as a bounded diagnostics value or as an allowed `driver` metric label value when observability policy allows it.

Examples of safe transport names:

```text
smtp
api
memory
null
queue
external
```

The returned name MUST NOT contain:

- recipients;
- subject;
- body;
- tenant ids;
- user ids;
- request ids;
- correlation ids;
- hostnames;
- DSNs;
- connection strings;
- credentials;
- tokens;
- usernames;
- passwords;
- API keys;
- provider message ids;
- environment-specific identifiers;
- backend object metadata.

`name()` MUST NOT imply transport discovery semantics.

`name()` MUST NOT introduce DI tag semantics.

`name()` MUST NOT introduce config roots, config keys, backend selection policy, or runtime wiring behavior.

### Transport send

`send()` sends the supplied `MailMessage` according to implementation-owned transport semantics.

`send()` returns `void`.

Implementations MUST NOT expose provider-specific objects or transport-specific response payloads through the contracts surface.

Implementations MUST NOT leak raw recipients, subject, body, headers, credentials, provider payloads, provider responses, or connection data in exceptions, logs, metrics, spans, health output, CLI output, or debug output.

`send()` MAY throw `MailException`.

When a transport reports failure through `MailException`, it MUST use the canonical generic mail delivery failure boundary only. Provider-specific, transport-specific, backend-specific, credential-specific, recipient-specific, subject-specific, and body-specific details MUST be redacted before creating or reporting the exception.

`send()` MUST NOT require callers to pass runtime policy arrays, config repositories, request contexts, queue contexts, service containers, or vendor transport objects.

Runtime owners adapt those concerns before calling the transport.

### Transport interface restrictions

`MailTransportInterface` MUST NOT expose:

- raw credentials;
- DSNs;
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

## Mail message model

`MailMessage` is the canonical immutable contracts model for a mail message passed across the contracts boundary.

The implementation path is:

```text
framework/packages/core/contracts/src/Mail/MailMessage.php
```

`MailMessage` MUST be immutable.

`MailMessage` MUST be final.

`MailMessage` MUST be readonly.

`MailMessage` MUST be outside DTO marker policy by default.

`MailMessage` MAY carry raw delivery data because transports require the actual message to send mail.

Raw delivery data MUST remain inside the runtime call path and MUST NOT become diagnostics or exported public safe shape.

`MailMessage` MUST NOT expose credentials, DSNs, transport configuration, provider configuration, service instances, runtime wiring objects, vendor message objects, MIME objects, streams, resources, closures, or filesystem handles.

### MailMessage logical fields

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

Field meanings:

| field           | type                | meaning                                                 |
|-----------------|---------------------|---------------------------------------------------------|
| `schemaVersion` | int                 | Stable mail message schema version.                     |
| `to`            | list<string>        | Primary recipient addresses.                            |
| `cc`            | list<string>        | Carbon-copy recipient addresses.                        |
| `bcc`           | list<string>        | Blind-carbon-copy recipient addresses.                  |
| `replyTo`       | list<string>        | Reply-to addresses.                                     |
| `subject`       | string              | Raw mail subject used for delivery.                     |
| `body`          | string              | Raw mail body used for delivery.                        |
| `headers`       | array<string,mixed> | Safe deterministic owner-provided header-like metadata. |
| `metadata`      | array<string,mixed> | Safe deterministic owner-provided runtime metadata.     |

The canonical mail message schema version is:

```text
1
```

`schemaVersion` MUST be a positive integer.

`to` MUST contain at least one recipient string.

`cc`, `bcc`, and `replyTo` MAY be empty lists.

`subject` MUST be a non-empty safe single-line string.

`body` MUST be a non-empty safe string.

`headers` MUST be a deterministic json-like map.

`metadata` MUST be a deterministic json-like map.

`MailMessage` MUST NOT use floats in headers or metadata.

### Recipient string policy

Recipient strings are sensitive delivery values.

Recipient strings MUST be validated exactly as supplied.

Recipient strings MUST NOT be trimmed, collapsed, lowercased, uppercased, canonicalized, normalized, parsed into vendor objects, or otherwise changed by `MailMessage`.

Each recipient string MUST be a non-empty safe single-line string.

Each recipient string MUST NOT contain:

- leading whitespace;
- trailing whitespace;
- CR;
- LF;
- NUL;
- unsafe control characters.

This contracts package does not define full email address syntax validation.

Strict email address validation, IDN policy, display-name support, provider-specific restrictions, and bounce handling are runtime-owned.

The contracts boundary validates structural safety only.

### Subject policy

`subject` is sensitive delivery content.

`subject` MUST be validated exactly as supplied.

`subject` MUST NOT be trimmed, collapsed, lowercased, uppercased, rewritten, encoded, decoded, MIME-encoded, or otherwise changed by `MailMessage`.

`subject` MUST be a non-empty safe single-line string.

`subject` MUST NOT contain:

- leading whitespace;
- trailing whitespace;
- CR;
- LF;
- NUL;
- unsafe control characters.

Runtime owners are responsible for transport-specific subject encoding.

### Body policy

`body` is sensitive delivery content.

`body` MUST be validated exactly as supplied.

`body` MUST NOT be trimmed, collapsed, lowercased, uppercased, rewritten, encoded, decoded, sanitized, rendered, or otherwise changed by `MailMessage`.

`body` MUST be a non-empty safe string.

`body` MAY contain ordinary whitespace, including spaces, tabs, CR, and LF, when those bytes are part of the message content.

`body` MUST NOT contain NUL or unsafe control characters.

HTML vs text body semantics are runtime-owned.

Template rendering is runtime-owned.

MIME multipart semantics are runtime-owned.

Attachment handling is runtime-owned and not introduced by this epic.

### Headers policy

`headers` is a deterministic json-like map.

Headers are safe owner-provided metadata only.

Headers MUST NOT contain raw credentials, tokens, cookies, authorization headers, provider credentials, DSNs, private keys, raw request headers, raw response headers, raw provider payloads, private customer data, service instances, vendor objects, streams, resources, closures, or filesystem handles.

Header map keys MUST be non-empty safe single-line strings.

Header map keys MUST NOT contain CR, LF, NUL, unsafe control characters, leading whitespace, or trailing whitespace.

Header string values MUST be safe strings.

Header string values MUST NOT contain NUL or unsafe control characters.

Headers MUST be normalized recursively using json-like metadata rules.

Header maps MUST be sorted recursively by byte-order `strcmp`.

Header lists MUST preserve list order.

### Metadata policy

`metadata` is a deterministic json-like map.

Metadata is safe owner-provided runtime metadata only.

Metadata MUST NOT contain raw recipients, raw subject, raw body, credentials, tokens, cookies, authorization headers, provider credentials, DSNs, private keys, raw request payloads, raw response payloads, raw provider payloads, private customer data, service instances, vendor objects, streams, resources, closures, or filesystem handles.

Metadata map keys MUST be non-empty safe single-line strings.

Metadata map keys MUST NOT contain CR, LF, NUL, unsafe control characters, leading whitespace, or trailing whitespace.

Metadata string values MUST be safe strings.

Metadata string values MUST NOT contain NUL or unsafe control characters.

Metadata MUST be normalized recursively using json-like metadata rules.

Metadata maps MUST be sorted recursively by byte-order `strcmp`.

Metadata lists MUST preserve list order.

## Json-like value model

Any json-like payload exposed by mail contracts MUST contain only:

- `null`
- `bool`
- `int`
- `string`
- list of allowed values
- map with string keys and allowed values

Floating-point values are forbidden.

The following values MUST NOT appear in exported json-like contract shapes:

- floats
- `NaN`
- `INF`
- `-INF`
- PHP objects
- closures
- resources
- streams
- filesystem handles
- service instances
- runtime wiring objects
- executable validators
- throwable instances
- vendor message objects
- vendor response objects
- transport clients
- MIME objects

If a future owner needs decimal values, they MUST be represented as strings with a documented format.

## Json-like container determinism

Lists preserve order.

Maps MUST be ordered deterministically by string key using byte-order `strcmp`.

Map ordering MUST be locale-independent.

Implementations that expose json-like maps MUST normalize map ordering recursively before export, serialization, rendering, or diagnostics.

An empty array is context-dependent:

- if the contract location requires a list, `[]` is treated as an empty list;
- if the contract location requires a map, `[]` is treated as an empty map at the semantic boundary;
- serialized PHP array representations may not preserve empty list vs empty map distinction.

Contracts MUST document the expected context for any ambiguous empty array field.

## MailMessage accessor shape

The canonical accessor shape is:

```text
schemaVersion(): int
to(): array
cc(): array
bcc(): array
replyTo(): array
subject(): string
body(): string
headers(): array
metadata(): array
toArray(): array
```

The PHPDoc return shape for `to()` MUST be:

```php
@return non-empty-list<non-empty-string>
```

The PHPDoc return shape for `cc()` MUST be:

```php
@return list<non-empty-string>
```

The PHPDoc return shape for `bcc()` MUST be:

```php
@return list<non-empty-string>
```

The PHPDoc return shape for `replyTo()` MUST be:

```php
@return list<non-empty-string>
```

The PHPDoc return shape for `subject()` MUST be:

```php
@return non-empty-string
```

The PHPDoc return shape for `body()` MUST be:

```php
@return non-empty-string
```

The PHPDoc return shape for `headers()` MUST be:

```php
@return array<string,mixed>
```

The PHPDoc return shape for `metadata()` MUST be:

```php
@return array<string,mixed>
```

Raw accessors are for runtime delivery only.

Runtime implementations MUST NOT log or print raw accessor values.

## MailMessage exported safe shape

`MailMessage::toArray()` MUST return a deterministic safe diagnostic shape.

`MailMessage::toArray()` MUST NOT expose raw recipients.

`MailMessage::toArray()` MUST NOT expose raw subject.

`MailMessage::toArray()` MUST NOT expose raw body.

`MailMessage::toArray()` MUST NOT expose credentials, DSNs, tokens, cookies, authorization headers, provider payloads, provider responses, private customer data, service instances, vendor objects, streams, resources, closures, or filesystem handles.

The canonical exported top-level key order is:

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

The canonical exported shape is:

```text
bccCount: int<0,max>
bodyLength: int<1,max>
ccCount: int<0,max>
headers: array<string,mixed>
metadata: array<string,mixed>
replyToCount: int<0,max>
schemaVersion: int
subjectLength: int<1,max>
toCount: int<1,max>
```

The exported `headers` value MUST be the safe normalized headers map.

The exported `metadata` value MUST be the safe normalized metadata map.

The exported count and length fields MUST be derived from raw message fields without exposing the raw values.

`subjectLength` and `bodyLength` MUST expose only byte length or owner-documented deterministic length semantics.

Length semantics MUST be deterministic and locale-independent.

The exported safe shape MUST be stable across operating systems, process locales, filesystem ordering, Composer package ordering, and repeated runs.

## Exact input validation policy

Mail contracts MUST validate textual input exactly as supplied unless this SSoT explicitly defines a canonical projection.

Mail contracts MUST NOT trim, collapse, lowercase, uppercase, normalize Unicode, remove whitespace, rewrite newlines, parse display names, canonicalize addresses, encode MIME data, or otherwise change textual input before validation.

The following fields MUST be validated exactly as supplied:

```text
recipient strings
subject
body
header keys
header string values
metadata keys
metadata string values
transport name
```

Allowed canonical projections are single-choice and must be documented by this SSoT.

Epic `1.170.0` defines no text canonicalization for mail message fields.

A value containing leading or trailing whitespace MUST be rejected when the field is defined as a safe single-line string.

A value containing unsafe control characters MUST be rejected.

## Safe string policy

A safe string MUST NOT contain NUL or unsafe control characters.

A safe single-line string MUST also not contain CR or LF.

Fields that use safe single-line strings MUST reject leading and trailing whitespace.

Fields that use safe strings MAY contain ordinary spaces and line breaks only when explicitly allowed by this SSoT.

The mail body is a safe string, not a safe single-line string.

The mail body MAY contain ordinary CR and LF when those bytes are part of the body content.

## Mail exception

`MailException` is the canonical contracts-level exception for mail sending failures.

The implementation path is:

```text
framework/packages/core/contracts/src/Mail/MailException.php
```

`MailException` MUST use this deterministic generic mail delivery error code:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The deterministic mail delivery error code MUST be exposed as:

```text
MailException::CODE
```

The exception SHOULD expose this accessor:

```text
errorCode(): string
```

`errorCode()` MUST return:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The PHP native exception integer code returned by `Throwable::getCode()` is not the canonical mail delivery error code.

The canonical mail delivery error code is the stable string code defined by `MailException::CODE`.

This avoids coupling the contracts-level machine code to PHP native integer exception code semantics.

`MailException` SHOULD extend `RuntimeException`.

`MailException` MUST be payload-free.

Payload-free means `MailException` MUST NOT carry mail-specific data fields, provider payload fields, diagnostic extension fields, recipient fields, subject fields, body fields, credential fields, DSN fields, or runtime-owned classification fields.

The optional native `$previous` exception chain is allowed only under the safe-redacted exception chaining rule defined by the contract surface restrictions section.

`MailException` MUST NOT contain recipients, subject, body, headers, credentials, DSNs, provider payloads, provider responses, request payloads, response payloads, private customer data, service instances, vendor objects, streams, resources, closures, or filesystem handles.

The exception message MUST be fixed, safe, and generic.

The canonical exception message is:

```text
Mail delivery failed.
```

The deterministic mail delivery message MUST be exposed as:

```text
MailException::MESSAGE
```

`MailException` messages MUST NOT include:

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

Concrete runtime implementations MAY wrap provider exceptions.

When wrapping provider exceptions, implementations MUST redact unsafe provider data before creating or reporting a `MailException`.

Runtime owner packages MAY define more specific platform-owned or integration-owned mail failure codes later.

Those owner-defined codes MUST NOT replace the generic contracts-level code defined by `MailException::CODE`.

Provider-specific, transport-specific, queue-specific, credential-specific, and policy-specific mail failure codes are runtime-owned and MUST NOT be introduced by epic `1.170.0`.

If mail errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

The contracts package MUST NOT implement mail exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside mail contracts.

## Transport-swap safety

Mail transport implementations MUST be swappable without public API changes.

The contract surface MUST NOT encode:

- SMTP hostnames;
- SMTP ports;
- SMTP auth modes;
- API endpoint URLs;
- provider names as required enum values;
- provider message ids;
- provider response objects;
- queue names;
- retry strategy names;
- DSN syntax;
- credential names;
- attachment storage implementation details;
- MIME library objects;
- vendor-specific exception classes.

A future in-memory implementation, SMTP implementation, API-backed implementation, and queue-backed implementation MUST all be able to implement the same `MailTransportInterface`.

Differences in exact delivery semantics, retry behavior, provider response handling, queue behavior, and failure policy are implementation-owned and MUST be documented by the runtime owner.

The contracts package defines only the stable port and safe message model.

## Async-safe shape policy

`MailMessage` MUST be safe to hand to a future queueing layer without changing the public contract shape.

Async-safe means:

- the message model is immutable;
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

Queue publishing, queue serialization, encryption at rest, retry policy, dead-letter policy, and worker execution are runtime-owned.

If a future queue owner serializes `MailMessage`, that owner MUST preserve the redaction policy and MUST NOT log serialized message bodies or recipients.

## Runtime ownership policy

Mail runtime behavior is expected to be owned by future runtime packages.

`platform/mail` is expected to implement mail orchestration using these contracts.

Transport implementations are expected to live in owner packages outside `core/contracts`, including future packages under:

```text
integrations/*
```

Possible future responsibilities of `platform/mail` include:

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

This is documented runtime policy only.

Epic `1.170.0` does not create or modify `platform/mail`.

## Credentials and secrets policy

Mail credentials are runtime-owned.

Mail contracts MUST NOT expose or require credentials.

Mail contracts MUST NOT contain:

- usernames;
- passwords;
- API keys;
- private keys;
- OAuth tokens;
- bearer tokens;
- SMTP credentials;
- provider secrets;
- DSNs;
- connection strings;
- raw environment values;
- secret names that reveal environment-specific implementation details.

Credentials and secrets MUST be resolved by runtime owner packages through an owner-approved secret mechanism.

Credentials and secrets MUST never be printed, logged, traced, exported, rendered, copied into error descriptor extensions, or exposed through health or CLI output.

## Config policy

Epic `1.170.0` introduces no config roots and no config keys.

The contracts package MUST NOT require package config files for mail contracts.

No files under package `config/` are introduced by this epic.

Possible future runtime config paths may include:

```text
mail.default_transport
mail.transports
mail.queue.enabled
```

These paths are documented here only as future runtime policy context.

They are not config roots or config keys introduced by `core/contracts`.

Transport selection, sender defaults, retry policy, queue policy, template policy, provider credentials, and backend configuration are runtime configuration concerns.

Future runtime owner packages MAY introduce mail config only through their own owner epics and the config roots registry process.

## DI tag policy

Epic `1.170.0` introduces no DI tags.

The contracts package MUST NOT declare public mail tag constants.

The contracts package MUST NOT define package-local mirror constants for mail tags.

The contracts package MUST NOT define mail tag metadata keys, mailer discovery semantics, transport discovery semantics, priority semantics, or policy discovery semantics.

If a future runtime owner needs mail DI tags, that owner MUST introduce them through:

```text
docs/ssot/tags.md
```

according to tag registry rules.

## Artifact policy

Epic `1.170.0` introduces no artifacts.

The contracts package MUST NOT generate:

- mail artifacts;
- mail transport artifacts;
- mail policy artifacts;
- mail template artifacts;
- mail queue artifacts;
- mail provider artifacts;
- mail routing artifacts;
- runtime lifecycle artifacts.

A future runtime owner MAY introduce generated mail artifacts only through its own owner epic and the artifact registry process.

Any future artifact MUST follow:

```text
docs/ssot/artifacts.md
```

## Observability and diagnostics policy

Mail contracts do not define observability signals.

Runtime implementations MAY emit logs, spans, metrics, or profiling signals around mail delivery only when those signals follow:

```text
docs/ssot/observability.md
docs/ssot/observability-and-errors.md
docs/ssot/error-descriptor.md
docs/ssot/profiling-ports.md
```

Metric labels MUST remain within the canonical allowlist from:

```text
docs/ssot/observability.md
```

Epic `1.170.0` introduces no new metric label keys.

The baseline allowed metric label keys are:

```text
method
status
driver
operation
table
outcome
```

For mail, runtime owners MAY use only safe, bounded-cardinality values for allowed labels.

Reasonable safe label usage MAY include:

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

only when these values are safe, stable, bounded, and owner-approved.

Mail implementations MUST NOT use the following as metric labels:

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
- policy value that is user-controlled or high-cardinality.

The label keys `request_id`, `correlation_id`, `tenant_id`, and `user_id` remain forbidden under the baseline policy.

Recipient-domain labels are not introduced by this epic.

If a future owner wants recipient-domain aggregation, that owner MUST update the observability SSoT explicitly and prove bounded-cardinality and privacy safety.

### Safe diagnostics

Safe implementation diagnostics MAY expose:

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

only when values are safe for the target output and do not create high-cardinality leakage.

Runtime owners MUST prefer omission over unsafe emission.

Safe derivations MUST NOT expose raw recipients, subject, body, credentials, provider payloads, or allow reconstruction of sensitive values.

## Error and exception policy

Mail failure handling is implementation-owned.

`MailException` is the contracts-level deterministic safe exception boundary.

`MailException` exposes the canonical generic mail delivery failure code:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The canonical code is exposed as:

```text
MailException::CODE
```

The canonical safe message is exposed as:

```text
MailException::MESSAGE
```

The canonical accessor is:

```text
errorCode(): string
```

`errorCode()` returns:

```text
CORETSIA_MAIL_DELIVERY_FAILED
```

The PHP native exception integer code returned by `Throwable::getCode()` is not the canonical mail delivery error code.

Concrete implementations MAY throw owner-defined exceptions.

Owner-defined exceptions and diagnostics MUST NOT expose:

- raw recipients;
- raw subject;
- raw body;
- raw headers;
- credentials;
- tokens;
- API keys;
- passwords;
- private keys;
- DSNs;
- provider request payloads;
- provider response payloads;
- provider object dumps;
- hostnames when environment-specific;
- connection strings;
- tenant ids;
- user ids;
- request ids;
- correlation ids;
- raw paths;
- raw queries;
- cookies;
- private customer data;
- absolute local paths;
- stack traces by default.

If mail errors are normalized later, mapping to `ErrorDescriptor` is owned by runtime error mapping packages.

A future runtime mapper SHOULD map `MailException` to an `ErrorDescriptor` using:

```text
code: CORETSIA_MAIL_DELIVERY_FAILED
message: Mail delivery failed.
```

HTTP status selection, problem-details rendering, CLI rendering, worker failure rendering, mapper discovery, mapper ordering, fallback policy, and provider-specific failure classification are runtime-owned.

Mapper-owned `ErrorDescriptor.extensions` MAY include safe mail metadata such as recipient counts, subject length, body length, operation, outcome, or driver.

Mapper-owned extensions MUST NOT include raw recipients, raw subject, raw body, raw headers, credentials, provider payloads, provider responses, DSNs, tokens, cookies, private customer data, absolute local paths, request ids, correlation ids, tenant ids, or user ids.

Mapper-owned extensions MUST obey the `ErrorDescriptor.extensions` json-like and redaction policy.

The contracts package MUST NOT implement mail exception mapping.

The contracts package MUST NOT require `ErrorDescriptor` construction inside mail contracts.

## Contract surface restrictions

Mail public method signatures MUST NOT contain:

- `Psr\Http\Message\*`;
- `Psr\Http\Message\RequestInterface`;
- `Psr\Http\Message\ResponseInterface`;
- `Coretsia\Platform\*`;
- `Coretsia\Integrations\*`;
- Symfony Mailer concrete classes;
- Symfony Mime concrete classes;
- PHPMailer concrete classes;
- SwiftMailer concrete classes;
- SMTP concrete classes;
- HTTP client concrete classes;
- queue concrete classes;
- vendor clients;
- vendor command objects;
- provider response objects;
- resources;
- closures;
- streams;
- iterators;
- generators;
- concrete service container objects;
- runtime wiring objects.

Mail public method signatures introduced by this SSoT MUST use only:

- PHP built-in scalar/array/null/void types;
- contracts-level mail types introduced by this epic.

Exception constructors MAY accept `?\Throwable $previous` solely for native PHP exception chaining.

Runtime owners MUST NOT pass unsafe provider, transport, queue, credential, recipient, subject, body, or backend exceptions as `$previous` unless those exceptions have already been redacted and are safe to retain.

`?\Throwable $previous` does not introduce runtime ownership, provider ownership, transport ownership, or error mapping ownership into `core/contracts`.

The only mail-specific object types allowed in public method signatures introduced by this SSoT are:

```text
Coretsia\Contracts\Mail\MailMessage
Coretsia\Contracts\Mail\MailException
```

`MailException` MAY appear in PHPDoc `@throws`.

`MailException::CODE`, `MailException::MESSAGE`, and `MailException::errorCode()` are allowed as deterministic contracts-level exception semantics.

Mail contracts MUST NOT introduce provider-specific, transport-specific, queue-specific, credential-specific, tenant-specific, user-specific, request-specific, or correlation-specific error code constants in epic `1.170.0`.

## What this epic MUST NOT create

Epic `1.170.0` MUST NOT create:

```text
framework/packages/platform/mail/*
framework/packages/platform/queue/*
framework/packages/platform/http/*
framework/packages/integrations/*
config/*.php
provider/module wiring files
mail transport implementation
SMTP transport implementation
API transport implementation
queue mail implementation
mail template renderer
mail policy loader
mail transport registry
mail exception mapper
mail health check implementation
DI tag constants
artifact files
```

These are runtime-owned concerns for future owner epics.

## Acceptance scenario

When a future runtime package sends mail:

1. application code constructs a `MailMessage`;
2. the message preserves recipients, subject, body, headers, and metadata exactly as supplied after structural validation;
3. the message is passed to `MailerInterface::send()`;
4. the runtime-owned mailer selects or delegates to a runtime-owned transport;
5. the transport uses raw message accessors only for delivery;
6. diagnostics expose only safe counts, lengths, hashes, operation, outcome, and driver values;
7. recipients, subject, body, credentials, provider payloads, and provider responses are not logged, traced, emitted as metric labels, copied into error descriptor extensions, printed to CLI output, or exposed in health output;
8. provider-specific failures are redacted before being reported through `MailException` or runtime-owned error reporting;
9. `MailException` exposes deterministic generic code `CORETSIA_MAIL_DELIVERY_FAILED` and fixed safe message `Mail delivery failed.`;
10. runtime mappers MAY map `MailException` to normalized errors without requiring `core/contracts` to construct or own `ErrorDescriptor`.

## Verification evidence

Contracts-level enforcement evidence for this epic includes:

```text
framework/packages/core/contracts/tests/Contract/MailContractsShapeContractTest.php
```

This test is expected to verify:

- `MailerInterface` exists and exposes the canonical application-facing send method;
- `MailTransportInterface` exists and exposes the canonical safe transport name and send methods;
- `MailMessage` is final, readonly, immutable, and outside DTO marker policy by default;
- `MailMessage` preserves raw delivery data for runtime delivery accessors only;
- `MailMessage::toArray()` exposes only deterministic redacted safe shape data;
- `MailMessage::toArray()` does not expose raw recipients, raw subject, or raw body;
- `MailMessage` headers and metadata are json-like, float-free, safe-only, and deterministic;
- `MailException` exposes deterministic code `CORETSIA_MAIL_DELIVERY_FAILED`;
- `MailException` exposes fixed safe message `Mail delivery failed.`;
- `MailException` is payload-free and does not require `ErrorDescriptor`;
- mail contracts do not depend on platform packages;
- mail contracts do not depend on integration packages;
- mail contracts do not depend on `Psr\Http\Message\*`.

Architecture gates are expected to verify that `core/contracts` does not introduce forbidden compile-time dependencies.

## Non-goals

This SSoT does not define:

- concrete mailer implementation;
- concrete transport implementation;
- SMTP behavior;
- API provider behavior;
- queue publishing;
- queue consuming;
- retry policy;
- fail-open or fail-closed policy;
- template rendering;
- markdown rendering;
- HTML rendering;
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
- exception mapping;
- provider-specific mail error codes;
- transport-specific mail error codes;
- queue-specific mail error codes;
- credential-specific mail error codes;
- metrics schema;
- tracing schema;
- logging backend behavior;
- `platform/mail` implementation;
- `integrations/*` mail transport implementation.

## Cross-references

- [SSoT Index](./INDEX.md)
- [Observability Naming and Labels Allowlist](./observability.md)
- [Observability and Errors SSoT](./observability-and-errors.md)
- [ErrorDescriptor SSoT](./error-descriptor.md)
- [DTO Policy](./dto-policy.md)
- [Config Roots Registry](./config-roots.md)
- [Tag Registry](./tags.md)
- [Artifact Header and Schema Registry](./artifacts.md)
- [Phase 1 — Core roadmap](../roadmap/PHASE-1—CORE.md)
