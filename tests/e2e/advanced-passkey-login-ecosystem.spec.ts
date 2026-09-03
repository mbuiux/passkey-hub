import { expect, test, type CDPSession, type Page, type TestInfo } from '@playwright/test';
import fs from 'node:fs/promises';
import path from 'node:path';

const BASE_URL = 'https://demo.local';

const USERS = {
  admin: {
    username: process.env.PLAYWRIGHT_ADMIN_USER || 'admin',
    password: process.env.PLAYWRIGHT_ADMIN_PASS || 'admin',
  },
  subscriber: {
    username: process.env.PLAYWRIGHT_SUBSCRIBER_USER || 'subscriber',
    password: process.env.PLAYWRIGHT_SUBSCRIBER_PASS || 'subscriber',
  },
  author: {
    username: process.env.PLAYWRIGHT_AUTHOR_USER || 'author',
    password: process.env.PLAYWRIGHT_AUTHOR_PASS || 'author',
  },
  editor: {
    username: process.env.PLAYWRIGHT_EDITOR_USER || 'editor',
    password: process.env.PLAYWRIGHT_EDITOR_PASS || 'editor',
  },
  contributor: {
    username: process.env.PLAYWRIGHT_CONTRIBUTOR_USER || 'contributor',
    password: process.env.PLAYWRIGHT_CONTRIBUTOR_PASS || 'contributor',
  },
};

const SELECTORS = {
  wpUserLogin: '#user_login',
  wpUserPass: '#user_pass',
  wpSubmit: '#wp-submit',
  pluginSettingsRoot: '#advanced-passkey-settings-page',
  advancedTab: '.advapafo-tabs .advapafo-tab:has-text("Advanced")',
  conditionalToggle: 'input[name="advapafo_conditional_ui_enabled"]',
  saveSettingsButton: '.advapafo-settings-form .advapafo-save-button',
  passkeyLoginButtonIds: ['#passkey-login-btn', '#advapafo-signin-passkey'],
  passkeyRegisterButtonIds: ['#register-passkey-btn', '#advapafo-passkey-register'],
  passkeyErrorNotice: ['.passkey-error-notice', '#advapafo-login-notice', '#advapafo-passkey-login-message'],
};

const DEBUG_LOG_PATH =
  process.env.PLAYWRIGHT_WP_DEBUG_LOG_PATH ||
  path.resolve(process.cwd(), '../..', 'lakeviewcc/app/public/wp-content/debug.log');

function wpUrl(pathname: string): string {
  return `${BASE_URL}${pathname}`;
}

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

function isAjaxActionRequest(request: { url(): string; method(): string; postData(): string | null }, action: string): boolean {
  const postBody = request.postData() ?? '';
  const hasUrlEncodedAction = postBody.includes(`action=${action}`);
  const hasFormDataAction = postBody.includes('name="action"') && postBody.includes(action);

  return (
    request.method() === 'POST' &&
    request.url().includes('/wp-admin/admin-ajax.php') &&
    (hasUrlEncodedAction || hasFormDataAction)
  );
}

async function firstAvailableLocator(page: Page, selectors: string[]) {
  for (const selector of selectors) {
    const locator = page.locator(selector).first();
    if (await locator.count()) {
      return locator;
    }
  }

  return page.locator(selectors[0]).first();
}

