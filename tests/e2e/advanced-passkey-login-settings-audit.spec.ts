import { expect, test, type Page, type TestInfo } from '@playwright/test';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';

const ADMIN_USERNAME = process.env.PLAYWRIGHT_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.PLAYWRIGHT_ADMIN_PASS || 'admin';
const WORDPRESS_ROOT = process.env.PLAYWRIGHT_WP_ROOT || path.resolve(process.cwd(), '..', '..', 'demo', 'app', 'public');
const MU_PLUGIN_DIR = path.join(WORDPRESS_ROOT, 'wp-content', 'mu-plugins');
const SETTINGS_AUDIT_MU_PLUGIN_PREFIX = 'advapafo-settings-audit-e2e';
const SETTINGS_PAGE_PATH = '/wp-admin/options-general.php?page=advanced-passkey-login';
const E2E_AUDIT_TOKEN = crypto.randomUUID();

const SELECTORS = {
  wpUserLogin: '#user_login',
  wpUserPass: '#user_pass',
  wpSubmit: '#wp-submit',
  settingsTab: '.advapafo-tabs .advapafo-tab:has-text("Settings")',
  advancedTab: '.advapafo-tabs .advapafo-tab:has-text("Advanced")',
  saveSettingsButton: '.advapafo-settings-form .advapafo-save-button',
  loginPasskeyButton: '#advapafo-signin-passkey',
  loginSeparator: '.advapafo-login-separator',
};

const SETTING_KEYS = [
  'advapafo_enabled',
  'advapafo_show_setup_notice',
  'advapafo_enable_woocommerce_support',
  'advapafo_enable_edd_support',
  'advapafo_enable_memberpress_support',
  'advapafo_enable_ultimate_member_support',
  'advapafo_enable_learndash_support',
  'advapafo_enable_buddyboss_support',
  'advapafo_enable_gravityforms_support',
  'advapafo_enable_pmp_support',
  'advapafo_eligible_roles',
  'advapafo_max_passkeys_per_user',
  'advapafo_user_verification',
  'advapafo_show_separator',
  'advapafo_conditional_ui_enabled',
  'advapafo_button_style',
  'advapafo_rp_name',
  'advapafo_rp_id',
  'advapafo_login_challenge_ttl',
  'advapafo_registration_challenge_ttl',
  'advapafo_rate_limit_window',
  'advapafo_rate_limit_max_failures',
  'advapafo_rate_limit_lockout',
] as const;

type SettingKey = (typeof SETTING_KEYS)[number];
type OptionsPayload = Record<SettingKey, unknown>;

type IntegrationSetting = {
  key: string;
  label: string;
  master_option: SettingKey;
  dependency_active: boolean;
};

function slugify(value: string): string {
  return value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 90) || 'screenshot';
}

async function captureEvidenceScreenshot(page: Page, testInfo: TestInfo, label: string, options: { selector?: string; fullPage?: boolean } = {}): Promise<void> {
  if (process.env.PLAYWRIGHT_EVIDENCE_SCREENSHOTS !== '1') return;

  const screenshotPath = testInfo.outputPath(`evidence-${slugify(label)}.png`);
  try {
    if (options.selector) {
      await page.locator(options.selector).first().scrollIntoViewIfNeeded({ timeout: 1_500 }).catch(() => undefined);
    }
    await page.screenshot({ path: screenshotPath, fullPage: options.fullPage ?? false, timeout: 5_000 });
  } catch (error) {
    testInfo.annotations.push({ type: 'evidence-screenshot-skipped', description: `${label}: ${String(error)}` });
  }
}

