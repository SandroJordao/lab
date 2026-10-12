<?php
require __DIR__ . '/lab/painel.php';

[$projetos, $arquivos] = painel_varrer(__DIR__);
$servidor    = painel_servidor();
$atividade   = painel_atividade($projetos, 26);
$hoje        = strtotime('today');
$mexidosHoje = count(array_filter($projetos, fn($p) => $p['ts'] >= $hoje));
$comGit      = count(array_filter($projetos, fn($p) => $p['git']));
$discoUso    = $servidor['disco_total'] ? 1 - $servidor['disco_livre'] / $servidor['disco_total'] : 0;
$v           = fn(string $f) => filemtime(__DIR__ . '/lab/' . $f);
$e           = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
$i           = 'painel_icone';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Sandro Jordão · localhost</title>
	<script>try { var t = localStorage.getItem('painel:tema'); if (t) document.documentElement.dataset.theme = JSON.parse(t); } catch (e) {}</script>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,700;12..96,800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
	<link rel="stylesheet" href="/lab/painel.css?v=<?= $v('painel.css') ?>">
	<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><linearGradient id='g' x2='1' y2='1'><stop offset='0' stop-color='%238b5cf6'/><stop offset='1' stop-color='%2306b6d4'/></linearGradient></defs><rect width='64' height='64' rx='16' fill='url(%23g)'/><text x='32' y='43' font-family='Arial' font-weight='900' font-size='28' text-anchor='middle' fill='white'>SJ</text></svg>">
