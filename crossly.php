<?php
/**
 * Plugin Name:       Crossly
 * Plugin URI:        https://crossly.net
 * Description:       Show your Crossly listings on your WordPress site and let visitors buy them. Shortcode, Gutenberg block, or a widget.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Crossly
 * Author URI:        https://crossly.net
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       crossly
 *
 * ── WHAT THIS PLUGIN IS ──────────────────────────────────────────────
 * A thin wrapper around the Crossly embed: it enqueues one script and emits a
 * custom element. That is the whole plugin, on purpose.
 *
 * The temptation is to render the catalog in PHP — fetch listings server-side,
 * build the markup, cache it in a transient. That would mean re-implementing
 * the grid, the styling hooks, the error states and the checkout handoff in a
 * second language, and then maintaining two of everything that drifts apart
 * the first time either changes. The embed already does all of it and is
 * tested; this plugin's job is to let somebody who has never seen a <script>
 * tag use it from the block editor.
 *
 * ── WHY THE KEY IS NOT TREATED AS A SECRET ───────────────────────────
 * `crossly_pk_…` is a PUBLISHABLE key. It is designed to sit in page source
 * and can only read the seller's own active listings and start a checkout.
 * So it is stored in a normal option and printed into the page — no
 * obfuscation theatre that would imply a protection that does not exist.
 */

if (!defined('ABSPATH')) {
    exit; // Loaded directly. Nothing here should run outside WordPress.
}

define('CROSSLY_VERSION', '0.1.0');
define('CROSSLY_EMBED_SRC', 'https://crossly.net/embed.js');
define('CROSSLY_OPTION_KEY', 'crossly_publishable_key');

/**
 * Register the embed script.
 *
 * Registered rather than enqueued, and enqueued only where a shortcode or
 * block actually rendered. A site with one Crossly page should not ship the
 * script on every other page — that is somebody else's Core Web Vitals.
 */
function crossly_register_assets(): void {
    wp_register_script(
        'crossly-embed',
        CROSSLY_EMBED_SRC,
        [],
        CROSSLY_VERSION,
        ['strategy' => 'async', 'in_footer' => true]
    );
}
add_action('wp_enqueue_scripts', 'crossly_register_assets');
add_action('enqueue_block_assets', 'crossly_register_assets');

/** The seller's publishable key, or '' if they have not saved one yet. */
function crossly_get_key(): string {
    return (string) get_option(CROSSLY_OPTION_KEY, '');
}

/**
 * Render a custom element with escaped attributes.
 *
 * Shared by the shortcode and the block so the two cannot drift. Every value
 * goes through esc_attr — shortcode attributes are author-supplied and land in
 * HTML, and "it's only the site owner" is how stored XSS gets in on a
 * multi-author site.
 */
function crossly_render_element(string $tag, array $attrs): string {
    if (crossly_get_key() === '') {
        // Visible only to someone who can fix it. A visitor gets nothing
        // rather than a broken-looking box or an explanation they cannot act
        // on.
        if (current_user_can('manage_options')) {
            return '<p class="crossly-notice">' .
                esc_html__('Crossly: add your publishable key in Settings → Crossly.', 'crossly') .
                '</p>';
        }
        return '';
    }

    wp_enqueue_script('crossly-embed');

    $html = '<' . esc_attr($tag);
    foreach ($attrs as $name => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $html .= ' ' . esc_attr($name) . '="' . esc_attr((string) $value) . '"';
    }
    $html .= '></' . esc_attr($tag) . '>';

    return $html;
}

/**
 * [crossly_catalog limit="12" q="jacket"]
 */
function crossly_catalog_shortcode($atts): string {
    $atts = shortcode_atts(
        ['limit' => '', 'q' => '', 'key' => ''],
        $atts,
        'crossly_catalog'
    );

    return crossly_render_element('crossly-catalog', [
        // A per-shortcode key wins, so one site can show two shops.
        'pk'    => $atts['key'] !== '' ? $atts['key'] : crossly_get_key(),
        'limit' => $atts['limit'],
        'q'     => $atts['q'],
    ]);
}
add_shortcode('crossly_catalog', 'crossly_catalog_shortcode');

/**
 * [crossly_buy listing="…" label="Buy this"]
 */
