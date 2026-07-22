# Job cb2d07b5 — Médico responsável: primeiro vs último vínculo?

## Pergunta
No paciente CPF `045.969.099-03`, admin vê **Médico responsável = Adriana** (primeiro), enquanto há 3 vínculos (Adriana, Darvin Teste, Simios). Deveria atualizar para o **último** que cadastrou (Simios)?

## Resposta (sem mudar código)
**Não atualizar automaticamente pelo último vínculo.** Manter `pacientes.medico_id` como origem/primeiro (ou responsável **explícito**).

Motivo curto:
- “Último que cadastrou” ≠ “mudou de médico” (pode ser co-atendimento / 2º CRM / clínica compartilhada).
- Com Opção 2 o dado operacional já está nos **vínculos** (notas por médico); a FK é rótulo de origem.
- Se o paciente mudou de médico de fato, o admin deve poder **trocar o responsável** de forma deliberada (melhor UX do que heurística silenciosa).

Alternativa se quiserem refletir “atual”: campo editável no admin (“Médico responsável”) + opcionalmente sugerir o vínculo mais recente, sem auto-write no store do 2º médico.

## Estado atual do código
`PacienteController@store` (upsert por CPF) e `update` **preservam** `medico_id` legado depois do primeiro vínculo — comportamento intencional da Opção 2.
