<?php
defined('ABSPATH') || exit;

// Сторінка налаштувань: Налаштування → WebMaster TOC
add_action('admin_menu', function () {
	add_options_page('WebMaster TOC', 'WebMaster TOC', 'manage_options', 'webmaster-toc', 'wmtoc_settings_page');
});

// Посилання в списку плагінів
add_filter('plugin_action_links_' . plugin_basename(WMTOC_FILE), function ($links) {
	array_unshift($links, '<a href="' . esc_url(admin_url('options-general.php?page=webmaster-toc')) . '">' . esc_html(wmtoc__('Налаштування', 'Настройки')) . '</a>');
	return $links;
});

// Попередження: плагін на паузі через конфлікт
add_action('admin_notices', function () {
	if (!current_user_can('manage_options')) return;
	$s = get_current_screen();
	if (!$s || !in_array($s->base, ['plugins', 'dashboard', 'post', 'edit', 'settings_page_webmaster-toc'], true)) return;
	foreach (wmtoc_conflicts() as $c) {
		$msg = $c === 'eztoc'
			? wmtoc__('WebMaster TOC на паузі: активний плагін Easy Table of Contents. Вимкніть його, щоб не було двох змістів, і WebMaster TOC запрацює.', 'WebMaster TOC на паузе: активен плагин Easy Table of Contents. Отключите его, чтобы не было двух оглавлений, и WebMaster TOC заработает.')
			: wmtoc__('WebMaster TOC на паузі: у functions.php теми є старий код змісту (ir_toc). Видаліть його, і WebMaster TOC запрацює.', 'WebMaster TOC на паузе: в functions.php темы есть старый код оглавления (ir_toc). Удалите его, и WebMaster TOC заработает.');
		echo '<div class="notice notice-warning"><p>' . esc_html($msg) . '</p></div>';
	}
});

