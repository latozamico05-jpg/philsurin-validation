<?php
// Shared role + access helpers. Required by config.php (web UI) and api/bootstrap.php.
if (!function_exists('audit_log')) {
    // System activity log: WHO did WHAT at WHICH location and WHEN.
    // Writes to audit_logs (created by db-bootstrap). Wrapped in try/catch so
    // logging can never break a login or save if the table is missing.
    function audit_log(?int $userId, string $action, string $entityType = '', string $entityId = '', ?array $old = null, ?array $new = null): void
    {
        try {
            global $db;
            if (!($db instanceof PDO)) {
                return;
            }
            $q = $db->prepare('INSERT INTO audit_logs(user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$userId, $action, $entityType, $entityId, $old ? json_encode($old) : null, $new ? json_encode($new) : null, $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)]);
        } catch (Throwable $ignore) {
        }
    }
}
if (!function_exists('philsurin_role')) {
    function philsurin_role(?array $user = null): string
    {
        $user = $user ?? ($_SESSION['user'] ?? []);
        return strtolower((string) ($user['role'] ?? 'viewer'));
    }
}
if (!function_exists('philsurin_allowed_roles')) {
    function philsurin_allowed_roles(): array
    {
        return ['admin', 'validator', 'validator_germination', 'validator_field', 'validator_harvest', 'viewer'];
    }
}
if (!function_exists('philsurin_validator_roles')) {
    function philsurin_validator_roles(): array
    {
        return ['validator', 'validator_germination', 'validator_field', 'validator_harvest'];
    }
}
if (!function_exists('philsurin_is_admin')) {
    function philsurin_is_admin(?array $user = null): bool
    {
        return philsurin_role($user) === 'admin';
    }
}
if (!function_exists('philsurin_can_encode')) {
    // Encoding (index.php + mobile encode + API validation endpoints).
    function philsurin_can_encode(?array $user = null): bool
    {
        return in_array(philsurin_role($user), array_merge(['admin'], philsurin_validator_roles()), true);
    }
}
if (!function_exists('philsurin_audit_id_column')) {
    // audit_logs is keyed differently depending on where the table came from:
    // the production import uses `audit_id`, while db-bootstrap.php creates `id`.
    // Reads that assume `id` throw "Unknown column", which the page's try/catch
    // swallows — silently hiding every real audit row. Detect the actual primary
    // key once per request so the feed works on either schema.
    function philsurin_audit_id_column(PDO $db): string
    {
        static $column = null;
        if ($column !== null) {
            return $column;
        }
        $column = 'id';
        try {
            foreach ($db->query('SHOW COLUMNS FROM audit_logs') as $col) {
                if (strcasecmp((string) ($col['Key'] ?? ''), 'PRI') === 0) {
                    $column = (string) $col['Field'];
                    break;
                }
            }
        } catch (Throwable $ignore) {
        }
        return $column;
    }
}
if (!function_exists('philsurin_can_audit')) {
    // Audit trail (audit.php + mobile audit + API audit endpoints).
    // Admin-only: system activity reveals who used the system and from where.
    function philsurin_can_audit(?array $user = null): bool
    {
        return philsurin_is_admin($user);
    }
}
if (!function_exists('philsurin_can_export')) {
    // Report downloads (export.php + mobile download buttons).
    function philsurin_can_export(?array $user = null): bool
    {
        return in_array(philsurin_role($user), array_merge(['admin'], philsurin_validator_roles()), true);
    }
}
if (!function_exists('philsurin_require_encode')) {
    function philsurin_require_encode(?array $user = null): void
    {
        if (!philsurin_can_encode($user)) {
            http_response_code(403);
            exit('Your account can view records but cannot save encoding data.');
        }
    }
}
if (!function_exists('philsurin_require_audit')) {
    function philsurin_require_audit(?array $user = null): void
    {
        if (!philsurin_can_audit($user)) {
            http_response_code(403);
            exit('Your account does not have access to the audit trail.');
        }
    }
}
if (!function_exists('philsurin_require_export')) {
    function philsurin_require_export(?array $user = null): void
    {
        if (!philsurin_can_export($user)) {
            http_response_code(403);
            exit('Your account does not have download access.');
        }
    }
}
if (!function_exists('philsurin_allowed_stages')) {
    // Stage scope per validator type: germination validators encode 01 GRADING
    // only, field validators encode 02-04 GRADING, harvest validators encode
    // HARVEST. Full validators and admins encode every stage.
    function philsurin_allowed_stages(?array $user = null): array
    {
        $role = philsurin_role($user);
        if ($role === 'admin' || $role === 'validator') {
            return ['01 GRADING', '02 GRADING', '03 GRADING', '04 GRADING', 'HARVEST'];
        }
        if ($role === 'validator_germination') {
            return ['01 GRADING'];
        }
        if ($role === 'validator_field') {
            return ['02 GRADING', '03 GRADING', '04 GRADING'];
        }
        if ($role === 'validator_harvest') {
            return ['HARVEST'];
        }
        return [];
    }
}
if (!function_exists('philsurin_can_encode_stage')) {
    function philsurin_can_encode_stage(string $stage, ?array $user = null): bool
    {
        if (!philsurin_can_encode($user)) {
            return false;
        }
        $stage = strtoupper(trim((string) preg_replace('/\s+/', ' ', str_replace('_', ' ', $stage))));
        foreach (philsurin_allowed_stages($user) as $allowed) {
            if ($stage === $allowed) {
                return true;
            }
            if ($allowed === 'HARVEST' && strpos($stage, 'HARVEST') !== false) {
                return true;
            }
        }
        return false;
    }
}
