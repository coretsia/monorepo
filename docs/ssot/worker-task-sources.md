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

# Worker Task Sources SSoT

```yaml
ssotVersion: 1
status: pre-stable
owner: platform/worker
```

This document is the canonical contract for acquiring and settling real work in `platform/worker` child processes.

## Ownership

The cross-package task-source SPI is owned by `core/contracts`:

```text
Coretsia\Contracts\Worker\WorkerTaskType
Coretsia\Contracts\Worker\WorkerTaskSourceInterface
Coretsia\Contracts\Worker\WorkerTaskSourceContextInterface
Coretsia\Contracts\Worker\WorkerTaskInterface
```

`platform/worker` consumes these ports and owns source selection, child orchestration, readiness ordering, max-request counting, and Worker failure mapping.

Transport/runtime adapter packages implement task sources and own queue, HTTP, broker, request-receive, response-emission, acknowledgement, retry, requeue, and dead-letter semantics.

`platform/worker` MUST NOT ship a synthetic, no-op, queue-placeholder, or HTTP-placeholder task source.

## Task types

The closed task-source vocabulary is:

```text
queue
http
```

`WorkerTaskType::values()` MUST return these values in deterministic order.

`WorkerPoolSpec::taskType()` MAY remain a normalized string at the Worker config boundary. Worker task-source resolution MUST convert it through `WorkerTaskType` before selection.

## Reserved tag

Task sources are contributed through the framework-reserved tag:

```text
worker.task_source
```

Runtime framework code MUST reference it through:

```text
Coretsia\Foundation\Tag\ReservedTags::WORKER_TASK_SOURCE
```

The exact metadata shape is:

```php
[
    'task_type' => 'queue', // or 'http'
]
```

No additional metadata keys are permitted.

For the selected `WorkerTaskType`:

```text
0 matching sources  -> startup failure: worker-task-source-missing
1 matching source   -> selected source
2+ matching sources -> startup failure: worker-task-source-ambiguous
```

Task-source selection MUST NOT use first-wins or priority override semantics. `TagRegistry` ordering remains deterministic, but priority does not resolve a task-source conflict.

The selected service MUST implement `WorkerTaskSourceInterface`, and its `taskType()` MUST match the registered `task_type` metadata. Invalid metadata or a type mismatch fails closed.

## Safe source context

`WorkerTaskSourceContextInterface` exposes only:

```text
workerIndex()
workerCount()
cancellationRequested()
maxBlockingWaitMs()
```

It MUST NOT expose:

- runtime or artifact paths;
- stop-flag paths;
- lifecycle lock or locator data;
- control endpoints or credentials;
- process handles or child tables;
- raw config trees;
- environment values;
- transport credentials.

`workerIndex()` is the stable zero-based child slot index.

`workerCount()` is the configured pool size.

## Readiness

Before a Worker child publishes its readiness frame, the selected source MUST receive:

```php
WorkerTaskSourceInterface::assertReady(
    WorkerTaskSourceContextInterface $context,
): void
```

`assertReady()` MAY validate transport/client/consumer/handler/emitter readiness.

It MUST NOT:

- reserve or consume a task;
- acknowledge or reject a task;
- execute application work;
- emit an HTTP response.

If source readiness fails, the child MUST fail before publishing `READY`.

The existing readiness wire protocol is not extended by this contract.

## Receive semantics

Task acquisition is:

```php
WorkerTaskSourceInterface::receive(
    WorkerTaskSourceContextInterface $context,
): ?WorkerTaskInterface
```

The only valid outcomes are:

```text
real task acquired  -> WorkerTaskInterface
shutdown requested  -> null
source/transport failure -> exception
```

`null` MUST NOT mean “temporarily empty”, “no work yet”, or an idle transport wake-up.

Synthetic/no-op tasks are forbidden.

## Blocking and cooperative cancellation

`receive()` MUST wait using transport-native blocking, an event-loop wait, long polling, or an equivalent transport-owned mechanism.

It MUST remain cooperatively interruptible.

Every source MUST bound each continuous transport wait by:

```text
context.maxBlockingWaitMs()
```

After each bounded wait returns without a task, the source MUST regain control and check:

```text
context.cancellationRequested()
```

The source MAY continue waiting only when cancellation is not requested.

The Worker-owned bounded-wait budget is:

```text
min(1000 ms, worker.stop_timeout_ms)
```

Transport-native cancellation or event-loop wake-up MAY return control earlier, but it MUST NOT extend the continuous blocking interval beyond `maxBlockingWaitMs()`.

This requirement ensures that the Worker-owned filesystem cancellation signal is observed within a deterministic bounded interval.

