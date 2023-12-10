<?php
/**
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// Don't allow direct access
if ( !defined( 'ABSPATH' ) ) {
	die( "Hello! I freelance as a plugin, you can't call me directly. :/" );
}


if ( !class_exists( 'I_Order_Terms' ) ) {
/**
 * I Order Terms plugin main class.
 */
class I_Order_Terms
{
	const PLUGIN_NAME = 'I Order Terms';
	const PLUGIN_VERSION = '1.5.0';
	const WP_MIN_VERSION = '3.5';
	const PLUGIN_BASENAME = 'i-order-terms/i-order-terms.php';
	const PLUGIN_OPTIONS_PAGE = 'iorderterms_general';

	/** @var string Plugin path */
	private $plugin_path;
	/** @var string Plugin URL */
	private $plugin_url;

	/** @var array List of notices for admin users */
	private $notices = array();
	/** @var array Taxonomies list */
	private $taxonomies = array();
	/** @var array Taxonomies that require custom sorting */
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


		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );

		add_filter( 'terms_clauses', array( $this, 'terms_clauses' ), 10, 3 );

		add_action( 'registered_taxonomy', array( $this, 'registered_taxonomy' ), 10, 3 );

		add_action( 'wpmu_new_blog', array( $this, 'wpmu_new_blog' ), 10, 6 );


		if ( is_admin() ) {
			// admin
			if ( defined( 'DOING_AJAX' ) ) {
				// ajax

				add_action( 'wp_ajax_i-order-terms', array( $this, 'ajax_order_terms' ) );
			} else {
				// no ajax

				add_action( 'admin_init', array( $this, 'admin_init' ) );

				add_action( 'admin_notices', array( $this, 'admin_notices' ) );

				add_action( 'admin_menu', array( $this, 'admin_menu' ) );

				add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );

				add_filter( 'plugin_action_links_' . self::PLUGIN_BASENAME, array( $this, 'action_links' ) );


				// update option name because of WP sanitation bug
				$option = get_option( 'iorderterms.general', null );
				if ( isset( $option ) ) {
					add_option( 'iorderterms_general', $option, '', 'yes' );
					delete_option( 'iorderterms.general' );
				}
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
	 * @param  int    $blog_id Blog ID.
	 * @param  int    $user_id User ID.
	 * @param  string $domain  Site domain.
	 * @param  string $path    Site path.
	 * @param  int    $site_id Site ID. Only relevant on multi-network installs.
	 * @param  array  $meta    Meta data. Used to set initial site options.
	 * @return void
	 */
	public function wpmu_new_blog( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
		global $wpdb;

		if ( is_plugin_active_for_network( self::PLUGIN_BASENAME ) ) {
			$current_blog = $wpdb->blogid;

			// activate plugin on new blog
			switch_to_blog( $blog_id );
			self::activate_partial();

			switch_to_blog( $current_blog );
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
				trigger_error( sprintf( __( '%s error: Unable to add column, a required database change.', 'i-order-terms' ), I_Order_Terms::PLUGIN_NAME ), E_USER_ERROR );
				return;
			}

			// add index (no error check because not a big deal if index creation fails)
			//$sql_index = "ALTER TABLE `{$wpdb->term_taxonomy}` ADD INDEX `custom_order_index` (`taxonomy`, `custom_order`);";
			$sql_index = "ALTER TABLE `{$wpdb->term_taxonomy}` ADD INDEX `custom_order_index` (`custom_order`);";
			$wpdb->query( $sql_index );
		}
	} // end activate_partial

	/**
	 * Adds plugin action links.
	 *
	 * @param  array $links List of plugin links.
	 * @return array
	 */
	public function action_links( $links )
	{
		// add settings link
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=i-order-terms-options' ) ) . '">' . __( 'Settings', 'i-order-terms' ) . '</a>' );

		return $links;
	} // end action_links

	/**
	 * Fetch taxonomies from options and register user defined taxonomies via filter.
	 *
	 * @return void
	 */
	public function after_setup_theme()
	{
		load_plugin_textdomain( 'i-order-terms', false, $this->plugin_path . '/languages/' );


		// fetch options from DB
		$options = get_option( 'iorderterms_general' );
		if ( is_array( $options ) && isset( $options['taxonomies-sort'] ) && is_array( $options['taxonomies-sort'] ) ) {
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
	 * @param  array  $clauses    SQL clauses.
	 * @param  mixed  $taxonomies Taxonomy name.
	 * @param  array  $args       Query arguments.
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
		if ( !in_array( $order, array( 'ASC', 'DESC' ) ) ) {
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
		// filter taxonomies
		$taxonomies = apply_filters( 'i_order_terms_taxonomies', $this->taxonomies_registered );
		if ( is_array( $taxonomies ) ) {
			$this->taxonomies_registered = $taxonomies;
		}

		return $this->taxonomies_registered;
	} // end get_taxonomies_registered

	/**
	 * Admin initialization.
	 *
	 * @return void
	 */
	public function admin_init()
	{
		// check version
		global $wp_version;

		// restrict plugin usage based on WordPress version
		if ( !function_exists( 'is_multisite' ) || version_compare( $wp_version, self::WP_MIN_VERSION, '<' ) ) {
			$this->notices[] = '<div id="i-order-terms-warning" class="updated"><p>' .sprintf( __( '%s plugin requires WordPress %s or higher. Please <a href="https://codex.wordpress.org/Updating_WordPress" target="_blank">upgrade WordPress</a> to a current version.', 'i-order-terms' ), self::PLUGIN_NAME, self::WP_MIN_VERSION ). '</p></div>';
		}

		// register settings
		register_setting( self::PLUGIN_OPTIONS_PAGE, 'iorderterms_general', array($this, 'settings_general_sanitize') );
		add_settings_section( 'general', '', array($this, 'settings_general_intro'), self::PLUGIN_OPTIONS_PAGE );
		add_settings_field( 'general_enable_sorting', __( 'Enable sorting', 'i-order-terms' ), array($this, 'settings_general_enable_sorting'), self::PLUGIN_OPTIONS_PAGE, 'general' );
	} // end admin_init

	/**
	 * Show messages to administrators.
	 *
	 * @return void
	 */
	public function admin_notices()
	{
		foreach ( $this->notices as $notice ) {
			echo $notice;
		}
	} // end admin_notices

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function settings_general_print()
	{
		?>

		<div class="wrap">
			<h2><?php echo esc_html( sprintf( __( '%s : Settings', 'i-order-terms' ), self::PLUGIN_NAME ) ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( self::PLUGIN_OPTIONS_PAGE ); ?>

				<?php do_settings_sections( 'iorderterms_general' ); ?>

				<?php submit_button(); ?>
			</form>
		</div>

		<?php

	} // end settings_general_print

	/**
	 * Sanitizes general settings input data.
	 *
	 * @param  array $options Input data.
	 * @return array
	 */
	public function settings_general_sanitize( $options )
	{
		if ( !isset( $options ) || !is_array( $options ) ) {
			$options = array();
		}

		if ( !isset( $options['taxonomies-sort'] ) || !is_array( $options['taxonomies-sort'] ) ) {
			$options['taxonomies-sort'] = array();
		}

		// check if taxonomy exists
		foreach ( $options['taxonomies-sort'] as $key => $taxonomy ) {
			if ( !taxonomy_exists( $taxonomy ) ) {
				unset( $options['taxonomies-sort'][$key] );
			}
		}

		return $options;
	} // end settings_general_sanitize

	/**
	 * Print the general settings intro text.
	 *
	 * @return void
	 */
	public function settings_general_intro()
	{
		// nothing to say at this time
	} // end settings_general_intro

	/**
	 * Render sorting option.
	 *
	 * @return void
	 */
	public function settings_general_enable_sorting()
	{
		// fetch options
		$options = get_option( 'iorderterms_general' );
		if ( !is_array( $options ) ) {
			$options = array();
		}

		?>

		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Enable sorting for taxonomies', 'i-order-terms' ); ?></span></legend>

			<?php
			if ( !isset( $options['taxonomies-sort'] ) || !is_array( $options['taxonomies-sort'] ) ) {
				$options['taxonomies-sort'] = array();
			}

			// fetch registered taxonomies (registered via register_taxonomy function)
			$taxonomies_registered = $this->get_taxonomies_registered();

			// fetch all taxonomies with standard WordPress UI that plugin supports
			$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );


			// Show check/uncheck all
			if ( count( $taxonomies ) > 1 ) {
				?>

				<label for="iorderterms-ctrl-all">
					<input id="iorderterms-ctrl-all" type="checkbox" onclick="iOrderTermsToggle(this)" />
					<span><?php echo esc_html_e( 'Check/uncheck all', 'i-order-terms' ); ?></span>
					<hr />
				</label>
				<br />
				<script>
				function iOrderTermsToggle(source) {
					var checkboxes = document.getElementsByName('iorderterms_general[taxonomies-sort][]');
					for ( var i = 0; i < checkboxes.length; i++) {
						checkboxes[i].checked = source.checked;
					}
				}
				</script>

				<?php
			}

			// List taxonomies
			foreach ( $taxonomies as $taxonomy ) {
				if ( $taxonomy->_builtin && in_array( $taxonomy->name, array( 'nav_menu' ) ) ) {
					continue;
				}

				$taxonomy_name_attr = esc_attr( $taxonomy->name );

				$is_registered = in_array( $taxonomy->name, $taxonomies_registered );
				$is_checked = $is_registered || in_array( $taxonomy->name, $options['taxonomies-sort'] );

				?>

				<label for="<?php echo "taxonomy-{$taxonomy_name_attr}"; ?>" title="<?php echo esc_attr( $taxonomy->description ); ?>">
					<input id="<?php echo "taxonomy-{$taxonomy_name_attr}"; ?>" name="iorderterms_general[taxonomies-sort][]" type="checkbox" value="<?php echo $taxonomy_name_attr; ?>"
						<?php checked( '1', $is_checked, true ); ?>
						<?php disabled( '1', $is_registered, true ); ?> />
					<span><?php echo esc_html( $taxonomy->label ); ?></span>
					<code><?php echo esc_html( $taxonomy->name ); ?></code>
				</label>
				<br />

				<?php
			}
			?>

			<p class="description"><?php esc_html_e( "(Taxonomies set as sortable via 'i_order_terms_taxonomies' filter or 'register_taxonomy' function can't be unchecked from options.)", 'i-order-terms' ); ?></p>
		 </fieldset>

		<?php
	}

	/**
	 * Add plugin to admin menu.
	 *
	 * @return void
	 */
	public function admin_menu()
	{
		add_options_page( sprintf( __( 'Settings &lsaquo; %s', 'i-order-terms' ), self::PLUGIN_NAME ), self::PLUGIN_NAME, 'manage_options', 'i-order-terms-options', array( $this, 'settings_general_print' ) );
	} // end admin_menu

	/**
	 * Loads assets in admin panel.
	 *
	 * @return void
	 */
	public function admin_assets()
	{
		// Check permissions
		if ( !current_user_can( 'manage_categories' ) ) return;

		// Fetch taxonomy name
		$taxonomy = filter_input( INPUT_GET, 'taxonomy', FILTER_SANITIZE_STRING );

		// Load assets only on taxonomy screen and when orderby is not selected
		if ( empty( $_GET['orderby'] ) && !empty( $taxonomy ) && in_array( $taxonomy, $this->taxonomies ) ) {

			// Include minified scripts and styles when script debug mode is off
			$min_sufix = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

			// Custom styles
			wp_register_style( 'iorderterms_custom_order', $this->plugin_url . '/css/admin-i-order-terms' . $min_sufix . '.css', false, self::PLUGIN_VERSION );
			wp_enqueue_style( 'iorderterms_custom_order' );

			// WP scripts
			wp_enqueue_script( 'jquery-ui-sortable' );

			// Custom scripts
			wp_register_script( 'iorderterms_custom_order', $this->plugin_url . '/js/admin-i-order-terms' . $min_sufix . '.js', array( 'jquery-ui-sortable' ), self::PLUGIN_VERSION );
			wp_enqueue_script( 'iorderterms_custom_order' );
		}
	} // end admin_assets


	/**
	 * Save new term order in database.
	 *
	 * @param  string   $taxonomy      Taxonomy name.
	 * @param  object   $term          Term object.
	 * @param  int      $custom_order  Taxonomy name.
	 * @param  int|bool $new_parent_id ID of new parent element.
	 * @return int|bool
	 */
	private function reorder_term( $taxonomy, $term, $custom_order, $new_parent_id = false )
	{
		global $wpdb;

		// new data
		$data = array(
			'custom_order' => $custom_order,
		);

		// update parent ID
		if ( $new_parent_id !== false && $term->parent != $new_parent_id ) {
			$data['parent'] = $new_parent_id;
		}

		$ret = $wpdb->update( $wpdb->term_taxonomy, $data, array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );
		clean_term_cache( $term->term_id, $taxonomy );

		return $ret;
	} // end reorder_term

	/**
	 * Encode JSON response.
	 *
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
			exit( $this->ajax_response( 'error', __( 'User does not have permission to perform this action.', 'i-order-terms' ) ) );
		}


		$taxonomy = filter_input( INPUT_POST, 'taxonomy', FILTER_SANITIZE_STRING );
		$term_id = filter_input( INPUT_POST, 'term_id', FILTER_SANITIZE_NUMBER_INT );
		$term_prev_id = filter_input( INPUT_POST, 'term_prev_id', FILTER_SANITIZE_NUMBER_INT );
		$term_next_id = filter_input( INPUT_POST, 'term_next_id', FILTER_SANITIZE_NUMBER_INT );
		$force_reload = filter_input( INPUT_POST, 'force_reload', FILTER_VALIDATE_BOOLEAN );

		// NOTE: term_prev_id/term_next_id can be null when moving to first/last position (not both at once)
		if ( !$term_id || !$taxonomy || !( $term_prev_id || $term_next_id ) ) {
			exit( $this->ajax_response( 'error', __( 'Input data fail!', 'i-order-terms' ) ) );
		}


		// fetch moved term
		$moved_term = get_term_by( 'id', $term_id, $taxonomy );
		if ( empty( $moved_term ) ) {
			exit( $this->ajax_response( 'error', __( 'Input data fail, no term found! Please try to reload page first.', 'i-order-terms' ) ) );
		}
		$term_parent_id = (int)$moved_term->parent;

		// fetch prev term
		if ( $term_prev_id ) {
			$term_prev = get_term_by( 'id', $term_prev_id, $taxonomy );
			if ( empty( $term_prev ) ) {
				exit( $this->ajax_response( 'error', __( 'Input data fail, no term found! Please try to reload page first.', 'i-order-terms' ) ) );
			}
			$term_prev_parent_id = (int)$term_prev->parent;
		} else {
			$term_prev_parent_id = null;
		}

		// fetch next term
		if ( $term_next_id ) {
			$term_next = get_term_by( 'id', $term_next_id, $taxonomy );
			if ( empty( $term_next ) ) {
				exit( $this->ajax_response( 'error', __( 'Input data fail, no term found! Please try to reload page first.', 'i-order-terms' ) ) );
			}
			$term_next_parent_id = (int)$term_next->parent;
		} else {
			$term_next_parent_id = null;
		}


		// parent ID for sorting
		if ( $term_next_id && isset( $term_next_parent_id ) && ( $term_prev_id == $term_next_parent_id || $term_parent_id != $term_prev_parent_id ) ) {
			// same level as next term
			$new_parent_id = $term_next_parent_id;
			if ( isset( $term_prev_id ) ) {
				$term_prev_id = null;
			}
		} else if ( $term_prev_id && isset( $term_prev_parent_id ) ) {
			// same level as previous term
			$new_parent_id = $term_prev_parent_id;
			if ( isset( $term_next_id ) ) {
				$term_next_id = null;
			}
		}


		// sort
		$terms = get_terms( $taxonomy, "parent={$new_parent_id}&hide_empty=0&i_order_terms=1&orderby=name&order=ASC" );
		if ( !empty( $terms ) ) {

			$index = 1;
			foreach ( $terms as $term ) {
				if ( isset( $term_next_id ) && $term->term_id == $term_next_id ) {
					// find term insert position

					// set custom order in database - for moved item
					if ( $this->reorder_term( $taxonomy, $moved_term, $index, $new_parent_id ) === false ) {
						exit( $this->ajax_response( 'error', __( 'Unable to save new term order for current item!', 'i-order-terms' ) ) );
					}

					// new index for next item
					$index++;
				}

				if ( $term->term_id != $moved_term->term_id ) {
					// for all but moved item

					if ( $term->custom_order != $index ) {
						// update in DB if custom_order changed

						// set new custom order
						if ( $this->reorder_term( $taxonomy, $term, $index ) === false ) {
							exit( $this->ajax_response( 'error', __( 'Unable to save new term order!', 'i-order-terms' ) ) );
						}
					}
				}

				if ( !isset( $term_next_id ) && $term->term_id == $term_prev_id ) {
					// find term insert position

					// new index for current item
					$index++;

					// set custom order in database - for moved item
					if ( $this->reorder_term( $taxonomy, $moved_term, $index, $new_parent_id ) === false ) {
						exit( $this->ajax_response( 'error', __( 'Unable to save new term order for current item!', 'i-order-terms' ) ) );
					}
				}

				$index++;
			}
		}


		// force page reload if changed parent ID or moved term has children
		// TODO: refresh without page reload
		if ( !$force_reload ) {
			$force_reload = ( $term_parent_id != $new_parent_id );

			if ( !$force_reload ) {
				$moved_term_children = get_terms( $taxonomy, "child_of={$moved_term->term_id}&hide_empty=0&fields=ids&number=1" );
				$force_reload = !empty( $moved_term_children );
			}
		}


		// success
		exit( $this->ajax_response( 'ok', '', $force_reload ) );
	} // end ajax_order_terms

} // I_Order_Terms
}
