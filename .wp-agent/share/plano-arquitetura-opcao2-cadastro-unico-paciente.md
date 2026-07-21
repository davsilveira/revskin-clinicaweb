# Plano de arquitetura — Opção 2: cadastro único de paciente com vínculo por médico

**Data:** 2026-07-21 (rev. 5) · **Ambiente:** `revski-main` · **Status:** proposta técnica

## Decisões do produto (confirmadas pelo cliente)
1. **Cadastro único** por pessoa. Um paciente = um registro em `pacientes`.
2. **Dados principais são compartilhados e editáveis por ambos os médicos.** Se um
   médico altera um dado compartilhado, o outro passa a ver a alteração.
3. **Campos privados por médico** (do anexo): **Observações** (`anotacoes`),
   **Nº Registro** (`codigo`) e **Indicado por** (`indicado_por`). Ficam invisíveis
   entre médicos e, na 2ª clínica, vêm **em branco**.
4. **Sem controle de concorrência** (lock/merge): "raramente um paciente estará em
   dois médicos ao mesmo tempo", então usamos "último a salvar vence" nos dados
   compartilhados, com auditoria (`updated_by_user_id`) já existente.
5. **Arquivar é por vínculo** (`medico_paciente.ativo`): cada médico arquiva/desativa
   o paciente só na sua visão; o registro segue ativo para os demais. `pacientes.ativo`
   global fica como desativação de nível admin (some para todos).
6. **LGPD: sem restrição de dedup.** O sistema é único e os dados pertencem ao
   sistema (não ao médico); logo o lookup por CPF pode trazer os dados principais de
   um paciente pré-cadastrado por qualquer clínica, sem barreira legal adicional.

---

## 1. Modelo de dados alvo

### 1.1 Nova tabela pivot `medico_paciente`
```
medico_paciente
  id
  medico_id            FK medicos    (onDelete cascade)
  paciente_id          FK pacientes  (onDelete cascade)
  anotacoes            text  null    -- Observações (privado)
  codigo               string null   -- Nº Registro (privado)
  indicado_por         string null   -- Indicado por (privado)
  ativo                bool  default true  -- arquivar por vínculo (decisão 5)
  origem               string null   -- 'form' | 'receita' | 'assistente' | 'callcenter' | 'import' (auditoria de como o vínculo nasceu)
  created_by_user_id   FK users null
  updated_by_user_id   FK users null
  created_at / updated_at
  UNIQUE (medico_id, paciente_id)    -- 1 vínculo por par
  UNIQUE (medico_id, codigo)         -- Nº Registro único POR médico (parcial: codigo not null)
  INDEX (paciente_id)
```

### 1.2 Mudanças em `pacientes`
- **Remover do escopo "compartilhado"** os 3 campos privados. Estratégia em 2 fases
  (ver §7) para não quebrar nada:
  - Fase A: manter as colunas `anotacoes/codigo/indicado_por` em `pacientes`
    (deprecadas), copiar os valores para o pivot na migração de dados.
  - Fase B (depois de tudo migrado e validado): `dropColumn` dessas 3 colunas.
- `cpf` volta a ser **único global** — adicionar índice `unique` no banco
  (hoje é só regra de validação). É a chave que define "a pessoa".
- **`medico_id` legado**: manter a coluna por ora (compat com relatórios/exports que
  já filtram por ela). Passa a significar "médico de origem / primeiro vínculo".
  Não é mais a fonte de verdade do acesso. Remoção fica para uma fase futura.

### 1.3 Relações Eloquent
```php
// Paciente
public function medicos(): BelongsToMany
{
    return $this->belongsToMany(Medico::class, 'medico_paciente')
        ->withPivot(['anotacoes', 'codigo', 'indicado_por',
                     'created_by_user_id', 'updated_by_user_id'])
        ->withTimestamps();
}

// Medico
public function pacientes(): BelongsToMany  // substitui o hasMany atual
{
    return $this->belongsToMany(Paciente::class, 'medico_paciente')->withPivot(...);
}
```
Opcional: model dedicado `MedicoPaciente extends Pivot` para encapsular regras e o
`updated_by`.

---

## 2. O que é compartilhado × privado

| Campo | Local | Visibilidade |
|---|---|---|
| nome, data_nascimento, sexo, fototipo | `pacientes` | Compartilhado (edição por ambos) |
| cpf, rg | `pacientes` | Compartilhado · **cpf = unique global** |
| telefone1/celular/telefone3, `telefones[]` | `pacientes` | Compartilhado |
| email1, email2 | `pacientes` | Compartilhado |
| endereço completo (tipo_endereco…cep, pais) | `pacientes` | Compartilhado |
| tiny_*, rd_* | `pacientes` | Compartilhado (1 pessoa = 1 contato no CRM/ERP) |
| **anotacoes (Observações)** | **pivot** | **Privado por médico** |
| **codigo (Nº Registro)** | **pivot** | **Privado · unique por médico** |
| **indicado_por (Indicado por)** | **pivot** | **Privado por médico** |

