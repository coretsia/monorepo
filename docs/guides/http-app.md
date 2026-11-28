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

# HTTP application dispatch

Goal: connect explicitly registered application actions to compiled routes, compose global and route-specific middleware, and return application responses through the canonical HTTP runtime.

Scope: Consumer applications based on `coretsia/skeleton`. Configuration paths and commands in this guide are relative to the **Skeleton application root**, not the Coretsia monorepo root. PHP classes and package metadata in the examples belong to an application-owned, Composer-autoloadable runtime module.

This is an operational guide. Normative dispatch, argument, middleware, and response policies belong to `docs/ssot/http-app-dispatch.md`; route declaration and artifact behavior belongs to `docs/ssot/routing-artifact.md`. All examples are synthetic and are intended for an isolated development application.

---

## 1) Prerequisites and activation

Install the applicable runtime packages and select the modules for the intended targets. Composer installation alone does not enable application dispatch.

- The command-owning `console` target needs `platform.cli` and `platform.routing` in its effective `ModulePlan` to validate and compile routes for an HTTP target.
- The intended `web` or `api` target must explicitly select `platform.http-app` and the application-owned module that contributes actions, middleware, and route providers.
- HttpApp requires `core.foundation`, `platform.errors`, `platform.http`, `platform.problem-details`, and `platform.routing` through canonical module planning. All required transitive dependencies, including Kernel and Redaction where required by those modules, must be available. The canonical outer Problem Details boundary is mandatory for HttpApp.
- The HTTP target must already have its intended runtime driver, effective compatible PSR-7/17 factory bindings, and an application-owned HTTP entrypoint. HttpApp does not select a transport or supply a concrete factory implementation.

File: `config/app.php`

```php
<?php

declare(strict_types=1);

return [
    'activeTargets' => [
        'console',
        'web',
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
                'platform.errors',
                'platform.http',
                'platform.http-app',
                'platform.problem-details',
                'platform.routing',
            ],
        ],
    ],
];
```

Merge these entries into the existing bootstrap configuration. Retain unrelated active targets, presets, and overrides; do not exclude required modules or edit Kernel-owned preset resources. The example uses `web`; for `api`, apply the equivalent selection to that target and use its own configuration and artifacts.

After the Skeleton's Kernel-only Composer bootstrap is installed, reconcile the catalog-managed packages before selecting the example application-owned module:

```bash
php coretsia plan
php coretsia review
php coretsia apply
```

`apply` is the effectful Composer operation. Run this workflow only when the effective selections of all active targets contain catalog-known modules. An application-owned module absent from the release installation catalog must be installed through the application's owning Composer composition. DependencySync rejects effective selections containing unknown catalog modules; do not invent catalog entries or modify generated installation metadata. If such a module is already selected, manage dependencies through the owning Composer composition instead.

The following sections define the application-owned services and the module that registers them. `Example\Catalog` is an example namespace, not a built-in Coretsia namespace.

---

## 2) Define invokable actions

An action is an explicitly registered application-owned service with an actual public non-static `__invoke()` method. Route handlers are logical identifiers, not class names, arbitrary method strings, or executable callables.

### 2.1 JSON-like return values and admitted arguments

File: `src/Catalog/CatalogLabel.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

final readonly class CatalogLabel
{
    public function value(): string
    {
        return 'catalog-demo';
    }
}
```

File: `src/Catalog/ShowItemAction.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Psr\Http\Message\ServerRequestInterface;

final readonly class ShowItemAction
{
    public function __invoke(
        ServerRequestInterface $request,
        string $item,
        CatalogLabel $label,
    ): array {
        return [
            'argument' => $label->value(),
            'item' => $item,
            'method' => $request->getMethod(),
            'started' => $request->getAttribute('example.catalog.started') === true,
        ];
    }
}
```

The resolver supplies the effective request to the exact `ServerRequestInterface` parameter when request injection is enabled. It supplies the matched `{item}` parameter by its exact name as an encoded string when route parameter injection is enabled. An unconstrained segment such as `blue%20chair` is not decoded to `blue chair`. The `CatalogLabel` parameter requires both an explicit binding under `CatalogLabel::class` and that exact concrete type in `http_app.arguments.allow_container_types`.

