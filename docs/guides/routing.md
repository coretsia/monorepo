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

# Routing

Goal: declare application routes, validate and compile them for an explicit application target, and deploy a validated immutable route table without request-time source discovery.

Scope: Consumer applications based on `coretsia/skeleton`. Configuration paths and commands in this guide are relative to the **Skeleton application root**, not the Coretsia monorepo root.

This is an operational guide. Normative route admission, grouping, artifact, and matching behavior belongs to `docs/ssot/routing-artifact.md`; format-neutral descriptors and ports belong to `docs/ssot/routing-and-http-app-contracts.md`.

---

## 1) Prerequisites and activation

Install the applicable runtime packages and select the modules through the effective target-local bootstrap configuration. Composer installation alone does not enable a module.

- The command-owning `console` target needs `platform.cli` and `platform.routing` in its effective `ModulePlan`.
- Each `web` or `api` target whose routes are to be compiled needs `platform.routing` and its intended route-contributing modules in its own effective `ModulePlan`.
- For an HTTP runtime, select `platform.http` independently and provide the intended HTTP runtime driver and compatible PSR-7/17 factory bindings. Routing does not supply these bindings or select HTTP.
- Routing requires `core.foundation`, `core.kernel`, and `platform.redaction` through the module dependency graph.
- For HTTP application action dispatch, explicitly select `platform.http-app` in the intended `web` or `api` target and satisfy its canonical runtime dependencies, including `platform.errors` and `platform.problem-details`. Routing and HTTP selection alone does not activate application action dispatch. Follow the [HTTP application dispatch guide](./http-app.md) for handler registration, middleware admission, and runtime prerequisites.

File: `config/app.php`

```php
<?php

declare(strict_types=1);

return [
    'activeTargets' => [
        'console',
        'web',
        'api',
    ],
    'moduleOverrides' => [
        'console' => [
            'include' => [
                'platform.cli',
                'platform.routing',
            ],
        ],
        'web' => [
            'include' => [
                'platform.http',
                'platform.routing',
            ],
        ],
        'api' => [
            'include' => [
                'platform.http',
                'platform.routing',
            ],
        ],
    ],
];
```

Merge these entries into the application's existing configuration, retaining unrelated active targets, presets, and overrides. This example selects both HTTP targets; omit a target from the example when the application does not use it. Keep include and exclude selections consistent. Do not edit Kernel-owned canonical preset resources to activate Routing.

After the Skeleton's Kernel-only Composer bootstrap is installed, reconcile the catalog-managed dependencies for the intended active targets. Run these operations only when the effective selections of all active targets contain catalog-known modules. For a new application, complete this reconciliation before selecting application-owned modules that are not in the release installation catalog:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

`apply` is the effectful Composer operation. DependencySync rejects an effective selection containing a module absent from the release installation catalog. If such a module is already selected, manage the application's Composer dependencies through its owning composition instead of running these operations against that selection. Application-owned route-contributing packages must be installed and autoloadable before they are enabled. Do not invent catalog entries or edit generated installation metadata to register route providers.

Verify the effective module selections:

```bash
php coretsia debug:modules --target=console
php coretsia debug:modules --target=web
php coretsia debug:modules --target=api
```

The Console plan owns command availability, not the declarations compiled for `web` or `api`. The commands boot the explicitly selected target's source host using its effective configuration, enabled modules, and authoritative environment. Source compilation itself does not require an existing HTTP runtime or a loaded Kernel artifact generation.

---

## 2) Declare standalone routes

A route provider implements `Coretsia\Contracts\Routing\RouteProviderInterface`. Its `id()` returns a stable logical provider identity; `routes()` returns a deterministic flat list of `RouteDefinition` instances.

The following examples belong to an application-owned, Composer-autoloadable runtime module. `Example\Routing` is an example namespace, not a built-in Coretsia namespace. Adapt it to the owning package.

File: `src/Routing/CatalogRouteProvider.php` (relative to the route-contributing package)

