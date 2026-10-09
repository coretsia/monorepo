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

# Sensitive Data Redaction SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/redaction
```

## Scope

This document is the Single Source of Truth for the Coretsia deterministic, config-free sensitive-data redaction port and default platform implementation: public contracts, immutable summary shapes, canonical structural-key and whole-value classification, traversal, fixed resource limits, summary byte materialization, domain-separated hashing, safe failures, and consumer ownership.

The canonical contract and implementation locations are:

```text
packages/core/contracts/src/Security/
packages/core/contracts/src/Security/Exception/RedactionException.php
packages/platform/redaction/src/Module/RedactionModule.php
packages/platform/redaction/src/Provider/RedactionServiceProvider.php
packages/platform/redaction/src/Redaction/
```

Only `SensitiveDataRedactorInterface` is the cross-package redaction port. The concrete redactor, key/value classifiers, and hashing helper are package-internal implementation services; their public PHP methods do not establish additional cross-package extension points.

## Normative language

The words MUST, MUST NOT, SHOULD, SHOULD NOT, and MAY are normative.

## Goal

The shared boundary redacts strings whose owners already know they are sensitive and recursively processes json-like diagnostic/output values under one canonical conservative classification policy. It is defense in depth and never authorizes the emission of otherwise unsafe source data.

## Authority boundary (MUST)

- `core/contracts` owns `SensitiveDataRedactorInterface`, `RedactionContext`, `RedactionKind`, `RedactionMode`, `RedactedValue`, and `RedactionException` as implementation-neutral contracts.
- `platform.redaction` owns the shared generic semantic redaction policy, the immutable classifier vocabulary, traversal, mode application, and stable summary hashing. `DefaultSensitiveDataRedactor` is its default implementation.
- `core/foundation` owns json-like shape validity, type/map-key rejection, canonical recursive normalization, structural resource-budget accounting, and stable JSON framing. `platform/redaction` MUST NOT duplicate those responsibilities.
- Eligible cross-package consumers depend on `SensitiveDataRedactorInterface` only. `SensitiveKeyClassifier`, `SensitiveValueClassifier`, and `StableRedactionHasher` remain package-internal and MUST NOT be treated as generic extension APIs.
- Producer/consumer owners retain their own safe-by-construction shapes, semantic-key rules, sink-schema allowlists, omission rules, path constraints, cardinality and resource budgets, and safe derivations. A redacted summary is not automatic permission to emit a value.
- Lower-layer Core owners MUST NOT introduce an upward dependency on `platform/redaction` to implement their existing boundary-specific validation, rejection, omission, or safe derivation. `core/kernel` Kernel Ops results remain safe by construction and independent of the redaction port.
- A distinct `platform/security` capability MUST NOT implement a competing generic semantic redaction engine.

## Canonical contracts (MUST)

### Redaction port

```php
namespace Coretsia\Contracts\Security;

interface SensitiveDataRedactorInterface
{
    public function redactValue(
        string $value,
        RedactionKind $kind,
        RedactionContext $context,
    ): RedactedValue;