function crossly_buy_shortcode($atts): string {
    $atts = shortcode_atts(
        ['listing' => '', 'label' => '', 'quantity' => '', 'key' => ''],
        $atts,
        'crossly_buy'
    );

    if ($atts['listing'] === '') {
        if (current_user_can('manage_options')) {
            return '<p class="crossly-notice">' .
                esc_html__('Crossly: [crossly_buy] needs a listing="…" id.', 'crossly') .
                '</p>';
        }
        return '';
    }

    return crossly_render_element('crossly-buy-button', [
        'pk'       => $atts['key'] !== '' ? $atts['key'] : crossly_get_key(),
        'listing'  => $atts['listing'],
        'label'    => $atts['label'],
        'quantity' => $atts['quantity'],
    ]);
}
add_shortcode('crossly_buy', 'crossly_buy_shortcode');

/**
 * The Gutenberg block.
 *
 * Server-rendered via `render_callback` rather than shipping a JS editor
 * bundle. The block's whole output is one custom element, so a build step and
 * a second copy of the rendering logic would buy nothing — and `block.json`
 * with `usesContext` keeps it working in the site editor and in classic
 * widgets alike.
 */
function crossly_register_block(): void {
    if (!function_exists('register_block_type')) {
        return;
    }
    register_block_type(__DIR__ . '/block', [
        'render_callback' => static function ($attributes): string {
            return crossly_render_element('crossly-catalog', [
                'pk'    => crossly_get_key(),
                'limit' => $attributes['limit'] ?? '',
                'q'     => $attributes['query'] ?? '',
            ]);
        },
    ]);
}
add_action('init', 'crossly_register_block');

// ── Settings ─────────────────────────────────────────────────────────

function crossly_settings_menu(): void {
    add_options_page(
        __('Crossly', 'crossly'),
        __('Crossly', 'crossly'),
        'manage_options',
        'crossly',
        'crossly_settings_page'
    );
}
add_action('admin_menu', 'crossly_settings_menu');

function crossly_register_settings(): void {
    register_setting('crossly', CROSSLY_OPTION_KEY, [
        'type'              => 'string',
        'sanitize_callback' => 'crossly_sanitize_key',
        'default'           => '',
    ]);
}
add_action('admin_init', 'crossly_register_settings');

/**
 * Reject anything that is not shaped like a publishable key.
 *
 * Specifically catches a seller pasting a Personal Access Token, which is the
 * mistake that actually matters: a PAT is a full-access credential and this
 * plugin prints its option into a public page. Refusing it here is the last
 * place we can stop that before it is on the internet.
 */
function crossly_sanitize_key($value): string {
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (strpos($value, 'crossly_pat_') === 0 || strpos($value, 'crossly_oat_') === 0) {
        add_settings_error(
            CROSSLY_OPTION_KEY,
            'crossly_secret_key',
            __(
                'That is a Personal Access Token, not a publishable key. It grants full access to your account and this plugin prints the key into your public pages — it has not been saved. Mint a publishable key in Crossly under Settings → Embeds; it starts with crossly_pk_.',
                'crossly'
            ),
            'error'
        );
        return (string) get_option(CROSSLY_OPTION_KEY, '');
    }

    if (strpos($value, 'crossly_pk_') !== 0) {
        add_settings_error(
            CROSSLY_OPTION_KEY,
            'crossly_bad_key',
            __('A Crossly publishable key starts with crossly_pk_.', 'crossly'),
            'error'
        );
        return (string) get_option(CROSSLY_OPTION_KEY, '');
    }

    return sanitize_text_field($value);
}

function crossly_settings_page(): void {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Crossly', 'crossly'); ?></h1>

        <form action="options.php" method="post">
            <?php
            settings_fields('crossly');
            settings_errors(CROSSLY_OPTION_KEY);
            ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="crossly_key"><?php echo esc_html__('Publishable key', 'crossly'); ?></label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="crossly_key"
                            name="<?php echo esc_attr(CROSSLY_OPTION_KEY); ?>"
                            value="<?php echo esc_attr(crossly_get_key()); ?>"
                            class="regular-text code"
                            placeholder="crossly_pk_live_…"
                        >
                        <p class="description">
                            <?php
                            echo esc_html__(
                                'From Crossly → Settings → Embeds. This key is safe to publish: it can read your active listings and start a checkout, and nothing else.',
                                'crossly'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2><?php echo esc_html__('Using it', 'crossly'); ?></h2>
        <p><?php echo esc_html__('Add the Crossly Catalog block, or paste a shortcode:', 'crossly'); ?></p>
        <p><code>[crossly_catalog]</code></p>
        <p><code>[crossly_catalog limit="24" q="denim"]</code></p>
        <p><code>[crossly_buy listing="LISTING_ID" label="Buy now"]</code></p>
        <p>
            <?php
            echo esc_html__(
                'Checkout happens on Crossly — card details are never entered on this site, so installing this plugin does not put your site in PCI scope.',
                'crossly'
            );
            ?>
        </p>
    </div>
    <?php
}
