=== Top Games ===
Contributors: vitodipinto
Tags: block, pattern, games, layout, featured, stories
Requires at least: 6.5
Tested up to: 6.6
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A dynamic block that renders “Top Games” with featured/standard cards, plus a companion “Top Stories — 3 Columns” layout pattern.

== Description ==

**Top Games** provides:

- **Dynamic block** `top-games/top-games` that renders selected games (CPT: `game` or `games`) or falls back to latest posts.
- **Pattern** “Top Stories — 3 Columns”: feature on the left, two stacked cards in the middle, and a “Top Stories” list on the right.

The block is rendered server-side (PHP) for performance and SEO, and outputs lightweight, BEM-style classes you can style in your theme.

**Author:** Vito Dipinto

== Features ==

- Select up to **2** specific games, or show **latest** with an optional **offset**.
- **Featured** variant with larger title and optional excerpt.
- Optional **heading** above the list (e.g., “Top Stories”).
- Responsive layout pattern suitable for home/section pages.
- Works with CPT slug **`game`** (preferred) or **`games`** (fallback).

== Blocks ==

1. **Top Games** — `top-games/top-games`  
   Dynamic block rendered via PHP (`includes/render-top-games.php`).

== Patterns ==

- **Top Stories — 3 Columns** (`top-stories-3col`)  
  Feature (left) • Stacked (middle) • Top Stories (right)

Inserted via the block inserter → Patterns → **Top Games**.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` and activate it, or install from a ZIP.
2. In the editor:
   - Insert the **Top Games** block from the block inserter.
   - Or insert the **Top Stories — 3 Columns** pattern from the Patterns panel.

== Usage ==

**Block attributes (editor sidebar):**
- **Selected**: choose up to 2 games (search by text or ID).
- **Items**: how many to show when none are selected (1–2).
- **Offset**: skip the first N items in fallback mode.
- **Show thumbnails**: toggle featured images.
- **Variant**: `standard` or `featured` (featured shows bigger title + optional excerpt).
- **Title size**: optional size override.
- **Heading text**: optional heading shown above the list.

**Pattern:** adds three columns containing:
1) a featured Top Games block, 2) a stacked Top Games block, 3) a Top Stories Top Games block.

== Frequently Asked Questions ==

= What post type does it read? =
The plugin looks for CPT **`game`**. If not found, it tries **`games`**.

= Can I change how items are laid out? =
Yes. The block outputs classes like `.wp-block-top-games`, `.top-games__card`, `.is-featured`, etc. Style them in your theme. The 3-column pattern is built with Core Columns; you can adapt it or switch to a Group (Grid) layout in your theme if preferred.

= Does it work without featured images or genres? =
Yes. Thumbs/genre are optional; the block checks availability.

== Screenshots ==

1. Block settings in the editor (selection, fallback, display).
2. “Top Stories — 3 Columns” pattern on the front end.

*(Add `assets/screenshot-1.png`, `assets/screenshot-2.png` to the plugin if you want them to appear here.)*

== Changelog ==

= 1.0.0 =
* Initial release: dynamic block and 3-column pattern.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

== Developer Notes ==

- **Block name:** `top-games/top-games` (see `build/top-games/block.json`)
- **Render:** `includes/render-top-games.php` (server-side)
- **Pattern registry:** files in `/patterns` are auto-registered on `init`.
- **Text domain:** `top-games`
- **Filters:** A sample `render_block` debug hook is included (commented out) in the main plugin file.

== License ==

GPLv2 or later. See the License URI.
