# Campo Minado (jQuery)

Campo Minado com três níveis (Iniciante, Intermediário e Especialista).

- **Placar (esquerda):** vitórias e derrotas do nível escolhido. Fica salvo no navegador (`localStorage`), então continua
  lá depois de recarregar a página — não precisa de banco de dados.
- **Partida (direita):** campos abertos, bombas marcadas e bombas restantes,
  com uma barra de progresso.

## Como rodar

Abra `index.html` por um servidor local, por exemplo o XAMPP:
coloque a pasta em `C:\xampp\htdocs\`, inicie o **Apache** e acesse
`http://localhost/jogoJQuery/`. Não há banco de dados nem API.

## Controles

| Ação | Mouse | Toque |
|---|---|---|
| Abrir célula | clique | toque |
| Bandeira | botão direito | toque longo |
| Abrir vizinhas de um número | clique no número | toque no número |

O primeiro clique nunca cai numa mina.

## Estrutura

- `index.html` — página e toda a lógica do jogo (jQuery)
- `css/style.css` — visual
- `img/back.jpg` — fundo
