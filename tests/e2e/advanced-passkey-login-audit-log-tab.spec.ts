import { expect, test, type Page } from '@playwright/test';

const ADMIN_USERNAME = process.env.PLAYWRIGHT_ADMIN_USER || 'admin';
const ADMIN_PASSWORD = process.env.PLAYWRIGHT_ADMIN_PASS || 'admin';
const SETTINGS_PAGE_PATH = '/wp-admin/options-general.php?page=advanced-passkey-login';

const SELECTORS = {
  wpUserLogin: '#user_login',
  wpUserPass: '#user_pass',
  wpSubmit: '#wp-submit',
  auditTab: '.advapafo-tabs .advapafo-tab:has-text("Audit Log")',
  statCards: '.wpkpro-audit-stats-grid .wpkpro-audit-stat',
  authenticatorTable: '#advapafo-authenticator-table',
  loginActivityTable: '#advapafo-login-activity-table',
  loginSearchInput: '#advapafo-login-search',
  loginMethodFilter: '#advapafo-login-method-filter',
  metricInfoList: '.wpkpro-metric-info-list',
  exportCsvLink: 'a:has-text("Export CSV")',
};

async function loginAsAdmin(page: Page): Promise<void> {
  await page.goto('/wp-login.php');
  await page.fill(SELECTORS.wpUserLogin, ADMIN_USERNAME);
  await page.fill(SELECTORS.wpUserPass, ADMIN_PASSWORD);
  const submit = page.locator(SELECTORS.wpSubmit);
  await submit.waitFor({ state: 'visible' });
  await submit.click({ force: true });
  await page.waitForURL(/\/wp-admin\/?(?:index\.php)?(?:\?.*)?$/);
}

async function goToAuditLogTab(page: Page): Promise<void> {
  await page.goto(SETTINGS_PAGE_PATH);
  // Tab switching requires the per-tab nonce baked into the link href, so click it rather than building the URL.
  const auditTab = page.locator(SELECTORS.auditTab).first();
  await auditTab.waitFor({ state: 'visible' });
  await auditTab.click({ force: true });
  await page.waitForURL(/options-general\.php\?page=advanced-passkey-login.*tab=audit/);
}

test.describe('advanced-passkey-login audit log tab', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('renders KPI cards, authenticator usage, and detailed login activity sections', async ({ page }) => {
    await goToAuditLogTab(page);

    await expect(page.locator('.wpkpro-section-header h2')).toHaveText('Full audit log');
    await expect(page.locator(SELECTORS.statCards)).toHaveCount(6);

    await expect(page.getByRole('heading', { name: 'Authenticator Usage' })).toBeVisible();
    await expect(page.locator(SELECTORS.authenticatorTable)).toBeVisible();

    await expect(page.getByRole('heading', { name: 'Detailed Login Activity' })).toBeVisible();
    await expect(page.locator(SELECTORS.loginActivityTable)).toBeVisible();
    const headerLabels = await page.locator(`${SELECTORS.loginActivityTable} thead th`).allTextContents();
    expect(headerLabels.map((label) => label.trim())).toEqual([
      'ID',
      'Method',
      'Status',
      'Timestamp',
      'Authenticator',
      'User Ref',
      'IP Address',
      'Event',
    ]);

    await expect(page.getByRole('heading', { name: 'Metric definitions' })).toBeVisible();
  });

  test('does not expose CSV export or the retired Security Policy method (Lite-only regression guard)', async ({ page }) => {
    await goToAuditLogTab(page);

    await expect(page.locator(SELECTORS.exportCsvLink)).toHaveCount(0);

    const methodOptions = await page.locator(`${SELECTORS.loginMethodFilter} option`).allTextContents();
    expect(methodOptions.map((label) => label.trim())).toEqual(['All Methods', 'Passkey', 'Password', 'Other']);

    await expect(page.locator(SELECTORS.metricInfoList)).not.toContainText('Security Policy');
  });

  test('search box filters the detailed login activity table', async ({ page }) => {
    await goToAuditLogTab(page);

    const rows = page.locator(`${SELECTORS.loginActivityTable} tbody tr`);
    const totalRows = await rows.count();
    test.skip(totalRows === 0, 'No audit log rows exist yet on this site to filter.');

    await page.fill(SELECTORS.loginSearchInput, 'zzz-no-such-audit-event-zzz');
    await expect(rows.filter({ hasNotText: '' })).toHaveCount(totalRows);
    const visibleAfterFilter = await rows.evaluateAll((nodes) =>
      nodes.filter((node) => (node as HTMLElement).style.display !== 'none').length,
    );
    expect(visibleAfterFilter).toBe(0);

    await page.fill(SELECTORS.loginSearchInput, '');
    const visibleAfterClear = await rows.evaluateAll((nodes) =>
      nodes.filter((node) => (node as HTMLElement).style.display !== 'none').length,
    );
    expect(visibleAfterClear).toBe(totalRows);
  });

  test('method filter narrows rows to the selected login method', async ({ page }) => {
    await goToAuditLogTab(page);

    const rows = page.locator(`${SELECTORS.loginActivityTable} tbody tr`);
    const totalRows = await rows.count();
    test.skip(totalRows === 0, 'No audit log rows exist yet on this site to filter.');

    await page.selectOption(SELECTORS.loginMethodFilter, 'passkey');
    const visibleMethodKeys = await rows.evaluateAll((nodes) =>
      nodes
        .filter((node) => (node as HTMLElement).style.display !== 'none')
        .map((node) => node.getAttribute('data-method-key')),
    );
    expect(visibleMethodKeys.every((key) => key === 'passkey')).toBe(true);

    await page.selectOption(SELECTORS.loginMethodFilter, 'all');
    const visibleAfterReset = await rows.evaluateAll((nodes) =>
      nodes.filter((node) => (node as HTMLElement).style.display !== 'none').length,
    );
    expect(visibleAfterReset).toBe(totalRows);
  });

  test('clicking a sortable column header toggles sort direction', async ({ page }) => {
    await goToAuditLogTab(page);

    const idHeader = page.locator(`${SELECTORS.loginActivityTable} thead th`).first();
    await expect(idHeader).toHaveClass(/is-sortable/);

    // Dispatch a real DOM click to avoid Playwright's pointer stability checks tripping on the sticky admin toolbar.
    await idHeader.evaluate((node) => (node as HTMLElement).click());
    await expect(idHeader).toHaveClass(/is-sorted-asc|is-sorted-desc/);

    const firstDirection = (await idHeader.getAttribute('class')) ?? '';
    await idHeader.evaluate((node) => (node as HTMLElement).click());
    await expect(idHeader).not.toHaveClass(new RegExp(firstDirection.match(/is-sorted-(asc|desc)/)?.[0] ?? 'is-sorted-none'));
  });
});