```php
<?php

declare(strict_types=1);

namespace Example\Routing;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;

final class CatalogRouteProvider implements RouteProviderInterface
{
    public function id(): string
    {
        return 'example.catalog';
    }

    public function routes(): array
    {
        return [
            new RouteDefinition(
                name: 'catalog.index',
                methods: ['GET'],
                pathTemplate: '/catalog',
                handler: 'Catalog::index',
            ),
            new RouteDefinition(
                name: 'catalog.show',
                methods: ['GET'],
                pathTemplate: '/catalog/{item}',
                handler: 'Catalog::show',
                requirements: ['item' => '[0-9]+'],
                metadata: [
                    'http_app_middleware' => [
                        'catalog.route.authorize',
                        'catalog.route.decorate',
                    ],
                    'section' => 'catalog',
                ],
            ),
        ];
    }
}
```

The handler is a logical identifier, not an executable callable or a filesystem path. Routing returns it as part of the match; it does not resolve or invoke the handler. Route names are unique across the complete target-local table, and provider identities are unique across distinct contributing services. Names, handler identifiers, and provider identities must be non-empty, at most 256 bytes, and match `[A-Za-z_][A-Za-z0-9_.:-]*` in full.

When HttpApp participates in the effective HTTP target, the logical handler identifier is handed to its separately owned application dispatch boundary through `RouteMatch::handler()`.

For example, `Catalog::show` remains an opaque logical identifier. It is not interpreted as a PHP static method or an implicit callable.

The application must explicitly register the same logical value as an `action_id` in `http_app.handlers` and provide the corresponding application-owned invokable service. Follow the [HTTP application dispatch guide](./http-app.md) for handler registration, argument resolution, and invocation.

The example `http_app_middleware` metadata member declares an ordered selection of consumer-owned middleware service identifiers. Routing preserves that list through compilation, hydration, and matching without resolving or executing its services.

To dispatch this route through HttpApp, register both middleware services explicitly and admit their exact identifiers through `http_app.route_middleware`. The metadata alone neither registers the services nor establishes an authorization policy.

Providers must not depend on request objects, request data, runtime artifacts, filesystem scanning, or controller and attribute discovery. An empty route list is valid.

### 2.1 Supported templates and requirements

- `/` is the root route.
- `/catalog` uses a static segment. Static segments match `[a-z_-][a-z0-9_-]*`.
- `/catalog/{item}` uses a complete named placeholder. Placeholder identifiers match `[A-Za-z_][A-Za-z0-9_]*` and must be unique within the complete template.
- `/catalog/{item}/labels/{label}` combines multiple complete segments.

Templates are case-sensitive and at most 256 bytes. Empty segments, trailing slashes (except `/`), relative paths, dot segments, embedded placeholders, wildcard segments, and optional segments are rejected.

Supported requirement expressions are exactly:

- `[0-9]+` — for example, `42`;
- `[A-Za-z]+` — for example, `Alice`;
- `[A-Za-z0-9_-]+` — for example, `item_42`.

Each requirement key must identify a placeholder in the supplied template. Requirements match complete encoded segments, not decoded text. An unconstrained placeholder matches a non-empty valid encoded segment. Arbitrary regular expressions and non-empty defaults are not supported.

Declare only the canonical methods `GET`, `HEAD`, `POST`, `PUT`, `PATCH`, `DELETE`, `OPTIONS`, `TRACE`, and `CONNECT`. Methods are matched exactly at runtime; `OTHER` is not a routing method. GET does not imply HEAD, and OPTIONS is not generated automatically.

### 2.2 Precedence and conflicts

The effective order is tag priority descending, then service id ascending (`strcmp`), then the exact route order emitted by each provider. Do not sort a provider's routes by name or specificity. Distinct overlapping domains are allowed and the first match wins, including overlaps between static and parameterized routes.

Duplicate names and equivalent matching domains with intersecting method sets are rejected. For example, `GET /catalog/{item}` and `GET /catalog/{id}` with the same numeric requirement have equivalent domains even though their placeholder names differ.

---

## 3) Compose nested route groups

`Coretsia\Platform\Routing\Definition\RouteGroup` is an immutable source declaration helper. It composes ordinary `RouteDefinition` instances before the provider returns its list. It is not a runtime registry or a different descriptor type.

### 3.1 Nested API version and parameterized groups

File: `src/Routing/ApiRouteProvider.php` (relative to the route-contributing package)