---

## 3. Controle de acesso (reescrita)

Trocar todo filtro baseado em `pacientes.medico_id` por pertencimento ao pivot.

**`User::canAccessPaciente()`**
```php
if ($this->isAdmin() || $this->isCallcenter()) return true;
if ($this->isMedico())
    return $paciente->medicos()->whereKey($this->medico_id)->exists();
if ($this->isSecretaria() && $this->clinica_id)
    return $paciente->medicos()
        ->whereIn('medicos.id', $this->getMedicoIdsDaClinica())->exists();
return false;
```

**`PacienteController::index()` / `search()`** — trocar
`->where('medico_id', $user->medico_id)` por
`->whereHas('medicos', fn($q) => $q->whereKey($user->medico_id))` e, para secretária,
`->whereHas('medicos', fn($q) => $q->whereIn('medicos.id', $ids))`.

**Filtro por médico na listagem** (`?medico_id=`) idem via `whereHas`.

> Nota de performance: `whereHas` sobre pivot com índice `(paciente_id)` +
> `(medico_id, paciente_id)` é barato na escala atual (1153 pacientes / 77 médicos).

---

## 4. Fluxo "Novo Paciente" (upsert + vínculo)

Este é o coração da Opção 2 e o que o anexo descreve.

### 4.1 Busca/pré-preenchimento por CPF (e e-mail)
Novo endpoint **`GET /api/pacientes/lookup?cpf=&email=`** (ou reaproveitar `search`):
- Se achar paciente por **CPF** (chave forte) → retorna os **dados principais** para
  o front pré-preencher. Fallback opcional: e-mail, ou `nome + data_nascimento`.
- Retorna também `ja_vinculado: bool` (se o paciente já tem vínculo com o médico
  atual) para o front decidir entre "criar vínculo" e "abrir existente".
- **Respeita acesso**: como o médico não deveria "ver" pacientes de terceiros na
  listagem, o lookup é um canal explícito de deduplicação — retorna só os campos
  principais (nunca `anotacoes/codigo/indicado_por` de outro médico).

No `PatientDrawer`, ao completar o CPF (11 dígitos) dispara o lookup; se casar,
mostra aviso "Paciente já cadastrado — os dados foram carregados" e trava os campos
principais como edição do registro existente.

### 4.2 Salvar = upsert + vínculo
`store()` passa a:
1. Resolver paciente por `cpf` (se enviado) → se existe, é **update** dos dados
   principais compartilhados; se não, **create**.
2. `syncWithoutDetaching` no pivot com `[medico_id => {anotacoes, codigo,
   indicado_por, created_by_user_id, updated_by_user_id}]` — cria o vínculo se
   ainda não houver.
3. Se o vínculo já existir para aquele médico → é edição do vínculo dele.

**Helper central `garantirVinculo(Paciente $p, int $medicoId, array $priv = [])`.**
Encapsula o `syncWithoutDetaching`/upsert do pivot e **é reusado por todos os
caminhos** que hoje setam `paciente->medico_id`: o form (`store/autosave/quickCreate`),
o **assistente de receita** e a **emissão de receita**. Isso garante que "criar uma
receita para um paciente sem médico" continue vinculando — só que agora criando uma
linha no pivot em vez de escrever no FK único.

### 4.3 A "2ª tela em branco" (anexo)
Sequência do anexo: ao **Salvar** o cadastro de um paciente que já existia em outra
clínica, abrir a **2ª etapa** com **Indicado por / Nº Registro / Observações
vazios** (não trazer os do 1º médico). Implementação: passo 2 no drawer que só
coleta os 3 campos privados e grava no pivot do médico atual. Para paciente novo
(sem vínculo prévio), o fluxo pode ser em tela única.

---

## 5. Impacto no back-end (inventário)