The example returns only synthetic public values. Do not use an action response to expose arbitrary request attributes, sensitive path parameters, or diagnostic state.

Argument resolution uses enabled request injection, then enabled matching route parameters, then admitted concrete container types, then declared PHP defaults. A selected but incompatible route parameter or unavailable selected service fails rather than falling through to another source. Nullable declarations alone do not imply null injection. Query parameters, body, cookies, headers, metadata, and arbitrary context values are not automatic argument sources. Union/intersection types, by-reference and variadic action parameters are not supported.

### 2.2 HTML strings

File: `src/Catalog/AboutAction.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

final readonly class AboutAction
{
    public function __invoke(): string
    {
        return '<p>Example catalog</p>';
    }
}
```

A string is explicit application-owned HTML, not a template. Escape untrusted values in the action before producing HTML. This example uses a fixed literal.

### 2.3 Explicit PSR-7 responses

File: `src/Catalog/EmptyAction.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

final readonly class EmptyAction
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function __invoke(): ResponseInterface
    {
        return $this->responses->createResponse(204);
    }
}
```

The response factory is an explicit declarative constructor dependency. It is not an automatically injected action argument. Use the effective HTTP-owned PSR-17 binding, not a package-local factory implementation.

When the corresponding representation mapping is enabled, null, bool, int, and valid JSON-like arrays produce status `200` with `Content-Type: application/json` through Foundation's stable JSON encoder. Strings produce status `200` with `Content-Type: text/html; charset=utf-8`. Existing `ResponseInterface` values pass through the action response mapper unchanged; ordinary middleware and HTTP finalization still apply. The empty array encodes as `[]` with a final LF. Floats, resources, arbitrary objects, and invalid nested values are not supported. Disabled mappings fail closed rather than choosing another representation. There is no automatic content negotiation or DTO serialization.

---

## 3) Define application middleware

Ordinary middleware implements `Psr\Http\Server\MiddlewareInterface`. Registration, admission, and execution are separate concerns. A middleware can delegate once and optionally decorate the returned response, or return its own response without delegation.

### 3.1 Before route matching

File: `src/Catalog/BeforeMatchMiddleware.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class BeforeMatchMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request->withAttribute('example.catalog.started', true));
    }
}
```

This middleware sets an unrelated application-owned request attribute and delegates exactly once. It does not rewrite the method or URI, perform matching, or manufacture framework-owned routing state.

### 3.2 After route matching and in route-specific composition

File: `src/Catalog/RouteMarkerMiddleware.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Coretsia\Platform\HttpApp\Routing\RouteDispatchState;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RouteMarkerMiddleware implements MiddlewareInterface
{
    public function __construct(private string $step)
    {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $match = RouteDispatchState::fromRequest($request)->match();
        $response = $handler->handle($request);

        if ($match === null) {
            return $response;
        }

        return $response->withAddedHeader('X-Example-Step', $this->step);
    }
}
```

Use this middleware only after the canonical matching contribution. `match()` returns the immutable `RouteMatch` for a matched application route or null for explicit reserved-path delegation. Middleware may inspect its handler, name, parameters, or safe metadata through the handoff. The example decorates only application-route responses with fixed synthetic markers, never with route parameters or raw request data. Reserved responses pass through unchanged.

The same class can be registered under distinct service ids for ordinary global middleware and route-specific middleware. The marker values come from explicit constructor arguments, not from request data or route metadata. They illustrate response unwind order and are not a production diagnostic header policy.

### 3.3 Synthetic authorization-style denial

File: `src/Catalog/DenyItemMiddleware.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DenyItemMiddleware implements MiddlewareInterface
{
    public function __construct(private ResponseFactoryInterface $responses)
    {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $this->responses->createResponse(403)
            ->withHeader('Cache-Control', 'no-store');
    }
}
```

This deliberately denies every request reaching the example middleware. It is a synthetic authorization-style policy, not a real authentication or authorization implementation. It does not trust a client-controlled flag, call the downstream handler, resolve the action, or invoke it. An ordinary response short-circuit does not become a routing exception or enter action observations. A real application must make its authorization decisions using its trusted admission controls.