```php
<?php

declare(strict_types=1);

namespace Example\Routing;

use Coretsia\Contracts\Routing\RouteDefinition;
use Coretsia\Contracts\Routing\RouteProviderInterface;
use Coretsia\Platform\Routing\Definition\RouteGroup;

final class ApiRouteProvider implements RouteProviderInterface
{
    public function id(): string
    {
        return 'example.api';
    }

    public function routes(): array
    {
        $api = RouteGroup::root()->group('/api', 'api', [], ['area' => 'api']);
        $v1 = $api->group('/v1', 'v1', [], ['version' => 1]);
        $teams = $v1->group(
            '/teams/{team}',
            'teams',
            ['team' => '[0-9]+'],
            [
                'http_app_middleware' => [
                    'teams.route.authorize',
                    'teams.route.decorate',
                ],
                'section' => 'teams',
            ],
        );

        return [
            new RouteDefinition('api.about', ['GET'], '/api/about', 'Api::about'),
            ...$v1->routes([
                new RouteDefinition('index', ['GET'], '/', 'Api::index'),
                new RouteDefinition('catalog', ['GET'], '/catalog', 'Catalog::index'),
            ]),
            $teams->route(new RouteDefinition(
                name: 'member',
                methods: ['GET'],
                pathTemplate: '/members/{member}',
                handler: 'Members::show',
                requirements: ['member' => '[A-Za-z]+'],
                metadata: ['view' => 'summary'],
            )),
        ];
    }
}
```

The emitted order is `api.about`, `api.v1.index`, `api.v1.catalog`, `api.v1.teams.member`. The route named `api.v1.index` has the complete template `/api/v1`, not `/api/v1/`.

The final grouped member route is equivalent to the following standalone declaration, using the same `RouteDefinition` import (use it **instead of**, not in addition to, the grouped member route):

```php
$standalone = new RouteDefinition(
    name: 'api.v1.teams.member',
    methods: ['GET'],
    pathTemplate: '/api/v1/teams/{team}/members/{member}',
    handler: 'Members::show',
    requirements: [
        'member' => '[A-Za-z]+',
        'team' => '[0-9]+',
    ],
    metadata: [
        'area' => 'api',
        'http_app_middleware' => [
            'teams.route.authorize',
            'teams.route.decorate',
        ],
        'section' => 'teams',
        'version' => 1,
        'view' => 'summary',
    ],
);
```

Equivalent standalone and grouped declarations produce identical fingerprints and artifact bytes when their ordered canonical descriptors and authoritative inputs are identical.

The member route inherits the complete `http_app_middleware` list from the Teams group. Its declared order remains `teams.route.authorize`, then `teams.route.decorate`.

The equivalent standalone declaration contains the same metadata list in the same order. Neither declaration registers or executes middleware.

If the target uses HttpApp, the application must explicitly register and admit these synthetic middleware service identifiers before dispatching the route. See the [HTTP application dispatch guide](./http-app.md).

### 3.2 Inheritance and safe metadata

- The root group has path prefix `/`, empty name prefix, and empty requirements and metadata. `group()` creates a new group without modifying its parent.
- Paths join with exactly one literal slash; `/` is the identity prefix. Non-empty name prefixes join with `.`, and empty prefixes introduce no separator. Group name prefixes are dot-separated identifier components matching `[A-Za-z_][A-Za-z0-9_-]*`.
- Group requirements may refer only to placeholders declared by that group's supplied prefix; a route's requirements refer only to its own supplied template. Combined requirements cannot override inherited keys, and placeholder names must be unique across the complete path.
- Group and route metadata are deterministic JSON-like maps. Duplicate keys, including a key whose inherited value is `null`, are rejected rather than overwritten or recursively merged.
- Methods, handler, and defaults belong to the individual route. Groups may contribute safe consumer-owned metadata but do not register or execute middleware, perform authorization, or resolve actions.
- List-valued metadata members retain their declared element order during inheritance. An inherited `http_app_middleware` list is not concatenated, sorted, deduplicated, or replaced by child declarations.
- The existing duplicate inherited metadata-key rejection applies to middleware selection metadata exactly as it applies to every other metadata key.

Keep metadata to safe static declaration data (for example, a logical area, version, or display category). Do not put credentials, sensitive keys, private customer data, raw request data, environment-derived values, or absolute filesystem paths in metadata. Slash-leading strings, backslash-leading strings, drive-qualified absolute paths, and `file://` values are rejected in metadata. The complete descriptor is checked by the canonical sensitive-key classifier and redactor; unsafe declarations are rejected, not rewritten. A redaction non-match is not proof of safety.

