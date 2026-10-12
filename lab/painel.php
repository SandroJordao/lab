<?php
/*
 * Painel do localhost: varre o htdocs e monta os dados de cada projeto
 * (stack, git, README, atividade). Tudo lido direto dos arquivos, sem
 * rodar comando externo, para a página abrir rápido.
 */

date_default_timezone_set('America/Sao_Paulo');

const PAINEL_IGNORAR = ['.', '..', 'lab', 'index.php', 'favicon.ico', 'Thumbs.db', 'desktop.ini'];
const PAINEL_MESES   = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

function painel_varrer(string $raiz): array
{
	$pastas   = [];
	$arquivos = [];

	foreach (scandir($raiz) as $nome) {
		if (in_array($nome, PAINEL_IGNORAR, true) || $nome[0] === '.') {
			continue;
		}

		$caminho = $raiz . '/' . $nome;

		if (is_dir($caminho)) {
			$pastas[] = painel_pasta($nome, $caminho);
		} else {
			$arquivos[] = [
				'nome'    => $nome,
				'url'     => '/' . rawurlencode($nome),
				'tamanho' => filesize($caminho),
				'ts'      => filemtime($caminho),
			];
		}
	}

	painel_cores($pastas);
	usort($pastas, fn($a, $b) => $b['ts'] <=> $a['ts']);

	return [$pastas, $arquivos];
}

function painel_pasta(string $nome, string $caminho): array
{
	$tem = fn(string $p) => file_exists($caminho . '/' . $p);

	$stack = [];
	if ($tem('artisan'))                                                    $stack[] = 'Laravel';
	if ($tem('codeigniter') || $tem('CI') || $tem('core/CodeIgniter.php')) $stack[] = 'CodeIgniter';
	if ($tem('composer.json'))                                              $stack[] = 'Composer';
	if ($tem('package.json'))                                               $stack[] = 'Node';
	if ($tem('api'))                                                        $stack[] = 'API';
	if (glob($caminho . '/*.sql'))                                          $stack[] = 'SQL';

	if ($tem('index.php')) {
		$stack[] = 'PHP';
		$tipo    = 'Aplicação PHP';
	} elseif ($tem('index.html') || $tem('index.htm')) {
		$stack[] = 'HTML';
		$tipo    = 'Site estático';
	} else {
		$tipo = 'Pasta';
	}

	$git    = painel_git($caminho);
	$readme = painel_readme($caminho);
	$ts     = $git['ultimo']['ts'] ?? filemtime($caminho);

	$spark = array_fill(0, 30, 0);
	$hoje  = strtotime('today');
	foreach ($git['commits'] ?? [] as $c) {
		$dias = (int) round(($hoje - strtotime(date('Y-m-d', $c))) / 86400);
		if ($dias >= 0 && $dias < 30) {
			$spark[29 - $dias]++;
		}
	}

	$busca = mb_strtolower(implode(' ', array_filter([
		$nome, implode(' ', $stack), $git['branch'] ?? '', $readme, $git['ultimo']['msg'] ?? '', $tipo,
	])));

	return [
		'nome'    => $nome,
		'url'     => '/' . rawurlencode($nome) . '/',
		'caminho' => str_replace('\\', '/', realpath($caminho)),
		'familia' => painel_familia($nome),
		'sigla'   => painel_sigla($nome),
		'stack'   => $stack,
		'tipo'    => $tipo,
		'desc'    => $readme,
		'git'     => $git,
		'ts'      => $ts,
		'spark'   => $spark,
		'busca'   => $busca,
	];
}

