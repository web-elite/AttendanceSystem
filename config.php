<?php
/**
 * App configuration.
 *
 * Values here are defaults; a project-root `.env` file (if present) overrides
 * the mapped keys. `.env` is parsed by a tiny built-in loader (no package, per
 * the YAGNI rule in AGENTS.md) — it is NOT a secrets store for the DB, only for
 * app-level settings.
 */

// ── Minimal .env loader ──────────────────────────────────────────────
// Reads KEY=VALUE lines, ignores blank lines and `#` comments, strips optional
// surrounding quotes, and exports into $env. Missing file → empty array.
$env = [];
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $val = trim(substr($line, $eq + 1));
        // Strip one pair of surrounding quotes, if present.
        if (strlen($val) >= 2 && ($val[0] === '"' || $val[0] === "'")
            && $val[strlen($val) - 1] === $val[0]) {
            $val = substr($val, 1, -1);
        }
        $env[$key] = $val;
    }
}

/**
 * Returns the `.env` value for $key, or $default if absent/empty.
 */
$envGet = static fn (string $key, string $default): string =>
    ($env[$key] ?? '') !== '' ? $env[$key] : $default;

// Resolve the SQLite path: DB_DATABASE may be a bare relative path
// ("database/app.sqlite") → join with project root; or an absolute path.
$dbRel = $envGet('DB_DATABASE', 'database/app.sqlite');
$dbPath = str_starts_with($dbRel, '/') ? $dbRel : __DIR__ . '/' . $dbRel;

return [
    'db_path'        => $dbPath,
    'app_url'        => $envGet('APP_URL', 'http://localhost:8080'),
    'app_name'       => 'حضور و غیاب',
    'timezone'       => 'Asia/Tehran',
    'secret_key'     => $envGet('APP_SECRET', 'change-me-in-production'),
    'session_name'   => 'crm_session',
    'session_lifetime' => 3600,
    'max_upload_mb'  => 2,
    // Expose the parsed env for anything that needs other keys later.
    'env'            => $env,
];
