/** Run php tools/build-schema.php inside php-wasm and copy the result back. */
import fs from 'node:fs';
import path from 'node:path';
import { Php, REPO } from './harness.mjs';

const php = new Php();
await php.boot();
await php.uploadRepo(REPO);

const result = await php.run(`<?php chdir('/app'); require '/app/tools/build-schema.php';`);
process.stdout.write(result.out);
if (result.err.trim()) process.stdout.write('\nSTDERR:\n' + result.err);

const sql = await php.read('/app/sql/schema.sql');
if (sql === null) {
    console.log('no generated file');
    process.exit(1);
}
const target = path.join(REPO, 'sql/schema.sql');
if (fs.readFileSync(target, 'utf8') !== sql) {
    fs.writeFileSync(target, sql);
    console.log('sql/schema.sql updated');
} else {
    console.log('sql/schema.sql already current');
}
process.exit(/Wrote/.test(result.out) ? 0 : 1);
