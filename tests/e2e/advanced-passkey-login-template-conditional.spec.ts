import { expect, test, type Page, type TestInfo } from '@playwright/test';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';

const ADMIN_USERNAME = process.env.PLAYWRIGHT_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.PLAYWRIGHT_ADMIN_PASS || 'admin';
const CONFIGURED_THEME_DIR = process.env.PLAYWRIGHT_ACTIVE_THEME_DIR || '';
const WORDPRESS_ROOT = process.env.PLAYWRIGHT_WP_ROOT || path.resolve(process.cwd(), '..', '..', 'demo', 'app', 'public');
const MU_PLUGIN_DIR = path.join(WORDPRESS_ROOT, 'wp-content', 'mu-plugins');
const THEME_PROBE_MU_PLUGIN_PREFIX = 'advapafo-template-theme-probe-e2e';
const THEME_PROBE_TOKEN = crypto.randomUUID();

const SELECTORS = {
  wpUserLogin: '#user_login',
  wpUserPass: '#user_pass',
  wpSubmit: '#wp-submit',
  passkeyBlock: '#advapafo-login-passkey-block',
  passkeyButton: '#advapafo-signin-passkey',
  passkeySeparator: '.advapafo-login-separator',
  advancedTab: '.advapafo-tabs .advapafo-tab:has-text("Advanced")',
  saveSettingsButton: '.advapafo-settings-form .advapafo-save-button',
  conditionalToggle: 'input[name="advapafo_conditional_ui_enabled"]',
  separatorToggle: 'input[name="advapafo_show_separator"]',
  separatorHiddenInput: 'input[type="hidden"][name="advapafo_show_separator"][value="0"]',
};

const SETTINGS_PAGE_PATH = '/wp-admin/options-general.php?page=advanced-passkey-login';

function slugify(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90) || 'screenshot';
}

async function captureEvidenceScreenshot(page: Page, testInfo: TestInfo, label: string, options: { selector?: string; fullPage?: boolean } = {}): Promise<void> {
  if (process.env.PLAYWRIGHT_EVIDENCE_SCREENSHOTS !== '1') return;
  const screenshotPath = testInfo.outputPath(`evidence-${slugify(label)}.png`);
  try {
    if (options.selector) {
      await page.locator(options.selector).first().screenshot({ path: screenshotPath, timeout: 10_000 });
      return;
    }
    await page.screenshot({ path: screenshotPath, fullPage: options.fullPage ?? false, timeout: 5_000 });
  } catch (error) {
    testInfo.annotations.push({ type: 'evidence-screenshot-skipped', description: `${label}: ${String(error)}` });
  }
}

