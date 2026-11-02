/**
 * Minimal PHP-in-Node harness built on php-wasm.
 *
 * The repository is copied into PHP's virtual filesystem under /app, PDO's
 * MySQL driver is swapped for an in-memory fake (fakedb.php), and an entry
 * script is executed with a simulated request. That lets the real controllers,
 * templates and SQL paths run without a MySQL server.
 *
 * Usage: node run-pages.mjs [repo-root]
 */
import { PhpNode } from 'php-wasm/PhpNode';
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const HERE = path.dirname(url.fileURLToPath(import.meta.url));
const SKIP = new Set(['.git', 'node_modules', 'uploads', 'out', 'storage']);

export const REPO = process.argv[2]
    ? path.resolve(process.argv[2])
    : path.resolve(HERE, '..', '..');

export function listFiles(root, base = root, out = []) {
    for (const entry of fs.readdirSync(base, { withFileTypes: true })) {
        if (SKIP.has(entry.name)) continue;
        const full = path.join(base, entry.name);
        if (entry.isDirectory()) {
            listFiles(root, full, out);
        } else if (entry.isFile()) {
            out.push(path.relative(root, full));
        }
    }
    return out;
}

export class Php {
    constructor() {
        this.php = new PhpNode();
        this.out = '';
        this.err = '';
        this.ready = false;
        this.dirs = new Set();
    }

    async boot() {
        if (this.ready) return this;
        await this.php.refresh();
        this.php.addEventListener('output', e => { this.out += [...e.detail].join(''); });
        this.php.addEventListener('error', e => { this.err += [...e.detail].join(''); });
        this.ready = true;
        return this;
    }

    async mkdirp(dir) {
        const parts = dir.split('/').filter(Boolean);
        let current = '';
        for (const part of parts) {
            current += '/' + part;
            if (this.dirs.has(current)) continue;
            try { await this.php.mkdir(current); } catch (e) { /* already exists */ }
            this.dirs.add(current);
        }
    }

    async uploadRepo(root = REPO, target = '/app') {
        const files = listFiles(root);
        for (const rel of files) {
            const dest = target + '/' + rel.split(path.sep).join('/');
            await this.mkdirp(path.posix.dirname(dest));
            await this.php.writeFile(dest, fs.readFileSync(path.join(root, rel)));
        }
        return files;
    }

    async write(dest, contents) {
        await this.mkdirp(path.posix.dirname(dest));
        await this.php.writeFile(dest, contents);
    }

    async read(dest) {
        try {
            const data = await this.php.readFile(dest);
            return typeof data === 'string' ? data : Buffer.from(data).toString('utf8');
        } catch (e) {
            return null;
        }
    }

    async run(code) {
        await this.boot();
        this.out = '';
        this.err = '';
        const status = await this.php.run(code);
        await new Promise(r => setImmediate(r));
        return { out: this.out, err: this.err, code: status | 0 };
    }
}

/**
 * Upload the app and replace Database::fromConfig() with the fake database.
 * The patch lives only inside the sandbox copy — the repository is untouched.
 */
export async function bootApp(root = REPO) {
    const php = new Php();
    await php.boot();
    await php.uploadRepo(root);

    const db = await php.read('/app/lib/db.php');
    if (db === null) throw new Error('lib/db.php missing in upload');
    const marker = 'return new self($dsn, DB_USER, DB_PASS);';
    if (!db.includes(marker)) throw new Error('could not patch Database::fromConfig()');
    await php.write('/app/lib/db.php', db.replace(marker,
        "require_once APP_ROOT . '/tests-support/fakedb.php'; return fake_db();"));

    await php.write('/app/tests-support/fakedb.php', fs.readFileSync(path.join(HERE, 'fakedb.php'), 'utf8'));
    await php.write('/app/tests-support/seed.php', fs.readFileSync(path.join(HERE, 'seed.php'), 'utf8'));

    return php;
}

export function assert(condition, message) {
    if (!condition) throw new Error('ASSERT: ' + message);
}

export function contains(haystack, needle, label = '') {
    if (!String(haystack).includes(needle)) {
        throw new Error('ASSERT missing ' + (label || JSON.stringify(needle)));
    }
}

export function notContains(haystack, needle, label = '') {
    if (String(haystack).includes(needle)) {
        throw new Error('ASSERT unexpected ' + (label || JSON.stringify(needle)));
    }
}
