# Corrigido em produção — e fechado para não voltar a acontecer

## O que foi corrigido agora

**A Fanilde estava reativada pela metade.** Vocês reativaram a ficha e limparam o `z-` do nome, mas
o vínculo dela com a Dra Sullege continuava arquivado — e a busca do Assistente filtra as duas
coisas. Na prática ela seguiria invisível para a médica. Isso entrou no reparo.

Além dela, reativei as outras 7 fichas que têm a mesma prova: **estão ativas no ClinicaWeb** e
ficaram arquivadas aqui por causa da migração.

| ficha | paciente | médico que prescreveu | última receita |
| --- | --- | --- | --- |
| 16742 | Fanilde Pirro Viana Paquer | Dra Sullege (e Dr Luiz Fernando) | 13/08/2026 |
| 16555 | Hugo Castelani Barreto Passos | Dra Glaucia | 01/06/2026 |
| 16453 | Gisele Rangel da Cunha Ferreira | Dra Eloisa | 04/12/2025 |
| 16984 | Jose Vittor Siqueira Coco | Dra Roberta | 29/09/2025 |
| 16879 | Simone Nascimento Madeira | Dra Roberta | 07/08/2025 |
| 16629 | Rosane Maria Santanna | Dra Patrícia | 31/05/2025 |
| 16569 | Daniela Cosim da Silva. | Dra Glaucia | 02/05/2025 |
| 16528 | Isabelle Carvalho Curvo | Dra Sullege | 10/04/2025 |

Foi tirado backup do banco antes de qualquer escrita. Depois rodei uma auditoria só de leitura: as
8 fichas estão ativas e **aparecem na busca de cada médico que as atendeu** — a consulta conferida é
a mesma que o Assistente de Receita usa.

Nenhuma ficha foi desativada, nenhum nome foi alterado além do que vocês já tinham limpado.

### O que deixei como está (de propósito)

Outras 6 fichas estão arquivadas aqui **e também no ClinicaWeb** — arquivar é decisão da clínica,
não cabe ao sistema desfazer: `xxMarcelo Michel1` (teste), Diva Bombachini Daniel, Ana Cristina
Miney, a Fanilde duplicada antiga (#16552), Patricia Pereira Lopes e Maria Aparecida Ramos Nogueira.

Só uma merece uma olhada de vocês: **Maria Aparecida Ramos Nogueira** tem receita de 03/03/2026 e
está arquivada nos dois sistemas. Se foi engano, dá para reativar do mesmo jeito.

## Para não acontecer de novo

O problema tinha três chances de passar batido. Fechei as três.

**1. A importação do ClinicaWeb agora conserta em vez de esconder.** Quando a ficha da pessoa está
ativa no ClinicaWeb e cai numa ficha arquivada aqui (a repetida `z-`), a importação reativa a ficha,
reativa o vínculo do médico e passa a usar o nome da ficha ativa. Antes ela mantinha o nome `z-` e o
"desativado", em silêncio. Ficha que vocês arquivaram no ClinicaWeb continua arquivada aqui — isso
não muda.

**2. A tela de conferência da importação avisa.** Receita que estiver entrando numa ficha arquivada
aparece marcada em amarelo: *"o médico não vai encontrá-la na busca"*. Era isso que faltava em
junho, quando a receita da Fanilde entrou sem um único aviso.

**3. A busca parou de dizer que a paciente não existe.** Se o médico digita o nome e existe uma
ficha arquivada, ela agora aparece no painel *"Já cadastrados no sistema"* marcada como
**ficha arquivada**, com o botão **Selecionar**. Ao selecionar, a ficha volta a ativa e o paciente
já segue na receita — sem recadastro, sem duplicata. A busca do dia a dia continua mostrando só
ficha ativa, então nada muda na rotina de quem não tem esse problema.

O ponto mais importante do ponto 3: mesmo que apareça amanhã uma ficha arquivada por outro motivo
qualquer (importação, engano, limpeza), **o médico vê a ficha em vez de ouvir "nenhum paciente
encontrado"** — que era o que levava a recadastrar a mesma pessoa.

## Como isso foi verificado

- Auditoria em produção, só leitura, ficha por ficha e médico por médico.
- Testes automatizados: 10 novos cobrindo importação e busca; suíte completa passando.
- Simulação do caso na cópia local: com a ficha e o vínculo arquivados, a tela que antes dizia
  "Nenhum paciente encontrado" passa a listar a ficha marcada; clicando em Selecionar, a paciente
  entra na receita e a ficha volta a ativa.
