/* ==========================================================================
   FICHEIRO: js/graficos.js
   Para que serve: desenha os gráficos (Chart.js) do painel Início. Procura
   todos os <canvas data-tipo="..."> e usa os atributos data-labels e
   data-valores (que o PHP preenche com dados da base de dados).
   Precisa do ficheiro js/vendor/chart.umd.js carregado antes (feito em index.php).
   CSS relacionado: ficheiro `css/style.css`, secção `extras` (.graficos, .grafico).
   ========================================================================== */
(function () {
  'use strict';
  if (typeof Chart === 'undefined') return;

  var cores = ['#0b7a6b', '#f2c230', '#b3261e', '#2b6cb0', '#7a4fb3', '#5b6b73', '#e07a1f'];
  Chart.defaults.color = getComputedStyle(document.body).color;
  Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;

  document.querySelectorAll('canvas[data-tipo]').forEach(function (cv) {
    var labels = JSON.parse(cv.getAttribute('data-labels') || '[]');
    var valores = JSON.parse(cv.getAttribute('data-valores') || '[]');
    var total = valores.reduce(function (a, b) { return a + b; }, 0);

    if (!labels.length || total === 0) {            // nada para mostrar
      var p = document.createElement('p');
      p.className = 'hint';
      p.textContent = (cv.getAttribute('data-titulo') || 'Gráfico') + ': ainda sem dados.';
      cv.replaceWith(p);
      return;
    }

    var tipo = cv.getAttribute('data-tipo');
    new Chart(cv, {
      type: tipo,
      data: { labels: labels, datasets: [{ data: valores, backgroundColor: cores, borderWidth: 0 }] },
      options: {
        responsive: true,
        animation: { duration: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 900 },
        plugins: {
          title: { display: true, text: cv.getAttribute('data-titulo') || '' },
          legend: { display: tipo !== 'bar', position: 'bottom' }
        },
        scales: tipo === 'bar' ? { y: { beginAtZero: true, ticks: { precision: 0 } } } : {}
      }
    });
  });
})();
