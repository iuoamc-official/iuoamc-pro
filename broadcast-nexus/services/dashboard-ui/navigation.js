document.addEventListener('DOMContentLoaded',()=>{
  const routes={
    studio:'/studio-control/',
    channels:'/channels-control/'
  };
  Object.entries(routes).forEach(([panel,url])=>{
    const button=document.querySelector(`#nav button[data-panel="${panel}"]`);
    if(!button)return;
    button.addEventListener('click',(event)=>{
      event.preventDefault();
      event.stopImmediatePropagation();
      window.location.href=url;
    },true);
  });
});
