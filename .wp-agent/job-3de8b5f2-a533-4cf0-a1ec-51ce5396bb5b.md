# Job 3de8b5f2 — Admin vê notas privadas por médico no paciente

## Goal
Campos exclusivos por médico (Indicado por, Nº Registro, Observações) ficavam em branco para admin. Admin passa a ver, em somente leitura, os valores agrupados por médico.

## What changed
- Backend anexa `privados_por_medico` na listagem/detalhe do paciente para admin/callcenter.
- No drawer de edição, admin **não** vê mais os inputs vazios; vê blocos por médico com label → valor.
- Update/autosave de não-médico **não sobrescreve** mais esses campos no pivot (evita apagar notas ao salvar o formulário em branco).
- Página `Pacientes/Show` também mostra a seção “Notas por médico” para admin.

## Files
- `app/Models/Paciente.php` — `privadosPorMedico()` / `attachPrivadosPorMedico()`
- `app/Http/Controllers/PacienteController.php`
- `app/Http/Controllers/ReceitaController.php`
- `app/Http/Controllers/CallCenterController.php`
- `resources/js/Components/PatientDrawer.jsx`
- `resources/js/Pages/Pacientes/Show.jsx`
- `tests/Feature/Opcao2PacienteMultiMedicoTest.php`

## Git
- Commit: `cffe130` — `feat(pacientes): admin vê notas privadas agrupadas por médico`
- **Sem deploy** (pedido explícito)

## Validate (local)
1. URL: https://revski-main.ddev.site:33177/login  
2. Login admin (ex.: `darvin@envolvelabs.com.br`)
3. Pacientes → buscar **Paciente Demo Admin Notas** (seed local id `17555`) ou qualquer paciente com vínculos
4. Abrir o drawer de edição
5. Em vez dos campos vazios, deve aparecer **Notas por médico** com blocos:
   - Nome do médico
   - Indicado por / Nº Registro / Observações
6. Médico logado continua vendo/editando só os campos do próprio vínculo

## Tests
```bash
cd "$(ddev describe revski-main -j | jq -r .raw.approot)"
ddev exec php artisan test --filter=Opcao2PacienteMultiMedicoTest
```
(7 passed)
