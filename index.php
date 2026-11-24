<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Russo+One&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script>
        /*
         * ============================================================
         *  CAMPO MINADO — jQuery
         * ============================================================
         *  $(function() { ... })
         *      Atalho do jQuery para $(document).ready(...): o código
         *      dentro só roda depois que o HTML da página foi carregado,
         *      garantindo que #board, #painel-placar etc. já existem.
         *
         *  Recursos do jQuery usados neste arquivo:
         *      $("seletor")          seleciona elementos (igual ao CSS)
         *      $("<div>")            cria um elemento novo
         *      .text(valor)          troca o texto do elemento
         *      .attr(nome, valor)    lê ou grava um atributo (ex.: data-linha)
         *      .addClass/.removeClass/.toggleClass  mexe nas classes CSS
         *      .css({...})           aplica estilos direto no elemento
         *      .empty()              remove todos os filhos
         *      .append(el)           coloca um elemento dentro de outro
         *      .on(evento, filtro, fn)  escuta eventos (com delegação)
         * ============================================================
         */
        $(function() {

            // ---------- Configuração ----------

            // Níveis de dificuldade: linhas × colunas, quantidade de minas
            // e tamanho MÁXIMO de cada célula em pixels (em telas menores
            // as células encolhem para o campo caber; veja ajustarTamanho)
            const niveis = {
                iniciante: {
                    linhas: 9,
                    colunas: 9,
                    minas: 10,
                    tamanho: 75
                },
                intermediario: {
                    linhas: 16,
                    colunas: 16,
                    minas: 40,
                    tamanho: 41
                },
                especialista: {
                    linhas: 16,
                    colunas: 30,
                    minas: 99,
                    tamanho: 38
                }
            };

            const TAMANHO_MINIMO = 24; // abaixo disso fica difícil de clicar; o campo rola
            const TAMANHO_CONFORTAVEL = 32; // menor que isso entre os painéis -> empilha o layout
            const ESPACO_ENTRE_CELULAS = 2; // igual ao "gap" do #board no CSS
            const TEMPO_TOQUE_LONGO = 450; // ms segurando o dedo para pôr bandeira

            // ---------- Estado do jogo ----------

            let linhas, colunas, totalMinas;
            let nivelAtual;
            let tabuleiro = []; // matriz [linha][coluna] com os dados de cada célula
            let fimDeJogo = false;
            let minasColocadas = false; // as minas só são sorteadas no 1º clique
            let reveladas = 0; // quantas células sem mina já foram abertas
            let bandeiras = 0; // quantas bandeiras estão no campo

            // Timer
            let inicioJogo = null; // momento do primeiro clique (null = timer parado)
            let intervaloTimer = null; // id do setInterval que atualiza a tela

            // Aplica as configurações do nível escolhido
            function definirNivel(nivel) {
                nivelAtual = nivel;
                linhas = niveis[nivel].linhas;
                colunas = niveis[nivel].colunas;
                totalMinas = niveis[nivel].minas;
            }

            // Lista as posições [linha, coluna] das até 8 células ao redor
            function vizinhas(lin, col) {
                const lista = [];
                for (let dLin = -1; dLin <= 1; dLin++) {
                    for (let dCol = -1; dCol <= 1; dCol++) {
                        const nLin = lin + dLin,
                            nCol = col + dCol;
                        if ((dLin || dCol) && nLin >= 0 && nLin < linhas && nCol >= 0 && nCol < colunas) {
                            lista.push([nLin, nCol]);
                        }
                    }
                }
                return lista;
            }

            // ---------- Timer ----------

            // Formata segundos para exibir (ex.: 12.3s)
            function formatarTempo(segundos) {
                return segundos.toFixed(1) + "s";
            }

            // Começa a contar o tempo.
            // A cada 100ms, $("#tempo-atual").text(...) atualiza o número na tela.
            function iniciarTimer() {
                inicioJogo = Date.now();
                intervaloTimer = setInterval(function() {
                    const segundos = (Date.now() - inicioJogo) / 1000;
                    $("#tempo-atual").text(formatarTempo(segundos));
                }, 100);
            }

            // Para o timer e devolve o tempo total em segundos
            function pararTimer() {
                clearInterval(intervaloTimer);
                intervaloTimer = null;
                if (inicioJogo === null) return 0;
                const segundos = (Date.now() - inicioJogo) / 1000;
                inicioJogo = null;
                $("#tempo-atual").text(formatarTempo(segundos));
                return segundos;
            }

            // ---------- Placar de vitórias e derrotas ----------

            // O placar fica salvo no próprio navegador (localStorage), separado
            // por nível: { iniciante: { vitorias: 3, derrotas: 5 }, ... }.
            // Assim ele continua lá depois de recarregar a página, sem precisar
            // de banco de dados. O try/catch cobre navegadores que bloqueiam o
            // localStorage (ex.: janela anônima): o jogo funciona, só não guarda.
            const CHAVE_PLACAR = "campoMinado.placar";

            function lerPlacar() {
                const placar = {};
                let salvo = {};
                try {
                    salvo = JSON.parse(localStorage.getItem(CHAVE_PLACAR)) || {};
                } catch (e) {}
                for (const nivel in niveis) {
                    placar[nivel] = {
                        vitorias: Number(salvo[nivel]?.vitorias) || 0,
                        derrotas: Number(salvo[nivel]?.derrotas) || 0
                    };
                }
                return placar;
            }

            let placar = lerPlacar();

            function salvarPlacar() {
                try {
                    localStorage.setItem(CHAVE_PLACAR, JSON.stringify(placar));
                } catch (e) {}
            }

            // Mostra vitórias, derrotas e aproveitamento do nível atual
            function exibirPlacar() {
                const {
                    vitorias,
                    derrotas
                } = placar[nivelAtual];
                $("#nome-nivel").text($(`.dificuldade[data-nivel=${nivelAtual}]`).text());
                const partidas = vitorias + derrotas;
                $("#vitorias").text(vitorias);
                $("#derrotas").text(derrotas);
                $("#aproveitamento").text(partidas === 0 ? "--" : Math.round(100 * vitorias / partidas) + "%");
            }

            // Soma 1 em "vitorias" ou "derrotas" do nível atual
            function registrarResultado(tipo) {
                placar[nivelAtual][tipo]++;
                salvarPlacar();
                exibirPlacar();
            }

            // ---------- Tabuleiro ----------

            // Calcula o tamanho das células para o campo caber na largura da tela.
            // Usa o tamanho do nível como máximo e TAMANHO_MINIMO como mínimo
            // (no mínimo, o campo rola de lado dentro de .board-wrap).
            //
            // Primeiro tenta o layout de 3 colunas (placar | campo | informações).
            // Se as células ficariam pequenas demais ali, liga a classe
            // "empilhado": os dois painéis sobem e o campo usa a largura toda.
            function ajustarTamanho() {
                const $area = $(".area-jogo").removeClass("empilhado");
                const $board = $("#board");
                const bordas = 2 * (parseFloat($board.css("border-left-width")) + parseFloat($board.css("padding-left")));
                const espacoColunas = parseFloat($area.css("column-gap"));
                const maximo = niveis[nivelAtual].tamanho;

                // maior célula que cabe numa largura (sem passar do máximo do nível)
                function celulaQueCabe(largura) {
                    return Math.min(maximo, Math.floor((largura - bordas - ESPACO_ENTRE_CELULAS * (colunas - 1)) / colunas));
                }

                const entrePaineis = $area.width() - $(".lateral").outerWidth() - $(".lateral-direita").outerWidth() - 2 * espacoColunas;
                let cabe = celulaQueCabe(entrePaineis);
                if (cabe < Math.min(maximo, TAMANHO_CONFORTAVEL)) {
                    $area.addClass("empilhado");
                    cabe = celulaQueCabe($area.width());
                }
                const tamanho = Math.max(TAMANHO_MINIMO, cabe);
                $board.css({
                    gridTemplateRows: `repeat(${linhas}, ${tamanho}px)`,
                    gridTemplateColumns: `repeat(${colunas}, ${tamanho}px)`,
                    fontSize: `${Math.round(tamanho * 0.45)}px`
                });
            }

            // Painel da direita: campos abertos, bombas marcadas e bombas restantes.
            // "Restantes" = minas - bandeiras (pode ficar negativo se sobrar bandeira).
            function atualizarInfo() {
                const semMina = linhas * colunas - totalMinas;
                $("#campos-abertos").text(`${reveladas} / ${semMina}`);
                $("#bombas-marcadas").text(bandeiras);
                $("#bombas-restantes").text(totalMinas - bandeiras);
                $("#barra-progresso").css("width", (100 * reveladas / semMina) + "%");
            }

            // Monta um tabuleiro novo.
            //   .empty()  apaga as células do jogo anterior
            //   $("<div>") cria cada célula; .attr guarda a posição em data-linha/data-coluna
            //   .append() coloca a célula dentro do #board
            // O elemento jQuery de cada célula fica guardado em tabuleiro[lin][col].el,
            // assim não é preciso procurá-lo no DOM a cada jogada.
            function iniciarTabuleiro() {
                tabuleiro = [];
                fimDeJogo = false;
                minasColocadas = false;
                reveladas = 0;
                bandeiras = 0;
                pararTimer();
                $("#tempo-atual").text(formatarTempo(0));
                atualizarInfo();
                exibirPlacar();
                $("#status").text("");
                const $board = $("#board").empty();
                ajustarTamanho();
                for (let lin = 0; lin < linhas; lin++) {
                    tabuleiro[lin] = [];
                    for (let col = 0; col < colunas; col++) {
                        const celula = $("<div>")
                            .addClass("cell")
                            .attr({
                                "data-linha": lin,
                                "data-coluna": col,
                                "aria-label": "fechada"
                            });
                        tabuleiro[lin][col] = {
                            mina: false,
                            revelada: false,
                            bandeira: false,
                            vizinhos: 0,
                            el: celula
                        };
                        $board.append(celula);
                    }
                }
            }

            // Sorteia as minas no 1º clique, deixando livres a célula clicada
            // e as 8 em volta — assim o primeiro clique nunca perde e sempre abre uma área.
            function colocarMinas(linSegura, colSegura) {
                const candidatas = [];
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        if (Math.abs(lin - linSegura) > 1 || Math.abs(col - colSegura) > 1) {
                            candidatas.push([lin, col]);
                        }
                    }
                }
                // embaralha só o começo da lista (Fisher-Yates parcial) e pega as primeiras
                for (let i = 0; i < totalMinas; i++) {
                    const j = i + Math.floor(Math.random() * (candidatas.length - i));
                    [candidatas[i], candidatas[j]] = [candidatas[j], candidatas[i]];
                    const [lin, col] = candidatas[i];
                    tabuleiro[lin][col].mina = true;
                }
                calcularVizinhos();
                minasColocadas = true;
            }

            // Para cada célula sem mina, conta quantas minas existem nas 8 vizinhas
            function calcularVizinhos() {
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        tabuleiro[lin][col].vizinhos = vizinhas(lin, col)
                            .filter(([nLin, nCol]) => tabuleiro[nLin][nCol].mina).length;
                    }
                }
            }

            // ---------- Jogadas ----------

            // Revela uma célula.
            //   Mina   -> perde o jogo
            //   Número -> mostra o número (data-n define a cor no CSS)
            //   Vazia  -> revela as vizinhas automaticamente.
            // A expansão usa uma pilha em vez de recursão: cada célula vazia
            // empilha as vizinhas, e o laço continua até a pilha esvaziar.
            function revelarCelula(lin, col) {
                const inicial = tabuleiro[lin][col];
                if (inicial.revelada || inicial.bandeira || fimDeJogo) return;
                if (inicial.mina) {
                    perder(lin, col);
                    return;
                }
                const pilha = [
                    [lin, col]
                ];
                while (pilha.length > 0) {
                    const [l, c] = pilha.pop();
                    const dados = tabuleiro[l][c];
                    if (dados.revelada || dados.bandeira) continue;
                    dados.revelada = true;
                    reveladas++;
                    dados.el.addClass("revealed");
                    if (dados.vizinhos > 0) {
                        dados.el.text(dados.vizinhos).attr({
                            "data-n": dados.vizinhos,
                            "aria-label": dados.vizinhos
                        });
                    } else {
                        dados.el.attr("aria-label", "vazia");
                        pilha.push(...vizinhas(l, c));
                    }
                }
                atualizarInfo();
                verificarVitoria();
            }

            // Clique num número já aberto: se ao redor já há tantas bandeiras
            // quanto o número, abre de uma vez todas as vizinhas sem bandeira.
            function abrirVizinhas(lin, col) {
                const dados = tabuleiro[lin][col];
                if (!dados.revelada || dados.vizinhos === 0 || fimDeJogo) return;
                const ao_redor = vizinhas(lin, col);
                const marcadas = ao_redor.filter(([l, c]) => tabuleiro[l][c].bandeira).length;
                if (marcadas !== dados.vizinhos) return;
                ao_redor.forEach(([l, c]) => revelarCelula(l, c));
            }

            // Marca/desmarca bandeira (classe "flag" no CSS + emoji 🚩)
            function alternarBandeira(lin, col) {
                const dados = tabuleiro[lin][col];
                if (dados.revelada || fimDeJogo) return;
                dados.bandeira = !dados.bandeira;
                bandeiras += dados.bandeira ? 1 : -1;
                dados.el
                    .toggleClass("flag", dados.bandeira)
                    .text(dados.bandeira ? "🚩" : "")
                    .attr("aria-label", dados.bandeira ? "bandeira" : "fechada");
                atualizarInfo();
            }

            // Derrota: mostra todas as minas. A que explodiu fica destacada,
            // bandeiras certas continuam e bandeiras erradas ganham um ❌.
            function perder(linExplodiu, colExplodiu) {
                fimDeJogo = true;
                pararTimer();
                $("#status").text("💥 Você perdeu!");
                registrarResultado("derrotas");
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        const dados = tabuleiro[lin][col];
                        if (dados.mina && !dados.bandeira) {
                            dados.el.addClass("revealed mine").text("💣").attr("aria-label", "mina");
                        } else if (!dados.mina && dados.bandeira) {
                            dados.el.addClass("flag-errada").text("❌").attr("aria-label", "bandeira errada");
                        }
                    }
                }
                tabuleiro[linExplodiu][colExplodiu].el.addClass("explodiu");
            }

            // Vitória = todas as células sem mina foram reveladas.
            // Marca as minas que faltavam, para o timer e salva o tempo no banco.
            function verificarVitoria() {
                if (fimDeJogo || reveladas !== linhas * colunas - totalMinas) return;
                fimDeJogo = true;
                const segundos = pararTimer();
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        const dados = tabuleiro[lin][col];
                        if (dados.mina && !dados.bandeira) {
                            dados.bandeira = true;
                            dados.el.addClass("flag").text("🚩").attr("aria-label", "bandeira");
                        }
                    }
                }
                bandeiras = totalMinas;
                atualizarInfo();
                $("#status").text("🎉 Você venceu em " + formatarTempo(segundos) + "!");
                registrarResultado("vitorias");
            }

            // Jogada principal (clique ou toque).
            function jogar(lin, col) {
                if (fimDeJogo) return;
                const dados = tabuleiro[lin][col];
                if (dados.revelada) {
                    abrirVizinhas(lin, col);
                    return;
                }
                if (dados.bandeira) return; // bandeira protege a célula (e não inicia o tempo)
                if (!minasColocadas) {
                    colocarMinas(lin, col);
                    iniciarTimer(); // o tempo começa no 1º clique
                }
                revelarCelula(lin, col);
            }

            // Lê a posição guardada em data-linha/data-coluna
            function posicao(celula) {
                return [parseInt($(celula).attr("data-linha")), parseInt($(celula).attr("data-coluna"))];
            }

            // ---------- Eventos ----------

            // Toque longo (celular) coloca bandeira.
            // ultimoToqueLongo evita que o "contextmenu" que alguns celulares
            // disparam junto com o toque longo desfaça a bandeira.
            let temporizadorToque = null;
            let toqueVirouBandeira = false;
            let ultimoToqueLongo = 0;

            $("#board").on("touchstart", ".cell", function() {
                const [lin, col] = posicao(this);
                toqueVirouBandeira = false;
                clearTimeout(temporizadorToque);
                temporizadorToque = setTimeout(function() {
                    toqueVirouBandeira = true;
                    ultimoToqueLongo = Date.now();
                    alternarBandeira(lin, col);
                    if (navigator.vibrate) navigator.vibrate(30);
                }, TEMPO_TOQUE_LONGO);
            });

            $("#board").on("touchend touchmove touchcancel", ".cell", function() {
                clearTimeout(temporizadorToque);
            });

            // Clique esquerdo numa célula.
            // .on("click", ".cell", fn) usa DELEGAÇÃO: o evento fica no #board
            // e vale para qualquer .cell dentro dele, inclusive as criadas depois
            // (as células são recriadas a cada partida).
            // Dentro da função, this é a célula clicada.
            $("#board").on("click", ".cell", function() {
                if (toqueVirouBandeira) { // o clique que vem depois do toque longo
                    toqueVirouBandeira = false;
                    return;
                }
                const [lin, col] = posicao(this);
                jogar(lin, col);
            });

            // Clique direito numa célula -> bandeira.
            // e.preventDefault() impede o menu do navegador de abrir.
            $("#board").on("contextmenu", ".cell", function(e) {
                e.preventDefault();
                if (Date.now() - ultimoToqueLongo < 1000) return;
                const [lin, col] = posicao(this);
                alternarBandeira(lin, col);
            });

            // Botões de dificuldade: tira "ativa" de todos, coloca no clicado
            // e começa uma partida nova no nível de data-nivel.
            $(".dificuldade").on("click", function() {
                $(".dificuldade").removeClass("ativa").attr("aria-pressed", "false");
                $(this).addClass("ativa").attr("aria-pressed", "true");
                definirNivel($(this).attr("data-nivel"));
                iniciarTabuleiro();
            });

            // Botão "Jogar de novo"
            $("#restart").on("click", iniciarTabuleiro);

            // Botão "Zerar" do placar: apaga vitórias/derrotas do nível atual
            $("#zerar-placar").on("click", function() {
                if (!confirm("Zerar vitórias e derrotas deste nível?")) return;
                placar[nivelAtual] = {
                    vitorias: 0,
                    derrotas: 0
                };
                salvarPlacar();
                exibirPlacar();
            });

            // Ao redimensionar a janela (ou girar o celular), recalcula o tamanho
            // das células. O setTimeout evita recalcular dezenas de vezes por segundo.
            let temporizadorResize = null;
            $(window).on("resize", function() {
                clearTimeout(temporizadorResize);
                temporizadorResize = setTimeout(ajustarTamanho, 100);
            });

            // ---------- Início ----------
            definirNivel("iniciante");
            iniciarTabuleiro();
        });
    </script>
    <title>Campo Minado</title>
