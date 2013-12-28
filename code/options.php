<?php
$option_page = 'iorderterms.general';

// check permissions
$capability = apply_filters( "option_page_capability_{$option_page}", 'manage_options' );
if ( !current_user_can( $capability ) ) {
	wp_die( __( 'Cheatin&#8217; uh?' ) );
}


// fetch options
$options = get_option( 'iorderterms.general' );
if ( !is_array( $options ) ) {
	$options = array();
}


// update options
$post_action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );
$post_option_page = filter_input( INPUT_POST, 'option_page', FILTER_SANITIZE_STRING );
if ( $post_action === 'update' && $post_option_page === $option_page ) {
	// validate nonce
	check_admin_referer( "{$option_page}-options" );


	// fetch selected taxonomies
	$taxonomies = filter_input( INPUT_POST, 'taxonomies', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );
	$options['taxonomies-sort'] = array_keys( $taxonomies );

	// update in DB
	update_option( 'iorderterms.general', $options );


	// if no settings errors were registered add a general 'updated' message.
	if ( !count( get_settings_errors() ) )
		add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	// redirect back to the settings page that was submitted
	$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
	wp_redirect( $goback );

	exit;
}
?>

<div class="wrap">
 <h2><?php echo esc_html( sprintf( __( '%s : Settings' ), I_Order_Terms::PLUGIN_NAME ) ); ?></h2>

 <form method="post">
	<?php settings_fields( $option_page ); ?>

	<table class="form-table">
		<tr>
			<th scope="row"><?php _e( 'Enable sorting' ); ?></th>
			<td>
			 <fieldset>
				<legend class="screen-reader-text"><span><?php _e( 'Enable sorting for taxonomies' ); ?></span></legend>
				<?php
				// fetch registered taxonomies (registered via register_taxonomy function)
				if ( !empty( $GLOBALS['i_order_terms'] ) && is_a( $GLOBALS['i_order_terms'], 'I_Order_Terms' ) ) {
					$taxonomies_registered = $GLOBALS['i_order_terms']->get_taxonomies_registered();
				} else {
					$taxonomies_registered = array();
				}

				// fetch all taxonomies with standard WordPress UI that plugin supports
				$taxonomies = get_taxonomies( array('show_ui' => true), 'objects' );
				if ( !isset( $options['taxonomies-sort'] ) ) {
					$options['taxonomies-sort'] = array();
				}
				foreach ( $taxonomies as $taxonomy ) {
					if ( $taxonomy->_builtin && in_array( $taxonomy->name, array('nav_menu') ) ) {
						continue;
					}

					$is_registered = in_array( $taxonomy->name, $taxonomies_registered );
					$is_checked = $is_registered || in_array( $taxonomy->name, $options['taxonomies-sort'] );
					?>
					<label for="<?php echo "taxonomy-$taxonomy->name"; ?>" title="<?php echo esc_attr( $taxonomy->description ); ?>">
						<input id="<?php echo "taxonomy-$taxonomy->name"; ?>" name="taxonomies[<?php echo $taxonomy->name; ?>]" type="checkbox" value="1"
							<?php checked( '1', $is_checked, true ); ?>
							<?php disabled( '1', $is_registered, true ); ?> />
						<span><?php echo esc_html( $taxonomy->label ); ?></span>
					</label>
					<br />
					<?php
				}
				?>

				<p class="description"><?php echo esc_html( __( "(Taxonomies set as sortable via 'register_taxonomy' function can't be unchecked from options.)" ) ); ?></p>
			 </fieldset>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
 </form>
</div>
