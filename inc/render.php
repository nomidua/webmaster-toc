<?php
defined('ABSPATH') || exit;

const WMTOC_MARK = '~(?:<p>\s*)?<!--wmtoc-->(?:\s*</p>)?~';

// Транслітерація для якорів (та сама таблиця, що й у старому TOC ІР)
function wmtoc_translit() {
	return ['а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya','і'=>'i','ї'=>'yi','є'=>'ye','ґ'=>'g'];
}

// Чистий текст заголовка: без тегів і HTML-сутностей
function wmtoc_plain($html) {
	$t = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	return trim(preg_replace('/[\s\x{00A0}]+/u', ' ', $t));
}

// Якір: транслітерація, лише латиниця і цифри, перші N слів
function wmtoc_slug($text, $words) {
	$s = strtr(mb_strtolower($text, 'UTF-8'), wmtoc_translit());
	$s = trim(preg_replace('/[\s-]+/', ' ', preg_replace('/[^a-z0-9\s-]/', ' ', $s)));
	$id = implode('-', array_slice(explode(' ', $s), 0, max(1, (int) $words)));
	return $id !== '' ? $id : 'section';
}

// Виключення: маска з * (без регістру), лише для коротких заголовків
function wmtoc_excluded($text, $masks = null, $max_words = null) {
	$masks = $masks ?? wmtoc_masks();
	$max_words = $max_words ?? (int) wmtoc_opt('exclude_words');
	$t = (string) preg_replace('/^[\s.,:;!?…—–-]+|[\s.,:;!?…—–-]+$/u', '', $text);
	if ($t === '' || !$masks || count((array) preg_split('/\s+/u', $t)) > $max_words) return false;
	foreach ($masks as $m) {
		if (preg_match('/^' . str_replace('\*', '.*', preg_quote($m, '/')) . '$/iu', $t)) return true;
	}
	return false;
}

// Мова запису: post_lang → підрахунок літер → мова за замовчуванням
function wmtoc_lang($post) {
	$l = get_post_meta($post->ID, 'post_lang', true);
	if ($l === 'uk' || $l === 'ru') return $l;
	$t  = mb_strtolower(wp_strip_all_tags($post->post_title . ' ' . $post->post_content), 'UTF-8');
	$uk = preg_match_all('/[іїєґ]/u', $t);
	$ru = preg_match_all('/[ыэъё]/u', $t);
	return $uk > $ru ? 'uk' : ($ru > $uk ? 'ru' : wmtoc_opt('default_lang'));
}

// Довжина чистого тексту статті
function wmtoc_text_len($html) {
	return mb_strlen(wmtoc_plain(preg_replace('~<(script|style)\b.*?</\1>~is', ' ', $html)), 'UTF-8');
}

// Розбір H2: ставить якорі, повертає пункти змісту
function wmtoc_parse($html) {
	$words = (int) wmtoc_opt('anchor_words');
	$masks = wmtoc_masks();
	$maxw  = (int) wmtoc_opt('exclude_words');
	$used  = [];
	$items = [];
	if (preg_match_all('/<[a-z][a-z0-9]*\b[^>]*\sid=["\']([^"\']+)["\']/i', $html, $m)) foreach ($m[1] as $x) $used[$x] = 1;
	$html = preg_replace_callback('/<h2\b([^>]*)>(.*?)<\/h2>/is', function ($h) use (&$used, &$items, $words, $masks, $maxw) {
		$text = wmtoc_plain($h[2]);
		if (preg_match('/\sid=["\']([^"\']+)["\']/i', ' ' . $h[1], $x)) {
			$id = $x[1];
		} else {
			$id = $base = wmtoc_slug($text, $words);
			for ($i = 2; isset($used[$id]); $i++) $id = $base . '-' . $i;
			$used[$id] = 1;
			$h[1] .= ' id="' . esc_attr($id) . '"';
		}
		if ($text !== '' && !wmtoc_excluded($text, $masks, $maxw)) $items[] = ['id' => $id, 'text' => $text];
		return '<h2' . $h[1] . '>' . $h[2] . '</h2>';
	}, $html);
	return ['html' => $html, 'items' => $items];
}

// Чи вимкнено зміст у записі (у т. ч. старий прапорець ІР)
function wmtoc_is_off($id) {
	return (bool) get_post_meta($id, '_wmtoc_off', true) || (bool) get_post_meta($id, '_ir_toc_off', true);
}

// Де можна виводити зміст
function wmtoc_should_run() {
	if (is_admin() || is_feed() || (defined('REST_REQUEST') && REST_REQUEST) || wmtoc_conflicts()) return false;
	if (!is_singular(wmtoc_post_types()) || !in_the_loop() || !is_main_query()) return false;
	if ((int) get_the_ID() !== (int) get_queried_object_id() || post_password_required()) return false;
	return !doing_filter('get_the_excerpt') && !doing_filter('wp_trim_excerpt');
}

