#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');

const manifestPath = process.argv[2] || process.env.MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT || '';
const outputDir = process.argv[3] || process.env.MAA_ADAPTER_VISUAL_ACCEPTANCE_REPORT_DIR || path.join('build', 'visual-acceptance');

if (!manifestPath) {
  console.error('Usage: node scripts/gutenberg-visual-acceptance.mjs <manifest.json> [output-dir]');
  process.exit(2);
}

const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const fixtures = Array.isArray(manifest.fixtures) ? manifest.fixtures : [];
if (fixtures.length === 0) {
  console.error(`No fixtures found in ${manifestPath}. Run smoke with MAA_ADAPTER_KEEP_VISUAL_ACCEPTANCE_FIXTURES=1.`);
  process.exit(1);
}

fs.mkdirSync(outputDir, { recursive: true });

function safeName(value) {
  return String(value || 'fixture').replace(/[^A-Za-z0-9_.-]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
}

function fail(message, details = {}) {
  return { ok: false, message, details };
}

function pass(message, details = {}) {
  return { ok: true, message, details };
}

async function pageSignals(page) {
  return page.evaluate(() => {
    const viewportWidth = document.documentElement.clientWidth;
    const scrollWidth = Math.max(document.documentElement.scrollWidth, document.body ? document.body.scrollWidth : 0);

    const visible = (element) => {
      const style = window.getComputedStyle(element);
      const rect = element.getBoundingClientRect();
      return style.visibility !== 'hidden' && style.display !== 'none' && rect.width > 0 && rect.height > 0;
    };

    const headings = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6')).map((element) => {
      const rect = element.getBoundingClientRect();
      return {
        tag: element.tagName.toLowerCase(),
        text: (element.textContent || '').replace(/\s+/g, ' ').trim(),
        visible: visible(element),
        left: rect.left,
        right: rect.right,
      };
    });

    const images = Array.from(document.querySelectorAll('img')).map((element) => {
      const rect = element.getBoundingClientRect();
      return {
        src: element.currentSrc || element.src || '',
        alt: element.getAttribute('alt') || '',
        complete: element.complete,
        naturalWidth: element.naturalWidth,
        naturalHeight: element.naturalHeight,
        visible: visible(element),
        left: rect.left,
        right: rect.right,
      };
    });

    const controls = Array.from(document.querySelectorAll('a,button,.wp-block-button__link')).map((element) => {
      const rect = element.getBoundingClientRect();
      return {
        tag: element.tagName.toLowerCase(),
        text: (element.textContent || '').replace(/\s+/g, ' ').trim(),
        visible: visible(element),
        left: rect.left,
        right: rect.right,
        width: rect.width,
      };
    }).filter((item) => item.visible && item.text !== '');

    const paddedSections = Array.from(document.querySelectorAll('.wp-block-group,.wp-block-media-text,.wp-block-columns,section,main > *')).filter((element) => {
      if (!visible(element)) {
        return false;
      }
      const style = window.getComputedStyle(element);
      return (parseFloat(style.paddingTop) || 0) + (parseFloat(style.paddingBottom) || 0) >= 48;
    }).length;

    const backgrounds = new Set();
    Array.from(document.querySelectorAll('body,main,.wp-block-group,.wp-block-media-text,.wp-block-columns')).forEach((element) => {
      const color = window.getComputedStyle(element).backgroundColor;
      if (color && color !== 'rgba(0, 0, 0, 0)' && color !== 'transparent') {
        backgrounds.add(color);
      }
    });

    return {
      title: document.title,
      isNotFound:
        document.body.classList.contains('error404') ||
        document.title.includes('Page not found') ||
        document.title.includes('Not Found') ||
        document.title.includes('未找到页面') ||
        document.querySelector('main .wp-block-query-no-results') !== null,
      viewportWidth,
      scrollWidth,
      horizontalOverflow: scrollWidth > viewportWidth + 1,
      headings,
      images,
      controls,
      paddedSections,
      backgroundColorCount: backgrounds.size,
    };
  });
}

