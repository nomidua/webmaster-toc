<?php
/**
 * Plugin Name: WebMaster TOC
 * Plugin URI: https://github.com/nomidua/webmaster-toc
 * Description: Зміст статті за заголовками H2: автоматично або в місці мітки, кнопки в класичному редакторі, оновлення з GitHub.
 * Version: 1.2
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: nomidua
 * License: GPLv2 or later
 * Text Domain: webmaster-toc
 */

defined('ABSPATH') || exit;

define('WMTOC_VERSION', '1.2');
define('WMTOC_FILE', __FILE__);
define('WMTOC_DIR', plugin_dir_path(__FILE__));
define('WMTOC_URL', plugin_dir_url(__FILE__));
define('WMTOC_REPO', 'nomidua/webmaster-toc');

// Неповна копія плагіна (наприклад, реліз без папок) — без фатальної помилки, лише попередження
$wmtoc_files = ['inc/settings.php', 'inc/render.php', 'inc/editor.php', 'inc/admin.php', 'inc/updater.php', 'assets/editor.js', 'assets/editor.css', 'assets/editor-content.css'];
$wmtoc_missing = array_filter($wmtoc_files, function ($f) { return !is_file(WMTOC_DIR . $f); });
if ($wmtoc_missing) {
	add_action('admin_notices', function () use ($wmtoc_missing) {
		echo '<div class="notice notice-error"><p>WebMaster TOC: не вистачає файлів (' . esc_html(implode(', ', $wmtoc_missing)) . '). Перевстановіть плагін з повного архіву.</p></div>';
	});
	return;
}
foreach (['settings', 'render', 'editor', 'admin', 'updater'] as $wmtoc_f) require_once WMTOC_DIR . 'inc/' . $wmtoc_f . '.php';