---

## 4) Declare routes and their middleware policies

File: `src/Catalog/CatalogRouteProvider.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

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
                name: 'example.catalog.show',
                methods: ['GET', 'POST'],
                pathTemplate: '/example/catalog/{item}',
                handler: 'catalog.show:detail',
                metadata: [
                    'http_app_middleware' => ['catalog.route.z', 'catalog.route.a'],
                ],
            ),
            new RouteDefinition(
                name: 'example.catalog.protected',
                methods: ['GET'],
                pathTemplate: '/example/protected/{item}',
                handler: 'catalog.show:detail',
                metadata: [
                    'http_app_middleware' => ['catalog.route.deny', 'catalog.route.a'],
                ],
            ),
            new RouteDefinition(
                name: 'example.catalog.about',
                methods: ['GET'],
                pathTemplate: '/example/about',
                handler: 'catalog.about',
            ),
            new RouteDefinition(
                name: 'example.catalog.empty',
                methods: ['GET'],
                pathTemplate: '/example/empty',
                handler: 'catalog.empty',
            ),
        ];
    }
}
```

The first two routes share one logical action id, `catalog.show:detail`, but select different middleware lists. Their `RouteMatch::handler()` values match the same `action_id` in section 6 exactly. The route metadata controls middleware selection, not action resolution. In the first route, `catalog.route.z` executes before `catalog.route.a`, even though its service id sorts later. In the protected route, `catalog.route.deny` returns `403` before the next selected middleware or action can execute.

The absent member on the About and Empty routes means no route-specific middleware. An explicit empty list is also valid. An explicitly present null, non-list, malformed or duplicate id, unadmitted id, or forbidden framework role fails closed; it does not mean an empty selection. Route metadata contains static identifiers, not middleware objects, executable values, or request data. Existing `RouteGroup` inheritance and duplicate metadata-key rejection apply. Routing preserves the list order in `routes@1` but does not resolve services or execute middleware.

---

## 5) Register services through declarative DI

Register the actions, allowlisted concrete argument service, route provider, and middleware in the enabled owning module's canonical definition provider. A class is not registered merely because it is autoloadable or implements a particular interface.

File: `src/Catalog/CatalogDefinitions.php` (relative to the application-owned package)

```php
<?php

declare(strict_types=1);

namespace Example\Catalog;

use Coretsia\Foundation\Container\Definition\ContainerDefinitionBuilder;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionContext;
use Coretsia\Foundation\Container\Definition\ContainerDefinitionProviderInterface;
use Coretsia\Foundation\Container\Definition\ContainerValueReference;
use Coretsia\Foundation\Tag\ReservedTags;
use Psr\Http\Message\ResponseFactoryInterface;

final class CatalogDefinitions implements ContainerDefinitionProviderInterface
{
    public function define(ContainerDefinitionBuilder $definitions, ContainerDefinitionContext $context): void
    {
        $responses = ContainerValueReference::service(ResponseFactoryInterface::class);

        $definitions
            ->classService(CatalogRouteProvider::class, CatalogRouteProvider::class)
            ->tag(ReservedTags::ROUTING_ROUTE_PROVIDER, CatalogRouteProvider::class, 0, [])
            ->classService(CatalogLabel::class, CatalogLabel::class)
            ->classService('catalog.show.action', ShowItemAction::class)
            ->classService('catalog.about.action', AboutAction::class)
            ->classService('catalog.empty.action', EmptyAction::class, [$responses])
            ->classService('catalog.global.before', BeforeMatchMiddleware::class)
            ->tag(ReservedTags::HTTP_MIDDLEWARE_APP, 'catalog.global.before', 200, [])
            ->classService('catalog.global.after', RouteMarkerMiddleware::class, ['app-after'])
            ->tag(ReservedTags::HTTP_MIDDLEWARE_APP, 'catalog.global.after', 0, [])
            ->classService('catalog.global.route-pre', RouteMarkerMiddleware::class, ['route-pre'])
            ->tag(ReservedTags::HTTP_MIDDLEWARE_ROUTE_PRE, 'catalog.global.route-pre', 0, [])
            ->classService('catalog.global.route', RouteMarkerMiddleware::class, ['route'])
            ->tag(ReservedTags::HTTP_MIDDLEWARE_ROUTE, 'catalog.global.route', -2000, [])
            ->classService('catalog.route.z', RouteMarkerMiddleware::class, ['selected-z'])
            ->classService('catalog.route.a', RouteMarkerMiddleware::class, ['selected-a'])
            ->classService('catalog.route.deny', DenyItemMiddleware::class, [$responses]);
    }
}
```

