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

namespace Coretsia\Contracts\Filesystem;

/**
 * Format-neutral logical disk filesystem port.
 *
 * This interface represents a single implementation-owned logical disk
 * boundary. It operates on logical disk paths only, not absolute local
 * filesystem paths, backend roots, object-storage URLs, stream handles, or
 * vendor-specific filesystem objects.
 *
 * Implementations own concrete storage behavior, backend selection, path
 * normalization, path safety enforcement, failure handling, diagnostics, and
 * integration with observability/error handling.
 *
 * The contracts package MUST NOT implement filesystem access, path
 * normalization, path traversal checks, disk registry behavior, upload
 * handling, session storage, lock storage, DI wiring, config defaults, config
 * rules, generated artifacts, or vendor filesystem adapters.
 *
 * Path strings and file contents are sensitive by default. Implementations and
 * callers MUST NOT expose raw paths or raw contents through unsafe diagnostics,
 * logs, spans, metrics, health output, CLI output, error descriptor
 * extensions, or worker failure output.
 */
interface DiskInterface
{
    /**
     * Returns whether a logical disk path exists.
     *
     * The path is storage-relative inside one configured logical disk boundary.
     * It MUST NOT be an absolute local filesystem path, backend root, URI,
     * object-storage URL, or vendor-specific path handle.
     *
     * This method distinguishes absence from existing empty contents.
     *
     * Invalid path handling is implementation-owned.
     *
     * @param non-empty-string $path Logical disk path.
     */
    public function exists(string $path): bool;

    /**
     * Reads full byte contents for a logical disk path.
     *
     * Missing and existing-empty states MUST remain distinguishable:
     *
     * - missing path returns null;
     * - existing empty file returns an empty string;
     * - existing non-empty file returns a non-empty string.
     *
     * The returned string is application data and MUST be treated as sensitive
     * by default. It MUST NOT be logged, traced, exported as metric labels, or
     * copied into unsafe diagnostics by default.
     *
     * This method intentionally does not expose resources, streams, file
     * handles, PSR-7 streams, vendor stream abstractions, SplFileInfo,
     * iterators, or backend metadata objects.
     *
     * Invalid path and backend failure handling are implementation-owned.
     *
     * @param non-empty-string $path Logical disk path.
     */
    public function read(string $path): ?string;

    /**
     * Writes or replaces full byte contents for a logical disk path.
     *
     * The contents string is application data and MUST be treated as sensitive
     * by default. It MUST NOT be logged, traced, exported as metric labels, or
     * copied into unsafe diagnostics by default.
     *
     * Append, partial writes, chunked writes, streamed writes, temporary files,
     * atomic write policy, overwrite policy, and backend-specific write options
     * are outside this contracts surface and belong to future runtime owner
     * packages.
     *
     * Invalid path and backend failure handling are implementation-owned.
     *
     * @param non-empty-string $path Logical disk path.
     */
    public function write(string $path, string $contents): void;

    /**
     * Deletes a logical disk path according to implementation-owned backend
     * semantics.
     *
     * Deleting a missing path SHOULD be noop-safe.
     *
     * This method MUST NOT expose raw backend paths, raw logical paths, vendor
     * diagnostics, object dumps, credentials, tokens, or file contents through
     * unsafe outputs.
     *
     * Invalid path and backend failure handling are implementation-owned.
     *
     * @param non-empty-string $path Logical disk path.
     */
    public function delete(string $path): void;

    /**
     * Lists logical disk paths under a logical prefix.
     *
     * The prefix is storage-relative inside one configured logical disk
     * boundary. An empty prefix represents the logical disk root.
     *
     * Returned paths MUST be logical disk paths, not absolute backend paths,
     * backend roots, URIs, object-storage URLs, SplFileInfo instances,
     * iterators, resources, stream handles, vendor file descriptors, or backend
     * metadata objects.
     *
     * Returned paths MUST be deterministic. The canonical listing order is:
     *
     * path ascending using byte-order strcmp
     *
     * No matching paths return an empty list. Backend failure behavior is
     * implementation-owned.
     *
     * @return list<non-empty-string>
     */
    public function listPaths(string $prefix = ''): array;
}
