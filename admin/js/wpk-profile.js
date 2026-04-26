/* global WPKProfile */
(function () {
    'use strict';

    // ── Base64url helpers ───────────────────────────────────────────────────

    function b64urlToBuffer(input) {
        var base64 = input.replace(/-/g, '+').replace(/_/g, '/');
        var pad = base64.length % 4;
        if (pad) base64 += '='.repeat(4 - pad);
        var binary = atob(base64);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        return bytes.buffer;
    }

    function bufferToB64url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        for (var i = 0; i < bytes.byteLength; i++) binary += String.fromCharCode(bytes[i]);
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }

    // ── DOM helpers ─────────────────────────────────────────────────────────

    function setMessage(text, isError) {
        var node = document.getElementById('wpk-passkey-profile-message');
        if (!node) return;
        node.textContent = text;
        node.classList.toggle('wpk-msg-error',   !!isError);
        node.classList.toggle('wpk-msg-success',  !isError && !!text);
        node.style.display = text ? '' : 'none';
    }

    function hydrateCreateOptions(options) {
        options.publicKey.challenge = b64urlToBuffer(options.publicKey.challenge);
        options.publicKey.user.id  = b64urlToBuffer(options.publicKey.user.id);
        if (Array.isArray(options.publicKey.excludeCredentials)) {
            options.publicKey.excludeCredentials = options.publicKey.excludeCredentials.map(function (item) {
                item.id = b64urlToBuffer(item.id);
                return item;
            });
        }
        return options;
    }

    // ── AJAX ────────────────────────────────────────────────────────────────

    async function postForm(data) {
        var resp = await fetch(WPKProfile.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: data,
        });
        if (!resp.ok) {
            throw new Error(WPKProfile.messages.failed);
        }
        return resp.json();
    }

    // ── Register ────────────────────────────────────────────────────────────

    async function registerPasskey() {
        var labelInput = document.getElementById('wpk-passkey-label');
        var label = labelInput ? labelInput.value.trim() : '';

        setMessage(WPKProfile.messages.starting, false);

        var beginData = new FormData();
        beginData.append('action', 'wpk_begin_registration');
        beginData.append('nonce',  WPKProfile.nonce);

        var beginResp = await postForm(beginData);
        if (!beginResp || !beginResp.success) {
            var errMsg = (beginResp && beginResp.data && beginResp.data.message) || WPKProfile.messages.failed;
            // Surface "limit reached" message specifically
            if (errMsg.toLowerCase().includes('maximum')) {
                throw new Error(WPKProfile.messages.limitReached || errMsg);
            }
            throw new Error(errMsg);
        }

        var options    = hydrateCreateOptions(beginResp.data.options);
        var credential = await navigator.credentials.create(options);

        var finishData = new FormData();
        finishData.append('action',            'wpk_finish_registration');
        finishData.append('nonce',             WPKProfile.nonce);
        finishData.append('token',             beginResp.data.token);
        finishData.append('clientDataJSON',    bufferToB64url(credential.response.clientDataJSON));
        finishData.append('attestationObject', bufferToB64url(credential.response.attestationObject));
        finishData.append('label',             label);

        if (typeof credential.response.getTransports === 'function') {
            finishData.append('transports', JSON.stringify(credential.response.getTransports()));
        }

        var finishResp = await postForm(finishData);
        if (!finishResp || !finishResp.success) {
            throw new Error((finishResp && finishResp.data && finishResp.data.message) || WPKProfile.messages.failed);
        }

        setMessage(WPKProfile.messages.success, false);
        setTimeout(function () { window.location.reload(); }, 800);
    }

    // ── Revoke ──────────────────────────────────────────────────────────────

    async function revokePasskey(row) {
        var credentialId = row.getAttribute('data-credential-id');
        if (!credentialId) return;

        var data = new FormData();
        data.append('action',       'wpk_revoke_credential');
        data.append('nonce',        WPKProfile.nonce);
        data.append('credentialId', credentialId);

        var resp = await postForm(data);
        if (!resp || !resp.success) {
            throw new Error((resp && resp.data && resp.data.message) || WPKProfile.messages.revokeFailed);
        }
        window.location.reload();
    }

    // ── Event binding ───────────────────────────────────────────────────────

    function init() {
        // Check WebAuthn support
        var registerBtn = document.getElementById('wpk-passkey-register');
        if (registerBtn) {
            if (!window.PublicKeyCredential || !navigator.credentials || !navigator.credentials.create) {
                registerBtn.disabled = true;
                setMessage(WPKProfile.messages.notSupported, true);
                return;
            }

            registerBtn.addEventListener('click', function (e) {
                e.preventDefault();
                registerBtn.disabled = true;
                registerPasskey()
                    .catch(function (err) {
                        setMessage(err.message || WPKProfile.messages.failed, true);
                    })
                    .finally(function () {
                        registerBtn.disabled = false;
                    });
            });
        }

        // Revoke buttons
        document.querySelectorAll('.wpk-passkey-revoke').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                if (!window.confirm(WPKProfile.messages.confirmRevoke)) return;
                var row = btn.closest('tr');
                if (!row) return;
                btn.disabled = true;
                revokePasskey(row).catch(function (err) {
                    setMessage(err.message || WPKProfile.messages.revokeFailed, true);
                    btn.disabled = false;
                });
            });
        });
    }

    document.addEventListener('DOMContentLoaded', init);
})();