function buildSettingsAuditPlugin(): string {
  return `<?php
/**
 * Temporary Playwright fixture for Advanced Passkeys settings audit tests.
 */
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

add_action(
  'init',
  static function (): void {
    if ( empty( $_GET['advapafo_settings_audit'] ) ) {
      return;
    }

    $token = sanitize_text_field( wp_unslash( $_GET['advapafo_settings_audit'] ) );
    if ( ! hash_equals( '${E2E_AUDIT_TOKEN}', $token ) ) {
      wp_send_json_error( array( 'message' => 'Invalid E2E settings audit token.' ), 403 );
    }

    $action = isset( $_GET['advapafo_settings_audit_action'] ) ? sanitize_key( wp_unslash( $_GET['advapafo_settings_audit_action'] ) ) : 'state';

    $setting_keys = array(
      'advapafo_enabled',
      'advapafo_show_separator',
      'advapafo_conditional_ui_enabled',
      'advapafo_show_setup_notice',
      'advapafo_enable_woocommerce_support',
      'advapafo_enable_edd_support',
      'advapafo_enable_memberpress_support',
      'advapafo_enable_ultimate_member_support',
      'advapafo_enable_learndash_support',
      'advapafo_enable_buddyboss_support',
      'advapafo_enable_gravityforms_support',
      'advapafo_enable_pmp_support',
      'advapafo_eligible_roles',
      'advapafo_max_passkeys_per_user',
      'advapafo_user_verification',
      'advapafo_button_style',
      'advapafo_rp_name',
      'advapafo_rp_id',
      'advapafo_login_challenge_ttl',
      'advapafo_registration_challenge_ttl',
      'advapafo_rate_limit_window',
      'advapafo_rate_limit_max_failures',
      'advapafo_rate_limit_lockout',
    );

    if ( 'reset' === $action ) {
      update_option( 'advapafo_enabled', 1 );
      update_option( 'advapafo_show_separator', 1 );
      update_option( 'advapafo_conditional_ui_enabled', 0 );
      update_option( 'advapafo_show_setup_notice', 1 );
      update_option( 'advapafo_enable_woocommerce_support', 0 );
      update_option( 'advapafo_enable_edd_support', 0 );
      update_option( 'advapafo_enable_memberpress_support', 0 );
      update_option( 'advapafo_enable_ultimate_member_support', 0 );
      update_option( 'advapafo_enable_learndash_support', 0 );
      update_option( 'advapafo_enable_buddyboss_support', 0 );
      update_option( 'advapafo_enable_gravityforms_support', 0 );
      update_option( 'advapafo_enable_pmp_support', 0 );
      update_option( 'advapafo_eligible_roles', array( 'administrator' ) );
      update_option( 'advapafo_max_passkeys_per_user', 0 );
      update_option( 'advapafo_user_verification', 'required' );
      update_option( 'advapafo_button_style', 'black' );
      update_option( 'advapafo_rp_name', '' );
      update_option( 'advapafo_rp_id', '' );
      update_option( 'advapafo_login_challenge_ttl', 300 );
      update_option( 'advapafo_registration_challenge_ttl', 300 );
      update_option( 'advapafo_rate_limit_window', 300 );
      update_option( 'advapafo_rate_limit_max_failures', 5 );
      update_option( 'advapafo_rate_limit_lockout', 900 );
    }

    $options = array();
    foreach ( $setting_keys as $setting_key ) {
      $options[ $setting_key ] = get_option( $setting_key, null );
    }

    $integration_settings = array();
    if ( class_exists( 'ADVAPAFO_Integration_Manager' ) && method_exists( 'ADVAPAFO_Integration_Manager', 'get_settings_registry' ) ) {
      $integration_settings = ADVAPAFO_Integration_Manager::get_settings_registry();
    }

    wp_send_json_success(
      array(
        'options'      => $options,
        'integrations' => $integration_settings,
      )
    );
  },
  100
);
`;
}

async function installSettingsAuditFixture(): Promise<void> {
  await removeSettingsAuditFixture();
  await fs.mkdir(MU_PLUGIN_DIR, { recursive: true });
  const fixturePath = path.join(MU_PLUGIN_DIR, `${SETTINGS_AUDIT_MU_PLUGIN_PREFIX}-${process.pid}.php`);
  await fs.writeFile(fixturePath, buildSettingsAuditPlugin(), 'utf8');
}

async function removeSettingsAuditFixture(): Promise<void> {
  const entries = await fs.readdir(MU_PLUGIN_DIR).catch(() => []);
  await Promise.all(
    entries
      .filter((entry) => entry.startsWith(SETTINGS_AUDIT_MU_PLUGIN_PREFIX) && entry.endsWith('.php'))
      .map((entry) => fs.rm(path.join(MU_PLUGIN_DIR, entry), { force: true })),
  );
}

async function requestAuditState(page: Page, action = 'state'): Promise<{ options: OptionsPayload; integrations: IntegrationSetting[] }> {
  const params = new URLSearchParams({
    advapafo_settings_audit: E2E_AUDIT_TOKEN,
    advapafo_settings_audit_action: action,
  });
  const response = await page.request.get(`/?${params.toString()}`);
  expect(response.ok()).toBeTruthy();
  const payload = (await response.json()) as { success?: boolean; data?: { options?: OptionsPayload; integrations?: IntegrationSetting[] } };
  expect(payload.success).toBe(true);
  expect(payload.data?.options).toBeTruthy();
  return {
    options: payload.data?.options as OptionsPayload,
    integrations: payload.data?.integrations ?? [],
  };
}

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/wp-login.php');
  await page.fill(SELECTORS.wpUserLogin, ADMIN_USERNAME);
  await page.fill(SELECTORS.wpUserPass, ADMIN_PASSWORD);
  await page.locator(SELECTORS.wpSubmit).waitFor({ state: 'visible' });
  await page.locator('form#loginform').evaluate((form) => {
    (form as HTMLFormElement).requestSubmit();
  });
  await page.waitForURL(/\/wp-admin\//, { timeout: 15_000 });
}