    /**
     * @return null|bool|int|string|array<int|string, mixed>
     */
    public function redactJsonLike(mixed $value, RedactionContext $context): mixed;
}
```

Both methods declare `@throws \Coretsia\Contracts\Security\Exception\RedactionException` in their contracts. `redactValue()` requires an explicit owner-selected kind, including `RedactionKind::Unknown` when that is the correct owner choice. `redactJsonLike()` classifies only by this document's canonical key and value rules, without caller-defined callbacks, policy maps, registry inputs, or runtime context.

The json-like return model is recursively `null|bool|int|string|array`, where arrays are ordered lists or string-keyed maps. Float, object (including closures), resource, and non-string map-key input is rejected by Foundation; it is not coerced, stringified, reflected for payload content, or passed to `__toString()`.

### RedactionKind

The string-backed `Coretsia\Contracts\Security\RedactionKind` enum contains **exactly**:

| Case              | String value       |
|-------------------|--------------------|
| `Unknown`         | `unknown`          |
| `Secret`          | `secret`           |
| `SecretReference` | `secret-reference` |
| `Credential`      | `credential`       |
| `Authorization`   | `authorization`    |
| `Cookie`          | `cookie`           |
| `SessionId`       | `session-id`       |
| `Token`           | `token`            |
| `Payload`         | `payload`          |
| `Sql`             | `sql`              |
| `Pii`             | `pii`              |
| `EnvValue`        | `env-value`        |
| `LocalPath`       | `local-path`       |

The enum contains identifiers only; no classifier or disclosure-policy logic belongs in the contracts enum.

### RedactionMode

The string-backed `Coretsia\Contracts\Security\RedactionMode` enum contains **exactly**:

| Case            | String value      | Allowed summary metadata |
|-----------------|-------------------|--------------------------|
| `Placeholder`   | `placeholder`     | neither length nor hash  |
| `Length`        | `length`          | byte length only         |
| `Hash`          | `hash`            | hash only                |
| `HashAndLength` | `hash-and-length` | both length and hash     |

`Placeholder` is the default. Other modes require the owner's explicit selection through `RedactionContext`. `raw`, `none`, `disabled`, `passthrough`, `debug`, and implicit environment/debug modes do not exist.

### RedactionContext

`Coretsia\Contracts\Security\RedactionContext` is a `final readonly` value object with `public const int SCHEMA_VERSION = 1`, constructed as:

```php
public function __construct(
    string $scope,
    RedactionMode $mode = RedactionMode::Placeholder,
)
```

Exact accessors are `schemaVersion(): int`, `scope(): string`, and `mode(): RedactionMode`. The scope MUST be a stable operation/output-boundary identifier, not runtime user/tenant/request-derived data. Its exact validation law is:

```text
\A[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*\z
maximum byte length: 128 (strlen)
```

Valid examples: `cli.output`, `logging.record`, `http.problem-detail`. An empty scope, uppercase letter, Unicode character, ASCII C0 byte, DEL, multiline value, NUL, ESC, or any byte-disallowed/overlong value fails with exactly `InvalidArgumentException('redaction-context-scope-invalid')`. The class performs only byte/syntax validation, not semantic detection of high-cardinality identifiers. Owners MUST NOT derive scope from paths, endpoints, tokens, field values, user/tenant identifiers, request/correlation IDs, or other high-cardinality runtime values. `RedactionContext` is not a runtime `ContextStore` or `ContextAccessorInterface`.

### RedactedValue

`Coretsia\Contracts\Security\RedactedValue` is a `final readonly` value object with `public const int SCHEMA_VERSION = 1` and constructor:

```php
public function __construct(
    RedactionKind $kind,
    RedactionMode $mode,
    ?int $length,
    ?string $hash,
)
```

Exact accessors: `schemaVersion(): int`, `kind(): RedactionKind`, `mode(): RedactionMode`, `length(): ?int`, `hash(): ?string`, and `toArray(): array`. `toArray()` returns **exactly** the following six `strcmp`-ordered keys, with no other fields:

```php
[
    'hash' => $hash,
    'kind' => $kind->value,
    'length' => $length,
    'mode' => $mode->value,
    'redacted' => true,
    'schemaVersion' => 1,
]
```

The PHPDoc return shape is `array{hash: ?string, kind: string, length: ?int, mode: string, redacted: true, schemaVersion: 1}`. Invariant combinations are:

| Mode              | `length`           | `hash`            |
|-------------------|--------------------|-------------------|
| `placeholder`     | `null`             | `null`            |
| `length`          | non-negative `int` | `null`            |
| `hash`            | `null`             | valid hash string |
| `hash-and-length` | non-negative `int` | valid hash string |

A valid hash matches exactly `\Asha256:[a-f0-9]{64}\z`. An invalid mode/metadata combination, negative length, or malformed hash throws exactly `InvalidArgumentException('redacted-value-shape-invalid')`, without embedding any constructor inputs. This object MUST NOT retain the raw value, bytes, context, path, origin, previous Throwable, preview, prefix, suffix, or masked original.

### RedactionException

`Coretsia\Contracts\Security\Exception\RedactionException` is `final`, extends `RuntimeException`, and is the **only** public redaction failure type. Its constructor is `private`; it stores only a private readonly allowlisted reason, never accepts/retains a previous Throwable, and is instantiated only by the following exact named constructors:

| Named constructor            | Public typed reason constant  | Reason value           |
|------------------------------|-------------------------------|------------------------|
| `inputInvalid(): self`       | `REASON_INPUT_INVALID`        | `input-invalid`        |
| `inputLimitExceeded(): self` | `REASON_INPUT_LIMIT_EXCEEDED` | `input-limit-exceeded` |
| `sensitiveMapKey(): self`    | `REASON_SENSITIVE_MAP_KEY`    | `sensitive-map-key`    |
| `outputInvalid(): self`      | `REASON_OUTPUT_INVALID`       | `output-invalid`       |
| `internalFailure(): self`    | `REASON_INTERNAL_FAILURE`     | `internal-failure`     |

`public const string ERROR_CODE = 'CORETSIA_REDACTION_FAILED'`. The other five reason constants are `public const string` with exactly the values above; there are no additional allowed reasons. Accessors: `errorCode(): string` returns `CORETSIA_REDACTION_FAILED`, and `reason(): string` returns the selected allowlisted reason. The message is exactly:

```text
CORETSIA_REDACTION_FAILED: <reason>
```

It MUST contain no rejected/selected value, key, scope, hash, length, path, pattern, class name, resource ID, environment detail, or payload fragment. The implementation does not redefine native PHP `Throwable` stack-trace storage or promise stack-trace sanitization; consumer error/observability sinks MUST NOT export raw stack traces under their own Core safety policy.

## Explicit module and DI boundary (MUST)

`RedactionModule` publishes `MODULE_ID = 'platform.redaction'`, `PACKAGE_ID = 'platform/redaction'`, `COMPOSER_PACKAGE = 'coretsia/platform-redaction'`, `KIND = 'runtime'`, and one provider `RedactionServiceProvider::class`. Composer `extra.coretsia` metadata declares a runtime module requiring `core.foundation`, with `conflicts = []` and no `defaultsConfigPath`.

The provider implements both Foundation `ServiceProviderInterface` and `ContainerDefinitionProviderInterface`. `define()` contributes, in exact order, `SensitiveKeyClassifier`, `SensitiveValueClassifier`, `StableRedactionHasher`, and `DefaultSensitiveDataRedactor` with exactly three typed `ContainerValueReference::service()` arguments in that order, then aliases `SensitiveDataRedactorInterface` to the default implementation. `register()` delegates to canonical Foundation declarative definitions and does not resolve services. All concrete implementation services are shared and stateless; alias resolution preserves the shared concrete target's identity. No config parameter, tag, closure/runtime object in definitions, or service locator is introduced.

Package/autoload presence and `RedactionModule` construction alone MUST NOT register services or enable the module. Explicit provider application is required; module planning remains Kernel-owned. No config, debug flag, environment, availability probe, or Composer-presence side effect may apply the provider.

## Canonical structural-key classification (MUST)

### Key canonicalization

`SensitiveKeyClassifier::classify(string $key): ?RedactionKind` constructs a **temporary** lookup key using the following exact steps:

1. Remove only ASCII hyphen (`-`), underscore (`_`), full stop (`.`), and ASCII space (`0x20`) bytes throughout the original structural key.
2. Fold only ASCII `A..Z` to `a..z`, without locale-dependent conversion and without Unicode normalization or transliteration.
3. Look up the resulting byte string in the exact immutable alias table below. Return its documented `RedactionKind` or `null` for an unknown alias.

The original structural key's bytes are never rewritten in the output map. Tabs, line breaks, non-ASCII separators, and other characters are not accepted as substitute separators. Classification uses no config, env, mutable regex file, runtime registry, or learned state.

### Immutable alias table

The canonical vocabulary consists of **exactly 98 aliases in 12 groups**. Alias values below are already canonicalized; no entry may be added solely because another boundary forbids a diagnostic field.

| `RedactionKind` value | Canonical aliases (exact, `\|`-delimited)                                                                                                                                                                                                                                                                                                                                                                                       |
|-----------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `secret-reference`    | `secretref\|secretreference\|keyref`                                                                                                                                                                                                                                                                                                                                                                                            |
| `secret`              | `secret\|secrets\|secretvalue\|password\|passwords\|passwd\|pwd\|clientsecret\|privatekey\|privatekeys\|secretkey`                                                                                                                                                                                                                                                                                                              |
| `credential`          | `credential\|credentials\|dsn\|connectionstring`                                                                                                                                                                                                                                                                                                                                                                                |
| `authorization`       | `authorization\|authorizationdata\|authorizationheader\|auth\|authdata\|proxyauthorization\|proxyauthorizationheader`                                                                                                                                                                                                                                                                                                           |
| `cookie`              | `cookie\|cookies\|setcookie`                                                                                                                                                                                                                                                                                                                                                                                                    |
| `session-id`          | `session\|sessionid\|sessionidentifier\|sessionidentifiers`                                                                                                                                                                                                                                                                                                                                                                     |
| `token`               | `token\|tokens\|accesstoken\|refreshtoken\|idtoken\|bearertoken\|apikey\|xapikey\|accesskey\|csrf\|csrftoken\|xsrf\|xsrftoken`                                                                                                                                                                                                                                                                                                  |
| `payload`             | `payload\|rawpayload\|body\|rawbody\|header\|headers\|rawheader\|rawheaders\|query\|rawquery\|querystring\|requestheader\|requestheaders\|rawrequestheader\|rawrequestheaders\|responseheader\|responseheaders\|rawresponseheader\|rawresponseheaders\|requestbody\|rawrequestbody\|requestpayload\|rawrequestpayload\|responsebody\|rawresponsebody\|responsepayload\|rawresponsepayload\|providerpayload\|rawproviderpayload` |
| `sql`                 | `sql\|rawsql\|sqlquery\|rawsqlquery\|sqlbindings\|sqlstatement`                                                                                                                                                                                                                                                                                                                                                                 |
| `pii`                 | `email\|emailaddress\|phone\|phonenumber\|username\|firstname\|lastname\|fullname\|dateofbirth\|birthdate\|dob`                                                                                                                                                                                                                                                                                                                 |
| `env-value`           | `envvalue\|rawenvvalue`                                                                                                                                                                                                                                                                                                                                                                                                         |
| `local-path`          | `localpath\|filepath\|absolutepath\|workingdirectory\|cwd`                                                                                                                                                                                                                                                                                                                                                                      |

### Structural ambiguities and owner boundaries

- `query`, `rawquery`, and `querystring` map to `RedactionKind::Payload` as opaque transport/query channels, **not** `Sql`. Raw header collections (`header|headers|rawheader|rawheaders` and request/response header variants) are complete `payload` branches; their protection does not rely on discovering nested authorization or cookie values.
- SQL is structural or owner-explicit only. `sql|rawsql|sqlquery|rawsqlquery|sqlbindings|sqlstatement` map to `RedactionKind::Sql`. An owner-known sensitive SQL scalar is passed to `redactValue($value, RedactionKind::Sql, $context)`. Unkeyed SQL-shaped text is not auto-classified by `SensitiveValueClassifier`.
- Generic `statement` remains `null` because it is not necessarily SQL; the SQL-specific key is `sql_statement`. Generic `bindings` remains `null` because it is also used for Foundation DI/container/config semantics; SQL-specific bindings use `sql_bindings`. A structural key alone must establish the corresponding shared semantic kind.
- Owner-specific forbidden identifiers such as `authidentifier`, `authidentifiers`, `userid`, `tenantid`, `requestid`, `correlationid`, `customer`, `customerdata`, and `privatecustomerdata` are **not** assigned a shared kind merely because `ErrorDescriptor`, Kernel UoW, observability, or another lower-layer owner prohibits them. Corresponding variants such as `auth_identifier`, `user_id`, `tenant_id`, `request_id`, and `correlation_id` also remain unclassified.
- Generic `address`, `path`, `rawpath`, `directory` remain `null`; a structural key alone does not establish PII or local-filesystem semantics. `raw_path` follows the same rule. An owner-known raw path may use `RedactionKind::LocalPath` explicitly.
- Generic `env`, `environment`, `dotenv` remain `null`. Existing Core environment-variable **names** and provenance/source vocabulary are not automatically raw env values. Only `envvalue|rawenvvalue` classify as `RedactionKind::EnvValue`. Owners may explicitly redact raw environment values with `RedactionKind::EnvValue`.
- For an owner-known sensitive value without a canonical shared key, omission or explicit `redactValue()` with `RedactionKind::Unknown` or another semantically correct kind is required. Destination-specific rejection remains authoritative. A classifier returning `null` never means the original may be emitted.

## Canonical whole-value classification (MUST)

### Scope and precedence

`SensitiveValueClassifier::classify(string $value): ?RedactionKind` applies deterministic high-confidence grammars in **exactly** this order, stopping at the first match:

1. `authorization`
2. `cookie`
3. `credential`
4. `token`
5. `local-path`
6. `pii`

Each grammar matches the **entire** classification candidate, not a substring. For every class except `local-path`, the candidate is the exact input string. `local-path` alone may use a temporary candidate with leading ASCII space/tab bytes removed; original value bytes are never changed. Syntax tokens, case folding, separators, and constrained character classes are ASCII-defined. Opaque remainders are not decoded, Unicode-normalized, semantically interpreted, or subjected to undocumented restrictions beyond the grammar-specific exclusions.

Unkeyed values are **never** automatically classified as `secret`, `secret-reference`, `session-id`, `payload`, `sql`, or `env-value`. Those kinds require an unambiguous sensitive structural key or the owner's explicit `redactValue()` classification. `null` is a non-match, not declassification.

### Authorization grammar

Two complete-value forms yield `RedactionKind::Authorization`:

1. Direct: ASCII case-insensitive `Bearer`, then **one or more** ASCII spaces/tabs, then a **non-empty** remainder containing no CR (`0x0D`) or LF (`0x0A`).
2. Header-like: ASCII case-insensitive `Authorization:` or `Proxy-Authorization:`, then **zero or more** ASCII spaces/tabs, then a **non-empty** remainder containing no CR/LF. The header-like form does not parse or restrict the authorization scheme; for example, a complete `Authorization: Basic synthetic-value` or `Authorization: Digest synthetic-value` matches.

A standalone unkeyed `Basic ...` string (for example `Basic plan` or `Basic configuration`) is not automatically authorization. An owner-known Basic authorization value uses the complete header-like grammar, an authorization structural key, or explicit `redactValue(..., RedactionKind::Authorization, ...)`.

### Cookie grammar

ASCII case-insensitive `Cookie:` or `Set-Cookie:`, followed by **zero or more** ASCII spaces/tabs and a **non-empty** remainder with no CR/LF, matches as `RedactionKind::Cookie`. No cookie attribute/value parsing is performed.

### Credential-bearing URI grammar

A complete credential-bearing URI matches `RedactionKind::Credential` **only** when all these constraints hold:

1. The complete candidate consists solely of visible ASCII bytes `0x21..0x7E`, excluding any ASCII whitespace or control byte.
2. The scheme matches exactly `[A-Za-z][A-Za-z0-9+.-]*` and is immediately followed by `://`.
3. Userinfo is before the first `@` before any `/`, `?`, or `#`; the first `:` separates a non-empty user component and a non-empty password component.
4. The user component excludes `:`, `@`, `/`, `?`, `#`. The password excludes `@`, `/`, `?`, `#` but MAY contain additional `:` bytes.
5. A non-empty authority follows `@`, with optional path, query, or fragment after it.

No URL parsing, percent-decoding, hostname validation, credential decoding, or scheme-specific interpretation occurs. Near-misses with empty user/password, missing `@`/authority, ASCII whitespace, or a control byte MUST NOT match this grammar.

### JWT, AWS, and prefixed-token grammars

The `token` precedence stage uses only these complete-value forms:

- **JWT-shaped token:** exactly three **non-empty** dot-separated segments with exactly two `.` separators; the first begins with exact ASCII `eyJ`; every segment uses only ASCII `[A-Za-z0-9_-]` and excludes `=` padding. No base64url decoding, JSON decoding, signature validation, or semantic JWT validation occurs. The `eyJ` discriminator is conservative and is not proof that the bytes represent a valid JWT.
- **AWS access-key-shaped token:** exact uppercase ASCII `AKIA` or `ASIA` followed by **exactly 16** uppercase ASCII letters/digits `[A-Z0-9]`.
- **Prefixed token:** exact lowercase ASCII `sk_` or `tok_` followed by **8 through 512 inclusive** ASCII `[A-Za-z0-9_-]` characters.

Ordinary dotted strings such as `0.7.0` or `a.b.c`, padded/extra-segment JWT near-misses, entropy-rich long strings, arbitrary numeric strings, and `token_...` technical identifiers such as `token_bucket_capacity` or `token_generation_mode` are not automatically tokens or phone numbers. An owner-known token outside the grammar uses a token structural key or explicit `redactValue(..., RedactionKind::Token, ...)`.

### Local-path grammar

For this grammar **alone**, discard any leading ASCII space/tab bytes from a temporary classification candidate. The original input bytes remain unchanged for string summary materialization. A candidate yields `RedactionKind::LocalPath` if it begins with:

- an UNC path prefix consisting of two backslash bytes (`\\`);
- a Windows drive prefix `[A-Za-z]:\` or `[A-Za-z]:/`;
- case-insensitive ASCII `file://` followed immediately by `/` or by a **non-empty authority** followed by `/` or `\`.

A generic value starting with **one** root backslash (for example `\Coretsia\Foundation\ExampleService`) is ambiguous with a PHP FQCN and MUST NOT be classified as a local path. A generic forward-slash-rooted value (`/health`, `/users/{id}`, `/api/v1/items`) is ambiguous with route/transport paths and MUST NOT be classified solely for its leading `/`. Owner-known POSIX or single-backslash-rooted local paths require an explicit local-path key or `redactValue(..., RedactionKind::LocalPath, ...)`. This classifier neither resolves filesystem paths nor accesses the filesystem.

### Email grammar

A complete input yields `RedactionKind::Pii` only when:

- there is **exactly one** `@` byte;
- the non-empty local part uses only ASCII `[A-Za-z0-9.!#$%&'*+/=?^_{}|~-]`, has no leading/trailing `.`, and has no consecutive `..`;
- the domain consists of **at least two** non-empty dot-separated labels;
- every domain label matches `[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?`;
- there is no whitespace, control byte, Unicode byte sequence, or trailing dot.

This is high-confidence byte-pattern classification, not email-address deliverability or full RFC validation. Invalid local dots, domain-label edge hyphens, single-label domains, and non-ASCII bytes remain unclassified.

### Non-matches, ambiguity, and opacity

The classifier performs no entropy scoring, generic long-string token detection, arbitrary numeric-phone inference, SQL phrase detection, generic `/...` or `\...` path detection, configuration/environment access, network calls, mutable pattern loading, or JSON/URL/base64/JWT/SQL/provider-payload decoding. Matched values are treated as **opaque bytes**. Non-matches, including near-misses and ordinary language, do not override producer or sink restrictions.

## Recursive redaction algorithm (MUST)

`redactJsonLike($input, $context)` follows exactly these stages:

1. **Complete Foundation input normalization:** call `JsonLikeNormalizer::normalize($input, limits: fixedLimits)` and obtain recursively normalized `null|bool|int|string|array`. The entire structure, including branches whose top-level keys will later be redacted, is validated for forbidden types, string map-key types, depth/node/individual-string/aggregate-string limits. Failure is mapped before semantic traversal; redaction MUST NOT hide an invalid child merely because its parent key is sensitive.
2. **Semantic traversal on normalized input:** preserve `null`, `bool`, and `int` leaves; for each string leaf, run `SensitiveValueClassifier` and replace a match with `RedactedValue::toArray()`. Unmatched strings pass through only as a consequence of shared classification; this alone is not permission to emit that data at a sink.
3. **Lists:** process items recursively in their existing order; list order is not sorted or changed.
4. **Maps, in Foundation-normalized `strcmp` order:** for each original string key reached by traversal, reject any ASCII C0 (`0x00..0x1F`) or DEL (`0x7F`) byte with `input-invalid` **immediately before classification**. Classify the original key with `SensitiveKeyClassifier` first. On a match, preserve that original key unchanged and replace its **complete normalized associated value branch** with one summary; do **not** semantically recurse into that branch. On no key match, classify the key's original bytes with `SensitiveValueClassifier`; if it matches, fail with `sensitive-map-key` **before** examining its associated normalized value semantically. Only when both key classifiers return `null` recurse into its value.
5. **Normalize completed output:** call the same Foundation normalizer with the same fixed limits on the complete redacted PHP value. On output normalization failure throw `output-invalid`, never return a partial result. The redactor returns the normalized json-like PHP shape and performs **no whole-result stable JSON serialization**.

Keys nested only inside a branch already chosen for complete replacement are not checked for C0/DEL merely to impose redaction semantic policy. Foundation has already validated their structural types and budgets. When a candidate reached map key would also cause a Foundation failure earlier in stage 1, the corresponding Foundation `input-invalid` or `input-limit-exceeded` mapping wins over the later C0/DEL or `sensitive-map-key` check. Structural key labels are **not** themselves treated as leaked redacted values when they are preserved in output after a key-classifier match; value-shaped map keys cannot be emitted and fail closed.

Complete classified branches and values MUST NOT appear as original, partially masked, prefixed, suffixed, previewed, or encoded variants in the output. JSON, URLs, Base64, JWT payloads, SQL, and provider payloads are not decoded or semantically parsed during traversal. Map keys remain original byte strings and maps remain recursively sorted by `strcmp`.

## Summary byte materialization and disclosure (MUST)

The redactor uses exactly one `RedactionContext::mode()` for **every** classified string and branch in a single call. No classifier, kind, field, debug flag, or environment selects another mode, and no per-branch fallback/downgrade/upgrade occurs.

- `Placeholder`: create a `RedactedValue($kind, Placeholder, null, null)` immediately. For a selected complete non-string branch, do **not** compute its byte representation, length, hash, or run `StableJsonEncoder` to materialize branch bytes.
- `Length`: materialize bytes once and report only `strlen($bytes)`.
- `Hash`: materialize bytes once and report only the canonical hash.
- `HashAndLength`: materialize bytes **once** and use that same byte sequence for both `strlen()` and hashing.

A direct `redactValue(string, kind, context)` and a selected recursive **string** branch use the original string bytes unchanged, including raw byte sequences, as the materialized `string` representation. A selected recursive **non-string** branch uses:

```php
$bytes = \substr(StableJsonEncoder::encodeStable($normalizedBranch), 0, -1);
```

`StableJsonEncoder::encodeStable()` owns a stable-JSON result terminated by exactly one LF (`0x0A`); the redactor removes exactly that encoder-owned final LF for the sensitive **branch-summary** representation only. It does not strip other bytes, invent another end-of-line rule, revalidate Foundation framing, or serialize the full output. For instance, a normalized non-string branch `['a' => 1]` has branch summary bytes `{"a":1}` and byte length `7`, not `8`. Encoding a normalized branch may still fail (for example malformed UTF-8); such a summary materialization failure maps to `input-invalid` in non-placeholder modes, while `Placeholder` remains able to replace the complete branch without encoding it.

The `string` and `json-like` representation discriminators contribute to **hashing only**; neither discriminator is included in the disclosed `length`. Under the same scope and kind, direct string `"null"` and non-string scalar `null` have identical four visible summary bytes but MUST hash under different representation preimages.

## Stable SHA-256 contract (MUST)

`StableRedactionHasher` is stateless and uses one private hash implementation for its two package-internal entrypoints:

```php
public function hashString(string $bytes, RedactionKind $kind, RedactionContext $context): string;
public function hashJsonLike(string $bytes, RedactionKind $kind, RedactionContext $context): string;
```

The exact binary preimage is:

```php
"coretsia.redaction@1\0" . $context->scope() . "\0" . $kind->value . "\0" . $representation . "\0" . $bytes
```

Here `$representation` is exactly `string` or `json-like`. The output is exactly `sha256:` plus 64 **lowercase** hexadecimal characters of SHA-256 over the entire preimage. NUL separators (`0x00`) and the `coretsia.redaction@1` prefix are literal. No salt, time, hostname, process ID, environment value, machine-dependent input, or random data participates. Same scope, kind, domain, and bytes yield the same digest; changing scope, kind, representation, or bytes changes the preimage domain.

Exact representation-domain fixture (`scope = cli.output`, `kind = secret`, `bytes = null` as the four ASCII string bytes `n`, `u`, `l`, `l`):

| Representation | Required digest                                                           |
|----------------|---------------------------------------------------------------------------|
| `string`       | `sha256:5766c080857ef006b1bd51448aed5fc55727f2e11216fa7961cc2b07fee19151` |
| `json-like`    | `sha256:7057aa75ab01ec4fb0e07ed6fc8ad0bde295c759a85919c960ef3b92fa28d567` |

These preimages differ in their representation field. The fixed vectors specify deterministic framing, **not** general SHA-256 collision freedom, encryption, non-reconstructability, or metadata admissibility. Low-entropy raw input may be guessed and its unsalted deterministic hash recomputed.

## Foundation limits and normalization ownership (MUST)

Both direct-string and recursive redaction use Foundation `JsonLikeNormalizer::normalize()` with the exact immutable limits:

```php
new JsonLikeNormalizationLimits(
    32,        // maxDepth
    10_000,    // maxNodes
    65_536,    // maxStringBytes
    1_048_576, // maxTotalStringBytes
)
```

| Limit             | Exact maximum   | Foundation meaning                                                                                  |
|-------------------|-----------------|-----------------------------------------------------------------------------------------------------|
| Container depth   | `32`            | nested list/map container depth                                                                     |
| Nodes             | `10000`         | list items and map values under Foundation's canonical accounting                                   |
| Individual string | `65536` bytes   | every string value or string map key                                                                |
| Aggregate strings | `1048576` bytes | every encountered string value and structural map key counted exactly once, including a root string |

Map keys do not consume node budget but participate in aggregate byte accounting. The aggregate budget uses `strlen()` bytes during the existing Foundation traversal and canonical sorted map-key order. An individual-string excess is detected before aggregate accounting for that same string; Foundation's depth, node, map-key-type, and individual-string failure precedence remains authoritative. Aggregate comparisons are overflow-safe. The redactor performs no second type, map-key-type, node, depth, individual-string, or aggregate-byte validation walk; output is passed to Foundation again under the same fixed limits.

`JsonLikeNormalizationLimits` has a fourth constructor field `public ?int $maxTotalStringBytes = null`, which is an **optional domain-neutral Foundation primitive**. Omitting the fourth field is backward-compatible: `null` means no aggregate-string-byte cap from that limits object. A non-null value MUST be positive; `0` does not mean unbounded. `platform/redaction` always explicitly supplies `1_048_576`, owns no independent aggregate counter, and does not change Foundation's generic limit semantics.

Foundation's sorted maps, preserved lists, type restrictions, diagnostic paths, and stable JSON framing remain the normative structural contract in `docs/ssot/json-like-runtime-values.md`. The redactor's C0/DEL reached-map-key check is additional semantic output safety, never a replacement Foundation validator or a pre-scan of hidden branches.

## Fail-closed failure mapping (MUST)

All explicit redaction failures use the contracts-level `RedactionException` reason allowlist:

| Stage                                       | Condition                                                                                            | Reason                 |
|---------------------------------------------|------------------------------------------------------------------------------------------------------|------------------------|
| Input normalization                         | forbidden type, non-string map key, or structurally invalid json-like value                          | `input-invalid`        |
| Input normalization                         | depth, node, individual-string, or aggregate-string resource excess                                  | `input-limit-exceeded` |
| Direct string normalization                 | `redactValue()` individual-string limit excess                                                       | `input-limit-exceeded` |
| Reached map-key semantic check              | ASCII C0/DEL byte in the key after successful normalization                                          | `input-invalid`        |
| Key-first semantic traversal                | structural key unclassified by `SensitiveKeyClassifier` but classified by `SensitiveValueClassifier` | `sensitive-map-key`    |
| Non-string branch summary materialization   | `StableJsonEncoder` failure while materializing normalized sensitive branch bytes                    | `input-invalid`        |
| Output normalization                        | output depth, node, individual-string, or aggregate-string resource excess                           | `output-invalid`       |
| Any other unexpected implementation failure | Throwable outside explicit mappings                                                                  | `internal-failure`     |

A `RedactionException` produced by an explicit stage mapping MUST propagate with its **same allowlisted reason** and MUST NOT be changed to `internal-failure`. Other caught `Throwable` values are mapped to `RedactionException::internalFailure()` without copying the previous Throwable, its message, or raw diagnostic information. No failure returns unchanged input, a partly processed tree, or an alternative fallback result. Successful redaction does not return a selected original raw value or whole sensitive branch.

The `RedactionException` diagnostic contract exposes only its error code and fixed reason. Package services emit no logging, tracing, metrics, reporting, stdout, or stderr containing classified input, scopes, kinds, hashes, lengths, keys, failures, or classification decisions. Native `Throwable` stack-trace handling and emission remain consumer-owned and are not sanitized by this package.

## Consumer safety and disclosure policy (MUST)

The primary requirement is **producer-owned safe-by-construction output**. Omission or a stable safe reason token is preferred when the original value is unnecessary. This shared redactor is a final defense-in-depth mechanism for eligible consumers; it is not a classifier-based source-data authorization system. An unclassified value or key MUST NOT be interpreted as permission to transport raw material.

`Placeholder` is the default disclosure mode. `Length|Hash|HashAndLength` are **metadata disclosures**, not declassification; byte lengths and deterministic unsalted digests may correlate or reconstruct low-entropy values. The owner MAY select a non-placeholder mode only when its policy explicitly permits that same metadata for **every sensitive branch that may be selected** by the recursive call. If any candidate branch requires stricter handling (low entropy, reconstructable value, or any other prohibited metadata), the owner MUST omit the value or use `Placeholder`, or split separately governed branches into separate redaction operations. No per-kind or per-branch policy registry or implicit mode fallback is provided.

A valid six-field redacted summary does not make an otherwise forbidden `ErrorDescriptor` extension key valid. Existing `ErrorDescriptor` semantic-key rules, local absolute-path bans, size/depth constraints, safe derivation, and non-reconstruction requirements remain in force. Logs, spans, and span events admit a summary only when their established sink schema and owner policy permit it; metric labels remain allowlist-only and MUST NOT contain arbitrary redacted-summary maps or new label dimensions. Absolute paths, SQL, headers, tokens, environment values, cookies, payloads, and raw stack traces are not made safe by classification or late redaction.

Stable secret references MAY remain visible only when their owner already deems them safe; unsafe references use omission, owner-safe derivation, or explicit `RedactionKind::SecretReference` where this port is an allowed dependency. Raw resolved secret/environment values are never inherently diagnostic-safe. An eligible owner explicitly classifies such strings using `RedactionKind::Secret` or `RedactionKind::EnvValue` as appropriate. Lower-layer Core owners continue using their own safe omissions/derivations without importing the platform implementation.

Cross-package callers MUST NOT depend on or instantiate `SensitiveKeyClassifier`, `SensitiveValueClassifier`, or `StableRedactionHasher` as public policy extension points. `core/kernel` Kernel Ops remains safe without consuming this port. Downstream CLI and other eligible output boundaries may use the port only where module planning explicitly provides `platform.redaction`; no automatic module enablement follows package presence.

## Configuration, runtime context, and observability policy (MUST)

`platform/redaction` has no config directory/root/key, env override, disable switch, debug bypass, dynamic runtime policy selection, policy registry, or mutable cache. Its default redactor, classifiers, and hasher are stateless shared services. No `ContextStore`, `ContextAccessorInterface`, `KernelRuntimeInterface`, `ResetInterface`, UoW participation, `kernel.reset`/`kernel.stateful` tag, service locator, logger, tracer, meter, or reporter is introduced. `RedactionContext` is a standalone immutable **method argument**, not a container-managed runtime context.

This package emits no logs, metrics, spans, error reports, stdout, or stderr, and does not observe its own sensitive inputs or decision metadata. A consumer MAY observe an already-safe operation outcome only under its own sink policy; the redactor does not emit telemetry or hide raw values in observability baggage.

## Determinism and verification (MUST)

Canonical package contracts, unit and integration tests MUST establish:

- exact enum values, context scope bounds, immutable result shape, safe error code/reasons, and absence of raw source data in redacted results;
- exact 98-key alias vocabulary and `RedactionKind` mappings, ASCII canonicalization, ambiguous/unclassified key controls, and precise baseline whole-value grammars with near-misses and precedence;
- Foundation-first normalization, sensitive-key complete-branch replacement, sensitive-value-shaped map-key rejection, list order, recursively sorted maps, original structural-key preservation, and no semantic payload decoding;
- all four modes, uniform per-call selection, byte lengths, exactly one non-string branch byte representation, Foundation-owned final LF removal, stable SHA-256 framing including `string|json-like` separation, and deterministic fixed vectors;
- all four Foundation input and output budgets, C0/DEL policy/precedence, exact stage-to-reason failure mapping, and no partial result or previous Throwable retention;
- explicit Foundation provider application, exact three constructor-reference dependencies, shared service identity, and absence of config/runtime-context/observability dependencies or automatic module enablement.

No generic library test authorizes consumer sink emission merely because `redactJsonLike()` returned a normalized shape. Sink-specific admissibility and error/observability tests remain owned by their respective producers.

## Non-goals

- Credential/secret resolution, vault or cloud SDK backends, authorization, or token/JWT signature validation.
- Decoding or semantically parsing URLs, JSON, Base64, JWT contents, SQL, request/response/provider payloads, or mail bodies.
- PII content rewriting, arbitrary phone-number detection, entropy scoring, AI guardrails, and content inspection.
- Configurable classifiers, mutable learned policies, callback hooks, per-kind disclosure rules, or raw/debug/passthrough output.
- Producer schema approval, sink encoding, final whole-result stable JSON serialization, telemetry emission, or PHP exception stack-trace sanitization.
- Kernel module-plan orchestration, Kernel Ops alteration, or competing `platform/security` generic redaction mechanics.

## Cross-references

- `docs/adr/ADR-0010-sensitive-data-redaction-boundary.md` — architecture and rejected alternatives.
- `docs/adr/ADR-0004-foundation-json-like-runtime-values.md` — Foundation structural model.
- `docs/adr/ADR-0013-secrets-port.md` — secrets boundary.
- `docs/adr/ADR-0030-canonical-runtime-container-definitions.md` — declarative provider semantics.
- `docs/ssot/json-like-runtime-values.md` — json-like normalizer, optional aggregate budget, and stable JSON framing.
- `docs/ssot/runtime-container-definitions.md` — canonical Foundation DI provider definitions.
- `docs/ssot/error-descriptor.md` — `ErrorDescriptor` field and extension policy.
- `docs/ssot/observability-and-errors.md` and `docs/ssot/observability.md` — safe diagnostic and telemetry sink policy.
- `docs/ssot/secrets-contracts.md` and `docs/ssot/config-and-env.md` — secret and environment ownership.
- `docs/ssot/modules-and-manifests.md` and `docs/ssot/application-dependency-sync.md` — module and dependency relationships.
