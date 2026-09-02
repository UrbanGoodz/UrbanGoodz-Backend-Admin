// Local-environment test helper: shells out to the mysql CLI already on
// PATH (Laragon) to fetch a real row id directly, rather than scraping it
// out of a DataTable that renders its rows via AJAX after a default date
// filter — which makes "grab an id from the page" unreliable to script.
const { execFileSync } = require('child_process');

const DB_NAME = 'urban_goodz_local';

function queryScalar(sql) {
    const out = execFileSync('mysql', ['-h127.0.0.1', '-uroot', '-N', '-B', DB_NAME, '-e', sql], {
        encoding: 'utf8',
    }).trim();
    return out.split('\n')[0] || null;
}

function latestOrderId() {
    return queryScalar('SELECT id FROM orders ORDER BY id DESC LIMIT 1;');
}

module.exports = { latestOrderId };
