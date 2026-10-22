<?php
/**
 * Plugin Name: WebMaster TOC
 * Plugin URI: https://github.com/nomidua/webmaster-toc
 * Description: Зміст статті за заголовками H2: автоматично або в місці мітки, кнопки в класичному редакторі, оновлення з GitHub.
 * Version: 1.1
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: nomidua
 * License: GPLv2 or later
 * Text Domain: webmaster-toc
 */

defined('ABSPATH') || exit;

define('WMTOC_VERSION', '1.1');
define('WMTOC_FILE', __FILE__);
define('WMTOC_DIR', plugin_dir_path(__FILE__));
define('WMTOC_URL', plugin_dir_url(__FILE__));
define('WMTOC_REPO', 'nomidua/webmaster-toc');

require_once WMTOC_DIR . 'inc/settings.php';
require_once WMTOC_DIR . 'inc/render.php';
require_once WMTOC_DIR . 'inc/editor.php';
require_once WMTOC_DIR . 'inc/admin.php';
require_once WMTOC_DIR . 'inc/updater.php';
