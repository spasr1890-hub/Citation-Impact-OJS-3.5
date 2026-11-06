/* Citation Impact — theme colours, search, sort, "show more" and chart tooltips. No dependencies. */
(function () {
	/*
	 * Theme colours. The page takes its colours from whatever theme the journal
	 * uses: the accent comes from the plugin setting or the theme's colour option
	 * (data-ci-accent, set by the server), or else from what the theme renders
	 * (link colour, header/navigation, buttons). Text and background follow the
	 * theme too, so dark themes get a dark palette. Same math as
	 * classes/CitationImpactPalette.php.
	 */
	var Palette = (function () {
		function parse(str) {
			if (!str) return null;
			var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec(str.trim());
			if (m) {
				var h = m[1].length === 3 ? m[1].replace(/./g, '$&$&') : m[1];
				return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16), 1];
			}
			m = /rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:[\s,/]+([\d.]+%?))?\s*\)/i.exec(str);
			if (!m) return null;
			var a = m[4] == null ? 1 : (m[4].slice(-1) === '%' ? parseFloat(m[4]) / 100 : parseFloat(m[4]));
			return [+m[1], +m[2], +m[3], a];
		}
		function hex(c) {
			return '#' + c.slice(0, 3).map(function (v) {
				v = Math.max(0, Math.min(255, Math.round(v)));
				return (v < 16 ? '0' : '') + v.toString(16);
			}).join('');
		}
		function mix(a, b, w) { return [a[0] * w + b[0] * (1 - w), a[1] * w + b[1] * (1 - w), a[2] * w + b[2] * (1 - w), 1]; }
		function lum(c) {
			var l = c.slice(0, 3).map(function (v) { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
			return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
		}
		function contrast(a, b) { var x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); }
		function ensure(c, bg, ratio, away) {
			for (var i = 0; i < 12 && contrast(c, bg) < ratio; i++) c = mix(c, away, 0.85);
			return c;
		}
		function colourful(c) { return c && c[3] > 0.5 && Math.max(c[0], c[1], c[2]) - Math.min(c[0], c[1], c[2]) >= 40; }
		var WHITE = [255, 255, 255, 1], BLACK = [0, 0, 0, 1];

		function accentVars(a, surface) {
			var dark = lum(surface) < 0.2, away = dark ? WHITE : BLACK;
			a = ensure(a, surface, 3, away);
			var soft = mix(a, surface, 0.12), v = {
				'--ci-accent': a,
				'--ci-accent-soft': soft,
				'--ci-accent-softer': mix(a, surface, 0.06),
				'--ci-accent-line': mix(a, surface, 0.35),
				'--ci-accent-ink': ensure(a, soft, 4.5, away),
				'--ci-ring-track': mix(a, surface, 0.22)
			};
			for (var i = 0; i <= 8; i++) {
				var c = i <= 4 ? mix(a, surface, 0.45 + 0.55 * i / 4) : mix(a, away, 1 - 0.45 * (i - 4) / 4);
				v['--ci-r' + i] = i === 0 ? ensure(c, surface, 2, away) : c;
			}
			return v;
		}
		function neutralVars(ink, card) {
			var dark = lum(card) < 0.2, away = dark ? WHITE : BLACK;
			if (contrast(ink, card) < 7) ink = dark ? [236, 236, 232, 1] : [27, 27, 26, 1];
			var good = ensure(dark ? [111, 207, 111, 1] : [0, 99, 0, 1], card, 4.5, away), base = mix(ink, card, 0.25);
			return {
				'--ci-card': card,
				'--ci-ink': ink,
				'--ci-ink-2': ensure(mix(ink, card, 0.8), card, 4.5, dark ? WHITE : BLACK),
				'--ci-ink-3': ensure(mix(ink, card, 0.65), card, 4.5, dark ? WHITE : BLACK),
				'--ci-line': mix(ink, card, 0.14),
				'--ci-grid': mix(ink, card, 0.09),
				'--ci-base': base,
				'--ci-zero': base,
				'--ci-track': mix(ink, card, 0.06),
				'--ci-hover': mix(ink, card, 0.03),
				'--ci-chip': mix(ink, card, 0.07),
				'--ci-good': good,
				'--ci-good-soft': mix(good, card, 0.14),
				'--ci-tip-bg': ink,
				'--ci-tip-ink': card
			};
		}
		function bg(el) {
			for (; el && el.nodeType === 1; el = el.parentNode) {
				var c = parse(getComputedStyle(el).backgroundColor);
				if (c && c[3] > 0.5) return c;
			}
			return WHITE;
		}
		/* The theme's own accent, read from what it renders. */
		function detectAccent(host) {
			var probe = document.createElement('a');
			probe.href = '#';
			probe.textContent = '.';
			probe.setAttribute('aria-hidden', 'true');
			probe.style.cssText = 'position:absolute;left:-9999px;top:0';
			host.appendChild(probe);
			var link = parse(getComputedStyle(probe).color);
			host.removeChild(probe);
			if (colourful(link)) return link;
			var sel = ['.pkp_structure_head', '.pkp_navigation_primary_wrapper', '.pkp_site_nav_menu', 'header .navbar', '.navbar',
				'.site-header', 'header', 'nav', '.btn-primary', '.cmp_button', 'button[type="submit"]'];
			for (var i = 0; i < sel.length; i++) {
				var el = document.querySelector(sel[i]);
				if (!el) continue;
				var c = parse(getComputedStyle(el).backgroundColor);
				if (colourful(c)) return c;
				c = parse(getComputedStyle(el).borderTopColor);
				if (colourful(c) && parseFloat(getComputedStyle(el).borderTopWidth) >= 2) return c;
			}
			return null;
		}
		function apply(root) {
			try {
				var host = root.parentNode || document.body;
				var surface = bg(host);
				var dark = lum(surface) < 0.2;
				var card = dark ? mix(WHITE, surface, 0.07) : (lum(surface) > 0.6 ? WHITE : mix(WHITE, surface, 0.85));
				var ink = parse(getComputedStyle(host).color) || BLACK;
				if (ink[3] < 1) ink = mix(ink, surface, ink[3]);
				var accent = parse(root.getAttribute('data-ci-accent')) || detectAccent(host) ||
					parse(root.getAttribute('data-ci-fallback')) || parse('#2a78d6');
				var vars = neutralVars(ink, card), av = accentVars(accent, card), k;
				for (k in av) vars[k] = av[k];
				for (k in vars) root.style.setProperty(k, hex(vars[k]));
				root.classList.toggle('cimpact-dark', dark);
			} catch (e) { /* keep the server/CSS colours */ }
		}
		return {apply: apply};
	})();

	function init() {
		var root = document.querySelector('.cimpact');
		if (root) Palette.apply(root);
		var dataEl = document.getElementById('cimpact-data');
		if (!root || !dataEl) return;
		var data;
		try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

		var tag = data.locale === 'id' ? 'id-ID' : 'en-US';
		var nf = new Intl.NumberFormat(tag);
		var nf1 = new Intl.NumberFormat(tag, {minimumFractionDigits: 1, maximumFractionDigits: 1});
		var ROWS = data.rows || [], PER = data.perPage || 25, shown = PER, view = ROWS.slice();
		var tbody = root.querySelector('.cimpact-rows'), q = root.querySelector('.cimpact-q'), sortSel = root.querySelector('.cimpact-sort');
		var yearSel = root.querySelector('.cimpact-years'), win = data.window || [0, 9999];
		var more = root.querySelector('.cimpact-more'), count = root.querySelector('.cimpact-count'), noMatch = root.querySelector('.cimpact-nomatch');

		function esc(s) {
			return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
				return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c];
			});
		}
		function num(v, one) { return v == null ? '<span class="cimpact-muted">–</span>' : (one ? nf1 : nf).format(v); }
		var MAX = Math.max(1, data.max || 0);
		function cites(v) {
			if (v == null) return '<span class="cimpact-muted">–</span>';
			var w = Math.max(2, v / MAX * 100);
			return '<span class="cimpact-cbar"><b>' + nf.format(v) + '</b><i style="width:' + w.toFixed(1) + '%"></i></span>';
		}
		function fcr(v) {
			if (v == null) return '<span class="cimpact-muted">–</span>';
			return '<span class="cimpact-fcr' + (v >= 1 ? ' is-high' : '') + '">' + nf1.format(v) + '</span>';
		}
		function row(r, i) {
			var doi = encodeURI(r.d);
			return '<tr><td class="cimpact-rk">' + (i + 1) + '</td><td><a class="cimpact-t" href="' + esc(r.u) + '">' + esc(r.t) + '</a>' +
				'<div class="cimpact-a">' + esc(r.a) + '</div>' +
				'<div class="cimpact-s"><a href="https://doi.org/' + esc(doi) + '" target="_blank" rel="noopener">doi:' + esc(r.d) + '</a> · ' +
				'<a href="https://badge.dimensions.ai/details/doi/' + esc(doi) + '" target="_blank" rel="noopener">' + esc(data.i18n.details) + '</a></div></td>' +
				'<td class="cimpact-hide-sm">' + esc(r.y || '') + '</td>' +
				'<td class="cimpact-cc">' + cites(r.c) + '</td>' +
				'<td class="cimpact-hide-sm">' + num(r.r) + '</td>' +
				'<td class="cimpact-hide-sm">' + fcr(r.f) + '</td></tr>';
		}
		function shownText(a, b) {
			return String(data.i18n.shown || '').split('{$shown}').join(nf.format(a)).split('{$total}').join(nf.format(b));
		}
		function draw() {
			var slice = view.slice(0, shown);
			tbody.innerHTML = slice.map(row).join('');
			if (noMatch) noMatch.hidden = view.length > 0;
			if (more) more.hidden = view.length <= shown;
			if (count) count.textContent = shownText(slice.length, view.length);
		}
		function apply() {
			var term = q ? q.value.trim().toLowerCase() : '', key = sortSel ? sortSel.value : 'c';
			var onlyWindow = yearSel && yearSel.value === 'window';
			view = ROWS.filter(function (r) {
				if (onlyWindow && !(r.y >= win[0] && r.y <= win[1])) return false;
				return !term || (r.t + ' ' + r.a + ' ' + r.d).toLowerCase().indexOf(term) !== -1;
			});
			view.sort(function (a, b) {
				if (key === 'y') return (b.dt || '').localeCompare(a.dt || '');
				var f = key === 'r' ? 'r' : 'c';
				return ((b[f] || 0) - (a[f] || 0)) || ((b.c || 0) - (a.c || 0)) || (b.dt || '').localeCompare(a.dt || '');
			});
			draw();
		}
		if (tbody) {
			var timer;
			if (q) q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { shown = PER; apply(); }, 150); });
			if (sortSel) sortSel.addEventListener('change', function () { shown = PER; apply(); });
			if (yearSel) yearSel.addEventListener('change', function () { shown = PER; apply(); });
			if (more) more.addEventListener('click', function () { shown += PER; draw(); });
		}

		// Tabs (arrow keys move between them).
		var tabs = Array.prototype.slice.call(root.querySelectorAll('.cimpact-tabs [role="tab"]'));
		function select(tab) {
			tabs.forEach(function (t) {
				var on = t === tab;
				t.setAttribute('aria-selected', on ? 'true' : 'false');
				t.tabIndex = on ? 0 : -1;
				var panel = document.getElementById(t.getAttribute('aria-controls'));
				if (panel) panel.hidden = !on;
			});
		}
		tabs.forEach(function (t, i) {
			t.addEventListener('click', function () { select(t); });
			t.addEventListener('keydown', function (e) {
				var k = e.key, n = null;
				if (k === 'ArrowRight') n = tabs[(i + 1) % tabs.length];
				else if (k === 'ArrowLeft') n = tabs[(i - 1 + tabs.length) % tabs.length];
				if (n) { e.preventDefault(); select(n); n.focus(); }
			});
		});

		// Chart tooltips (mouse and keyboard).
		var box = root.querySelector('.cimpact-chartbox'), tip = box && box.querySelector('.cimpact-tip');
		if (box && tip) {
			Array.prototype.forEach.call(box.querySelectorAll('.ci-bar'), function (g) {
				var title = g.querySelector('title');
				if (title) title.parentNode.removeChild(title);
				function show() {
					var hit = g.querySelector('.ci-hit').getBoundingClientRect(), mark = g.querySelector('.ci-mark'), o = box.getBoundingClientRect();
					var top = (mark ? mark.getBoundingClientRect().top : hit.bottom) - o.top - 6;
					var left = Math.min(Math.max(hit.left + hit.width / 2 - o.left, 80), o.width - 80);
					tip.textContent = g.getAttribute('data-tip');
					tip.style.left = left + 'px';
					tip.style.top = Math.max(top, 18) + 'px';
					tip.style.opacity = 1;
				}
				function hide() { tip.style.opacity = 0; }
				g.addEventListener('mouseenter', show); g.addEventListener('focus', show);
				g.addEventListener('mouseleave', hide); g.addEventListener('blur', hide);
			});
		}
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
