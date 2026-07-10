<?php
/**
 * PRC Block Forms
 *
 * @package           PRC_Block_Forms
 * @author            Seth Rubenstein
 * @copyright         2026 Pew Research Center
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       PRC Block Forms
 * Plugin URI:        https://github.com/pewresearch/prc-platform
 * Description:       Forms management for the PRC Platform: reusable Forms CPT, form blocks, response logging, REST submission handlers, and the Synced Form block.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.2
 * Author:            Seth Rubenstein
 * Author URI:        https://pewresearch.org
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       prc-block-forms
 * Requires Plugins:  prc-scripts
 */

declare(strict_types=1);

namespace PRC\Platform\Block_Forms;

if ( ! defined( 'WPINC' ) ) {
	die;
}
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'DEFAULT_TECHNICAL_CONTACT' ) ) {
	define( 'DEFAULT_TECHNICAL_CONTACT', 'webdev@pewresearch.org' );
}

define( 'PRC_BLOCK_FORMS_FILE', __FILE__ );
define( 'PRC_BLOCK_FORMS_DIR', __DIR__ );
define( 'PRC_BLOCK_FORMS_VERSION', '1.0.0' );

require plugin_dir_path( __FILE__ ) . 'includes/utils.php';

/**
 * The core plugin class that defines the hooks that initialize the various components.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-plugin.php';

/**
 * Begins execution of the plugin.
 */
function run_prc_block_forms(): void {
	$plugin = new Plugin();
	$plugin->run();
}
run_prc_block_forms();
