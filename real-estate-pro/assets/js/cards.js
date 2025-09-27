(function(){
  function preload(srcs, cb){
    var left = srcs.length;
    if(!left){ cb(); return; }
    srcs.forEach(function(s){
      var img = new Image();
      img.onload = img.onerror = function(){ if(--left===0) cb(); };
      img.src = s;
    });
  }

  function initCardSlider(card){
    var imgs = [];
    try{
      imgs = JSON.parse(card.getAttribute('data-images')) || [];
    }catch(e){ imgs = []; }

    var stageLink = card.querySelector('.rep-cs-stage');
    var stage = stageLink ? stageLink.querySelector('img') : null;
    var prev  = card.querySelector('.rep-cs-prev');
    var next  = card.querySelector('.rep-cs-next');
    var i = 0;

    if(!stage || !imgs.length){ return; }

    function set(n){
      i = (n + imgs.length) % imgs.length;
      // Evitar reflujo raro en Safari
      stage.style.willChange = 'opacity';
      stage.style.opacity = '0.001';
      stage.onload = function(){ stage.style.opacity='1'; stage.style.willChange='auto'; };
      stage.src = imgs[i];
    }

    // Preload para evitar parpadeos en Safari
    preload(imgs, function(){ set(0); });

    function stop(e){ e.preventDefault(); e.stopPropagation(); }
    if(prev){ prev.addEventListener('click', function(e){ stop(e); set(i-1); }, {passive:false}); }
    if(next){ next.addEventListener('click', function(e){ stop(e); set(i+1); }, {passive:false}); }
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.rep-card-slider').forEach(initCardSlider);
  });
})();