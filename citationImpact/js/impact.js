/* Citation Impact — search, sort, "show more" and chart tooltips. No dependencies. */
(function () {
	function init() {
		var root = document.querySelector('.cimpact');
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