| Arquivo | Mudança |
|---|---|
| `database/migrations/*_create_medico_paciente_table.php` | **novo** pivot |
| `database/migrations/*_migrate_pacientes_to_pivot.php` | data migration (§7) |
| `database/migrations/*_cpf_unique_pacientes.php` | unique global em `cpf` |
| `app/Models/Paciente.php` | `medicos()` belongsToMany; remover `anotacoes/codigo/indicado_por` do `$fillable` compartilhado; acessores de pivot |
| `app/Models/Medico.php` | `pacientes()` vira belongsToMany |
| `app/Models/User.php` | `canAccessPaciente()` via pivot |
| `app/Http/Controllers/PacienteController.php` | `store/update/autosave/quickCreate` → upsert+pivot; `index/search/show/destroy` → acesso via pivot; `destroy` arquiva o **vínculo** (não o paciente global); novo `lookup()`; mover validação de `codigo` (unique por médico) e `cpf` (unique global) |
| **`app/Http/Controllers/AssistenteReceitaController.php`** | **CRÍTICO.** Linhas 230-231 e 462-463 fazem `$paciente->update(['medico_id' => $medicoId])` quando o paciente não tem médico. Isso é um **2º caminho** de criação de vínculo. Trocar por `garantirVinculo($paciente, $medicoId)` (upsert no pivot). Também `initialPacienteMedicoLabel`/`initialPacienteMedicoId` (l.104-114) assume 1 médico |
| **`app/Http/Controllers/ReceitaController.php`** | `create/edit` (l.126-133, 150): hoje bloqueia/prepara médico assumindo `paciente->medico_id` único. Ao emitir receita para paciente sem vínculo com o médico escolhido, **criar o vínculo** em vez de tratar como "de outro médico". `defaultMedicoId` deixa de vir de `paciente->medico_id` |
| `app/Observers/PacienteObserver.php` | segue observando dados compartilhados (Tiny). Campos privados não disparam sync — ok, saem de `pacientes`. Confirmar que o job de sync continua com 1 contato por pessoa |
| `app/Http/Controllers/ExportController.php` | filtros do export de `pacientes` por `medico_id` e `indicado_por` (l.262) → via pivot; export de `receitas`/`atendimentos` por `medico_id` **não muda** (têm FK própria) |
| `app/Jobs/ProcessExportJob.php` | l.347 `select 'pacientes:id,medico_id'` → carregar médicos via pivot |
| `app/Services/Export/FieldCatalog.php` | l.344 resolver `paciente->medico_id`; `codigo/indicado_por/anotacoes` do paciente passam a exigir contexto de médico (ou vira "todos os vínculos" concatenados) |
| `app/Http/Controllers/CallCenterController.php` | `index/show` carregam `paciente.medico` (single). Callcenter deve usar **`atendimento.medico`** (FK própria do atendimento), não `paciente.medico`. Callcenter enxerga todos (não filtra por vínculo) |
| `app/Http/Controllers/RelatorioController.php` | relatórios filtram por **`receita.medico_id`** (FK própria) → **sem impacto**. Só um eventual "nº de pacientes por médico" passa a contar via pivot |
| `app/Console/Commands/ReestabelecerPacientesMedico.php` | l.115 move `pacientes.medico_id`; passa a mover/mesclar **linhas do pivot** (dedup por `(medico,paciente)`); ajustar contagens l.316 |
| `app/Console/Commands/AuditMedicoLinks.php` | l.883 mescla médicos duplicados reatribuindo `pacientes.medico_id` → passa a mesclar linhas do pivot (evitando violar `UNIQUE(medico,paciente)`) |
| `routes/web.php` | rota `pacientes.lookup` |

**Uniqueness — resumo da migração de regras:**
- `cpf`: validação global → **unique global no banco** (com `ignore` no update).
- `codigo`: hoje unique global no banco → **unique por médico no pivot**
  (`Rule::unique('medico_paciente','codigo')->where('medico_id',$id)`), e **dropar**
  o unique de `pacientes.codigo`.

---

## 6. Impacto no front-end

| Arquivo | Mudança |
|---|---|
| `resources/js/Components/PatientDrawer.jsx` | **usado em 4 telas** (Pacientes/Index, Receitas/Index, Receitas/Form, CallCenter/Show). Os 3 campos privados passam a refletir o pivot do **médico do contexto** — que **não é necessariamente o usuário logado** (na receita, admin/callcenter/secretária escolhem o médico). Precisa receber `medicoContextoId` e carregar/salvar o pivot desse médico. Adicionar lookup por CPF + pré-preenchimento; passo 2 "em branco" do anexo; `medico_id` deixa de ser imutável — vira "adicionar vínculo" |
| `resources/js/Pages/Receitas/Form.jsx` (l.2458) | ao editar o paciente pelo drawer dentro da receita, o médico do contexto é o médico **da receita** (`data.medico_id`), não o logado |
| `resources/js/Pages/Receitas/Index.jsx` (l.541) e `CallCenter/Show.jsx` (l.267) | mesmo ajuste de `medicoContextoId` |
| `resources/js/Pages/Pacientes/Index.jsx` | coluna "médico" pode listar **vários**; filtro por médico via pivot; contagem sem duplicar; arquivar = arquivar o vínculo do médico visualizado |
| `resources/js/Pages/Pacientes/Show.jsx` | exibir os campos privados **do médico logado**; para admin/callcenter, mostrar por médico (lista de vínculos) |
| endpoints `autosave`/`quickCreate` | payload dos 3 campos + `medico_id` de contexto vai para o pivot |

