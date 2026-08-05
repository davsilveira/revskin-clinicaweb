/**
 * Documento de identificação do paciente: CPF quando houver, senão o documento livre
 * (passaporte/ID de estrangeiro). Ambos são opcionais desde 07/2026 — daí o fallback.
 */
export function documentoPaciente(paciente) {
    return paciente?.cpf || paciente?.outro_documento || null;
}

/** Rótulo correspondente — nunca chamar passaporte de "CPF". */
export function documentoPacienteLabel(paciente) {
    return paciente?.cpf ? 'CPF' : 'Documento';
}
