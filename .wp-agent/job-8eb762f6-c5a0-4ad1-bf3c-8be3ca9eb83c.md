# Worklog — Plano de arquitetura da Opção 2 (cadastro único de paciente)

## O que foi pedido
Após a análise anterior recomendar a Opção 2, arquitetar um **plano de
implementação** (artefato) para viabilizá-la. Decisões do cliente incorporadas:
- Dados principais **compartilhados e editáveis por ambos os médicos**; alteração de
  um é vista pelo outro.
- **Sem tratamento de concorrência** — raramente dois médicos no mesmo paciente ao
  mesmo tempo → "último a salvar vence" + auditoria já existente.

## O que foi feito
1. Aprofundei o mapeamento de impacto no código (além da análise anterior):
   - Form real é o componente `resources/js/Components/PatientDrawer.jsx` (create +
     edit + autosave), com os 3 campos privados já no layout (Observações / Nº
     Registro / Indicado por).
   - Rotas em `routes/web.php` (`pacientes.*`, `autosave`, `quickCreate`).
   - Pontos que assumem 1 médico por paciente: `ExportController` (filtro pacientes
     por `medico_id`/`indicado_por`), `FieldCatalog`, `CallCenterController`,
     `RelatorioController`, comando `ReestabelecerPacientesMedico`,
     `PacienteObserver` (sync Tiny).
2. Escrevi o plano completo, faseado em 6 PRs reversíveis, cobrindo: modelo de dados
   (pivot `medico_paciente`), o que é compartilhado × privado, reescrita do controle
   de acesso via pivot, fluxo "Novo Paciente" = upsert + vínculo + lookup por CPF +
   2ª tela em branco do anexo, mudanças de unicidade (`cpf` global, `codigo` por
   médico), migração dos 1153 registros, casos de borda/LGPD, plano de testes e
   riscos.

## Artefato entregue
- `.wp-agent/share/plano-arquitetura-opcao2-cadastro-unico-paciente.md` — o plano.
- (Contexto anterior: `.wp-agent/share/analise-cadastro-paciente-multi-medico.md`.)

## Arquivos
- **Criado:** `.wp-agent/share/plano-arquitetura-opcao2-cadastro-unico-paciente.md`.
- **Criado:** este worklog.
- **Nenhum código de produção alterado** (tarefa de arquitetura/planejamento).

## Como validar
Ler o plano em `.wp-agent/share/plano-arquitetura-opcao2-cadastro-unico-paciente.md`.
Pontos que pedem decisão do cliente antes do PR1: (a) `ativo` global vs por vínculo
ao "arquivar"; (b) base legal LGPD para o lookup por CPF expor dados principais de
paciente de outra clínica.