---

## 6.1 Impacto por fluxo (todas as superfícies que tocam paciente)

Levantamento feito por varredura de `medico_id`/`ativo`/`PatientDrawer` em `app/` e
`resources/js/`. Um paciente é criado/alterado/vinculado em **mais lugares que só a
tela de pacientes** — todos precisam usar o pivot:

1. **Tela de Pacientes** (`Pacientes/Index` + `PatientDrawer`): CRUD principal.
   Listagem, busca, criar, editar, arquivar (agora por vínculo).
2. **Emitir receita** (`ReceitaController@create/edit`, `Receitas/Form`): hoje o
   médico-padrão vem de `paciente->medico_id` e há bloqueio quando o paciente é "de
   outro médico" (l.128). Com pivot: escolher o médico da receita **cria/garante o
   vínculo**; some o conceito de "paciente de outro médico".
3. **Editar paciente dentro da receita** (`Receitas/Form` l.2458 → `PatientDrawer`):
   o usuário edita o paciente sem sair da receita. Os campos privados exibidos/salvos
   são os do **médico da receita**, não os do usuário logado. **Este é o caso que o
   cliente citou** e exige o `medicoContextoId` no drawer.
4. **Assistente de receita** (`AssistenteReceitaController`): ao gerar receita para um
   paciente sem médico, faz `paciente->update(['medico_id'=>...])` (l.230-231/462-463)
   → passa a `garantirVinculo(...)`. O rótulo do médico inicial (l.104-114) precisa
   escolher um vínculo relevante em vez de assumir um só.
5. **Novo paciente a partir da receita** (`Receitas/Index` l.541 → `PatientDrawer`) e
   **Call Center** (`CallCenter/Show` l.267 → `PatientDrawer`): mesmos ajustes.
6. **Call Center** (`CallCenterController`): lista atendimentos e mostra dados do
   paciente. Deve usar `atendimento.medico` (FK do atendimento), não `paciente.medico`.
   Não filtra por vínculo (callcenter vê tudo).
7. **Relatórios** (`RelatorioController`): filtram por `receita.medico_id` (FK própria
   da receita) → **sem impacto**. Só um eventual "pacientes por médico" muda de fonte
   (FK → pivot).
8. **Exportações** (`ExportController`, `ProcessExportJob`, `FieldCatalog`): export de
   pacientes filtra por `medico_id`/`indicado_por` → via pivot; export de
   receitas/atendimentos não muda. Campos privados no export do paciente viram
   "por vínculo" (ou concatenados).
9. **Integração Tiny/RD** (`PacienteObserver`, `SyncClienteTinyJob`): melhora — 1
   pessoa = 1 contato. Validar que o sync continua disparando só para dados
   compartilhados (os privados saem de `pacientes`).
10. **Comandos de manutenção** (`ReestabelecerPacientesMedico`, `AuditMedicoLinks`):
    hoje reatribuem `pacientes.medico_id`; passam a mover/mesclar linhas do pivot,
    deduplicando por `(medico_id, paciente_id)`.

## 6.2 Impacto por papel de usuário

| Papel | Hoje | Depois (Opção 2) |
|---|---|---|
| **Médico** | Vê só pacientes com `medico_id` = seu; não troca médico; campos privados são colunas do paciente | Vê pacientes onde tem **vínculo** (pivot); `codigo/indicado_por/anotacoes` vêm do **seu** vínculo; arquivar afeta só o vínculo dele; ao emitir receita para paciente existente, ganha vínculo automático |
| **Admin** | Vê tudo; edita qualquer paciente; um paciente = um médico | Vê tudo; **um paciente = vários vínculos**; na tela do paciente vê os campos privados **por médico**; pode desativar global (`pacientes.ativo`) além de por vínculo |
| **Call Center** | Vê tudo; usa `paciente.medico` (single) | Vê tudo; passa a usar `atendimento.medico`; ao criar/editar paciente pelo drawer, escolhe o médico de contexto; campos privados são os do médico escolhido |
| **Secretária** | Vê pacientes dos médicos da sua clínica (via `medico_id`); médico obrigatório ao criar | Vê pacientes com vínculo a **algum** médico da clínica (pivot); médico obrigatório continua; campos privados por médico da clínica |

> Observação de acesso: hoje o médico **não vê** pacientes de terceiros nem na busca.
> O `lookup` por CPF é o único canal que revela um paciente pré-existente de outra
> clínica — liberado (decisão 6, LGPD). Fora isso, listagem/busca continuam restritas
> ao vínculo, então nada vaza silenciosamente.

