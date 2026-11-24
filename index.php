<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://code.jquery.com/jquery-4.0.0.js"></script>
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
         *      garantindo que #board, #painel-tempo etc. já existem.
         *
         *  Recursos do jQuery usados neste arquivo:
         *      $("seletor")          seleciona elementos (igual ao CSS)
         *      $("<div>")            cria um elemento novo
         *      .text(valor)          troca o texto do elemento
         *      .attr(nome, valor)    lê ou grava um atributo (ex.: data-linha)
         *      .addClass/.removeClass  adiciona/remove classes CSS
         *      .css({...})           aplica estilos direto no elemento
         *      .empty()              remove todos os filhos
         *      .append(el)           coloca um elemento dentro de outro
         *      .on(evento, filtro, fn)  escuta eventos (com delegação)
         *      $.getJSON / $.post    requisições AJAX para a API em PHP
         * ============================================================
         */
        $(function() {

            // ---------- Configuração ----------

            // Endereço da API que grava/lê os tempos no banco (api/tempos.php)
            const URL_API = "api/tempos.php";

            // Níveis de dificuldade: linhas × colunas, quantidade de minas
            // e tamanho de cada célula em pixels
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

            // ---------- Estado do jogo ----------

            let linhas, colunas, totalMinas, tamanhoCelula;
            let nivelAtual;
            let tabuleiro = [];   // matriz [linha][coluna] com os dados de cada célula
            let fimDeJogo = false;

            // Timer
            let inicioJogo = null;     // momento do primeiro clique (null = timer parado)
            let intervaloTimer = null; // id do setInterval que atualiza a tela

            // Aplica as configurações do nível escolhido
            function definirNivel(nivel) {
                nivelAtual = nivel;
                linhas = niveis[nivel].linhas;
                colunas = niveis[nivel].colunas;
                totalMinas = niveis[nivel].minas;
                tamanhoCelula = niveis[nivel].tamanho;
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

            // ---------- Recordes (banco de dados via AJAX) ----------

            // Escreve o melhor e o pior tempo no painel.
            // "--" quando ainda não há nenhuma vitória no nível.
            function exibirRecordes(recordes) {
                $("#tempo-melhor").text(recordes.melhor === null ? "--" : formatarTempo(recordes.melhor));
                $("#tempo-pior").text(recordes.pior === null ? "--" : formatarTempo(recordes.pior));
            }

            // Busca no banco o melhor e o pior tempo do nível atual.
            // $.getJSON(url, dados) faz um GET (api/tempos.php?nivel=...)
            // e já converte a resposta JSON em objeto JavaScript.
            //   .done(fn) roda quando dá certo
            //   .fail(fn) roda se a API/banco estiver fora do ar
            function mostrarRecordes() {
                const nivelPedido = nivelAtual;
                $.getJSON(URL_API, { nivel: nivelPedido })
                    .done(function(recordes) {
                        // se o jogador trocou de nível enquanto esperava, ignora a resposta antiga
                        if (nivelPedido === nivelAtual) exibirRecordes(recordes);
                    })
                    .fail(function() {
                        exibirRecordes({ melhor: null, pior: null });
                    });
            }

            // Salva no banco o tempo de uma vitória.
            // $.post(url, dados) envia os campos "nivel" e "segundos" por POST.
            // A API responde com o melhor/pior já atualizados, que vão direto pro painel.
            function salvarRecorde(segundos) {
                const nivelPedido = nivelAtual;
                $.post(URL_API, { nivel: nivelPedido, segundos: segundos.toFixed(1) }, null, "json")
                    .done(function(recordes) {
                        if (nivelPedido === nivelAtual) exibirRecordes(recordes);
                    })
                    .fail(function() {
                        $("#status").text($("#status").text() + " (não foi possível salvar o tempo)");
                    });
            }

            // ---------- Tabuleiro ----------

            // Monta um tabuleiro novo.
            //   .empty()  apaga as células do jogo anterior
            //   .css({})  define o grid do CSS conforme o nível (linhas, colunas, tamanho)
            //   $("<div>") cria cada célula; .attr guarda a posição em data-linha/data-coluna
            //   .append() coloca a célula dentro do #board
            function iniciarTabuleiro() {
                tabuleiro = [];
                fimDeJogo = false;
                pararTimer();
                $("#tempo-atual").text(formatarTempo(0));
                mostrarRecordes();
                $("#status").text("");
                $("#board").empty().css({
                    gridTemplateRows: `repeat(${linhas}, ${tamanhoCelula}px)`,
                    gridTemplateColumns: `repeat(${colunas}, ${tamanhoCelula}px)`,
                    fontSize: `${Math.round(tamanhoCelula * 0.45)}px`
                });
                for (let lin = 0; lin < linhas; lin++) {
                    tabuleiro[lin] = [];
                    for (let col = 0; col < colunas; col++) {
                        tabuleiro[lin][col] = {
                            mina: false,
                            revelada: false,
                            bandeira: false,
                            vizinhos: 0
                        };
                        const celula = $("<div>")
                            .addClass("cell")
                            .attr("data-linha", lin)
                            .attr("data-coluna", col);
                        $("#board").append(celula);
                    }
                }
                colocarMinas();
                calcularVizinhos();
            }

            // Coloca minas em posições aleatórias (sem repetir)
            function colocarMinas() {
                let colocadas = 0;
                while (colocadas < totalMinas) {
                    let lin = Math.floor(Math.random() * linhas);
                    let col = Math.floor(Math.random() * colunas);
                    if (!tabuleiro[lin][col].mina) {
                        tabuleiro[lin][col].mina = true;
                        colocadas++;
                    }
                }
            }

            // Para cada célula sem mina, conta quantas minas existem nas 8 vizinhas
            function calcularVizinhos() {
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        if (!tabuleiro[lin][col].mina) {
                            let contagem = 0;
                            for (let dLin = -1; dLin <= 1; dLin++) {
                                for (let dCol = -1; dCol <= 1; dCol++) {
                                    let nLin = lin + dLin,
                                        nCol = col + dCol;
                                    if (nLin >= 0 && nLin < linhas && nCol >= 0 && nCol < colunas && tabuleiro[nLin][nCol].mina) {
                                        contagem++;
                                    }
                                }
                            }
                            tabuleiro[lin][col].vizinhos = contagem;
                        }
                    }
                }
            }

            // Encontra a <div> de uma célula pela posição usando seletor de atributo:
            // .cell[data-linha=2][data-coluna=5]
            function buscarCelula(lin, col) {
                return $(`.cell[data-linha=${lin}][data-coluna=${col}]`);
            }

            // ---------- Jogadas ----------

            // Revela uma célula.
            //   Mina   -> perde o jogo
            //   Número -> mostra o número (data-n define a cor no CSS)
            //   Vazia  -> revela as vizinhas automaticamente (recursão)
            function revelarCelula(lin, col) {
                const dados = tabuleiro[lin][col];
                if (dados.revelada || dados.bandeira || fimDeJogo) return;
                dados.revelada = true;
                const celula = buscarCelula(lin, col).addClass("revealed");
                if (dados.mina) {
                    celula.addClass("mine").text("💣");
                    $("#status").text("💥 Você perdeu!");
                    fimDeJogo = true;
                    pararTimer();
                    revelarTodasMinas();
                    return;
                }
                if (dados.vizinhos > 0) {
                    celula.text(dados.vizinhos).attr("data-n", dados.vizinhos);
                } else {
                    for (let dLin = -1; dLin <= 1; dLin++) {
                        for (let dCol = -1; dCol <= 1; dCol++) {
                            let nLin = lin + dLin,
                                nCol = col + dCol;
                            if (nLin >= 0 && nLin < linhas && nCol >= 0 && nCol < colunas) {
                                revelarCelula(nLin, nCol);
                            }
                        }
                    }
                }
                verificarVitoria();
            }

            // Marca/desmarca bandeira (classe "flag" no CSS + emoji 🚩)
            function alternarBandeira(lin, col) {
                const dados = tabuleiro[lin][col];
                if (dados.revelada || fimDeJogo) return;
                dados.bandeira = !dados.bandeira;
                const celula = buscarCelula(lin, col);
                if (dados.bandeira) {
                    celula.addClass("flag").text("🚩");
                } else {
                    celula.removeClass("flag").text("");
                }
            }

            // Ao perder, mostra onde estavam todas as minas
            function revelarTodasMinas() {
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        if (tabuleiro[lin][col].mina) {
                            buscarCelula(lin, col).addClass("mine").text("💣");
                        }
                    }
                }
            }

            // Vitória = todas as células sem mina foram reveladas.
            // Para o timer e salva o tempo no banco.
            function verificarVitoria() {
                if (fimDeJogo) return;
                let reveladas = 0;
                for (let lin = 0; lin < linhas; lin++) {
                    for (let col = 0; col < colunas; col++) {
                        if (tabuleiro[lin][col].revelada) reveladas++;
                    }
                }
                if (reveladas === linhas * colunas - totalMinas) {
                    fimDeJogo = true;
                    const segundos = pararTimer();
                    $("#status").text("🎉 Você venceu em " + formatarTempo(segundos) + "!");
                    salvarRecorde(segundos);
                }
            }

            // ---------- Eventos ----------

            // Clique esquerdo numa célula.
            // .on("click", ".cell", fn) usa DELEGAÇÃO: o evento fica no #board
            // e vale para qualquer .cell dentro dele, inclusive as criadas depois
            // (as células são recriadas a cada partida).
            // Dentro da função, $(this) é a célula clicada.
            $("#board").on("click", ".cell", function() {
                const lin = parseInt($(this).attr("data-linha"));
                const col = parseInt($(this).attr("data-coluna"));
                if (!fimDeJogo && inicioJogo === null) iniciarTimer(); // o tempo começa no 1º clique
                revelarCelula(lin, col);
            });

            // Clique direito numa célula -> bandeira.
            // e.preventDefault() impede o menu do navegador de abrir.
            $("#board").on("contextmenu", ".cell", function(e) {
                e.preventDefault();
                const lin = parseInt($(this).attr("data-linha"));
                const col = parseInt($(this).attr("data-coluna"));
                alternarBandeira(lin, col);
            });

            // Botões de dificuldade: tira "ativa" de todos, coloca no clicado
            // e começa uma partida nova no nível de data-nivel.
            $(".dificuldade").on("click", function() {
                $(".dificuldade").removeClass("ativa");
                $(this).addClass("ativa");
                definirNivel($(this).attr("data-nivel"));
                iniciarTabuleiro();
            });

            // Botão "Jogar de novo"
            $("#restart").on("click", iniciarTabuleiro);

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
            <button class="dificuldade ativa" data-nivel="iniciante">Iniciante</button>
            <button class="dificuldade" data-nivel="intermediario">Intermediário</button>
            <button class="dificuldade" data-nivel="especialista">Especialista</button>
        </div>
    </header>

    <div class="area-jogo">
        <div class="lateral">
            <aside id="painel-tempo">
                <h2>⏱ Tempo</h2>
                <div class="tempo">
                    <span>Best</span>
                    <strong id="tempo-melhor">--</strong>
                </div>
                <div class="tempo">
                    <span>Worst</span>
                    <strong id="tempo-pior">--</strong>
                </div>
                <div class="tempo">
                    <span>Current</span>
                    <strong id="tempo-atual">0.0s</strong>
                </div>
            </aside>
            <p id="status"></p>
            <button id="restart">Jogar de novo</button>
        </div>
        <div id="board"></div>
    </div>

</body>

</html>
