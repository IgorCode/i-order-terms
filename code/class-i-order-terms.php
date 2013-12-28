<?php
/**
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hello! I freelance as a plugin, you can\'t call me directly. :/';
	exit;
}


if ( !class_exists( 'I_Order_Terms' ) ) {
/**
 * I Order Terms plugin main class.
 *
 * @since 1.0.0
 */
class I_Order_Terms
{
	const PLUGIN_NAME = 'I Order Terms';
	const PLUGIN_VERSION = '1.1.0';
	const WP_MIN_VERSION = '3.5';
	const LANG_DOMAIN = 'iorderterms';

	private $plugin_path;
	private $plugin_url;

	private $notices = array();
	private $taxonomies = array();
	private $taxonomies_registered = array();


	/**
	 * Plugin initialization.
	 *
	 * @param  string $plugin_path Path to plugin folder.
	 * @param  string $plugin_url  URL to plugin folder.
	 * @return void
	 */
	public function __construct( $plugin_path, $plugin_url )
	{
		$this->plugin_path = $plugin_path;
		$this->plugin_url = $plugin_url;


		add_action( 'after_setup_theme', array($this, 'after_setup_theme') );

		add_filter( 'terms_clauses', array($this, 'terms_clauses'), 10, 3 );

		add_action( 'registered_taxonomy', array($this, 'registered_taxonomy'), 10, 3 );

		add_action( 'wpmu_new_blog', array($this, 'wpmu_new_blog'), 10, 6 );


		if ( is_admin() ) {
			// admin
			if ( defined( 'DOING_AJAX' ) ) {
				// ajax

				add_action( 'wp_ajax_i-order-terms', array($this, 'ajax_order_terms') );
			} else {
				// no ajax

				add_action( 'admin_init', array($this, 'admin_init') );

				add_action( 'admin_notices', array($this, 'admin_notices') );

				add_action( 'admin_menu', array($this, 'admin_menu') );

				add_action( 'admin_enqueue_scripts', array($this, 'admin_scripts') );
			}
		}
	} // end __construct

	/**
	 * Adds database column and index on plugin installation.
	 *
	 * @param  bool $networkwide
	 * @return void
	 */
	public static function activate( $networkwide )
	{
		// single site installation
		I_Order_Terms::activate_partial();

		// multisite network wide installation
		if ( function_exists( 'is_multisite' ) && is_multisite() && $networkwide ) {
			// check if it is a network activation - if so, run the activation function for each blog id
			global $wpdb;

			// remember current blog ID
			$current_blog = $wpdb->blogid;

			// get all blog ID's
			$sql_blogs = "SELECT blog_id FROM {$wpdb->blogs};";
			$blog_ids = $wpdb->get_col( $sql_blogs );
			foreach ( $blog_ids as $blog_id ) {
				if ( $blog_id != $current_blog ) {
					switch_to_blog( $blog_id );
					I_Order_Terms::activate_partial();
				}
			}

			switch_to_blog( $current_blog );
	    }
	} // end activate

	/**
	 * Installs plugin for new blog in multisite environment.
	 *
	 * @param int    $blog_id Blog ID.
	 * @param int    $user_id User ID.
	 * @param string $domain  Site domain.
	 * @param string $path    Site path.
	 * @param int    $site_id Site ID. Only relevant on multi-network installs.
	 * @param array  $meta    Meta data. Used to set initial site options.
	 * @return void
	 */
	public function wpmu_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;

