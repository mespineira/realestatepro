(function(){
  function qs(el, s){ return el.querySelector(s); }
  function qsa(el, s){ return Array.prototype.slice.call(el.querySelectorAll(s)); }

  function Gallery(root){
    if(!root) return;
    var stageImg = qs(root, '.rep-g-stage .rep-g-current');
    var prevBtn  = qs(root, '.rep-g-prev');
    var nextBtn  = qs(root, '.rep-g-next');
    var thumbs   = qsa(root, '.rep-gt-thumb');

    var lb      = document.querySelector('[data-rep-lightbox]');
    var lbImg   = lb ? qs(lb, '.rep-lb-img') : null;
    var lbPrev  = lb ? qs(lb, '.rep-lb-prev') : null;
    var lbNext  = lb ? qs(lb, '.rep-lb-next') : null;
    var lbClose = lb ? qs(lb, '.rep-lb-close') : null;
    var lbThumbs= lb ? qsa(lb, '.rep-lb-thumb') : [];

    var index = 0;

    function setIndex(i){
      if(!thumbs.length) return;
      index = (i + thumbs.length) % thumbs.length;
      var th = thumbs[index];
      var med = th.getAttribute('data-med');
      var full= th.getAttribute('data-full');
      stageImg.src = med;
      stageImg.setAttribute('data-full', full);
      thumbs.forEach(function(t){ t.classList.remove('is-active'); });
      th.classList.add('is-active');
      lbThumbs.forEach(function(t){ t.classList.remove('is-active'); });
      if(lbThumbs[index]) lbThumbs[index].classList.add('is-active');
    }

    function next(){ setIndex(index+1); }
    function prev(){ setIndex(index-1); }

    thumbs.forEach(function(t,i){
      t.addEventListener('click', function(){ setIndex(i); });
    });

    if(nextBtn) nextBtn.addEventListener('click', next);
    if(prevBtn) prevBtn.addEventListener('click', prev);

    // Lightbox
    function openLB(){
      if(!lb || !stageImg) return;
      lbImg.src = stageImg.getAttribute('data-full') || stageImg.src;
      lb.removeAttribute('hidden');
      document.body.style.overflow='hidden';
    }
    function closeLB(){
      if(!lb) return;
      lb.setAttribute('hidden','');
      document.body.style.overflow='';
    }
    function lbNextFn(){
      next(); lbImg.src = stageImg.getAttribute('data-full') || stageImg.src;
    }
    function lbPrevFn(){
      prev(); lbImg.src = stageImg.getAttribute('data-full') || stageImg.src;
    }

    if(stageImg) stageImg.addEventListener('click', openLB);
    if(lbClose) lbClose.addEventListener('click', closeLB);
    if(lbNext)  lbNext.addEventListener('click', lbNextFn);
    if(lbPrev)  lbPrev.addEventListener('click', lbPrevFn);

    lbThumbs.forEach(function(t,i){
      t.addEventListener('click', function(){
        setIndex(i);
        lbImg.src = stageImg.getAttribute('data-full') || stageImg.src;
      });
    });

    // Teclado
    document.addEventListener('keydown', function(e){
      if(lb && !lb.hasAttribute('hidden')){
        if(e.key==='Escape') closeLB();
        if(e.key==='ArrowRight') lbNextFn();
        if(e.key==='ArrowLeft')  lbPrevFn();
      } else {
        if(e.key==='ArrowRight') next();
        if(e.key==='ArrowLeft')  prev();
      }
    });

    // Mini carrusel de thumbs (scroll con botones)
    var strip = qs(root, '.rep-gt-strip');
    var tPrev = qs(root, '.rep-gt-prev');
    var tNext = qs(root, '.rep-gt-next');
    if(strip && tPrev && tNext){
      tPrev.addEventListener('click', function(){ strip.scrollBy({left:-200,behavior:'smooth'}); });
      tNext.addEventListener('click', function(){ strip.scrollBy({left:200,behavior:'smooth'}); });
    }

    // Inicial
    setIndex(0);
  }

  document.addEventListener('DOMContentLoaded', function(){
    var g = document.querySelector('[data-rep-gallery]');
    if(g) Gallery(g);
  });
})();