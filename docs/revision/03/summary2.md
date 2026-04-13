# CW3 - Summary 2 (Novos Itens)

Status da revisão (abril 2026). Itens marcados `[x]` já tratados no código ou são **instruções ao utilizador** (não bug). Ver notas no fim.

## Prioridade Crítica

### Bugs

- [x] Assistente com médico errado na receita — corrigido após o reporte; manter smoke test em alterações futuras.
- [x] CPF: mensagem de erro não sumia ao corrigir — corrigido (limpeza de `fieldErrors.cpf` ao digitar).

---

## Prioridade Alta

### Cadastro de Paciente

- [x] Auto-preenchimento indevido no email **(instrução)** — costuma ser autofill do browser; usar `autoComplete` adequado e, se necessário, desativar sugestões noutro perfil do browser.
- [x] Lista de países: ordenação alfabética com Brasil como default.
- [x] Telefone internacional — com país ≠ Brasil, o campo principal e os adicionais aceitam texto livre (sem máscara BR).
- [ ] Autocomplete de paciente pelo nome na receita (combobox) — listagem de pacientes já tem busca ao digitar; avaliar melhoria no formulário de receita.
- [x] UX: nome do paciente ao clicar na linha sem receita — abre o drawer de edição em vez de só aviso.

### Assistente / Fluxo

- [x] Tela intermediária — comportamento atual: passo 1 só quando o paciente não tem médico associado; caso contrário vai direto à avaliação clínica.
- [x] Sim/Não (Gravidez, Rosácea) — substituído por controlo tipo **toggle** Sim/Não.

### Formatação / Localização

- [x] Datas em formato controlado (calendário PT-BR) — componente com `react-day-picker` + `date-fns` nos fluxos principais (paciente, receita, assistente).

### Login

- [x] "Lembrar de mim" e ir ao dashboard — `/` redireciona autenticados para o dashboard; login usa `remember`.

---

## Prioridade Média

### Pacientes / Busca

- [x] Código interno na listagem e busca por código / CPF (incl. dígitos só).

### Produtos / Receita

- [x] Produtos de catálogo legado (integração Tiny): somente leitura destacado; médico pode remover/substituir onde aplicável.
- [x] **(instrução)** Integração CW3 ↔ RD/Tiny — alterações de produto no CW3 não espelham automaticamente no ERP; depende de webhooks/configuração (documentar no manual).

### Campos de Produto

- [x] Label "Anotações" da linha do item → **Fórmula** (conteúdo continua a ser `anotacoes` do item).
- [x] Fórmula e campos longos: textarea com crescimento automático (sem scroll preso na célula).
- [x] Coluna **Unidade** (ex.: g, ml) junto à quantidade.

### Permissões / UX Médico

- [x] Médico: edição via `/edit` redireciona para visualização; `PUT` bloqueado; ícone na listagem passa a **Visualizar** (receitas abertas).
- [x] Ocultar "Anotações internas" (nível receita) para médicos; backend ignora alterações a `anotacoes` por médico.

### Interface

- [x] Frase "A revolução…" — não encontrada no código atual (verificado).

---

## Notas

- **Tiny vs "Olist"** nos docs do cliente: o ERP de catálogo é **Tiny**; texto antigo "Olist" trata-se de nomenclatura/desatualização.
- **Datas**: input nativo `type="date"` varia com o browser; a app passa a usar datepicker com locale pt-BR nos ecrãs acima.
- **Merge com Summary 1**: manter bugs críticos no topo; este ficheiro reflete sprint UX + cadastro.