// Експорт налаштувань у JSON
add_action('admin_post_wmtoc_export', function () {
	if (!current_user_can('manage_options')) wp_die('403');
	check_admin_referer('wmtoc_export');
	$host = preg_replace('/[^a-z0-9.-]/i', '', (string) wp_parse_url(home_url(), PHP_URL_HOST));
	nocache_headers();
	header('Content-Type: application/json; charset=utf-8');
	header('Content-Disposition: attachment; filename="webmaster-toc-' . $host . '-' . gmdate('Y-m-d') . '.json"');
	echo wp_json_encode(['plugin' => 'webmaster-toc', 'version' => WMTOC_VERSION, 'settings' => wmtoc_opt()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	exit;
});

// Імпорт налаштувань з JSON
add_action('admin_post_wmtoc_import', function () {
	if (!current_user_can('manage_options')) wp_die('403');
	check_admin_referer('wmtoc_import');
	$msg  = 'import_bad';
	$file = $_FILES['wmtoc_file']['tmp_name'] ?? '';
	if ($file && is_uploaded_file($file) && filesize($file) < 200000) {
		$j = json_decode((string) file_get_contents($file), true);
		if (is_array($j) && ($j['plugin'] ?? '') === 'webmaster-toc' && is_array($j['settings'] ?? null)) {
			update_option('wmtoc_settings', wmtoc_sanitize($j['settings']));
			$msg = 'import_ok';
		}
	}
	wp_safe_redirect(admin_url('options-general.php?page=webmaster-toc&wmtoc_msg=' . $msg));
	exit;
});

// Скидання налаштувань
add_action('admin_post_wmtoc_reset', function () {
	if (!current_user_can('manage_options')) wp_die('403');
	check_admin_referer('wmtoc_reset');
	delete_option('wmtoc_settings');
	wp_safe_redirect(admin_url('options-general.php?page=webmaster-toc&wmtoc_msg=reset'));
	exit;
});

// Рядок форми
function wmtoc_row($title, $html, $desc = '') {
	echo '<tr><th scope="row">' . esc_html($title) . '</th><td>' . $html . ($desc ? '<p class="description">' . esc_html($desc) . '</p>' : '') . '</td></tr>';
}
function wmtoc_num($k, $min, $max, $suffix = '') {
	return '<input type="number" class="small-text" name="wmtoc_settings[' . $k . ']" value="' . esc_attr(wmtoc_opt($k)) . '" min="' . $min . '" max="' . $max . '"> ' . esc_html($suffix);
}
function wmtoc_text($k, $cls = 'regular-text') {
	return '<input type="text" class="' . $cls . '" name="wmtoc_settings[' . $k . ']" value="' . esc_attr(wmtoc_opt($k)) . '">';
}
function wmtoc_color_field($k, $label, $hint) {
	return '<div class="wmtoc-color"><span class="wmtoc-sw" data-k="' . $k . '" title="' . esc_attr(wmtoc__('Вибрати колір', 'Выбрать цвет')) . '"><input type="color" class="wmtoc-pick" data-k="' . $k . '" tabindex="-1"></span><input type="text" class="regular-text wmtoc-c" data-k="' . $k . '" name="wmtoc_settings[' . $k . ']" value="' . esc_attr(wmtoc_opt($k)) . '" placeholder="' . esc_attr(wmtoc__('як у темі', 'как в теме')) . '"><span class="wmtoc-cl"><b>' . esc_html($label) . '</b> — ' . esc_html($hint) . '</span></div>';
}

function wmtoc_settings_page() {
	if (!current_user_can('manage_options')) return;
	$o   = wmtoc_opt();
	$msg = sanitize_key($_GET['wmtoc_msg'] ?? '');
	$msgs = [
		'import_ok'  => ['success', wmtoc__('Налаштування імпортовано.', 'Настройки импортированы.')],
		'import_bad' => ['error', wmtoc__('Файл не підходить: потрібен JSON, експортований з WebMaster TOC.', 'Файл не подходит: нужен JSON, экспортированный из WebMaster TOC.')],
		'reset'      => ['success', wmtoc__('Налаштування скинуто до стандартних.', 'Настройки сброшены к стандартным.')],
		'checked'    => ['success', wmtoc__('Оновлення перевірено.', 'Обновления проверены.')],
	];
	echo '<div class="wrap wmtoc-wrap"><h1>WebMaster TOC</h1>';
	if (isset($msgs[$msg])) echo '<div class="notice notice-' . $msgs[$msg][0] . ' is-dismissible"><p>' . esc_html($msgs[$msg][1]) . '</p></div>';
	wmtoc_update_box();

	echo '<form method="post" action="options.php">';
	settings_fields('wmtoc');

	echo '<h2>' . esc_html(wmtoc__('Коли показувати', 'Когда показывать')) . '</h2><table class="form-table" role="presentation">';
	wmtoc_row(wmtoc__('Заголовків H2', 'Заголовков H2'), wmtoc_num('min_h2', 1, 50, wmtoc__('і більше', 'и больше')), wmtoc__('Рахуються без виключених заголовків. Мітка в тексті ставить зміст уже від 2 H2.', 'Считаются без исключённых заголовков. Метка в тексте ставит оглавление уже от 2 H2.'));
	wmtoc_row(wmtoc__('Довжина тексту', 'Длина текста'), wmtoc_num('min_chars', 0, 100000, wmtoc__('символів і більше', 'символов и больше')), wmtoc__('Чистий текст статті з пробілами, без HTML. Обидві умови мають виконуватися разом.', 'Чистый текст статьи с пробелами, без HTML. Оба условия должны выполняться вместе.'));
	wmtoc_row(wmtoc__('Де', 'Где'), '<label><input type="checkbox" checked disabled> ' . esc_html(wmtoc__('Записи', 'Записи')) . '</label> &nbsp; <label><input type="hidden" name="wmtoc_settings[pages]" value="0"><input type="checkbox" name="wmtoc_settings[pages]" value="1"' . checked($o['pages'], 1, false) . '> ' . esc_html(wmtoc__('Сторінки', 'Страницы')) . '</label>');
	echo '</table>';

	echo '<h2>' . esc_html(wmtoc__('Вигляд', 'Вид')) . '</h2><table class="form-table" role="presentation">';
	wmtoc_row(wmtoc__('Згорнутий', 'Свёрнутый'), wmtoc_num('collapse_from', 0, 100, wmtoc__('пунктів і більше', 'пунктов и больше')), wmtoc__('0 — завжди відкритий.', '0 — всегда открыт.'));
	wmtoc_row(wmtoc__('Заголовок змісту', 'Заголовок оглавления'), wmtoc_text('title_uk', 'regular-text wmtoc-short') . ' uk &nbsp; ' . wmtoc_text('title_ru', 'regular-text wmtoc-short') . ' ru');
	wmtoc_row(wmtoc__('Мова за замовчуванням', 'Язык по умолчанию'), '<select name="wmtoc_settings[default_lang]"><option value="uk"' . selected($o['default_lang'], 'uk', false) . '>uk</option><option value="ru"' . selected($o['default_lang'], 'ru', false) . '>ru</option></select>', wmtoc__('Мова береться з post_lang запису, інакше визначається за текстом (і/ї/є/ґ проти ы/э/ъ/ё). Ця — коли визначити не вдалося.', 'Язык берётся из post_lang записи, иначе определяется по тексту (і/ї/є/ґ против ы/э/ъ/ё). Этот — когда определить не удалось.'));
	$cf = '';
	foreach (wmtoc_color_keys() as $k => $l) $cf .= wmtoc_color_field($k, $l[0], $l[1]);
	wmtoc_row(wmtoc__('Кольори', 'Цвета'), $cf, wmtoc__('#hex, rgb(), rgba(), назва кольору або змінна теми, наприклад var(--accent-color-1). Порожнє поле — як у темі. Квадрат — вибір кольору.', '#hex, rgb(), rgba(), название цвета или переменная темы, например var(--accent-color-1). Пустое поле — как в теме. Квадрат — выбор цвета.'));
	wmtoc_row(wmtoc__('Товщина смуги', 'Толщина полосы'), wmtoc_num('line_width', 0, 10, wmtoc__('px, 0 — без смуги', 'px, 0 — без полосы')));
	wmtoc_row(wmtoc__('Відступ при переході', 'Отступ при переходе'), wmtoc_num('offset', 0, 500, 'px'), wmtoc__('Щоб заголовок після кліку в змісті не ховався під липкою шапкою.', 'Чтобы заголовок после клика в оглавлении не прятался под липкой шапкой.'));
	wmtoc_row(wmtoc__('Додатковий CSS', 'Дополнительный CSS'), '<textarea name="wmtoc_settings[custom_css]" rows="4" class="large-text code" placeholder=".wmtoc summary{font-size:20px;}">' . esc_textarea($o['custom_css']) . '</textarea>', wmtoc__('Класи: .wmtoc, .wmtoc summary, .wmtoc-list, .wmtoc a.', 'Классы: .wmtoc, .wmtoc summary, .wmtoc-list, .wmtoc a.'));
	wmtoc_row(wmtoc__('Прев’ю', 'Превью'), '<div class="wmtoc-preview">' . wmtoc_preview_html() . '</div>', wmtoc__('Змінні теми var(--…) тут не діють, лише на сайті.', 'Переменные темы var(--…) здесь не работают, только на сайте.'));
	echo '</table>';

	echo '<h2>' . esc_html(wmtoc__('Якорі та виключення', 'Якоря и исключения')) . '</h2><table class="form-table" role="presentation">';
	wmtoc_row(wmtoc__('Якір заголовка', 'Якорь заголовка'), wmtoc_num('anchor_words', 1, 15, wmtoc__('слів максимум', 'слов максимум')), wmtoc__('Транслітерація латиницею. Наявні id заголовків не змінюються. Зміна числа змінить якорі в усіх статтях.', 'Транслитерация латиницей. Существующие id заголовков не меняются. Изменение числа изменит якоря во всех статьях.'));
	wmtoc_row(wmtoc__('Не включати в зміст', 'Не включать в оглавление'), '<textarea name="wmtoc_settings[exclude]" rows="7" class="regular-text code">' . esc_textarea($o['exclude']) . '</textarea>', wmtoc__('Одна маска в рядку, * — будь-які символи, регістр не важливий. Наприклад *вывод* виключить «Выводы».', 'Одна маска в строке, * — любые символы, регистр не важен. Например *вывод* исключит «Выводы».'));
	wmtoc_row(wmtoc__('Лише короткі заголовки', 'Только короткие заголовки'), wmtoc_num('exclude_words', 1, 20, wmtoc__('слів максимум', 'слов максимум')), wmtoc__('Довші заголовки не виключаються, навіть якщо збігаються з маскою: «Вывод средств с карты Монобанк» залишиться.', 'Более длинные заголовки не исключаются, даже если совпадают с маской: «Вывод средств с карты Монобанк» останется.'));
	echo '</table>';

	submit_button();
	echo '</form>';

	echo '<h2>' . esc_html(wmtoc__('Імпорт / експорт', 'Импорт / экспорт')) . '</h2><table class="form-table" role="presentation">';
	wmtoc_row(wmtoc__('Експорт', 'Экспорт'), '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">' . wp_nonce_field('wmtoc_export', '_wpnonce', true, false) . '<input type="hidden" name="action" value="wmtoc_export"><button class="button">' . esc_html(wmtoc__('Завантажити JSON', 'Скачать JSON')) . '</button></form>');
	wmtoc_row(wmtoc__('Імпорт', 'Импорт'), '<form method="post" enctype="multipart/form-data" action="' . esc_url(admin_url('admin-post.php')) . '">' . wp_nonce_field('wmtoc_import', '_wpnonce', true, false) . '<input type="hidden" name="action" value="wmtoc_import"><input type="file" name="wmtoc_file" accept=".json,application/json" required> <button class="button">' . esc_html(wmtoc__('Імпортувати', 'Импортировать')) . '</button></form>', wmtoc__('Замінить усі поточні налаштування.', 'Заменит все текущие настройки.'));
	wmtoc_row(wmtoc__('Скидання', 'Сброс'), '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" onsubmit="return confirm(\'' . esc_js(wmtoc__('Скинути всі налаштування до стандартних?', 'Сбросить все настройки к стандартным?')) . '\')">' . wp_nonce_field('wmtoc_reset', '_wpnonce', true, false) . '<input type="hidden" name="action" value="wmtoc_reset"><button class="button-link wmtoc-reset">' . esc_html(wmtoc__('Скинути до стандартних', 'Сбросить к стандартным')) . '</button></form>');
	echo '</table></div>';
	wmtoc_admin_assets();
}

// Прев'ю змісту на сторінці налаштувань
function wmtoc_preview_html() {
	$t = wmtoc_admin_uk() ? wmtoc_opt('title_uk') : wmtoc_opt('title_ru');
	$items = wmtoc_admin_uk() ? ['Що таке зміст', 'Як він працює', 'Налаштування кольорів'] : ['Что такое оглавление', 'Как оно работает', 'Настройка цветов'];
	$li = '';
	foreach ($items as $i) $li .= '<li><a href="#" onclick="return false">' . esc_html($i) . '</a></li>';
	return wmtoc_css() . '<details class="wmtoc" open><summary>' . esc_html($t) . '</summary><ul class="wmtoc-list">' . $li . '</ul></details>';
}

function wmtoc_admin_assets() {
	?>
<style>
.wmtoc-wrap .wmtoc-short{width:12em;}
.wmtoc-wrap .wmtoc-color{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin:0 0 6px;}
.wmtoc-wrap .wmtoc-color .regular-text{width:22em;max-width:100%;}
.wmtoc-wrap .wmtoc-cl{color:#50575e;}
.wmtoc-wrap .wmtoc-cl b{color:#1d2327;font-weight:600;}
.wmtoc-wrap .wmtoc-sw{position:relative;flex:0 0 auto;width:28px;height:28px;box-sizing:border-box;border:1px solid #8c8f94;border-radius:4px;background-color:#fff;background-image:repeating-conic-gradient(#dcdcde 0 25%,#fff 0 50%);background-size:10px 10px;cursor:pointer;}
.wmtoc-wrap .wmtoc-sw:hover{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;}
.wmtoc-wrap .wmtoc-sw i{position:absolute;inset:0;border-radius:3px;}
.wmtoc-wrap .wmtoc-pick{position:absolute;inset:0;width:100%;height:100%;padding:0;border:0;opacity:0;cursor:pointer;}
.wmtoc-wrap .wmtoc-preview{max-width:520px;font-size:15px;}
.wmtoc-wrap .wmtoc-preview .wmtoc{margin:0;}
.wmtoc-wrap .wmtoc-update{display:flex;align-items:center;gap:12px;margin:12px 0 4px;}
.wmtoc-wrap .wmtoc-reset{color:#b32d2e;}
.wmtoc-wrap .wmtoc-reset:hover{color:#8a2424;}
</style>
<script>
(function(){
var prev=document.querySelector('.wmtoc-preview'),st=document.createElement('style');document.head.appendChild(st);
function val(k){var i=document.querySelector('.wmtoc-c[data-k="'+k+'"]');return i?i.value.trim():'';}
function hex(v){var c=document.createElement('canvas').getContext('2d');c.fillStyle='#000';c.fillStyle=v;return /^#[0-9a-f]{6}$/i.test(c.fillStyle)?c.fillStyle:null;}
function upd(){
 document.querySelectorAll('.wmtoc-sw').forEach(function(sw){var k=sw.dataset.k,v=val(k),i=sw.querySelector('i')||sw.appendChild(document.createElement('i'));i.style.background='';if(v)i.style.background=v;var h=v&&hex(v);if(h)sw.querySelector('.wmtoc-pick').value=h;});
 var w=parseInt((document.querySelector('[name="wmtoc_settings[line_width]"]')||{}).value,10)||0,P='.wmtoc-preview .wmtoc',c='';
 c+=P+'{background:'+(val('c_bg')||'transparent')+'!important;border-left:'+(val('c_line')&&w?w+'px solid '+val('c_line'):'0')+'!important;}';
 c+=P+' summary{color:'+(val('c_title')||'inherit')+'!important;}'+P+' summary::after{border-color:'+(val('c_line')||'currentColor')+'!important;}';
 c+=P+' ul.wmtoc-list li::marker{color:'+(val('c_marker')||'inherit')+';}'+P+' a{color:'+(val('c_link')||'#2271b1')+'!important;}'+P+' a:hover{color:'+(val('c_hover')||val('c_link')||'#135e96')+'!important;}';
 st.textContent=c;
}
document.querySelectorAll('.wmtoc-c').forEach(function(i){i.addEventListener('input',upd);});
document.querySelectorAll('.wmtoc-pick').forEach(function(p){p.addEventListener('input',function(){var t=document.querySelector('.wmtoc-c[data-k="'+p.dataset.k+'"]');t.value=p.value;upd();});});
var lw=document.querySelector('[name="wmtoc_settings[line_width]"]');if(lw)lw.addEventListener('input',upd);
upd();
})();
</script>
	<?php
}