The ordinary global `app` contributions use priorities `200` and `0` to execute before and after `RouterMiddleware`, whose canonical priority is `100`. The `route_pre` contribution follows the app slot. The ordinary `route` contribution uses priority `-2000` to illustrate that `ActionDispatchMiddleware`, despite priority `-1000`, is placed after all ordinary effective route middleware, including lower-priority tagged and manually appended entries. The route-specific services are registered without global middleware tags; selection belongs to route metadata.

All contributions use existing Foundation definitions and `ReservedTags`. Do not register the canonical router, dispatcher, outer error boundary, or global Pipeline again. Middleware registration alone does not require a dependency on the HTTP execution package. The example uses the HttpApp handoff API, so its owning package has a direct dependency on `coretsia/platform-http-app`.

### 5.1 Install and select the owning module

For this example, the application-owned package must be installed and autoloadable through Composer. The runtime manifest reads installed package metadata, not provider declarations added only to the Skeleton root `composer.json`.

File: `composer.json` (fragment, relative to the application-owned package)

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
            "moduleId": "integrations.example-catalog",
            "providers": [
                "Example\\Catalog\\CatalogDefinitions"
            ],
            "requires": [
                "core.foundation",
                "platform.http-app",
                "platform.routing"
            ],
            "conflicts": []
        }
    }
}
```

Merge this fragment into the owning package's existing Composer metadata, preserving other provider entries and their order. Use a unique module id from a supported runtime layer. Declare direct Composer dependencies on `coretsia/core-contracts`, `coretsia/core-foundation`, `coretsia/platform-http-app`, `coretsia/platform-routing`, `psr/http-factory`, `psr/http-message`, `psr/http-server-handler`, and `psr/http-server-middleware` using compatible release constraints.

After installing this package through the application's Composer composition, add `integrations.example-catalog` to `moduleOverrides.web.include` in `config/app.php`, retaining the entries from section 1. Select it in `api` instead or as well if those routes are intended for that target.

Verify the effective plans:

```bash
php coretsia debug:modules --target=console
php coretsia debug:modules --target=web
```

The Console target owns command availability. The Web target owns these declarations and configuration. Selecting the application module only in Console does not contribute its services or routes to Web.

---

## 6) Configure handler and middleware admission

File: `apps/web/config/http_app.php`

```php
<?php

declare(strict_types=1);

use Example\Catalog\CatalogLabel;

