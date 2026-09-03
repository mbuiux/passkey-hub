import { expect, test, type Page, type TestInfo } from '@playwright/test';
import crypto from 'node:crypto';
import fs from 'node:fs/promises';
import path from 'node:path';

const ADMIN_USERNAME = process.env.PLAYWRIGHT_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.PLAYWRIGHT_ADMIN_PASS || 'admin';
const WORDPRESS_ROOT = process.env.PLAYWRIGHT_WP_ROOT || path.resolve(process.cwd(), '..', '..', 'demo', 'app', 'public');
const MU_PLUGIN_DIR = path.join(WORDPRESS_ROOT, 'wp-content', 'mu-plugins');
const LOCAL_CONFIG_MU_PLUGIN_PREFIX = 'advapafo-local-configuration-e2e';
const SETTINGS_PAGE_PATH = '/wp-admin/options-general.php?page=advanced-passkey-login';
const E2E_LOGIN_TOKEN = crypto.randomUUID();
let fixtureCounter = 0;

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

type FixtureConfig = {
  constantConfiguration?: string;
  localConfiguration?: string;
  legacyFilters?: string;
  setup?: string;
  environmentType?: 'local' | 'development' | 'staging' | 'production';
};

const SELECTORS = {
  settingsTab: '.advapafo-tabs .advapafo-tab:has-text("Settings")',
  advancedTab: '.advapafo-tabs .advapafo-tab:has-text("Advanced")',
  eligibleRoles: 'input[name="advapafo_eligible_roles[]"]',
  maxPasskeys: 'input[name="advapafo_max_passkeys_per_user"]',
  conditionalToggle: 'input[name="advapafo_conditional_ui_enabled"]',
  loginChallengeTtl: 'input[name="advapafo_login_challenge_ttl"]',
  registrationChallengeTtl: 'input[name="advapafo_registration_challenge_ttl"]',
  managedBadge: '.advapafo-badge--managed-code',
};

function buildLocalConfigPlugin(config: FixtureConfig = {}): string {
  const constantDefinition = config.constantConfiguration
    ? `if ( ! defined( 'ADVAPAFO_SETTINGS' ) ) {
	define( 'ADVAPAFO_SETTINGS', ${config.constantConfiguration} );
}
`
    : '';
  const environmentDefinition = config.environmentType
    ? `if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	define( 'WP_ENVIRONMENT_TYPE', '${config.environmentType}' );
}
`
    : '';
  const localConfigurationFilter = config.localConfiguration
    ? `add_filter(
	'advapafo_local_configuration',
	static function ( array $configuration ): array {
		return array_replace(
			$configuration,
			${config.localConfiguration}
		);
	}
);
`
    : '';

  return `<?php
/**
 * Temporary Playwright fixture for Advanced Passkeys local configuration tests.
 */
${environmentDefinition}${constantDefinition}
add_action(
  'init',
  static function (): void {
    if ( empty( $_GET['advapafo_e2e_login'] ) || empty( $_GET['advapafo_e2e_user'] ) ) {
      return;
    }

    $token = sanitize_text_field( wp_unslash( $_GET['advapafo_e2e_login'] ) );
    if ( ! hash_equals( '${E2E_LOGIN_TOKEN}', $token ) ) {
      wp_die( esc_html__( 'Invalid E2E login token.', 'advanced-passkey-login' ), 403 );
    }

    $user_login = sanitize_user( wp_unslash( $_GET['advapafo_e2e_user'] ), true );
    $user       = get_user_by( 'login', $user_login );
    if ( ! $user || ! user_can( $user, 'manage_options' ) ) {
      wp_die( esc_html__( 'Invalid E2E user.', 'advanced-passkey-login' ), 403 );
    }

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, false, is_ssl() );
    wp_safe_redirect( admin_url() );
    exit;
  }
);

add_action(
  'init',
  static function (): void {
    if ( empty( $_GET['advapafo_e2e_probe'] ) ) {
      return;
    }

    $token = sanitize_text_field( wp_unslash( $_GET['advapafo_e2e_probe'] ) );
    if ( ! hash_equals( '${E2E_LOGIN_TOKEN}', $token ) ) {
      wp_send_json_error( array( 'message' => 'Invalid E2E probe token.' ), 403 );
    }

    $action = isset( $_GET['advapafo_e2e_action'] ) ? sanitize_key( wp_unslash( $_GET['advapafo_e2e_action'] ) ) : 'setting';

    if ( 'login_nonce' === $action ) {
      wp_send_json_success( array( 'nonce' => wp_create_nonce( 'advapafo_login' ) ) );
    }

    if ( 'seed_last_used' === $action ) {
      $user_login = isset( $_GET['advapafo_e2e_user'] ) ? sanitize_user( wp_unslash( $_GET['advapafo_e2e_user'] ), true ) : '';
      $days_ago   = isset( $_GET['advapafo_e2e_days_ago'] ) ? absint( wp_unslash( $_GET['advapafo_e2e_days_ago'] ) ) : 0;
      $user       = get_user_by( 'login', $user_login );

      if ( ! $user instanceof WP_User ) {
        wp_send_json_error( array( 'message' => 'Invalid E2E seed user.' ), 404 );
      }

      update_user_meta( (int) $user->ID, 'advapafo_last_passkey_login_at', (string) ( time() - ( $days_ago * DAY_IN_SECONDS ) ) );
      wp_send_json_success( array( 'seeded' => true ) );
    }

    $setting_key = isset( $_GET['advapafo_e2e_setting'] ) ? sanitize_key( wp_unslash( $_GET['advapafo_e2e_setting'] ) ) : '';
    $user_login  = isset( $_GET['advapafo_e2e_user'] ) ? sanitize_user( wp_unslash( $_GET['advapafo_e2e_user'] ), true ) : '';
    $user        = '' !== $user_login ? get_user_by( 'login', $user_login ) : null;
    $context     = $user instanceof WP_User ? array( 'user' => $user ) : array();

    wp_send_json_success(
      array(
        'setting'     => $setting_key,
        'value'       => function_exists( 'advapafo_get_setting' ) ? advapafo_get_setting( $setting_key, null, $context ) : null,
        'overridden'  => function_exists( 'advapafo_is_setting_overridden' ) ? advapafo_is_setting_overridden( $setting_key ) : false,
        'environment' => function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
      )
    );
  },
  100
);

${config.setup || ''}

${localConfigurationFilter}
${config.legacyFilters || ''}
`;
}

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto(
    `/?advapafo_e2e_login=${encodeURIComponent(E2E_LOGIN_TOKEN)}&advapafo_e2e_user=${encodeURIComponent(ADMIN_USERNAME)}`,
  );

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

