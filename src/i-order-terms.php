<?php
/**
 * Plugin Name: I Order Terms
 * Plugin URI: https://wordpress.org/plugins/i-order-terms/
 * Description: Allows theme developers to add order/sort functionality for categories, tags and terms.
 * Version: 1.4.0
 * Author: Igor Jerosimic
 * Author URI: http://igor.jerosimic.net/
 * Text Domain: i-order-terms
 * Domain Path: /languages
 * License: GPLv2 or later
 *
 *
 * Copyright 2013 Igor Jerosimic (igor.jerosimic.net)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 * @package IOrderTerms
 * @author Igor Jerosimic
 */

// Don't allow direct access
if ( !defined( 'ABSPATH' ) ) {
	die( "Hello! I freelance as a plugin, you can't call me directly. :/" );
}


require dirname( __FILE__ ) . '/code/class-i-order-terms.php';


// Plugin activation (NOTE: must be hooked inside main file)
register_activation_hook( __FILE__, array( 'I_Order_Terms', 'activate' ) );

// Init plugin
$GLOBALS['i_order_terms'] = new I_Order_Terms( dirname( __FILE__ ), plugins_url( '', __FILE__ ) );
