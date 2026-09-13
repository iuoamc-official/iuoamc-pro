document.addEventListener('DOMContentLoaded',()=>{
  const nav=document.querySelector('#nav');
  if(nav && !nav.querySelector('button[data-panel="webrtc"]')){
    const studio=nav.querySelector('button[data-panel="studio"]');
    const button=document.createElement('button');
    button.dataset.panel='webrtc';
    button.innerHTML='<span class="name">WebRTC Lab</span><span>W</span>';
    if(studio?.nextSibling)nav.insertBefore(button,studio.nextSibling);else nav.appendChild(button);
  }
  const routes={
    studio:'/studio-control/',
    webrtc:'/webrtc-control/',
    channels:'/channels-control/',
    scheduler:'/scheduler-control/',
    media:'/media-control/',
    playout:'/playout-control/',
    encoder:'/encoder-control/',
    distribution:'/distribution-control/',
    iptv:'/iptv-control/',
    noc:'/noc-control/',
    failover:'/failover-control/',
    supervisor:'/supervisor-control/',
    health:'/system-health/'
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
