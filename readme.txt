=== I Order Terms ===
Contributors: x64igor
Tags: sort, order, terms, taxonomy
Requires at least: 3.5
Tested up to: 4.1
Stable tag: 1.3.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows theme developers to add order/sort functionality for categories, tags and custom taxonomies.

== Description ==
Plugin can be used for reordering categories, tags and custom taxonomies. This plugin is primarily intended as an aid to theme developers.

Plugin supports multisite installation.

= Requirements =
The minimum requirement is that you have at least WordPress 3.5 installed.

= Example usage =
**Fetching sorted terms from custom taxonomy will be enabled by default:**

`$terms = get_terms( 'your-taxonomy-name' );`

**If you wish to sort by name (disable plugin's custom sorting) you will have to set 'i_order_terms' to 'false':**

`$terms = get_terms( 'your-taxonomy-name', 'i_order_terms=0' );`

= Warning =
Plugin ads new column to 'term_taxonomy' table, make sure to backup your database before installing. Column is removed when you delete plugin.


== Installation ==
1. You can download and install "I Order Terms" plugin by using the built in WordPress plugin installer. Or you can upload plugin folder "i-order-terms" manually to your "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. You will need to enable plugin for taxonomy that you wish to sort.

= Enabling plugin for taxonomy =
You can use settings page or add code in your function file. There are two options you can use to make taxonomy sortable:

1) You can enable sorting when registering taxonomy:
`
register_taxonomy( 'your-taxonomy-name', 'your-post-type', array(
	'label' => __('Category'),

	// this parameter is used to enable
	// sorting for taxonomy 'your-taxonomy-name'
	'i_order_terms' => true,
));
`

2) Other option is to pass array of taxonomies (or tags/categories) via filter "i_order_terms_taxonomies" in your functions file like this:
`
function custom_i_order_terms_taxonomies($taxonomies) {
	$taxonomies = array_merge($taxonomies, array('taxonomy', 'category'));
	return $taxonomies;
}
add_filter('i_order_terms_taxonomies', 'custom_i_order_terms_taxonomies');
`

This will enable taxonomy sorting for 'taxonomy' and 'category' taxonomies.
Naturally you will have to provide your taxonomy names.

== Frequently Asked Questions ==

= Will this work on WordPress multisite? =
Yes, it will work on multisite installation.

= What permissions are required for users to reorder terms? =
User needs to have "manage_categories" permission to be able to order terms.

= Where can I report a bug? =
You can report bugs from contact form on my website at <a href="http://www.igorware.com/contact?referrer-ver=I-Order-Terms">http://www.igorware.com/contact</a>.
Please make sure to include plugin version when reporting bugs.

== Screenshots ==
1. The screenshot of Category section after drag and drop reorder operation.
2. Settings section where you can select which taxonomy should be sortable.

== Changelog ==
= 1.3.1 =
* Shows taxonomy name next to taxonomy label in plugin settings. This should avoid confusion when there are several taxonomies with same label
= 1.3.0 =
* Sort (drag&drop) is now available right after you add new term, no need to refresh page like before
* Improved security by preventing directory browsing
* Removed screenshots from plugin folder, this should save you some bandwith :)
= 1.2.0 =
* Added link to settings on plugins page
* Settings page completely rewritten to use WordPress Settings API
* Drag & drop can now be done in different levels i.e. you can now change parent of dragged item
= 1.1.0 =
* Added settings page for plugin
* Removed limitation of accepting only one taxonomy when using functions like get_categories and get_terms
= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.3.1 =
* Shows taxonomy name next to taxonomy label in plugin settings
= 1.3.0 =
* Sort (drag&drop) is now available right after you add new term, no need to refresh page like before
= 1.2.0 =
* Settings page rewritten and drag & drop now able to change parent of dragged item
= 1.1.0 =
* Added settings page for plugin
= 1.0.0 =
* Initial release
