# A paciente da Dra Sullege que o sistema jura não existir

**Fanilde Pirro Viana Paquer** — CPF 850.146.051-68, nascimento 09/06/1978, receita finalizada de
**R$ 1.293,00 em 11/06/2026** (nº 16742-0008).

A médica digita o nome no Assistente de Receita e recebe *"Nenhum paciente encontrado"*. O Antonio,
pelo acesso dele, vê a ficha e a receita anterior. A Fernanda, entrando com o acesso da Dra, não vê.
Nada disso é problema de acesso ou de celular: **a ficha está no banco, com nove receitas, mas
marcada como arquivada.**

Confirmado em produção hoje (13/08/2026), com a busca do próprio Assistente reproduzida no lugar da
médica: **0 resultados**.

## O que está acontecendo

A ficha da paciente em produção é a **#16742**, com nome gravado como **"z-Fanilde Pirro Viana
Paquer"**. Ela carrega dois cadeados:

| Cadeado | Campo | Efeito |
|---|---|---|
| 1 | `pacientes.ativo = 0` | esconde a ficha de **todos** na busca |
| 2 | `medico_paciente.ativo = 0` (Dra Sullege) | esconde a ficha **da médica**, mesmo se a 1ª fosse resolvida |

Simulamos a consulta da busca afrouxando um cadeado por vez: sem o primeiro, ainda dá 0; sem o
segundo, ainda dá 0. Os dois estão fechados ao mesmo tempo — por isso o resultado é o mesmo em
qualquer tela da médica.

O Antonio consegue ver porque é administrador: o filtro por médico não se aplica a ele, e a lista de
receitas não esconde receita de ficha arquivada. Ou seja, o mesmo dado existe para uns e não para
outros — exatamente o sintoma que a clínica descreveu.

## Por que a ficha nasceu arquivada

Não foi ninguém no ClinicaWeb novo que arquivou. Veio da carga do CLW2, e a causa é um hábito legítimo
da clínica lá + uma decisão errada da nossa importação.

No CLW2, quando uma ficha ficava ruim ou repetida, a equipe **desativava a antiga** (às vezes
renomeando com `z-`/`zzz` para ela cair no fim da lista) e **criava uma nova**. No caso da Fanilde
havia **quatro** fichas no CLW2:

| CLW2 | nome | ativo | nascimento |
|---|---|---|---|
| #240 | `Fanilde Pirro Viana Paquer ` | não | 25/08/1988 |
| #573 | `z-Fanilde Pirro Viana Paquer` | não | 09/06/1978 |
| #577 | `zzzFANILDE PIRRO VIANA PAQUER` | não | 09/06/1978 |
| **#594** | **`Fanilde Pirro Viana Paquer`** | **sim** | 09/06/1978 |

A ficha boa é a **#594** — é nela que está a receita de 11/06/2026 (receita 2259 no CLW2, do médico
102 = Dra Sullege).

Nossa importação junta fichas com o mesmo CPF e **fica sempre com a de id menor**, que aqui é a
`z-` desativada. Aí ela:

- herdou o nome da ficha ruim (`z-Fanilde…`);
- herdou `ativo = 0` da ficha ruim;
- **recebeu as receitas da ficha boa**, inclusive a de junho de 2026.

Depois, a normalização que criou os vínculos médico–paciente copiou esse mesmo `ativo = 0` para o
vínculo da Dra Sullege — o segundo cadeado.

Resumo em uma frase: **a paciente ativa do CLW2 foi importada por cima da ficha que a clínica tinha
jogado no lixo, e ficou com a etiqueta de lixo.**

## Não é só ela: 8 fichas com o mesmo defeito

Cruzamos o dump do CLW2 (06/08/2026) com produção. Toda ficha nossa que está arquivada mas cuja
origem no CLW2 está **ativa** é erro nosso, do mesmo tipo:

| ficha | nome | receitas | última | médico |
|---|---|---|---|---|
| 16742 | z-Fanilde Pirro Viana Paquer | 9 | 11/06/2026 | Sullege Suzuki |
| 16555 | Hugo Castelani Barreto Passos | 7 | 01/06/2026 | Glaucia Antonioli |
| 16453 | Gisele Rangel da Cunha Ferreira | 3 | 04/12/2025 | Eloisa Ayres |
| 16984 | Jose Vittor Siqueira Coco | 2 | 29/09/2025 | Roberta Saldanha |
| 16879 | Simone Nascimento Madeira | 3 | 07/08/2025 | Roberta Saldanha |
| 16629 | Rosane Maria Santanna | 1 | 31/05/2025 | Patrícia Sant'Anna |
| 16569 | Daniela Cosim da Silva. | 2 | 02/05/2025 | Glaucia Antonioli |
| 16528 | Isabelle Carvalho Curvo | 1 | 10/04/2025 | Sullege Suzuki |

