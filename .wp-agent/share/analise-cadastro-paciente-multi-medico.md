# Análise: cadastro de paciente vinculado a mais de um médico

**Data:** 2026-07-21
**Contexto:** Hoje um paciente pertence a **um único médico**. Quando um segundo
médico tenta cadastrar um paciente que já existe, o cadastro é **bloqueado**.
O objetivo é decidir entre duas abordagens para resolver isso.

---

## 1. Como o sistema funciona hoje (fatos do código)

| Peça | Situação atual | Onde |
|------|----------------|------|
| Vínculo médico↔paciente | **FK única** `pacientes.medico_id` — 1 paciente = 1 médico | `create_pacientes_table`, `Paciente::medico()` |
| Controle de acesso | Médico só enxerga `where medico_id = seu`; secretária vê os médicos da clínica; admin/callcenter vê tudo | `User::canAccessPaciente()`, `PacienteController::index()` / `search()` |
| Troca de médico | Uma vez preenchido, `medico_id` **não pode mais mudar** (nem admin) | `PacienteController::update()` linha ~417 |
| CPF | Único **apenas por regra de validação** `unique:pacientes,cpf` — **não há unique no banco** | `store()`/`update()` |
| Nº Registro (`codigo`) | **Único no banco** (`->unique()`) + regra de validação | migration linha 16 |
| E-mail (`email1`) | Obrigatório, **não é único** | `store()` |
| Prescrições | `receitas` já guarda **`(paciente_id, medico_id)`** — ou seja, já existe um M:N implícito médico↔paciente via receita | `create_receitas_table` |

### O que realmente causa o bloqueio
O 2º médico é barrado pela regra **`cpf` unique global**. Mesmo que passasse, ele
**não veria** o paciente do outro médico (filtro `medico_id`), então não teria como
"reaproveitar" o cadastro — o efeito prático é "cadastro impedido".

### Dados reais deste ambiente (medidos agora)
- **1153** pacientes, todos com `medico_id`.
- Só **59** têm CPF preenchido → hoje o CPF é uma chave fraca na prática.
- **0** CPFs duplicados (a regra bloqueia).
- **11 grupos de nome duplicado, todos entre médicos diferentes (~23 registros)** →
  **o problema que você teme na Opção 1 já está acontecendo**: são pacientes iguais
  cadastrados por médicos diferentes, e o admin já enxerga "2 versões".
- **4** pacientes já têm receitas de **mais de um médico** no mesmo registro.

> Leitura importante: a duplicação de registro **já existe hoje de forma
> descontrolada** (via nome), e o modelo de receitas **já trata o mesmo paciente
> como podendo ser atendido por vários médicos**. Isso pesa a favor da Opção 2.

---

## 2. Opção 1 — Um cadastro por médico (registros separados)

Cada médico tem seu próprio registro de paciente. Regra: um médico não pode
cadastrar o **mesmo** paciente duas vezes, mas médicos diferentes têm cópias
independentes.

**Implementação:** trocar o unique global de CPF por unique **composto**
`(medico_id, cpf)`; idem para `codigo`. Praticamente **nenhuma mudança** no modelo
de acesso (continua tudo em cima de `medico_id`).

### Prós
- **Baixo esforço.** Muda só as regras de unicidade (global → por médico). Todo o
  resto do sistema (acesso, listagem, busca, receita) continua igual.
- **Isolamento total** entre médicos "de graça" — cada um tem seus próprios
  Observações, Nº Registro, Indicado Por, e também dados principais.
- Risco técnico baixo, sem migração de dados complexa.

### Contras
- **Duplicação de dados principais** (nome, CPF, endereço, telefone, nascimento).
  Se o paciente muda de telefone, precisa atualizar em N cadastros → dados ficam
  divergentes entre médicos.
- **Visão do admin/callcenter vira um problema** — exatamente o que você citou:
  pesquisar "João" retorna 2–3 Joões. **Isso já acontece hoje** e a Opção 1
  **oficializa e amplia** o problema.
- **Relatórios e métricas distorcidos** (contagem de pacientes, exportações,
  integrações Tiny/RD) contam a mesma pessoa várias vezes.
- **Não atende o anexo.** O anexo pede um **cadastro único compartilhado** onde só
  3 campos (Observações, Nº Registro, Indicado Por) são privados por médico. A
  Opção 1 separa **tudo**, não só esses 3 campos.
- Integrações externas (`cpf`, `rd_contact_id`, `tiny_id`) passam a ter colisão
  conceitual: qual dos N registros é "a pessoa" no CRM/ERP?

---

## 3. Opção 2 — Cadastro único + vínculo por médico (recomendada)

Um único registro de paciente (dados principais compartilhados). O vínculo com
cada médico vira uma **tabela de relacionamento** `medico_paciente`, que carrega os
campos privados do anexo. Ao digitar CPF/e-mail no "Novo Paciente", o sistema
**localiza o paciente existente e traz os dados principais** (a criação vira um
*update* dos dados principais + criação do vínculo com os campos privados em branco).

### Modelo de dados proposto
```
pacientes                         (dados principais compartilhados — nome, CPF,
                                   nascimento, endereço, telefones, e-mails)

medico_paciente  (NOVO pivot)     medico_id, paciente_id,
                                   anotacoes      (Observações — privado)
                                   codigo         (Nº Registro — privado, único POR médico)
                                   indicado_por   (Indicado Por — privado)
                                   created_by_user_id / timestamps
                                   UNIQUE(medico_id, paciente_id)
```
Os campos `anotacoes`, `codigo`, `indicado_por` **saem** de `pacientes` e passam
para o pivot (é o que o anexo pede). `cpf` continua no paciente e volta a ser
**único global** (é o que define "a pessoa").