		if ( is_plugin_active_for_network( 'i-order-terms/i-order-terms.php' ) ) {
			$current_blog = $wpdb->blogid;

			// activate plugin on new blog
			switch_to_blog($blog_id);
			I_Order_Terms::activate_partial();

			switch_to_blog($current_blog);
		}
	} // end wpmu_new_blog

	/**
	 * Adds database column and index on plugin installation.
	 *
	 * @return void
	 */
	public static function activate_partial()
	{
		// NOTE: trigger_error must be used during plugin activation
		global $wpdb;

		// check if column already exists
		$sql_check = "SHOW COLUMNS FROM `{$wpdb->term_taxonomy}` LIKE 'custom_order';";
		if ( !$wpdb->get_row( $sql_check ) ) {
			// column doesn't exist

			// add column
			$sql_column = "ALTER TABLE `{$wpdb->term_taxonomy}` ADD `custom_order` INT (11) NOT NULL DEFAULT 9999;";
			if ( $wpdb->query( $sql_column ) === false ) {
				trigger_error( sprintf( __( '%s error: Unable to add column, a required database change.', I_Order_Terms::LANG_DOMAIN ), I_Order_Terms::PLUGIN_NAME ), E_USER_ERROR );
				return;
			}

			// add index (no error check because not a big deal if index creation fails)
			//$sql_index = "ALTER TABLE `{$wpdb->term_taxonomy}` ADD INDEX `custom_order_index` (`taxonomy`, `custom_order`);";
			$sql_index = "ALTER TABLE `{$wpdb->term_taxonomy}` ADD INDEX `custom_order_index` (`custom_order`);";
			$wpdb->query( $sql_index );
		}
	} // end activate_partial

	/**
	 * Fetch taxonomies from options and register user defined taxonomies via filter.
	 *
	 * @return void
	 */
	public function after_setup_theme()
	{
		// fetch options from DB
		$options = get_option( 'iorderterms.general' );
		if ( is_array( $options ) && is_array( $options['taxonomies-sort'] ) ) {
			$this->taxonomies = array_merge( $this->taxonomies, $options['taxonomies-sort'] );
		}

		// register taxonomies via filter
		$taxonomies = apply_filters( 'i_order_terms_taxonomies', $this->taxonomies );
		if ( is_array( $taxonomies ) ) {
			$this->taxonomies = $taxonomies;
		}

		// remove dups
		$this->taxonomies = array_unique( $this->taxonomies );
	} // end after_setup_theme

	/**
	 * Used for sorting taxonomies.
	 *
	 * @param array  $clauses    SQL clauses.
	 * @param mixed  $taxonomies Taxonomy name.
	 * @param array  $args       Query arguments.
	 * @return array Return SQL clauses.
	 */
	public function terms_clauses( $clauses, $taxonomies, $args )
	{
		// user disabled custom sort
		if ( isset( $args['i_order_terms'] ) && $args['i_order_terms'] == false ) return $clauses;

		// default sorting is to use custom order
		if ( isset( $args['orderby'] ) && $args['orderby'] !== 'name' ) return $clauses;

		// accept only single taxonomy queries & only if taxonomy is registered for custom sorting
		if ( /* count( $taxonomies ) !== 1 || */ !in_array( $taxonomies[0], $this->taxonomies ) ) return $clauses;

		// user sorting by a column
		if ( is_admin() && !empty( $_GET['orderby'] ) ) return $clauses;


		// order
		$order = strtoupper( $args['order'] );
		if ( !in_array( $order, array('ASC', 'DESC') ) ) {
			$order = 'ASC';
		}
		$orderby = "ORDER BY custom_order {$order}";


		if ( !empty( $clauses['orderby'] ) ) {
			// insert custom column in front of current column
			$clauses['orderby'] = str_replace( 'ORDER BY', "{$orderby},", $clauses['orderby'] );
		} else {
			// sort by custom sort column and name
			$clauses['orderby'] = "{$orderby}, name";
		}

		return $clauses;
	} // end terms_clauses

	/**
	 * Register taxonomies that require custom sorting.
	 *
	 * @param  string $taxonomy    Taxonomy name.
	 * @param  string $object_type Custom post type name.
	 * @param  array  $args        Arguments that developer used when registering taxonomy.
	 * @return void
	 */
	public function registered_taxonomy( $taxonomy, $object_type, $args )
	{
		if ( isset( $args['i_order_terms'] ) && $args['i_order_terms'] != false ) {
			$this->taxonomies[] = $taxonomy;
			$this->taxonomies_registered[] = $taxonomy;
		}
	} // end registered_taxonomy

	/**
	 * Fetch taxonomies that were registered via registered_taxonomy function.
	 *
	 * @return array
	 */
	public function get_taxonomies_registered()
	{
		return $this->taxonomies_registered;
	} // end get_taxonomies_registered

	/**
	 * Admin initialization - checks WP version.
	 *
	 * @return void
	 */
	public function admin_init()
	{
		// check version
		global $wp_version;

		// restrict plugin usage based on WordPress version
		if ( !function_exists( 'is_multisite' ) || version_compare( $wp_version, self::WP_MIN_VERSION, '<' ) ) {
			$this->notices[] = '<div id="i-order-terms-warning" class="updated"><p>' .sprintf( __( '%s plugin requires WordPress %s or higher. Please <a href="http://codex.wordpress.org/Upgrading_WordPress" target="_blank">upgrade WordPress</a> to a current version.', self::LANG_DOMAIN ), self::PLUGIN_NAME, self::WP_MIN_VERSION ). '</p></div>';
		}
	} // end admin_init

	/**
	 * Show messages to administrators.
	 *
	 * @return void
	 */
	public function admin_notices()
	{
		foreach ($this->notices as $notice) {
			echo $notice;
		}
	} // end admin_notices

	/**
	 * Render options page.
	 *
	 * @return void
	 */
	public function include_options()
	{
		include( $this->plugin_path . '/code/options.php' );
	} // end include_options

	/**
	 * Add plugin to admin menu.
	 *
	 * @return void
	 */
	public function admin_menu()
	{
		add_options_page( sprintf( __( 'Settings &lsaquo; %s' ), self::PLUGIN_NAME ), self::PLUGIN_NAME, 'manage_options', 'i-order-terms-options', array($this, 'include_options') );
	} // end admin_menu

	/**
	 * Loads scripts in admin panel.
	 *
	 * @return void
	 */
	public function admin_scripts()
	{
		// check permissions
		if ( !current_user_can( 'manage_categories' ) ) return;

		// fetch taxonomy name
		$taxonomy = filter_input( INPUT_GET, 'taxonomy', FILTER_SANITIZE_STRING );

		// load script only on taxonomy screen and when orderby is not selected
		if ( empty( $_GET['orderby'] ) && !empty( $taxonomy ) && in_array( $taxonomy, $this->taxonomies ) ) {
			// WP scripts
			wp_enqueue_script( 'jquery-ui-sortable' );

			// custom scripts
			wp_register_script( 'iorderterms_custom_order', $this->plugin_url . '/js/admin-i-order-terms.js', array('jquery-ui-sortable'), self::PLUGIN_VERSION );
			wp_enqueue_script( 'iorderterms_custom_order' );
		}
	} // end admin_scripts


	/**
	 * Save new term order in database.
	 *
	 * @access private
	 * @param  string $taxonomy     Taxonomy name.
	 * @param  object $term         Term object.
	 * @param  int    $custom_order Taxonomy name.
	 * @return int|bool
	 */
	private function reorder_term( $taxonomy, $term, $custom_order )
	{
		global $wpdb;

		$ret = $wpdb->update( $wpdb->term_taxonomy, array('custom_order' => $custom_order), array('term_taxonomy_id' => $term->term_taxonomy_id) );
		clean_term_cache( $term->term_id, $taxonomy );

		return $ret;
	} // end reorder_term

	/**
	 * Encode JSON response.
	 *
	 * @access private
	 * @param  string $status       Response status.
	 * @param  string $message      Textual message for user.
	 * @param  bool   $force_reload Should we force terms (page) reload.
	 * @return object
	 */
	private function ajax_response( $status, $message, $force_reload = false )
	{
		if ( $status === 'error' ) {
			$message = self::PLUGIN_NAME . ': ' . $message;
		}

		$data = array(
			'status' => $status,
			'message' => $message,
			'force_reload' => $force_reload,
		);

		return json_encode( $data );
	} // end ajax_response

	/**
	 * Ajax handler for ordering terms.
	 *
	 * @return void
	 */
	public function ajax_order_terms()
	{
		if ( !current_user_can( 'manage_categories' ) ) {
			die( json_encode( array('status' => 'error', 'error' => __( 'User does not have permission to perform this action.', I_Order_Terms::LANG_DOMAIN ) ) ) );
		}


		$taxonomy = filter_input( INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING );
		$term_id = filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT );
		$term_prev_id = filter_input( INPUT_POST, 'term_prev_id', FILTER_SANITIZE_NUMBER_INT );
		$term_next_id = filter_input( INPUT_POST, 'term_next_id', FILTER_SANITIZE_NUMBER_INT );
		$force_reload = filter_input( INPUT_POST, 'force_reload', FILTER_VALIDATE_BOOLEAN );

		// NOTE: term_prev_id/term_next_id can be null when moving to first/last position (not both at once)
		if ( !$term_id || !$taxonomy || !( $term_prev_id || $term_next_id ) ) {
			die( $this->ajax_response( 'error', __( 'Input data fail!', I_Order_Terms::LANG_DOMAIN ) ) );
		}


		// fetch parent from moved term
		$moved_term = get_term_by('id', $term_id, $taxonomy);
		if ( empty( $moved_term ) ) {
			die( $this->ajax_response( 'error', __( 'Input data fail, no term found!', I_Order_Terms::LANG_DOMAIN ) ) );
		}
		$term_parent_id = (int)$moved_term->parent;


		// sort
		$terms = get_terms( $taxonomy, "parent={$term_parent_id}&hide_empty=0&i_order_terms=1&orderby=name&order=ASC" );
		if ( !empty( $terms ) ) {

			$index = 1;
			foreach ($terms as $term) {
				if ( $term_next_id && $term->term_id == $term_next_id ) {
					// find term insert position

					// set custom order in database - for moved item
					if ( $this->reorder_term( $taxonomy, $moved_term, $index ) === false ) {
						die( $this->ajax_response( 'error', __( 'Unable to save new term order for current item!', I_Order_Terms::LANG_DOMAIN ) ) );
					}

					// new index for next item
					$index++;
				}

				if ( $term->term_id != $term_id ) {
					// for all but moved item

					if ( $term->custom_order != $index ) {
						// set in DB if custom_order changed

						// set new custom order
						if ( $this->reorder_term( $taxonomy, $term, $index ) === false ) {
							die( $this->ajax_response( 'error', __( 'Unable to save new term order!', I_Order_Terms::LANG_DOMAIN ) ) );
						}
					}
				}

				if ( !$term_next_id && $term->term_id == $term_prev_id ) {
					// find term insert position

					// new index for current item
					$index++;

					// set custom order in database - for moved item
					if ( $this->reorder_term( $taxonomy, $moved_term, $index ) === false ) {
						die( $this->ajax_response( 'error', __( 'Unable to save new term order for current item!', I_Order_Terms::LANG_DOMAIN ) ) );
					}
				}

				$index++;
			}
		}


		// force reload if moved term has children
		if ( !$force_reload ) {
			$moved_term_children = get_terms( $taxonomy, "child_of={$term_id}&hide_empty=0&fields=ids&number=1" );
			$force_reload = !empty( $moved_term_children );
		}


		// success
		die( $this->ajax_response( 'ok', '', $force_reload ) );
	} // end ajax_order_terms

} // I_Order_Terms
}
