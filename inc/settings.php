<?php
defined('ABSPATH') || exit;

// Значення за замовчуванням
function wmtoc_defaults() {
	return [
		'min_h2'        => 5,
		'min_chars'     => 3000,
		'pages'         => 0,
		'collapse_from' => 9,
		'title_uk'      => 'Зміст',
		'title_ru'      => 'Содержание',
		'default_lang'  => 'ru',
		'c_line'        => '#646970',
		'line_width'    => 3,
		'c_bg'          => '#f6f7f7',
		'c_title'       => '#1d2327',
		'c_link'        => '#1d2327',
		'c_hover'       => '#646970',
		'c_marker'      => '#646970',
		'offset'        => 90,
		'custom_css'    => '',
		'anchor_words'  => 5,
		'exclude'       => "*ступление*\n*аключение*\n*итог*\n*вывод*\n*вступ*\n*исновок*\n*аключення*",
		'exclude_words' => 4,
	];
}

// Поточні налаштування (одне значення або всі)
function wmtoc_opt($key = null) {
	$o = wmtoc_migrate((array) get_option('wmtoc_settings', []));
	$o = wp_parse_args($o, wmtoc_defaults());
	return $key === null ? $o : ($o[$key] ?? null);
}

// Типи записів, де працює зміст
function wmtoc_post_types() {
	return wmtoc_opt('pages') ? ['post', 'page'] : ['post'];
}

// Маски виключень у вигляді масиву
function wmtoc_masks() {
	return array_values(array_filter(array_map('trim', explode("\n", (string) wmtoc_opt('exclude'))), 'strlen'));
}

// Старі ключі 1.0 (accent, bg, text) → кольори 1.1
function wmtoc_migrate($o) {
	if (isset($o['accent']) && !isset($o['c_line'])) {
		$o['c_line'] = $o['c_hover'] = $o['c_marker'] = $o['accent'];
	}
	if (isset($o['text']) && !isset($o['c_title'])) $o['c_title'] = $o['c_link'] = $o['text'];
	if (isset($o['bg']) && !isset($o['c_bg'])) $o['c_bg'] = $o['bg'];
	unset($o['accent'], $o['text'], $o['bg']);
	return $o;
}

// Колір: порожньо (як у темі), #hex, rgb()/rgba()/hsl(), var(--…), градієнт тощо; null — не прийнято
function wmtoc_color($v) {
	$v = trim(preg_replace('/\s+/', ' ', (string) $v));
	if ($v === '') return '';
	if (strlen($v) > 300 || preg_match('/[;{}<>"\'\\\\@]|url\s*\(|expression|javascript/i', $v)) return null;
	$depth = 0;
	foreach (str_split($v) as $ch) {
		if ($ch === '(') $depth++;
		if ($ch === ')' && --$depth < 0) return null;
	}
	return $depth === 0 && preg_match('/^[#\w\s(),.%\/+-]+$/', $v) ? $v : null;
}

// Кольорові поля: ключ → підписи
function wmtoc_color_keys() {
	return [
		'c_line'   => [wmtoc__('Смуга', 'Полоса'), wmtoc__('ліва смуга і стрілка', 'левая полоса и стрелка')],
		'c_bg'     => [wmtoc__('Фон', 'Фон'), wmtoc__('можна градієнт: linear-gradient(90deg, #f9f9f9 0%, #fff 50%)', 'можно градиент: linear-gradient(90deg, #f9f9f9 0%, #fff 50%)')],
		'c_title'  => [wmtoc__('Заголовок', 'Заголовок'), wmtoc__('«Зміст / Содержание»', '«Зміст / Содержание»')],
		'c_link'   => [wmtoc__('Пункти', 'Пункты'), wmtoc__('посилання в списку', 'ссылки в списке')],
		'c_hover'  => [wmtoc__('Наведення', 'Наведение'), wmtoc__('пункт під курсором', 'пункт под курсором')],
		'c_marker' => [wmtoc__('Маркери', 'Маркеры'), wmtoc__('крапки списку', 'точки списка')],
	];
}

// Очищення налаштувань при збереженні та імпорті
function wmtoc_sanitize($in) {
	$d   = wmtoc_defaults();
	$in  = wmtoc_migrate(is_array($in) ? $in : []);
	$int = function ($k, $min, $max) use ($in, $d) {
		$v = isset($in[$k]) && is_numeric($in[$k]) ? (int) $in[$k] : $d[$k];
		return max($min, min($max, $v));
	};
	$txt = function ($k) use ($in, $d) {
		$v = isset($in[$k]) ? sanitize_text_field((string) $in[$k]) : '';
		return $v !== '' ? $v : $d[$k];
	};
	$masks = [];
	foreach (explode("\n", str_replace("\r", '', (string) ($in['exclude'] ?? ''))) as $m) {
		$m = sanitize_text_field($m);
		if ($m !== '' && !in_array(mb_strtolower($m), array_map('mb_strtolower', $masks), true)) $masks[] = $m;
	}
	$prev = (array) get_option('wmtoc_settings', []);
	$colors = [];
	foreach (array_keys($d) as $k) {
		if (strpos($k, 'c_') !== 0) continue;
		$c = array_key_exists($k, $in) ? wmtoc_color($in[$k]) : $d[$k];
		if ($c === null) {
			$c = $prev[$k] ?? $d[$k];
			add_settings_error('wmtoc_settings', 'wmtoc_' . $k, wmtoc__('Колір не розпізнано, залишено попередній: ', 'Цвет не распознан, оставлен прежний: ') . sanitize_text_field((string) $in[$k]));
		}
		$colors[$k] = $c;
	}
	return $colors + [
		'min_h2'        => $int('min_h2', 1, 50),
		'min_chars'     => $int('min_chars', 0, 100000),
		'pages'         => empty($in['pages']) ? 0 : 1,
		'collapse_from' => $int('collapse_from', 0, 100),
		'title_uk'      => $txt('title_uk'),
		'title_ru'      => $txt('title_ru'),
		'default_lang'  => ($in['default_lang'] ?? '') === 'uk' ? 'uk' : 'ru',
		'line_width'    => $int('line_width', 0, 10),
		'offset'        => $int('offset', 0, 500),
		'custom_css'    => str_replace('<', '', (string) ($in['custom_css'] ?? '')),
		'anchor_words'  => $int('anchor_words', 1, 15),
		'exclude'       => implode("\n", $masks),
		'exclude_words' => $int('exclude_words', 1, 20),
	];
}

add_action('admin_init', function () {
	register_setting('wmtoc', 'wmtoc_settings', ['type' => 'array', 'sanitize_callback' => 'wmtoc_sanitize']);
});

// Текст інтерфейсу за мовою адмінки користувача
function wmtoc_admin_uk() {
	return strpos(get_user_locale(), 'uk') === 0;
}
function wmtoc__($uk, $ru) {
	return wmtoc_admin_uk() ? $uk : $ru;
}

// Конфлікти: активний Easy TOC або старий код змісту ІР — тоді плагін на паузі
function wmtoc_conflicts() {
	$c = [];
	if (class_exists('ezTOC') || in_array('easy-table-of-contents/easy-table-of-contents.php', (array) get_option('active_plugins', []), true)) $c[] = 'eztoc';
	if (function_exists('ir_toc_btn')) $c[] = 'ir';
	return $c;
}
