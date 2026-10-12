/*
 * Painel do localhost: busca, atalhos, favoritos, tema, QR e um segredo.
 */
(() => {
	const $  = (s, el = document) => el.querySelector(s);
	const $$ = (s, el = document) => [...el.querySelectorAll(s)];

	const guardar = {
		ler(chave, padrao) {
			try {
				const v = localStorage.getItem('painel:' + chave);
				return v === null ? padrao : JSON.parse(v);
			} catch { return padrao; }
		},
		gravar(chave, valor) {
			try { localStorage.setItem('painel:' + chave, JSON.stringify(valor)); } catch {}
		},
	};

	const normalizar = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

	const grid  = $('#grid');
	const cards = $$('.card', grid);
	const busca = $('#busca');
	const root  = document.documentElement;

	cards.forEach((c, i) => {
		c.dataset.indice = normalizar(c.dataset.busca);
		c.style.viewTransitionName = 'card-' + i;
	});

	// Em tela estreita o heatmap rola: começa mostrando as semanas mais recentes
	const heatmap = $('.heatmap');
	if (heatmap) heatmap.scrollLeft = heatmap.scrollWidth;

	/* Toast */

	let timerToast;
	const toast = msg => {
		const el = $('#toast');
		el.textContent = msg;
		el.classList.add('mostra');
		clearTimeout(timerToast);
		timerToast = setTimeout(() => el.classList.remove('mostra'), 2200);
	};

	/* Relógio e saudação */

	const saudacao = h =>
		h < 5  ? 'Codando de madrugada' :
		h < 12 ? 'Bom dia' :
		h < 18 ? 'Boa tarde' : 'Boa noite';

	const relogio = () => {
		const d = new Date();
		const hm = d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
		const s  = String(d.getSeconds()).padStart(2, '0');
		$('#hora').innerHTML = `${hm}<span class="seg">:${s}</span>`;
		$('#data').textContent = d.toLocaleDateString('pt-BR', { weekday: 'long', day: 'numeric', month: 'long' });
		$('#saudacao').textContent = saudacao(d.getHours());
	};
	relogio();
	setInterval(relogio, 1000);

	/* Tempo relativo */

	const rtf = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
	const unidades = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];

	const relativo = ts => {
		const s = ts - Date.now() / 1000;
		for (const [u, seg] of unidades) {
			if (Math.abs(s) >= seg) return rtf.format(Math.round(s / seg), u);
		}
		return 'agora mesmo';
	};

	const atualizarTempos = () => $$('.rel').forEach(el => { el.textContent = relativo(+el.dataset.ts); });
	atualizarTempos();
	setInterval(atualizarTempos, 60000);

	/* Tema */

	$('#tema').addEventListener('click', () => {
		const atual = root.dataset.theme || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
		const novo  = atual === 'dark' ? 'light' : 'dark';
		const trocar = () => { root.dataset.theme = novo; };
		document.startViewTransition ? document.startViewTransition(trocar) : trocar();
		guardar.gravar('tema', novo);
	});

	/* Ordenação e favoritos */

	const favoritos = new Set(guardar.ler('favoritos', []));
	let ordem = guardar.ler('ordem', 'recentes');

	const comparar = {
		recentes: (a, b) => b.dataset.ts - a.dataset.ts,
		az:       (a, b) => a.dataset.nome.localeCompare(b.dataset.nome),
		familia:  (a, b) => a.dataset.familia.localeCompare(b.dataset.familia) || a.dataset.nome.localeCompare(b.dataset.nome),
	};

	const ordenar = (animar = true) => {
		const aplicar = () => {
			cards.forEach(c => {
				const fav = favoritos.has(c.dataset.nome);
				c.classList.toggle('fav', fav);
				$('.estrela', c).setAttribute('aria-pressed', fav);
			});
			[...cards]
				.sort((a, b) => (favoritos.has(b.dataset.nome) - favoritos.has(a.dataset.nome)) || comparar[ordem](a, b))
				.forEach(c => grid.append(c));
			$$('[data-ordem]').forEach(b => b.setAttribute('aria-checked', b.dataset.ordem === ordem));
		};
		animar && document.startViewTransition ? document.startViewTransition(aplicar) : aplicar();
	};
	ordenar(false);

	$$('[data-ordem]').forEach(b => b.addEventListener('click', () => {
		ordem = b.dataset.ordem;
		guardar.gravar('ordem', ordem);
		ordenar();
	}));

	/* Ações dos cards */

	grid.addEventListener('click', e => {
		const botao = e.target.closest('[data-acao]');
		if (!botao) return;
		e.preventDefault();

		const card = botao.closest('.card');
		const nome = card.dataset.nome;

		if (botao.dataset.acao === 'fav') {
			favoritos.has(nome) ? favoritos.delete(nome) : favoritos.add(nome);
			guardar.gravar('favoritos', [...favoritos]);
			botao.classList.remove('pop');
			void botao.offsetWidth;
			botao.classList.add('pop');
			toast(favoritos.has(nome) ? `${nome} fixado no topo` : `${nome} solto`);
			ordenar();
		}

		if (botao.dataset.acao === 'copiar') {
			navigator.clipboard.writeText(botao.dataset.caminho)
				.then(() => toast('Caminho copiado'))
				.catch(() => toast(botao.dataset.caminho));
		}

		if (botao.dataset.acao === 'qr') {
			mostrarQr(document.body.dataset.lan + $('.card-link', card).getAttribute('href'), nome);
		}
	});

	// Luz que segue o mouse
	grid.addEventListener('pointermove', e => {
		const card = e.target.closest('.card');
		if (!card) return;
		const r = card.getBoundingClientRect();
		card.style.setProperty('--mx', e.clientX - r.left + 'px');
		card.style.setProperty('--my', e.clientY - r.top + 'px');
	});

	/* Busca */

	const visiveis = () => cards.filter(c => !c.hidden);

	const filtrar = () => {
		const termos = normalizar(busca.value.trim()).split(/\s+/).filter(Boolean);
		cards.forEach(c => { c.hidden = !termos.every(t => c.dataset.indice.includes(t)); });

		const nada = !visiveis().length;
		$('#vazio').hidden = !nada;
		if (nada) {
			$('#vazio-termo').textContent = busca.value.trim();
			$('#vazio-url').textContent = busca.value.trim();
		}
	};

	busca.addEventListener('input', filtrar);

	const abrir = (href, novaAba) => novaAba ? window.open(href, '_blank') : (location.href = href);

	busca.addEventListener('keydown', e => {
		if (e.key === 'Enter') {
			const primeiro = visiveis()[0];
			const termo = busca.value.trim();
			if (primeiro) abrir($('.card-link', primeiro).href, e.ctrlKey || e.metaKey);
			else if (termo) abrir('/' + termo.replace(/^\/+/, ''), e.ctrlKey || e.metaKey);
		}
		if (e.key === 'ArrowDown') {
			e.preventDefault();
			$('.card-link', visiveis()[0] || document)?.focus();
		}
	});

	const colunas = () => getComputedStyle(grid).gridTemplateColumns.split(' ').length;

	document.addEventListener('keydown', e => {
		const digitando = e.target.matches('input, textarea, [contenteditable]');

		if ((e.key === '/' && !digitando) || (e.key === 'k' && (e.ctrlKey || e.metaKey))) {
			e.preventDefault();
			busca.focus();
			busca.select();
			return;
		}

		if (e.key === 'Escape' && !$('#qr').open) {
			busca.value = '';
			filtrar();
			busca.blur();
			return;
		}

		// Setas entre os cards
		if (e.target.classList.contains('card-link') && e.key.startsWith('Arrow')) {
			const lista = visiveis();
			const atual = lista.indexOf(e.target.closest('.card'));
			const passo = { ArrowRight: 1, ArrowLeft: -1, ArrowDown: colunas(), ArrowUp: -colunas() }[e.key];
			const alvo  = atual + passo;
			e.preventDefault();
			if (alvo < 0) busca.focus();
			else $('.card-link', lista[Math.min(alvo, lista.length - 1)]).focus();
		}

		if (e.target.classList.contains('card-link') && e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
			e.preventDefault();
			abrir(e.target.href, true);
		}
	});

	/* QR para abrir no celular */

	let bibliotecaQr;
	const carregarQr = () => bibliotecaQr ??= new Promise((ok, falha) => {
		const s = document.createElement('script');
		s.src = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.js';
		s.onload = () => ok(window.qrcode);
		s.onerror = () => { bibliotecaQr = null; falha(); };
		document.head.append(s);
	});

	const dialogo = $('#qr');

	async function mostrarQr(url, titulo) {
		$('#qr-titulo').textContent = titulo;
		$('#qr-url').textContent = url;
		$('#qr-img').textContent = 'gerando...';
		dialogo.showModal();
		try {
			const qrcode = await carregarQr();
			const qr = qrcode(0, 'M');
			qr.addData(url);
			qr.make();
			$('#qr-img').innerHTML = qr.createSvgTag({ cellSize: 6, margin: 0, scalable: true });
		} catch {
			$('#qr-img').textContent = 'Sem internet para gerar o QR. Digite o endereço abaixo no celular.';
		}
	}

	$$('[data-qr]').forEach(b => b.addEventListener('click', () => mostrarQr(b.dataset.qr, b.dataset.titulo)));
	$('[data-fechar]', dialogo).addEventListener('click', () => dialogo.close());
	dialogo.addEventListener('click', e => { if (e.target === dialogo) dialogo.close(); });

	/* ↑↑↓↓←→←→BA */

	const konami = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown', 'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
	let progresso = 0;

	document.addEventListener('keydown', e => {
		if (e.target.matches('input')) return;
		progresso = e.key.toLowerCase() === konami[progresso].toLowerCase() ? progresso + 1 : (e.key === konami[0] ? 1 : 0);
		if (progresso < konami.length) return;

		progresso = 0;
		document.body.classList.add('fliperama');
		const pacman = cards.find(c => c.dataset.nome === 'pacman');
		toast(pacman ? 'INSERT COIN... carregando o Pac-Man' : 'Modo fliperama ativado');
		setTimeout(() => {
			if (pacman) location.href = $('.card-link', pacman).href;
			else document.body.classList.remove('fliperama');
		}, 1800);
	});
})();
