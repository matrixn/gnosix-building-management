import {generateBuilding} from './building-generator.js';
import {drawBuilding} from './building-scene.js';
const root=document.querySelector('[data-building-studio],[data-building-dashboard]');
if(root){
 const payload=JSON.parse(root.querySelector('[data-building-data]').textContent),cfg=payload.config,t=payload.strings,editing=root.hasAttribute('data-building-studio');
 let state=payload.state,stage=root.dataset.stageValue||'identity',level=state.levels[0]?.key||'unplaced',space=null,unit=null,view=matchMedia('(max-width:700px)').matches?'plan':'axonometric',explode=30,page=0,unitPage=0,animate=true,lastPaintedLevel=null;
 let undo=[],undoBytes=0,timer,queue=Promise.resolve(true),revision=0,savedRevision=0,version=Number(root.dataset.version||0),source='manual',busy=false,conflict=false;
 const $=s=>root.querySelector(s),all=s=>[...root.querySelectorAll(s)],uid=()=>crypto.randomUUID(),clone=x=>JSON.parse(JSON.stringify(x));
 const el=(tag,props={},text)=>{const e=document.createElement(tag);Object.entries(props).forEach(([k,v])=>{if(k==='class')e.className=v;else e.setAttribute(k,String(v));});if(text!==undefined)e.textContent=text;return e;};
 const button=(text,action,cls='identity-text-button')=>{const b=el('button',{type:'button',class:cls},text);b.addEventListener('click',action);return b;};
 const formatArea=value=>value==null?'—':new Intl.NumberFormat(document.documentElement.lang,{maximumFractionDigits:3}).format(Number(value));
 const flatSpaces=()=>[...state.levels.flatMap(l=>l.spaces),...state.unplaced_spaces];
 const currentLevel=()=>state.levels.find(l=>l.key===level);
 const currentSpaces=()=>level==='unplaced'?state.unplaced_spaces:currentLevel()?.spaces||[];
 const metric=(key,value)=>{const d=el('div',{class:'bld-metric'});d.append(el('strong',{'aria-label':value==='—'?t.unknown:String(value)},value),el('span',{},t[key]||key));return d;};
 const saveStatus=text=>{if(editing)$('[data-save-status]').textContent=text;};
 function capture(){if(!editing)return;const entry=JSON.stringify(state);if(undo.at(-1)===entry)return;undo.push(entry);undoBytes+=entry.length;while(undo.length>20||undoBytes>8000000){undoBytes-=undo.shift().length;} $('[data-undo]').disabled=!undo.length;}
 function mark(){revision++;if(!conflict){saveStatus(t.dirty);clearTimeout(timer);timer=setTimeout(()=>save(false),900);}}
 function change(work){capture();work();source='manual';mark();render();}
 function inputField(label,value,onInput,type='text',attributes={},track=true){
  const wrap=el('div',{class:'identity-field'}),id='bld-field-'+uid(),labelEl=el('label',{for:id},label),input=el('input',{id,type,...attributes});input.value=value==null?'':type==='number'?Number(value):value;input.dataset.focus=label;
  if(track)input.addEventListener('focus',capture);input.addEventListener('input',()=>{input.removeAttribute('aria-invalid');onInput(input.value);if(track){source='manual';mark();paint();}});wrap.append(labelEl,input);return wrap;
 }
 function selectField(label,value,items,onChange){const wrap=el('div',{class:'identity-field'}),id='bld-field-'+uid(),select=el('select',{id});select.dataset.focus=label;wrap.append(el('label',{for:id},label),select);items.forEach(([v,label])=>{const o=el('option',{value:v},label);o.selected=v===value;select.append(o);});select.addEventListener('change',()=>change(()=>onChange(select.value)));return wrap;}
 function selectLevel(key){level=key;space=null;unit=null;page=0;render();}
 function focusInspector(){const host=$('[data-inspector]');const rect=host.getBoundingClientRect();if(rect.top<0||rect.top>innerHeight-100)host.scrollIntoView({block:'start'});}
 function selectSpace(key){if(!editing&&root.dataset.editUrl){location.assign(root.dataset.editUrl+'?space='+encodeURIComponent(key));return;}const owner=state.levels.find(l=>l.spaces.some(s=>s.key===key));level=owner?.key||'unplaced';space=key;unit=null;page=Math.floor(currentSpaces().findIndex(s=>s.key===key)/40);render();if(matchMedia('(max-width:1250px)').matches)focusInspector();}
 function counts(){const spaces=flatSpaces();return {levels:state.levels.length,spaces:spaces.length,beds:spaces.some(x=>x.bed_count!==null)?(spaces.some(x=>x.bed_count===null)?'≥':'')+spaces.reduce((s,x)=>s+Number(x.bed_count||0),0):'—',units:state.units.length};}
 function paint(){
  const spacing=$('[data-explode]'),maxGap=Math.min(70,Math.floor((Math.min(150,650/Math.max(1,Math.min(12,state.levels.length)))-26)/1.7));spacing.max=String(maxGap);explode=Math.min(explode,maxGap);spacing.value=String(explode);
  const active=document.activeElement,svgFocus=active?.closest?.('[data-scene]'),svgSpace=svgFocus?active.closest('[data-space]')?.dataset.space:null,svgLevel=svgFocus?active.closest('[data-level]')?.dataset.level:null;
  drawBuilding($('[data-scene]'),state,{level,space},{view,explode,strings:t,animate,focus:lastPaintedLevel!==level,onLevel:selectLevel,onSpace:selectSpace});animate=false;lastPaintedLevel=level;$('[data-explode]').closest('label').hidden=view==='plan'||state.levels.length<2;if(svgSpace)$('[data-scene]').querySelector(`[data-space="${svgSpace}"]`)?.focus({preventScroll:true});else if(svgLevel)$('[data-scene]').querySelector(`[data-level="${svgLevel}"]>[role=button]`)?.focus({preventScroll:true});all('[data-view]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.view===view)));
  const c=counts();$('[data-metrics]').replaceChildren(...Object.entries(c).map(([k,v])=>metric(k,v)));
  paintLevels();paintSpaces();
  if(editing){const summary=$('[data-review-summary]');summary.replaceChildren(el('h3',{},state.identity.display_name||state.identity.name||t.unknown),...Object.entries(c).map(([k,v])=>metric(k,v)));}
 }
 function paintLevels(){
  const host=$('[data-level-list]'),scroll=host.scrollTop,focused=document.activeElement?.dataset?.levelKey;host.replaceChildren();
  state.levels.forEach((l,i)=>{const b=button('',()=>selectLevel(l.key),'bld-level-button');b.dataset.levelKey=l.key;b.setAttribute('aria-pressed',String(l.key===level));b.append(el('i',{},String(i+1).padStart(2,'0')),el('span',{},l.name),el('small',{},String(l.spaces.length)));host.append(b);});
  if(state.unplaced_spaces.length){const b=button(t.unplaced+' · '+state.unplaced_spaces.length,()=>selectLevel('unplaced'),'bld-level-button');b.setAttribute('aria-pressed',String(level==='unplaced'));host.append(b);}
  if(!state.levels.length&&!state.unplaced_spaces.length)host.append(el('p',{class:'identity-caption'},t.no_levels));host.scrollTop=scroll;if(focused)[...host.querySelectorAll('[data-level-key]')].find(b=>b.dataset.levelKey===focused)?.focus({preventScroll:true});
 }
 function paintSpaces(){
  const spaces=currentSpaces(),host=$('[data-space-list]'),pages=$('[data-space-pages]');page=Math.min(page,Math.max(0,Math.ceil(spaces.length/40)-1));
  $('[data-level-title]').textContent=currentLevel()?.name||t.unplaced;$('[data-space-total]').textContent=`${spaces.length} ${t.spaces}`;host.replaceChildren();pages.replaceChildren();
  spaces.slice(page*40,page*40+40).forEach(s=>{const b=button('',()=>selectSpace(s.key),'bld-space-tile');b.setAttribute('aria-pressed',String(space===s.key));b.append(el('strong',{},s.number||'·'),el('span',{},s.name),el('small',{},t['type_'+s.space_type]||s.space_type),el('small',{},`${formatArea(s.area_m2)} m² · ${s.bed_count??'—'} ${t.beds} · ${s.capacity??'—'} ${t.capacity_short}`));host.append(b);});
  if(!spaces.length)host.append(el('p',{class:'identity-caption'},t.empty_level));
  if(spaces.length>40){const prev=button('← '+t.previous,()=>{page--;paintSpaces();});prev.disabled=page===0;const next=button(t.next+' →',()=>{page++;paintSpaces();});next.disabled=(page+1)*40>=spaces.length;pages.append(prev,el('span',{},`${page+1} / ${Math.ceil(spaces.length/40)}`),next);}
 }
 function removeRelations(keys){const removed=new Set(keys);state.units=state.units.filter(u=>{u.space_keys=u.space_keys.filter(k=>!removed.has(k));return u.unit_type==='whole_building'||u.space_keys.length;});}
 function addSpace(list,type='room',number=null){const s={key:uid(),name:(t['type_'+type]||t.space)+(number?' '+number:''),space_type:type,number,bed_count:null,capacity:null,area_m2:null,beds:[]};list.push(s);return s;}
 function paintInspector(){
  const host=$('[data-inspector]');host.replaceChildren();const selected=flatSpaces().find(s=>s.key===space);
  if(unit){if(editing)paintUnitInspector(host);else{const u=state.units.find(x=>x.key===unit);host.append(el('h2',{},u.name),el('p',{},t['unit_'+u.unit_type]));flatSpaces().filter(s=>u.space_keys.includes(s.key)).forEach(s=>host.append(el('p',{},s.name)));}return;}
  if(selected){
   host.append(el('h2',{},selected.name));
   if(!editing){host.append(el('p',{},t['type_'+selected.space_type]||selected.space_type));['number','bed_count','capacity','area_m2'].forEach(k=>host.append(metric(k,selected[k]??t.unknown)));if(selected.beds.length)host.append(el('h3',{},t.individual_beds),...selected.beds.map(b=>el('p',{},b.label)));return;}
   host.append(inputField(t.space_name,selected.name,v=>selected.name=v,'text',{maxlength:150}),selectField(t.space_type,selected.space_type,cfg.space_types.map(k=>[k,t['type_'+k]]),v=>selected.space_type=v),inputField(t.number,selected.number,v=>selected.number=v||null,'text',{maxlength:40}));
   ['capacity','bed_count','area_m2'].forEach(k=>host.append(inputField(t[k],selected[k],v=>selected[k]=v===''?null:Number(v),'number',{min:0,max:k==='area_m2'?100000000:1000,step:k==='area_m2'?'.001':1})));
   host.append(selectField(t.move_to_level,level,[['unplaced',t.unplaced],...state.levels.map(l=>[l.key,l.name])],target=>{
    const old=currentSpaces(),index=old.findIndex(s=>s.key===selected.key);if(index<0)return;old.splice(index,1);level=target;currentSpaces().push(selected);page=Math.floor((currentSpaces().length-1)/40);
   }));
   host.append(el('h3',{},t.individual_beds));
   selected.beds.forEach(b=>{const row=el('div',{class:'bld-bed-row'});row.append(inputField(t.bed_label,b.label,v=>b.label=v,'text',{maxlength:80}),button('×',()=>change(()=>{selected.beds=selected.beds.filter(x=>x.key!==b.key);selected.bed_count=selected.beds.length;state.units=state.units.filter(u=>u.bed_key!==b.key);})));row.lastChild.setAttribute('aria-label',t.remove_space+' · '+b.label);host.append(row);});
   host.append(button('+ '+t.add_bed,()=>change(()=>{if(selected.beds.length>=1000)return;selected.beds.push({key:uid(),label:t.bed+' '+(selected.beds.length+1)});selected.bed_count=selected.beds.length;})),button(t.remove_space,()=>change(()=>{removeRelations([selected.key]);if(level==='unplaced')state.unplaced_spaces=state.unplaced_spaces.filter(x=>x.key!==space);else currentLevel().spaces=currentSpaces().filter(x=>x.key!==space);space=null;}),'bld-danger'));
   return;
  }
  const l=currentLevel();host.append(el('h2',{},l?.name||t.unplaced));
  if(editing&&l){
   host.append(inputField(t.level_name,l.name,v=>l.name=v,'text',{maxlength:150}),selectField(t.level_kind,l.kind||'',[['',t.unknown],...cfg.level_kinds.map(k=>[k,t['kind_'+k]])],v=>l.kind=v||null));
   const index=state.levels.indexOf(l),controls=el('div',{class:'bld-inspector-actions'});const up=button('↑',()=>change(()=>{[state.levels[index-1],state.levels[index]]=[state.levels[index],state.levels[index-1]];}));up.disabled=index===0;up.setAttribute('aria-label',t.move_up);
   const down=button('↓',()=>change(()=>{[state.levels[index+1],state.levels[index]]=[state.levels[index],state.levels[index+1]];}));down.disabled=index===state.levels.length-1;down.setAttribute('aria-label',t.move_down);
   controls.append(up,down,button(t.copy,()=>change(()=>{if(state.levels.length>=cfg.max_levels)return;const copied=clone(l);copied.key=uid();copied.name=l.name+' +';copied.spaces.forEach(s=>{s.key=uid();s.beds.forEach(b=>b.key=uid());});state.levels.splice(index+1,0,copied);level=copied.key;})));
   host.append(controls,button(t.remove_level,()=>{if(confirm(t.remove_level_confirm))change(()=>{removeRelations(l.spaces.map(s=>s.key));state.levels=state.levels.filter(x=>x.key!==level);level=state.levels[0]?.key||'unplaced';});},'bld-danger'),el('p',{class:'field-help'},t.archive_note));
  }
  if(editing){
   host.append(el('h3',{},t.add_spaces));const quantity=el('input',{type:'number',required:'required',min:1,max:500,value:1,'aria-label':t.quantity}),type=el('select',{'aria-label':t.space_type}),prefix=el('input',{maxlength:15,'aria-label':t.prefix,placeholder:t.prefix}),start=el('input',{type:'number',required:'required',min:1,max:999999,value:101,'aria-label':t.start_number});cfg.space_types.forEach(k=>type.append(el('option',{value:k},t['type_'+k])));
   const defaults={};['area_m2','bed_count','capacity'].forEach(k=>{const field=inputField(t['default_'+k],null,v=>defaults[k]=v===''?null:Number(v),'number',{min:0,max:k==='area_m2'?100000000:1000,step:k==='area_m2'?'.001':1},false);field.querySelector('input').dataset.bulkDefault=k;host.append(field);});
   host.append(quantity,type,prefix,start,button('+ '+t.add,()=>{const n=Number(quantity.value),first=Number(start.value);if(!quantity.reportValidity()||!start.reportValidity()||[...host.querySelectorAll('[data-bulk-default]')].some(i=>!i.reportValidity()))return;if(counts().spaces+n>cfg.max_spaces){error(t.too_large);return;}change(()=>{const list=currentSpaces();if(level==='unplaced'&&!state.unplaced_spaces)state.unplaced_spaces=[];for(let i=0;i<n;i++)Object.assign(addSpace(list,type.value,prefix.value+(first+i)),defaults);});}),button(t.renumber,()=>{if(!start.reportValidity())return;change(()=>currentSpaces().forEach((s,i)=>s.number=prefix.value+(Number(start.value)+i)));}));
  }
  host.append(el('p',{class:'identity-caption'},t.select_space));const help=el('details',{class:'studio-help'});help.append(el('summary',{},t.why),el('p',{},t.memory_hint));host.append(help);
 }
 function paintUnits(){const host=$('[data-unit-list]');host.replaceChildren();state.units.slice(unitPage*30,unitPage*30+30).forEach(u=>{const b=button('',()=>{unit=u.key;space=null;paintInspector();focusInspector();},'bld-unit-button');b.append(el('strong',{},u.name),el('small',{},t['unit_'+u.unit_type]));host.append(b);});if(!state.units.length)host.append(el('p',{class:'identity-caption'},t.no_units));if(state.units.length>30){const prev=button('←',()=>{unitPage--;paintUnits();});prev.disabled=unitPage===0;const next=button('→',()=>{unitPage++;paintUnits();});next.disabled=(unitPage+1)*30>=state.units.length;host.append(prev,el('span',{},`${unitPage+1} / ${Math.ceil(state.units.length/30)}`),next);}}
 function paintUnitInspector(host){
  const u=state.units.find(u=>u.key===unit);if(!u){unit=null;paintInspector();return;}
  host.append(el('h2',{},t.accommodation),inputField(t.unit_name,u.name,v=>u.name=v,'text',{maxlength:150}),selectField(t.unit_type,u.unit_type,cfg.unit_types.map(k=>[k,t['unit_'+k]]),v=>{u.unit_type=v;if(v!=='bed')u.bed_key=null;}),el('p',{class:'field-help'},t.unit_help));
  const spaces=flatSpaces(),select=el('select',{multiple:true,'aria-label':t.linked_spaces,size:Math.min(8,Math.max(3,spaces.length))});spaces.forEach(s=>{const o=el('option',{value:s.key},s.name);o.selected=u.space_keys.includes(s.key);select.append(o);});select.addEventListener('change',()=>change(()=>{u.space_keys=[...select.selectedOptions].map(o=>o.value);u.bed_key=null;}));host.append(select);
  if(u.unit_type==='bed'){const beds=spaces.filter(s=>u.space_keys.includes(s.key)).flatMap(s=>s.beds.map(b=>[b.key,s.name+' / '+b.label]));host.append(selectField(t.selected_bed,u.bed_key||'',[['',t.unknown],...beds],v=>u.bed_key=v||null));}
  host.append(button(t.remove_unit,()=>change(()=>{state.units=state.units.filter(x=>x.key!==unit);unit=null;}),'bld-danger'));
 }
 function render(){if(editing){$('[data-add-level]').disabled=state.levels.length>=cfg.max_levels;$('[data-add-unit]').disabled=state.units.length>=cfg.max_units;}if(editing)$('[data-identity=custom_classification]').closest('.identity-field').hidden=state.identity.classification!=='custom';const focus=document.activeElement?.dataset?.focus;paint();paintInspector();paintUnits();if(focus)[...root.querySelectorAll('[data-focus]')].find(e=>e.dataset.focus===focus)?.focus({preventScroll:true});if(editing)all('[data-preset]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.preset===state.identity.classification)));}
 function syncIdentity(){if(!editing)return;all('[data-identity]').forEach(input=>input.value=state.identity[input.dataset.identity]??'');all('[data-preset]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.preset===state.identity.classification)));}
 function setStage(next){stage=next;all('[data-panel]').forEach(p=>p.hidden=p.dataset.panel!==stage);$('[data-workbench]').hidden=stage==='identity';all('[data-stage]').forEach(b=>b.setAttribute('aria-current',b.dataset.stage===stage?'step':'false'));if(stage!=='identity')render();}
 function exportLocal(){const blob=new Blob([JSON.stringify({format:'gnosix.building.v1',structure:state},null,2)],{type:'application/json'}),url=URL.createObjectURL(blob),a=el('a',{href:url,download:'gnosix-building.json'});a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);}
 function error(message){$('[data-error]').textContent=message;$('[data-error]').focus();}
 function highlightError(errors){
  if(!errors)return;const key=Object.keys(errors)[0],parts=key.split('.'),identity=all('[data-identity]').find(e=>e.dataset.identity===key);
  if(identity){setStage('identity');const details=identity.closest('details');if(details)details.open=true;identity.setAttribute('aria-invalid','true');identity.focus();return;}
  if(parts[0]==='levels'&&state.levels[Number(parts[1])]){const l=state.levels[Number(parts[1])];level=l.key;space=parts[2]==='spaces'?l.spaces[Number(parts[3])]?.key:null;unit=null;setStage('structure');focusInspector();}
  if(parts[0]==='units'&&state.units[Number(parts[1])]){unit=state.units[Number(parts[1])].key;space=null;setStage('structure');focusInspector();}
 }
 function save(complete){
  queue=queue.then(async()=>{
   if(conflict)return false;
   const current=revision,submitted=clone(state);saveStatus(complete?t.save_complete:t.saving);
   try{
    const response=await fetch(complete?root.dataset.commitUrl:root.dataset.draftUrl,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:JSON.stringify({version,state:submitted,source_kind:source,stage})});
    const data=await response.json();if(!response.ok){if(data.errors?.version||data.errors?.building){conflict=true;$('[data-fresh]').hidden=false;}error(data.errors?Object.values(data.errors).flat().join(' '):t.failed);saveStatus(t.failed);highlightError(data.errors);return false;}
    if(data.state&&current===revision)state=data.state;version=data.version;savedRevision=current;$('[data-error]').textContent='';saveStatus(current===revision?t.saved:t.dirty);
    if(complete&&data.redirect){busy=true;location.assign(data.redirect);}return true;
   }catch{error(t.failed);saveStatus(t.failed);return false;}
  });return queue;
 }
 all('[data-view]').forEach(b=>b.addEventListener('click',()=>{view=b.dataset.view;all('[data-view]').forEach(x=>x.setAttribute('aria-pressed',String(x===b)));paint();}));
 $('[data-explode]').addEventListener('input',e=>{explode=Number(e.target.value);paint();});
 if(editing){
  $('[data-unknown-type]').addEventListener('click',()=>change(()=>state.identity.classification=null));
  root.classList.add('building-ready');syncIdentity();setStage(stage);
  if(root.dataset.focusSpace&&flatSpaces().some(s=>s.key===root.dataset.focusSpace)){setStage('structure');selectSpace(root.dataset.focusSpace);}
  if(state.levels.length)$('[data-generate-first]').value=String(state.levels.length+(state.levels[0]?.kind==='upper'?1:0));
  function updateGeneratorSummary(){const n=Number($('[data-generate-levels]').value),rooms=Number($('[data-generate-spaces]').value),total=n*rooms;const area=$('[data-generate-default=area_m2]').value,beds=$('[data-generate-default=bed_count]').value,capacity=$('[data-generate-default=capacity]').value;const values={levels:n,rooms:total,area:area===''?'—':Number((total*Number(area)).toFixed(3)),beds:beds===''?'—':total*Number(beds),capacity:capacity===''?'—':total*Number(capacity)};$('[data-generate-summary]').textContent=t.generator_summary.replace(/:(levels|rooms|area|beds|capacity)/g,(_,key)=>values[key]);}
  all('[data-generator] input,[data-generator] select').forEach(i=>i.addEventListener('input',updateGeneratorSummary));const initialPreset=cfg.presets[state.identity.classification];if(initialPreset){$('[data-generate-levels]').value=initialPreset.levels;$('[data-generate-spaces]').value=initialPreset.spaces;$('[data-generate-default=bed_count]').value=initialPreset.beds??'';}updateGeneratorSummary();
  all('[data-identity]').forEach(input=>{input.addEventListener('focus',capture);input.addEventListener(input.tagName==='SELECT'?'change':'input',()=>{input.removeAttribute('aria-invalid');source='manual';const key=input.dataset.identity;state.identity[key]=input.value===''?(key==='name'?'':null):['construction_year','latitude','longitude'].includes(key)?Number(input.value):key==='country'?input.value.toUpperCase():input.value;mark();if(key==='name'||key==='display_name')paint();});});
  all('[data-stage]').forEach(b=>b.addEventListener('click',()=>{setStage(b.dataset.stage);mark();}));all('[data-next]').forEach(b=>b.addEventListener('click',()=>{setStage(b.dataset.next);mark();root.querySelector(`[data-panel="${stage}"] h2`)?.scrollIntoView({block:'start'});}));
  all('[data-preset]').forEach(b=>b.addEventListener('click',()=>change(()=>{state.identity.classification=b.dataset.preset;const preset=cfg.presets[b.dataset.preset];$('[data-generate-levels]').value=preset.levels;$('[data-generate-spaces]').value=preset.spaces;$('[data-generate-default=bed_count]').value=preset.beds??'';updateGeneratorSummary();})));
  $('[data-add-level]').addEventListener('click',()=>change(()=>{if(state.levels.length>=cfg.max_levels)return;const l={key:uid(),name:t.new_level,kind:null,spaces:[]};state.levels.push(l);level=l.key;space=null;unit=null;}));
  $('[data-generate]').addEventListener('click',()=>{
   if(all('[data-generator] input').some(i=>!i.reportValidity()))return;
   const options={levels:Number($('[data-generate-levels]').value),rooms:Number($('[data-generate-spaces]').value),firstLevel:Number($('[data-generate-first]').value),mode:$('[data-generate-mode]').value,individualBeds:$('[data-generate-beds]').checked,units:$('[data-generate-units]').checked};
   all('[data-generate-default]').forEach(i=>options[i.dataset.generateDefault]=i.value);
   try{
    const generated=generateBuilding(state,options,cfg,t,uid);
    if(options.mode==='replace'&&(state.levels.length||state.unplaced_spaces.length)&&!confirm(t.generate_confirm))return;
    const firstNew=generated.levels[options.mode==='append'?state.levels.length:0]?.key;
    change(()=>{state=generated;level=firstNew||state.levels[0]?.key||'unplaced';space=null;unit=null;animate=true;});
    $('[data-generate-first]').value=String(options.firstLevel+options.levels);
    $('[data-generator-panel]').open=false;
    $('[data-workbench]').scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion:reduce)').matches?'auto':'smooth'});
   }catch(e){error(e.message);}
  });
  $('[data-add-unit]').addEventListener('click',()=>{change(()=>{const u={key:uid(),name:t.new_unit,unit_type:flatSpaces().length?'suite':'whole_building',space_keys:space?[space]:[],bed_key:null};state.units.push(u);unit=u.key;space=null;});focusInspector();});
  $('[data-undo]').addEventListener('click',()=>{if(!undo.length)return;const entry=undo.pop();undoBytes-=entry.length;state=JSON.parse(entry);level=state.levels.some(l=>l.key===level)?level:state.levels[0]?.key||'unplaced';space=null;unit=null;syncIdentity();mark();render();$('[data-undo]').disabled=!undo.length;});
  $('[data-save]').addEventListener('click',()=>{clearTimeout(timer);save(false);});
  all('[data-complete]').forEach(button=>button.addEventListener('click',async()=>{if(busy)return;if(!state.identity.name.trim()){setStage('identity');$('[data-identity=name]').focus();error(t.confirm_name);return;}clearTimeout(timer);busy=true;all('[data-complete]').forEach(b=>b.disabled=true);const ok=await save(true);if(!ok){busy=false;all('[data-complete]').forEach(b=>b.disabled=false);}}));
  $('[data-export]').addEventListener('click',exportLocal);
  $('[data-import]').addEventListener('change',async event=>{const file=event.target.files[0];if(!file)return;if(file.size>cfg.max_payload_bytes){error(t.too_large);return;}try{const imported=JSON.parse(await file.text());if(imported.format!=='gnosix.building.v1'||!imported.structure)throw Error();
    const candidate=imported.structure;if(!candidate.identity||!Array.isArray(candidate.levels)||!Array.isArray(candidate.unplaced_spaces)||!Array.isArray(candidate.units)||candidate.levels.length>cfg.max_levels)throw Error();
    if($('[data-import-template]').checked){
     const mappings=new Map(),fresh=key=>{if(!mappings.has(key))mappings.set(key,uid());return mappings.get(key);};
     candidate.identity.reference=null;
     candidate.levels.forEach(l=>{l.key=fresh(l.key);l.spaces.forEach(s=>{s.key=fresh(s.key);s.beds.forEach(b=>b.key=fresh(b.key));});});
     candidate.unplaced_spaces.forEach(s=>{s.key=fresh(s.key);s.beds.forEach(b=>b.key=fresh(b.key));});
     candidate.units.forEach(u=>{u.key=fresh(u.key);u.space_keys=u.space_keys.map(fresh);if(u.bed_key)u.bed_key=fresh(u.bed_key);});
    }
    if(!confirm(t.import_confirm))return;
    const prior=state,priorSource=source;clearTimeout(timer);await queue;
    // Validate and persist on the server before rendering any imported structure.
    capture();state=candidate;source='import';revision++;const ok=await save(false);
    if(!ok){state=prior;source=priorSource;return;}
    $('[data-undo]').disabled=false;
    level=state.levels[0]?.key||'unplaced';space=null;unit=null;syncIdentity();render();saveStatus(t.imported);
   }catch{error(t.invalid_structure);}finally{event.target.value='';}
  });
  window.addEventListener('beforeunload',event=>{if(!busy&&revision!==savedRevision){event.preventDefault();event.returnValue='';}});
 }
 render();
}