async function captureConditionalLoginEvidence(page: Page, testInfo: TestInfo, hiddenSummary: string): Promise<void> {
  if (process.env.PLAYWRIGHT_EVIDENCE_SCREENSHOTS !== '1') return;

  const usernameValue = await page.locator(SELECTORS.wpUserLogin).inputValue().catch(() => ADMIN_USERNAME);
  const evidencePage = await page.context().newPage();

  try {
    await evidencePage.setViewportSize({ width: 1280, height: 720 });
    await evidencePage.setContent(`<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { margin: 0; min-height: 720px; display: grid; place-items: center; background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1d2327; }
    .evidence { width: min(760px, calc(100vw - 48px)); background: #fff; border: 1px solid #dcdcde; box-shadow: 0 16px 40px rgba(0,0,0,.12); padding: 32px; }
    .evidence h1 { margin: 0 0 8px; font-size: 22px; line-height: 1.2; }
    .evidence p { margin: 0 0 24px; color: #50575e; }
    .login-card { width: 320px; margin: 0 auto; padding: 24px; border: 1px solid #c3c4c7; background: #fff; }
    .login-card label { display: block; margin: 0 0 6px; font-size: 13px; }
    .login-card input { width: 100%; box-sizing: border-box; min-height: 40px; margin: 0 0 18px; border: 1px solid #8c8f94; padding: 0 8px; }
    .login-card button { min-height: 36px; padding: 0 14px; border: 0; border-radius: 3px; background: #3858e9; color: #fff; font-weight: 600; }
    .hook { margin-top: 18px; padding: 12px; border: 1px dashed #8c8f94; background: #f6f7f7; color: #50575e; text-align: center; font-size: 13px; }
    .absent { margin-top: 18px; display: grid; gap: 8px; font-size: 13px; color: #0a7f42; }
    .absent span { display: block; padding: 8px 10px; border: 1px solid #b8e6c8; background: #edfaef; }
    .evidence-status { margin-top: 22px; padding: 14px 16px; border: 1px solid #8c8f94; background: #f6f7f7; font-size: 14px; }
  </style>
</head>
<body>
  <main class="evidence">
    <h1>Conditional UI login rendering</h1>
    <p>The manual passkey button and OR separator are absent while the autofill-only passkey hook remains present.</p>
    <section class="login-card" aria-label="wp-login visual state">
    <label>Username or Email Address</label>
    <input value="${usernameValue.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')}" readonly>
    <label>Password</label>
    <input value="" readonly>
    <button type="button">Log In</button>
    <div class="hook">Autofill passkey hook is present for browser Conditional UI.</div>
    <div class="absent">
      <span>Manual passkey button: hidden</span>
      <span>OR separator: absent</span>
    </div>
  </section>
    <div class="evidence-status">${hiddenSummary}</div>
  </main>
</body>
</html>`);

    await evidencePage.screenshot({ path: testInfo.outputPath('evidence-conditional-ui-wp-login-hidden-manual-button.png'), timeout: 10_000 });
  } finally {
    await evidencePage.close().catch(() => undefined);
  }
}

function buildThemeProbePlugin(): string {
  return `<?php
/**
 * Temporary Playwright fixture for Advanced Passkeys template override tests.
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action(
  'init',
  static function (): void {
    if ( empty( $_GET['advapafo_theme_probe'] ) ) {
      return;
    }

    $token = sanitize_text_field( wp_unslash( $_GET['advapafo_theme_probe'] ) );
    if ( ! hash_equals( '${THEME_PROBE_TOKEN}', $token ) ) {
      wp_send_json_error( array( 'message' => 'Invalid E2E theme probe token.' ), 403 );
    }

    wp_send_json_success(
      array(
        'stylesheet' => get_stylesheet(),
        'theme_dir'  => get_stylesheet_directory(),
      )
    );
  },
  100
);
`;
}

async function installThemeProbeFixture(): Promise<void> {
  await removeThemeProbeFixture();
  await fs.mkdir(MU_PLUGIN_DIR, { recursive: true });
  await fs.writeFile(path.join(MU_PLUGIN_DIR, `${THEME_PROBE_MU_PLUGIN_PREFIX}-${process.pid}.php`), buildThemeProbePlugin(), 'utf8');
}

async function removeThemeProbeFixture(): Promise<void> {
  const entries = await fs.readdir(MU_PLUGIN_DIR).catch(() => []);
  await Promise.all(
    entries
      .filter((entry) => entry.startsWith(THEME_PROBE_MU_PLUGIN_PREFIX) && entry.endsWith('.php'))
      .map((entry) => fs.rm(path.join(MU_PLUGIN_DIR, entry), { force: true })),
  );
}