return [
    'handlers' => [
        [
            'action_id' => 'catalog.show:detail',
            'service_id' => 'catalog.show.action',
        ],
        [
            'action_id' => 'catalog.about',
            'service_id' => 'catalog.about.action',
        ],
        [
            'action_id' => 'catalog.empty',
            'service_id' => 'catalog.empty.action',
        ],
    ],
    'route_middleware' => [
        'catalog.route.a',
        'catalog.route.deny',
        'catalog.route.z',
    ],
    'arguments' => [
        'allow_request' => true,
        'allow_route_params' => true,
        'allow_container_types' => [CatalogLabel::class],
    ],
    'response' => [
        'json' => [
            'enabled' => true,
        ],
        'html' => [
            'enabled' => true,
        ],
    ],
];
```

The file returns the `http_app` subtree only. Other keys retain their effective values from the canonical config merge, including tracing, metrics, and logging flags. Preserve unrelated valid settings when applying this example; ensure the final merged lists contain unique admitted entries. Use `apps/api/config/http_app.php` for equivalent API-target configuration.

### 6.1 Explicit handler and argument admission

For both item routes, the match's handler is exactly `catalog.show:detail`, which selects the configured `service_id` `catalog.show.action`, registered as `ShowItemAction::class` in section 5. This is not a class-name fallback. Each handler record contains exactly `action_id` and `service_id`, and logical identifiers are values rather than structural config-map keys. The logical identifier grammar is `[A-Za-z_][A-Za-z0-9_.:-]*` in full, with a 256-byte limit. Duplicate action ids are rejected even if their service ids agree.

`CatalogLabel::class` is registered under its exact class name and admitted in `allow_container_types`. The effective configuration snapshot is supplied by the existing read-only `ConfigRepositoryInterface`. Provider-time semantic validation and runtime admission use the same list policies; they do not read a separate application config registry.

### 6.2 Explicit middleware admission

`http_app.route_middleware` admits service ids; it does not establish execution order. Every selected id in `http_app_middleware` must occur in the effective admission list. Both lists require unique non-empty canonical ids. The selected list for the first route is deliberately in a different order from the configuration list. Each resolved service must implement `MiddlewareInterface`. Canonical matching, dispatch, and outer Problem Details middleware roles cannot be selected, including through aliases resolving to those classes.

### 6.3 Graph completeness and runtime admission

The definition provider registers the services without constructing them. HttpApp declares every configured handler service id, allowlisted argument type, and admitted route middleware id through `ContainerDefinitionBuilder::requireService()`. Canonical Kernel production graph completeness checks require these bindings even for middleware not selected by any current route. Do not depend on implicit concrete-class autowiring or create another graph validator.

Runtime resolution remains lazy and Container-owned. The dispatcher resolves only selected route middleware, checks the complete chain before execution, and fails closed if any selected service is unavailable or invalid. No required middleware is silently skipped. An unmapped action id, failing registered service construction, or unsupported runtime instance is a dispatch failure, not a routing miss. Shared and non-shared service lifecycle remains Foundation-owned; independent canonical reset discovery is not filtered by route selection.

---

## 7) Compile and deploy for the selected target

Follow [Routing](./routing.md) for canonical source validation, route artifact publication, and deployment prerequisites. Use the same target, authoritative environment, effective configuration, and selected modules for the Kernel generation and Routing artifact:

```bash
php coretsia config:compile --target=web
php coretsia routes:validate --app=web
php coretsia routes:compile --app=web
php coretsia cache:verify --target=web
```

Stop if any command fails. Use `--app` for Routing commands and `--target` for Kernel commands. For `api`, use its own corresponding sequence. The Console plan must provide the commands, while the selected HTTP target must provide the application services and route providers. `routes:validate` checks source declarations without publishing; `routes:compile` publishes the validated owner-local `routes@1` artifact. `cache:verify` verifies the Kernel generation, not the route artifact. Prepare both artifacts before starting the intended runtime and coordinate their deployment. Do not edit generated artifacts or rely on request-time source fallback, recompilation, or route discovery.

HTTP entrypoint readiness consumes the boot-selected immutable Routing table and rejects missing or invalid required artifacts. Start the application's existing HTTP runtime with the intended driver and factories; this guide does not introduce a front controller, SAPI adapter, bootstrap path, or additional runtime composition mechanism.

---

## 8) Exercise the dispatch boundary

Assume the configured Web runtime is already listening on a private development endpoint. Send requests using the actual listener and a host, scheme, and environment permitted by the existing HTTP policies. The expected results below assume no earlier middleware short-circuits and the request reaches the indicated application or terminal boundary.

### 8.1 Observe the matched application route

For `GET /example/catalog/blue%20chair`, the relevant request entry order of the example contributions is:

```text
canonical outer Problem Details boundary
  -> existing global HTTP middleware
  -> catalog.global.before
  -> RouterMiddleware
  -> catalog.global.after
  -> catalog.global.route-pre
  -> catalog.global.route
  -> ActionDispatchMiddleware
       -> catalog.route.z
       -> catalog.route.a
       -> action terminal (catalog.show:detail)