function evaluateFixtureViewport(fixture, viewport, signals) {
  const checks = [];
  checks.push(signals.isNotFound ? fail('front end opened a not-found page instead of the fixture', { title: signals.title, front_end_url: fixture.front_end_url }) : pass('front end opened the fixture page'));
  checks.push(signals.horizontalOverflow ? fail('front end has horizontal overflow', { scrollWidth: signals.scrollWidth, viewportWidth: signals.viewportWidth }) : pass('front end has no horizontal overflow'));

  const visibleHeadings = signals.headings.filter((heading) => heading.visible);
  checks.push(visibleHeadings.length > 0 ? pass('visible headings exist', { count: visibleHeadings.length }) : fail('no visible headings found'));

  const emptyHeadings = visibleHeadings.filter((heading) => heading.text === '');
  checks.push(emptyHeadings.length === 0 ? pass('visible headings are non-empty') : fail('visible headings include empty text', { emptyHeadings }));

  const visibleImages = signals.images.filter((image) => image.visible);
  checks.push(visibleImages.length > 0 ? pass('visible images exist', { count: visibleImages.length }) : fail('no visible images found'));

  const brokenImages = visibleImages.filter((image) => !image.src || !image.complete || image.naturalWidth <= 0 || image.naturalHeight <= 0);
  checks.push(brokenImages.length === 0 ? pass('visible images loaded') : fail('visible images include broken sources', { brokenImages }));

  const imagesWithoutAlt = visibleImages.filter((image) => image.alt.trim() === '');
  checks.push(imagesWithoutAlt.length === 0 ? pass('visible images have alt text') : fail('visible images include missing alt text', { imagesWithoutAlt }));

  const controls = signals.controls.filter((control) => control.text.length > 0);
  checks.push(controls.length > 0 ? pass('visible CTA/control text exists', { count: controls.length }) : fail('no visible CTA/control text found'));

  const overflowingControls = controls.filter((control) => control.left < -1 || control.right > signals.viewportWidth + 1);
  checks.push(overflowingControls.length === 0 ? pass('visible controls stay within viewport') : fail('visible controls overflow viewport', { overflowingControls }));

  const requiredPaddedSections = fixture.fixture_type === 'pattern_page_plan' ? 4 : 2;
  checks.push(signals.paddedSections >= requiredPaddedSections ? pass('key sections have visible spacing', { paddedSections: signals.paddedSections }) : fail('too few visibly padded sections', { paddedSections: signals.paddedSections, requiredPaddedSections }));

  const warnings = [];
  if (signals.backgroundColorCount < 2) {
    warnings.push({
      code: 'low_background_variety',
      message: 'Only one visible background color was detected; the page may feel visually flat.',
      backgroundColorCount: signals.backgroundColorCount,
    });
  }
  if (viewport.width <= 430 && visibleHeadings.some((heading) => heading.right > signals.viewportWidth + 1 || heading.left < -1)) {
    warnings.push({
      code: 'mobile_heading_overflow',
      message: 'At least one mobile heading reaches outside the viewport.',
    });
  }

  return {
    ok: checks.every((check) => check.ok),
    fixture_type: fixture.fixture_type,
    post_id: fixture.post_id,
    viewport,
    checks,
    warnings,
    signals: {
      headingCount: visibleHeadings.length,
      imageCount: visibleImages.length,
      controlCount: controls.length,
      paddedSections: signals.paddedSections,
      backgroundColorCount: signals.backgroundColorCount,
      isNotFound: signals.isNotFound,
      scrollWidth: signals.scrollWidth,
      viewportWidth: signals.viewportWidth,
    },
  };
}

