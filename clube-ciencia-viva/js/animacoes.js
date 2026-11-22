/* ==========================================================================
   FICHEIRO: js/animacoes.js
   Para que serve: as animações simples e pequenos cuidados de interface,
   carregado em TODAS as páginas (ver includes/layout.php, função rodape).
      1. Os números do painel (data-count) "contam" até ao valor final.
      2. As mensagens amarelas (.flash) desaparecem sozinhas ao fim de uns segundos.
      3. Formulários com data-confirm pedem confirmação antes de enviar (ex.: Apagar).
      4. Depois de enviares um formulário, o botão fica desativado (evita cliques duplos).
      5. Os filtros com data-auto-submit submetem o formulário ao mudar.
      6. O menu recolhe/abre nos ecrãs pequenos (botão "Menu").
   A entrada suave das caixas .fade-in é feita só com CSS (secção `extras`),
   para o conteúdo continuar visível mesmo sem JavaScript. O menu também
   funciona sem JavaScript: nesse caso fica sempre aberto.
   ========================================================================== */
(function () {
  'use strict';
  document.documentElement.classList.add('js');
  var reduzir = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* --- 1. Números que contam -------------------------------------------- */
  document.querySelectorAll('[data-count]').forEach(function (el) {
    var alvo = parseInt(el.getAttribute('data-count'), 10) || 0;
    if (reduzir || alvo === 0) { el.textContent = alvo; return; }
    var inicio = null, duracao = 900;
    function passo(t) {
      if (inicio === null) inicio = t;
      var p = Math.min((t - inicio) / duracao, 1);
      el.textContent = Math.round(alvo * (1 - Math.pow(1 - p, 3)));      // abranda no fim
      if (p < 1) requestAnimationFrame(passo);
    }
    el.textContent = '0';
    requestAnimationFrame(passo);
  });

  /* --- 2. Mensagens que desaparecem (só as normais; os erros ficam) ------ */
  document.querySelectorAll('.flash[role="status"]').forEach(function (m) {
    setTimeout(function () {
      m.style.transition = 'opacity .6s ease'; m.style.opacity = '0';
      setTimeout(function () { m.remove(); }, 700);
    }, 6000);
  });

  /* --- 3 e 4. Confirmar e evitar envio duplo ----------------------------- */
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      var msg = f.getAttribute('data-confirm');
      if (msg && !window.confirm(msg)) { ev.preventDefault(); return; }
      if (f.hasAttribute('data-sem-loading') || ev.defaultPrevented) return;
      setTimeout(function () {
        f.querySelectorAll('button:not([type=button])').forEach(function (b) { b.disabled = true; });
      }, 0);
    });
  });

  /* --- 5. Filtros que submetem sozinhos ---------------------------------- */
  document.querySelectorAll('select[data-auto-submit]').forEach(function (s) {
    s.addEventListener('change', function () { if (s.form) s.form.submit(); });
  });

  /* --- 6. Menu em ecrãs pequenos ---------------------------------------- */
  var lateral = document.querySelector('.side');
  var botaoMenu = document.querySelector('.menu-btn');
  if (lateral && botaoMenu) {
    function fecharMenu() {
      lateral.classList.remove('menu-aberto');
      botaoMenu.setAttribute('aria-expanded', 'false');
    }
    botaoMenu.addEventListener('click', function () {
      var aberto = lateral.classList.toggle('menu-aberto');
      botaoMenu.setAttribute('aria-expanded', aberto ? 'true' : 'false');
    });
    lateral.querySelectorAll('nav a').forEach(function (a) {
      a.addEventListener('click', fecharMenu);
    });
    document.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape' && lateral.classList.contains('menu-aberto')) {
        fecharMenu();
        botaoMenu.focus();
      }
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth > 760) fecharMenu();
    });
  }

  /* --- 7. Aviso quando uma tabela tem colunas escondidas ------------------ */
  function marcarTabelas() {
    document.querySelectorAll('.scroll').forEach(function (s) {
      var precisa = s.scrollWidth > s.clientWidth + 4;
      var seguinte = s.nextElementSibling;
      var temAviso = seguinte && seguinte.classList.contains('scroll-hint');
      if (precisa && !temAviso) {
        var aviso = document.createElement('p');
        aviso.className = 'hint scroll-hint';
        aviso.textContent = 'Desliza a tabela para ver as restantes colunas →';
        s.insertAdjacentElement('afterend', aviso);
      } else if (!precisa && temAviso) {
        seguinte.remove();
      }
    });
  }
  window.addEventListener('load', marcarTabelas);
  var esperaTabela;
  window.addEventListener('resize', function () {
    clearTimeout(esperaTabela);
    esperaTabela = setTimeout(marcarTabelas, 200);
  });
})();
