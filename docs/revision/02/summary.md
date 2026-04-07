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
*(Pausado: aguardando retorno do cliente sobre onde deve haver autosave, onde salvar manual e o papel exato do botão "Finalizar". Não alterar o comportamento até essa definição.)*

- [ ] Padronizar comportamento:
  - [ ] Definir onde salva automático
  - [ ] Definir onde precisa salvar manual
  - [ ] Definir função do botão "Finalizar"
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
  - [ ] Evitar corte de texto *(parcial: autosize melhora; textos muito longos podem ainda precisar de wrap em revisão)*

---

### 🐞 Bugs adicionais

**O que são (glossário):**
- **Tooltip de compras:** o balão na coluna *Data de aquisição* da receita (e em `Receita #` visualização) ao passar o mouse — lista “Últimas aquisições”. O problema relatado é **datas repetidas** no tooltip e/ou **histórico que não reflete bem as compras** (ordem ou lista incompleta/errada).
- **Duplicidade de médicos (ex.: Dra. Angela):** o **mesmo profissional aparece duas vezes** em listas/dropdowns (paciente, clínica, filtros, etc.), em geral por **dois cadastros de médico**, **CRM diferente** tratado como pessoa distinta, ou **query** que não deduplica vínculo usuário/médico.

- [x] Corrigir tooltip / coluna **Data de aquisição** na receita *(abr. 2026)*:
  - [x] **Extração:** `extrairItemAquisicoesLegado` só gera linha em `itemAquisicoesLegado.json` quando `dta_ult_aquisicao` da linha do dump **coincide** com a data do evento CC — removido fallback para “primeira linha com o mesmo produto”; múltiplas linhas candidatas com a mesma data → ignorado (ambíguo). Reduz falsos positivos (ex.: data em produto não adquirido).
  - [x] **UI / API:** `loadAcquisitionDates` e Call Center usam apenas `receita_item_aquisicoes` **da mesma linha** + `receita_itens.data_aquisicao` — **sem** agregar por paciente+produto noutras receitas. `Form.jsx` / `ProductItemsEditor` deixam de puxar datas de receitas anteriores pelo mesmo produto.
  - [x] **Operação:** após mudança de regra, é necessário **re-extrair** (`migration:extrair-legado`) e **reimportar** (ou fluxo backup + `limpar-dados-reimport` + import). README de migração atualizado (`itemAquisicoesLegado.json`, observações).
  - [ ] Relatórios que leem `receita_item_aquisicoes` globalmente — validar se o critério continua adequado ao negócio *(não alterado neste ciclo)*.
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

- [ ] Simplificar numeração:
  - [ ] De: `1460-0003`
  - [ ] Para: `1, 2, 3...`
- [ ] Definir fluxo de envio ao call center:
  - [ ] Envio automático ao finalizar?
  - [ ] Opção de cancelar envio
  - [ ] Possibilidade de reenviar depois

---

### 🖨️ Impressão

- [ ] Definir claramente:
  - [ ] Onde clicar para imprimir receita
  - [ ] Em qual etapa isso acontece

---

### 👤 Permissões

- [ ] Validar permissões do perfil médico:
  - [ ] Pode cadastrar médicos?
  - [ ] Pode cadastrar produtos?
- [ ] Definir se essas ações:
  - [ ] São feitas dentro do sistema
  - [ ] Ou via sistema externo (ex: RD)

---

### 🧹 Padronização

- [ ] Remover "Dr." / "Dra." dos nomes
- [ ] Padronizar listas:
  - [ ] Evitar duplicidade
  - [ ] Garantir consistência entre telas

---

### 🎨 Interface

- [ ] Remover "Bem-vindo" do topo
- [ ] Manter apenas aviso verde na tela inicial

---

## 📚 Documentação (Manual)

*(Itens abaixo dependem da definição do cliente sobre salvamento / Finalizar — alinhar depois do retorno.)*

- [ ] Documentar:
  - [ ] Onde salva automático
  - [ ] Onde precisa salvar manualmente
  - [ ] Diferença entre "Salvar" e "Finalizar"
  - [ ] Comportamento do Assistente
  - [ ] Fluxo de envio ao Call Center

---

## 📌 Observações

- Sistema atual possui inconsistência entre telas (Assistente vs Receita)
- Fluxo precisa ser mais previsível (menos decisões do usuário)
- Reduzir cliques e etapas redundantes é essencial
- Migração CC→item mais **estrita** implica **menos** linhas em `itemAquisicoesLegado` / possíveis erros na importação para vínculos antigos “chutados”; monitorar relatórios e casos reais após reimport

---

## 🔭 Próximos passos sugeridos

1. **Relatório de aquisição de produtos** — Confirmar com negócio se o recorte por `receita_item_aquisicoes` (por linha) continua coerente com o que o relatório deve mostrar (vs. histórico agregado por paciente+produto).
2. **Salvamento / Finalizar** — Retomar quando houver retorno do cliente (itens em *Prioridade Alta → Salvamento* e *Documentação*).
3. **Média prioridade** — Numeração de receitas, fluxo Call Center, impressão, permissões médico, padronização de nomes/listas, UI “Bem-vindo”.
4. **Produtos legado sem Tiny** — Decidir política (SKU catch-all, só log, cadastro Tiny) para linhas ignoradas na migração.
5. **Excel relatório** — Fechar item “evitar corte de texto” (wrap) se ainda necessário após autosize.

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