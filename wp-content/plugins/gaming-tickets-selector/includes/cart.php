<?php
/**
 * Cart REST API (server-side basket) for Gaming Tickets.
 *
 * Improvements:
 *  - Fetch price from Pretix /items/{id}/
 *  - Fetch currency from Pretix /events/{slug}/
 *  - Backfill price/currency if missing
 *  - Avoid 502 on Pretix errors (fallbacks allowed)
 *  - Merge key: instanceId::date::itemId
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/env.php';

add_action('rest_api_init', function () {
    register_rest_route('gaming-tickets/v1', '/cart', [
        'methods'  => 'GET',
        'callback' => 'gts_cart_rest_get',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('gaming-tickets/v1', '/cart', [
        'methods'  => 'DELETE',
        'callback' => 'gts_cart_rest_clear',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('gaming-tickets/v1', '/cart/items', [
        'methods'  => 'POST',
        'callback' => 'gts_cart_rest_add',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
        'args' => [
            'type'          => [ 'required' => true ],
            'eventId'       => [ 'required' => true ],
            'instanceId'    => [ 'required' => true ],
            'instanceTitle' => [ 'required' => true ],
            'date'          => [ 'required' => true ],
            'start'         => [ 'required' => true ],
            'itemId'        => [ 'required' => true ],
            'quantity'      => [
                'required' => true,
                'validate_callback' => fn($v) => is_numeric($v),
                'sanitize_callback' => fn($v) => max(1, (int) $v),
            ],
            'price'         => [ 'required' => false ],
            'currency'      => [ 'required' => false ],
        ],
    ]);

    register_rest_route('gaming-tickets/v1', '/cart/items/(?P<lineId>[A-Za-z0-9\-]+)', [
        'methods'  => 'PATCH',
        'callback' => 'gts_cart_rest_update_by_id',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
        'args' => [
            'quantity' => [
                'required' => true,
                'validate_callback' => fn($v) => is_numeric($v),
                'sanitize_callback' => fn($v) => max(0, (int) $v),
            ],
        ],
    ]);

    register_rest_route('gaming-tickets/v1', '/cart/items/(?P<lineId>[A-Za-z0-9\-]+)', [
        'methods'  => 'DELETE',
        'callback' => 'gts_cart_rest_remove_by_id',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
    ]);

    // Legacy routes
    register_rest_route('gaming-tickets/v1', '/cart/items', [
        'methods'  => 'PATCH',
        'callback' => 'gts_cart_rest_update',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
    ]);
    register_rest_route('gaming-tickets/v1', '/cart/items', [
        'methods'  => 'DELETE',
        'callback' => 'gts_cart_rest_remove',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
    ]);

    register_rest_route('gaming-tickets/v1', '/cart/checkout', [
        'methods'  => 'POST',
        'callback' => 'gts_cart_rest_checkout',
        'permission_callback' => 'gts_cart_permission_optional_nonce',
    ]);
});

function gts_cart_permission_optional_nonce( WP_REST_Request $req ) {
    $nonce = $req->get_header('x-wp-nonce');
    return $nonce ? wp_verify_nonce($nonce, 'wp_rest') : true;
}

function gts_cart_key() {
    if ( ! empty($_COOKIE['gts_cart_key']) ) {
        return sanitize_text_field($_COOKIE['gts_cart_key']);
    }
    $key = wp_generate_uuid4();
    setcookie('gts_cart_key', $key, time() + 7 * DAY_IN_SECONDS,
        COOKIEPATH ?: '/', COOKIE_DOMAIN ?: '', is_ssl(), true);
    return $key;
}


//this function loads the cart items from the transient. If the transient is not set, it returns an empty array. If it is set, it returns the items.
function gts_cart_load($key) {
    $items = get_transient("gts_cart_$key");
    return is_array($items) ? $items : [];
}

//this function saves the cart items to the transient. It also expires the transient after 1 day. It sets the transient to the array of items.
function gts_cart_save($key, $items) {
    set_transient("gts_cart_$key", array_values($items), DAY_IN_SECONDS);
}

/* ---------------- Pretix helpers ---------------- */

