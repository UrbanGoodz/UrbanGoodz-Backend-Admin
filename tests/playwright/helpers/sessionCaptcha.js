// Local-environment test helper: the admin login form uses an image captcha
// whose phrase is stored server-side in the session file. In a real browser
// this is unsolvable without OCR. In a *local* test environment we have
// filesystem access to the same session store the app writes to, so we can
// read the phrase directly instead of trying to defeat the captcha image.
// This must never be used against staging/production — it depends on
// reading local disk session files.
const fs = require('fs');
const path = require('path');

const SESSIONS_DIR = path.resolve(__dirname, '../../../storage/framework/sessions');

/**
 * Returns the `six_captcha` phrase for the most recently written session file.
 * Call this immediately after loading the login page and before submitting
 * the form — reloading the page regenerates the phrase.
 */
function readLatestCaptchaPhrase() {
    const files = fs.readdirSync(SESSIONS_DIR)
        .map((name) => ({ name, mtime: fs.statSync(path.join(SESSIONS_DIR, name)).mtimeMs }))
        .sort((a, b) => b.mtime - a.mtime);

    if (files.length === 0) {
        throw new Error('No session files found — is the app writing sessions to file storage?');
    }

    const payload = fs.readFileSync(path.join(SESSIONS_DIR, files[0].name), 'utf8');
    const match = payload.match(/s:11:"six_captcha";s:\d+:"([^"]*)"/);
    if (!match) {
        throw new Error('six_captcha not found in latest session file: ' + files[0].name);
    }
    return match[1];
}

module.exports = { readLatestCaptchaPhrase };
