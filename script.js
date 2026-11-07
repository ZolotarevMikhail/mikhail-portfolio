// Общий скрипт zolmy.ru: тема, появление блоков, контактные формы.
'use strict';

// Переключение светлой/тёмной темы с сохранением выбора в браузере.
function toggleTheme(){
  const h=document.documentElement;
  const next=h.getAttribute('data-theme')==='dark'?'light':'dark';
  h.setAttribute('data-theme',next);
  const icon=document.getElementById('themeIcon'),text=document.getElementById('themeText');
  if(icon)icon.textContent=next==='dark'?'☀️':'🌙';
  if(text)text.textContent=next==='dark'?'Светлая':'Тёмная';
  try{localStorage.setItem('portfolio-theme',next)}catch(e){}
}

(function init(){
  // восстановить тему до появления контента, чтобы не мигало
  try{
    const t=localStorage.getItem('portfolio-theme');
    if(t){
      document.documentElement.setAttribute('data-theme',t);
      const icon=document.getElementById('themeIcon'),text=document.getElementById('themeText');
      if(icon)icon.textContent=t==='dark'?'☀️':'🌙';
      if(text)text.textContent=t==='dark'?'Светлая':'Тёмная';
    }
  }catch(e){}

  // появление блоков: скрипт сначала прячет (pre), затем проявляет (in).
  // без JS блоки видны сразу (правило .pre добавляется только скриптом)
  const reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fadeEls=document.querySelectorAll('.fade');
  if(reduced||!('IntersectionObserver'in window)){
    return;
  }
  const io=new IntersectionObserver((es)=>{es.forEach(e=>{if(e.isIntersecting){e.target.classList.remove('pre');e.target.classList.add('in');io.unobserve(e.target);}})},{threshold:.12});
  fadeEls.forEach(el=>{el.classList.add('pre');io.observe(el);});
})();

// Переключение контактных форм: одновременно открыта только одна.
function toggleContact(kind){
  const ids={tg:'formTelegram',mail:'formEmail',call:'formCall',vk:'formVk'};
  const inputs={tg:'tgMessage',mail:'mailFrom',call:'callPhone',vk:'vkMessage'};
  const target=document.getElementById(ids[kind]);
  if(!target)return false;
  const wasOpen=target.classList.contains('show');
  Object.values(ids).forEach(id=>{const el=document.getElementById(id);if(el)el.classList.remove('show');});
  if(!wasOpen){
    target.classList.add('show');
    setTimeout(()=>{const el=document.getElementById(inputs[kind]);if(el)el.focus();},50);
  }
  return false;
}

// Универсальная отправка формы через send.php → Telegram.
function sendToTelegram(form, payload, statusEl){
  const btn=form.querySelector('button[type="submit"]');
  const orig=btn?btn.textContent:'';
  if(btn){btn.disabled=true;btn.textContent='Отправляю...';}
  if(statusEl){statusEl.textContent='';statusEl.className='contact-form-note';}
  fetch('send.php',{
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:new URLSearchParams(payload).toString()
  })
  .then(r=>r.json())
  .then(data=>{
    if(data.ok){
      form.reset();
      if(statusEl){statusEl.textContent='✓ Отправлено! Я отвечу в Telegram.';statusEl.className='contact-form-note ok';}
    }else if(statusEl){
      statusEl.textContent='✗ Не отправилось. Напишите мне в Telegram — ссылка рядом.';
      statusEl.className='contact-form-note err';
    }
  })
  .catch(()=>{
    if(statusEl){
      statusEl.textContent='✗ Ошибка сети. Напишите мне в Telegram — ссылка рядом.';
      statusEl.className='contact-form-note err';
    }
  })
  .finally(()=>{
    if(btn){btn.disabled=false;btn.textContent=orig;}
  });
  return false;
}

// Отдельные отправители: значение берётся из поля конкретной формы.
function submitTelegram(e){e.preventDefault();const f=e.target;const t=f.querySelector('#tgMessage');if(!t||!t.value.trim())return false;return sendToTelegram(f,{type:'tg',message:t.value.trim()},f.querySelector('.contact-form-note'));}
function submitVk(e){e.preventDefault();const f=e.target;const t=f.querySelector('#vkMessage');if(!t||!t.value.trim())return false;return sendToTelegram(f,{type:'vk',message:t.value.trim()},f.querySelector('.contact-form-note'));}
function submitEmail(e){e.preventDefault();const f=e.target;const from=f.querySelector('#mailFrom');if(!from||!from.value.trim())return false;const task=f.querySelector('#mailTask');return sendToTelegram(f,{type:'mail',email:from.value.trim(),task:task?task.value.trim():''},f.querySelector('.contact-form-note'));}
function submitCall(e){e.preventDefault();const f=e.target;const ph=f.querySelector('#callPhone');if(!ph||!ph.value.trim())return false;return sendToTelegram(f,{type:'call',phone:ph.value.trim()},f.querySelector('.contact-form-note'));}

// Открытие нужной контактной формы по параметру ссылки (?form=tg).
(function openFormFromQuery(){
  try{
    const p=new URLSearchParams(location.search).get('form');
    if(p&&'tg,mail,vk,call'.split(',').includes(p)){
      const target=document.getElementById({tg:'formTelegram',mail:'formEmail',call:'formCall',vk:'formVk'}[p]);
      if(target)target.classList.add('show');
    }
  }catch(e){}
})();