For HttpApp-owned middleware selection, use only static canonical service identifiers in the ordered `http_app_middleware` list.

Do not place middleware instances, factories, closures, executable values, credentials, or request-derived authorization data in route metadata.

Routing validates generic metadata safety. The selected runtime consumer owns additional semantic admission. For HttpApp, explicit middleware service registration, admission, execution order, and short-circuit behavior are covered by the [HTTP application dispatch guide](./http-app.md).

### 3.3 Invalid group declarations

The following examples are independent invalid operations, not additional routes to register. They assume `RouteDefinition` and `RouteGroup` are imported as in section 3.1.

Duplicate inherited placeholder (even without a second requirement):

```php
RouteGroup::root()
    ->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], [])
    ->route(new RouteDefinition('member', ['GET'], '/members/{team}', 'Members::show'));
```

A child group cannot supply or override a requirement for a placeholder declared only by its parent:

```php
RouteGroup::root()
    ->group('/teams/{team}', 'teams', ['team' => '[0-9]+'], [])
    ->group('/members', 'members', ['team' => '[0-9]+'], []);
```

Duplicate inherited metadata key (the inherited `null` still counts):

```php
RouteGroup::root()
    ->group('/api', 'api', [], ['section' => null])
    ->group('/v1', 'v1', [], ['section' => 'catalog']);
```

Duplicate inherited middleware metadata key (an empty child list cannot replace the inherited selection):

```php
RouteGroup::root()
    ->group('/catalog', 'catalog', [], [
        'http_app_middleware' => [
            'catalog.route.authorize',
            'catalog.route.decorate',
        ],
    ])
    ->route(new RouteDefinition(
        name: 'show',
        methods: ['GET'],
        pathTemplate: '/{item}',
        handler: 'Catalog::show',
        metadata: [
            'http_app_middleware' => [],
        ],
    ));
```

The duplicate `http_app_middleware` key is rejected even though the child value is an empty list. An identical redeclaration is also invalid. RouteGroup does not provide a middleware override or recursive metadata merge policy.

These operations fail during source declaration composition. If they occur inside `routes()`, the provider registry rejects the provider output through the safe compilation boundary. Successfully flattened routes still pass all global conflict, reserved-domain, safety, and resource checks.

---

## 4) Register providers through declarative DI

Register the route provider services and tag them in the enabled owning module's canonical container definition provider. A provider is not discovered merely because its class implements `RouteProviderInterface`.

File: `src/Routing/RoutingDefinitions.php` (relative to the route-contributing package)

```php
<?php

declare(strict_types=1);

namespace Example\Routing;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Tag\ReservedTags;

final class RoutingDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $definitions
            ->classService(CatalogRouteProvider::class, CatalogRouteProvider::class)
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, CatalogRouteProvider::class, 10, [])
            ->classService(ApiRouteProvider::class, ApiRouteProvider::class)
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, ApiRouteProvider::class, 0, []);
    }
}
```

For this example, the route-contributing package must be installed as a Composer dependency of the application. The runtime manifest reads installed package metadata; adding provider metadata only to the Skeleton root `composer.json` does not register a runtime module.

File: `composer.json` (fragment, relative to the route-contributing package)

```json
{
    "autoload": {
        "psr-4": {
            "Example\\": "src/"
        }
    },
    "extra": {
        "coretsia": {
            "kind": "runtime",
            "moduleId": "integrations.example-routing",
            "providers": [
                "Example\\Routing\\RoutingDefinitions"
            ],
            "requires": [
                "platform.routing"
            ],
            "conflicts": []
        }
    }
}
```

Merge this fragment into the owning package's existing Composer metadata, preserving other provider entries and their order. Use a unique module id from a supported runtime layer. Declare direct Composer dependencies on `coretsia/core-contracts`, `coretsia/core-foundation`, and `coretsia/platform-routing` using compatible release constraints.

After installing the package through the application's Composer composition, add `integrations.example-routing` to `moduleOverrides.web.include` and/or `moduleOverrides.api.include` in `config/app.php` for the intended targets. Verify the resulting target-local plans with `debug:modules`. Selecting the module only in Console does not contribute routes to Web or API. For target-specific declarations, register only the intended providers through the selected modules.

