# 📋 CW3 - Lista Consolidada de Atividades

## 🔴 Prioridade Crítica

### 🐞 Bugs

- [x] Corrigir Assistente abrindo com médico errado (sempre pega o primeiro da lista)
- [x] Garantir que receita utilize o médico correto (evitar efeito cascata)
- [x] Corrigir troca de paciente na listagem de receitas
- [x] Corrigir inconsistência onde receita abre com nome de outro paciente
- [x] Corrigir salvamento:
  - [x] Produto adicionado na receita não está sendo salvo ao sair

---

## 🟠 Prioridade Alta

### 🧠 Fluxo e UX

#### Assistente
- [x] Remover etapa de pedir nome do paciente novamente
- [x] Abrir direto no Assistente com paciente selecionado
- [x] Impedir alteração do médico no Assistente (inclusive ADM)

#### Salvamento
*Definição fechada (abr. 2026): autosave apenas em **Receita** e **Paciente** (drawer); nos restantes ecrãs prevalece **Salvar** explícito e o **modal** “sair sem guardar” quando há alterações. Ver [manual-usuario-operacao.md](./manual-usuario-operacao.md).*

- [x] Padronizar comportamento:
  - [x] Definir onde salva automático *(Receita + Paciente)*
  - [x] Definir onde precisa salvar manual *(demais módulos / drawers com confirmação)*
  - [x] Definir função do botão "Finalizar"
- [x] Criar POP-UP obrigatório:
  - [x] "Deseja sair sem salvar?"

#### Navegação
- [x] Ao clicar em "Home":
  - [x] Validar alterações não salvas
  - [x] Exibir alerta antes de sair

---

### 📊 Relatórios

- [x] Mostrar todos os produtos adquiridos por paciente *(período do relatório passa a filtrar por **data de aquisição**, sem exigir que a **data da receita** caiba no mesmo intervalo — evita sumir compra registrada no período)*
- [x] Ordenar por nome (ordem alfabética pelo **primeiro nome**)
- [x] Ajustar exportação Excel:
  - [x] Auto ajustar largura das colunas
  - [x] Evitar corte de texto *(quebra de texto + alinhamento vertical no topo em `AquisicaoProdutosExport` e `ReceitasMedicoExport`)*

---

### 🐞 Bugs adicionais

**O que são (glossário):**
- **Tooltip de compras:** o balão na coluna *Data de aquisição* da receita (e em `Receita #` visualização) ao passar o mouse — lista “Últimas aquisições”. O problema relatado é **datas repetidas** no tooltip e/ou **histórico que não reflete bem as compras** (ordem ou lista incompleta/errada).
- **Duplicidade de médicos (ex.: Dra. Angela):** o **mesmo profissional aparece duas vezes** em listas/dropdowns (paciente, clínica, filtros, etc.), em geral por **dois cadastros de médico**, **CRM diferente** tratado como pessoa distinta, ou **query** que não deduplica vínculo usuário/médico.

- [x] Corrigir tooltip / coluna **Data de aquisição** na receita *(abr. 2026)*:
  - [x] **Extração:** `extrairItemAquisicoesLegado` só gera linha em `itemAquisicoesLegado.json` quando `dta_ult_aquisicao` da linha do dump **coincide** com a data do evento CC — removido fallback para “primeira linha com o mesmo produto”; múltiplas linhas candidatas com a mesma data → ignorado (ambíguo). Reduz falsos positivos (ex.: data em produto não adquirido).
  - [x] **UI / API:** `loadAcquisitionDates` e Call Center usam apenas `receita_item_aquisicoes` **da mesma linha** + `receita_itens.data_aquisicao` — **sem** agregar por paciente+produto noutras receitas. `Form.jsx` / `ProductItemsEditor` deixam de puxar datas de receitas anteriores pelo mesmo produto.
  - [x] **Operação:** após mudança de regra, é necessário **re-extrair** (`migration:extrair-legado`) e **reimportar** (ou fluxo backup + `limpar-dados-reimport` + import). README de migração atualizado (`itemAquisicoesLegado.json`, observações).
  - [x] Relatórios que leem `receita_item_aquisicoes` — considerado **alinhado** após correção do script de importação / extração; monitorar negócio se surgirem casos limite.
