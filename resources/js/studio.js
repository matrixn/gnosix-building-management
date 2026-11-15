document.querySelectorAll('[data-space-form]').forEach(form => {
    const building = form.querySelector('[data-space-building]'), floor = form.querySelector('[data-space-floor]');
    if (!building || !floor) return;
    const sync = () => {
        [...floor.options].forEach(option => {option.disabled = !!option.value && option.dataset.building !== building.value; option.hidden=option.disabled;});
        if (floor.selectedOptions[0]?.disabled) floor.value='';
    };
    building.addEventListener('change', sync); sync();
});

const form=document.querySelector('[data-studio]');
if(form){
    const schema=JSON.parse(document.getElementById('studio-schema').textContent);
    const status=form.querySelector('[data-studio-status]'), version=form.querySelector('[name=version]');
    const guided=form.querySelector('[data-guided]'), commit=form.querySelector('[data-studio-commit]');
    let step=form.dataset.step, queue=Promise.resolve(true), timer, revision=0, savedRevision=0, submitting=false;
    form.noValidate=true;
    const field=key=>form.querySelector(`[data-answer="${key}"]`);
    const visible=(definition,values)=>Object.entries(definition.when||{}).every(([key,condition])=>Object.hasOwn(condition,'min') ? Number(values[key])>=condition.min : condition.includes(values[key]));
    function answers(){
        const scenario=form.querySelector('[name="answers[scenario]"]:checked')?.value||'cabin';
        const values={scenario}, defaults=schema.scenarios[scenario].defaults;
        Object.entries(schema.fields).forEach(([key,definition])=>{
            let value;
            if(!visible(definition,values))value=definition.hidden_value;
            else if(definition.type==='checks')value=[...form.querySelectorAll(`[name="answers[${key}][]"]:checked`)].map(input=>input.value);
            else{value=field(key).value;if(value==='')value=defaults[key]??definition.default;}
            values[key]=definition.type==='number'?Number(value):value;
        });
        return values;
    }
    function activeSteps(){return schema.scenarios[answers().scenario].steps||schema.steps;}
    function sync(){
        const values=answers(), steps=activeSteps();if(!steps.includes(step))step=steps[0];
        const index=steps.indexOf(step);
        form.querySelector('[name=step]').value=step;
        form.querySelectorAll('[data-question]').forEach(wrapper=>{wrapper.hidden=!visible(schema.fields[wrapper.dataset.question],values);});
        form.querySelectorAll('[data-step-panel]').forEach(panel=>panel.hidden=guided.checked&&! (panel.dataset.stepPanel===step));
        form.querySelectorAll('[data-studio-step]').forEach(button=>{button.hidden=!steps.includes(button.dataset.studioStep);button.setAttribute('aria-current',button.dataset.studioStep===step?'step':'false');});
        form.querySelector('[data-studio-back]').hidden=!guided.checked||index===0;
        form.querySelector('[data-studio-next]').hidden=!guided.checked||index===steps.length-1;
        const properties=values.property_count, buildings=properties*values.building_count, floors=buildings*values.floor_count;
        const spaces=properties*(values.room_count+values.dormitory_count+values.shared_facilities.length);
        const counts={properties,buildings,floors,spaces,beds:properties*values.dormitory_count*values.beds_per_dorm};
        document.querySelector('[data-preview-name]').textContent=values.property_name||'GNOSIX';
        Object.entries(counts).forEach(([key,count])=>document.querySelector(`[data-preview-count="${key}"]`).textContent=String(count));
        document.querySelector('[data-studio-progress]').style.width=`${(index+1)/steps.length*100}%`;
        commit.disabled=properties+buildings+floors+spaces>schema.batch_node_limit;
        form.querySelector('[data-studio-limit]').hidden=!commit.disabled;
    }
    function reportStep(){
        const values=answers();
        for(const [key,definition] of Object.entries(schema.fields)){
            if(!visible(definition,values)|| (guided.checked&&definition.step!==step)||definition.type==='checks')continue;
            const input=field(key);input.required=!!definition.required;
            if(!input.reportValidity())return false;
        }
        return true;
    }
    function fail(result){
        const errors=result?.errors;
        status.textContent=errors?Object.values(errors).flat().join(' '):form.dataset.failed;
        status.classList.add('is-error');
        if(errors){
            const key=Object.keys(errors)[0].split('.').pop();
            if(schema.fields[key]){step=schema.fields[key].step;sync();field(key)?.focus();}
        }
    }
    function send(final=false){
        queue=queue.then(async()=>{
            const currentRevision=revision;status.textContent=form.dataset.saving;status.classList.remove('is-error');
            try{
                const response=await fetch(final?form.action:form.dataset.saveUrl,{method:'POST',body:new FormData(form),credentials:'same-origin',headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest'}});
                const result=await response.json();if(!response.ok){fail(result);return false;}
                savedRevision=currentRevision;version.value=result.version;status.textContent=currentRevision===revision?form.dataset.saved:form.dataset.saving;
                if(final&&result.redirect){submitting=true;location.assign(result.redirect);}
                return true;
            }catch{fail();return false;}
        });return queue;
    }
    async function go(next,history=true){
        if(!activeSteps().includes(next))return;
        clearTimeout(timer);
        if(!await send())return;
        step=next;sync();await send();
        if(history){const url=new URL(location.href);url.searchParams.set('stage',step);window.history.pushState({studioStep:step},'',url);}
        form.querySelector(`[data-step-panel="${step}"] h2`)?.focus();
    }
    form.querySelectorAll('[data-step-panel] h2').forEach(heading=>heading.tabIndex=-1);
    form.querySelectorAll('[data-studio-step]').forEach(button=>button.addEventListener('click',()=>go(button.dataset.studioStep)));
    form.querySelector('[data-studio-next]').hidden=false;form.querySelector('[data-studio-back]').hidden=false;
    form.querySelector('[data-studio-next]').addEventListener('click',()=>{if(reportStep())go(activeSteps()[activeSteps().indexOf(step)+1]);});
    form.querySelector('[data-studio-back]').addEventListener('click',()=>go(activeSteps()[activeSteps().indexOf(step)-1]));
    form.querySelectorAll('[name="answers[scenario]"]').forEach(input=>input.addEventListener('change',()=>{
        const defaults=schema.scenarios[input.value].defaults;
        Object.entries(schema.fields).forEach(([key,definition])=>{const input=field(key);if(input&&input.dataset.edited!=='1')input.value=defaults[key]??definition.default;});
        revision++;sync();clearTimeout(timer);send();
    }));
    form.querySelectorAll('[data-answer]').forEach(input=>input.addEventListener('input',()=>{input.dataset.edited='1';revision++;sync();status.textContent=form.dataset.saving;clearTimeout(timer);timer=setTimeout(()=>send(),700);}));
    form.querySelectorAll('input[type=checkbox]').forEach(input=>input.addEventListener('change',()=>{revision++;sync();clearTimeout(timer);send();}));
    form.addEventListener('submit',async event=>{
        if(submitting)return;event.preventDefault();clearTimeout(timer);
        const final=event.submitter?.hasAttribute('data-studio-commit');
        if(final&&!reportStep())return;
        commit.disabled=true;const saved=await send();if(saved&&final)await send(true);commit.disabled=false;sync();
    });
    window.addEventListener('beforeunload',event=>{if(!submitting&&revision!==savedRevision){event.preventDefault();event.returnValue='';}});
    window.history.replaceState({studioStep:step},'');
    window.addEventListener('popstate',event=>{if(event.state?.studioStep)go(event.state.studioStep,false);});
    sync();
}