function gts_pretix_fetch_event_currency() {
    $env = gts_pretix_env();
    $cache_key = "gts_pretix_event_currency_{$env['org']}_{$env['event']}";

    $cached = get_transient($cache_key);
    if ($cached) return $cached;
    if (!empty($env['currency'])) {
        //we cache the currency for 1 hour
        set_transient($cache_key, $env['currency'], 3600);
        return $env['currency'];
    }

    $url = rtrim($env['base'], '/') . "/api/v1/organizers/{$env['org']}/events/{$env['event']}/";
    $res = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'Authorization' => "Token {$env['token']}",
            'Accept'        => 'application/json',
            'Host'          => 'localhost:8345',
        ],
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return null;
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($json) || empty($json['currency'])) return null;

    $currency = $json['currency'];
    //we cache the currency for 1 hour
    set_transient($cache_key, $currency, 3600);
    return $currency;
}

function gts_pretix_fetch_item_price($itemId) {
    $env = gts_pretix_env();
    $cache_key = "gts_pretix_item_price_{$env['org']}_{$env['event']}_{$itemId}";
    //we cache the price for 10 minutes
    $cached = get_transient($cache_key);
    if ($cached !== false) return (float) $cached;

    $url = rtrim($env['base'], '/') . "/api/v1/organizers/{$env['org']}/events/{$env['event']}/items/" . rawurlencode($itemId) . "/";
    $res = wp_remote_get($url, [
        'timeout' => 10,
        'headers' => [
            'Authorization' => "Token {$env['token']}",
            'Accept'        => 'application/json',
            'Host'          => 'localhost:8345',
        ],
    ]);
    if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) return null;
    $json = json_decode(wp_remote_retrieve_body($res), true);
    if (!is_array($json)) return null;

    $price = isset($json['default_price']) ? (float)$json['default_price'] : null;
    if ($price === null) return null;

    set_transient($cache_key, $price, 600);
    return $price;
}

function gts_pretix_fetch_item_price_currency($itemId) {
    $price    = gts_pretix_fetch_item_price($itemId);
    $currency = gts_pretix_fetch_event_currency();
    if ($price === null || !$currency) return null;
    return [ 'price' => $price, 'currency' => $currency ];
}

/* ---------------- REST handlers ---------------- */

function gts_cart_rest_get() {
    $key     = gts_cart_key();
    $items   = gts_cart_load($key);
    $changed = false;

    $subtotal = 0.0;
    $currency = null;

    foreach ($items as &$it) {
        if (empty($it['lineId'])) {
            $it['lineId'] = wp_generate_uuid4();
            $changed = true;
        }
        if ((!isset($it['price']) || !isset($it['currency'])) && !empty($it['itemId'])) {
            $pc = gts_pretix_fetch_item_price_currency($it['itemId']);
            if ($pc) {
                $it['price']    = $pc['price'];
                $it['currency'] = $pc['currency'];
                $changed = true;
            }
        }
        if (isset($it['price'], $it['quantity'])) {
            $subtotal += ((float)$it['price']) * (int)$it['quantity'];
            if (!$currency && !empty($it['currency'])) $currency = $it['currency'];
        }
    }
    unset($it);

    if ($changed) gts_cart_save($key, $items);

    return new WP_REST_Response([
        'items'  => $items,
        'totals' => [
            'currency' => $currency ?: (gts_pretix_fetch_event_currency() ?: 'EUR'),
            'subtotal' => round($subtotal, 2),
        ],
    ], 200);
}

function gts_cart_rest_clear() {
    $key = gts_cart_key();
    gts_cart_save($key, []);
    return new WP_REST_Response([
        'ok' => true,
        'items' => [],
        'totals' => ['currency' => (gts_pretix_fetch_event_currency() ?: 'EUR'), 'subtotal' => 0]
    ], 200);
}

