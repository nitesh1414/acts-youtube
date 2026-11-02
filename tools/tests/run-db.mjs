/** Run tools/tests/tests/db.php in php-wasm. */
import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';
import { bootApp, REPO } from './harness.mjs';

const HERE = path.dirname(url.fileURLToPath(import.meta.url));
const php = await bootApp(REPO);
await php.write('/app/tests-support/tests-db.php', fs.readFileSync(path.join(HERE, 'tests/db.php'), 'utf8'));

const result = await php.run(`<?php
$_SERVER['HTTP_USER_AGENT'] = 'Harness/1.0';
require '/app/tests-support/tests-db.php';
`);
process.stdout.write(result.out);
if (result.err.trim()) process.stdout.write('\nSTDERR:\n' + result.err);
const match = /(\d+) failed/.exec(result.out);
process.exit(match && Number(match[1]) === 0 ? 0 : 1);
