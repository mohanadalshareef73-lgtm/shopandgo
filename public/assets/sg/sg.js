/* Shop & Go — أدوات الواجهة: رسوم بيانية صغيرة وتفاعلات عامة */
(function () {
  const SG = window.SG = {};
  SG.fmt = n => Math.round(Number(n) || 0).toLocaleString('en-US');
  /* نسخ نص بزر: <button data-copy="النص"> — يعمل أيضاً على http عبر textarea */
  SG.copy = async (text, btn) => {
    let ok = false;
    try { await navigator.clipboard.writeText(text); ok = true; } catch (e) {
      const t = document.createElement('textarea'); t.value = text; t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select();
      try { ok = document.execCommand('copy'); } catch (e2) {} t.remove();
    }
    if (btn) { const o = btn.dataset.label || btn.innerHTML; btn.dataset.label = o; btn.innerHTML = ok ? '✓ نُسخ' : 'تعذّر النسخ'; setTimeout(() => btn.innerHTML = o, 1400); }
    return ok;
  };
  document.addEventListener('click', e => { const b = e.target.closest('[data-copy]'); if (!b) return; e.preventDefault(); e.stopPropagation(); SG.copy(b.dataset.copy, b); });
  SG.fmt2 = n => (Math.round((Number(n) || 0) * 100) / 100).toLocaleString('en-US');
  const tip = document.createElement('div');
  tip.className = 'sg-tip';
  document.addEventListener('DOMContentLoaded', () => document.body.appendChild(tip));
  SG.tip = (e, html) => { tip.innerHTML = html; tip.style.left = e.clientX + 12 + 'px'; tip.style.top = e.clientY - 34 + 'px'; tip.style.opacity = 1; };
  SG.hideTip = () => { tip.style.opacity = 0; };

  /* خط صغير (sparkline) أو أعمدة صغيرة */
  SG.spark = function (svg) {
    const vals = JSON.parse(svg.dataset.spark || '[]');
    const color = svg.dataset.color || '#2a78d6';
    const kind = svg.dataset.kind || 'line';
    const w = 120, h = 40;
    svg.setAttribute('viewBox', `0 0 ${w} ${h}`);
    svg.setAttribute('preserveAspectRatio', 'none');
    if (!vals.length) { svg.innerHTML = ''; return; }
    const max = Math.max(...vals), min = kind === 'bar' ? 0 : Math.min(...vals), rng = (max - min) || 1;
    if (kind === 'bar') {
      const bw = w / vals.length;
      // اليمين = الأقدم (RTL)، اليسار = الأحدث
      svg.innerHTML = vals.map((v, i) => { const bh = Math.max(2, (v - min) / rng * (h - 2)); const x = w - (i + 1) * bw; return `<rect x="${x + 1}" y="${h - bh}" width="${Math.max(1, bw - 2)}" height="${bh}" rx="1.5" fill="${color}" opacity="${i === vals.length - 1 ? 1 : .45}"/>`; }).join('');
      return;
    }
    const pts = vals.map((v, i) => [w - i / Math.max(1, vals.length - 1) * w, h - 4 - (v - min) / rng * (h - 8)]);
    const d = pts.map((p, i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const last = pts[pts.length - 1];
    svg.innerHTML = `<path d="${d} L0 ${h} L${w} ${h}Z" fill="${color}" opacity=".10"/><path d="${d}" fill="none" stroke="${color}" stroke-width="2" stroke-linejoin="round" vector-effect="non-scaling-stroke"/><circle cx="${last[0]}" cy="${last[1]}" r="3" fill="${color}"/>`;
  };

  /* خط مقارنة: الفترة الحالية (أزرق) والسابقة (منقط رمادي) */
  SG.lineCompare = function (svg) {
    const o = JSON.parse(svg.dataset.chart);
    const W = 760, H = +(svg.dataset.h || 250), L = 12, R = 66, T = 12, B = 28, iw = W - L - R, ih = H - T - B;
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    const n = o.labels.length, cur = o.cur, prev = o.prev || [];
    const max = Math.max(1, ...cur, ...prev) * 1.1;
    const x = i => W - R - i / Math.max(1, n - 1) * iw, y = v => T + ih - v / max * ih;
    let g = '';
    for (let k = 0; k <= 4; k++) { const v = max / 4 * k, yy = y(v); g += `<line x1="${L}" x2="${W - R}" y1="${yy}" y2="${yy}" stroke="#f0f2f6"/><text x="${W - R + 12}" y="${yy + 4}">${SG.fmt(v)}</text>`; }
    const step = Math.ceil(n / 12);
    o.labels.forEach((t, i) => { if (i % step === 0) g += `<text x="${x(i)}" y="${H - 8}" text-anchor="middle">${t}</text>`; });
    const path = a => a.map((v, i) => (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(v).toFixed(1)).join(' ');
    const curN = o.curCount ?? cur.length;
    const curShown = cur.slice(0, curN);
    if (prev.length) g += `<path d="${path(prev)}" fill="none" stroke="#b7bccb" stroke-width="2" stroke-dasharray="5 4"/>`;
    if (curShown.length) {
      g += `<path d="${path(curShown)} L${x(curShown.length - 1)} ${T + ih} L${x(0)} ${T + ih}Z" fill="#2a78d6" opacity=".08"/>`;
      g += `<path d="${path(curShown)}" fill="none" stroke="#2a78d6" stroke-width="2.2" stroke-linejoin="round"/>`;
    }
    g += `<line class="xh" y1="${T}" y2="${T + ih}" stroke="#8a90a3" stroke-dasharray="3 3" opacity="0"/><circle class="xd" r="4.5" fill="#2a78d6" stroke="#fff" stroke-width="2" opacity="0"/>`;
    svg.innerHTML = g;
    svg.onmousemove = e => {
      const r = svg.getBoundingClientRect(), px = (e.clientX - r.left) / r.width * W;
      const i = Math.max(0, Math.min(n - 1, Math.round((W - R - px) / iw * (n - 1))));
      const xh = svg.querySelector('.xh'), xd = svg.querySelector('.xd');
      xh.setAttribute('x1', x(i)); xh.setAttribute('x2', x(i)); xh.setAttribute('opacity', 1);
      if (i < curShown.length) { xd.setAttribute('cx', x(i)); xd.setAttribute('cy', y(cur[i])); xd.setAttribute('opacity', 1); } else xd.setAttribute('opacity', 0);
      SG.tip(e, `${o.labels[i]} · ${o.curName}: <b>${i < curShown.length ? SG.fmt(cur[i]) : '—'}</b>` + (prev.length ? ` · ${o.prevName}: ${SG.fmt(prev[i] || 0)}` : ''));
    };
    svg.onmouseleave = () => { SG.hideTip(); svg.querySelector('.xh').setAttribute('opacity', 0); svg.querySelector('.xd').setAttribute('opacity', 0); };
  };

  /* أعمدة: values + labels (يدعم القيم السالبة) */
  SG.bars = function (svg) {
    const o = JSON.parse(svg.dataset.chart);
    const W = +(svg.dataset.w || 760), H = +(svg.dataset.h || 230), R = 56, T = 10, B = 26, iw = W - 12 - R, ih = H - T - B;
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    const vals = o.values, n = vals.length;
    const max = Math.max(1, ...vals) * 1.15, min = Math.min(0, ...vals) * 1.2, rng = max - min;
    const y = v => T + (max - v) / rng * ih, bw = iw / Math.max(1, n);
    let g = '';
    for (let k = 0; k <= 4; k++) { const v = min + rng / 4 * k; g += `<line x1="12" x2="${W - R}" y1="${y(v)}" y2="${y(v)}" stroke="#f0f2f6"/><text x="${W - R + 10}" y="${y(v) + 4}">${SG.fmt(v)}</text>`; }
    if (min < 0) g += `<line x1="12" x2="${W - R}" y1="${y(0)}" y2="${y(0)}" stroke="#c9cdd8"/>`;
    const step = Math.ceil(n / 16);
    const pad = Math.min(8, bw * .2);
    vals.forEach((v, i) => {
      const xx = W - R - (i + 1) * bw + pad, top = v >= 0 ? y(v) : y(0), h = Math.abs(y(v) - y(0));
      const col = v < 0 ? '#c4352f' : (o.highlightLast && i === n - 1 ? (o.color || '#2a78d6') : (o.soft || o.color || '#2a78d6'));
      g += `<rect class="bb" data-i="${i}" x="${xx}" y="${top}" width="${Math.max(2, bw - pad * 2)}" height="${Math.max(2, h)}" rx="4" fill="${col}"/>`;
      if (i % step === 0) g += `<text x="${xx + (bw - pad * 2) / 2}" y="${H - 8}" text-anchor="middle" style="font-family:InterNum,Somar">${o.labels[i]}</text>`;
    });
    svg.innerHTML = g;
    svg.querySelectorAll('.bb').forEach(b => {
      b.onmousemove = e => { const i = +b.dataset.i; SG.tip(e, `${o.labels[i]} · <b>${SG.fmt(vals[i])}</b> ${o.unit || ''}`); };
      b.onmouseleave = SG.hideTip;
    });
  };

  SG.init = function (root) {
    root = root || document;
    root.querySelectorAll('svg[data-spark]').forEach(SG.spark);
    root.querySelectorAll('svg[data-line]').forEach(SG.lineCompare);
    root.querySelectorAll('svg[data-bars]').forEach(SG.bars);
  };

  document.addEventListener('DOMContentLoaded', () => {
    SG.init();
    // القوائم المنسدلة
    document.addEventListener('click', e => {
      const t = e.target.closest('[data-dd]');
      document.querySelectorAll('.dropdown.open').forEach(d => { if (!t || d !== t.closest('.dropdown')) d.classList.remove('open'); });
      if (t) { t.closest('.dropdown').classList.toggle('open'); e.preventDefault(); }
      const m = e.target.closest('[data-modal]');
      if (m) { document.getElementById(m.dataset.modal).classList.add('open'); e.preventDefault(); }
      if (e.target.classList.contains('modal-bg') || e.target.closest('[data-close]')) { const bg = e.target.closest('.modal-bg'); if (bg) bg.classList.remove('open'); }
      const b = e.target.closest('.sg-burger');
      if (b) document.querySelector('.sg-side').classList.toggle('open');
    });
    // تأكيد قبل الإرسال
    document.querySelectorAll('form[data-confirm]').forEach(f => f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
    // إرسال تلقائي عند التغيير
    document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form.submit()));
    // منع الإرسال المزدوج
    document.querySelectorAll('form').forEach(f => f.addEventListener('submit', () => { setTimeout(() => f.querySelectorAll('button[type=submit],button:not([type])').forEach(b => b.disabled = true), 10); }));
  });
})();
