#!/usr/bin/env node
// Read-only check of the live site in both languages, to run right after uploading the
// theme and plugin. It only reads public pages (plus the plugin list when .env has
// credentials) and never changes anything.
//
//   node scripts/check-live.mjs
//
// Exit code 1 when a check fails.

import { readFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const themeVersion = readFileSync(join(root, 's1sports-theme/style.css'), 'utf8').match(/^Version:\s*(.+)$/m)[1].trim();
const pluginVersion = readFileSync(join(root, 's1sports-content/s1sports-content.php'), 'utf8').match(/Version:\s*([\d.]+)/)[1];

const sites = {
  sv: {
    base: 'https://s1sports.se', other: 's1sports.co',
    pages: ['/', '/junior-utveckling/', '/vara-tjanster/', '/vara-idrottare/', '/om-oss/', '/kontakt/', '/boka-mote/', '/professionell-radgivning/', '/college-rekrytering/'],
    dropdown: ['Collegerekrytering', 'Professionell rådgivning', 'Juniorutveckling'],
    upcoming: 'Kommande', booking: '/boka-mote/', junior: '/junior-utveckling/',
  },
  en: {
    base: 'https://s1sports.co', other: 's1sports.se',
    pages: ['/', '/junior-development/', '/our-services/', '/our-athletes/', '/about-us/', '/contact/', '/book-a-meeting/', '/pro-consulting/', '/college-recruiting/'],
    dropdown: ['College recruiting', 'Professional guidance', 'Junior development'],
    upcoming: 'Upcoming', booking: '/book-a-meeting/', junior: '/junior-development/',
  },
};

let failures = 0;
const ok = (msg) => console.log('  ok    ' + msg);
const bad = (msg) => { failures += 1; console.log('  FAIL  ' + msg); };
const check = (cond, msg) => (cond ? ok(msg) : bad(msg));
const text = (s) => s.replace(/<[^>]+>/g, '').replace(/&amp;/g, '&').replace(/&#8217;/g, "'").replace(/\s+/g, ' ').trim();

async function get(url) {
  const res = await fetch(url + (url.includes('?') ? '&' : '?') + 'nocache=' + Date.now(), { headers: { 'User-Agent': 's1sports-check' }, redirect: 'follow' });
  return { status: res.status, body: await res.text() };
}

console.log(`Expecting theme ${themeVersion} and plugin ${pluginVersion}\n`);

for (const [lang, site] of Object.entries(sites)) {
  console.log(`== ${site.base} (${lang})`);
  for (const path of site.pages) {
    const { status, body } = await get(site.base + path);
    const label = path;
    if (status !== 200) { bad(`${label} returns ${status}`); continue; }
    const problems = [];
    const htmlLang = (body.match(/<html[^>]*lang="([^"]+)"/) || [])[1] || '';
    if (!htmlLang.startsWith(lang)) problems.push(`html lang is "${htmlLang}"`);
    if (/<b>(Fatal error|Warning|Notice|Deprecated)<\/b>|critical error|kritiskt fel/i.test(body)) problems.push('PHP error on page');
    if (!body.includes(`mirror.css?ver=${themeVersion}`)) problems.push(`not served by theme ${themeVersion}`);
    const items = [...body.matchAll(/class="s1-submenu__link"[^>]*>([^<]*)</g)].map((m) => text(m[1]));
    if (items.join('|') !== site.dropdown.join('|')) problems.push(`dropdown is [${items.join(', ')}]`);
    const switcher = [...body.matchAll(/class="wpml-ls-link"[^>]*href="([^"]+)"|href="([^"]+)"[^>]*class="wpml-ls-link"/g)].map((m) => m[1] || m[2]);
    if (!switcher.some((href) => href.includes(site.other))) problems.push('language switcher does not link to ' + site.other);
    if (lang === 'sv' && /(>\s*)(College rekrytering|Junior utveckling)(\s*<)/.test(body)) problems.push('two-word spelling still on page');
    check(problems.length === 0, `${label}${problems.length ? ': ' + problems.join('; ') : ''}`);
  }

  // Development pages: found through the public REST API by their template.
  const list = await get(`${site.base}/wp-json/wp/v2/pages?per_page=100&_fields=link,template,title`);
  const devPages = list.status === 200 ? JSON.parse(list.body).filter((p) => p.template === 'template-development.php') : [];
  if (!devPages.length) {
    console.log('  info  no published Development pages yet in this language');
  }
  for (const page of devPages) {
    const { status, body } = await get(page.link);
    const problems = [];
    if (status !== 200) problems.push(`returns ${status}`);
    if (!body.includes('class="s1d-h1"')) problems.push('Development layout missing');
    if (/<b>(Fatal error|Warning|Notice|Deprecated)<\/b>/.test(body)) problems.push('PHP error on page');
    const events = (body.match(/class="s1d-card[ "]/g) || []).length;
    if (events && !body.includes(site.upcoming)) problems.push(`labels not in ${lang}`);
    const register = (body.match(/href="([^"]*s1_event=[^"]*)"/) || [])[1] || '';
    if (register && !register.includes(site.base.replace('https://', '') + site.booking)) problems.push('"register interest" links to ' + register.split('?')[0]);
    check(problems.length === 0, `${new URL(page.link).pathname} (${events} events)${problems.length ? ': ' + problems.join('; ') : ''}`);
  }
  const junior = await get(site.base + site.junior);
  const cards = (junior.body.match(/class="s1d-sport"/g) || []).length;
  check(cards === devPages.length, `${site.junior} shows ${cards} sport card(s) for ${devPages.length} published Development page(s)`);
  console.log('');
}

// Plugin version, when the API credentials in .env are available.
const envFile = join(root, '.env');
if (existsSync(envFile)) {
  const env = Object.fromEntries(readFileSync(envFile, 'utf8').split(/\r?\n/).map((l) => l.match(/^\s*([A-Z0-9_]+)\s*=\s*(.*?)\s*$/)).filter(Boolean).map((m) => [m[1], m[2]]));
  if (env.WP_USER && env.WP_APP_PASSWORD) {
    const auth = 'Basic ' + Buffer.from(`${env.WP_USER}:${env.WP_APP_PASSWORD}`).toString('base64');
    const res = await fetch('https://s1sports.se/wp-json/wp/v2/plugins?_fields=plugin,status,version', { headers: { Authorization: auth } });
    const plugin = res.ok ? (await res.json()).find((p) => p.plugin.startsWith('s1sports-content/')) : null;
    check(plugin && plugin.status === 'active' && plugin.version === pluginVersion, `plugin s1sports-content is ${plugin ? plugin.status + ' ' + plugin.version : 'not found'}`);
  }
}

console.log(failures ? `\n${failures} check(s) failed.` : '\nAll checks passed.');
process.exitCode = failures ? 1 : 0;
