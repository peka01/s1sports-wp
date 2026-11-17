#!/usr/bin/env node
// Small client for the S1 Sports WordPress REST API. No dependencies (Node 18+).
// Credentials come from the .env file in the repo root (never committed), never from the command line.
//
//   node scripts/wp.mjs check                                   who am I, what may I do
//   node scripts/wp.mjs GET  "/wp/v2/atleter?per_page=5&_fields=id,title,slug"
//   node scripts/wp.mjs POST /wp/v2/atleter/123 '{"title":"New name"}' --write
//   node scripts/wp.mjs POST /wp/v2/atleter/123 @body.json --write
//   node scripts/wp.mjs DELETE /wp/v2/atleter/123 --write        (moves to trash)
//   node scripts/wp.mjs upload photo.jpg --write                 (adds to the media library)
//
// Flags:  --en     use the English domain (WP_URL_EN) instead of the Swedish one
//         --write  required for anything that changes the live site

import { readFileSync, existsSync } from 'node:fs';
import { basename, dirname, extname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const env = {};
const envFile = join(here, '..', '.env');
if (existsSync(envFile)) {
  for (const line of readFileSync(envFile, 'utf8').split(/\r?\n/)) {
    const m = line.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/);
    if (m && !line.trim().startsWith('#')) env[m[1]] = m[2].replace(/^(['"])(.*)\1$/, '$2');
  }
}

const args = process.argv.slice(2);
const flag = (name) => {
  const i = args.indexOf(name);
  if (i === -1) return false;
  args.splice(i, 1);
  return true;
};
const useEn = flag('--en');
const allowWrite = flag('--write');

const base = ((useEn ? env.WP_URL_EN : env.WP_URL) || 'https://s1sports.se').replace(/\/+$/, '');
const headers = { 'User-Agent': 's1sports-wp-client', Accept: 'application/json' };
const hasAuth = Boolean(env.WP_USER && env.WP_APP_PASSWORD);
if (hasAuth) {
  headers.Authorization = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');
}

function fail(message) {
  console.error(message);
  process.exit(1);
}

async function call(method, path, body, extraHeaders = {}) {
  if (method !== 'GET' && !allowWrite) fail(`${method} changes the live site. Add --write to confirm.`);
  if (method !== 'GET' && !hasAuth) fail('WP_USER and WP_APP_PASSWORD are not set in .env.');
  const url = base + '/wp-json' + (path.startsWith('/') ? path : '/' + path);
  const res = await fetch(url, { method, headers: { ...headers, ...extraHeaders }, body });
  const text = await res.text();
  let data;
  try { data = JSON.parse(text); } catch { data = text; }
  const total = res.headers.get('x-wp-total');
  console.error(`${method} ${url} -> ${res.status}` + (total ? ` (total ${total}, pages ${res.headers.get('x-wp-totalpages')})` : ''));
  return { ok: res.ok, status: res.status, data };
}

const [command, ...rest] = args;
if (!command) fail('Usage: node scripts/wp.mjs check | <GET|POST|PUT|PATCH|DELETE> <path> [json|@file] | upload <file>   [--en] [--write]');

if (command === 'check') {
  if (!hasAuth) fail('No credentials yet: fill in WP_USER and WP_APP_PASSWORD in .env.');
  const { ok, data } = await call('GET', '/wp/v2/users/me?context=edit');
  if (!ok) fail(`Login failed: ${data?.message ?? data}`);
  const caps = data.capabilities ?? {};
  const can = (c) => (caps[c] ? 'yes' : 'no');
  console.log(`Signed in to ${base} as ${data.name} (${(data.roles ?? []).join(', ')})`);
  console.log(`edit pages: ${can('edit_pages')} | publish pages: ${can('publish_pages')} | upload media: ${can('upload_files')} | edit theme: ${can('edit_themes')} | install plugins: ${can('install_plugins')}`);
} else if (command === 'upload') {
  const file = rest[0];
  if (!file || !existsSync(file)) fail('upload needs a path to an existing file.');
  const types = { '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.png': 'image/png', '.webp': 'image/webp', '.gif': 'image/gif', '.svg': 'image/svg+xml', '.pdf': 'application/pdf' };
  const type = types[extname(file).toLowerCase()] ?? 'application/octet-stream';
  const { ok, data } = await call('POST', '/wp/v2/media', readFileSync(file), {
    'Content-Type': type,
    'Content-Disposition': `attachment; filename="${basename(file).replace(/"/g, '')}"`,
  });
  console.log(JSON.stringify(ok ? { id: data.id, source_url: data.source_url } : data, null, 2));
  if (!ok) process.exit(1);
} else {
  const method = command.toUpperCase();
  if (!['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) fail(`Unknown command: ${command}`);
  const path = rest[0];
  if (!path) fail('A REST path is required, for example /wp/v2/pages');
  let body = rest[1];
  if (body?.startsWith('@')) body = readFileSync(body.slice(1), 'utf8');
  if (body) {
    try { JSON.parse(body); } catch { fail('The request body is not valid JSON.'); }
  }
  const { ok, data } = await call(method, path, body, body ? { 'Content-Type': 'application/json' } : {});
  console.log(typeof data === 'string' ? data : JSON.stringify(data, null, 2));
  if (!ok) process.exit(1);
}