---

## 7. Migração de dados (1153 pacientes)

Migration idempotente, em transação:
1. Para cada `paciente` com `medico_id` não nulo → inserir linha no pivot
   `(medico_id, paciente_id, anotacoes, codigo, indicado_por, ativo=pacientes.ativo,
   origem='import', created_by_user_id=created_by_user_id, timestamps)`. Pacientes
   sem `medico_id` (se houver) ficam sem vínculo — válido.
2. **Antes de aplicar `cpf` unique global**: rodar checagem de colisão. Hoje há **0**
   CPFs duplicados, então é seguro; ainda assim o script deve abortar e listar se
   aparecer algum antes do deploy.
3. **Nomes duplicados entre médicos (11 grupos / ~23 registros)**: NÃO são unificados
   automaticamente (podem ser homônimos). Gerar **relatório de possíveis
   duplicados** (mesmo nome, e opcionalmente mesma data de nascimento) para revisão
   manual/mesclagem posterior — não bloqueia o deploy.
4. Fase B (release seguinte): `dropColumn anotacoes/codigo/indicado_por` de
   `pacientes` e drop do unique antigo de `codigo`.

Rollback: `down()` recria colunas e repovoa a partir do pivot (só possível enquanto
na Fase A). Guardar backup do dump antes (já há `database/backups/`).

---

## 8. Casos de borda e políticas
- **Editar dados compartilhados**: qualquer médico vinculado + admin/callcenter
  (decisão 2). "Último a salvar vence"; `updated_by_user_id` registra quem foi.
- **Arquivar por vínculo (decisão 5)**: `destroy()` (e o botão "arquivar" da tela do
  médico) grava `medico_paciente.ativo=false` só no vínculo daquele médico. O
  paciente segue visível para os outros. `pacientes.ativo` global vira ação exclusiva
  de admin ("remover do sistema para todos"). O scope `Paciente::ativo()` e os filtros
  de listagem/busca do **médico** passam a olhar o `ativo` **do vínculo**; admin/
  callcenter usam o `ativo` global.
- **`codigo` (Nº Registro) duplicado entre médicos**: passa a ser permitido (é
  privado). Só é único dentro do mesmo médico.
- **Lookup / LGPD (decisão 6)**: liberado. O sistema é único e os dados pertencem ao
  sistema, não ao médico; então o lookup por CPF pode trazer os dados principais de
  qualquer paciente pré-cadastrado, sem barreira legal. (Recomendação técnica apenas:
  exigir CPF completo — não busca parcial — para não virar um "scanner" de base.)
- **Receitas e atendimentos**: já apontam para `medico_id` próprio — **nada muda** na
  emissão/relatório; ficam naturalmente consistentes com o pivot. O único ajuste é
  que emitir receita **cria o vínculo** (via `garantirVinculo`) em vez de escrever no
  FK único do paciente.
- **Paciente sem nenhum vínculo**: passa a ser um estado válido (ex.: criado por
  admin/callcenter sem médico). Aparece para admin/callcenter; não aparece para
  nenhum médico até ganhar um vínculo (inclusive via receita).

---

## 9. Plano de testes
- **Unit/Feature (PHPUnit)**:
  - médico A cria paciente; médico B "cria" o mesmo CPF → 1 registro em `pacientes`,
    2 linhas no pivot; campos privados isolados.
  - acesso: A não vê `codigo/indicado_por/anotacoes` de B; ambos veem dados
    compartilhados; alteração de dado compartilhado por A aparece para B.
  - unique: `cpf` global barra 2º registro; `codigo` colide só dentro do mesmo médico.
  - secretária/admin/callcenter enxergam conforme regra do pivot.
  - **emitir receita/assistente para paciente sem vínculo → cria o vínculo** (pivot)
    e não escreve mais no FK; emitir para paciente já existente não duplica vínculo.
  - **editar paciente dentro da receita** grava os privados no médico **da receita**,
    não no do usuário logado.
  - **arquivar por vínculo**: médico A arquiva → some para A, continua para B; admin
    desativa global → some para todos.
  - migração: seeder com dados legado → assert 1:1 paciente↔pivot e valores movidos.
- **E2E manual** (§ worklog): fluxo "Novo Paciente" com CPF existente → pré-preenche
  → 2ª tela em branco → salva vínculo.

---

## 10. Faseamento sugerido (entregas incrementais)
1. **PR1 — Esquema**: pivot + data migration + `cpf` unique (Fase A, colunas antigas
   mantidas). Sem mudança de comportamento visível. Deploy seguro/reversível.
2. **PR2 — Acesso & leitura**: `canAccessPaciente`, `index/search/show` via pivot;
   Show/Index exibindo campos privados do médico logado. Read-path já correto.