- [x] Remover duplicidade de médicos (ex: Dra Angela) *(extração: nome canónico do médico no JSON; importação: um `User` por `medico_id` para papel médico — **requer reextrair + reimportar** ou limpar users duplicados já criados)*
- [x] Corrigir médicos que aparecem em clínicas mas não na lista de médicos
- [x] Investigar persistência de senha: relato (06/04) de que senha criada junto com Darvin não ficou salva no sistema; foi necessário reset por e-mail *(reteste: fluxo de criação/gravação de senha OK; caso tratado como equívoco do usuário)*
- [x] **Edição de utilizadores (drawer Admin)** *(abr. 2026)*:
  - [x] Ao mudar **Perfil**, o formulário deixava de enviar nome/e-mail/etc.: `setData({ role })` no Inertia substitui o estado inteiro — corrigido com `setData((prev) => ({ ...prev, ... }))`.
  - [x] **Salvar** desabilitado para médico com número só em **Telefone** (legado preenche `telefone1`, `telefone2` vazio): validação front e back passam a aceitar **telefone ou celular** (mín. 10 dígitos com DDD). `MedicoService` + `UserController` (`store`/`update`) com regra `after`; asterisco removido só do campo Celular em `MedicoFormFields`.
  - [x] **UF CRM** vazia: pré-preenchimento a partir do **primeiro endereço** do médico (`uf`) ao abrir edição, quando `uf_crm` não veio do cadastro.

---

## 🟡 Prioridade Média

### 📄 Receitas

- [x] Simplificar **exibição** da numeração *(abr. 2026)*:
  - [x] Cabeçalho e listas usam **sequência legível** (ex.: `Receita #2`, `Editar Receita #3`) com **Nº registro** do paciente (`codigo`) em campo separado quando existir
  - [x] Listagem filtrada por paciente: coluna **Receita** com só o número da sequência; **Nº registro** no card do paciente
  - *Nota:* o valor armazenado `numero` (ex.: `paciente_id`-sequência) mantém-se na base para integridade; a simplificação é na **UI** e exportações podem ainda mostrar o código completo onde fizer sentido

- [x] Fluxo de envio integrações *(processo já definido no sistema: quando o médico **finaliza** a receita, o envio para **Tiny** e **RD** ocorre nesse momento; não é tema em aberto de produto — documentar no manual quando o bloco Salvamento/Finalizar estiver fechado com o cliente)*

---

### 🖨️ Impressão

- [x] Deixar explícito na UI *(receita em modo visualização **aberta**): texto orientando que o **PDF / Download PDF** fica disponível após **finalizar**, e onde usar o botão na visualização finalizada*

---

### 👤 Permissões

- [ ] **Repassado à equipa cliente** — validar política e eventual ajuste de roles *(sem alteração de código neste ciclo)*
  - Pode cadastrar médicos?
  - Pode cadastrar produtos?
  - Dentro do sistema vs. sistema externo (ex.: RD)

---

### 🧹 Padronização

- [x] Remover "Dr." / "Dra." da **exibição** *(helper `nomeExibicaoSemTitulo` — receitas, dashboard, relatórios, call center, pacientes, médicos, clínicas, drawer de paciente; cadastros continuam a gravar o nome completo como no legado)*

- [x] Padronizar listas *(o pedido original era reduzir **duplicados** nas listas/dropdowns — ex.: mesmo médico duas vezes — e melhorar **consistência** entre ecrãs — nomes, colunas, traço “sem dados”, comportamento de busca)*
  - [x] Duplicidade *(ciclos anteriores: médicos, extração CC; export Excel deixou de prefixar “Dra.” manualmente)*
  - [x] Consistência entre telas *(melhoria contínua; vários ajustes feitos em receitas/pacientes/listagens; novos pedidos tratam caso a caso)*