The exact tag is `routing.route_provider`. Tag metadata must be `[]`. The definition provider declares services and tags; it must not resolve the route providers or call their `routes()` methods. `RouteProviderRegistry` resolves the services during explicit source compilation and consumes the canonical tag order without re-sorting. No application-specific provider registry or discovery mechanism is needed.

The example registers the Catalog provider before the API provider by priority. Within each provider, the declared list order is preserved. See `docs/ssot/runtime-container-definitions.md`, `docs/ssot/modules-and-manifests.md`, and `docs/ssot/tags.md` for the declarative wiring and module metadata contracts.

---

## 5) Validate source declarations without publishing

Run from the Skeleton application root:

```bash
php coretsia routes:validate --app=web
php coretsia routes:validate --app=api
```

Both commands require an explicit `--app` value of `web` or `api`. `--target` is not a Routing command option. The commands run on a Console source host and select the requested target's source configuration and providers independently of the Console provider set.

Validation performs the same complete source compilation and admission checks as publication, including the canonical serialized-byte limit. It does not read, create, modify, or delete `routes.php`, create artifact directories, or acquire a route publication lock. It does not verify the deployed route artifact or a Kernel generation. Provider code must remain deterministic and free of effects.

Exit code `0` indicates successful validation; the structured result contains `app`, `valid` (`true`), and `route_count`. Exit code `1` indicates a handled failure. Do not deploy on failure.

### 5.1 Reserved route rejection

Application declarations must not intersect the canonical system-route table, regardless of method, environment, module selection, or endpoint exposure. For example, the following templates fail compilation or validation:

- `/_alive` — exact active reservation;
- `/health` — exact reserved declaration;
- `/health/{item}` — reserved `/health/` namespace;
- `/_debug/{section}` — reserved `/_debug/` development namespace;
- `/{section}` — unconstrained domain intersects exact reserved declarations even though the template is not a literal reserved path.

A numeric requirement on `/{number}` makes that single-segment domain disjoint from the canonical reserved declarations; other admission rules still apply. Do not treat the examples as a separate reservation registry. The complete canonical list belongs to `docs/ssot/system-route-table.md`.

Grouping does not change admission. This valid group composition produces an invalid application route for compilation:

```php
RouteGroup::root()
    ->group('/health', 'health', [], [])
    ->route(new RouteDefinition('item', ['GET'], '/{item}', 'Health::show'));
```

The complete template `/health/{item}` intersects the reserved prefix. Source admission reports `CORETSIA_ROUTING_RESERVED_ROUTE_FORBIDDEN` with reason `routing-reserved-route-forbidden`. Choose a disjoint application namespace instead of disabling the guard.

---

## 6) Compile and publish routes

After source validation succeeds, publish the target-local artifacts:

```bash
php coretsia routes:compile --app=web
php coretsia routes:compile --app=api
```

Each command validates the complete source result before publication. The owner artifact is `routes@1`, stored at `<RuntimePathContext::artifactRoot()>/routes.php`. The default layout is:

```text
var/cache/web/routes.php
var/cache/api/routes.php
```

The effective artifact root comes from the target's bootstrap configuration. Do not assume the default layout when `artifactsCacheDir` is customized. Each target has its own `routes.lock` for publication coordination. Neither the route artifact nor its lock belongs to a Kernel immutable generation.

Compilation publishes atomically under the owner-local lock, leaves identical bytes unchanged, and preserves the previous valid artifact on failure before replacement. Exit code `0` indicates successful publication or identical-byte reuse; the structured result contains `app`, `artifact` (`routes.php`), and `route_count`. Exit code `1` indicates a handled failure. `routes:validate` never publishes an artifact, even on success.

### 6.1 Select CLI output format

Use the canonical CLI-global `--format` option, not a Routing-specific option:

```bash
php coretsia routes:validate --app=web --format=json
php coretsia routes:validate --app=api --format=plain
php coretsia routes:compile --app=web --format=table
php coretsia routes:compile --app=api --format=json
```

`json`, `table`, and `plain` select the existing shared CLI renderers. `adaptive` delegates format selection to the canonical CLI output policy and terminal capabilities; when omitted, the configured default applies. The output format does not change compilation, validation, or publication semantics.