// CSS змісту: виводиться один раз і лише там, де є зміст; порожній колір — як у темі
function wmtoc_css() {
	$o = wmtoc_opt();
	$w = (int) $o['line_width'];
	$line = $o['c_line'] !== '' && $w > 0 ? 'border-left:' . $w . 'px solid ' . $o['c_line'] . ';' : '';
	$arrow = $o['c_line'] !== '' ? $o['c_line'] : 'currentColor';
	$css = '.wmtoc{box-sizing:border-box;margin:0 0 30px;padding:14px 20px;' . ($o['c_bg'] !== '' ? 'background:' . $o['c_bg'] . ';' : '') . $line . '}'
		. '.wmtoc summary{display:flex;align-items:center;justify-content:space-between;gap:12px;list-style:none;cursor:pointer;font-size:1.15em;font-weight:600;line-height:1.3;' . ($o['c_title'] !== '' ? 'color:' . $o['c_title'] . ';' : '') . '}'
		. '.wmtoc summary::-webkit-details-marker{display:none;}'
		. '.wmtoc summary::after{content:"";flex:0 0 auto;width:8px;height:8px;margin:0 4px 4px 0;border-right:2px solid ' . $arrow . ';border-bottom:2px solid ' . $arrow . ';transform:rotate(45deg);transition:transform .2s;}'
		. '.wmtoc[open] summary::after{margin:4px 4px 0 0;transform:rotate(-135deg);}'
		. '.wmtoc ul.wmtoc-list{margin:12px 0 0!important;padding:0 0 0 20px!important;list-style:disc outside!important;}'
		. '.wmtoc ul.wmtoc-list li{margin:0 0 6px!important;padding:0!important;line-height:1.4;list-style:inherit!important;}'
		. '.wmtoc ul.wmtoc-list li:last-child{margin-bottom:0!important;}'
		. '.wmtoc ul.wmtoc-list li::before{content:none!important;}'
		. ($o['c_marker'] !== '' ? '.wmtoc ul.wmtoc-list li::marker{color:' . $o['c_marker'] . ';}' : '')
		. '.wmtoc a{text-decoration:none!important;box-shadow:none!important;border:0!important;' . ($o['c_link'] !== '' ? 'color:' . $o['c_link'] . '!important;' : '') . '}'
		. ($o['c_hover'] !== '' ? '.wmtoc a:hover{color:' . $o['c_hover'] . '!important;}' : '')
		. 'h2[id]{scroll-margin-top:' . (int) $o['offset'] . 'px;}'
		. $o['custom_css'];
	return '<style id="wmtoc-css">' . $css . '</style>';
}

// Мітки місця: [wm_toc] і старі [ir_toc], [ez-toc], [toc]
add_action('init', function () {
	foreach (['wm_toc', 'ir_toc', 'ez-toc', 'toc'] as $tag) {
		if (!shortcode_exists($tag)) add_shortcode($tag, function () { return '<!--wmtoc-->'; });
	}
}, 20);

// Головний фільтр: вставка змісту в текст запису
add_filter('the_content', 'wmtoc_filter', 20);
function wmtoc_filter($c) {
	if (!wmtoc_should_run() || wmtoc_is_off(get_the_ID())) return preg_replace(WMTOC_MARK, '', $c);
	$manual = (bool) preg_match(WMTOC_MARK, $c);
	$p = wmtoc_parse($c);
	$n = count($p['items']);
	$ok = $manual ? $n >= 2 : ($n >= (int) wmtoc_opt('min_h2') && wmtoc_text_len($c) >= (int) wmtoc_opt('min_chars'));
	if (!$ok) return preg_replace(WMTOC_MARK, '', $c);

	static $css_done = false;
	$o     = wmtoc_opt();
	$title = wmtoc_lang(get_post()) === 'uk' ? $o['title_uk'] : $o['title_ru'];
	$open  = !$o['collapse_from'] || $n < (int) $o['collapse_from'];
	$li    = '';
	foreach ($p['items'] as $it) $li .= '<li><a href="#' . esc_attr($it['id']) . '">' . esc_html($it['text']) . '</a></li>';
	$toc = ($css_done ? '' : wmtoc_css())
		. '<nav class="wmtoc-nav" aria-label="' . esc_attr($title) . '"><details class="wmtoc"' . ($open ? ' open' : '') . '><summary>' . esc_html($title) . '</summary><ul class="wmtoc-list">' . $li . '</ul></details></nav>';
	$css_done = true;

	$html = $p['html'];
	if ($manual) {
		$html = preg_replace_callback(WMTOC_MARK, function () use ($toc) { return $toc; }, $html, 1);
		return preg_replace(WMTOC_MARK, '', $html);
	}
	return preg_replace_callback('/<h2\b/i', function () use ($toc) { return $toc . '<h2'; }, $html, 1);
}
