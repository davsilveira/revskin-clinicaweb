<?php

namespace App\Services;

use App\Models\Medico;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Validator;

class MedicoService
{
    /**
     * Regras de validação para criar/atualizar médico.
     */
    public static function validationRules(): array
    {
        return [
            'apelido' => 'nullable|string|max:255',
            'crm' => 'required|string|max:20',
            'uf_crm' => 'required|string|size:2',
            'cpf' => 'nullable|string|max:14',
            'rg' => 'nullable|string|max:20',
            'especialidade' => 'nullable|string|max:255',
            'clinica_ids' => 'nullable|array',
            'clinica_ids.*' => 'exists:clinicas,id',
            'email' => 'required|email|max:255',
            'telefone' => 'nullable|string|max:20',
            'celular' => 'nullable|string|max:20',
            'rodape_receita' => 'nullable|string',
            'anotacoes' => 'nullable|string',
            'ativo' => 'boolean',
            'assinatura' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'remover_assinatura' => 'nullable|boolean',
            'enderecos' => 'nullable|array',
            'enderecos.*.nome' => 'required|string|max:100',
            'enderecos.*.cep' => 'nullable|string|max:10',
            'enderecos.*.endereco' => 'nullable|string|max:255',
            'enderecos.*.numero' => 'nullable|string|max:20',
            'enderecos.*.complemento' => 'nullable|string|max:255',
            'enderecos.*.bairro' => 'nullable|string|max:255',
            'enderecos.*.cidade' => 'nullable|string|max:255',
            'enderecos.*.uf' => 'nullable|string|max:2',
        ];
    }

    /**
     * Exige pelo menos um contacto com DDD (mínimo 10 dígitos) em telefone ou celular.
     */
    public static function validateTelefoneOuCelular(Validator $validator): void
    {
        $data = $validator->getData();
        $tel = preg_replace('/\D+/', '', (string) ($data['telefone'] ?? ''));
        $cel = preg_replace('/\D+/', '', (string) ($data['celular'] ?? ''));
        if (strlen($tel) < 10 && strlen($cel) < 10) {
            $msg = 'Informe telefone ou celular com DDD (mínimo 10 dígitos).';
            $validator->errors()->add('telefone', $msg);
            $validator->errors()->add('celular', $msg);
        }
    }

    /**
     * Cria um médico a partir dos dados validados.
     *
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, ?Request $request = null): Medico
    {
        $medicoData = $this->mapToMedicoData($validated);
        $enderecos = $this->extractEnderecos($validated);
        $clinicaIds = $validated['clinica_ids'] ?? [];

        $medico = Medico::create($medicoData);

        if (! empty($clinicaIds)) {
            $medico->clinicas()->sync($clinicaIds);
        }

        if ($request?->hasFile('assinatura')) {
            $path = $request->file('assinatura')->store('assinaturas', 'public');
            $medico->update(['assinatura_path' => $path]);
        }

        foreach ($enderecos as $index => $endereco) {
            $medico->enderecos()->create([
                ...$endereco,
                'principal' => $index === 0,
            ]);
        }

        return $medico;
    }

    /**
     * Atualiza um médico com os dados validados.
     *
     * @param  array<string, mixed>  $validated
     */
    public function update(Medico $medico, array $validated, ?Request $request = null): Medico
    {
        $medicoData = $this->mapToMedicoData($validated);
        $enderecos = $this->extractEnderecos($validated);
        $clinicaIds = $validated['clinica_ids'] ?? [];

        $medico->update($medicoData);
        $medico->clinicas()->sync($clinicaIds);

        if ($request?->boolean('remover_assinatura')) {
            if ($medico->assinatura_path) {
                Storage::disk('public')->delete($medico->assinatura_path);
            }
            $medico->update(['assinatura_path' => null]);
        } elseif ($request?->hasFile('assinatura')) {
            if ($medico->assinatura_path) {
                Storage::disk('public')->delete($medico->assinatura_path);
            }
            $path = $request->file('assinatura')->store('assinaturas', 'public');
            $medico->update(['assinatura_path' => $path]);
        }

        $medico->enderecos()->delete();
        foreach ($enderecos as $index => $endereco) {
            $medico->enderecos()->create([
                ...$endereco,
                'principal' => $index === 0,
            ]);
        }

        return $medico;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function mapToMedicoData(array $validated): array
    {
        return [
            'apelido' => $validated['apelido'] ?? null,
            'crm' => $validated['crm'] ?? null,
            'uf_crm' => $validated['uf_crm'] ?? null,
            'cpf' => $validated['cpf'] ?? null,
            'rg' => $validated['rg'] ?? null,
            'especialidade' => $validated['especialidade'] ?? null,
            'email1' => $validated['email'] ?? null,
            'telefone1' => $validated['telefone'] ?? null,
            'telefone2' => $validated['celular'] ?? null,
            'rodape_receita' => $validated['rodape_receita'] ?? null,
            'anotacoes' => $validated['anotacoes'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>
     */
    private function extractEnderecos(array $validated): array
    {
        $enderecos = $validated['enderecos'] ?? [];

        return array_values(array_filter($enderecos, function ($e) {
            $nome = trim($e['nome'] ?? '');
            return $nome !== '';
        }));
    }
}