3. **PR3 — Escrita (form + receita)**: `garantirVinculo` helper;
   `store/update/autosave/quickCreate` gravando no pivot; **substituir os
   `paciente->update(['medico_id'])` do `AssistenteReceitaController` e o default de
   `ReceitaController` por `garantirVinculo`**; `codigo` unique por médico; `destroy`
   arquiva por vínculo.
4. **PR4 — Contexto de médico no drawer**: `PatientDrawer` recebe `medicoContextoId`;
   Receitas/Form, Receitas/Index e CallCenter/Show passam o médico correto; campos
   privados carregam/salvam do vínculo desse médico.
5. **PR5 — Fluxo Novo Paciente**: endpoint `lookup`, pré-preenchimento por CPF e 2ª
   tela em branco do anexo.
6. **PR6 — Periféricos**: exports/`FieldCatalog`/`ProcessExportJob`, callcenter
   usando `atendimento.medico`, e comandos `ReestabelecerPacientesMedico` /
   `AuditMedicoLinks` mesclando pivot.
7. **PR7 — Limpeza (Fase B)**: `dropColumn` dos 3 campos e do unique antigo de
   `codigo`; opcional descontinuar `pacientes.medico_id`.

## 11. Riscos & mitigação
| Risco | Mitigação |
|---|---|
| Regressão de acesso (vazar paciente entre médicos) | Testes de feature por papel antes do PR2 ir a produção |
| Colisão de `cpf` ao aplicar unique | Checagem pré-deploy (hoje 0 colisões) + abortar migração se houver |
| Homônimos tratados como pessoas distintas | Relatório de duplicados para revisão manual; não auto-mesclar |
| Integrações Tiny/RD apontando p/ registro errado | Como vira 1 pessoa = 1 registro, melhora; validar `SyncClienteTinyJob` após PR1 |
| **Vínculo criado por receita ficar esquecido** (2º caminho) | Centralizar em `garantirVinculo` e cobrir com teste; `origem` no pivot audita como o vínculo nasceu |
| **Drawer salvar privado no médico errado** (contexto na receita/callcenter) | `medicoContextoId` obrigatório no drawer; teste de "editar paciente dentro da receita" |
| Esforço/tempo | Faseamento em 7 PRs pequenos e reversíveis |

---

## 12. Subida em produção (deploy + rotina de normalização)

### 12.1 Como o deploy funciona hoje (recap do repo)
- **`.github/workflows/deploy-hostinger.yml`**: push em `main` → build → rsync para a
  Hostinger → roda **`scripts/hostinger-post-deploy.sh`**, que faz
  `artisan down` → **`migrate --force`** → `optimize:clear` + `config/route/view:cache`
  → symlink de storage → `artisan up`. Ou seja: **migrations de schema rodam sozinhas
  no deploy, sob modo manutenção.**
- **Comandos de dados** rodam **à parte**, via um workflow `workflow_dispatch`
  dedicado sobre SSH, em **dry-run por padrão + toggle `force`** (padrão do
  `.github/workflows/reestabelecer-pacientes.yml` que dispara
  `medicos:reestabelecer-pacientes`). É exatamente o mecanismo pedido para eu
  disparar a normalização **após** o deploy.

### 12.2 Sim, há rotina de normalização — 3 comandos Laravel a criar
A migração de dados (mover 1153 vínculos para o pivot + checar unicidade de CPF) **não**
deve ir "escondida" dentro de uma migration que roda no deploy automático: é grande,
precisa de dry-run/inspeção e de ser **re-executável**. Seguindo a convenção do projeto,
crio comandos idempotentes, disparados por mim após o deploy:

| Comando | O que faz | Segurança |
|---|---|---|
| `pacientes:opcao2-preflight` | **Read-only.** Relata: nº de pacientes, quantos com/sem `medico_id`, **colisões de CPF** (bloqueiam o unique), grupos de nome duplicado entre médicos, e quantas linhas o backfill criaria. Sai com **código ≠ 0 se houver colisão de CPF** (trava o pipeline). | Só leitura |
| `pacientes:backfill-vinculos` | **A normalização.** Para cada paciente com `medico_id`, faz `upsert` no pivot `medico_paciente` (`anotacoes/codigo/indicado_por/ativo/origem='import'/created_by_user_id`). **Idempotente** (usa `updateOrInsert` por `(medico_id,paciente_id)`), pode rodar N vezes. | **dry-run por padrão**, aplica só com `--force`; roda em transação; imprime resumo (criados/atualizados/pulados) |
| `pacientes:opcao2-verify` | **Read-only.** Confere pós-backfill: todo paciente com `medico_id` tem a linha no pivot equivalente; contagens batem; nenhum vínculo órfão; nenhum CPF duplicado. Sai ≠ 0 se algo divergir. | Só leitura |

