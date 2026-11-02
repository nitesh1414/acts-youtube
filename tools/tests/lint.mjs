/** Parse every PHP file with token_get_all(TOKEN_PARSE) inside php-wasm. */
import path from 'node:path';
import { Php, REPO, listFiles } from './harness.mjs';

const files = listFiles(REPO).filter(f => f.endsWith('.php'));
const php = new Php();
await php.boot();
await php.uploadRepo(REPO);
await php.write('/app/files.json.b64', Buffer.from(JSON.stringify(files)).toString('base64'));

const result = await php.run(`<?php
$files = json_decode(base64_decode(file_get_contents('/app/files.json.b64')), true);
$bad = [];
foreach ($files as $rel) {
    $code = @file_get_contents('/app/' . $rel);
    if ($code === false) { $bad[] = [$rel, 'unreadable']; continue; }
    try {
        token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        $bad[] = [$rel, $e->getMessage() . ' on line ' . $e->getLine()];
    } catch (Throwable $e) {
        $bad[] = [$rel, get_class($e) . ': ' . $e->getMessage()];
    }
}
echo count($files), " files\\n", count($bad), " errors\\n";
foreach ($bad as $row) { echo "FAIL {$row[0]}: {$row[1]}\\n"; }
`);

process.stdout.write(result.out);
if (result.err) process.stdout.write(result.err);
const match = /(\d+) errors/.exec(result.out);
process.exit(match && Number(match[1]) === 0 ? 0 : 1);
