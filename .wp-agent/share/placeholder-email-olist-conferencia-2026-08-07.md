# O placeholder `@cadastraremail.rsk` chegou em quem veio do oList sem e-mail?

**Sim — 690 pacientes.** Conferido em produção agora (só leitura, nada foi alterado).

Importante: o comando que rodei na subida (`pacientes:normalizar-emails-placeholder`) **não
responde essa pergunta**. Ele só procura os domínios *quebrados* (`@cadastraremail.com` e
`@cadastrar_email.com`) para trocá-los pelo `.rsk`. O "nenhum e-mail de marcação para normalizar"
significava "não há domínio errado para consertar", não "não há placeholder". Quem coloca o
placeholder é a própria carga do oList, no momento da importação.

## Números em produção

**Base toda — 1.432 pacientes**

| | |
|---|---|
| com placeholder `@cadastraremail.rsk` | **690** |
| com e-mail de verdade | 476 |
| sem e-mail nenhum | 266 |

**Só os que vieram do oList (`tiny_id` preenchido) — 1.240 pacientes**

| | |
|---|---|
| com placeholder `@cadastraremail.rsk` | **690** |
| com e-mail de verdade | 463 |
| sem e-mail nenhum | 87 → **76 não têm telefone** + 11 explicados abaixo |

Os **690 placeholders da base inteira são exatamente os 690 do oList** — ou seja, nenhum cadastro
de outra origem ganhou marcação, que é a regra combinada (placeholder só na importação do oList,
nunca em cadastro novo feito no sistema).

## Os 87 do oList que ficaram sem e-mail

- **76 não têm telefone.** É a regra explícita do código: sem telefone não há o que gerar
  (`<dígitos>@cadastraremail.rsk`), e o cadastro fica sem e-mail mesmo — estado válido desde que
  o campo virou opcional.
- **11 têm telefone.** Fui atrás de cada um:
  - **9 não vieram do oList** — foram criados pela importação CLW2 de hoje (`created_at`
    18:57:42, o horário exato do apply) e todos têm receita `clw2_importada`. O `tiny_id` deles é
    posterior (sync às 20:10), porque o observer empurra paciente novo para o oList. Cadastro
    vindo do CLW2 **não** ganha placeholder de propósito. São: Thais Amaro, Franciely Tatiane
    Ignacio dos Santos, Lorena Leite Castilho, Pedro Sambi Freitas, Nara de Oliveira Alves,
    Sarah Crespin, Adriana Haberkorn Iavelberg, Carolina Martins Carreiro e Marcela de Carvalho
    Ponce Kawauti.
  - **2 são da carga de junho** (#17152 Lilian Martins e #16956 VICTORIA MALDONADO) e o telefone
    que eles têm aqui — `(00) 00000-0000` — veio do CLW2. O placeholder é montado com o telefone
    **do contato no oList**, não com o que está no cadastro daqui; se ele tivesse vindo do oList,
    o código teria gerado `0000000000@cadastraremail.rsk` (tem 10 dígitos, passa na regra). Não
    gerou, logo o contato do oList não tinha telefone válido.

**Conclusão: não há nenhum caso de "veio do oList sem e-mail, tinha telefone e ficou sem
marcação".** Nada a corrigir.

## Como conferir você mesmo

Actions → workflow **"Migração CLW2 / limpeza (PRODUÇÃO)"** → etapa **`diag-emails`**. É só
leitura e imprime exatamente a tabela acima, mais a lista nominal dos que ficaram sem e-mail com
telefone.