async function resolveThemeDir(page: Page): Promise<string> {
  if (CONFIGURED_THEME_DIR) {
    return CONFIGURED_THEME_DIR;
  }

  await installThemeProbeFixture();
  const response = await page.request.get(`/?advapafo_theme_probe=${encodeURIComponent(THEME_PROBE_TOKEN)}`);
  expect(response.ok(), 'Active theme probe request should succeed.').toBeTruthy();

  const payload = (await response.json()) as { success?: boolean; data?: { stylesheet?: string; theme_dir?: string } };
  expect(payload.success, 'Active theme probe should return a success payload.').toBe(true);

  const themeDir = payload.data?.theme_dir || '';
  expect(themeDir, 'Active theme directory could not be resolved. Set PLAYWRIGHT_ACTIVE_THEME_DIR or PLAYWRIGHT_WP_ROOT.').not.toBe('');
  await expect(async () => {
    await fs.access(themeDir);
  }, `Resolved active theme directory should exist: ${themeDir}`).not.toThrow();

  return themeDir;
}

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/wp-login.php');
  await page.fill(SELECTORS.wpUserLogin, ADMIN_USERNAME);
  await page.fill(SELECTORS.wpUserPass, ADMIN_PASSWORD);
  await page.locator(SELECTORS.wpSubmit).waitFor({ state: 'visible' });
  await page.locator('form#loginform').evaluate((form) => {
    (form as HTMLFormElement).requestSubmit();
  });

  const adminUrlReached = page.waitForURL(/\/wp-admin\//, { timeout: 12_000 }).then(() => true).catch(() => false);
  const loginErrorVisible = page
    .locator('#login_error')
    .waitFor({ state: 'visible', timeout: 12_000 })
    .then(() => true)
    .catch(() => false);

  const [isAdminUrl, hasLoginError] = await Promise.all([adminUrlReached, loginErrorVisible]);

  if (!isAdminUrl && hasLoginError) {
    const loginErrorText = (await page.locator('#login_error').innerText()).trim();
    throw new Error(`WordPress login failed for user "${ADMIN_USERNAME}": ${loginErrorText}`);
  }

  if (!isAdminUrl) {
    throw new Error('WordPress login did not reach /wp-admin and no explicit #login_error was found.');
  }
}

async function openAdvancedSettingsTab(page: Page): Promise<void> {
  await page.goto(SETTINGS_PAGE_PATH);

  const advancedTab = page.locator(SELECTORS.advancedTab).first();
  const advancedHref = await advancedTab.getAttribute('href');
  if (advancedHref) {
    await page.goto(advancedHref);
  } else {
    await advancedTab.click({ force: true });
  }

  await expect(page.locator(SELECTORS.conditionalToggle).first()).toBeVisible();
}

