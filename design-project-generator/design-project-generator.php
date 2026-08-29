<?php
/**
 * Plugin Name:       Design Project Generator
 * Plugin URI:        https://github.com/conect2hamza/random-project-generator-for-graphics
 * Description:       DesignForge — generate realistic graphic design briefs, practise against timed challenges, build interactive mini demos and export your work. No paid APIs required.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            DesignForge
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       design-project-generator
 * Domain Path:       /languages
 *
 * @package DesignProjectGenerator
 */

defined( 'ABSPATH' ) || exit;

define( 'DPG_VERSION', '1.0.0' );
define( 'DPG_PLUGIN_FILE', __FILE__ );
define( 'DPG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DPG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DPG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DPG_PLUGIN_DIR . 'includes/class-security.php';
require_once DPG_PLUGIN_DIR . 'includes/class-project-database.php';
require_once DPG_PLUGIN_DIR . 'includes/class-project-generator.php';
require_once DPG_PLUGIN_DIR . 'includes/class-post-types.php';
require_once DPG_PLUGIN_DIR . 'includes/class-export.php';
require_once DPG_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once DPG_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once DPG_PLUGIN_DIR . 'includes/class-block.php';
require_once DPG_PLUGIN_DIR . 'includes/class-admin.php';
require_once DPG_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Main plugin instance.
 *
 * @return DPG_Plugin
 */
function dpg() {
	return DPG_Plugin::instance();
}

dpg();

register_activation_hook( __FILE__, array( 'DPG_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DPG_Plugin', 'deactivate' ) );
