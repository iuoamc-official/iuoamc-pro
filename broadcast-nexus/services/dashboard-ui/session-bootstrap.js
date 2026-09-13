document.addEventListener('DOMContentLoaded',async()=>{
  if(location.pathname.startsWith('/auth-control/'))return;
  try{
    const r=await fetch('/auth-api/v1/session',{credentials:'same-origin',cache:'no-store'});
    if(!r.ok)throw new Error('unauthenticated');
    const d=await r.json();
    document.documentElement.dataset.operatorSession='active';
    document.documentElement.dataset.operatorSubject=d.subject||'operator';
    const jwtInput=document.getElementById('jwt');
    if(jwtInput&&typeof window.connectOps==='function'){
      jwtInput.value='session-cookie';
      await window.connectOps();
      jwtInput.value='';
    }
  }catch(e){
    const next=encodeURIComponent(location.pathname+location.search+location.hash);
    location.replace('/auth-control/?next='+next);
  }
});

window.nexusLogout=async function(){
  try{await fetch('/auth-api/v1/session/logout',{method:'POST',credentials:'same-origin',cache:'no-store'})}catch(e){}
  location.replace('/auth-control/');
};
