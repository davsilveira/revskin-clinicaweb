# Job f173fdff — por que a Fanilde está desativada e com `z-` se no ClinicaWeb está ativa

Follow-up do job f8b5e9c5 (paciente que a busca da Dra Sullege jurava não existir). A pergunta agora
é de causa: se no ClinicaWeb a ficha aparece ativa e com nome limpo, de onde saiu o `z-` e o
"desativado" aqui?

## O que as fotos mostravam

Duas telas do ClinicaWeb (legado): o cadastro e as receitas da ficha **#594** —
`Fanilde Pirro Viana Paquer`, CPF 850.146.051-68, Dra Sullege Suzuki, receita nº 3 de 11/06/2026
(BIOMASSANCE, AQUELINE II, SYNCHRON, TONALITE 2.3 30G, REJECTOR, CREME HIDROXITIRO, e-Apostila,
HYALU GEL, DERMANE). O endereço da foto (`Rua Dom Antônio Malam` + `Apto 202`) é a grafia exclusiva
da #594 — serviu para identificar a linha sem depender da URL.

## Cadeia de causa (com evidência, não dedução)

1. **Dump CLW2 06/08**: 4 fichas dela — #573 `z-…` (inativa), #577 `zzzFANILDE…` (inativa),
   #579 `zzzFanilde Paquer` (inativa), **#594 nome limpo (ativa)**. Hábito da clínica: renomear com
   `z`/`zzz` e desmarcar ativo em vez de apagar a repetida.
2. **Import inicial 16/06/2026**: merge por CPF mantém a **mais antiga** → sobreviveu a #573.
   A ficha CLW3 #16742 nasceu com nome `z-…` e `ativo=0`; as receitas da #594 foram penduradas nela.
3. **Import incremental 07/08/2026**: `ativo` não está em `PACIENTE_SHARED_FIELDS` (arquivar é
   decisão do CLW3, não do dump) e `nome` só é sobrescrito quando `dta_ult_alteracao` do legado é
   maior que nosso `updated_at`. A #594 não tem `dta_ult_alteracao` (só inclusão 26/06/2025) →
   nome limpo descartado como "velho".
   Prova no report local da mesma rodada (`storage/app/imports/fe14743.../report-apply-20260807-181951.json`):
   única entrada para `clw3_id 16742` é `p-577`, diff = sexo+fototipo, `before.nome == after.nome == "z-Fanilde…"`,
   `ativo` "não" antes e depois. **`p-594` não gerou entrada nenhuma** (um dos `pacientes_skip: 355`).
   A receita `r-2259` ("nº 3", 2026-06-11) entrou como `action: nova`, `warning: null`.
4. **oList 07/08 19:10**: `tiny_sync_at == updated_at == 2026-08-07 19:10:51` — foi o pull do oList
   que trocou o endereço para a versão boa (no report das 18:19 ainda era `R. Dom Antonio Malan`).
   O pull faz `unset($attrs['nome'])` para ficha existente e só seta `ativo=true` ao **criar**.
   Ou seja: endereço/e-mail/sexo foram sendo atualizados; os dois campos que escondem a paciente
   (`nome` com `z-` e `ativo`) são exatamente os que nada reescreve.
5. **21/07 backfill de vínculos**: copiou `pacientes.ativo=0` para o vínculo com a Dra Sullege —
   segunda tranca.

## Correção de prevenção implementada

O furo real: a importação passou muda. Agora não passa mais.

- `LegadoIncrementalImporter::avisoFichaInvisivel()` checa os dois
  filtros da busca (`pacientes.ativo`, `medico_paciente.ativo`) e o `upsertReceita` carimba
  `warning: paciente_arquivado|vinculo_arquivado` na linha de conferência da receita + sinal
  `receita_em_ficha_invisivel` no JSON.
- `upsertPaciente`: quando a linha do dump está ativa e a ficha aqui está arquivada, emite
  `paciente_ativo_no_legado_arquivado_no_clw3` — e esse sinal sobrevive ao caminho de skip
  (antes só `cpf_divergente` sobrevivia).
- `ImportacaoClw2.jsx`: rótulos dos dois avisos novos ("o médico não vai encontrá-la na busca").
- 4 testes novos em `LegadoIncrementalImportTest` (ficha arquivada, vínculo arquivado, caso normal
  sem aviso, e o skip que agora sinaliza). Suite: 24 passed. Pint limpo.

## Entregue

- `.wp-agent/share/por-que-fanilde-esta-desativada-e-com-z.md` — explicação para a clínica, com a
  tabela das 4 fichas e o trecho do report como prova.

## Pendente (inalterado do job anterior)

Reativação das 8 fichas (`reativar-pacientes-arquivados`, dry-run já validado) segue aguardando o
"pode aplicar" da clínica. 2 urgentes: Fanilde (Dra Sullege) e uma da Dra Renata.
