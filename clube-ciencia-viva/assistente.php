<?php
/* ==========================================================================
   FICHEIRO: assistente.php
   Para que serve: a página do ASSISTENTE DE IA (perguntas sobre ciência e
   sobre o clube). A página só desenha a conversa; o envio da pergunta é feito
   pelo js/assistente.js, que chama api/ia.php (esse é que fala com o Ollama
   e guarda a pergunta e a resposta na tabela pergunta_ia).
   Cada utilizador vê apenas o seu próprio histórico.
   ========================================================================== */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
$u = exigir_login();

const CATEGORIAS_IA = ['Geral', 'Física', 'Química', 'Biologia', 'Programação', 'Ambiente', 'Sobre o clube'];

/* Últimas 10 perguntas do utilizador, da mais antiga para a mais recente. */
$historico = array_reverse(consulta('SELECT pergunta, resposta FROM pergunta_ia WHERE id_utilizador = ? ORDER BY id_pergunta DESC LIMIT 10',
                                    [$u['id_utilizador']]));

cabecalho('Assistente IA', 'assistente');
/* CSS deste bloco:
   Encontras o CSS da caixa (.doc, .lead) no ficheiro `css/style.css`, na secção `cartoes`.
   Encontras o CSS da conversa (.chat, .msg, .eu, .ia, .erro) no ficheiro `css/style.css`, na secção `extras`.
   Encontras o CSS do formulário (.stack, select, textarea, .btn) no ficheiro `css/style.css`, na secção `formularios`.
   Encontras o CSS do texto pequeno (.hint) no ficheiro `css/style.css`, na secção `avisos`. */
?>
<section class="doc wide fade-in">
  <h1>Assistente IA</h1>
  <p class="lead">Pergunta sobre ciência, experiências ou sobre o clube.</p>
  <div class="chat" id="chat" aria-live="polite">
    <?php if (!$historico): ?><div class="msg ia" id="msg-inicial">Olá! Sou o assistente do Clube de Ciência Viva. Em que te posso ajudar?</div><?php endif; ?>
    <?php foreach ($historico as $h): ?>
      <div class="msg eu"><?= e($h['pergunta']) ?></div>
      <div class="msg ia"><?= e($h['resposta']) ?></div>
    <?php endforeach; ?>
  </div>
  <form class="stack" id="form-ia" method="post" action="" data-sem-loading>
    <label>Categoria
      <select id="categoria" name="categoria"><?php foreach (CATEGORIAS_IA as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select>
    </label>
    <label>A tua pergunta
      <textarea id="pergunta" name="pergunta" rows="3" maxlength="1000" required placeholder="Ex.: Porque é que o céu é azul?"></textarea>
    </label>
    <div class="linha-fim"><button class="btn" id="enviar">Perguntar</button></div>
  </form>
  <p class="hint">As respostas são geradas por IA e podem ter erros. Confirma sempre com o professor, sobretudo em experiências com material perigoso.</p>
</section>
<?php rodape(['assistente.js']); ?>