Assinaturas (padrão dos comandos existentes, ex.: `--force` como no
`ReestabelecerPacientesMedico`):
```
pacientes:opcao2-preflight
pacientes:backfill-vinculos   {--force : aplica de fato (sem = dry-run)}
                              {--chunk=500 : tamanho do lote}
pacientes:opcao2-verify
```

### 12.3 Ordem de subida (casada com o faseamento dos 7 PRs)
1. **Backup do banco** antes de tudo (a Hostinger + `database/backups/` do repo).
2. **Deploy do PR1** (cria a tabela `medico_paciente` **vazia**; **sem** unique de
   `cpf` ainda). `migrate --force` roda no post-deploy. **Nenhuma mudança visível.**
3. **Eu disparo** o novo workflow `workflow_dispatch` → `pacientes:opcao2-preflight`.
   - Se acusar colisão de CPF → parar e resolver os dados antes de prosseguir.
4. **Eu disparo** `pacientes:backfill-vinculos` **em dry-run**; confiro o resumo.
5. **Eu disparo** `pacientes:backfill-vinculos --force`; depois
   `pacientes:opcao2-verify` (tem de sair verde).
6. **Deploy do PR2** (acesso/leitura via pivot). Agora o pivot já está populado, então
   ler pelo pivot mostra os mesmos dados de antes — sem regressão.
7. **Deploys dos PR3→PR6** (escrita, drawer, lookup, periféricos), cada um com seus
   testes; o `cpf` unique global entra numa migration **defensiva** (aborta com lista
   de CPFs colidentes em vez de corromper) já que o preflight garante 0 colisões.
8. **PR7 (Fase B)**: só depois de tudo validado em produção, `dropColumn` dos 3 campos
   e do unique antigo de `codigo`. Antes disso, um último
   `pacientes:opcao2-verify --strict` confirma que nada lê mais as colunas antigas.

### 12.4 Workflow de disparo (a criar)
Espelhar `reestabelecer-pacientes.yml` num novo
`.github/workflows/opcao2-normalizar-pacientes.yml` com `workflow_dispatch` e inputs
`comando` (preflight/backfill/verify) e `force` (bool). Roda via SSH, mesmo bloco de
detecção de PHP 8.4 já usado nos outros workflows. Assim **eu disparo pelo GitHub
Actions após cada passo**, com dry-run por padrão.

### 12.5 Rollback
- **Fase A (PR1–PR6)** é reversível: como as colunas antigas de `pacientes` e o
  `medico_id` continuam existentes e sincronizados, dá para reverter o deploy do
  código sem perder dado (o pivot é aditivo). Um `pacientes:backfill-vinculos` pode
  ser re-rodado a qualquer momento.
- **`cpf` unique**: a migration tem `down()` que remove o índice.
- **Fase B** (drop de colunas) é o único passo destrutivo — só após período de
  observação e com backup imediatamente antes.

---

## 13. Limpeza de usuários/médicos duplicados (herança da importação legado)

O cliente reportou **médicos duplicados** e **"muitos cadastros de secretários"**.
Investiguei os dados reais (`revski-main`) e o diagnóstico é claro.

### 13.1 O que realmente está duplicado (dados medidos)
- **184 usuários**: 11 admin, 96 médico, 77 secretária. **41 e-mails `@legado.revskin.com.br`**.
- **14 médicos duplicados = par legado + "shell":**
  - conta **legado** (`xxx@legado.revskin.com.br`) que **carrega o `medico_id`** (e
    portanto os pacientes/receitas);
  - conta **"real" vazia** (`xxx@revskin.com.br`, nome "Dra. X", role médico, **sem
    `medico_id`**), criada na 2ª rodada de importação e nunca vinculada.
  - Mesma *local-part* de e-mail e mesmo nome (só muda o prefixo "Dr./Dra."). É por
    isso que o cliente vê o médico **duas vezes**.
  - **As 14 shells têm 0 dependências** (nenhum `created_by`, nenhum `user_medico`,
    nenhum paciente) — confirmado por query. São puramente lixo de importação.
- **27 contas legado SEM gêmeo** (12 médico + 11 secretária + 4 admin): são as contas
  **efetivamente em uso** (o profissional loga com o e-mail legado). **NÃO apagar.**
- **11 secretárias genéricas** `Secretaria Administrativa 1..11`
  (`secretadm1..11@revskin.com.br`, uma por clínica). **Verificado no dump legado mais
  recente** (`docs/clinicaweb/database/bkp_cw2_20260610.sql`, 10/jun): **as 11 existem
  na tabela `user` do legado** → **são legítimas, mantidas** (decisão do cliente:
  existe no legado ⇒ manter).