For automation, request `--format=json` explicitly. The canonical `coretsia.cli-output@1` envelope contains `meta.command`, `meta.outcome`, `meta.exit_code`, and `data.records`. A successful Routing result is a `json` record whose `payload` contains the fields described above. Handled failures use an `error` record with `code` and `message` (the fixed safe reason token). Check the process exit code as well as the structured result. Do not expect a raw route list or an absolute artifact path in the command output.

---

## 7) Deploy and consume the immutable runtime table

Prepare the Kernel immutable generation separately from the Routing owner artifact. For each intended target, use the same source declarations, target-local configuration, enabled modules, and authoritative application environment for both operations:

```bash
php coretsia config:compile --target=web
php coretsia routes:validate --app=web
php coretsia routes:compile --app=web
php coretsia cache:verify --target=web

php coretsia config:compile --target=api
php coretsia routes:validate --app=api
php coretsia routes:compile --app=api
php coretsia cache:verify --target=api
```

Run only the sequence for targets being deployed and stop if any command fails. `cache:verify` verifies the Kernel generation, not `routes@1`. Deployment must coordinate both artifacts and must not start a runtime requiring Routing until its target-local route artifact has been successfully published for the intended environment. The fingerprint checks canonical payload, target, environment, and reserved registry; it is not a signature or a freshness comparison against live providers.

The HTTP entrypoint checks `RoutingArtifactReadinessInterface` only when `platform.routing` is enabled in the effective runtime `ModulePlan`. Readiness consumes the same shared immutable route table as the router. Missing or invalid required artifacts or readiness bindings prevent HTTP startup. If Routing is not selected, HTTP does not require its artifact. This guide assumes an application-owned HTTP entrypoint and compatible driver/factory setup; it does not introduce a Skeleton front controller or HTTP dispatch implementation.

### 7.1 Match through the routing port

The runtime consumer uses `Coretsia\Contracts\Routing\RouterInterface`, resolved from the canonical runtime container. For example, with the declarations in section 3 and a router supplied by the runtime:

```php
$match = $router->match('GET', '/api/v1/teams/42/members/Alice');
```

The expected match has name `api.v1.teams.member`, template `/api/v1/teams/{team}/members/{member}`, handler `Members::show`, and string parameters `member => Alice` and `team => 42`, together with the safe declared metadata. A miss returns `null`. For example, `/api/v1/teams/no/members/Alice`, `/api/v1/teams/42/members/Alice/`, and `/api/v1/teams/42/members/%41lice` do not match that declaration. Paths must be canonical encoded URI paths; percent-encoded octets are not decoded or case-normalized. Host is not a matching input.

The matched metadata also contains the inherited middleware selection:

```php
$match?->metadata()['http_app_middleware'] ?? [];
```

For this matched member route, the expected list is:

```php
[
    'teams.route.authorize',
    'teams.route.decorate',
]
```

This is declaration metadata, not a list of resolved middleware instances.

The same routing port can enumerate the declared methods of all admitted routes whose complete matching domains accept the concrete path:

```php
$methods = $router->allowedMethods('/api/v1/teams/42/members/Alice');
```

With the declarations in section 3, the expected result is `['GET']`.

The result contains unique exact declared methods in ascending byte order. Invalid, reserved, non-canonical, or unmatched paths return an empty list. GET does not imply HEAD, and OPTIONS is not added automatically.

Allowed-method enumeration uses the same immutable route table and parsed templates. It does not perform another observed match or mutate routing context.

The router removes a stale `PATH_TEMPLATE` at the start of matching and writes the complete declared template on success. It does not resolve actions, invoke handlers, or perform HTTP dispatch.

When HttpApp is enabled, its non-terminal matching middleware hands the successful `RouteMatch` to the remaining global HTTP middleware through the request-local routing state.

The final application dispatch contribution consumes the logical handler identifier and the admitted ordered middleware metadata. It owns action resolution and invocation separately from Routing.

Routing does not construct application 404/405 responses or HTTP Allow headers. Follow the [HTTP application dispatch guide](./http-app.md) for the canonical dispatch and error behavior.

### 7.2 Artifact-only behavior

