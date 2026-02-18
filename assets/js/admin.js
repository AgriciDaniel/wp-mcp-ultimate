/* global jQuery, wpMcpUltimate */
(function ($) {
    'use strict';

    /**
     * Copy text content of an element to clipboard.
     */
    function copyToClipboard(elementId, button) {
        var el = document.getElementById(elementId);
        if (!el) return;

        var text = el.textContent || el.innerText;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showCopied(button);
            });
        } else {
            // Fallback for older browsers.
            var textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            showCopied(button);
        }
    }

    function showCopied(button) {
        var $btn = $(button);
        var original = $btn.text();
        $btn.text('Copied!').addClass('copied');
        setTimeout(function () {
            $btn.text(original).removeClass('copied');
        }, 2000);
    }

    /**
     * Update config blocks with real base64 credentials.
     */
    function updateConfigs(base64) {
        $('.mcp-placeholder').each(function () {
            $(this).text(base64).removeClass('mcp-placeholder');
        });
    }

    /**
     * Switch UI to "key active" state.
     */
    function showKeyActive() {
        $('#mcp-key-status').html(
            '<span class="mcp-badge mcp-badge-active">API Key Active</span>' +
            '<button type="button" class="button button-secondary" id="mcp-revoke-key">Revoke Key</button>'
        );
        $('#mcp-test-connection').prop('disabled', false);
        $('#mcp-connection-dot').removeClass('mcp-dot-inactive mcp-dot-active').addClass('mcp-dot-unknown');
        $('#mcp-connection-text').text('Key configured \u2014 test to verify');
    }

    /**
     * Switch UI to "no key" state.
     */
    function showNoKey() {
        $('#mcp-key-status').html(
            '<button type="button" class="button button-primary" id="mcp-generate-key">Generate API Key</button>'
        );
        $('#mcp-key-output').hide();
        $('#mcp-test-connection').prop('disabled', true);
        $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
        $('#mcp-connection-text').text('No API key configured');

        // Reset placeholders.
        $('.mcp-code-wrapper .mcp-placeholder').text('BASE64_ENCODED_CREDENTIALS');
    }

    // ---- Event handlers ----

    $(document).on('click', '#mcp-generate-key', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Generating...');

        $.post(wpMcpUltimate.ajaxUrl, {
            action: 'wp_mcp_ultimate_generate_key',
            nonce: wpMcpUltimate.nonce
        }, function (response) {
            if (response.success) {
                var data = response.data;
                $('#mcp-key-value').text(data.password);
                $('#mcp-key-output').show();
                updateConfigs(data.base64);
                showKeyActive();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Generate API Key');
            }
        }).fail(function () {
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false).text('Generate API Key');
        });
    });

    $(document).on('click', '#mcp-revoke-key', function () {
        if (!confirm('Are you sure you want to revoke the API key? MCP clients will stop working.')) {
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Revoking...');

        $.post(wpMcpUltimate.ajaxUrl, {
            action: 'wp_mcp_ultimate_revoke_key',
            nonce: wpMcpUltimate.nonce
        }, function (response) {
            if (response.success) {
                showNoKey();
            } else {
                alert('Error: ' + (response.data && response.data.message ? response.data.message : 'Unknown error'));
                $btn.prop('disabled', false).text('Revoke Key');
            }
        }).fail(function () {
            alert('Request failed. Please try again.');
            $btn.prop('disabled', false).text('Revoke Key');
        });
    });

    $(document).on('click', '#mcp-test-connection', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Testing...');

        $.post(wpMcpUltimate.ajaxUrl, {
            action: 'wp_mcp_ultimate_test_connection',
            nonce: wpMcpUltimate.nonce
        }, function (response) {
            if (response.success) {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-inactive').addClass('mcp-dot-active');
                $('#mcp-connection-text').text('Connected');
            } else {
                $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
                $('#mcp-connection-text').text(response.data && response.data.message ? response.data.message : 'Connection failed');
            }
            $btn.prop('disabled', false).text('Test Connection');
        }).fail(function () {
            $('#mcp-connection-dot').removeClass('mcp-dot-unknown mcp-dot-active').addClass('mcp-dot-inactive');
            $('#mcp-connection-text').text('Request failed');
            $btn.prop('disabled', false).text('Test Connection');
        });
    });

    // Copy buttons.
    $(document).on('click', '.mcp-copy-btn', function () {
        var target = $(this).data('target');
        if (target) {
            copyToClipboard(target, this);
        }
    });

})(jQuery);