The following idle policies are forbidden in production task sources:

```text
tryPop -> sleep -> retry
tryPop -> usleep -> retry
busy polling
synthetic NoopTask / IdleTask / EmptyTask
```

A bounded native broker wait is not busy polling.

## Max-request counting

`worker.max_requests` counts real acquired task attempts.

The canonical order is:

```text
receive real task
-> increment processed count
-> execute the task
```

Therefore:

```text
real acquired task       -> counts
cooperative cancellation -> does not count
transport wake-up        -> does not count
synthetic task           -> forbidden
```

Counting does not depend on task success or settlement success.

## Execution boundary

Each acquired task executes through:

```text
Coretsia\Contracts\Runtime\KernelRuntimeInterface::runUnitOfWork(...)
```

`ApplicationWorker` MUST NOT invoke Kernel hooks or Foundation reset orchestration directly.

The task-source type is the bounded Worker observability operation id.

## Settlement

`WorkerTaskInterface` owns transport-specific settlement:

```text
execute()        -> application-level task body
complete(result) -> success-side settlement
fail(failure)    -> application/UoW failure settlement
```

Canonical flow:

```text
Kernel/UoW succeeds
-> complete(result)

Kernel/UoW fails
-> fail(original failure)
-> rethrow original failure

fail() fails
-> worker-task-settlement-failed

complete() fails
-> worker-task-settlement-failed
-> MUST NOT call fail() automatically
```

The last invariant prevents duplicate or ambiguous settlement after an acknowledgement or response emission may already have partially succeeded.

## Failure taxonomy

Pre-readiness/source-selection failures belong to `WorkerStartFailedException`:

```text
worker-task-source-missing
worker-task-source-ambiguous
worker-task-source-invalid
worker-task-source-unresolvable
worker-task-source-not-ready
```

Post-readiness task-source failures belong to `WorkerLifecycleFailedException`:

```text
worker-task-source-terminated
worker-task-source-receive-failed
worker-task-settlement-failed
```

Failure diagnostics MUST NOT include source service ids, adapter class names, queue names, broker endpoints, DSNs, URLs, request paths, headers, cookies, tokens, payloads, raw exception messages, absolute local paths, command lines, or environment values.

## Adapter ownership

A queue source owns transport-specific behavior such as:

```text
blocking reservation
handler invocation
acknowledgement
reject/retry/requeue/dead-letter
```

An HTTP source owns runtime-specific behavior such as:

```text
request receive
PSR-7 request construction where applicable
RequestHandlerInterface invocation
response emission
failure response emission
```

PSR HTTP and concrete runtime/queue dependencies belong to those adapter packages, not `platform/worker`.

It is valid for `platform/worker` to have no production task source installed. In that state the selected source is missing, the child MUST fail before readiness, and supervisor startup rolls back instead of entering a synthetic hot-recycle loop.

## Configuration boundary

`worker.task_type` selects a task-source type, not a service id.

Do not introduce:

```text
worker.task_source_service
worker.queue_source_service
worker.http_source_service
worker.task_type=none
```

Service discovery belongs to provider definitions plus `ReservedTags` and `TagRegistry`.

## Enforcement evidence

Contracts-level shape and vocabulary are enforced by:

```text
framework/packages/core/contracts/tests/Contract/WorkerTaskTypeIsStableContractTest.php
framework/packages/core/contracts/tests/Contract/WorkerTaskSourceContractsShapeContractTest.php
```

Worker-level selection, context, execution, settlement, and lifecycle behavior are enforced by:

```text
framework/packages/platform/worker/tests/Unit/WorkerTaskSourceResolverTest.php
framework/packages/platform/worker/tests/Unit/WorkerTaskSourceContextTest.php
framework/packages/platform/worker/tests/Unit/WorkerShutdownBudgetTest.php
framework/packages/platform/worker/tests/Unit/ApplicationWorkerTest.php
framework/packages/platform/worker/tests/Unit/ApplicationWorkerMaxRequestsTest.php
framework/packages/platform/worker/tests/Integration/WorkerHandlesMultipleTasksSequentiallyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskSourceResolverSelectsServiceLazilyTest.php
framework/packages/platform/worker/tests/Integration/WorkerTaskSourceStartupFailureProcessTest.php
```

## Cross-references

- [Tag Registry](./tags.md)
- [Runtime Container Definitions](./runtime-container-definitions.md)
- [Runtime Drivers](./runtime-drivers.md)
- [Worker Architecture](../architecture/worker.md)
- [ADR-0017](../adr/ADR-0017-persistent-worker-supervisor-application-worker.md)
- [SSoT Index](./INDEX.md)
