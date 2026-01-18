/**
 * Casa Alba Headless Auth - Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Auto-refresh stats on analytics page
        if ($('.casa-alba-stat-card').length > 0) {
            // Optional: Add auto-refresh functionality
            // setInterval(refreshStats, 60000); // Refresh every minute
        }

        // Confirm before revoking sessions
        $('.revoke-session-btn').on('click', function(e) {
            if (!confirm(casaAlbaAuth.strings.confirmRevoke)) {
                e.preventDefault();
            }
        });

        // Generate JWT secret
        $('#generate-jwt-secret').on('click', function() {
            var secret = generateRandomSecret(64);
            $('#jwt_secret').val(secret);
        });

        // Toggle Turnstile fields based on checkbox
        $('#turnstile_enabled').on('change', function() {
            var isEnabled = $(this).is(':checked');
            $('#turnstile_site_key, #turnstile_secret_key').prop('disabled', !isEnabled);
        }).trigger('change');

        // Toggle Rate Limiting fields based on checkbox
        $('#rate_limit_enabled').on('change', function() {
            var isEnabled = $(this).is(':checked');
            $('#rate_limit_max_attempts, #rate_limit_window, #rate_limit_lockout_duration').prop('disabled', !isEnabled);
        }).trigger('change');

        // Format time inputs
        $('input[type="number"][name$="_expiration"], input[type="number"][name$="_window"], input[type="number"][name$="_duration"]').on('blur', function() {
            var seconds = parseInt($(this).val());
            if (!isNaN(seconds)) {
                var humanTime = formatSeconds(seconds);
                $(this).siblings('.description').find('.time-display').remove();
                $(this).siblings('.description').append(' <strong class="time-display">(' + humanTime + ')</strong>');
            }
        });

        // Real-time validation for email in analytics
        $('#filter-email').on('input', function() {
            var email = $(this).val().toLowerCase();
            $('.analytics-table tbody tr').each(function() {
                var rowEmail = $(this).find('td:first').text().toLowerCase();
                if (email === '' || rowEmail.includes(email)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });

        // Check for plugin updates
        $('#casa-alba-check-updates').on('click', function() {
            var $button = $(this);
            var $result = $('#casa-alba-update-result');

            $button.prop('disabled', true).find('.dashicons').addClass('spin');
            $result.hide();

            $.ajax({
                url: casaAlbaHeadless.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'casa_alba_check_updates',
                    nonce: casaAlbaHeadless.nonce
                },
                success: function(response) {
                    $button.prop('disabled', false).find('.dashicons').removeClass('spin');

                    if (response.success) {
                        var data = response.data;
                        $('#casa-alba-latest-version').text(data.new_version || data.latest_version);
                        $('#casa-alba-checked-at').text(data.checked_at || new Date().toLocaleString());

                        if (data.update_available) {
                            $result.html('<div class="notice notice-warning inline"><p><strong>¡Nueva versión ' + data.new_version + ' disponible!</strong> Recarga la página para ver el botón de actualización.</p></div>').show();
                            // Reload page to show update button
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            $result.html('<div class="notice notice-success inline"><p>Estás usando la última versión (' + data.current_version + ')</p></div>').show();
                        }
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>Error al verificar actualizaciones: ' + (response.data.message || 'Error desconocido') + '</p></div>').show();
                    }
                },
                error: function() {
                    $button.prop('disabled', false).find('.dashicons').removeClass('spin');
                    $result.html('<div class="notice notice-error inline"><p>Error de conexión al verificar actualizaciones</p></div>').show();
                }
            });
        });

    });

    /**
     * Generate random secret
     */
    function generateRandomSecret(length) {
        var chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
        var secret = '';
        var array = new Uint32Array(length);
        window.crypto.getRandomValues(array);

        for (var i = 0; i < length; i++) {
            secret += chars[array[i] % chars.length];
        }

        return secret;
    }

    /**
     * Format seconds to human readable time
     */
    function formatSeconds(seconds) {
        var days = Math.floor(seconds / 86400);
        var hours = Math.floor((seconds % 86400) / 3600);
        var minutes = Math.floor((seconds % 3600) / 60);
        var secs = seconds % 60;

        var parts = [];
        if (days > 0) parts.push(days + ' día' + (days > 1 ? 's' : ''));
        if (hours > 0) parts.push(hours + ' hora' + (hours > 1 ? 's' : ''));
        if (minutes > 0) parts.push(minutes + ' minuto' + (minutes > 1 ? 's' : ''));
        if (secs > 0 && parts.length === 0) parts.push(secs + ' segundo' + (secs > 1 ? 's' : ''));

        return parts.join(', ');
    }

    /**
     * Refresh statistics (optional feature)
     */
    function refreshStats() {
        $.ajax({
            url: casaAlbaAuth.ajaxUrl,
            type: 'POST',
            data: {
                action: 'casa_alba_get_stats',
                nonce: casaAlbaAuth.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateStatsDisplay(response.data);
                }
            }
        });
    }

    /**
     * Update stats display
     */
    function updateStatsDisplay(stats) {
        // Update stat cards with animation
        $('.casa-alba-stat-card').each(function() {
            var $card = $(this);
            var statKey = $card.data('stat-key');
            if (stats[statKey] !== undefined) {
                var $number = $card.find('.stat-number');
                animateNumber($number, stats[statKey]);
            }
        });
    }

    /**
     * Animate number change
     */
    function animateNumber($element, newValue) {
        var currentValue = parseInt($element.text().replace(/[^0-9]/g, ''));
        if (isNaN(currentValue)) currentValue = 0;

        $({ value: currentValue }).animate({ value: newValue }, {
            duration: 500,
            easing: 'swing',
            step: function() {
                $element.text(Math.round(this.value).toLocaleString());
            },
            complete: function() {
                $element.text(newValue.toLocaleString());
            }
        });
    }

})(jQuery);

