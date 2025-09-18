<?php
/* =========================================================
 * PMPro email-domain validation per level (ACF Options)
 * Level -> ACF repeater:
 *   1 => domain_list
 *   2 => domain_list2
 *   3 => domain_list3
 *
 * Each repeater row has sub-field: email_domain
 * Supported patterns:
 *   - Exact: "example.org"
 *   - Wildcard subdomain: "*.example.org"
 *   - Leading-dot suffix: ".example.org"  (treated like wildcard; also accepts apex)
 *
 * Default behavior: if a level has NO configured domains, BLOCK registration.
 * ========================================================= */

add_filter('pmpro_registration_checks', 'sca_pmpro_restrict_email_domains_per_level', 10, 1);

function sca_pmpro_restrict_email_domains_per_level($ok) {
    // 1) Read email from checkout
    $email = isset($_REQUEST['bemail']) ? sanitize_email(wp_unslash($_REQUEST['bemail'])) : '';
    if ($email === '') {
        return $ok; // don't fail if email isn't present yet
    }

    // 2) Resolve level id robustly
    $level_id = 0;

    // Prefer PMPro API if available (most reliable)
    if (function_exists('pmpro_getLevelAtCheckout')) {
        $level = pmpro_getLevelAtCheckout();
        if (!empty($level) && !empty($level->id)) {
            $level_id = (int) $level->id;
        }
    }

    // Fallback to request params
    if ($level_id === 0) {
        if (isset($_REQUEST['pmpro_level'])) {
            $level_id = (int) $_REQUEST['pmpro_level'];
        } elseif (isset($_REQUEST['level'])) {
            $level_id = (int) $_REQUEST['level'];
        }
    }

    // If we still don't have a level, block softly to be safe.
    if ($level_id === 0) {
        return sca_pmpro_block("We couldn't determine your membership level. Please try again or contact support.");
    }

    // 3) Map level -> ACF repeater
    switch ($level_id) {
        case 1: $acf_field = 'domain_list';  break;
        case 2: $acf_field = 'domain_list2'; break;
        case 3: $acf_field = 'domain_list3'; break;
        default:
            return sca_pmpro_block("Unknown membership level selected. Please contact support.");
    }

    // 4) Load allowed patterns for this level from ACF Options
    $allowed = sca_allowed_domains_from_acf($acf_field);

    // 5) Enforce: block-by-default if no domains configured for this level
    if (empty($allowed)) {
        return sca_pmpro_block("No approved email domains are configured for this membership level. Please contact sca@surreycare.org.uk.");
    }

    // 6) Check email domain against patterns
    if (!sca_is_email_domain_allowed($email, $allowed)) {
        return sca_pmpro_block("Your email address does not appear to be associated with a Surrey Care Association. For help, contact sca@surreycare.org.uk.");
    }

    return $ok;
}

/** ------------ Helpers ------------ */

function sca_pmpro_block($message) {
    global $pmpro_msg, $pmpro_msgt;
    $pmpro_msg  = $message;
    $pmpro_msgt = "pmpro_error";
    return false;
}

function sca_get_domain_from_email($email) {
    $parts  = explode('@', $email);
    $domain = isset($parts[1]) ? strtolower(trim($parts[1])) : '';

    // Normalize IDN domains if possible
    if ($domain !== '' && function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii) {
            $domain = strtolower($ascii);
        }
    }
    return $domain;
}

function sca_allowed_domains_from_acf($repeater_field_name) {
    $domains = [];
    if (function_exists('have_rows') && have_rows($repeater_field_name, 'option')) {
        while (have_rows($repeater_field_name, 'option')) {
            the_row();
            $d = (string) get_sub_field('email_domain');
            $d = strtolower(trim($d));
            if ($d === '') continue;

            // Normalize IDN config too
            if (function_exists('idn_to_ascii')) {
                $maybe = idn_to_ascii($d, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
                if ($maybe) $d = strtolower($maybe);
            }

            $domains[] = $d;
        }
    }
    return array_values(array_unique($domains));
}

/**
 * Match rules:
 *  - "*.example.org" => any subdomain of example.org (NOT the apex)
 *  - ".example.org"  => suffix match; accepts subdomains and apex "example.org"
 *  - "example.org"   => exact apex only
 */
function sca_is_email_domain_allowed($email, array $allowed_domains) {
    $domain = sca_get_domain_from_email($email);
    if ($domain === '') return false;

    foreach ($allowed_domains as $pattern) {
        $pattern = ltrim(strtolower($pattern));

        // Handle leading dot config like ".example.org"
        if (strpos($pattern, '*.') !== 0 && strpos($pattern, '.') === 0) {
            // Convert ".example.org" to a special suffix mode
            $base = ltrim($pattern, '.'); // "example.org"
            if ($domain === $base) return true; // allow apex
            if (endswith($domain, '.' . $base)) return true; // allow any subdomain
            continue;
        }

        // Wildcard "*.example.org" => subdomains only (not apex)
        if (substr($pattern, 0, 2) === '*.') {
            $base = substr($pattern, 2);
            if ($base === '') continue;
            if ($domain !== $base && endswith($domain, '.' . $base)) {
                return true;
            }
            continue;
        }

        // Exact apex
        if ($domain === $pattern) {
            return true;
        }
    }

    return false;
}

/** Polyfill for endswith without mbstring */
if (!function_exists('endswith')) {
    function endswith($haystack, $needle) {
        if ($needle === '') return true;
        $len = strlen($needle);
        return substr($haystack, -$len) === $needle;
    }
}
