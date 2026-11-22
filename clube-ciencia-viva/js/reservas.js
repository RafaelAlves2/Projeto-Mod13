/* ==========================================================================
   FICHEIRO: js/reservas.js
   Para que serve: no formulário de Nova reserva, quando escolhes o início e
   o fim, pergunta a api/disponibilidade.php quantas unidades de cada
   equipamento estão livres e mostra-as na coluna "Livres no período".
   Também limita o campo "Quantidade" a esse máximo.
   (O servidor volta sempre a verificar tudo ao enviar: isto é só ajuda visual.)
   CSS relacionado: ficheiro `css/style.css`, secção `tabelas` (a tabela) e `formularios` (os campos).
   ========================================================================== */
(function () {
  'use strict';
  var ini = document.getElementById('inicio'), fim = document.getElementById('fim');
  if (!ini || !fim) return;
  var base = (document.querySelector('meta[name="base"]') || {}).content || '';
  var espera = null;

  function atualizar() {
    var celulas = document.querySelectorAll('td[data-disp]');
    if (!ini.value || !fim.value || fim.value <= ini.value) {
      celulas.forEach(function (c) { c.textContent = '—'; });
      return;
    }
    celulas.forEach(function (c) { c.textContent = '…'; });
    fetch(base + '/api/disponibilidade.php?inicio=' + encodeURIComponent(ini.value) + '&fim=' + encodeURIComponent(fim.value),
          { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.ok) { celulas.forEach(function (c) { c.textContent = '—'; }); return; }
        celulas.forEach(function (c) {
          var id = c.getAttribute('data-disp');
          var livres = d.livres[id] !== undefined ? d.livres[id] : 0;
          c.textContent = livres;
          var campo = document.querySelector('input[name="qtd[' + id + ']"]');
          if (campo) {
            campo.max = livres;
            if (parseInt(campo.value || '0', 10) > livres) campo.value = livres;
          }
        });
      })
      .catch(function () { celulas.forEach(function (c) { c.textContent = '—'; }); });
  }

  function agendar() { clearTimeout(espera); espera = setTimeout(atualizar, 250); }
  ini.addEventListener('change', agendar);
  fim.addEventListener('change', agendar);
  atualizar();
})();
