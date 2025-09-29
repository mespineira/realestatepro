(function(){
  // === Scroll suave de anclas (Fotos, Mapa, Ficha) ===
  document.addEventListener('click', function(e){
    var a = e.target.closest('.rep-anchorbar a[href^="#"]');
    if(!a) return;
    var id = a.getAttribute('href');
    var target = document.querySelector(id);
    if(!target) return;
    e.preventDefault();
    // CORRECCIÓN: Ajustado el offset para compensar la barra de anclas y el header fijo.
    var y = target.getBoundingClientRect().top + window.pageYOffset - 150; 
    window.scrollTo({ top: y, behavior: 'smooth' });

    // Si el enlace es "Mapa", disparamos init del mapa
    if (a.hasAttribute('data-rep-goto-map')) {
      document.dispatchEvent(new Event('rep:open-map'));
    }
  });

  // === Inicializar mapa cuando la sección entra en viewport ===
  var mapa = document.querySelector('#mapa');
  if (mapa) {
    var once = false;
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(ent){
        if (ent.isIntersecting && !once) {
          once = true;
          document.dispatchEvent(new Event('rep:open-map'));
          io.disconnect();
        }
      });
    }, { threshold: 0.2 });
    io.observe(mapa);
  }
})();

(function(){
  // === Simulador de hipoteca ===
  function fmt(n){ return (Math.round(n*100)/100).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function calc(box){
    var price = parseFloat(box.querySelector('[data-m="price"]').value||0);
    var down  = parseFloat(box.querySelector('[data-m="down"]').value||0);
    var years = parseFloat(box.querySelector('[data-m="years"]').value||30);
    var rate  = parseFloat(box.querySelector('[data-m="rate"]').value||2.5)/100;
    var taxPct= 0.10; // 10% por defecto
    var taxes = price * taxPct;
    var loan  = Math.max(0, price + taxes - down);
    var n = years*12, i = rate/12;
    var quota = (i>0) ? (loan * (i*Math.pow(1+i,n)) / (Math.pow(1+i,n)-1)) : (loan/n);

    box.querySelector('[data-m="taxes"]').textContent = fmt(taxes);
    box.querySelector('[data-m="loan"]').textContent  = fmt(loan);
    box.querySelector('[data-m="quota"]').textContent = fmt(quota);
  }
  function init(){
    document.querySelectorAll('[data-rep-mortgage]').forEach(function(box){
      box.addEventListener('input', function(e){
        if(e.target.matches('input')) calc(box);
      });
      calc(box);
    });
  }
  document.addEventListener('DOMContentLoaded', init);
})();
