import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';

test('plugin uses Typecho 1.3 plugin structure', async () => {
  const text = await readFile('DdysOpen/Plugin.php', 'utf8');

  assert.match(text, /namespace TypechoPlugin\\DdysOpen/);
  assert.match(text, /implements PluginInterface/);
  assert.match(text, /contentEx/);
  assert.match(text, /addPanel/);
  assert.match(text, /addAction/);
  assert.match(text, /0 === strpos\(\$tag, 'ddys_'\)/);
});

test('plugin exposes the full shortcode surface', async () => {
  const text = await readFile('DdysOpen/src/Shortcodes.php', 'utf8');
  const shortcodes = [...text.matchAll(/'(ddys_[a-z_]+)'\s*=>/g)].map((match) => match[1]);
  const unique = [...new Set(shortcodes.filter((item) => item.startsWith('ddys_')))];

  assert.equal(unique.length, 21);
  assert.ok(unique.includes('ddys_request_form'));
  assert.ok(unique.includes('ddys_sources'));
  assert.ok(unique.includes('ddys_collection'));
});

test('renderer covers nested DDYS API shapes', async () => {
  const renderer = await readFile('DdysOpen/src/Renderer.php', 'utf8');
  const helpers = await readFile('DdysOpen/src/Helpers.php', 'utf8');

  assert.match(renderer, /normalizeListItems/);
  assert.match(renderer, /normalizeSourceGroups/);
  assert.match(renderer, /resourceLinks/);
  assert.match(renderer, /extractCalendarDays/);
  assert.match(helpers, /magnet/);
  assert.match(helpers, /ed2k/);
  assert.match(helpers, /thunder/);
  assert.match(helpers, /safeReturnUrl/);
});

test('cache handles request lock cleanup', async () => {
  const cache = await readFile('DdysOpen/src/Cache.php', 'utf8');

  assert.match(cache, /pruneLocks/);
  assert.match(cache, /request_\*\.lock/);
});

test('readme uses language-specific official website anchor text', async () => {
  const en = await readFile('README.md', 'utf8');
  const zh = await readFile('README.zh-CN.md', 'utf8');
  const mojibakePattern = new RegExp([
    '\u6d63\u5ea3',
    '\u8930\u8fab',
    '\u6d93\u5d88'
  ].join('|'));

  assert.match(en, /\[DDYS\]\(https:\/\/ddys\.io\/\)/);
  assert.match(zh, /\[低端影视\]\(https:\/\/ddys\.io\/\)/);
  assert.doesNotMatch(zh, mojibakePattern);
});
