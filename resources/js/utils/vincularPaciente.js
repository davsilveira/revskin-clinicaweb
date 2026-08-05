/**
 * Vincula ao médico um paciente que já existe no sistema (escolhido na busca por nome).
 *
 * É o passo que torna utilizável a carga de clientes vinda do oList: esses pacientes entram
 * sem vínculo com médico nenhum, então não aparecem na busca "meus pacientes" até alguém
 * usá-los.
 *
 * Devolve sempre `{ paciente, erro }`: o 422 do backend explica o que fazer ("selecione o
 * médico responsável", "não pertence à sua clínica") e virar um "tente novamente" genérico
 * deixaria o usuário preso repetindo o mesmo clique.
 *
 * @returns {Promise<{paciente: object|null, erro: string|null}>}
 */
export async function vincularPacienteAoMedico(pacienteId, { medicoId = null } = {}) {
    const csrf =
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    try {
        const resp = await fetch(`/api/pacientes/${pacienteId}/vincular`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ _token: csrf, medico_id: medicoId || null }),
        });

        let json = null;
        try {
            json = await resp.json();
        } catch (_) {
            json = null;
        }

        if (resp.ok && json?.paciente) {
            return { paciente: json.paciente, erro: null };
        }

        if (resp.status === 419) {
            return { paciente: null, erro: 'Sua sessão expirou. Recarregue a página (F5) e tente de novo.' };
        }

        return {
            paciente: null,
            erro: json?.errors?.medico_id?.[0]
                || json?.message
                || 'Não foi possível usar este cadastro. Tente novamente.',
        };
    } catch (_) {
        return { paciente: null, erro: 'Sem conexão com o servidor. Verifique a internet e tente de novo.' };
    }
}

export default vincularPacienteAoMedico;
