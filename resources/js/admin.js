import { browserSupportsWebAuthn, startAuthentication, startRegistration } from '@simplewebauthn/browser';

const csrf = document.querySelector('meta[name="csrf-token"]').content;
async function post(url, body = {}) {
    const response = await fetch(url, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf}, body:JSON.stringify(body)});
    const data = await response.json().catch(()=>({message:'Sesiunea a expirat. Reîncarcă pagina.'}));
    if (!response.ok) throw new Error(data.message || 'Cererea nu a putut fi verificată.');
    return data;
}
const feedback = document.querySelector('.passkey-feedback');
async function perform(button, work) {
    if (!browserSupportsWebAuthn() || !window.isSecureContext) {
        feedback.textContent='Passkeys necesită HTTPS (sau localhost) și un browser compatibil.'; return;
    }
    button.disabled=true; feedback.textContent='Confirmă pe dispozitivul tău…';
    try { await work(); }
    catch(error){feedback.textContent=error.name==='NotAllowedError'?'Acțiunea a fost anulată sau a expirat. Poți încerca din nou.':error.message;}
    finally {button.disabled=false;}
}
const login=document.querySelector('[data-passkey-login]');
login?.addEventListener('click',()=>perform(login,async()=>{
    const optionsJSON=await post(login.dataset.options);
    const credential=await startAuthentication({optionsJSON});
    const result=await post(login.dataset.submit,{credential});
    window.location.assign(result.redirect);
}));
const registration=document.querySelector('[data-passkey-register]');
registration?.addEventListener('submit',e=>{
    e.preventDefault(); const button=registration.querySelector('button');
    perform(button,async()=>{
        const optionsJSON=await post(registration.dataset.options);
        const credential=await startRegistration({optionsJSON});
        await post(registration.dataset.submit,{name:registration.elements.passkey_name.value,credential});
        window.location.reload();
    });
});
