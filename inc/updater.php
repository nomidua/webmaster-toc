<?php
defined('ABSPATH') || exit;

// Остання версія з GitHub Releases (публічне репо, без токена), кеш 12 год
function wmtoc_remote($force = false) {
	$c = $force ? false : get_site_transient('wmtoc_remote');
	if (is_array($c)) return $c;
	$r = wp_remote_get('https://api.github.com/repos/' . WMTOC_REPO . '/releases/latest', [
		'timeout' => 10,
		'headers' => ['Accept' => 'application/vnd.github+json', 'User-Agent' => 'WebMaster-TOC/' . WMTOC_VERSION],
	]);
	if (is_wp_error($r) || (int) wp_remote_retrieve_response_code($r) !== 200) {
		set_site_transient('wmtoc_remote', [], HOUR_IN_SECONDS);
		return [];
	}
	$j = json_decode(wp_remote_retrieve_body($r), true);
	$zip = $j['zipball_url'] ?? '';
	foreach ((array) ($j['assets'] ?? []) as $a) {
		if (substr($a['name'] ?? '', -4) === '.zip') { $zip = $a['browser_download_url']; break; }
	}
	$d = [
		'version' => ltrim((string) ($j['tag_name'] ?? ''), 'vV'),
		'zip'     => $zip,
		'body'    => (string) ($j['body'] ?? ''),
		'date'    => (string) ($j['published_at'] ?? ''),
		'url'     => (string) ($j['html_url'] ?? ''),
	];
	set_site_transient('wmtoc_remote', $d, 12 * HOUR_IN_SECONDS);
	return $d;
}

function wmtoc_has_update() {
	$r = wmtoc_remote();
	return !empty($r['version']) && !empty($r['zip']) && version_compare($r['version'], WMTOC_VERSION, '>') ? $r : false;
}

// Нова версія в списку оновлень WordPress
add_filter('pre_set_site_transient_update_plugins', function ($t) {
	if (!is_object($t)) return $t;
	$base = plugin_basename(WMTOC_FILE);
	$item = (object) ['id' => 'github.com/' . WMTOC_REPO, 'slug' => 'webmaster-toc', 'plugin' => $base, 'url' => 'https://github.com/' . WMTOC_REPO, 'requires_php' => '7.4', 'icons' => [], 'banners' => []];
	if ($r = wmtoc_has_update()) {
		$item->new_version = $r['version'];
		$item->package = $r['zip'];
		$t->response[$base] = $item;
		unset($t->no_update[$base]);
	} else {
		$item->new_version = WMTOC_VERSION;
		$item->package = '';
		$t->no_update[$base] = $item;
		unset($t->response[$base]);
	}
	return $t;
});

// Вікно «Подробиці»: опис і список змін з релізу
add_filter('plugins_api', function ($res, $action, $args) {
	if ($action !== 'plugin_information' || ($args->slug ?? '') !== 'webmaster-toc') return $res;
	$r = wmtoc_remote();
	return (object) [
		'name'          => 'WebMaster TOC',
		'slug'          => 'webmaster-toc',
		'version'       => $r['version'] ?? WMTOC_VERSION,
		'author'        => 'nomidua',
		'homepage'      => 'https://github.com/' . WMTOC_REPO,
		'requires'      => '6.2',
		'requires_php'  => '7.4',
		'last_updated'  => $r['date'] ?? '',
		'download_link' => $r['zip'] ?? '',
		'sections'      => [
			'description' => wmtoc__('Зміст статті за заголовками H2: автоматично або в місці мітки, кнопки в класичному редакторі, оновлення з GitHub.', 'Оглавление статьи по заголовкам H2: автоматически или в месте метки, кнопки в классическом редакторе, обновления с GitHub.'),
			'changelog'   => wpautop(esc_html($r['body'] ?? '')),
		],
	];
}, 10, 3);

// Архів з GitHub розпаковується в папку з іншою назвою — повертаємо webmaster-toc
add_filter('upgrader_source_selection', function ($source, $remote, $upgrader, $extra = []) {
	global $wp_filesystem;
	if (($extra['plugin'] ?? '') !== plugin_basename(WMTOC_FILE) || !$wp_filesystem) return $source;
	$want = trailingslashit($remote) . dirname(plugin_basename(WMTOC_FILE)) . '/';
	if (untrailingslashit($source) === untrailingslashit($want)) return $source;
	return $wp_filesystem->move($source, $want, true) ? $want : new WP_Error('wmtoc_rename', 'WebMaster TOC: не вдалося перейменувати папку оновлення.');
}, 10, 4);

// Ручна перевірка оновлень
add_action('admin_post_wmtoc_check', function () {
	if (!current_user_can('update_plugins')) wp_die('403');
	check_admin_referer('wmtoc_check');
	wmtoc_remote(true);
	delete_site_transient('update_plugins');
	wp_update_plugins();
	wp_safe_redirect(admin_url('options-general.php?page=webmaster-toc&wmtoc_msg=checked'));
	exit;
});

// Посилання «Перевірити оновлення» в рядку плагіна
add_filter('plugin_row_meta', function ($meta, $file) {
	if ($file !== plugin_basename(WMTOC_FILE) || !current_user_can('update_plugins')) return $meta;
	$meta[] = '<a href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=wmtoc_check'), 'wmtoc_check')) . '">' . esc_html(wmtoc__('Перевірити оновлення', 'Проверить обновления')) . '</a>';
	return $meta;
}, 10, 2);

// Блок оновлення вгорі сторінки налаштувань
function wmtoc_update_box() {
	$r = wmtoc_has_update();
	$base = plugin_basename(WMTOC_FILE);
	echo '<div class="wmtoc-update"><span>' . esc_html(wmtoc__('Оновлення з GitHub.', 'Обновления с GitHub.') . ' ' . wmtoc__('Поточна', 'Текущая') . ' v' . WMTOC_VERSION . '.' . ($r ? ' ' . wmtoc__('Актуальна', 'Актуальная') . ' v' . $r['version'] . '.' : '')) . '</span>';
	if ($r && current_user_can('update_plugins')) {
		echo '<a class="button button-primary" href="' . esc_url(wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . rawurlencode($base)), 'upgrade-plugin_' . $base)) . '">' . esc_html(wmtoc__('Оновити', 'Обновить')) . '</a>';
	} elseif (current_user_can('update_plugins')) {
		echo '<a class="button" href="' . esc_url(wp_nonce_url(admin_url('admin-post.php?action=wmtoc_check'), 'wmtoc_check')) . '">' . esc_html(wmtoc__('Перевірити оновлення', 'Проверить обновления')) . '</a>';
	}
	echo '</div>';
}
