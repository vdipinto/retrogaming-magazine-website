<?php
/**
 * Pretix-backed "instances" endpoint (unified with env helper).
 *
 * Route:
 *   GET /gaming-tickets/v1/instances?month=YYYY-MM[&event=slug]
 *
 * Behavior:
 *   - Lists subevents for the selected month (series).
 *   - Falls back to single-event date if there are no subevents.
 *   - Enriches each instance with availability from quotas.
 *   - Adds localized title from Pretix's name field.
 *   - Attaches default ticket product pricing so the frontend can show price
 *     and include itemId when adding to the cart.
 *
 * Currency notes:
 *   - Currency is defined on the Pretix **event**, not on **items**.
 *   - We fetch the event currency once, cache it, and apply it to each instance.
 *   - We DO NOT default to EUR anywhere.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ✅ Use shared env helper for Pretix config (no duplication)
require_once __DIR__ . '/env.php';

add_action('rest_api_init', function () {
  register_rest_route('gaming-tickets/v1', '/instances', [
    'methods'  => 'GET',
    'callback' => 'gts_instances',
    'permission_callback' => '__return_true',
  ]);
});

/**
 * Fetch and cache the Pretix event currency (e.g. "GBP").
 *
 * @param string $org
 * @param string $event
 * @param string $base
 * @param array  $headers
 * @return string|null ISO currency code or null on failure
 */
function gts_fetch_event_currency( $org, $event, $base, $headers ) {
    $cache_key = "gts_event_currency_{$org}_{$event}";
    $cached = get_transient( $cache_key );
    if ( is_string( $cached ) && $cached !== '' ) {
        return $cached;
    }

    $url = rtrim($base, '/') . "/api/v1/organizers/{$org}/events/{$event}/";
    $res = wp_remote_get( $url, [ 'headers' => $headers, 'timeout' => 10 ] );
    if ( is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200 ) {
        return null;
    }

    $body = json_decode( wp_remote_retrieve_body($res), true );
    $ccy  = $body['currency'] ?? null;

    if ( is_string($ccy) && $ccy !== '' ) {
        set_transient( $cache_key, $ccy, 10 * MINUTE_IN_SECONDS );
        return $ccy;
    }

    return null;
}

/**
 * INTERNAL: Fetch a "default" Pretix item (product) for an event.
 *
 * Why do we fetch this here?
 * - Instances (dates/times) don't carry prices in Pretix; prices live on Items (products).
 * - DaySummary.jsx wants to show unit price and compute a live total before adding to cart.
 * - We fetch the first active item as a simple default and attach its data to each instance.
 *
 * Caching:
 * - We cache the chosen item (id, title, price) for 10 minutes to keep the endpoint fast.
 *
 * @param string $org    Pretix organizer slug
 * @param string $event  Pretix event slug
 * @param string $base   Pretix base URL (e.g., https://pretix.example.com)
 * @param array  $headers HTTP headers (Authorization, etc.)
 * @param string $lang   2-letter locale (e.g., "en", "it")
 * @return array|null { itemId, itemTitle, price } or null if not found
 */
function gts_fetch_default_item_for_event( $org, $event, $base, $headers, $lang ) {
    $cache_key = "gts_default_item_{$org}_{$event}";
    $cached = get_transient( $cache_key );
    if ( is_array($cached) && isset($cached['itemId'], $cached['price']) ) {
        return $cached;
    }

    // NOTE: You can tweak this query to select by category/slug if needed.
    $url  = rtrim($base, '/') . "/api/v1/organizers/{$org}/events/{$event}/items/?active=true&ordering=id";
    $res  = wp_remote_get( $url, [ 'headers' => $headers, 'timeout' => 10 ] );
    if ( is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200 ) {
        return null;
    }

    $body  = json_decode( wp_remote_retrieve_body($res), true );
    $first = $body['results'][0] ?? null; // "default" = first active item
    if ( ! is_array($first) ) return null;

    // Localize the item name
    $name = 'Ticket';
    if ( ! empty($first['name']) && is_array($first['name']) ) {
        $name = $first['name'][$lang] ?? ($first['name']['en'] ?? reset($first['name']));
    }

    $out = [
        'itemId'    => (string) ($first['id'] ?? ''),
        'itemTitle' => $name,
        'price'     => isset($first['default_price']) ? (float) $first['default_price'] : 0.0,
        // Items do NOT carry currency — leave null; we will apply event currency in gts_instances().
        'currency'  => $first['currency'] ?? null,
    ];

    set_transient( $cache_key, $out, 10 * MINUTE_IN_SECONDS );
    return $out;
}

