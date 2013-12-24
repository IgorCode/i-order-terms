=== I Order Terms ===
Contributors: x64igor
Tags: sort, order, terms, taxonomy
Requires at least: 3.5
Tested up to: 3.8
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Allows theme developers to add order/sort functionality for categories, tags and custom taxonomies.

== Description ==
Plugin can be used for reordering categories, tags and custom taxonomies. This plugin is primarily intended as an aid to theme developers.

Plugin supports multisite installation.

= Requirements =
Requires at least WordPress 3.5.

= Installation =
There are two options you can use to make taxonomy sortable:

1. You can enable taxonomy sorting when registering taxonomy:

	<pre>
	register_taxonomy( 'your-taxonomy-name', 'your-post-type', array(
		'label' => __('Category'),
		'i_order_terms' => true, // this parameter is used to enable sorting for taxonomy 'your-taxonomy-name'
	));
	</pre>

2. Other option is to pass array of taxonomies (or tags/categories) via filter "i_order_terms_taxonomies" in your functions file like this:

	<pre>
	function custom_i_order_terms_taxonomies() {
		return array('your-taxonomy-name', 'category');
	}
	add_filter( 'i_order_terms_taxonomies', 'custom_i_order_terms_taxonomies' );
	</pre>

	This will enable taxonomy sorting for 'your-taxonomy-name' and 'category' taxonomies.
	Naturally you will have to provide your taxonomy names.

= Permissions =
User needs to have "manage_categories" permission to be able to order terms.

= Example usage =
**Fetch sorted terms from custom taxonomy:**

`$terms = get_terms( 'your-taxonomy-name', '' );`

**Disable custom sorting:**

`$terms = get_terms( 'your-taxonomy-name', 'i_order_terms=0' );`

= Known Issues =
Sort (drag&drop) is not available right after you add new term, you need to refresh page to be able to drag newly created term.

= Warning =
Plugin ads new column to 'term_taxonomy' table, make sure to backup your database before installing. Column is removed when you delete plugin.


== Installation ==
1. You can download and install "I Order Terms" plugin by using the built in WordPress plugin installer. Or you can upload plugin folder "i-order-terms" manually to your "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. You will need to enable plugin for taxonomy that you wish to sort.

= Enabling plugin for taxonomy =
There are two options you can use to make taxonomy sortable:

1. You can enable taxonomy sorting when registering taxonomy:

	<pre>
	register_taxonomy( 'your-taxonomy-name', 'your-post-type', array(
		'label' => __('Category'),
		'i_order_terms' => true, // this parameter is used to enable sorting for taxonomy 'your-taxonomy-name'
	));
	</pre>

2. Other option is to pass array of taxonomies (or tags/categories) via filter "i_order_terms_taxonomies" in your functions file like this:

	<pre>
	function custom_i_order_terms_taxonomies() {
		return array('your-taxonomy-name', 'category');
	}
	add_filter( 'i_order_terms_taxonomies', 'custom_i_order_terms_taxonomies' );
	</pre>

	This will enable taxonomy sorting for 'your-taxonomy-name' and 'category' taxonomies.
	Naturally you will have to provide your taxonomy names.

== Frequently Asked Questions ==

= Will this work on WordPress multisite? =

Yes, it will work on multisite installation.

= Where can I report a bug? =

You can report bugs from contact form on my website at <a href="http://www.igorware.com/contact?referrer-ver=I-Order-Terms">http://www.igorware.com/contact</a>.
Please make sure to include plugin version when reporting bugs.

== Screenshots ==
1. The screenshot of Category section after drag and drop reorder operation.

== Changelog ==
= 1.0.0 =
* Initial release

== Upgrade Notice ==
= 1.0.0 =
* Initial release
