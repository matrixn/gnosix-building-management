(() => {
  'use strict';
  document.documentElement.classList.add('js');
  const $ = (selector, root=document) => root.querySelector(selector);
  const $$ = (selector, root=document) => [...root.querySelectorAll(selector)];
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  let paused = reduce.matches;
  const motionButton = $('[data-motion]');
  function syncMotion() {
    document.body.classList.toggle('motion-paused', paused || document.hidden);
    if (paused) $$('[data-tilt]').forEach(el => el.style.transform='');
    if (motionButton) {
      motionButton.setAttribute('aria-pressed', String(paused));
      motionButton.textContent = paused ? motionButton.dataset.resume : motionButton.dataset.pause;
    }
  }
  syncMotion();
  motionButton?.addEventListener('click', () => {paused=!paused; syncMotion();});
  reduce.addEventListener('change', () => {paused=reduce.matches; syncMotion();});
  document.addEventListener('visibilitychange', syncMotion);
  const menu=$('.menu-toggle'), nav=$('.site-header nav');
  menu?.addEventListener('click', () => {
    const open=nav.classList.toggle('is-open');
    menu.setAttribute('aria-expanded', String(open));
    menu.setAttribute('aria-label', open ? menu.dataset.menuClose : menu.dataset.menuOpen);
  });
  $$('a', nav || document.createElement('div')).forEach(a => a.addEventListener('click', () => {
    nav.classList.remove('is-open'); menu.setAttribute('aria-expanded','false'); menu.setAttribute('aria-label',menu.dataset.menuOpen);
  }));
  const tabs=$$('[data-segment]');
  function selectSegment(button) {
    tabs.forEach(tab => {tab.setAttribute('aria-selected',String(tab===button)); tab.tabIndex=tab===button ? 0 : -1;});
    $$('[data-segment-panel]').forEach(panel => panel.hidden=panel.dataset.segmentPanel!==button.dataset.segment);
  }
  tabs.forEach((button,i) => {
    button.tabIndex=i===0 ? 0 : -1;
    button.addEventListener('click', () => selectSegment(button));
    button.addEventListener('keydown', e => {
      let j;
      if (['ArrowDown','ArrowRight'].includes(e.key)) j=(i+1)%tabs.length;
      if (['ArrowUp','ArrowLeft'].includes(e.key)) j=(i+tabs.length-1)%tabs.length;
      if (e.key==='Home') j=0;
      if (e.key==='End') j=tabs.length-1;
      if (j!==undefined) {e.preventDefault(); selectSegment(tabs[j]); tabs[j].focus();}
    });
  });
  $$('[data-layer]').forEach(button => button.addEventListener('click', () => {
    $$('[data-layer]').forEach(other => other.setAttribute('aria-pressed',String(other===button)));
    $('.building-stage').dataset.layer=button.dataset.layer;
    $('[data-layer-label]').textContent=button.dataset.layerDescription;
  }));
  // Progressive enhancement: all example content remains readable without JS.
  $$('[data-demo-tabs]').forEach(group => {
    const buttons = $$(':scope > button[data-demo-target]', group);
    const panels = buttons.map(button => document.getElementById(button.dataset.demoTarget));
    group.setAttribute('role', 'tablist');
    function activate(index) {
      buttons.forEach((button, i) => {
        button.setAttribute('aria-selected', String(i === index));
        button.tabIndex = i === index ? 0 : -1;
        panels[i].hidden = i !== index;
      });
    }
    buttons.forEach((button, i) => {
      button.setAttribute('role', 'tab');
      button.setAttribute('aria-controls', panels[i].id);
      panels[i].setAttribute('role', 'tabpanel');
      panels[i].setAttribute('aria-labelledby', button.id);
      panels[i].tabIndex = 0;
      button.addEventListener('click', () => activate(i));
      button.addEventListener('keydown', event => {
        let next;
        if (['ArrowRight', 'ArrowDown'].includes(event.key)) next = (i + 1) % buttons.length;
        if (['ArrowLeft', 'ArrowUp'].includes(event.key)) next = (i + buttons.length - 1) % buttons.length;
        if (event.key === 'Home') next = 0;
        if (event.key === 'End') next = buttons.length - 1;
        if (next !== undefined) { event.preventDefault(); activate(next); buttons[next].focus(); }
      });
    });
    activate(0);
  });
  const systems = $$('[data-dna]');
  function selectSystem(button) {
    systems.forEach(other => other.setAttribute('aria-pressed', String(other === button)));
    $$('[data-dna-room]').forEach(room => room.classList.toggle('is-dependent', room.dataset.dnaRoom.split(' ').includes(button.dataset.dna)));
    $('[data-dna-result]').textContent = button.dataset.summary;
  }
  systems.forEach(button => button.addEventListener('click', () => selectSystem(button)));
  if (systems.length) selectSystem(systems[0]);
  const handover = $$('[data-handover]'), handoverResult = $('[data-handover-result]');
  function updateHandover() {
    const count = handover.filter(input => input.checked).length;
    $('[data-handover-bar]').style.width = `${count / handover.length * 100}%`;
    handoverResult.textContent = count === handover.length ? handoverResult.dataset.complete : handoverResult.dataset.progress.replace(':count', String(count));
  }
  handover.forEach(input => input.addEventListener('change', updateHandover));
  if (handover.length) updateHandover();
  const form=$('#contact-form');
  if (form) {
    $$('input,textarea,select', form).forEach(input => {
      input.addEventListener('input', () => input.setCustomValidity(''));
      input.addEventListener('change', () => input.setCustomValidity(''));
      input.addEventListener('invalid', () => {
        if (input.validity.valueMissing) input.setCustomValidity(form.dataset.required);
        else if (input.name==='email' && input.validity.typeMismatch) input.setCustomValidity(form.dataset.email);
        else if (input.name==='phone' && input.validity.patternMismatch) input.setCustomValidity(form.dataset.phone);
        else if (input.name==='message' && input.validity.tooShort) input.setCustomValidity(form.dataset.message);
      });
    });
    form.addEventListener('submit', () => {const button=$('button[type=submit]',form); button.disabled=true; button.textContent=form.dataset.sending;});
  }
  $('[data-contact-feedback]')?.focus({preventScroll:true});
  if (matchMedia('(hover:hover) and (pointer:fine)').matches) {
    $$('[data-tilt]').forEach(el => {
      el.addEventListener('pointermove', e => {
        if (paused || reduce.matches) return;
        const r=el.getBoundingClientRect(),x=(e.clientX-r.left)/r.width-.5,y=(e.clientY-r.top)/r.height-.5;
        el.style.transform=`perspective(1100px) rotateX(${-y*5}deg) rotateY(${x*7}deg)`;
      });
      el.addEventListener('pointerleave', () => el.style.transform='');
    });
  }
})();