async function openSettingsTab(page: Page): Promise<void> {
  await page.goto(SETTINGS_PAGE_PATH);

  const settingsTab = page.locator(SELECTORS.settingsTab).first();
  const settingsHref = await settingsTab.getAttribute('href');
  if (settingsHref) {
    await page.goto(settingsHref);
  } else {
    await settingsTab.click({ force: true });
  }

  await expect(page.locator(SELECTORS.eligibleRoles).first()).toBeVisible();
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

async function installLocalConfigurationFixture(config: FixtureConfig = {}): Promise<void> {
  await removeLocalConfigurationFixture();
  await fs.mkdir(MU_PLUGIN_DIR, { recursive: true });
  fixtureCounter += 1;
  const fixturePath = path.join(MU_PLUGIN_DIR, `${LOCAL_CONFIG_MU_PLUGIN_PREFIX}-${process.pid}-${fixtureCounter}.php`);
  await fs.writeFile(fixturePath, buildLocalConfigPlugin(config), 'utf8');
}

async function removeLocalConfigurationFixture(): Promise<void> {
  const entries = await fs.readdir(MU_PLUGIN_DIR).catch(() => []);
  await Promise.all(
    entries
      .filter((entry) => entry.startsWith(LOCAL_CONFIG_MU_PLUGIN_PREFIX) && entry.endsWith('.php'))
      .map((entry) => fs.rm(path.join(MU_PLUGIN_DIR, entry), { force: true })),
  );
}

async function getProbe(page: Page, params: Record<string, string>): Promise<any> {
  const searchParams = new URLSearchParams({
    advapafo_e2e_probe: E2E_LOGIN_TOKEN,
    ...params,
  });
  const response = await page.request.get(`/?${searchParams.toString()}`);
  expect(response.ok()).toBeTruthy();
  const payload = await response.json();
  expect(payload.success).toBe(true);
  return payload.data;
}

async function getSettingProbe(page: Page, setting: string, user = ''): Promise<any> {
  return getProbe(page, {
    advapafo_e2e_action: 'setting',
    advapafo_e2e_setting: setting,
    ...(user ? { advapafo_e2e_user: user } : {}),
  });
}

async function waitForSettingProbe(page: Page, setting: string, expectedValue: unknown, expectedOverridden?: boolean): Promise<void> {
  await expect
    .poll(
      async () => {
        const probe = await getSettingProbe(page, setting);
        return {
          value: probe.value,
          overridden: probe.overridden,
        };
      },
      {
        message: `${setting} local-configuration fixture should be active before admin UI assertions`,
        timeout: 15_000,
      },
    )
    .toEqual({
      value: expectedValue,
      overridden: expectedOverridden ?? true,
    });
}

async function getLoginNonce(page: Page): Promise<string> {
  const data = await getProbe(page, { advapafo_e2e_action: 'login_nonce' });
  expect(typeof data.nonce).toBe('string');
  return data.nonce;
}

async function seedLastUsed(page: Page, daysAgo: number): Promise<void> {
  await getProbe(page, {
    advapafo_e2e_action: 'seed_last_used',
    advapafo_e2e_user: ADMIN_USERNAME,
    advapafo_e2e_days_ago: String(daysAgo),
  });
}

test.describe('advanced-passkey-login local configuration', () => {
  test.beforeEach(async () => {
    await removeLocalConfigurationFixture();
  });

  test.afterEach(async () => {
    await removeLocalConfigurationFixture();
  });

  test('filter overrides lock only managed settings and preserve database fallbacks', async ({ page }, testInfo) => {
    await installLocalConfigurationFixture({
      localConfiguration: `array(
				'conditional_ui_enabled' => true,
				'eligible_roles'         => array( 'administrator' ),
				'enforce_https'          => true,
				'login_challenge_ttl'    => 444,
			)`,
    });

  await waitForSettingProbe(page, 'conditional_ui_enabled', true);
  await waitForSettingProbe(page, 'eligible_roles', ['administrator']);

    await loginAsAdmin(page);

    await openSettingsTab(page);

    const administratorRole = page.locator(`${SELECTORS.eligibleRoles}[value="administrator"]`).first();
    await expect(administratorRole, 'Filter-managed administrator role should be checked.').toBeChecked();
    await expect(administratorRole, 'Filter-managed eligible roles should be disabled.').toBeDisabled();

    const subscriberRole = page.locator(`${SELECTORS.eligibleRoles}[value="subscriber"]`).first();
    if (await subscriberRole.count()) {
      await expect(subscriberRole, 'Filter-managed non-administrator role should be unchecked.').not.toBeChecked();
      await expect(subscriberRole, 'All role checkboxes should lock when eligible_roles is code-managed.').toBeDisabled();
    }

    await expect(
      page.locator('h3:has-text("Eligible user roles")').locator(SELECTORS.managedBadge),
      'Eligible roles should show the managed-code indicator.',
    ).toHaveText('Managed via code');

    await expect(
      page.locator(SELECTORS.maxPasskeys).first(),
      'Unconfigured settings should remain editable through the database fallback path.',
    ).toBeEditable();

    await openAdvancedSettingsTab(page);

    const conditionalToggle = page.locator(SELECTORS.conditionalToggle).first();
    await expect(conditionalToggle, 'Filter-managed Conditional UI should be enabled.').toBeChecked();
    await expect(conditionalToggle, 'Filter-managed Conditional UI should be disabled in the admin UI.').toBeDisabled();
    await expect(
      page.locator('h3:has-text("Enable passkey autofill")').locator(SELECTORS.managedBadge),
      'Conditional UI should show the managed-code indicator.',
    ).toHaveText('Managed via code');

    const loginChallengeTtl = page.locator(SELECTORS.loginChallengeTtl).first();
    await expect(loginChallengeTtl, 'Filter-managed login challenge TTL should use the filter value.').toHaveValue('444');
    await expect(loginChallengeTtl, 'Filter-managed login challenge TTL should be readonly.').toHaveAttribute('readonly', 'readonly');
    await expect(
      page.locator('label[for="advapafo_login_challenge_ttl"]').locator('..').locator(SELECTORS.managedBadge),
      'Login challenge TTL should show the managed-code indicator.',
    ).toHaveText('Managed via code');

    await expect(
      page.locator(SELECTORS.registrationChallengeTtl).first(),
      'Settings not supplied by the filter should stay editable and fall back to their database/default value.',
    ).toBeEditable();
    await captureEvidenceScreenshot(page, testInfo, 'filter managed advanced settings', {
      selector: '.advapafo-card--setting:has-text("Enable passkey autofill")',
    });
  });

  test('ADVAPAFO_SETTINGS constant overrides lock matching admin controls', async ({ page }, testInfo) => {
    await installLocalConfigurationFixture({
      constantConfiguration: `array(
	'max_passkeys_per_user' => 9,
	'login_challenge_ttl'   => 321,
)`,
    });

  await waitForSettingProbe(page, 'max_passkeys_per_user', 9);

    await loginAsAdmin(page);
    await openSettingsTab(page);

    const maxPasskeys = page.locator(SELECTORS.maxPasskeys).first();
    await expect(maxPasskeys, 'Constant-managed max passkeys should render its effective value.').toHaveValue('9');
    await expect(maxPasskeys, 'Constant-managed max passkeys should be readonly.').toHaveAttribute('readonly', 'readonly');
    await expect(
      page.locator('label[for="advapafo_max_passkeys_per_user"]').locator('..').locator(SELECTORS.managedBadge),
      'Constant-managed max passkeys should show the managed-code badge.',
    ).toHaveText('Managed via code');

    await openAdvancedSettingsTab(page);

    const loginChallengeTtl = page.locator(SELECTORS.loginChallengeTtl).first();
    await expect(loginChallengeTtl, 'Constant-managed login challenge TTL should render its effective value.').toHaveValue('321');
    await expect(loginChallengeTtl, 'Constant-managed login challenge TTL should be readonly.').toHaveAttribute('readonly', 'readonly');
    await captureEvidenceScreenshot(page, testInfo, 'constant managed challenge timeout', {
      selector: '.advapafo-card:has-text("Passkey challenge timeouts")',
    });
  });

  test('setting evaluation order resolves filter, constant, database, mapped option, then default per key', async ({ page }, testInfo) => {
    await installLocalConfigurationFixture({
      constantConfiguration: `array(
	'login_challenge_ttl'        => 222,
	'registration_challenge_ttl' => 555,
)`,
      localConfiguration: `array(
				'login_challenge_ttl' => 333,
			)`,
      setup: `add_action(
	'init',
	static function (): void {
		update_option(
			'advapafo_settings',
			array(
				'login_challenge_ttl'        => 111,
				'registration_challenge_ttl' => 444,
				'rate_limit_window'          => 666,
			)
		);
		update_option( 'advapafo_rate_limit_lockout', 777 );
		delete_option( 'advapafo_rate_limit_max_failures' );
	},
	1
);
`,
    });

    await expect((await getSettingProbe(page, 'login_challenge_ttl')).value).toBe(333);
    await expect((await getSettingProbe(page, 'registration_challenge_ttl')).value).toBe(555);
    await expect((await getSettingProbe(page, 'rate_limit_window')).value).toBe(666);
    await expect((await getSettingProbe(page, 'rate_limit_lockout')).value).toBe('777');
    await expect((await getSettingProbe(page, 'rate_limit_max_failures')).value).toBe(5);

    await loginAsAdmin(page);
    await openAdvancedSettingsTab(page);
    await captureEvidenceScreenshot(page, testInfo, 'evaluation order effective advanced values', {
      selector: '.advapafo-card:has-text("Passkey challenge timeouts")',
    });
  });

  test('legacy configuration filters still feed through the centralized getter', async ({ page }, testInfo) => {
    await installLocalConfigurationFixture({
      setup: `add_action(
	'init',
	static function (): void {
		delete_option( 'advapafo_settings' );
		delete_option( 'advapafo_conditional_ui_enabled' );
		delete_option( 'advapafo_max_passkeys_per_user' );
	},
	1
);
`,
      legacyFilters: `add_filter( 'advapafo_enable_conditional_ui', static fn( bool $enabled ): bool => true );
add_filter( 'advapafo_max_passkeys_per_user', static fn( $value, WP_User $user ): int => 12, 10, 2 );
`,
    });

    const conditionalUi = await getSettingProbe(page, 'conditional_ui_enabled');
    expect(conditionalUi.value).toBe(true);
    expect(conditionalUi.overridden).toBe(false);

    const maxPasskeys = await getSettingProbe(page, 'max_passkeys_per_user', ADMIN_USERNAME);
    expect(maxPasskeys.value).toBe(12);
    expect(maxPasskeys.overridden).toBe(false);

    await loginAsAdmin(page);
    await openAdvancedSettingsTab(page);

    await expect(page.locator(SELECTORS.conditionalToggle).first()).toBeChecked();
    await expect(page.locator(SELECTORS.conditionalToggle).first()).toBeEnabled();
    await captureEvidenceScreenshot(page, testInfo, 'legacy filter conditional UI enabled', {
      selector: '.advapafo-card--setting:has-text("Enable passkey autofill")',
    });
  });

  test('local configuration controls Last used pill label and freshness through the login AJAX endpoint', async ({ page }, testInfo) => {
    await installLocalConfigurationFixture({
      localConfiguration: `array(
				'last_used_pill_label'          => 'Previously used',
				'last_used_pill_freshness_days' => 120,
			)`,
    });

    const nonce = await getLoginNonce(page);

    await seedLastUsed(page, 30);
    const freshResponse = await page.request.post('/wp-admin/admin-ajax.php', {
      form: {
        action: 'advapafo_get_last_used_hint',
        nonce,
        login: ADMIN_USERNAME,
      },
    });
    expect(freshResponse.ok()).toBeTruthy();
    const freshPayload = await freshResponse.json();
    expect(freshPayload.success).toBe(true);
    expect(freshPayload.data).toMatchObject({ showPill: true, label: 'Previously used' });

    await page.goto('/wp-login.php');
    await page.locator('#user_login').fill(ADMIN_USERNAME);
    await expect(page.locator('.advapafo-passkey-last-used-pill').first()).toHaveText('Previously used');
    await expect(page.locator('.advapafo-passkey-last-used-pill').first()).toBeVisible();
    await captureEvidenceScreenshot(page, testInfo, 'last used pill custom label', { selector: '#login' });

    await seedLastUsed(page, 200);
    const staleResponse = await page.request.post('/wp-admin/admin-ajax.php', {
      form: {
        action: 'advapafo_get_last_used_hint',
        nonce,
        login: ADMIN_USERNAME,
      },
    });
    expect(staleResponse.ok()).toBeTruthy();
    const stalePayload = await staleResponse.json();
    expect(stalePayload.success).toBe(true);
    expect(stalePayload.data).toMatchObject({ showPill: false, label: 'Previously used' });
  });

  test('enforce_https local configuration changes non-SSL login challenge behavior', async ({ page }, testInfo) => {
    const baseUrl = testInfo.project.use.baseURL || process.env.PLAYWRIGHT_BASE_URL || 'https://lakeviewcc.local';
    const httpOrigin = new URL(baseUrl as string);
    httpOrigin.protocol = 'http:';
    const ajaxUrl = new URL('/wp-admin/admin-ajax.php', httpOrigin).toString();

    await installLocalConfigurationFixture({
      environmentType: 'local',
      localConfiguration: `array(
				'enforce_https' => true,
			)`,
      setup: `add_action(
  'init',
  static function (): void {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->prefix}advapafo_rate_limits" );
  },
  1
);
`,
    });

    const blockedResponse = await page.request.post(ajaxUrl, {
      form: {
        action: 'advapafo_begin_login',
        nonce: 'invalid-e2e-nonce',
      },
      maxRedirects: 0,
    });
    expect(blockedResponse.status()).toBe(400);
    const blockedPayload = await blockedResponse.json();
    expect(blockedPayload).toMatchObject({ success: false, data: { message: 'Passkeys require HTTPS.' } });
    await page.goto(new URL('/wp-login.php', httpOrigin).toString());
    await captureEvidenceScreenshot(page, testInfo, 'http secure context warning', { selector: '#login' });

    await installLocalConfigurationFixture({
      environmentType: 'local',
      localConfiguration: `array(
				'enforce_https' => false,
			)`,
      setup: `add_action(
  'init',
  static function (): void {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->prefix}advapafo_rate_limits" );
  },
  1
);
`,
    });

    const allowedPastHttpsResponse = await page.request.post(ajaxUrl, {
      form: {
        action: 'advapafo_begin_login',
        nonce: 'invalid-e2e-nonce',
      },
      maxRedirects: 0,
    });
    expect(allowedPastHttpsResponse.status()).toBe(403);
    const allowedPastHttpsPayload = await allowedPastHttpsResponse.json();
    expect(allowedPastHttpsPayload).toMatchObject({ success: false, data: { message: 'Invalid request.' } });
  });
});