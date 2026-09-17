=== Crossly ===
Contributors: crossly
Tags: shop, ecommerce, store, catalog, reselling
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 0.1.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Show your Crossly listings on your WordPress site and let visitors buy them.

== Description ==

Crossly lists your inventory across marketplaces. This plugin puts that same
inventory on your own WordPress site — as a block, a shortcode, or both.

* **Crossly Catalog block** — add it in the editor, pick how many items.
* `[crossly_catalog limit="24" q="denim"]`
* `[crossly_buy listing="LISTING_ID"]`

Checkout happens on Crossly. Card details are never entered on your site, so
installing this plugin does **not** put your site in PCI scope.

= What the plugin sends =

One script, from crossly.net, on pages where you actually used the block or
shortcode — not site-wide. It loads your active listings using a *publishable*
key, which is designed to be public: it can read your own active listings and
start a checkout, and nothing else. It cannot read your orders, your buyers'
addresses, your costs or your margins.

The plugin sets no cookies of its own and does not track your visitors.

== Installation ==

1. Install and activate.
2. In Crossly, go to Settings → Embeds and mint a publishable key.
3. In WordPress, go to Settings → Crossly and paste it.
4. Add the Crossly Catalog block to any page.

== Frequently Asked Questions ==

= Is it safe to put the key in my page? =

Yes — that is what a publishable key is for. If you paste a Personal Access
Token instead, the plugin refuses to save it and tells you why: a PAT grants
full access to your account, and this plugin prints its key into public pages.

= Can I style it? =

Yes. The embed uses CSS custom properties and `::part`:

`crossly-catalog { --crossly-accent: #ff5a5f; --crossly-min-col: 260px; }`

= Can I show more than one shop? =

Yes, pass a key per shortcode: `[crossly_catalog key="crossly_pk_live_…"]`

== Changelog ==

= 0.1.0 =
* First release. Catalog block, catalog shortcode, buy-button shortcode.