</head>
<body data-lan="<?= $e($servidor['lan']) ?>">
	<div class="aurora" aria-hidden="true"></div>

	<main class="wrap">
		<header class="topo">
			<div>
				<p class="eyebrow"><span class="ponto-vivo"></span><span>localhost · <span id="saudacao">Olá</span>, Sandro</span></p>
				<h1><span>S</span>andro <span>J</span>ordão</h1>
				<p class="resumo">
					<strong><?= count($projetos) ?></strong> projetos,
					<strong><?= $comGit ?></strong> com git,
					<strong><?= $mexidosHoje ?></strong> mexidos hoje
				</p>
			</div>
			<div class="relogio">
				<time id="hora">--:--</time>
				<span id="data"></span>
			</div>
		</header>

		<section class="status" aria-label="Status do servidor">
			<span class="chip"><i class="led on"></i>Apache <?= $e($servidor['apache'] ?? '') ?></span>
			<span class="chip"><i class="led on"></i>PHP <?= $e($servidor['php']) ?><?= $servidor['xdebug'] ? ' + Xdebug' : '' ?></span>
			<span class="chip"><i class="led <?= $servidor['mysql'] ? 'on' : 'off' ?>"></i>MySQL <?= $servidor['mysql'] ? 'online' : 'parado' ?></span>
			<?php if ($servidor['disco_total']): ?>
				<span class="chip" title="<?= round($discoUso * 100) ?>% do disco C: em uso">
					<?= $i('disco') ?><?= painel_bytes($servidor['disco_livre']) ?> livres
					<span class="medidor<?= $discoUso > .9 ? ' cheio' : '' ?>"><i style="width: <?= round($discoUso * 100) ?>%"></i></span>
				</span>
			<?php endif ?>
			<button class="chip chip-btn" data-qr="<?= $e($servidor['lan']) ?>/" data-titulo="Este painel">
				<?= $i('celular') ?><?= $e($servidor['lan_ip']) ?>
			</button>
			<a class="chip chip-btn" href="/phpmyadmin/"><?= $i('banco') ?>phpMyAdmin</a>
			<a class="chip chip-btn" href="vscode://file/<?= $e(str_replace('\\', '/', __DIR__)) ?>"><?= $i('codigo') ?>htdocs no VS Code</a>
		</section>

		<section class="pulso" aria-label="Atividade">
			<div class="pulso-info">
				<h2>Pulso</h2>
				<dl>
					<div><dt>commits em 6 meses</dt><dd><?= $atividade['total'] ?></dd></div>
					<div><dt>hoje</dt><dd><?= $atividade['hoje'] ?></dd></div>
					<div><dt>sequência</dt><dd><?= $atividade['sequencia'] ?><small><?= $atividade['sequencia'] === 1 ? 'dia' : 'dias' ?></small><?= $atividade['sequencia'] >= 3 ? $i('chama') : '' ?></dd></div>
				</dl>
				<?php if ($atividade['destaque']): ?>
					<p class="destaque">Em alta nos últimos 30 dias: <strong><?= $e($atividade['destaque']['nome']) ?></strong> com <?= $atividade['destaque']['n'] ?> commits</p>
				<?php endif ?>
			</div>
			<div class="heatmap" role="img" aria-label="<?= $atividade['total'] ?> commits nas últimas 26 semanas">
				<?php foreach ($atividade['dias'] as $d): ?>
					<i class="n<?= $d['nivel'] ?><?= $d['hoje'] ? ' hoje' : '' ?>" title="<?= $d['rotulo'] ?>: <?= $d['n'] ?> <?= $d['n'] === 1 ? 'commit' : 'commits' ?>"></i>
				<?php endforeach ?>
			</div>
		</section>

		<div class="barra">
			<label class="busca">
				<?= $i('busca') ?>
				<input id="busca" type="search" placeholder="Buscar projeto, stack, branch, commit..." autocomplete="off" spellcheck="false">
				<kbd>/</kbd>
			</label>
			<div class="segmentos" role="radiogroup" aria-label="Ordenar">
				<button role="radio" data-ordem="recentes">Recentes</button>
				<button role="radio" data-ordem="az">A-Z</button>
				<button role="radio" data-ordem="familia">Família</button>
			</div>
			<button id="tema" class="icone-btn" title="Alternar tema"><?= $i('sol') ?><?= $i('lua') ?></button>
		</div>

		<div class="grid" id="grid">
			<?php foreach ($projetos as $n => $p): $git = $p['git']; ?>
				<article class="card<?= $p['ts'] >= $hoje ? ' quente' : '' ?>"
					style="--h: <?= $p['hue'] ?>; --i: <?= $n ?>"
					data-nome="<?= $e($p['nome']) ?>"
					data-familia="<?= $e($p['familia']) ?>"
					data-ts="<?= $p['ts'] ?>"
					data-busca="<?= $e($p['busca']) ?>">
					<a class="card-link" href="<?= $e($p['url']) ?>" aria-label="Abrir <?= $e($p['nome']) ?>"></a>

					<div class="card-topo">
						<span class="sigla"><?= $e($p['sigla']) ?></span>
						<div class="acoes">
							<a class="acao" href="vscode://file/<?= $e($p['caminho']) ?>" title="Abrir no VS Code"><?= $i('codigo') ?></a>
							<?php if ($git['remoto'] ?? null): ?>
								<a class="acao" href="<?= $e($git['remoto']) ?>" target="_blank" rel="noopener" title="Abrir repositório"><?= $i('externo') ?></a>
							<?php endif ?>
							<button class="acao" data-acao="qr" title="Abrir no celular"><?= $i('celular') ?></button>
							<button class="acao" data-acao="copiar" data-caminho="<?= $e(str_replace('/', '\\', $p['caminho'])) ?>" title="Copiar caminho"><?= $i('copiar') ?></button>
							<button class="acao estrela" data-acao="fav" title="Fixar no topo" aria-pressed="false"><?= $i('estrela') ?></button>
						</div>
					</div>

					<h2 class="card-nome"><?= $e($p['nome']) ?></h2>
					<p class="card-desc"><?= $e($p['desc'] ?? $p['tipo']) ?></p>

					<?php if ($p['stack']): ?>
						<ul class="tags"><?php foreach ($p['stack'] as $s): ?><li><?= $e($s) ?></li><?php endforeach ?></ul>
					<?php endif ?>

					<?php if ($git): ?><?= painel_spark($p['spark']) ?><?php endif ?>

					<footer class="card-rodape">
						<?php if ($git): ?>
							<span class="branch"><?= $i('branch') ?><?= $e($git['branch']) ?></span>
						<?php else: ?>
							<span class="branch sem-git">sem git</span>
						<?php endif ?>
						<time class="rel" data-ts="<?= $p['ts'] ?>" datetime="<?= date('c', $p['ts']) ?>"></time>
						<?php if ($git['ultimo'] ?? null): ?>
							<span class="msg" title="<?= $e($git['ultimo']['msg']) ?>"><?= $e($git['ultimo']['msg']) ?></span>
						<?php endif ?>
					</footer>
				</article>
			<?php endforeach ?>
		</div>

		<p class="vazio" id="vazio" hidden>
			Nenhum projeto com “<span id="vazio-termo"></span>”.<br>
			<small>Aperte <kbd>Enter</kbd> para tentar <code>localhost/<span id="vazio-url"></span></code> mesmo assim.</small>
		</p>

		<?php if ($arquivos): ?>
			<section class="soltos">
				<h3>Arquivos soltos</h3>
				<div>
					<?php foreach ($arquivos as $a): ?>
						<a class="chip chip-btn" href="<?= $e($a['url']) ?>"><?= $i('arquivo') ?><?= $e($a['nome']) ?> <small><?= painel_bytes($a['tamanho']) ?></small></a>
					<?php endforeach ?>
				</div>
			</section>
		<?php endif ?>

		<footer class="rodape">
			<span><kbd>/</kbd> buscar <kbd>↑</kbd><kbd>↓</kbd> navegar <kbd>Enter</kbd> abrir <kbd>Ctrl</kbd>+<kbd>Enter</kbd> nova aba <kbd>Esc</kbd> limpar</span>
			<span class="segredo" title="psiu">↑↑↓↓←→←→BA</span>
		</footer>
	</main>

	<dialog id="qr">
		<div class="qr-caixa">
			<button class="icone-btn qr-fechar" data-fechar title="Fechar"><?= $i('fechar') ?></button>
			<p class="eyebrow">Abrir no celular</p>
			<h2 id="qr-titulo"></h2>
			<div id="qr-img"></div>
			<code id="qr-url"></code>
			<p class="dica">O celular precisa estar no mesmo Wi-Fi. Se não abrir, libere o Apache no firewall do Windows.</p>
		</div>
	</dialog>

	<div class="toast" id="toast" role="status"></div>

	<script src="/lab/painel.js?v=<?= $v('painel.js') ?>"></script>
</body>
</html>
