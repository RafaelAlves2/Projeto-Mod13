/* ==========================================================================
   FICHEIRO: js/assistente.js
   Para que serve: faz a conversa do Assistente IA funcionar sem recarregar a
   página. Ao enviar, mostra a tua pergunta, uma mensagem "A pensar…", chama
   api/ia.php e troca essa mensagem pela resposta (ou por um erro).
   Enter envia; Shift+Enter muda de linha.
   O texto é sempre inserido com textContent (nunca como HTML), por segurança.
   CSS relacionado: ficheiro `css/style.css`, secção `extras` (.chat, .msg, .eu, .ia, .erro).
   ========================================================================== */
(function () {
  'use strict';
  var form = document.getElementById('form-ia');
  if (!form) return;
  var chat = document.getElementById('chat');
  var campo = document.getElementById('pergunta');
  var cat = document.getElementById('categoria');
  var botao = document.getElementById('enviar');
  var base = (document.querySelector('meta[name="base"]') || {}).content || '';
  var csrf = (document.querySelector('meta[name="csrf"]') || {}).content || '';

  function mensagem(texto, classe) {
    var d = document.createElement('div');
    d.className = 'msg ' + classe;
    d.textContent = texto;
    chat.appendChild(d);
    chat.scrollTop = chat.scrollHeight;
    return d;
  }
  chat.scrollTop = chat.scrollHeight;

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var pergunta = campo.value.trim();
    if (!pergunta) return;
    var inicial = document.getElementById('msg-inicial');
    if (inicial) inicial.remove();

    mensagem(pergunta, 'eu');
    var espera = mensagem('A pensar… Pode demorar até 2 minutos.', 'ia');
    campo.value = '';
    botao.disabled = true;

    fetch(base + '/api/ia.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
      body: JSON.stringify({ pergunta: pergunta, categoria: cat.value })
    })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { espera.textContent = d.resposta; }
        else { espera.className = 'msg erro'; espera.textContent = d.erro || 'Ocorreu um erro.'; }
      })
      .catch(function () { espera.className = 'msg erro'; espera.textContent = 'Não consegui obter resposta. Verifica a ligação e tenta outra vez.'; })
      .then(function () { botao.disabled = false; chat.scrollTop = chat.scrollHeight; campo.focus(); });
  });

  campo.addEventListener('keydown', function (ev) {
    if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); form.requestSubmit(); }
  });
})();