- Nenhuma pessoa é médico e secretária ao mesmo tempo; **0 CRMs duplicados**; **0
  médicos vazios**; **0 e-mails idênticos**. O comando existente `AuditMedicoLinks`
  funde duplicatas na tabela `medicos` — **não cobre este caso**, que é duplicação na
  tabela `users` (2 usuários, 1 só registro `medicos`).

> **Por que ainda entra duplicado:** houve duas criações — a 1ª importação gerou os
> usuários com `@legado.revskin.com.br` (+ `medico_id`); depois o script foi ajustado
> para e-mail sem legado e recriou "Dra. X" **sem reaproveitar** o usuário/médico já
> existente. A deduplicação nunca foi feita, então os dois lados coexistem.

### 13.2 Rotina de limpeza (novo comando, disparado por mim pós-deploy)
Fica **junto do pacote de normalização** (§12), como o cliente pediu. Comando
idempotente, **dry-run por padrão + `--force`**, no padrão dos comandos existentes:
```
usuarios:auditar-duplicados-legado   {--force}
```
Lógica (**todas as decisões do cliente já embutidas**):
1. **Pares médico legado+shell (14) — decisão: manter o e-mail limpo.** Consolidar em
   **uma** conta com o **e-mail `@revskin.com.br`** e o **`medico_id`/dados**.
   Implementação segura, sem cirurgia de FK: **apaga a shell** vazia (revalidando que
   tem **0 dependências** — se tiver qualquer referência, não apaga e reporta) e
   **renomeia o e-mail do legado** `xxx@legado.revskin.com.br` → `xxx@revskin.com.br`
   (que a shell liberou), mantendo o nome "Dra. X". Uma transação por par.
2. **Contas legado sem gêmeo (27) — decisão: limpar o sufixo `@legado`.** Renomear
   `xxx@legado.revskin.com.br` → `xxx@revskin.com.br` (12 médico + 11 secretária + 4
   admin). **Guardar contra colisão**: se o e-mail limpo já existir em outro usuário,
   pular e reportar (não deve ocorrer — os 27 não têm gêmeo, mas o comando checa).
   Não apaga nada; só troca o e-mail (login). Comunicar aos usuários que o login
   passa a ser sem "legado" (senha inalterada).
3. **Secretárias genéricas (11) — decisão: manter.** Confirmado no dump legado
   (§13.1) que existem → **nenhuma ação** (não desativa, não apaga). Só aparecem no
   relatório como "mantidas (existem no legado)".
4. Modo relatório (sem `--force`) imprime: os 14 pares a consolidar, os 27 e-mails a
   limpar (com checagem de colisão), e as 11 genéricas mantidas — com contagem de
   dependências de cada shell antes de qualquer remoção.

### 13.3 Prevenção (para não voltar a duplicar)
- No fluxo de criação de médico/usuário, **deduplicar por e-mail (local-part) e por
  CRM** antes de inserir: se já existe usuário/médico equivalente, **reutilizar**.
- Idealmente descontinuar de vez o padrão `@legado.revskin.com.br` após esta limpeza.

### 13.4 Encaixe no deploy
Roda **depois** do backfill de pacientes (§12.3), no mesmo workflow de disparo:
`preflight → backfill → verify → **auditar-duplicados-legado (dry-run)** → conferir →
**--force**`. Independente da Opção 2, mas agrupado por conveniência operacional.
Fazer backup antes (é o único passo que apaga linhas de `users`).

> Observação: o anexo enviado nesta rodada (tela do AffiliateWP/Payouts) não tem
> relação com este item — parece anexo trocado. O diagnóstico acima veio dos dados.

---

### Resumo executivo
Introduzir a tabela `medico_paciente` com os 3 campos privados, tornar `cpf` a chave
única global da pessoa, mover `codigo` para "único por médico", reescrever o acesso
para pertencimento ao pivot e transformar o "Novo Paciente" num **upsert + criação
de vínculo** com busca por CPF e a 2ª tela em branco do anexo. Ponto de atenção que a
varredura revelou: o vínculo médico↔paciente também nasce **fora** da tela de
pacientes (na emissão de receita e no assistente), e o paciente é editável **dentro
da receita** e no call center — tudo precisa passar pelo helper `garantirVinculo` e
pelo `medicoContextoId` no drawer. Entregar em 7 PRs incrementais e reversíveis,
começando por um PR de esquema sem efeito visível. A subida em produção (§12) usa o
pipeline Hostinger já existente para as migrations e **3 comandos Laravel idempotentes**
(`opcao2-preflight` → `backfill-vinculos` → `opcao2-verify`) que eu disparo por um
workflow `workflow_dispatch` após o deploy, em dry-run por padrão.