async function openSettingsTab(page: Page): Promise<void> {
  await page.goto(SETTINGS_PAGE_PATH);
  const settingsTab = page.locator(SELECTORS.settingsTab).first();
  const settingsHref = await settingsTab.getAttribute('href');
  if (settingsHref) {
    await page.goto(settingsHref);
  } else {
    await settingsTab.click({ force: true });
  }
  await expect(page.locator('input[name="advapafo_enabled"]').first()).toBeVisible();
}

async function openAdvancedTab(page: Page): Promise<void> {
  await page.goto(SETTINGS_PAGE_PATH);
  const advancedTab = page.locator(SELECTORS.advancedTab).first();
  const advancedHref = await advancedTab.getAttribute('href');
  if (advancedHref) {
    await page.goto(advancedHref);
  } else {
    await advancedTab.click({ force: true });
  }
  await expect(page.locator('input[name="advapafo_conditional_ui_enabled"]').first()).toBeVisible();
}

async function saveSettings(page: Page): Promise<void> {
  await page.locator(SELECTORS.saveSettingsButton).first().click({ force: true });
  await page.waitForURL(/options-general\.php\?page=advanced-passkey-login/, { timeout: 15_000 });
  await expect(page.locator(SELECTORS.saveSettingsButton).first()).toBeVisible();
}

async function setCheckbox(page: Page, selector: string, checked: boolean): Promise<void> {
  const checkbox = page.locator(selector).first();
  await expect(checkbox).toHaveCount(1);
  await expect(checkbox, `${selector} should be editable for this settings step`).toBeEnabled();
  await checkbox.evaluate((node, shouldCheck) => {
    const input = node as HTMLInputElement;
    input.checked = Boolean(shouldCheck);
    input.dispatchEvent(new Event('change', { bubbles: true }));
  }, checked);
}

async function expectOption(options: OptionsPayload, setting: SettingKey, expected: unknown): Promise<void> {
  if (Array.isArray(expected)) {
    expect(options[setting], `${setting} did not persist expected array value`).toEqual(expected);
    return;
  }
  expect(String(options[setting]), `${setting} did not persist expected value`).toBe(String(expected));
}