### Prós
- **Zero duplicação** dos dados principais → 1 pessoa = 1 registro. Resolve a dor do
  admin ("uma versão só") e mantém relatórios/integrações corretos.
- **Atende exatamente o anexo**: só Observações, Nº Registro e Indicado Por são
  privados por médico; na 2ª clínica esses campos vêm **em branco**.
- **Alinhado ao que o sistema já faz**: `receitas(paciente_id, medico_id)` já é um
  M:N; o pivot só formaliza esse vínculo e resolve o acesso de forma coerente.
- Fluxo de cadastro melhor: digitou CPF conhecido → puxa dados → evita retrabalho e
  erros de digitação.
- `Nº Registro` único **por médico** (não mais global) — hoje é global, o que já é
  uma limitação; a Opção 2 corrige isso naturalmente.

### Contras
- **Refatoração maior** (é o custo real desta opção):
  - Nova migration + tabela pivot; migrar os **1153** vínculos atuais
    (`medico_id` → linha no pivot, movendo `anotacoes/codigo/indicado_por`).
  - Reescrever **acesso**: `canAccessPaciente`, filtros de `index()`/`search()`
    e `getMedicoIdsDaClinica` para olhar o pivot em vez do FK único.
  - Ajustar `store/update/autosave/quickCreate` e o front (Form/Index/Show).
  - Fluxo novo de "buscar por CPF/e-mail e pré-preencher" + a **2ª tela em branco**
    do anexo (salvar cadastro → tela com Indicado Por / Nº Registro / Observações
    vazios).
- **Regras de edição dos dados principais**: se dois médicos compartilham o mesmo
  paciente, quem pode editar nome/endereço? Precisa decidir política (ex.: dados
  principais editáveis por qualquer médico vinculado + admin, com log de auditoria
  — já existe `created_by/updated_by`).
- **Privacidade**: ao "puxar" um paciente de outra clínica, o 2º médico passa a ver
  os **dados principais** já cadastrados (nome/CPF/telefone). O anexo assume isso
  ("um segundo médico poderá acessar os dados pré-cadastrados"); só os 3 campos
  ficam ocultos. Confirmar que isso é aceitável do ponto de vista LGPD/negócio.

---

## 4. Comparativo direto

| Critério | Opção 1 (por médico) | Opção 2 (único + vínculo) |
|---|---|---|
| Esforço de implementação | **Baixo** | Médio/Alto |
| Duplicação de dados | Alta (piora hoje) | **Nenhuma** |
| Visão do admin/callcenter | Confusa (N versões) | **Limpa (1 versão)** |
| Relatórios / integrações (Tiny/RD) | Distorcidos | **Corretos** |
| Aderência ao anexo | Não atende | **Atende** |
| Manutenção de dados (telefone mudou etc.) | Repetir em N cadastros | **1 lugar** |
| Privacidade entre médicos | Total (tudo separado) | Só 3 campos privados |
| Risco de migração | Baixo | Requer migração dos 1153 vínculos |

---

## 5. Recomendação

**Adotar a Opção 2 (cadastro único + tabela de vínculo `medico_paciente`).**

Motivos decisivos:
1. **É o que o anexo especifica** — cadastro compartilhado com apenas 3 campos
   privados por médico, e 2ª tela em branco.
2. **Resolve a dor central do admin** (uma pessoa = um registro). A Opção 1 não só
   deixa de resolver como **amplia** um problema que os dados mostram já existir
   (11 grupos de nomes duplicados entre médicos).
3. **É coerente com o modelo que já existe**: `receitas` já trata paciente como
   atendível por vários médicos. O pivot só torna isso explícito e consistente no
   controle de acesso.
4. Mantém **relatórios, exportações e integrações externas** corretos, sem contar a
   mesma pessoa várias vezes.

O único ponto real a favor da Opção 1 é o **custo de implementação**. Se houver
necessidade de entregar um paliativo **imediato**, dá para desbloquear em uma linha
mudando o unique de CPF de global para composto `(medico_id, cpf)` — mas isso
**consolida a duplicação** e depois é caro de desfazer. Recomendo ir direto para a
Opção 2.

### Esboço de plano para a Opção 2
1. **Migration**: criar `medico_paciente (medico_id, paciente_id, anotacoes,
   codigo, indicado_por, created_by_user_id, timestamps, UNIQUE(medico_id,
   paciente_id), UNIQUE(medico_id, codigo))`.
2. **Data migration**: para cada paciente, inserir 1 linha no pivot com seu
   `medico_id` atual e mover `anotacoes/codigo/indicado_por`. Depois remover essas
   colunas de `pacientes` (ou deixá-las deprecadas por segurança em 1ª fase).
3. **Modelos**: `Paciente::medicos()` belongsToMany com pivot; `Medico::pacientes()`
   belongsToMany. Manter `medico_id` só enquanto durar a transição, se quiser.
4. **Acesso**: reescrever `canAccessPaciente`, `index()`, `search()` e
   `getMedicoIdsDaClinica` para usar `whereHas('medicos', ...)`.
5. **CPF volta a ser único global**; `codigo` passa a ser único **por médico** (no
   pivot).
6. **Fluxo "Novo Paciente"**: ao digitar CPF (ou e-mail) já existente, buscar e
   pré-preencher os dados principais; salvar = *upsert* do paciente + criação do
   vínculo. Implementar a 2ª tela em branco (Indicado Por / Nº Registro /
   Observações) conforme o anexo.
7. **Política de edição dos dados principais** + auditoria (reusar
   `created_by_user_id` / `updated_by_user_id`).
8. Revisar exportações/integrações (Tiny, RD, relatórios) para o novo modelo.
