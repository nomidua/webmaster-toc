<?php
defined('ABSPATH') || exit;

// Екран редагування запису потрібного типу
function wmtoc_editor_screen() {
	$s = function_exists('get_current_screen') ? get_current_screen() : null;
	return $s && $s->base === 'post' && in_array($s->post_type, wmtoc_post_types(), true);
}

// Прихований стан «Без TOC» у формі запису (перемикається кнопкою в редакторі)
add_action('edit_form_after_editor', function ($post) {
	if (!in_array($post->post_type, wmtoc_post_types(), true)) return;
	wp_nonce_field('wmtoc_off', 'wmtoc_nonce');
	echo '<input type="hidden" name="wmtoc_off" id="wmtoc-off" value="' . (wmtoc_is_off($post->ID) ? '1' : '0') . '">';
});

// Збереження «Без TOC»; при знятті прибирає й старий прапорець ІР
add_action('save_post', function ($id, $post) {
	if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($id) || !in_array($post->post_type, wmtoc_post_types(), true)) return;
	if (!isset($_POST['wmtoc_nonce'], $_POST['wmtoc_off']) || !wp_verify_nonce(sanitize_key($_POST['wmtoc_nonce']), 'wmtoc_off') || !current_user_can('edit_post', $id)) return;
	if ($_POST['wmtoc_off'] === '1') {
		update_post_meta($id, '_wmtoc_off', '1');
	} else {
		delete_post_meta($id, '_wmtoc_off');
		delete_post_meta($id, '_ir_toc_off');
	}
}, 10, 2);

// Плагін TinyMCE: кнопки, плашка мітки, статус
add_filter('mce_external_plugins', function ($plugins, $editor_id = 'content') {
	if ($editor_id === 'content' && wmtoc_editor_screen()) $plugins['wmtoc'] = WMTOC_URL . 'assets/editor.js?ver=' . WMTOC_VERSION;
	return $plugins;
}, 10, 2);

// Стилі плашки всередині візуального редактора
add_filter('mce_css', function ($css) {
	if (!wmtoc_editor_screen()) return $css;
	$url = WMTOC_URL . 'assets/editor-content.css?ver=' . WMTOC_VERSION;
	return $css ? $css . ',' . $url : $url;
});

// Стилі кнопок і дані для скрипта редактора
add_action('admin_enqueue_scripts', function () {
	if (!wmtoc_editor_screen()) return;
	wp_enqueue_style('wmtoc-editor', WMTOC_URL . 'assets/editor.css', [], WMTOC_VERSION);
	wp_enqueue_script('mce-view');
	$o = wmtoc_opt();
	$data = [
		'minH2'      => (int) $o['min_h2'],
		'minChars'   => (int) $o['min_chars'],
		'masks'      => wmtoc_masks(),
		'maxWords'   => (int) $o['exclude_words'],
		'paused'     => (bool) wmtoc_conflicts(),
		'locale'     => wmtoc_admin_uk() ? 'uk-UA' : 'ru-RU',
		'i18n'       => [
			'toc'       => 'TOC',
			'off'       => wmtoc__('Без TOC', 'Без TOC'),
			'tocTitle'  => wmtoc__('Вставити мітку змісту після абзацу, де стоїть курсор. Без мітки зміст стане перед першим H2.', 'Вставить метку оглавления после абзаца, где стоит курсор. Без метки оглавление встанет перед первым H2.'),
			'offTitle'  => wmtoc__('Не показувати зміст у цій статті. Зберігається кнопкою «Оновити».', 'Не показывать оглавление в этой статье. Сохраняется кнопкой «Обновить».'),
			'ph'        => wmtoc__('TOC буде тут', 'TOC будет здесь'),
			'phRemove'  => wmtoc__('Прибрати мітку', 'Убрать метку'),
			'st'        => wmtoc__('Зміст', 'Содержание'),
			'stYes'     => wmtoc__('буде', 'будет'),
			'stNo'      => wmtoc__('не буде', 'не будет'),
			'stOff'     => wmtoc__('вимкнено', 'отключено'),
			'stManual'  => wmtoc__('вручну', 'вручную'),
			'stPaused'  => wmtoc__('на паузі', 'на паузе'),
			'stHeads'   => wmtoc__('Заголовки', 'Заголовки'),
			'stPcs'     => wmtoc__('шт.', 'шт.'),
			'stOf'      => wmtoc__('з', 'из'),
			'stSize'    => wmtoc__('розмір знаків', 'размер знаков'),
			'stNeed2'   => wmtoc__('для мітки потрібно від 2 заголовків', 'для метки нужно от 2 заголовков'),
		],
	];
	wp_register_script('wmtoc-data', false, [], WMTOC_VERSION, false);
	wp_enqueue_script('wmtoc-data');
	wp_add_inline_script('wmtoc-data', 'window.wmtocEditor=' . wp_json_encode($data, JSON_UNESCAPED_UNICODE) . ';');
});