test.describe('advanced-passkey-login comprehensive settings audit', () => {
  test.beforeEach(async ({ page }) => {
    await installSettingsAuditFixture();
    await requestAuditState(page, 'reset');
  });

  test.afterEach(async ({ page }) => {
    await requestAuditState(page, 'reset').catch(() => undefined);
    await removeSettingsAuditFixture();
  });

  test('validates every registered Lite setting through the admin UI save flow', async ({ page }, testInfo) => {
    const validatedSettings = new Set<SettingKey>();
    const expectedIntegrationValues = new Map<SettingKey, string>();

    async function validateSetting(setting: SettingKey, assertion: () => Promise<void>): Promise<void> {
      await test.step(`✓ ${setting}`, async () => {
        await assertion();
        validatedSettings.add(setting);
        testInfo.annotations.push({ type: 'setting-validated', description: setting });
      });
    }

    await loginAsAdmin(page);
    await openSettingsTab(page);

    await setCheckbox(page, 'input[name="advapafo_enabled"]', true);
    await setCheckbox(page, 'input[name="advapafo_show_setup_notice"]', false);

    const integrationState = await requestAuditState(page);
    for (const integration of integrationState.integrations) {
      const setting = integration.master_option;
      const toggle = page.locator(`input[name="${setting}"][type="checkbox"]`).first();
      await expect(toggle, `${setting} integration toggle should render on the Settings tab`).toHaveCount(1);

      if (await toggle.isDisabled()) {
        await expect(page.locator(`input[type="hidden"][name="${setting}"][value="0"]`).first(), `${setting} should preserve OFF while dependency is missing`).toHaveCount(1);
        expectedIntegrationValues.set(setting, '0');
      } else {
        await setCheckbox(page, `input[name="${setting}"][type="checkbox"]`, true);
        expectedIntegrationValues.set(setting, '1');
      }
    }

    await page.locator('input[name="advapafo_eligible_roles[]"][value="administrator"]').setChecked(true, { force: true });
    await page.locator('input[name="advapafo_eligible_roles[]"][value="editor"]').setChecked(true, { force: true });
    await page.locator('input[name="advapafo_eligible_roles[]"][value="subscriber"]').setChecked(false, { force: true }).catch(() => undefined);
    await page.locator('input[name="advapafo_max_passkeys_per_user"]').fill('3');
    await page.locator('select[name="advapafo_user_verification"]').selectOption('preferred');

    await saveSettings(page);
    const settingsOptions = (await requestAuditState(page)).options;

    await validateSetting('advapafo_enabled', () => expectOption(settingsOptions, 'advapafo_enabled', '1'));
    await validateSetting('advapafo_show_setup_notice', () => expectOption(settingsOptions, 'advapafo_show_setup_notice', '0'));
    for (const [setting, expectedValue] of expectedIntegrationValues.entries()) {
      await validateSetting(setting, () => expectOption(settingsOptions, setting, expectedValue));
    }
    await validateSetting('advapafo_eligible_roles', () => expectOption(settingsOptions, 'advapafo_eligible_roles', ['administrator', 'editor']));
    await validateSetting('advapafo_max_passkeys_per_user', () => expectOption(settingsOptions, 'advapafo_max_passkeys_per_user', '3'));
    await validateSetting('advapafo_user_verification', () => expectOption(settingsOptions, 'advapafo_user_verification', 'preferred'));
    await captureEvidenceScreenshot(page, testInfo, 'settings tab all everyday settings saved', { selector: '.advapafo-settings-form', fullPage: true });

    await openAdvancedTab(page);

    await setCheckbox(page, 'input[name="advapafo_show_separator"]', true);
    await setCheckbox(page, 'input[name="advapafo_conditional_ui_enabled"]', true);
    await saveSettings(page);

    const conditionalOptions = (await requestAuditState(page)).options;
    await validateSetting('advapafo_conditional_ui_enabled', () => expectOption(conditionalOptions, 'advapafo_conditional_ui_enabled', '1'));
    await validateSetting('advapafo_show_separator', () => expectOption(conditionalOptions, 'advapafo_show_separator', '0'));

    await page.goto('/wp-login.php');
    await expect(page.locator(SELECTORS.loginPasskeyButton).first(), 'Conditional UI should hide the manual passkey button').toBeAttached();
    await expect(page.locator(SELECTORS.loginPasskeyButton).first(), 'Manual passkey button should not be visible while Conditional UI is enabled').toBeHidden();
    await expect(page.locator(SELECTORS.loginSeparator), 'OR separator should be absent while Conditional UI is enabled').toHaveCount(0);

    await loginAsAdmin(page);
    await openAdvancedTab(page);

    await setCheckbox(page, 'input[name="advapafo_conditional_ui_enabled"]', false);
    await page.locator('select[name="advapafo_button_style"]').selectOption('light_grey');
    await page.locator('input[name="advapafo_rp_name"]').fill('Demo Passkeys E2E');
    await page.locator('input[name="advapafo_rp_id"]').fill('Demo.Local!@#');
    await page.locator('input[name="advapafo_login_challenge_ttl"]').fill('123');
    await page.locator('input[name="advapafo_registration_challenge_ttl"]').fill('234');
    await page.locator('input[name="advapafo_rate_limit_window"]').fill('456');
    await page.locator('input[name="advapafo_rate_limit_max_failures"]').fill('7');
    await page.locator('input[name="advapafo_rate_limit_lockout"]').fill('890');

    await saveSettings(page);
    const advancedOptions = (await requestAuditState(page)).options;

    await validateSetting('advapafo_button_style', () => expectOption(advancedOptions, 'advapafo_button_style', 'light_grey'));
    await validateSetting('advapafo_rp_name', () => expectOption(advancedOptions, 'advapafo_rp_name', 'Demo Passkeys E2E'));
    await validateSetting('advapafo_rp_id', () => expectOption(advancedOptions, 'advapafo_rp_id', 'demo.local'));
    await validateSetting('advapafo_login_challenge_ttl', () => expectOption(advancedOptions, 'advapafo_login_challenge_ttl', '123'));
    await validateSetting('advapafo_registration_challenge_ttl', () => expectOption(advancedOptions, 'advapafo_registration_challenge_ttl', '234'));
    await validateSetting('advapafo_rate_limit_window', () => expectOption(advancedOptions, 'advapafo_rate_limit_window', '456'));
    await validateSetting('advapafo_rate_limit_max_failures', () => expectOption(advancedOptions, 'advapafo_rate_limit_max_failures', '7'));
    await validateSetting('advapafo_rate_limit_lockout', () => expectOption(advancedOptions, 'advapafo_rate_limit_lockout', '890'));

    await page.goto('/wp-login.php');
    await expect(page.locator(SELECTORS.loginSeparator), 'OR separator should stay hidden after saving show_separator=false').toHaveCount(0);
    await expect(page.locator(SELECTORS.loginPasskeyButton).first(), 'Light grey button style should render on wp-login.php').toHaveClass(/advapafo-passkey-btn--light-grey/);
    await captureEvidenceScreenshot(page, testInfo, 'advanced tab visual settings applied on login', { selector: '#login' });

    const missingSettings = SETTING_KEYS.filter((setting) => !validatedSettings.has(setting));
    expect(missingSettings, `Every registered setting should be checked off. Missing: ${missingSettings.join(', ')}`).toEqual([]);
  });
});
