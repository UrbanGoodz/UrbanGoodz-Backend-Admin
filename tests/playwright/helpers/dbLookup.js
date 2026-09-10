// Local-environment test helper: fetches a real row id directly from the
// database, rather than scraping it out of a DataTable that renders its rows
// via AJAX after a default date filter - which makes "grab an id from the
// page" unreliable to script.
//
// Previously this hardcoded DB_NAME = 'urban_goodz_local' and assumed a bare
// `mysql` on PATH. Both assumptions broke the suite on any machine whose
// schema is named differently (script/e2e-bootstrap.sh builds
// urbangoodz_e2e_test) or where Laragon's mysql is not on PATH, and the
// failure surfaced as an opaque "Command failed" rather than anything
// pointing at configuration. It now reads the same .env the application uses,
// and falls back to the Laragon binary when mysql is not on PATH.
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const ENV_PATH = path.resolve(__dirname, '../../../.env');

function envValue(key, fallback = null) {
    try {
        const line = fs
            .readFileSync(ENV_PATH, 'utf8')
            .split('\n')
            .find((l) => l.trim().startsWith(`${key}=`));
        if (!line) return fallback;
        return line.slice(line.indexOf('=') + 1).trim().replace(/^["']|["']$/g, '') || fallback;
    } catch {
        return fallback;
    }
}

/** Prefer mysql on PATH; otherwise fall back to the Laragon install. */
function mysqlBinary() {
    if (process.env.MYSQL_BIN) return process.env.MYSQL_BIN;
    try {
        execFileSync('mysql', ['--version'], { stdio: 'ignore' });
        return 'mysql';
    } catch {
        const base = 'C:\\laragon\\bin\\mysql';
        try {
            const dir = fs.readdirSync(base).find((d) => d.startsWith('mysql-'));
            if (dir) {
                const candidate = path.join(base, dir, 'bin', 'mysql.exe');
                if (fs.existsSync(candidate)) return candidate;
            }
        } catch {
            /* fall through */
        }
        return 'mysql';
    }
}

function queryScalar(sql) {
    const args = [
        `-h${envValue('DB_HOST', '127.0.0.1')}`,
        `-P${envValue('DB_PORT', '3306')}`,
        `-u${envValue('DB_USERNAME', 'root')}`,
    ];
    const pass = envValue('DB_PASSWORD', '');
    if (pass) args.push(`-p${pass}`);
    args.push('--protocol=TCP', '-N', '-B', envValue('DB_DATABASE', 'urban_goodz_local'), '-e', sql);

    const out = execFileSync(mysqlBinary(), args, { encoding: 'utf8' }).trim();
    return out.split('\n')[0] || null;
}

function latestOrderId() {
    return queryScalar('SELECT id FROM orders ORDER BY id DESC LIMIT 1;');
}

module.exports = { latestOrderId, queryScalar };