async function setConditionalUi(page: Page, enabled: boolean): Promise<void> {
  await openAdvancedSettingsTab(page);

  const toggle = page.locator(SELECTORS.conditionalToggle).first();
  await expect(toggle).toBeVisible();
  await toggle.evaluate((node, shouldEnable) => {
    const input = node as HTMLInputElement;
    input.checked = Boolean(shouldEnable);
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, enabled);

  await page.locator(SELECTORS.saveSettingsButton).first().click({ force: true });
  await page.waitForURL(/options-general\.php\?page=advanced-passkey-login/);
}

test.describe('advanced-passkey-login template + conditional ui', () => {
  test('conditional UI lock behavior and wp-login rendering are enforced', async ({ page }, testInfo) => {
    await loginAsAdmin(page);

    try {
      await setConditionalUi(page, true);

      await openAdvancedSettingsTab(page);

      const separatorToggle = page.locator(SELECTORS.separatorToggle).first();
      await expect(separatorToggle, 'OR separator toggle should be locked when Conditional UI is enabled.').toBeDisabled();
      await expect(page.locator(SELECTORS.separatorHiddenInput), 'Hidden fallback field should force separator OFF while locked.').toHaveCount(1);

      await page.goto('/wp-login.php');

      const passkeyBlock = page.locator(SELECTORS.passkeyBlock).first();
      await expect(passkeyBlock, 'Conditional UI should still render the passkey block hook for autofill script context.').toBeAttached();

      const passkeyButton = page.locator(SELECTORS.passkeyButton).first();
      const hiddenAttr = await passkeyButton.getAttribute('hidden');
      const hiddenByMarkers = await passkeyButton.evaluate((node) => {
        const el = node as HTMLElement;
        const display = window.getComputedStyle(el).display;
        const visibility = window.getComputedStyle(el).visibility;
        return {
          hasHiddenClass: el.classList.contains('advapafo-passkey-btn--hidden'),
          display,
          visibility,
        };
      });

      expect(
        hiddenAttr !== null || hiddenByMarkers.hasHiddenClass || hiddenByMarkers.display === 'none' || hiddenByMarkers.visibility === 'hidden',
        'Manual passkey button should be hidden (or marked hidden) when Conditional UI is enabled.',
      ).toBeTruthy();
      await expect(page.locator(SELECTORS.passkeySeparator), 'OR separator should be absent when Conditional UI is enabled.').toHaveCount(0);

      const paddingTop = await passkeyBlock.evaluate((node) => window.getComputedStyle(node).paddingTop);
      expect(paddingTop, 'Conditional-only passkey block should not add bottom-form whitespace via top padding.').toBe('0px');
      await captureConditionalLoginEvidence(
        page,
        testInfo,
        `hidden=${String(hiddenAttr !== null)} class=${String(hiddenByMarkers.hasHiddenClass)} display=${hiddenByMarkers.display} visibility=${hiddenByMarkers.visibility} separator=absent paddingTop=${paddingTop}`,
      );
    } finally {
      await loginAsAdmin(page);
      await setConditionalUi(page, false);
    }
  });

  test('theme override template is loaded when login/button.php exists in theme override path', async ({ page }, testInfo) => {
    const themeDir = await resolveThemeDir(page);

    const relativeOverridePath = path.join('advanced-passkeys', 'login', 'button.php');
    const overrideFilePath = path.join(themeDir, relativeOverridePath);
    const markerClass = 'advapafo-e2e-template-override-marker';

    const overrideTemplate = [
      '<?php',
      'if ( ! defined( "ABSPATH" ) ) { exit; }',
      '$advapafo_show_sep = isset( $advapafo_show_sep ) ? (bool) $advapafo_show_sep : false;',
      '$advapafo_conditional_enabled = isset( $advapafo_conditional_enabled ) ? (bool) $advapafo_conditional_enabled : false;',
      '$advapafo_style_classes = isset( $advapafo_style_classes ) ? (string) $advapafo_style_classes : "advapafo-passkey-btn--black";',
      '?>',
      '<div id="advapafo-login-passkey-block" class="<?php echo esc_attr( $advapafo_conditional_enabled ? "advapafo-login-passkey-block--conditional-only" : "" ); ?>">',
      '  <div class="<?php echo esc_attr( "advapafo-login-passkey-wrap" . ( $advapafo_show_sep ? "" : " advapafo-no-separator" ) . ( $advapafo_conditional_enabled ? " advapafo-login-passkey-wrap--conditional-only" : "" ) ); ?>">',
      '    <button type="button" id="advapafo-signin-passkey" class="button button-large advapafo-passkey-btn <?php echo esc_attr( $advapafo_style_classes ); ?> ' + markerClass + '">',
      '      <?php esc_html_e( "Sign in with Passkey", "advanced-passkey-login" ); ?>',
      '    </button>',
      '    <p id="advapafo-passkey-login-message" class="advapafo-login-message advapafo-is-hidden" aria-live="polite"></p>',
      '  </div>',
      '</div>',
      '',
    ].join('\n');

    await fs.mkdir(path.dirname(overrideFilePath), { recursive: true });
    await fs.writeFile(overrideFilePath, overrideTemplate, 'utf8');

    try {
      await page.goto('/wp-login.php');
      const overrideMarker = page.locator(`.${markerClass}`).first();
      await expect(overrideMarker, 'Theme override template marker class was not rendered.').toBeVisible();
      await captureEvidenceScreenshot(page, testInfo, 'theme override login button marker', { selector: '#login' });
    } finally {
      await fs.rm(overrideFilePath, { force: true });
      await removeThemeProbeFixture();
    }
  });
});
