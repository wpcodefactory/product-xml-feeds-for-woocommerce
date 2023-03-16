=== Product XML Feeds for WooCommerce ===
Contributors: omardabbas
Tags: woocommerce, product xml feeds, xml, export
Requires at least: 4.4
Tested up to: 5.3
Stable tag: 1.7.3
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Product XML feeds for WooCommerce.

== Description ==

**Product XML Feeds for WooCommerce** plugin lets you add WooCommerce product XML feeds. Feeds are automatically regenerated using crons.

You can set **custom XML templates** for the feed. Feeds are created using plugin's [shortcodes](https://wpfactory.com/item/product-xml-feeds-woocommerce/). Different templates can be set for:

* XML header.
* XML item.
* XML footer.

In addition you can set custom **XML file path and name** and set custom **products sorting** options.

Plugin is **WPML compatible**. I.e. you can create separate feeds for each language.

To **include only certain products** in feed, you can use these products filtering options:

* Products to include/exclude.
* Product categories to include/exclude.
* Product tags to include/exclude.
* Variable products: Variable products only; Variation products only; Both variable and variation products.
* Products scope: All products; Only products that are on sale; Only products that are not on sale; Only products that are featured; Only products that are not featured.
* Products offset and total number of products.

= Premium Version =

[Product XML Feeds for WooCommerce Pro](https://wpfactory.com/item/product-xml-feeds-woocommerce/) version allows you to create **unlimited number of feeds** with individual settings for each.

It also includes **additional products filtering options**:

* Stock status: In stock products; Out of stock products; On backorder products.
* Min and max product prices.
* Catalog visibility: Shop and search results; Shop only; Search results only; Hidden products.
* Custom taxonomy to include.
* Attribute to include.

You can also change the **update (i.e. regeneration) period** (weekly by default): Every minute; Hourly; Twice daily; Daily; Weekly.

= Feedback =

* We are open to your suggestions and feedback. Thank you for using or trying out one of our plugins!
* Please visit [Product XML Feeds for WooCommerce plugin page](https://wpfactory.com/item/product-xml-feeds-woocommerce/).

== Installation ==

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Start by visiting plugin settings at "WooCommerce > Settings > Product XML Feeds".

== Changelog ==

= 1.7.3 - 20/11/2019 =
* Plugin author changed.

= 1.7.2 - 17/11/2019 =
* Dev - General - Advanced Options - '"Raw" input' option added.
* Tested up to: 5.3.

= 1.7.1 - 10/11/2019 =
* Fix - "Total XML files (feeds)" option fixed.

= 1.7.0 - 10/11/2019 =
* Dev - Feeds - Products Filtering Options - "Offset products" and "Total products" options added.
* Dev - Feeds - General Options - "Sorting" options added.
* Dev - Feeds - Admin settings descriptions updated.
* Dev - Code refactoring.
* WC tested up to: 3.8.

= 1.6.0 - 01/11/2019 =
* Dev - Advanced Options - PHP time limit - Now applied to each block size in query loop. Default and min values are set to `-1` now.
* Dev - Code refactoring.
* WC tested up to: 3.7.

= 1.5.4 - 22/07/2019 =
* Dev - Shortcodes - `[alg_product_terms]` - `orderby` - `hierarchy` value added.
* Dev - Shortcodes - `[alg_product_terms]` - `orderby` - `term_id` value added.
* Dev - Shortcodes - `[alg_product_terms]` - Code refactoring.

= 1.5.3 - 20/07/2019 =
* Dev - Shortcodes - `[alg_product_terms]` - `orderby` attribute (and `parent_id` value) added.
* Dev - Feeds - Admin settings restyled ("Extra Products Filtering Options" section added).

= 1.5.2 - 19/07/2019 =
* Dev - Shortcodes - `strip_shortcodes` - `yes-force` - Pattern updated in `preg_replace()`.

= 1.5.1 - 18/07/2019 =
* Dev - Feeds - Template Options - Default values for "XML header" and "XML item" updated.
* Dev - Feeds - Products Filtering Options - "Attribute to include" options added.
* Dev - Shortcodes - `cdata` attribute added to all shortcodes (defaults to `no`).
* Dev - Shortcodes - `strip_shortcodes` attribute added to all shortcodes (defaults to `no`) (other possible values: `yes` and `yes-force`).
* Dev - Shortcodes - `[alg_product_variation_meta]` shortcode added.
* Dev - Shortcodes - `[alg_product_variation_data]` shortcode added.

= 1.5.0 - 17/06/2019 =
* Dev - Shortcodes - `on_empty_apply_shortcodes` attribute added to all shortcodes (defaults to `no`).
* Dev - Shortcodes - `variable_price_type` attribute (defaults to `range`) added to `alg_product_price`, `alg_product_price_excluding_tax` and `alg_product_price_including_tax` shortcodes.

= 1.4.9 - 07/06/2019 =
* Fix - Feeds - Products Filtering Options - Stock status - Fixed for variations which have "Manage stock?" option enabled.
* Dev - Shortcodes - `[alg_product_terms]` - Optional `parent` attribute added.
* Dev - Shortcodes - `[alg_product_terms]` - Code refactoring.

= 1.4.8 - 20/05/2019 =
* Fix - Shortcodes - `[alg_product_stock_quantity]` - Returning empty string instead of boolean `false` (fixes the issue with `on_empty` attribute).

= 1.4.7 - 15/05/2019 =
* Fix - Feeds - Template Options - Default templates are now correctly retrieved even if "Save changes" button was never clicked.
* Dev - Feeds - Template Options - XML item - Default template changed (`CDATA` added; less shortcodes included).
* Dev - Feeds - Advanced Options - "Query post type" option removed (now always set to "Products only"). This fixes the issue with "Products and variations" option and some "Products Filtering" section options.
* Dev - Shortcodes - `custom_function` global attribute added.
* Dev - Shortcodes - `strip_tags` global attribute added (defaults to `yes`).

= 1.4.6 - 09/05/2019 =
* Fix - Advanced Options - Set correct min values in settings.
* Dev - Feeds - Products Filtering Options - "Custom taxonomy to include" options added.
* Dev - Advanced Options - "PHP memory limit" option added.
* Dev - Tested up to: 5.2.

= 1.4.5 - 26/04/2019 =
* Dev - Shortcodes - General - `[alg_format_date]` shortcode added.
* Dev - Shortcodes - General - `[alg_format_number]` shortcode added.
* Dev - Shortcodes - General - `[alg_to_timestamp]` shortcode added.
* Dev - Settings - IDs added to product lists (in "Products Filtering Options > Products to include / exclude").
* Dev - "WC tested up to" updated.

= 1.4.4 - 05/04/2019 =
* Dev - Advanced - "Query post type" option added.

= 1.4.3 - 27/03/2019 =
* Dev - Shortcodes - `convert_currency_from`, `convert_currency_to` and `convert_currency_precision` global shortcode attributes added.
* Dev - Shortcodes - `find_replace_sep` global shortcode attribute added. Now `find` and `replace` global shortcode attributes can find and replace multiple values.
* Dev - Shortcodes - `[alg_product_available_variations]` - `sep`, `sep2` and `sep3` attributes added.
* Dev - Feeds - Products Filtering Options - "Catalog visibility" option added.
* Dev - Feeds - General Options - "WPML language" option added.

= 1.4.2 - 28/01/2019 =
* Dev - Variable products - "Variation products only" code rewritten (`get_the_ID()` added etc.).
* Dev - Feeds - Product "Min price" and "Max price" options added.
* Dev - Code refactoring.
* Dev - Admin settings restyled (subsections added).

= 1.4.1 - 24/01/2019 =
* Fix - Variable products - "Variation products only" option fixed.
* Dev - Shortcodes - `[alg_product_name]` shortcode added.

= 1.4.0 - 26/11/2018 =
* Dev - Shortcodes - `[alg_product_description]` shortcode added.
* Dev - Shortcodes - `[alg_product_gallery_image_url]` shortcode added.
* Dev - Shortcodes - `[alg_product_function]` shortcode added.
* Dev - Feeds - Product "Stock status" options added.
* Dev - Feeds - "Admin title" option added (to set custom feed title (instead of "XML Feed #X")).
* Dev - General - "PHP time limit" option added.
* Dev - General - Block size for products query default value changed to 512.
* Dev - Admin settings descriptions updated.
* Dev - Minor code refactoring.

= 1.3.1 - 18/10/2018 =
* Fix - Shortcodes - `[alg_product_time_since_last_sale]` - `days_to_cover` attribute fixed.
* Dev - Shortcodes - `[alg_product_time_since_last_sale]` - Code refactoring.

= 1.3.0 - 10/10/2018 =
* Fix - "Total XML files (feeds)" option fixed (new feeds settings are displayed at once now).
* Dev - `use_parent_id` attribute added to all "products" shortcodes.
* Dev - "Reset section settings" options added.
* Dev - Code refactoring.
* Dev - Admin settings restyled and descriptions updated.
* Dev - Plugin URI updated.

= 1.2.2 - 23/03/2018 =
* Dev - `[alg_product_terms]` shortcode added.
* Dev - General - Advanced - "Block Size for Products Query" option added.
* Dev - `includes/shortcodes` folder added.
* Dev - "WC tested up to" added to plugin header.

= 1.2.1 - 31/07/2017 =
* Dev - `on_empty` attribute added to all shortcodes.

= 1.2.0 - 27/07/2017 =
* Dev - WooCommerce v3 compatibility - Getting product ID and short description with functions (instead of accessing properties directly).
* Dev - WooCommerce v3 compatibility - `get_price_including_tax()` and `get_price_excluding_tax()` replaced with `wc_get_price_including_tax()` and `wc_get_price_excluding_tax()`.
* Dev - WooCommerce v3 compatibility - `get_tags()` and `get_categories()` replaced with `wc_get_product_tag_list()` and `wc_get_product_category_list()`.
* Dev - WooCommerce v3 compatibility - `list_attributes()` replaced with `wc_display_product_attributes()`.
* Dev - WooCommerce v3 compatibility - `$_product->get_dimensions( true )` replaced with `wc_format_dimensions( $_product->get_dimensions( false ) )`.
* Fix - `[alg_product_available_variations]` - "Glued" attributes bug fixed etc.
* Dev - `[alg_product_id]` shortcode added.
* Dev - `[alg_product_time_since_last_sale]` - Code refactoring.
* Dev - Link updated from http://coder.fm to https://wpcodefactory.com.
* Dev - Plugin header ("Text Domain" etc.) updated.
* Dev - Code cleanup and minor fixes.
* Dev - POT file added.

= 1.1.1 - 15/01/2017 =
* Fix - "If plugin is enabled" check fixed.

= 1.1.0 - 12/01/2017 =
* Fix - "Variable Products" option description and attributes fixed.
* Dev - Settings for each feed moved to separate section.
* Dev - `[alg_current_datetime]` shortcode added.
* Dev - Link to all available shortcodes added.

= 1.0.0 - 10/01/2017 =
* Initial Release.

== Upgrade Notice ==

= 1.0.0 =
This is the first release of the plugin.
