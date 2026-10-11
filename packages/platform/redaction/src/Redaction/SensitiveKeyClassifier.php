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

namespace Coretsia\Platform\Redaction\Redaction;

use Coretsia\Contracts\Security\RedactionKind;

/**
 * Canonical, immutable structural-key classification policy.
 *
 * Only ASCII case folding and removal of the four documented ASCII
 * separators affect the temporary lookup key; original keys are preserved.
 *
 * @internal
 */
final class SensitiveKeyClassifier
{
    /**
     * @var array<string, RedactionKind>
     */
    private const array ALIASES = [
        'secretref' => RedactionKind::SecretReference,
        'secretreference' => RedactionKind::SecretReference,
        'keyref' => RedactionKind::SecretReference,
        'secret' => RedactionKind::Secret,
        'secrets' => RedactionKind::Secret,
        'secretvalue' => RedactionKind::Secret,
        'password' => RedactionKind::Secret,
        'passwords' => RedactionKind::Secret,
        'passwd' => RedactionKind::Secret,
        'pwd' => RedactionKind::Secret,
        'clientsecret' => RedactionKind::Secret,
        'privatekey' => RedactionKind::Secret,
        'privatekeys' => RedactionKind::Secret,
        'secretkey' => RedactionKind::Secret,
        'credential' => RedactionKind::Credential,
        'credentials' => RedactionKind::Credential,
        'dsn' => RedactionKind::Credential,
        'connectionstring' => RedactionKind::Credential,
        'authorization' => RedactionKind::Authorization,
        'authorizationdata' => RedactionKind::Authorization,
        'authorizationheader' => RedactionKind::Authorization,
        'auth' => RedactionKind::Authorization,
        'authdata' => RedactionKind::Authorization,
        'proxyauthorization' => RedactionKind::Authorization,
        'proxyauthorizationheader' => RedactionKind::Authorization,
        'cookie' => RedactionKind::Cookie,
        'cookies' => RedactionKind::Cookie,
        'setcookie' => RedactionKind::Cookie,
        'session' => RedactionKind::SessionId,
        'sessionid' => RedactionKind::SessionId,
        'sessionidentifier' => RedactionKind::SessionId,
        'sessionidentifiers' => RedactionKind::SessionId,
        'token' => RedactionKind::Token,
        'tokens' => RedactionKind::Token,
        'accesstoken' => RedactionKind::Token,
        'refreshtoken' => RedactionKind::Token,
        'idtoken' => RedactionKind::Token,
        'bearertoken' => RedactionKind::Token,
        'apikey' => RedactionKind::Token,
        'xapikey' => RedactionKind::Token,
        'accesskey' => RedactionKind::Token,
        'csrf' => RedactionKind::Token,
        'csrftoken' => RedactionKind::Token,
        'xsrf' => RedactionKind::Token,
        'xsrftoken' => RedactionKind::Token,
        'payload' => RedactionKind::Payload,
        'rawpayload' => RedactionKind::Payload,
        'body' => RedactionKind::Payload,
        'rawbody' => RedactionKind::Payload,
        'header' => RedactionKind::Payload,
        'headers' => RedactionKind::Payload,
        'rawheader' => RedactionKind::Payload,
        'rawheaders' => RedactionKind::Payload,
        'query' => RedactionKind::Payload,
        'rawquery' => RedactionKind::Payload,
        'querystring' => RedactionKind::Payload,
        'requestheader' => RedactionKind::Payload,
        'requestheaders' => RedactionKind::Payload,
        'rawrequestheader' => RedactionKind::Payload,
        'rawrequestheaders' => RedactionKind::Payload,
        'responseheader' => RedactionKind::Payload,
        'responseheaders' => RedactionKind::Payload,
        'rawresponseheader' => RedactionKind::Payload,
        'rawresponseheaders' => RedactionKind::Payload,
        'requestbody' => RedactionKind::Payload,
        'rawrequestbody' => RedactionKind::Payload,
        'requestpayload' => RedactionKind::Payload,
        'rawrequestpayload' => RedactionKind::Payload,
        'responsebody' => RedactionKind::Payload,
        'rawresponsebody' => RedactionKind::Payload,
        'responsepayload' => RedactionKind::Payload,
        'rawresponsepayload' => RedactionKind::Payload,
        'providerpayload' => RedactionKind::Payload,
        'rawproviderpayload' => RedactionKind::Payload,
        'sql' => RedactionKind::Sql,
        'rawsql' => RedactionKind::Sql,
        'sqlquery' => RedactionKind::Sql,
        'rawsqlquery' => RedactionKind::Sql,
        'sqlbindings' => RedactionKind::Sql,
        'sqlstatement' => RedactionKind::Sql,
        'email' => RedactionKind::Pii,
        'emailaddress' => RedactionKind::Pii,
        'phone' => RedactionKind::Pii,
        'phonenumber' => RedactionKind::Pii,
        'username' => RedactionKind::Pii,
        'firstname' => RedactionKind::Pii,
        'lastname' => RedactionKind::Pii,
        'fullname' => RedactionKind::Pii,
        'dateofbirth' => RedactionKind::Pii,
        'birthdate' => RedactionKind::Pii,
        'dob' => RedactionKind::Pii,
        'envvalue' => RedactionKind::EnvValue,
        'rawenvvalue' => RedactionKind::EnvValue,
        'localpath' => RedactionKind::LocalPath,
        'filepath' => RedactionKind::LocalPath,
        'absolutepath' => RedactionKind::LocalPath,
        'workingdirectory' => RedactionKind::LocalPath,
        'cwd' => RedactionKind::LocalPath,
    ];

    public function classify(string $key): ?RedactionKind
    {
        $canonical = \strtr(
            \str_replace(['-', '_', '.', ' '], '', $key),
            'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'abcdefghijklmnopqrstuvwxyz',
        );

        return self::ALIASES[$canonical] ?? null;
    }
}