async function maybeCheckEditor(page, fixture) {
  const user = process.env.WP_ADMIN_USER || '';
  const passValue = process.env.WP_ADMIN_PASSWORD || '';
  if (!fixture.block_editor_url || !user || !passValue) {
    return {
      ok: true,
      skipped: true,
      reason: 'Set WP_ADMIN_USER and WP_ADMIN_PASSWORD to check the block editor.',
    };
  }

  await page.goto(fixture.block_editor_url, { waitUntil: 'domcontentloaded' });
  if (page.url().includes('wp-login.php')) {
    await page.locator('#user_login').fill(user);
    await page.locator('#user_pass').fill(passValue);
    await Promise.all([
      page.waitForLoadState('domcontentloaded'),
      page.locator('#wp-submit').click(),
    ]);
    await page.goto(fixture.block_editor_url, { waitUntil: 'domcontentloaded' });
  }
  await page.waitForTimeout(2500);
  const bodyText = await page.locator('body').innerText({ timeout: 10000 }).catch(() => '');
  const invalidNeedles = [
    'This block contains unexpected or invalid content',
    'Attempt Block Recovery',
    'Resolve',
    '区块包含未预料的或无效的内容',
    '尝试恢复',
  ];
  const matched = invalidNeedles.filter((needle) => bodyText.includes(needle));
  return matched.length === 0
    ? { ok: true, skipped: false, message: 'block editor did not show invalid block recovery prompt' }
    : { ok: false, skipped: false, message: 'block editor showed invalid block recovery prompt', matched };
}

const browserLaunchOptions = { headless: true };
if (process.env.MAA_ADAPTER_VISUAL_ACCEPTANCE_BROWSER_CHANNEL) {
  browserLaunchOptions.channel = process.env.MAA_ADAPTER_VISUAL_ACCEPTANCE_BROWSER_CHANNEL;
} else if (process.platform === 'darwin') {
  browserLaunchOptions.channel = 'chrome';
}

const browser = await chromium.launch(browserLaunchOptions);
const results = [];

try {
  for (const fixture of fixtures) {
    const viewports = Array.isArray(fixture.viewports) && fixture.viewports.length > 0
      ? fixture.viewports
      : manifest.viewports || [];
    for (const viewport of viewports) {
      const context = await browser.newContext({
        viewport: { width: Number(viewport.width), height: Number(viewport.height) },
        deviceScaleFactor: 1,
      });
      const page = await context.newPage();
      await page.goto(fixture.front_end_url, { waitUntil: 'networkidle' });
      const signals = await pageSignals(page);
      const screenshotName = `${safeName(fixture.fixture_type)}-${safeName(fixture.post_id)}-${safeName(viewport.name)}-${viewport.width}x${viewport.height}.png`;
      const screenshotPath = path.join(outputDir, screenshotName);
      await page.screenshot({ path: screenshotPath, fullPage: true });
      const result = evaluateFixtureViewport(fixture, viewport, signals);
      result.front_end_url = fixture.front_end_url;
      result.screenshot = screenshotPath;
      results.push(result);
      await context.close();
    }

    const editorContext = await browser.newContext();
    const editorPage = await editorContext.newPage();
    const editorResult = await maybeCheckEditor(editorPage, fixture);
    results.push({
      ok: editorResult.ok,
      fixture_type: fixture.fixture_type,
      post_id: fixture.post_id,
      editor: true,
      block_editor_url: fixture.block_editor_url,
      ...editorResult,
    });
    await editorContext.close();
  }
} finally {
  await browser.close();
}

const report = {
  generated_at: new Date().toISOString(),
  manifest_path: manifestPath,
  output_dir: outputDir,
  fixture_count: fixtures.length,
  result_count: results.length,
  ok: results.every((result) => result.ok),
  warning_count: results.reduce((count, result) => count + (Array.isArray(result.warnings) ? result.warnings.length : 0), 0),
  results,
};

const reportPath = path.join(outputDir, 'report.json');
fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));

for (const result of results) {
  if (result.editor && result.skipped) {
    console.log(`[skip] editor ${result.fixture_type}#${result.post_id}: ${result.reason}`);
    continue;
  }
  const target = result.editor ? 'editor' : `${result.viewport.name} ${result.viewport.width}x${result.viewport.height}`;
  console.log(`[${result.ok ? 'ok' : 'fail'}] ${result.fixture_type}#${result.post_id} ${target}`);
  if (Array.isArray(result.warnings)) {
    for (const warning of result.warnings) {
      console.log(`[warn] ${result.fixture_type}#${result.post_id} ${target}: ${warning.code}`);
    }
  }
}

console.log(`Visual acceptance report: ${reportPath}`);
process.exit(report.ok ? 0 : 1);
