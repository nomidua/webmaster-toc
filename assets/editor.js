/* WebMaster TOC — плагін TinyMCE: кнопки «TOC» / «Без TOC», плашка мітки, статус у нижній смузі */
(function () {
	'use strict';
	var D = window.wmtocEditor || {}, T = D.i18n || {};
	var TAGS = ['wm_toc', 'ir_toc', 'ez-toc', 'toc'];
	var MARK_RE = /\[(wm_toc|ir_toc|ez-toc|toc)(\s[^\]]*)?\]/i;

	// Плашка замість шорткоду у візуальному редакторі
	if (window.wp && wp.mce && wp.mce.views) {
		TAGS.forEach(function (tag) {
			wp.mce.views.register(tag, {
				initialize: function () {
					this.render('<div class="wmtoc-ph"><span class="wmtoc-ph-ico"></span><span class="wmtoc-ph-txt">' + T.ph + '</span><span class="wmtoc-ph-x" title="' + T.phRemove + '">✕</span></div>');
				},
				edit: function () {}
			});
		});
	}

	// Статус змісту за тими ж правилами, що й на сайті
	function stripTags(html) {
		var d = document.createElement('div');
		d.innerHTML = html;
		return (d.textContent || '').replace(/[\s ]+/g, ' ').trim();
	}
	function maskRe(m) {
		return new RegExp('^' + m.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$', 'iu');
	}
	var masks = (D.masks || []).map(maskRe);
	function excluded(t) {
		t = t.replace(/^[\s.,:;!?…—–-]+|[\s.,:;!?…—–-]+$/g, '');
		if (!t || t.split(/\s+/).length > D.maxWords) return false;
		return masks.some(function (r) { return r.test(t); });
	}
	function fmt(n) {
		try { return n.toLocaleString(D.locale); } catch (e) { return String(n); }
	}
	function status(html, off) {
		var p = T.st + ': ';
		if (D.paused) return p + T.stPaused;
		if (off) return p + T.stOff;
		var n = 0, m, re = /<h2\b[^>]*>([\s\S]*?)<\/h2>/gi;
		while ((m = re.exec(html))) {
			var t = stripTags(m[1]);
			if (t && !excluded(t)) n++;
		}
		if (MARK_RE.test(html)) return n >= 2 ? p + T.stManual + ' (' + T.stHeads + ' ' + n + ' ' + T.stPcs + ')' : p + T.stNo + ' (' + T.stNeed2 + ')';
		var chars = stripTags(html.replace(/\[\/?[\w-]+[^\]]*\]/g, ' ')).length;
		if (n < D.minH2) return p + T.stNo + ' (' + T.stHeads + ' ' + n + ' ' + T.stOf + ' ' + D.minH2 + ' ' + T.stPcs + ')';
		if (chars < D.minChars) return p + T.stNo + ' (' + T.stSize + ' ' + fmt(chars) + ' ' + T.stOf + ' ' + fmt(D.minChars) + ')';
		return p + T.stYes + ' (' + T.stHeads + ' ' + n + ' ' + T.stPcs + ', ' + T.stSize + ' ' + fmt(chars) + ')';
	}


	tinymce.PluginManager.add('wmtoc', function (editor) {
		if (editor.id !== 'content') return;
		var offInput, offBtn, stEl, box, timer;

		function isOff() { return offInput && offInput.value === '1'; }
		function visual() { return !editor.isHidden(); }
		function content() {
			var ta = document.getElementById('content');
			return visual() ? editor.getContent() : (ta ? ta.value : '');
		}
		function update() {
			clearTimeout(timer);
			timer = setTimeout(function () {
				if (stEl) stEl.textContent = status(content(), isOff());
			}, 300);
		}

		// Мітка: одна на статтю, окремим абзацом після поточного блоку
		function insertMark() {
			editor.focus();
			var body = editor.getBody();
			editor.dom.select('.wpview').forEach(function (v) {
				if (TAGS.indexOf(v.getAttribute('data-wpview-type')) > -1) editor.dom.remove(v);
			});
			var block = editor.selection.getNode();
			while (block && block.parentNode && block.parentNode !== body) block = block.parentNode;
			var p = editor.dom.create('p', {}, '<br data-mce-bogus="1">');
			if (block && block !== body && block.parentNode === body) editor.dom.insertAfter(p, block);
			else body.insertBefore(p, body.firstChild);
			editor.selection.setCursorLocation(p, 0);
			editor.insertContent('[wm_toc]');
			if (wp.mce && wp.mce.views) wp.mce.views.render();
			editor.undoManager.add();
			editor.nodeChanged();
			update();
		}

		function toggleOff() {
			if (!offInput) return;
			offInput.value = isOff() ? '0' : '1';
			paintOff();
			if (editor.setDirty) editor.setDirty(true); else editor.isNotDirty = false;
			update();
		}
		function paintOff() {
			if (!offBtn) return;
			offBtn.classList.toggle('is-active', isOff());
			offBtn.setAttribute('aria-pressed', isOff() ? 'true' : 'false');
		}

		// Кнопки справа в останньому видимому ряду панелі
		function makeBox() {
			box = document.createElement('div');
			box.className = 'wmtoc-tb';
			box.innerHTML = '<button type="button" class="wmtoc-btn wmtoc-btn-toc" title="' + T.tocTitle + '"><svg class="wmtoc-ico-list" viewBox="0 0 20 20" aria-hidden="true"><path d="M7.5 5.5h9M7.5 10h9M7.5 14.5h9"/><circle cx="3.8" cy="5.5" r="1.1"/><circle cx="3.8" cy="10" r="1.1"/><circle cx="3.8" cy="14.5" r="1.1"/></svg><span class="wmtoc-btn-txt">' + T.toc + '</span></button>'
				+ '<button type="button" class="wmtoc-btn wmtoc-btn-off" aria-pressed="false" title="' + T.offTitle + '"><svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M5 15 15 5"/></svg><span class="wmtoc-btn-txt">' + T.off + '</span></button>';
			box.addEventListener('mousedown', function (e) { e.preventDefault(); });
			box.querySelector('.wmtoc-btn-toc').addEventListener('click', function (e) { e.preventDefault(); insertMark(); });
			offBtn = box.querySelector('.wmtoc-btn-off');
			offBtn.addEventListener('click', function (e) { e.preventDefault(); toggleOff(); });
		}
		function place() {
			if (!box) return;
			var rows = editor.getContainer().querySelectorAll('.mce-toolbar-grp .mce-toolbar'), row = null;
			for (var i = rows.length - 1; i >= 0; i--) {
				if (rows[i].offsetParent !== null && getComputedStyle(rows[i]).display !== 'none') { row = rows[i]; break; }
			}
			row = row || rows[rows.length - 1];
			var bodyEl = row && (row.querySelector('.mce-container-body') || row);
			if (bodyEl && box.parentNode !== bodyEl) bodyEl.insertBefore(box, bodyEl.firstChild);
		}

		editor.on('init', function () {
			offInput = document.getElementById('wmtoc-off');
			makeBox();
			place();
			paintOff();
			var wc = document.getElementById('wp-word-count') || document.querySelector('#post-status-info td');
			if (wc) {
				stEl = document.createElement('span');
				stEl.className = 'wmtoc-status';
				wc.appendChild(stEl);
			}
			var ta = document.getElementById('content');
			if (ta) ta.addEventListener('input', update);
			update();
		});
		editor.on('wp-toolbar-toggle', function () { setTimeout(place, 0); });
		editor.on('show', function () { setTimeout(place, 0); update(); });
		editor.on('keyup change setcontent undo redo', update);

		// ✕ на плашці прибирає мітку
		editor.on('click', function (e) {
			var x = e.target && e.target.closest ? e.target.closest('.wmtoc-ph-x') : null;
			if (!x) return;
			var v = editor.dom.getParent(x, '.wpview');
			if (v) {
				e.preventDefault();
				editor.undoManager.transact(function () { editor.dom.remove(v); });
				editor.nodeChanged();
				update();
			}
		});
	});
})();