async function loginWithWordPressUser(page: Page, username: string, password: string): Promise<void> {
  await page.goto('/wp-login.php');
  await page.fill(SELECTORS.wpUserLogin, username);
  await page.fill(SELECTORS.wpUserPass, password);
  await page.locator(SELECTORS.wpSubmit).waitFor({ state: 'visible' });
  await page.locator('form#loginform').evaluate((form) => {
    (form as HTMLFormElement).requestSubmit();
  });

  const adminUrlReached = page.waitForURL(/\/wp-admin\//, { timeout: 25_000 }).then(() => true).catch(() => false);
  const loginErrorVisible = page
    .locator('#login_error')
    .waitFor({ state: 'visible', timeout: 12_000 })
    .then(() => true)
    .catch(() => false);

  const [isAdminUrl, hasLoginError] = await Promise.all([adminUrlReached, loginErrorVisible]);

  if (!isAdminUrl && hasLoginError) {
    const loginErrorText = (await page.locator('#login_error').innerText()).trim();
    throw new Error(`WordPress login failed for user "${username}": ${loginErrorText}`);
  }

  if (!isAdminUrl) {
    throw new Error(`WordPress login did not reach /wp-admin for user "${username}" and no explicit #login_error was found.`);
  }
}

async function ensureConditionalUiDisabled(page: Page): Promise<void> {
  await page.goto('/wp-admin/options-general.php?page=advanced-passkey-login');

  const advancedTab = page.locator(SELECTORS.advancedTab).first();
  const advancedHref = await advancedTab.getAttribute('href');
  if (advancedHref) {
    await page.goto(advancedHref);
  } else {
    await advancedTab.click({ force: true });
  }

  const toggle = page.locator(SELECTORS.conditionalToggle).first();
  if (await toggle.isChecked()) {
    await toggle.evaluate((node) => {
      const input = node as HTMLInputElement;
      input.checked = false;
      input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await page.locator(SELECTORS.saveSettingsButton).first().click({ force: true });
    await page.waitForURL(/options-general\.php\?page=advanced-passkey-login/);
  }
}

async function logoutWordPressUser(page: Page): Promise<void> {
  await page.goto('/wp-login.php?action=logout');

  const confirmLogout = page.getByRole('link', { name: /log out/i });
  if (await confirmLogout.count()) {
    await confirmLogout.first().click();
  }

  await page.waitForURL(/\/wp-login\.php(?:\?.*)?$/);
}

async function installVirtualAuthenticator(page: Page): Promise<{ cdp: CDPSession; authenticatorId: string }> {
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('WebAuthn.enable');

  const { authenticatorId } = (await cdp.send('WebAuthn.addVirtualAuthenticator', {
    options: {
      protocol: 'ctap2',
      transport: 'internal',
      hasResidentKey: true,
      hasUserVerification: true,
      isUserVerified: true,
      automaticPresenceSimulation: true,
    },
  })) as { authenticatorId: string };

  return { cdp, authenticatorId };
}

async function removeVirtualAuthenticator(cdp: CDPSession, authenticatorId: string): Promise<void> {
  void cdp;
  void authenticatorId;
}

async function runSuccessfulPasskeyAuthSequence(page: Page): Promise<void> {
  await test.step('Authenticate as administrator using password flow', async () => {
    await loginWithWordPressUser(page, USERS.admin.username, USERS.admin.password);
    await page.waitForURL(/\/wp-admin\/?(?:index\.php)?(?:\?.*)?$/);
    await ensureConditionalUiDisabled(page);
  });

  const { cdp, authenticatorId } = await test.step('Provision Chromium virtual authenticator for deterministic WebAuthn', async () => {
    return installVirtualAuthenticator(page);
  });

  try {
    await test.step('Register passkey credential from profile screen', async () => {
      await page.goto('/wp-admin/profile.php');

      const registerButton = await firstAvailableLocator(page, SELECTORS.passkeyRegisterButtonIds);
      await expect(registerButton, 'Passkey registration button is required for registration sequence.').toBeVisible();

      const finishRegistration = page.waitForResponse((response) =>
        isAjaxActionRequest(response.request(), 'advapafo_finish_registration'),
      );

      // Dispatch a real DOM click: pointer-based clicks can time out waiting for "stable"
      // on this page even though the button is fully visible/interactive.
      await registerButton.evaluate((node) => (node as HTMLElement).click());

      const finishRegistrationResponse = await finishRegistration;
      expect(finishRegistrationResponse.ok(), 'Passkey finish_registration should return a successful HTTP response.').toBeTruthy();
      const finishRegistrationPayload = (await finishRegistrationResponse.json()) as { success?: boolean };
      expect(finishRegistrationPayload.success, 'Passkey finish_registration payload should indicate success=true.').toBeTruthy();
    });

    await test.step('Logout and authenticate back in with passkey button flow', async () => {
      await logoutWordPressUser(page);
      await page.goto('/wp-login.php');

      const passkeyLoginButton = await firstAvailableLocator(page, SELECTORS.passkeyLoginButtonIds);
      await expect(passkeyLoginButton, 'Passkey login button should be present on wp-login.php.').toBeVisible();

      await passkeyLoginButton.click();
      await page.waitForURL(/\/wp-admin\//);
    });
  } finally {
    await removeVirtualAuthenticator(cdp, authenticatorId);
  }
}

async function ensureLogFile(pathname: string): Promise<void> {
  await fs.mkdir(path.dirname(pathname), { recursive: true });
  await fs.appendFile(pathname, '');
}

async function getLogOffset(pathname: string): Promise<number> {
  const stat = await fs.stat(pathname);
  return stat.size;
}

async function readLogDelta(pathname: string, offset: number): Promise<string> {
  const full = await fs.readFile(pathname, 'utf8');
  const delta = full.slice(offset);
  return delta;
}

test.describe('advanced-passkey-login ecosystem edge vectors', () => {
  test('Multi-role & privilege isolation: subscriber can auth but cannot access plugin admin configuration', async ({ page }, testInfo) => {
    await test.step('Sign in as subscriber role', async () => {
      await loginWithWordPressUser(page, USERS.subscriber.username, USERS.subscriber.password);
      await page.waitForURL(/\/wp-admin\//);
    });

    const response = await test.step('Directly request plugin settings URI from low-privilege account', async () => {
      return page.goto('/wp-admin/options-general.php?page=advanced-passkey-settings');
    });

    await test.step('Assert native WordPress denial/forbidden behavior and hidden settings form', async () => {
      const status = response?.status();
      const bodyText = await page.locator('body').innerText();

      const deniedByStatus = status === 403;
      const deniedByText = /sorry, you are not allowed|permission denied|forbidden|403/i.test(bodyText);
      expect(
        deniedByStatus || deniedByText,
        `Expected permissions denial for subscriber. Status=${String(status)} body starts with: ${bodyText.slice(0, 120)}`,
      ).toBeTruthy();

      await expect(page.locator(SELECTORS.pluginSettingsRoot), 'Plugin settings form must not be visible to subscriber role.').toHaveCount(0);
      await captureEvidenceScreenshot(page, testInfo, 'subscriber denied plugin settings access');
    });
  });

  test('DOM chaos simulator: passkey selector binding remains stable under login DOM distortions', async ({ page }, testInfo) => {
    const selectorErrors: string[] = [];

    await test.step('Open wp-login.php and register JS error listeners', async () => {
      page.on('pageerror', (error) => {
        const text = String(error?.message ?? '');
        if (/queryselector|cannot read properties|undefined|null/i.test(text)) {
          selectorErrors.push(text);
        }
      });

      page.on('console', (msg) => {
        if (msg.type() !== 'error') return;
        const text = msg.text();
        if (/queryselector|cannot read properties|undefined|null/i.test(text)) {
          selectorErrors.push(text);
        }
      });

      await page.goto('/wp-login.php');
    });

    await test.step('Inject DOM chaos wrappers, duplicate controls, and style overrides', async () => {
      // Architectural note:
      // This mutation pass simulates common third-party login customizer behavior where wrappers,
      // duplicate controls, and broad CSS overrides alter DOM depth/order. We intentionally preserve
      // passkey button visibility and ID to ensure plugin logic depends on stable selectors, not brittle tree structure.
      await page.evaluate(() => {
        const form = document.querySelector('#loginform');
        if (!form || !form.parentElement) return;

        for (let i = 0; i < 3; i++) {
          const wrapper = document.createElement('div');
          wrapper.className = `chaos-wrapper-${i}`;
          form.parentElement.insertBefore(wrapper, form);
          wrapper.appendChild(form);
        }

        for (let i = 0; i < 2; i++) {
          const dummySubmit = document.createElement('button');
          dummySubmit.type = 'button';
          dummySubmit.className = `chaos-dummy-submit-${i}`;
          dummySubmit.textContent = `Dummy Submit ${i + 1}`;
          form.appendChild(dummySubmit);
        }

        const style = document.createElement('style');
        style.textContent = [
          '.login form { padding: 18px !important; border-width: 2px !important; }',
          '.chaos-wrapper-0, .chaos-wrapper-1, .chaos-wrapper-2 { margin: 4px 0; }',
          '.chaos-dummy-submit-0, .chaos-dummy-submit-1 { display: inline-block; opacity: 0.6; }',
        ].join('\n');
        document.head.appendChild(style);
      });
    });

    await test.step('Click strict passkey ID selector and assert plugin attempts execution', async () => {
      const passkeyButton = await firstAvailableLocator(page, SELECTORS.passkeyLoginButtonIds);
      await expect(passkeyButton, 'Passkey trigger must remain present after DOM chaos injection.').toBeVisible();

      const beginLoginRequestPromise = page
        .waitForRequest((request) => isAjaxActionRequest(request, 'advapafo_begin_login'), { timeout: 7_000 })
        .catch(() => null);

      await passkeyButton.click();

      const beginLoginRequest = await beginLoginRequestPromise;
      const pluginErrorNotice = await firstAvailableLocator(page, SELECTORS.passkeyErrorNotice);
      const noticeVisible = await pluginErrorNotice.isVisible().catch(() => false);

      const executionAttempted =
        !!beginLoginRequest ||
        noticeVisible ||
        (await passkeyButton
          .evaluate((node) => {
            const button = node as HTMLButtonElement;
            return button.disabled || button.classList.contains('advapafo-btn-busy');
          })
          .catch(() => false));

      expect(executionAttempted, 'Passkey trigger did not produce a begin_login request, state change, or user-facing notice.').toBeTruthy();
      expect(selectorErrors, `DOM chaos should not trigger generic selector resolution errors. Found: ${selectorErrors.join(' | ')}`).toEqual([]);
      await captureEvidenceScreenshot(page, testInfo, 'wp-login after DOM chaos selector test', { selector: '#login' });
    });
  });

  test('Multisite RpId simulation: subdirectory subsite flow registers credential under root-domain RpId', async ({ page, browserName }, testInfo) => {
    test.skip(browserName !== 'chromium', 'Multisite RpId simulation uses Chromium CDP virtual authenticators.');

    const { cdp, authenticatorId } = await test.step('Enable virtual authenticator', async () => {
      return installVirtualAuthenticator(page);
    });

    try {
      await test.step('Mock subsite login route under /site2/ path', async () => {
        await page.route('**/site2/wp-login.php*', async (route) => {
          await route.fulfill({
            status: 200,
            contentType: 'text/html',
            body: [
              '<!doctype html>',
              '<html lang="en">',
              '<head><meta charset="utf-8"><title>Site2 Login</title></head>',
              '<body>',
              '<h1>Site2 Login</h1>',
              '<button id="passkey-login-btn" type="button">Sign in with Passkey</button>',
              '</body>',
              '</html>',
            ].join(''),
          });
        });

        await page.goto(wpUrl('/site2/wp-login.php'));
      });

      await test.step('Run WebAuthn registration using mocked config with rp.id=demo.local', async () => {
        const result = await page.evaluate(async () => {
          const challenge = Uint8Array.from({ length: 32 }, (_, i) => (i + 17) % 255);
          const userId = Uint8Array.from({ length: 16 }, (_, i) => (i + 51) % 255);

          const mockedConfig: CredentialCreationOptions = {
            publicKey: {
              challenge,
              rp: {
                name: 'Demo Local Network',
                id: 'demo.local',
              },
              user: {
                id: userId,
                name: 'site2-user@demo.local',
                displayName: 'Site2 User',
              },
              pubKeyCredParams: [{ type: 'public-key' as const, alg: -7 }],
              authenticatorSelection: {
                residentKey: 'required' as const,
                userVerification: 'required' as const,
              },
              timeout: 60_000,
              attestation: 'none' as const,
            },
          };

          try {
            const credential = await navigator.credentials.create(mockedConfig);
            return {
              ok: Boolean(credential),
              error: '',
            };
          } catch (error) {
            return {
              ok: false,
              error: String((error as Error).message || error),
            };
          }
        });

        expect(result.ok, `Expected successful credential creation with root RpId on /site2 path, got error: ${result.error}`).toBeTruthy();
        expect(result.error, 'RpId mismatch should not occur when rp.id uses root registrable domain.').not.toMatch(/rp|relying party|domain|securityerror/i);
        await captureEvidenceScreenshot(page, testInfo, 'mock subsite login after root RpId credential');
      });
    } finally {
      await removeVirtualAuthenticator(cdp, authenticatorId);
    }
  });

  test('WP_DEBUG zero-tolerance: passkey auth flow emits no plugin-related PHP Notice/Warning/Deprecated lines', async ({ page, browserName }, testInfo) => {
    test.skip(browserName !== 'chromium', 'Debug-log validation requires Chromium virtual authenticator flow.');

    let offset = 0;

    await test.step('Ensure debug.log exists and mark baseline read offset', async () => {
      await ensureLogFile(DEBUG_LOG_PATH);
      offset = await getLogOffset(DEBUG_LOG_PATH);
    });

    await test.step('Run successful passkey authentication workflow', async () => {
      await runSuccessfulPasskeyAuthSequence(page);
    });

    await test.step('Read debug.log delta and assert zero plugin-related PHP notices/warnings/deprecations', async () => {
      const delta = await readLogDelta(DEBUG_LOG_PATH, offset);
      const lines = delta.split(/\r?\n/).filter(Boolean);

      const problematic = lines.filter((line) => {
        const phpSeverity = /\[PHP (Notice|Warning|Deprecated)\]|PHP (Notice|Warning|Deprecated)/i.test(line);
        const pluginRelated = /advanced-passkey-login/i.test(line);
        return phpSeverity && pluginRelated;
      });

      expect(
        problematic,
        `Expected zero plugin-related PHP Notice/Warning/Deprecated entries in debug.log delta. Found:\n${problematic.join('\n')}`,
      ).toEqual([]);
    });
  });
});