---

### 🎨 Interface

- [x] Remover "Bem-vindo" do topo *(mantido “Olá, {nome}” / saudação simples; aviso verde na home preservado)*

---

## 📚 Documentação (Manual)

- [x] Manual de operação: **[manual-usuario-operacao.md](./manual-usuario-operacao.md)** *(autosave vs manual, Salvar vs Finalizar, Assistente, integrações Tiny/RD, PDF, numeração na UI, nota sobre Excel)*

- [x] Documentar:
  - [x] Onde salva automático
  - [x] Onde precisa salvar manualmente
  - [x] Diferença entre "Salvar" e "Finalizar"
  - [x] Comportamento do Assistente
  - [x] Integrações ao finalizar (Tiny / RD) e relação com Call Center

---

## 📌 Observações

- **Migração — itens de receita sem produto na base:** comando Artisan `migration:relatorio-itens-sem-produto-na-base` + documentação no README; gera CSV para apoio a cadastro no Tiny / atualização do mapeamento (não altera a regra de “ignorar item sem produto” na importação).
- Sistema atual possui inconsistência entre telas (Assistente vs Receita)
- Fluxo precisa ser mais previsível (menos decisões do usuário)
- Reduzir cliques e etapas redundantes é essencial
- Migração CC→item mais **estrita** implica **menos** linhas em `itemAquisicoesLegado` / possíveis erros na importação para vínculos antigos “chutados”; monitorar relatórios e casos reais após reimport

---

## 🔭 Próximos passos sugeridos

1. **Permissões (médico / cadastros)** — Fechar com a equipa cliente e, se necessário, abrir tarefas técnicas.
2. **Produtos legado sem produto na base** — Comando `php artisan migration:relatorio-itens-sem-produto-na-base` gera CSV para cadastro Tiny / mapeamento; política operacional continua decisão de negócio.
3. **Relatórios** — Monitorar feedback após wrap Excel; reabrir se algum relatório específico ainda cortar texto.
4. **Consistência UI** — Novos pedidos de alinhamento entre ecrãs, caso a caso.

---

## ✅ Validação manual (após deploy / `npm run build`)

Use esta checklist para marcar os itens `[x]` acima à medida que validar.

1. **Relatório itens sem produto:** na máquina com `receitas.json` atualizado e base com produtos, executar `php artisan migration:relatorio-itens-sem-produto-na-base`. Confirmar que gera CSV em `storage/app/private/migration-backups/` e que colunas (IDs legado, códigos, `codigo_tentado_na_base`) fazem sentido abrindo no Excel/LibreOffice.
2. **Home / layout:** abrir dashboard — não deve aparecer “Bem-vindo ao painel”; deve manter saudação tipo “Olá, …” e o aviso verde existente.
3. **Dr./Dra. só na exibição:** abrir lista de médicos, ficha paciente (médico responsável e tabela de receitas), detalhe Call Center, relatórios *Aquisição de produtos* (cabeçalho do paciente e filtros de médico), *Receitas por médico*, edição de clínica (chips/lista de médicos), drawer de paciente (médico responsável) — nomes sem prefixo “Dr.”/“Dra.” onde antes apareciam na UI; formulário de cadastro de médico pode continuar a mostrar o nome completo ao editar.
4. **PDF / impressão:** abrir uma receita **aberta** em visualização (`/receitas/{id}`) e em edição com `viewMode` — deve haver texto claro de que o PDF só após **finalizar**; numa receita **finalizada**, o botão **Download PDF** deve continuar visível e funcional.

---

## 🚀 Sugestão de organização

### Sprint 1 (Crítico)
- Bugs de médico errado
- Bugs de receita / paciente
- Salvamento

### Sprint 2 (UX)
- Assistente
- Pop-ups
- Navegação

### Sprint 3 (Melhoria)
- Relatórios
- Interface
- Padronização

### Sprint 4 (Extra)
- Documentação
- Permissões
- Impressão