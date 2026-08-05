import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import debounce from 'lodash/debounce';

/**
 * Busca, por NOME, pacientes que já existem no sistema (todos os médicos + os clientes
 * trazidos do oList). É o que evita cadastro duplicado agora que a busca por CPF não serve:
 * boa parte dos clientes do oList não tem CPF.
 *
 * @param {object}  opts
 * @param {string}  opts.termo        texto digitado (normalmente o campo Nome)
 * @param {boolean} opts.habilitado   desliga a busca (ex.: já escolheu um cadastro)
 * @param {number|string|null} opts.medicoId  para marcar quem já é paciente deste médico
 * @param {number}  opts.minChars     mínimo de caracteres para buscar
 */
export default function usePacientesCandidatos({ termo, habilitado = true, medicoId = null, minChars = 3 }) {
    const [candidatos, setCandidatos] = useState([]);
    const [total, setTotal] = useState(0);
    const [buscando, setBuscando] = useState(false);
    /**
     * Sequência da requisição: sem ela, a resposta lenta de um nome antigo sobrescreve a lista
     * do nome atual — e os botões "Usar este cadastro" apontariam para o paciente errado.
     * Também serve de cancelamento: `limpar` incrementa e a resposta em voo é descartada.
     */
    const seqRef = useRef(0);

    const buscar = useMemo(
        () =>
            debounce(async (nome, medico, seq) => {
                try {
                    const params = new URLSearchParams({ nome });
                    if (medico) params.set('medico_id', String(medico));
                    const resp = await fetch(`/api/pacientes/candidatos?${params.toString()}`, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (seq !== seqRef.current) return; // resposta obsoleta ou cancelada
                    if (!resp.ok) {
                        setCandidatos([]);
                        setTotal(0);
                        return;
                    }
                    const json = await resp.json();
                    if (seq !== seqRef.current) return;
                    setCandidatos(json.candidatos || []);
                    setTotal(json.total || 0);
                } catch (_) {
                    // conveniência: falha de rede aqui não pode travar o cadastro
                } finally {
                    if (seq === seqRef.current) {
                        setBuscando(false);
                    }
                }
            // 250 ms: o endpoint responde em ~25 ms, então o que o usuário sentia como
            // "demora" era quase todo o debounce. O spinner dentro do campo cobre o resto.
            }, 250),
        []
    );

    const limpar = useCallback(() => {
        seqRef.current += 1;
        buscar.cancel();
        setCandidatos((prev) => (prev.length ? [] : prev));
        setTotal((prev) => (prev ? 0 : prev));
        setBuscando(false);
    }, [buscar]);

    useEffect(() => () => {
        seqRef.current += 1;
        buscar.cancel();
    }, [buscar]);

    const nome = String(termo || '').trim();

    useEffect(() => {
        if (!habilitado || nome.length < minChars) {
            limpar();
            return;
        }
        seqRef.current += 1;
        setBuscando(true);
        buscar(nome, medicoId, seqRef.current);
    }, [habilitado, nome, medicoId, minChars, buscar, limpar]);

    return { candidatos, total, buscando, limpar };
}