/**
 * GET /instances handler
 *
 * @param \WP_REST_Request $req Query: month (YYYY-MM), optional event (slug)
 * @return \WP_REST_Response
 */
function gts_instances( \WP_REST_Request $req ) {
    // --- Config via helper ------------------------------------------
    $env   = gts_pretix_env();
    $org   = $env['org'];
    $event = $env['event'];
    $base  = $env['base'];
    $token = $env['token'];

    // Optional per-request override of event slug
    $event = sanitize_title( $req->get_param('event') ?: $event );

    // Month filter (defaults to current UTC month)
    $month = sanitize_text_field( $req->get_param('month') ?: gmdate('Y-m') );

    // Localize titles (we use first two letters; "it_IT" -> "it")
    $lang  = substr( get_locale() ?: 'en_US', 0, 2 );

    // HTTP headers for Pretix API
    $headers = [
      'Authorization' => "Token {$token}",
      // DEV NOTE:
      // In your Docker-for-Mac environment Pretix needed an explicit Host header.
      // Keep it if you need it locally, but ensure it's NOT hard-coded in production.
      'Host'          => 'localhost:8345',  // docker-for-mac quirk; harmless elsewhere
      'User-Agent'    => 'WP-Pretix-Bridge/1.0',
      'Accept'        => 'application/json',
    ];
    $args = ['headers' => $headers, 'timeout' => 10];

    $instances = [];

    // 🔹 Get event currency once (cached). If null, we leave currency off; frontend will handle gracefully.
    $eventCurrency = gts_fetch_event_currency( $org, $event, $base, $headers );

    // 🔸 Fetch the "default" item once so we can attach price.
    //     If null, we still return instances but without price.
    $defaultItem = gts_fetch_default_item_for_event( $org, $event, $base, $headers, $lang );

    // 1) Try subevents (series)
    $res = wp_remote_get("{$base}/api/v1/organizers/{$org}/events/{$event}/subevents/?ordering=date_from", $args);
    if ( ! is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200 ) {
      $body = json_decode( wp_remote_retrieve_body($res), true );
      foreach ( ($body['results'] ?? []) as $s ) {
        $start = $s['date_from'] ?? null;
        if ( ! $start || substr($start, 0, 7) !== $month ) continue;

        // Localize subevent title
        $name = '';
        if ( ! empty($s['name']) && is_array($s['name']) ) {
          $name = $s['name'][$lang] ?? ($s['name']['en'] ?? reset($s['name']));
        }

        // Base instance shape
        $row = [
          'id'        => (string)($s['id'] ?? ''),
          'start'     => $start,
          'status'    => !empty($s['active']) ? 'onsale' : 'soldout',
          'remaining' => null,  // filled by quotas below (null = unlimited/unknown)
          'title'     => $name,
        ];

        // 🔹 Attach pricing fields if we have a default item.
        if ( $defaultItem ) {
          $row['itemId']   = $defaultItem['itemId'];     // Pretix product id
          $row['price']    = $defaultItem['price'];      // number (float)
          if ( $eventCurrency ) {
            $row['currency'] = $eventCurrency;           // e.g., "GBP"
          }
          // (Optional) include itemTitle if you want to show product name:
          // $row['itemTitle'] = $defaultItem['itemTitle'];
        }

        $instances[] = $row;
      }
    }

    // 1b) Fallback to single-event (no subevents)
    if ( empty($instances) ) {
      $one = wp_remote_get("{$base}/api/v1/organizers/{$org}/events/{$event}/", $args);
      if ( ! is_wp_error($one) && wp_remote_retrieve_response_code($one) === 200 ) {
        $e = json_decode( wp_remote_retrieve_body($one), true );

        // Localize event title
        $ename = '';
        if ( ! empty($e['name']) && is_array($e['name']) ) {
          $ename = $e['name'][$lang] ?? ($e['name']['en'] ?? reset($e['name']));
        }

        if ( ! empty($e['date_from']) && substr($e['date_from'], 0, 7) === $month ) {
          $row = [
            'id'        => $event,            // event slug (string) in fallback mode
            'start'     => $e['date_from'],
            'status'    => !empty($e['live']) ? 'onsale' : 'soldout',
            'remaining' => null,
            'title'     => $ename,
          ];

          // 🔹 Attach pricing fields in fallback too
          if ( $defaultItem ) {
            $row['itemId']   = $defaultItem['itemId'];
            $row['price']    = $defaultItem['price'];
            if ( $eventCurrency ) {
              $row['currency'] = $eventCurrency;
            }
            // $row['itemTitle'] = $defaultItem['itemTitle'];
          }

          $instances[] = $row;
        }
      }
    }

    // 2) Availability via quotas (bulk)
    // Build a list of numeric subevent IDs (string event slug is ignored)
    $ids = array_values( array_filter( array_map(
      fn($i) => ctype_digit($i['id']) ? (int)$i['id'] : null,
      $instances
    ) ) );

    if ( ! empty($ids) ) {
      $qurl = "{$base}/api/v1/organizers/{$org}/events/{$event}/quotas/?subevent__in=" . implode(',', $ids) . "&with_availability=true";
      $qres = wp_remote_get($qurl, $args);
      if ( ! is_wp_error($qres) && wp_remote_retrieve_response_code($qres) === 200 ) {
        $qbody = json_decode( wp_remote_retrieve_body($qres), true );
        $leftBySub = [];

        foreach ( ($qbody['results'] ?? []) as $q ) {
          $sub   = $q['subevent'] ?? null;
          if ( $sub === null ) continue;

          // Pretix returns "available_number"; when missing it's effectively unlimited.
          $avail = array_key_exists('available_number', $q) ? $q['available_number'] : null; // null = unlimited/unknown

          if ( ! array_key_exists($sub, $leftBySub) ) {
            $leftBySub[$sub] = $avail;
          } else {
            // combine quotas conservatively: min of known numbers, or null if any are unlimited
            if ( $leftBySub[$sub] === null || $avail === null ) {
              $leftBySub[$sub] = null;
            } else {
              $leftBySub[$sub] = min( (int) $leftBySub[$sub], (int) $avail );
            }
          }
        }

        // Project quota availability back onto the instance list
        foreach ( $instances as &$i ) {
          $sid = ctype_digit($i['id']) ? (int)$i['id'] : $i['id']; // numeric subevent id or string slug
          if ( isset($leftBySub[$sid]) ) {
            $i['remaining'] = $leftBySub[$sid]; // int or null
            if ( $i['remaining'] === 0 ) {
              $i['status'] = 'soldout';
            } elseif ( is_int($i['remaining']) && $i['remaining'] <= 5 ) {
              $i['status'] = 'limited';
            } else {
              $i['status'] = 'onsale';
            }
          }
        }
        unset($i);
      }
    }

    // 3) Response
    $data = [
      'eventId'   => $event,
      'month'     => $month,
      'instances' => array_values($instances),
      'updatedAt' => gmdate('c'),
    ];

    $r = new WP_REST_Response($data, 200);
    $r->header('Cache-Control', 'public, s-maxage=10, stale-while-revalidate=20');
    $r->header('X-Source', 'pretix+quotas+titles+default-item+event-currency');
    return $r;
}