- The reader captures bounded bytes and decodes the canonical PHP-array grammar without execution. Never load `routes.php` via `include`, `require`, `eval`, or an executable cache loader.
- The table contains only validated flattened descriptors and their parsed templates. Runtime matching does not resolve providers, interpret source groups, scan source files, compile routes, or read the artifact again.
- Ordered consumer-owned metadata lists are preserved in the existing canonical route descriptor through compilation and hydration. Routing does not resolve middleware identifiers or execute middleware.
- Allowed-method enumeration consumes the same immutable table without reloading artifacts, mutating routing context, or emitting additional routing observations.
- Missing or invalid artifacts fail closed. There is no runtime source fallback, regeneration, repair, or request-time reload.
- A successfully hydrated route table remains unchanged when the artifact is replaced. Start a new runtime to consume the replacement, coordinating it with the intended Kernel generation. Do not edit generated artifact bytes by hand.

---

## 8) Common failures and fixes

### 8.1 Routing commands are unavailable or the target is rejected

Verify installed dependencies and the effective Console plan, then check the explicitly selected `web` or `api` plan. Routing must be enabled on both the command-owning Console and the selected target. Do not use `--target` or omit `--app` for Routing commands.

### 8.2 Missing routes or unexpected precedence

Check that the intended route-contributing module is enabled for the selected target, the canonical definition provider is declared in its metadata, and its route provider services carry `ReservedTags::ROUTING_ROUTE_PROVIDER` with empty metadata. Review tag priorities and the provider-emitted list order. Do not add a second discovery path or sort by route names.

### 8.3 Invalid declarations or reserved domains

Check the complete flattened template, requirements, unique names, equivalent domains, and inherited metadata keys. Ensure metadata contains only safe declaration data. Fix the source declaration and run `routes:validate` again before publication.

For inherited middleware metadata, check whether a child group or route redeclares an existing `http_app_middleware` key. Remove the duplicate declaration or place the complete ordered selection at the intended declaration level.

Successful Routing validation establishes generic route and artifact admission. It does not establish HttpApp middleware service availability, semantic middleware admission, handler availability, or authorization correctness.

### 8.4 HTTP startup rejects the route artifact

Check the target's authoritative artifact root, regular non-symlink file requirements, effective environment and readiness binding. Publish a valid route artifact from the intended source host and prepare the corresponding Kernel generation before restarting. Source validation alone cannot repair a missing deployment artifact.

### 8.5 Route matches but application dispatch fails

Check effective HttpApp module participation, explicit logical handler registration, middleware service registration, the configured route middleware admission list, and the supported action argument policy.

Do not modify route matching or generated artifacts to bypass an application dispatch failure.

Follow the [HTTP application dispatch guide](./http-app.md) for the separately owned application dispatch prerequisites and failure boundaries.

---

## 9) Related documentation

- [HTTP application dispatch](./http-app.md) — explicit handler registration, route-specific middleware admission, guarded dispatch, action invocation, and response mapping.
- `docs/ssot/routing-artifact.md` — canonical route grammar, grouping, artifact schema, publication, hydration, and readiness.
- `docs/ssot/routing-and-http-app-contracts.md` — format-neutral routing descriptors and ports.
- `docs/ssot/http-app-dispatch.md` — normative application dispatch and route-specific middleware policy.
- `docs/ssot/http-middleware-wiring.md` — canonical HTTP composition and guarded continuation policy.
- `docs/ssot/system-route-table.md` — canonical reserved endpoint declarations.
- `docs/ssot/reserved-path-matching.md` — concrete encoded path normalization and reservation semantics.
- `docs/ssot/tags.md` — provider tag ownership and ordering.
- `docs/ssot/runtime-container-definitions.md` — canonical declarative service wiring.
- `docs/ssot/modules-and-manifests.md` — target-local module selection and provider metadata.
- `docs/ssot/modes.md` — canonical preset and target-local override policy.
- `docs/ssot/application-dependency-sync.md` — physical package reconciliation.
- `docs/ssot/artifact-generations.md` — Kernel generation publication and selection.
- `docs/ssot/runtime-drivers.md` — runtime driver selection.
- `docs/ssot/sensitive-data-redaction.md` — canonical safety and redaction policy.
- `docs/adr/ADR-0018-platform-routing-compiled-routes.md` — Routing ownership and architectural decisions.