function painel_git(string $caminho): ?array
{
	$dir = $caminho . '/.git';
	if (!is_dir($dir)) {
		return null;
	}

	$head   = trim((string) @file_get_contents($dir . '/HEAD'));
	$branch = str_starts_with($head, 'ref: refs/heads/') ? substr($head, 16) : substr($head, 0, 7);

	// O reflog guarda cada movimento do HEAD:
	// <antigo> <novo> Nome <email> <timestamp> <fuso>\t<ação>: <mensagem>
	$commits = [];
	$ultimo  = null;
	$linhas  = @file($dir . '/logs/HEAD', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

	foreach ($linhas as $linha) {
		if (!preg_match('/^\S+ \S+ .*? <[^>]*> (\d+) [+-]\d{4}\t([^:]+): (.*)$/', $linha, $m)) {
			continue;
		}
		$ts   = (int) $m[1];
		$acao = $m[2];

		if (str_starts_with($acao, 'commit')) {
			$commits[] = $ts;
			$ultimo    = ['msg' => $m[3], 'ts' => $ts];
		} elseif (str_starts_with($acao, 'pull') || str_starts_with($acao, 'merge') || str_starts_with($acao, 'clone')) {
			$ultimo = ['msg' => $acao . ': ' . $m[3], 'ts' => $ts];
		}
	}

	$remoto = null;
	$config = (string) @file_get_contents($dir . '/config');
	if (preg_match('/\[remote "origin"\][^\[]*?url\s*=\s*(\S+)/s', $config, $m)) {
		$url = preg_replace('#^git@([^:]+):#', 'https://$1/', $m[1]);
		$url = preg_replace('#^https?://[^@/]+@#', 'https://', $url); // nunca expõe token embutido na URL
		$url = preg_replace('#\.git$#', '', $url);
		if (str_starts_with($url, 'http')) {
			$remoto = $url;
		}
	}

	return ['branch' => $branch, 'commits' => $commits, 'ultimo' => $ultimo, 'remoto' => $remoto];
}

function painel_readme(string $caminho): ?string
{
	$arquivo = $caminho . '/README.md';
	if (!is_file($arquivo)) {
		return null;
	}

	$codigo = false;
	foreach (file($arquivo, FILE_IGNORE_NEW_LINES) as $linha) {
		$linha = trim($linha);
		if (str_starts_with($linha, '```')) {
			$codigo = !$codigo;
			continue;
		}
		if ($codigo || $linha === '' || preg_match('/^[#!\[<|>\-=*]/', $linha)) {
			continue;
		}
		$texto = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $linha);
		$texto = str_replace(['**', '__', '`'], '', $texto);
		return mb_strimwidth($texto, 0, 150, '…');
	}

	return null;
}

function painel_familia(string $nome): string
{
	$base = preg_replace('/^www\./i', '', strtolower($nome));
	return preg_split('/[-_.]/', $base)[0];
}

function painel_sigla(string $nome): string
{
	$partes = array_values(array_filter(
		preg_split('/[-_.\s]+/', strtolower($nome)),
		fn($p) => $p !== '' && !in_array($p, ['www', 'com', 'br', 'old'], true)
	));

	if (count($partes) >= 2) {
		return mb_strtoupper(mb_substr($partes[0], 0, 1) . mb_substr($partes[1], 0, 1));
	}

	$p = $partes[0] ?? $nome;
	return mb_strtoupper(mb_substr($p, 0, 1)) . mb_substr($p, 1, 1);
}

// Projetos da mesma família (glooom-*, trackone-*) ganham tons vizinhos.
function painel_cores(array &$pastas): void
{
	$posicao = [];
	foreach ($pastas as &$p) {
		$f        = $p['familia'];
		$i        = $posicao[$f] = ($posicao[$f] ?? -1) + 1;
		$p['hue'] = (crc32($f) % 360 + $i * 22) % 360;
	}
}

