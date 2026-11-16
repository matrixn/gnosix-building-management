const NS='http://www.w3.org/2000/svg';
const node=(tag,attrs={},text)=>{const n=document.createElementNS(NS,tag);Object.entries(attrs).forEach(([k,v])=>n.setAttribute(k,String(v)));if(text!==undefined)n.textContent=text;return n;};
const points=p=>p.map(v=>v.join(',')).join(' ');
const color=type=>({technical:'#69dfe0',common:'#a993ff',corridor:'#7384aa',storage:'#7384aa',dormitory:'#65ddba',bathroom:'#69dfe0'}[type]||'#bafa59');
export function drawBuilding(host,state,selection,options){
 const levels=state.levels, strings=options.strings, plan=options.view==='plan', selected=selection.level;
 const selectedIndex=Math.max(0,levels.findIndex(l=>l.key===selected)),windowStart=levels.length>12?Math.max(0,Math.min(levels.length-12,selectedIndex-5)):0;
 const entries=levels.slice(windowStart,windowStart+12);const maxHeight=Math.min(150,650/Math.max(1,entries.length));const step=Math.min(maxHeight,26+options.explode*1.7);
 const base=Math.max(0,entries.length-1)*step+65, height=Math.max(310,base+245);
 const svg=node('svg',{viewBox:plan?'0 0 800 540':`0 0 800 ${height}`,role:'group','aria-label':strings.schematic});
 const defs=node('defs');const pattern=node('pattern',{id:'gnx-grid',width:32,height:32,patternUnits:'userSpaceOnUse'});pattern.append(node('path',{d:'M32 0H0V32',fill:'none',stroke:'#a993ff', 'stroke-opacity':'.08'}));defs.append(pattern);svg.append(defs,node('rect',{width:800,height:plan?540:height,fill:'url(#gnx-grid)'}));
 if(levels.length>12&&!plan)svg.append(node('text',{x:400,y:24,'text-anchor':'middle',fill:'#c4b7de','font-size':15},`${strings.level_window} ${windowStart+1}–${windowStart+entries.length} / ${levels.length}`));
 if(!entries.length&&selected!=='unplaced'){svg.append(node('text',{x:400,y:260,'text-anchor':'middle',fill:'#bbb7cf','font-size':18},strings.no_levels));host.replaceChildren(svg);return;}
 const drawSpaces=(group,spaces,project,active)=>{
  const visible=spaces.slice(0,active?100:8);const cols=Math.max(1,Math.ceil(Math.sqrt(Math.max(visible.length,1)*1.5))),rows=Math.ceil(Math.max(visible.length,1)/cols);
  visible.forEach((s,i)=>{const x=(i%cols)*220/cols,y=Math.floor(i/cols)*160/rows,w=220/cols-3,h=160/rows-3;
   const p=[project(x+2,y+2),project(x+w,y+2),project(x+w,y+h),project(x+2,y+h)];
   const cell=node('g',{'class':'bld-svg-space','data-space':s.key,role:active?'button':'presentation',tabindex:active?0:-1,'aria-hidden':active?'false':'true','pointer-events':active?'auto':'none','aria-label':s.name});
   cell.append(node('title',{},s.name),node('polygon',{points:points(p),fill:color(s.space_type),'fill-opacity':selection.space===s.key?'.9':active?'.34':'.08',stroke:color(s.space_type),'stroke-opacity':active?'.75':'.12','stroke-width':selection.space===s.key?2:1}));
   if(active&&spaces.length<=40){const center=project(x+w/2,y+h/2);cell.append(node('text',{x:center[0],y:center[1]+4,'text-anchor':'middle',fill:'#f2f5e9','font-size':plan?(spaces.length<=16?26:18):11,'pointer-events':'none'},String(s.number||i+1).slice(0,10)));}
   cell.addEventListener('click',e=>{e.stopPropagation();options.onSpace(s.key);});cell.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();e.stopPropagation();options.onSpace(s.key);}});group.append(cell);
  });
  if(spaces.length>100&&active){const p=project(110,180);group.append(node('text',{x:p[0],y:p[1],fill:'#b9b3d1','text-anchor':'middle','font-size':12},`+${spaces.length-100} · ${strings.spaces}`));}
 };
 if(plan){const level=entries.find(l=>l.key===selected);const spaces=selected==='unplaced'?state.unplaced_spaces:level?.spaces||[];const project=(x,y)=>[70+x*3,50+y*2.5];const g=node('g');g.append(node('rect',{x:70,y:50,width:660,height:400,rx:10,fill:'#25203f',stroke:'#a993ff','stroke-width':2}));drawSpaces(g,spaces,project,true);svg.append(g);if(!spaces.length)svg.append(node('text',{x:400,y:250,fill:'#bbb7cf','text-anchor':'middle','font-size':18},strings.empty_level));}
 else {
  svg.append(node('ellipse',{cx:410,cy:base+128,rx:240,ry:40,fill:'#000','fill-opacity':'.28'}));
  const drawLevel=(level,i)=>{const z=i*step;const p=(x,y)=>[400+(x-y-30)*1.2,base+(x+y)*.5-z];const active=level.key===selected;const corners=[p(0,0),p(220,0),p(220,160),p(0,160)];const g=node('g',{'class':`bld-svg-level ${active?'is-active':''} ${options.animate?'is-assembling':''} ${active&&options.focus?'is-focusing':''}`,'data-level':level.key,role:'group','aria-label':`${level.name}, ${level.spaces.length} ${strings.spaces}`,style:`animation-delay:${Math.min(i*35,350)}ms`});
   const thick=12;
   g.append(node('polygon',{points:points([corners[1],corners[2],[corners[2][0],corners[2][1]+thick],[corners[1][0],corners[1][1]+thick]]),fill:active?'#526b31':'#261c42',stroke:'#695087','stroke-width':'.7'}));
   g.append(node('polygon',{points:points([corners[2],corners[3],[corners[3][0],corners[3][1]+thick],[corners[2][0],corners[2][1]+thick]]),fill:active?'#344d27':'#1c1933',stroke:'#695087','stroke-width':'.7'}));
   g.append(node('polygon',{points:points(corners),fill:active?'#303b34':'#2d234b','fill-opacity':active?'.98':'.24',stroke:active?'#bafa59':'#8d6bc7','stroke-width':active?2:1,role:'button',tabindex:0,'aria-label':level.name}));
   drawSpaces(g,level.spaces,p,active);
   if(active||entries.length<10){const label=p(-15,160);g.append(node('path',{d:`M${label[0]} ${label[1]}h-28`,stroke:active?'#bafa59':'#9074b1'}),node('text',{x:label[0]-36,y:label[1]+4,'text-anchor':'end',fill:active?'#bafa59':'#c1b3d9','font-size':14},level.name.slice(0,24)));}
   g.addEventListener('click',()=>options.onLevel(level.key));g.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();options.onLevel(level.key);}});svg.append(g);
  };
  entries.forEach((l,i)=>{if(l.key!==selected)drawLevel(l,i);});const activeIndex=entries.findIndex(l=>l.key===selected);if(activeIndex>=0)drawLevel(entries[activeIndex],activeIndex);
  if(selected==='unplaced'){const g=node('g');drawSpaces(g,state.unplaced_spaces,(x,y)=>[370+x-y,base+(x+y)*.5],true);svg.append(g);}
 }
 host.replaceChildren(svg);
}