```

Other effective middleware remains in its canonical position; this is not a replacement for the complete HTTP topology. Matching does not invoke the action. `ActionDispatchMiddleware` validates the original routing handoff, resolves the selected sequence, and runs it using an invocation-local guarded HTTP Pipeline. The terminal verifies the captured state and invokes the admitted action using the effective request from the last delegating middleware. No second match or independent invocation engine is involved.

The response is status `200` with `Content-Type: application/json` and the stable JSON body (with a final LF):

```json
{"argument":"catalog-demo","item":"blue%20chair","method":"GET","started":true}
```

The example `X-Example-Step` header values are appended on response unwind, in this order:

```text
selected-a
selected-z
route
route-pre
app-after
```

The header illustrates execution order independently of the configuration admission-list order. The framework's normal response finalization still applies.

### 8.2 Compare route-specific policies and return values

- `GET /example/protected/blue%20chair` selects `catalog.route.deny` before `catalog.route.a`. The denial returns `403` with `Cache-Control: no-store` and no action invocation. The complete selected chain is admitted before execution, but `catalog.route.a` is not executed. Ordinary outer middleware may decorate the denial response.
- `GET /example/about` has no route-specific selection and produces status `200`, `Content-Type: text/html; charset=utf-8`, and the exact body `<p>Example catalog</p>`.
- `GET /example/empty` has no route-specific selection and returns the action's PSR-7 status `204` response, subject to ordinary middleware and HTTP finalization.

Action tracing, metrics, and completion logging begin only after successful action terminal admission. A match, a route middleware admission failure, or an ordinary middleware denial before terminal entry does not produce action observations. Those observations describe the action resolution, invocation, and response mapping boundary, not the finalized HTTP outcome.

### 8.3 Application misses and system endpoints

- `GET /example/missing` is an ordinary application miss and produces canonical `404` Problem Details when no route admits the path.
- `PUT /example/catalog/blue%20chair` has a valid unmatched method and a path admitted by GET and POST. It produces canonical `405` Problem Details with `Allow: GET, POST`. The allowed methods are unique and byte-order-sorted; Problem Details owns header construction from validated descriptor metadata. The public JSON does not include `allowed_methods`. GET does not imply HEAD and OPTIONS is not generated automatically.
- `GET /_alive` remains an HTTP-owned liveness endpoint. When the request reaches the terminal, its response is `200`, `Content-Type: text/plain; charset=utf-8`, with exact body `ALIVE`. Reserved paths are delegated through the existing downstream middleware to HTTP-owned terminal behavior, not handled by an application action. Endpoint availability and earlier middleware policies remain authoritative.

The canonical outer Problem Details boundary handles thrown application failures through the existing Errors boundary. Do not catch routing exceptions to render a separate error response or turn dispatch/continuation failures into 404/405. Ordinary middleware short-circuit responses, such as the synthetic 403, are not automatically converted into Problem Details.

---

## 9) Security and middleware lifecycle

### 9.1 Preserve the routing handoff and routing inputs

- Read `RouteMatch` after matching through `RouteDispatchState::fromRequest($request)->match()`. The framework-owned `RouteDispatchState::ATTRIBUTE` MUST NOT be overwritten, removed, or replaced, even with an equivalent state instance. Do not manufacture it in application middleware or store it globally or in ContextStore.
- Every guarded continuation after matching MUST preserve the exact original HTTP method, encoded URI path, and URI host as well as the original handoff identity. Unrelated request attributes and legitimate parsed-body adaptations can be changed without changing routing identity.
- A middleware MUST NOT delegate changed routing inputs to a downstream middleware, even if that middleware intends to restore them before action dispatch. The guarded Pipeline rejects the first invalid delegation before entering the next middleware or terminal. Final dispatch validation is additional protection, not a replacement for the continuation checks.
- If an application requires method or URI rewriting, it must occur before `RouterMiddleware` and before **every applicable admission control that depends on the rewritten inputs**. Placement before matching alone is insufficient. Pre-match rewriting does not authorize bypassing maintenance, trusted-host, HTTPS, security, or request-context policies. If no placement preserves those decisions and canonical context semantics, do not perform the rewrite. No rewriting middleware or automatic rematching is introduced here.

### 9.2 Preserve authorization and short-circuit ordering

Application middleware is trusted code. A protected response cache MUST NOT return a cached response before applicable authorization has admitted the request. Route-specific authorization cannot protect a response returned earlier by global middleware. Arrange protected cache lookup after all applicable admission decisions, or do not serve protected cached responses in that scope. A route-specific allowlist is not an authorization policy and guarded dispatch is not a security sandbox.

### 9.3 Use continuations only within their invocation

When delegating, call the supplied handler exactly once while `process()` is active. Do not retry it after a returned response, failed validation, or downstream `Throwable`. A short-circuit must return without downstream execution. Middleware MUST NOT retain downstream continuation handlers beyond `process()` execution. They expire when the invocation returns or throws and cannot be reused later.

Keep requests, matches, resolved invocation-local chains, and action results out of reusable mutable state. Follow the existing shared service and reset contracts for stateful middleware. The dispatcher does not orchestrate another UnitOfWork or reset pass. Continuation enforcement provides at-most-once action terminal entry within the canonical invocation, not transaction rollback or cross-request idempotency.

---

## 10) Common failures and fixes

### 10.1 Dispatch is not active or HTTP startup fails

Verify the effective target plan, installed dependencies, selected driver, factory bindings, mandatory Problem Details participation, and both the Kernel generation and route artifact. Package installation alone does not enable HttpApp. Do not register substitute canonical middleware roles or bypass readiness.

### 10.2 Configured service binding is missing

Verify the owning package is installed and autoloadable, selected in the intended target, and declares its definition provider in installed metadata. Check the exact ids in the effective handler records, concrete type allowlist, and route middleware admission list against explicit definitions. Correct the source configuration or definition and prepare the target again; do not rely on implicit autowiring.

### 10.3 Route matches but action dispatch fails

Check the exact handler id, public invokable signature, configured argument sources, encoded route parameters, concrete type admission, and return-value policy. Check the complete selected middleware list, service registration, and resolved types. Invalid explicit selections are not treated as absent. Canonical internal failures must not be reclassified as application misses.

### 10.4 Continuation or routing identity violation

Check whether middleware retained or reused a handler, replaced the routing handoff, or delegated a modified method, encoded path, or host. Fix the owning middleware and ordering, not the canonical guard. Do not attempt rematching or later restoration to recover the invocation.

---

## 11) Related documentation

- [Routing](./routing.md) — declaration, source validation, artifact publication, and runtime readiness.
- `docs/ssot/http-app-dispatch.md` — canonical dispatch, explicit registration, argument and response mapping, guarded continuation, and lifecycle policy.
- `docs/ssot/routing-and-http-app-contracts.md` — format-neutral descriptors and ports.
- `docs/ssot/routing-artifact.md` — compiled route table and metadata semantics.
- `docs/ssot/http-middleware-wiring.md` and `docs/ssot/http-middleware-catalog.md` — canonical middleware placement and ordering.
- `docs/ssot/http-system-endpoints.md` and `docs/ssot/reserved-path-matching.md` — reserved ownership and terminal behavior.
- `docs/ssot/runtime-container-definitions.md` and `docs/ssot/di-tags-and-middleware-ordering.md` — canonical declarative service wiring and contributions.
- `docs/ssot/config-roots.md` and `docs/ssot/config-and-env.md` — effective configuration ownership and consumption.
- `docs/ssot/modules-and-manifests.md` and `docs/ssot/application-dependency-sync.md` — module selection and physical package reconciliation.
- `docs/ssot/error-descriptor.md` and `docs/ssot/problem-details.md` — canonical routing failures and HTTP Allow-header adaptation.
- `docs/ssot/observability.md` and `docs/ssot/logging-policy.md` — bounded action observations.
- `docs/ssot/uow-and-reset-contracts.md` — canonical runtime lifecycle.
- `docs/adr/ADR-0043-platform-http-app-router-middleware-action-invoker.md` — application dispatch architectural decisions.