function painel_atividade(array $projetos, int $semanas): array
{
	$porDia = [];
	foreach ($projetos as $p) {
		foreach ($p['git']['commits'] ?? [] as $ts) {
			$d          = date('Y-m-d', $ts);
			$porDia[$d] = ($porDia[$d] ?? 0) + 1;
		}
	}

	$hoje   = new DateTimeImmutable('today');
	$inicio = $hoje->modify('-' . ($semanas * 7 - 1) . ' days');
	$inicio = $inicio->modify('-' . $inicio->format('w') . ' days'); // volta pro domingo

	$dias  = [];
	$total = 0;
	for ($d = $inicio; $d <= $hoje; $d = $d->modify('+1 day')) {
		$n      = $porDia[$d->format('Y-m-d')] ?? 0;
		$total += $n;
		$dias[] = [
			'n'     => $n,
			'nivel' => $n === 0 ? 0 : ($n <= 2 ? 1 : ($n <= 5 ? 2 : ($n <= 9 ? 3 : 4))),
			'rotulo' => $d->format('j') . ' ' . PAINEL_MESES[$d->format('n') - 1],
			'hoje'  => $d == $hoje,
		];
	}

	// Sequência de dias seguidos com commit (conta a partir de ontem se hoje ainda está zerado)
	$seq = 0;
	$d   = isset($porDia[$hoje->format('Y-m-d')]) ? $hoje : $hoje->modify('-1 day');
	while (isset($porDia[$d->format('Y-m-d')])) {
		$seq++;
		$d = $d->modify('-1 day');
	}

	$destaque = null;
	foreach ($projetos as $p) {
		$soma = array_sum($p['spark']);
		if ($soma > 0 && $soma > ($destaque['n'] ?? 0)) {
			$destaque = ['nome' => $p['nome'], 'n' => $soma];
		}
	}

	return [
		'dias'      => $dias,
		'total'     => $total,
		'sequencia' => $seq,
		'hoje'      => $porDia[$hoje->format('Y-m-d')] ?? 0,
		'destaque'  => $destaque,
	];
}

function painel_servidor(): array
{
	$mysql = @fsockopen('127.0.0.1', 3306, $errno, $errstr, 0.25);
	if ($mysql) {
		fclose($mysql);
	}

	preg_match('#Apache/([\d.]+)#', $_SERVER['SERVER_SOFTWARE'] ?? '', $apache);

	// IP da rede local, para abrir os projetos no celular
	$ips = @gethostbynamel(gethostname()) ?: [];
	$lan = null;
	foreach ($ips as $ip) {
		if (preg_match('/^(192\.168\.|10\.|172\.(1[6-9]|2\d|3[01])\.)/', $ip)) {
			$lan = $ip;
			break;
		}
	}
	$lan  ??= $ips[0] ?? '127.0.0.1';
	$porta = (int) ($_SERVER['SERVER_PORT'] ?? 80);

	$livre = @disk_free_space('C:') ?: 0;
	$total = @disk_total_space('C:') ?: 0;

	return [
		'apache'      => $apache[1] ?? null,
		'php'         => PHP_VERSION,
		'xdebug'      => extension_loaded('xdebug'),
		'mysql'       => (bool) $mysql,
		'lan'         => 'http://' . $lan . ($porta !== 80 ? ':' . $porta : ''),
		'lan_ip'      => $lan,
		'disco_livre' => $livre,
		'disco_total' => $total,
	];
}

function painel_bytes(float $b): string
{
	$u = ['B', 'KB', 'MB', 'GB', 'TB'];
	$i = 0;
	while ($b >= 1024 && $i < count($u) - 1) {
		$b /= 1024;
		$i++;
	}
	return number_format($b, $i >= 3 ? 0 : 1, ',', '.') . ' ' . $u[$i];
}

function painel_spark(array $valores): string
{
	$max  = max(1, max($valores));
	$barras = '';
	foreach ($valores as $i => $v) {
		$h       = $v === 0 ? 2 : 4 + round($v / $max * 18);
		$classe  = $v === 0 ? ' class="zero"' : '';
		$barras .= sprintf('<rect%s x="%d" y="%d" width="3" height="%d" rx="1"/>', $classe, $i * 4, 22 - $h, $h);
	}
	return '<svg class="spark" viewBox="0 0 120 22" preserveAspectRatio="none" aria-hidden="true">' . $barras . '</svg>';
}

function painel_icone(string $nome): string
{
	static $icones = [
		'estrela'  => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
		'branch'   => '<line x1="6" x2="6" y1="3" y2="15"/><circle cx="18" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M18 9a9 9 0 0 1-9 9"/>',
		'codigo'   => '<polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/>',
		'externo'  => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'celular'  => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
		'copiar'   => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
		'busca'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'sol'      => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/>',
		'lua'      => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
		'banco'    => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/>',
		'arquivo'  => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/>',
		'fechar'   => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
		'chama'    => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
		'disco'    => '<line x1="22" x2="2" y1="12" y2="12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" x2="6.01" y1="16" y2="16"/><line x1="10" x2="10.01" y1="16" y2="16"/>',
	];

	return '<svg class="i i-' . $nome . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($icones[$nome] ?? '') . '</svg>';
}
