# Import CLW2: dry-run sem inflar merges/updates

Regra aplicada:

1. **Receita já importada** + dump ≤ CLW3 + sem edição local → **skip** (não refresh).
2. **Paciente já existente** + **0 campos** a mudar → **skip**.

Dry-run piloto (3 médicos) passou de ~201 merges / 350 updates para **1 merge** e **0 updates**; a lista mostra só o que realmente entra (novos + 1 merge + 52 receitas novas). Skips aparecem só como nota “Omitidos da lista”.
