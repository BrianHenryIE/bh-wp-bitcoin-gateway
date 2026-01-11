<?php
/**
 * PHPUnit bootstrap file for wpunit tests. Since the plugin will not be otherwise autoloaded.
 *
 * @package    brianhenryie/bh-wp-bitcoin-gateway
 */

/** @var string $plugin_root_dir Defined in {@see /tests/bootstrap.php} */
global $plugin_root_dir;
require_once $plugin_root_dir . '/autoload.php';

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-posts-list-table.php';

/** @var string $project_root_dir Defined in {@see /tests/bootstrap.php} */
global $project_root_dir;
require_once $project_root_dir . '/vendor/wp-cli/wp-cli/php/utils.php';
