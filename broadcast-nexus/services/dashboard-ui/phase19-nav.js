document.addEventListener('DOMContentLoaded',()=>{
  const studio=document.querySelector('#nav button[data-panel="studio"]');
  if(!studio)return;
  studio.addEventListener('click',(event)=>{
    event.preventDefault();
    event.stopImmediatePropagation();
    window.location.href='/studio-control/';
  },true);
});