function gts_cart_rest_add(WP_REST_Request $req) {
    $key   = gts_cart_key();
    $items = gts_cart_load($key);

    $payload = [
        'type'          => 'ticket.add',
        'eventId'       => sanitize_text_field($req['eventId']),
        'instanceId'    => sanitize_text_field($req['instanceId']),
        'instanceTitle' => sanitize_text_field($req['instanceTitle']),
        'date'          => sanitize_text_field($req['date']),
        'start'         => sanitize_text_field($req['start']),
        'itemId'        => sanitize_text_field($req['itemId']),
        'quantity'      => max(1, (int)$req['quantity']),
    ];

    $pc = gts_pretix_fetch_item_price_currency($payload['itemId']);
    $payload['price']    = $pc['price']    ?? ($req['price'] ?? null);
    $payload['currency'] = $pc['currency'] ?? ($req['currency'] ?? null);

    $MAX_QTY = 2;
    $payload['quantity'] = min($MAX_QTY, $payload['quantity']);

    $merge_key = $payload['instanceId'].'::'.$payload['date'].'::'.$payload['itemId'];
    $found = false;
    foreach ($items as &$it) {
        $it_key = ($it['instanceId'] ?? '').'::'.($it['date'] ?? '').'::'.($it['itemId'] ?? '');
        if ($it_key === $merge_key) {
            if (empty($it['lineId'])) $it['lineId'] = wp_generate_uuid4();
            $it['quantity'] = min($MAX_QTY, (int)$it['quantity'] + (int)$payload['quantity']);
            if (!isset($it['price']) && isset($payload['price'])) $it['price'] = $payload['price'];
            if (!isset($it['currency']) && isset($payload['currency'])) $it['currency'] = $payload['currency'];
            $found = true;
            break;
        }
    }
    unset($it);
    if (!$found) {
        $payload['lineId'] = wp_generate_uuid4();
        $items[] = $payload;
    }
    gts_cart_save($key, $items);
    return gts_cart_rest_get();
}

function gts_cart_rest_update_by_id(WP_REST_Request $req) {
    $key     = gts_cart_key();
    $items   = gts_cart_load($key);
    $lineId  = sanitize_text_field($req['lineId']);
    $MAX_QTY = 2;
    $qty     = min($MAX_QTY, max(0, (int)$req['quantity']));

    $next = [];
    foreach ($items as $it) {
        if (($it['lineId'] ?? '') === $lineId) {
            if ($qty > 0) { $it['quantity'] = $qty; $next[] = $it; }
        } else $next[] = $it;
    }
    gts_cart_save($key, $next);
    return gts_cart_rest_get();
}

function gts_cart_rest_remove_by_id(WP_REST_Request $req) {
    $key    = gts_cart_key();
    $items  = gts_cart_load($key);
    $lineId = sanitize_text_field($req['lineId']);
    $next = array_values(array_filter($items, fn($it) => ($it['lineId'] ?? '') !== $lineId));
    gts_cart_save($key, $next);
    return gts_cart_rest_get();
}

function gts_cart_rest_update(WP_REST_Request $req) {
    $key   = gts_cart_key();
    $items = gts_cart_load($key);
    $merge = sanitize_text_field($req['instanceId']).'::'.sanitize_text_field($req['date']);
    $MAX_QTY = 2;
    $qty   = min($MAX_QTY, max(0, (int)$req['quantity']));

    $next = [];
    foreach ($items as $it) {
        $is_target = (($it['instanceId'] ?? '').'::'.($it['date'] ?? '')) === $merge;
        if ($is_target) {
            if ($qty > 0) { $it['quantity'] = $qty; $next[] = $it; }
        } else $next[] = $it;
    }
    gts_cart_save($key, $next);
    return gts_cart_rest_get();
}

function gts_cart_rest_remove(WP_REST_Request $req) {
    $key   = gts_cart_key();
    $items  = gts_cart_load($key);
    $merge = sanitize_text_field($req['instanceId']).'::'.sanitize_text_field($req['date']);
    $filtered = array_values(array_filter(
        $items, fn($it) => (($it['instanceId'] ?? '').'::'.($it['date'] ?? '')) !== $merge
    ));
    gts_cart_save($key, $filtered);
    return gts_cart_rest_get();
}

function gts_cart_rest_checkout(WP_REST_Request $req) {
    $env   = gts_pretix_env();
    $key   = gts_cart_key();
    $items = gts_cart_load($key);
    $pretend_checkout = site_url('/basket/confirm/');
    return new WP_REST_Response([
        'ok'       => true,
        'items'    => $items,
        'redirect' => $pretend_checkout,
        'pretix'   => [
            'org'   => $env['org'],
            'event' => $env['event'],
            'base'  => $env['base'],
        ],
    ], 200);
}