</head>

<body>
    <header class="topo">
        <h1>Campo <span class="minado">Minado</span></h1>
        <div id="dificuldades">
            <button class="dificuldade ativa" data-nivel="iniciante" aria-pressed="true">Iniciante</button>
            <button class="dificuldade" data-nivel="intermediario" aria-pressed="false">Intermediário</button>
            <button class="dificuldade" data-nivel="especialista" aria-pressed="false">Especialista</button>
        </div>
    </header>

    <div class="area-jogo">
        <div class="lateral">
            <aside class="painel" id="painel-placar">
                <h2>Placar</h2>
                <p class="subtitulo" id="nome-nivel">Iniciante</p>
                <div class="info">
                    <span>⏱ Tempo</span>
                    <strong id="tempo-atual">0.0s</strong>
                </div>
                <div class="info">
                    <span>Vitórias</span>
                    <strong id="vitorias">0</strong>
                </div>
                <div class="info">
                    <span>Derrotas</span>
                    <strong id="derrotas">0</strong>
                </div>
                <div class="info">
                    <span>Aproveitamento</span>
                    <strong id="aproveitamento">--</strong>
                </div>
                <p class="rodape-painel"><button id="zerar-placar" type="button">Zerar placar do nível</button></p>
            </aside>
            <p id="status" role="status" aria-live="polite"></p>
            <div class="botoes">
                <button id="restart">Jogar de novo</button>
            </div>
            <p class="ajuda">
                Clique: abrir · Botão direito ou toque longo: bandeira<br>
                Clique num número: abre as vizinhas quando as bandeiras batem
            </p>
        </div>
        <div class="board-wrap">
            <div id="board" role="group" aria-label="Campo minado"></div>
        </div>
        <div class="lateral-direita">
            <aside class="painel" id="painel-info">
                <h2>📋 Partida</h2>
                <div class="info">
                    <span>Campos abertos</span>
                    <strong id="campos-abertos">0 / 0</strong>
                </div>
                <div class="info">
                    <span>🚩 Bombas marcadas</span>
                    <strong id="bombas-marcadas">0</strong>
                </div>
                <div class="info">
                    <span>💣 Bombas restantes</span>
                    <strong id="bombas-restantes">0</strong>
                </div>
                <div class="progresso" aria-hidden="true">
                    <div id="barra-progresso"></div>
                </div>
            </aside>
        </div>
    </div>

</body>

</html>