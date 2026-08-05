/**
 * Lista de pacientes JÁ CADASTRADOS que casam com o nome digitado.
 *
 * Substitui a busca por CPF no cadastro de paciente: o oList tem muitos clientes sem CPF, que
 * nunca apareceriam numa busca por documento. Quem usa isto são médicos com pressa, então a
 * regra da tela é: painel discreto (não é erro nem alerta), uma linha por paciente com os dados
 * que diferenciam homônimos, e nenhuma instrução — o botão já diz o que faz.
 */

/** Só os dados que diferenciam homônimos, separados por ponto, sem rótulo nem caixinha. */
function linhaResumo(c) {
    const partes = [];

    if (c.data_nascimento_br) {
        partes.push(c.idade != null ? `${c.data_nascimento_br} (${c.idade})` : c.data_nascimento_br);
    }
    if (c.celular) partes.push(c.celular);
    if (c.email1) partes.push(c.email1);
    if (c.documento) partes.push(`${c.documento_label}: ${c.documento}`);
    if (c.cidade) partes.push([c.cidade, c.uf].filter(Boolean).join('/'));

    return partes.join(' · ');
}

export default function PacientesEncontrados({
    candidatos = [],
    total = 0,
    buscando = false,
    onSelecionar,
    onIgnorar,
    nomeDigitado = '',
    className = '',
    titulo = null,
    rotuloAcao = 'Usar',
    ocultarIds = [],
    ocultarVinculados = false,
}) {
    const lista = candidatos.filter(
        (c) => !ocultarIds.includes(c.id) && !(ocultarVinculados && c.ja_vinculado)
    );

    // Sem texto de "procurando": o spinner fica dentro do campo Nome, onde o olho já está.
    if (lista.length === 0) {
        return null;
    }

    // Conta contra o que está NA TELA (a lista já foi filtrada), senão o "mais N" mente
    // quando ocultarIds/ocultarVinculados removem linhas.
    const ocultos = Math.max(0, total - lista.length);

    return (
        <div className={`mt-2 overflow-hidden rounded-lg border border-gray-200 bg-gray-50 ${className}`} role="status">
            <p className="px-3 pt-2.5 text-xs font-medium text-gray-600">
                {titulo ?? (total === 1 ? '1 paciente já cadastrado com este nome' : `${total} pacientes já cadastrados com este nome`)}
            </p>

            <ul className="mt-1.5 divide-y divide-gray-200 border-t border-gray-200 bg-white">
                {lista.map((c) => (
                    <li key={c.id} className="flex items-center gap-3 px-3 py-2">
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm text-gray-900">
                                {c.nome}
                                {c.ja_vinculado && (
                                    <span className="ml-1.5 text-xs text-emerald-700">· seu paciente</span>
                                )}
                            </p>
                            <p className="truncate text-xs text-gray-500" title={linhaResumo(c)}>
                                {linhaResumo(c) || 'Sem outros dados cadastrados'}
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() => onSelecionar?.(c)}
                            className="shrink-0 rounded-md border border-emerald-600 px-2.5 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                        >
                            {/* Já é paciente do médico: usar o cadastro é abrir/atualizar, não vincular de novo. */}
                            {c.ja_vinculado ? 'Abrir' : rotuloAcao}
                        </button>
                    </li>
                ))}
            </ul>

            {(ocultos > 0 || onIgnorar) && (
                <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs">
                    {ocultos > 0 ? (
                        <span className="text-gray-500">Mais {ocultos} — digite o sobrenome para refinar</span>
                    ) : (
                        <span />
                    )}
                    {onIgnorar && (
                        <button
                            type="button"
                            onClick={onIgnorar}
                            className="text-gray-600 underline hover:text-gray-900 hover:no-underline"
                        >
                            Nenhum destes — cadastrar {nomeDigitado ? `"${nomeDigitado}"` : 'um paciente'} como novo
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
