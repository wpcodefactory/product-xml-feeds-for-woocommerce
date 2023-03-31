=== Product XML Feed Manager for WooCommerce - Google Shopping, Social Sites, Skroutz & More ===
Contributors: omardabbas
Tags: woocommerce, product xml feeds, xml, export
Requires at least: 4.4
Tested up to: 6.1
Stable tag: 2.7.4
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create your own XML files using tens of preconfigured shortcodes for you on your WooCommerce store

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

= Demo Store =

If you want to try the plugin features, play around with its settings before installing it on your live website, feel free to do so on this demo store:
URL: https://wpwhale.com/demo/wp-admin/
User: demo
Password: G6_32e!r@

= Feedback =

* We are open to your suggestions and feedback. Thank you for using or trying out one of our plugins!
* Please visit [Product XML Feeds for WooCommerce plugin page](https://wpfactory.com/item/product-xml-feeds-woocommerce/).

== Installation ==

1. Upload the entire plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Start by visiting plugin settings at "WooCommerce > Settings > Product XML Feeds".

== Changelog ==

= 2.7.4 - 31/03/2023 =
* Move to WPFactory.

= 2.7.3 - 23/03/2023 =
* Fixed a bug in variations handling
* Verified compatibility with WooCommerce 7.5

= 2.7.2 - 04/03/2023 =
* Added external & bundle products to "Filter by product type" section
* Added a shortcode to include category description
* Verified compatibility with WooCommerce 7.4

= 2.7.1 - 06/02/2023 =
* Fixed a bug in filtering type affecting variations
* Verified compatibility with WooCommerce 7.3

= 2.7 - 22/12/2022 =
* Added custom meta query without value parameter (_thumbnail_id, EXISTS)
* Allowed entering products in Include/Exclude by SKU number
* Verified compatibility with WooCommerce 7.2

= 2.6.4 - 29/11/2022 =
* Fixed issues in filtering variations & variable products
* New attributes added

= 2.6.3 - 13/11/2022 =
* New option to filter products by type (simple, variable, grouped)
* Verified compatibility with WooCommerce 7.1

= 2.6.2 - 04/11/2022 =
* Added new section for Custom meta filters
* New shortcode added alg_product_available_variations
* Verified compatibility with WordPress 6.1

= 2.6.1 - 15/10/2022 =
* Fixed Fatal Error messages appearing on multi-language feeds
* Verified compatibility with WooCommerce 7.0

= 2.6 - 20/09/2022 =
* Included new options to exclude categories by ID in Taxonomy shortcode
* Added a new option in interface to exclude tags if empty
* A new option to specify requirements for variations (Skroutz compatible)
* Compatibility with WooComemrce 6.9

= 2.5.4 - 06/09/2022 =
* Included new attributes (is_child, is_parent) for categories taxonomy to show each in separate row
* Included a new attribute (pick_order=N) for tag taxonomy to show multiple tags

= 2.5.3 - 10/08/2022 =
* Fixed a bug related to product tags
* Enhanced description shortcode to handle new lines properly
* Compatibility with WooComemrce 6.8

= 2.5.2 - 28/06/2022 =
* Added shortcode to include category IDs
* Added a shortcode to list attributes hierarchy
* Added a shortcode to list tags separately
* Verified compatibility with WooComemrce 6.6

= 2.5.1 - 12/06/2022 =
* Verified compatibily with  WooCommerce 6.5 & WordPress 6.0

= 2.5 - 19/03/2022 =
* Verified compatibily with  WooCommerce 6.3
* Added new attribute select="min_price" to select the lowest price from variations

= 2.4.1 - 18/02/2022 =
* Added an attribute on_zero for shortcode [alg_product_sale_price] to handle sale price as fallback if price is zero

= 2.4 - 12/02/2022 =
* Fixed a bug that showed draft products in feed in particular cases
* Enhanced how feed should show products without stock
* Verified compatibily with  WooCommerce 6.2

= 2.3 - 28/01/2022 =
* Verified compatibily with WordPress 5.9 & WooCommerce 6.1

= 2.2.6 - 11/10/2021 =
* Fixed a bug related to duplicating variations with pre-set filters
* Verified compatibility with WooCommerce 5.7

= 2.2.5 - 20/09/2021 =
* More WPML compatibilty with attribute exchange_rate for manual currency conversion when needed
* Added 2 attributes to [alg_product_stock_availability] to show specific values (Y,N) based on stock status

= 2.2.4 - 14/09/2021 =
* Verified compatibility with WooCommerce 5.6
* Added parameters to work with Advanced Custom Fields plugin
* Fixed a notice message (Undefined index: sum_with)

= 2.2.3 - 16/08/2021 =
* Added parameter "strip tags" to [alg_product_description] shortcode
* Added a new shortcode [alg_product_publish_date] to include product publish date (date & time follow PHP date standards)

= 2.2.2 - 06/08/2021 =
* Allowed turning off schedule (useful when using cron jobs handling introduced in 2.2.1)
* Minor typo fix on showing stock

= 2.2.1 - 24/07/2021 =
* Added an option for manual cron job handling
* Fixed a bug that was duplicating products in feed
* Verified compatibility with WooCommerce 5.5 & WP 5.8

= 2.2 - 09/07/2021 =
* Added new shortcode to list variations based on stock status
* Added an attribute to show stock status without quantity
* Verified compatibility with WooCommerce 5.4

= 2.1.3 - 20/04/2021 =
* Tested compatibilty with WC 5.2 & WP 5.7

= 2.1.2 - 28/02/2021 =
* Tested compatibilty with WC 5.0

= 2.1.1 - 07/02/2021 =
* Added options to multiply/add flat prices to regular price shortcode

= 2.1 - 27/01/2021 =
* Added shortcodes for compatibiltity with multi language stores
* Enhanced on shortcodes for proper results
* Tested compatibility with WC 4.9

= 2.0 - 11/12/2020 =
* Fixed a warning message for query_post_type
* Tested compatibility with WC 4.8 & WP 5.6

= 1.9.6 - 15/11/2020 =
* Bug fix for fatal error that appears in some stores
* Tested compatibility with WC 4.7

= 1.9.5 - 22/09/2020 =
* Created a new option to export a .txt version for the output file
* Variations product will now read the parent long and short description by default

= 1.9.4 - 18/09/2020 =
* Added an option to remove plugin branding (XML footer) in Pro version

= 1.9.3 - 13/09/2020 =
* Added optional attributes to read from arrays
* [alg_product_sale_price] & [alg_product_regular_price] now show price ranges for variations of variable product
* Added a link to plugin homepage in XML footer
* Tested compatibility with WC 4.5

= 1.9.2 - 26/08/2020 =
* Bug fix: Plugin was showing <Attributes/> instead of </Attributes> in shortcodes.
* Verified compatibility with WC 4.4 & WP 5.5

= 1.9.1 - 28/04/2020 =
* Fixed a bug that showed all attributes slugs in cases where it should show empty (no attributes value)

= 1.9 - 24/04/2020 =
* Feature: new shortcode added: [alg_product_categories_ids] to retrieve product category ids

= 1.8.2 - 15/04/2020 =
* Feature: Two new shortcodes added to show attribute slug & att. values slugs
* Tested compatibility with WC 4 & WP 5.4

= 1.8.1 - 01/03/2020 =
* Dev: Fixed a bug that showed variations twice in specific scenarios

= 1.8 - 08/02/2020 =
* Dev: Fixed filtering options bug for variation & variable products
* Overall code enhancement

= 1.7.6 - 29/01/2020 =
* Dev: Changed site_url to home_url in XML file path

= 1.7.4 - 02/01/2020 =
* Text updates over the plugin pages.
* Copyrights Update
* Added a section to review the plugin

= 1.7.3 - 20/11/2019 =
* Plugin author changed.

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