Em todos os oito o padrão é idêntico: no CLW2 existem 2, 3 ou 4 fichas da mesma pessoa, as antigas
desativadas e a última ativa; aqui sobrou uma ficha só, com o `ativo` da antiga.

Os casos mais urgentes são os dois de 2026 — **Fanilde** (Dra Sullege) e **Hugo Castelani** (Dra
Glaucia): são pacientes em tratamento agora, cujos médicos não os acham.

Outras 6 fichas arquivadas com receita **não** entram nessa conta, porque no CLW2 elas também estão
desativadas (16401 `xxMarcelo Michel1`, 16409, 16505, 16552, 17062 Patricia Pereira Lopes — que já
estava na fila de duplicados — e 17370). Essas precisam de resposta da clínica, não de reparo
automático.

## O efeito colateral perigoso

Quando a médica não acha a paciente, o sistema oferece **"Cadastrar paciente"**. E a rede de
segurança que deveria avisar "essa pessoa já existe no sistema" também filtra por ficha ativa —
testamos: ela devolve **0 candidatos** para "Fanilde Pirro Viana Paquer".

Ou seja: o caminho natural da médica é criar um **quinto** cadastro da mesma pessoa, com o histórico
de nove receitas ficando para trás. É a mesma fábrica de cadastros repetidos que os jobs anteriores
passaram uma semana limpando.

Sinal de que a clínica já está improvisando: hoje às 15:36 alguém copiou a receita de junho na ficha
#16742 (gerou a 16742-0009, R$ 1.293,00) e cancelou 8 segundos depois.

## O reparo

Foi criado o comando `pacientes:reativar`, que **só reativa, nunca desativa**:

1. reativa a ficha (`pacientes.ativo = 1`);
2. reativa o vínculo dos médicos que **têm receita** com aquela ficha (prova de que atendem a
   pessoa) — vínculo inativo de médico sem receita fica como está, porque aí pode ser arquivamento
   de verdade;
3. com `--limpar-prefixo`, tira o marcador do nome (`z-Fanilde Pirro Viana Paquer` →
   `Fanilde Pirro Viana Paquer`). Nome que é só marcador, sem sobrenome (`zzz-Marcelo1`), fica
   intacto — e nome de gente que começa com Z ou X (Zilda, Xuxa) não é cortado.

Sem lista de ids ele apenas **lista os candidatos** — nada é gravado. Rodado assim em produção hoje,
somente leitura.

Validado ponta a ponta no ambiente local (mesmo dump de produção), entrando como a Dra Sullege:

- antes: `.wp-agent/screenshots/f8b5e9c5-antes-busca-paciente.png` — "Nenhum paciente encontrado";
- depois: `.wp-agent/screenshots/f8b5e9c5-depois-busca-paciente.png` — "Fanilde Pirro Viana Paquer ·
  850.146.051-68 · 09/06/1978" na lista.

### Para aplicar em produção

Actions → **Reativar pacientes arquivados (PRODUÇÃO)**:

- os dois urgentes: `ids = 16742,16555`
- os oito do quadro: `ids = 16742,16555,16453,16984,16879,16629,16569,16528`

com `limpar_prefixo` marcado e `force` marcado. O workflow tira backup do banco antes, mostra a
simulação e só então aplica. Sem `force`, só simula.

Depois disso a receita de junho volta a aparecer para a Dra Sullege e ela consegue seguir o
atendimento na ficha certa, com o histórico inteiro.

## O que ainda precisa de decisão

1. **Aplicar o reparo nos 8** (ou só nos 2 urgentes) — é o "pode seguir" da clínica.
2. **A ficha #16552** ("Fanilde Pirro Viana Paquer", nascimento 25/08/1988, celular
   (99) 99999-9999, 1 receita de 23/06/2025): é a ficha ruim que a clínica desativou no CLW2 em
   2025. Fica arquivada, mas vale confirmar que aquela receita de 2025 pertence à mesma pessoa — se
   pertencer, o certo é fundir na #16742 para o histórico não ficar partido.
3. **A receita 16742-0009**, criada e cancelada hoje às 15:36: confirmar que foi tentativa de
   contornar o problema e pode ficar cancelada.
4. **Impedir a repetição**: hoje, quando o médico não acha o paciente, o sistema diz "Nenhum
   paciente encontrado" mesmo existindo ficha arquivada com aquele nome/CPF, e a rede de segurança
   do cadastro também não a mostra. A recomendação é a tela avisar "existe um cadastro arquivado
   com este nome — fale com a administração" em vez de convidar a criar outro. Não está feito.
