import { readdir, readFile } from 'node:fs/promises';
import { join, relative } from 'node:path';

const root = process.cwd();
const failures = [];

const requiredFiles = [
  'DdysOpen/Plugin.php',
  'DdysOpen/Action.php',
  'DdysOpen/panel.php',
  'DdysOpen/src/Helpers.php',
  'DdysOpen/src/Settings.php',
  'DdysOpen/src/Cache.php',
  'DdysOpen/src/ApiClient.php',
  'DdysOpen/src/Renderer.php',
  'DdysOpen/src/Shortcodes.php',
  'DdysOpen/src/Admin.php',
  'DdysOpen/assets/css/frontend.css',
  'DdysOpen/assets/css/admin.css',
  'DdysOpen/assets/js/admin.js',
  'DdysOpen/assets/images/icon-16.png',
  'DdysOpen/assets/images/icon-32.png',
  'DdysOpen/assets/images/icon-192.png',
  'DdysOpen/assets/images/icon-512.png',
  'README.md',
  'README.zh-CN.md',
  'LICENSE'
];

const requiredShortcodes = [
  'ddys_movies',
  'ddys_latest',
  'ddys_hot',
  'ddys_search',
  'ddys_suggest',
  'ddys_calendar',
  'ddys_movie',
  'ddys_sources',
  'ddys_related',
  'ddys_comments',
  'ddys_collections',
  'ddys_collection',
  'ddys_shares',
  'ddys_share',
  'ddys_requests',
  'ddys_activities',
  'ddys_user',
  'ddys_types',
  'ddys_genres',
  'ddys_regions',
  'ddys_request_form'
];

for (const file of requiredFiles) {
  await mustExist(file);
}

await checkPluginEntry();
await checkShortcodes();
await checkRenderer();
await checkDocs();
await checkPhpGuards();
await checkForbiddenText();

if (failures.length) {
  for (const failure of failures) {
    console.error(`- ${failure}`);
  }
  process.exit(1);
}

console.log('DDYS Typecho Plugin checks passed.');

async function mustExist(path) {
  try {
    await read(path);
  } catch {
    failures.push(`Missing required file: ${path}`);
  }
}

async function checkPluginEntry() {
  const text = await read('DdysOpen/Plugin.php');
  for (const fragment of [
    'namespace TypechoPlugin\\DdysOpen',
    'implements PluginInterface',
    'function activate',
    'function deactivate',
    'function config',
    'function personalConfig',
    "factory('Widget\\Base\\Contents')->contentEx",
    "factory('Widget\\Archive')->header",
    'Helper::addPanel',
    'Helper::addAction',
    'ACTION_TEST',
    'ACTION_FLUSH',
    'ACTION_REQUEST'
  ]) {
    if (!text.includes(fragment)) {
      failures.push(`Plugin.php missing ${fragment}`);
    }
  }

  if (!text.includes("0 === strpos($tag, 'ddys_')")) {
    failures.push('Plugin::render should accept both short and full shortcode names.');
  }
}

async function checkShortcodes() {
  const text = await read('DdysOpen/src/Shortcodes.php');
  for (const shortcode of requiredShortcodes) {
    if (!text.includes(`'${shortcode}'`)) {
      failures.push(`Missing shortcode ${shortcode}`);
    }
  }
}

async function checkRenderer() {
  const renderer = await read('DdysOpen/src/Renderer.php');
  const helpers = await read('DdysOpen/src/Helpers.php');
  for (const fragment of [
    'normalizeListItems',
    'normalizeSourceGroups',
    'resourceLinks',
    'collectionDetail',
    'shareDetail',
    'extractCalendarDays',
    'requestForm'
  ]) {
    if (!renderer.includes(fragment)) {
      failures.push(`Renderer missing ${fragment}`);
    }
  }

  if (!helpers.includes('magnet') || !helpers.includes('ed2k') || !helpers.includes('thunder')) {
    failures.push('Resource protocol allow-list is incomplete.');
  }

  if (!helpers.includes('safeReturnUrl')) {
    failures.push('Helpers should include safeReturnUrl for frontend form redirects.');
  }

  const cache = await read('DdysOpen/src/Cache.php');
  if (!cache.includes('pruneLocks') || !cache.includes('request_*.lock')) {
    failures.push('Cache should prune and flush request lock files.');
  }
}

async function checkDocs() {
  const en = await read('README.md');
  const zh = await read('README.zh-CN.md');
  if (!en.includes('[DDYS](https://ddys.io/)')) {
    failures.push('English README must use DDYS as official website anchor text.');
  }
  if (!zh.includes('[低端影视](https://ddys.io/)')) {
    failures.push('Chinese README must use 低端影视 as official website anchor text.');
  }
  if (/npm/i.test(en) || /npm/i.test(zh)) {
    failures.push('README files should not mention npm for this plugin.');
  }
}

async function checkPhpGuards() {
  const files = await listFiles(join(root, 'DdysOpen'));
  for (const file of files.filter((item) => item.endsWith('.php'))) {
    const rel = relative(root, file).replace(/\\/g, '/');
    const text = await read(rel);
    if (!text.includes("defined('__TYPECHO_ROOT_DIR__')")) {
      failures.push(`${rel} must guard direct access.`);
    }
  }
}

async function checkForbiddenText() {
  const files = await listFiles(root);
  const patterns = ['ghp' + '_', 'npm' + '_', '2026' + 'facai', 'x9k' + 'Nx', 'Do not ' + 'bundle', '不要' + '把', '浣庣', '褰辫', '涓嶈'];

  for (const file of files) {
    const rel = relative(root, file).replace(/\\/g, '/');
    if (rel === 'tools/check.mjs') {
      continue;
    }
    if (/\.(png|jpg|jpeg|webp|gif)$/i.test(rel)) {
      continue;
    }
    const text = await read(rel);
    for (const pattern of patterns) {
      if (text.includes(pattern)) {
        failures.push(`${rel} contains restricted text pattern ${pattern}`);
      }
    }
  }
}

async function read(path) {
  return readFile(join(root, path), 'utf8');
}

async function listFiles(dir) {
  const entries = await readdir(dir, { withFileTypes: true });
  const output = [];
  for (const entry of entries) {
    if (entry.name === 'node_modules' || entry.name === '.git') {
      continue;
    }
    const full = join(dir, entry.name);
    if (entry.isDirectory()) {
      output.push(...await listFiles(full));
    } else {
      output.push(full);
    }
  }
  return output;
}
