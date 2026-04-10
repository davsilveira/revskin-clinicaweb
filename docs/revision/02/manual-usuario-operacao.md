# Manual de operação (CW3 / ClinicaWeb)

Documento de apoio ao utilizador final e à equipa. Alinhado com o comportamento atual do sistema (rev. 02).

---

## Salvamento automático e manual

### Onde existe **autosave** (gravação automática)

- **Receita** (criar/editar): o formulário da receita grava alterações automaticamente após um intervalo, quando os dados obrigatórios estão válidos (e conforme regras de bloqueio, ex.: receita finalizada).
- **Paciente** (drawer de edição/criação): quando o autosave está ativo no contexto (ex.: listagem de pacientes, receitas com edição de paciente), as alterações podem ser persistidas em segundo plano; o rodapé pode mostrar indicação do último guardado.

### Onde **não** há autosave — uso de **Salvar** e modal “sair sem guardar”

Na maior parte dos ecrãs (produtos, utilizadores, clínicas, configurações em geral, drawers com `useDrawerUnsavedChanges`, etc.):

- As alterações só são gravadas quando o utilizador confirma **Salvar** (ou ação equivalente).
- Ao fechar o drawer ou navegar com alterações por gravar, o sistema pode mostrar um **modal** a perguntar se deseja **guardar**, **sair sem guardar** ou **cancelar**.

### **Salvar** vs **Finalizar** (receita)

- **Salvar**: mantém a receita em edição (estado **aberta**, salvo exceções de bloqueio); persiste produtos, valores e notas conforme validação.
- **Finalizar**: conclui o ciclo da receita no sistema (estado **finalizada**); após finalizar, regras de edição e PDF aplicam-se conforme perfil. **Neste momento** disparam-se os envios acordados para integrações (**Tiny ERP** e **RD Station**), quando configurados.

---

## Assistente de receita

- Pode abrir **já com o paciente selecionado** quando o acesso inclui `paciente_id` (ex.: a partir da ficha ou fluxos da aplicação).
- O **médico** associado ao assistente **não deve ser alterável** no assistente (incluindo administradores), para evitar inconsistências com a receita gerada.

---

## Integrações ao finalizar e Call Center

- **Tiny** e **RD**: o disparo está ligado à **finalização** da receita (não ao simples “Salvar” intermédio).
- **Call Center**: o vínculo com a receita e o estado do atendimento seguem as regras já implementadas na app; receitas muito avançadas no fluxo de call center podem ficar **bloqueadas à edição** até ao estado permitido.

---

## PDF da receita

- Enquanto a receita está **aberta**, o download em PDF pode não estar disponível; a interface indica que é necessário **finalizar** primeiro.
- Com receita **finalizada**, o botão **Download PDF** fica disponível na visualização (e fluxos associados).

---

## Numeração e identificação da receita

- O identificador técnico completo (ex.: `paciente_id` + sequência) continua a existir na base de dados.
- Na interface, a **sequência** por paciente é mostrada de forma legível (ex.: **Receita #2**, **Receita #1** no cabeçalho; na listagem filtrada por paciente pode mostrar-se só o número da sequência na coluna “Receita”), e o **Nº registro** do paciente (`codigo`) quando existir, em campos separados onde aplicável.

---

## Relatórios Excel

- As exportações dos relatórios (**Aquisição de produtos**, **Receitas por médico**) aplicam **ajuste de largura** e **quebra de texto nas células** para reduzir conteúdo cortado em colunas com textos longos (nomes de produtos, pacientes, etc.).

---

*Última atualização: documento rev. 02 (sincronizado com `summary.md`).*
