(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const $ = (s, root=document) => root.querySelector(s);
  const $$ = (s, root=document) => [...root.querySelectorAll(s)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  let paused = reduce.matches;
  const motionButton = $('[data-motion]');
  function syncMotion(){ document.body.classList.toggle('motion-paused', paused); if(paused) $$('[data-tilt]').forEach(el=>el.style.transform=''); if(motionButton){motionButton.setAttribute('aria-pressed',String(paused));motionButton.textContent=paused?'Reia animațiile':'Pauză animații';} }
  syncMotion();
  motionButton?.addEventListener('click',()=>{paused=!paused;syncMotion();});
  reduce.addEventListener('change',()=>{paused=reduce.matches;syncMotion();});
  document.addEventListener('visibilitychange',()=>document.body.classList.toggle('motion-paused',document.hidden||paused));
  const menu=$('.menu-toggle'), nav=$('.site-header nav');
  menu?.addEventListener('click',()=>{const open=nav.classList.toggle('is-open');menu.setAttribute('aria-expanded',String(open));menu.setAttribute('aria-label',open?'Închide meniul':'Deschide meniul');});
  $$('a',nav||document.createElement('div')).forEach(a=>a.addEventListener('click',()=>{nav.classList.remove('is-open');menu.setAttribute('aria-expanded','false');menu.setAttribute('aria-label','Deschide meniul');}));
  const dialog=$('#pilot-dialog'); let opener; if(dialog?.dataset.open==='true') dialog.showModal();
  $$('[data-pilot]').forEach(b=>b.addEventListener('click',()=>{opener=b;dialog.showModal();}));
  $('.dialog-close')?.addEventListener('click',()=>dialog.close());
  dialog?.addEventListener('click',e=>{if(e.target===dialog){const r=dialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)dialog.close();}});
  dialog?.addEventListener('close',()=>opener?.focus());
  const segments={hotel:['OSPITALITATE, FĂRĂ ÎNTRERUPERI','O experiență bună începe în spatele scenei.','De la aerul condiționat din camera 204 la centrala din subsol: istoricul tehnic ar trebui să fie la îndemâna echipei, chiar și când se schimbă tura.',['Echipamente organizate pe camere și spații','Defecțiuni și intervenții cu istoric','Revizii, garanții și documente la un loc']],developer:['DE LA CONSTRUCȚIE LA CONTINUITATE','Predai clădirea. Păstrezi povestea întreagă.','Documentația și informațiile tehnice ar trebui să urmeze proprietatea după predare. Un pașaport digital poate deveni puntea dintre dezvoltator și viitoarea echipă de administrare.',['Documentație structurată pentru predare','Echipamente și garanții legate de imobil','Istoric pregătit să crească după livrare']],manager:['O PERSPECTIVĂ ASUPRA PORTOFOLIULUI','Mai multe clădiri. Un mod comun de lucru.','O structură consecventă pentru documente, intervenții și echipamente, astfel încât schimbarea echipei să nu însemne și pierderea contextului.',['Evidență tehnică pentru fiecare proprietate','Context comun pentru echipe și furnizori','Continuitate la schimbarea administratorului']]};
  const tabs=$$('[data-segment]');
  function selectSegment(b){tabs.forEach(x=>{x.setAttribute('aria-selected',String(x===b));x.tabIndex=x===b?0:-1;});const d=segments[b.dataset.segment];$('[data-segment-kicker]').textContent=d[0];$('[data-segment-title]').textContent=d[1];$('[data-segment-copy]').textContent=d[2];const list=$('[data-segment-list]');list.replaceChildren(...d[3].map(text=>{const li=document.createElement('li');li.textContent=text;return li;}));$('#segment-panel').setAttribute('aria-labelledby',b.id);}
  tabs.forEach((b,i)=>{b.tabIndex=i===0?0:-1;b.addEventListener('click',()=>selectSegment(b));b.addEventListener('keydown',e=>{let j;if(['ArrowDown','ArrowRight'].includes(e.key))j=(i+1)%tabs.length;if(['ArrowUp','ArrowLeft'].includes(e.key))j=(i+tabs.length-1)%tabs.length;if(e.key==='Home')j=0;if(e.key==='End')j=tabs.length-1;if(j!==undefined){e.preventDefault();selectSegment(tabs[j]);tabs[j].focus();}});});
  $$('[data-layer]').forEach(b=>b.addEventListener('click',()=>{$$('[data-layer]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));const stage=$('.building-stage');stage.dataset.layer=b.dataset.layer;$('[data-layer-label]').textContent={all:'Instalații · Istoric · Documente',systems:'Rețele tehnice · Identificare echipamente',history:'Intervenții · Continuitate în timp'}[b.dataset.layer];}));
  $('#pilot-form')?.addEventListener('submit',()=>{const button=$('#pilot-form button[type=submit]');button.disabled=true;button.textContent='Se trimite…';});
  if(matchMedia('(hover:hover) and (pointer:fine)').matches){$$('[data-tilt]').forEach(el=>{el.addEventListener('pointermove',e=>{if(paused||reduce.matches)return;const r=el.getBoundingClientRect();const x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;el.style.transform=`perspective(1100px) rotateX(${-y*5}deg) rotateY(${x*7}deg)`;});el.addEventListener('pointerleave',()=>el.style.transform='');});}
})();
