<?php
// public_html/ainews/index.php
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>The ONE · AI NEWS</title>
<!-- Favicons -->
<link rel="icon" type="image/x-icon" href="/ainews/favicon.ico">
<link rel="icon" type="image/svg+xml" href="/ainews/favicon.svg">


<style>
  :root{
    --bg:#f3f4f6; --card:#ffffff; --border:#e5e7eb; --fg:#0f172a; --muted:#6b7280;
    --link:#1d4ed8; --brand:#f97316; --chip-bg:#fff7ed; --chip-bd:#f59e0b; --chip-fg:#9a3412;
    --toggle-bg:#f3f4f6; --toggle-on:#111827; --toggle-off:#9ca3af;
    --btn-bg:#ffffff; --btn-bd:#d1d5db; --btn-fg:#111827; --btn-bg-dis:#f3f4f6; --btn-bd-dis:#e5e7eb; --btn-fg-dis:#9ca3af;
  }
  html,body{margin:0;background:var(--bg);color:var(--fg);
    font:14px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Inter,Helvetica,Arial,sans-serif;
    -webkit-font-smoothing:antialiased; text-rendering:optimizeLegibility;
  }
  .topbar{display:flex;align-items:center;gap:14px;padding:14px 22px;background:var(--card);border-bottom:1px solid var(--border);}
  .one-badge{width:40px;height:40px;border-radius:10px;background:var(--brand);color:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:800;line-height:1;letter-spacing:.2px;box-shadow:0 1px 2px rgba(0,0,0,.06);}
  .one-badge .t{font-size:10px;opacity:.95;margin-top:1px;}
  .one-badge .o{font-size:12px;margin-top:1px;}
  .title{font-weight:800;font-size:18px;letter-spacing:.2px;}
  .subtitle{color:var(--muted);font-size:12px;margin-top:2px;}

  .filters{display:flex;flex-wrap:wrap;gap:12px;padding:14px 22px;background:var(--card);border-bottom:1px solid var(--border);align-items:flex-end;}
  .f{display:flex;flex-direction:column;gap:4px;}
  .f label{font-size:11px;color:var(--muted);}
  .f select{appearance:none;background:#fff;color:var(--fg);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:14px;}
  .mode{display:flex;gap:4px;background:var(--toggle-bg);border:1px solid var(--border);border-radius:10px;padding:4px;}
  .mode button{border:0;background:transparent;padding:8px 10px;border-radius:8px;font-size:13px;color:var(--toggle-off);cursor:pointer;}
  .mode button.active{background:#fff;color:var(--toggle-on);box-shadow:0 1px 2px rgba(0,0,0,.06);}

  main{max-width:880px;margin:0 auto;padding:18px; position:relative; min-height:60vh;}
  .card{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:14px 16px;margin:10px 0;box-shadow:0 1px 2px rgba(0,0,0,.04);}
  .row{display:flex;align-items:flex-start;gap:12px;}
  .chip{flex:0 0 auto;align-self:flex-start;font-size:12px;font-weight:700;color:var(--chip-fg);background:var(--chip-bg);border:1.5px solid var(--chip-bd);border-radius:999px;padding:6px 10px;line-height:1;}
  .titleline{font-size:16px;font-weight:700;margin:2px 0 6px;}
  .titleline a{color:inherit;text-decoration:none;}
  .titleline a:hover{color:var(--link);text-decoration:underline;}
  .meta{font-size:12px;color:var(--muted);display:flex;gap:12px;flex-wrap:wrap;}

  /* Pager */
  .pager{display:flex;gap:10px;justify-content:flex-end;margin:16px 2px;}
  .btn{
    appearance:none; border:1px solid var(--btn-bd); background:var(--btn-bg); color:var(--btn-fg);
    padding:8px 12px; border-radius:8px; font-weight:600; cursor:pointer;
    display:inline-flex; align-items:center; gap:8px;
  }
  .btn:disabled{background:var(--btn-bg-dis); border-color:var(--btn-bd-dis); color:var(--btn-fg-dis); cursor:not-allowed;}
  .btn .arr{font-weight:800;}
  .pager-info{margin-right:auto; align-self:center; color:var(--muted); font-size:12px;}

  footer{text-align:center;padding:18px;color:var(--muted);font-size:12px;}

.one-badge { 
  text-decoration:none; 
  color:#fff; 
}
.one-badge:visited { 
  color:#fff; 
}


</style>
</head>
<body>

  <header class="topbar">
<a href="/ainews/" class="one-badge" aria-label="The ONE">
  <div class="t">The</div><div class="o">ONE</div>
</a>
    <div>
      <div class="title">AI NEWS</div>
      <div class="subtitle">A calm, focused reader</div>
    </div>
  </header>

  <section class="filters" aria-label="Filters">
    <div class="f">
      <label for="sort">Sort by</label>
      <select id="sort">
        <option value="published_at_desc">Newest → Oldest</option>
        <option value="published_at_asc">Oldest → Newest</option>
      </select>
    </div>
    <div class="f">
      <label for="since">Search for</label>
      <select id="since">
        <option value="24h">Last 24h</option>
        <option value="7d">Past Week</option>
        <option value="30d">Past Month</option>
        <option value="365d">Past Year</option>
      </select>
    </div>
    <div class="f">
      <label for="per_page">Results per page</label>
      <select id="per_page">
        <option value="20">20</option>
        <option value="30">30</option>
        <option value="50">50</option>
        <option value="100">100</option>
      </select>
    </div>

    <!-- Mode toggle -->
    <div class="f" style="margin-left:auto">
      <label>Mode</label>
      <div class="mode" role="tablist" aria-label="Filter mode">
        <button id="modeOriginal" role="tab">Original</button>
        <button id="modeSimple" role="tab">Simple (AI/LLM/ChatGPT)</button>
      </div>
    </div>
  </section>

  <main>
    <div id="list"></div>

    <!-- Pager -->
    <div class="pager" id="pager" hidden>
      <div class="pager-info" id="pagerInfo"></div>
      <button class="btn" id="prevBtn"><span class="arr">◀</span> Prev</button>
      <button class="btn" id="nextBtn">Next <span class="arr">▶</span></button>
    </div>
  </main>

  <footer>Cached ~120s • Endpoint: <code>/ainews/api/articles.php</code></footer>

<script>
(function(){
  const API='/ainews/api/articles.php';
  const $list=document.getElementById('list');
  const $since=document.getElementById('since');
  const $sort=document.getElementById('sort');
  const $per=document.getElementById('per_page');
  const $modeOriginal=document.getElementById('modeOriginal');
  const $modeSimple=document.getElementById('modeSimple');

  const $pager=document.getElementById('pager');
  const $prev=document.getElementById('prevBtn');
  const $next=document.getElementById('nextBtn');
  const $pagerInfo=document.getElementById('pagerInfo');

  const sp=new URLSearchParams(location.search);
  const validPer=[20,30,50,100];
  let state={
    since: sp.get('since') || '24h',
    sort: sp.get('sort') || 'published_at_desc',
    per_page: validPer.includes(Number(sp.get('per_page'))) ? Number(sp.get('per_page')) : 30,
    page: Math.max(1, Number(sp.get('page') || 1)),
    mode: (sp.get('mode') || 'original').toLowerCase()==='simple'?'simple':'original',
    total: 0, pages: 1
  };
  // init controls
  $since.value=state.since; $sort.value=state.sort; $per.value=String(state.per_page);
  updateModeButtons();

  // events
  $since.onchange=()=>{ state.since=$since.value; state.page=1; syncURL(); load(); };
  $sort.onchange =()=>{ state.sort=$sort.value;   state.page=1; syncURL(); load(); };
  $per.onchange  =()=>{ state.per_page=Number($per.value); state.page=1; syncURL(); load(); };
  $modeOriginal.onclick=()=>{ state.mode='original'; state.page=1; updateModeButtons(); syncURL(); load(); };
  $modeSimple.onclick  =()=>{ state.mode='simple';   state.page=1; updateModeButtons(); syncURL(); load(); };

  $prev.onclick=()=>{ if(state.page>1){ state.page--; syncURL(); load(); window.scrollTo({top:0, behavior:'smooth'});} };
  $next.onclick=()=>{ if(state.page<state.pages){ state.page++; syncURL(); load(); window.scrollTo({top:0, behavior:'smooth'});} };

  function updateModeButtons(){
    $modeOriginal.classList.toggle('active', state.mode==='original');
    $modeSimple.classList.toggle('active', state.mode==='simple');
    $modeOriginal.setAttribute('aria-selected', state.mode==='original'?'true':'false');
    $modeSimple.setAttribute('aria-selected', state.mode==='simple'?'true':'false');
  }

  function syncURL(){
    const p=new URLSearchParams();
    if(state.since!=='24h') p.set('since',state.since);
    if(state.sort!=='published_at_desc') p.set('sort',state.sort);
    if(state.per_page!==30) p.set('per_page',state.per_page);
    if(state.page!==1) p.set('page',state.page);
    if(state.mode!=='original') p.set('mode',state.mode);
    const qs=p.toString();
    history.replaceState(null,'', qs?('?'+qs):'');
  }

  async function load(){
    $list.innerHTML='<p style="color:#6b7280;margin:8px 2px;">Loading…</p>';
    const p=new URLSearchParams({
      since:state.since, sort:state.sort, per_page:String(state.per_page), page:String(state.page), mode:state.mode
    });
    const res=await fetch(API+'?'+p.toString());
    if(!res.ok){ $list.innerHTML='<p>Failed to load.</p>'; $pager.hidden=true; return; }
    const data=await res.json();
    state.total = Number(data.total || 0);
    state.pages = Math.max(1, Math.ceil(state.total / state.per_page));
    render(data.items||[]);
    updatePager();
  }

  function updatePager(){
    if(state.total<=state.per_page){ $pager.hidden=true; return; }
    $pager.hidden=false;
    $prev.disabled = state.page<=1;
    $next.disabled = state.page>=state.pages;
    const from=(state.page-1)*state.per_page+1;
    const to=Math.min(state.page*state.per_page, state.total);
    $pagerInfo.textContent = `Showing ${from}-${to} of ${state.total}`;
  }

  function render(items){
    if(!items.length){ $list.innerHTML='<p style="color:#6b7280;">No results.</p>'; return; }
    const frag=document.createDocumentFragment();
    for(const it of items){
      const card=div('card');
      const row =div('row');

      const chip=div('chip', hostFrom(it.url) || (it.source_name||it.source||''));
      row.appendChild(chip);

      const body=div();
      const t=div('titleline');
      const a=document.createElement('a');
      a.href=it.url; a.target='_blank'; a.rel='noopener'; a.textContent=it.title;
      t.appendChild(a);

      const m=div('meta');
      const bits=[(it.source_name||it.source||''), timeAgo(it.published_at)];
      m.textContent=bits.join(' • ');
      if(it.comments_url){
        m.appendChild(document.createTextNode(' • '));
        const c=document.createElement('a'); c.href=it.comments_url; c.target='_blank'; c.rel='noopener'; c.textContent='comments';
        m.appendChild(c);
      }

      body.appendChild(t); body.appendChild(m);
      row.appendChild(body);
      card.appendChild(row);
      frag.appendChild(card);
    }
    $list.innerHTML=''; $list.appendChild(frag);
  }

  // helpers
  function div(cls, text){ const n=document.createElement('div'); if(cls) n.className=cls; if(text!=null) n.textContent=text; return n; }
  function hostFrom(url){ try{ const u=new URL(url); return u.hostname.replace(/^www\./,''); }catch{return '';} }
  function timeAgo(iso){
    const t=new Date(iso).getTime(), s=Math.max(0,(Date.now()-t)/1000);
    if(s<60) return Math.floor(s)+'s ago'; const m=s/60,h=m/60,d=h/24;
    if(m<60) return Math.floor(m)+'m ago';
    if(h<24) return Math.floor(h)+'h ago';
    if(d<7)  return Math.floor(d)+'d ago';
    return Math.floor(d/7)+'w ago';
  }

  // initial
  load();
})();
</script>
</body>
</html>
