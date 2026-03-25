<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;

class TinyContatoMapper
{
    /**
     * Raw data_atualizacao string from Tiny V2 contato (snake or camel).
     */
    public static function contatoDataAtualizacaoRaw(array $contato): ?string
    {
        $v = $contato['data_atualizacao'] ?? $contato['dataAtualizacao'] ?? null;

        return $v !== null && $v !== '' ? (string) $v : null;
    }

    /**
     * Parse Tiny datetime (d/m/Y H:i:s or d/m/Y).
     */
    public static function parseDataAtualizacao(?string $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trim = trim($value);
        $tz = config('app.timezone');
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', $trim, $tz);
        } catch (\Throwable) {
            try {
                return Carbon::createFromFormat('d/m/Y', $trim, $tz)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }
    }

    public static function tinUpdatedAtFromContato(array $contato): CarbonInterface
    {
        $raw = self::contatoDataAtualizacaoRaw($contato);
        $parsed = self::parseDataAtualizacao($raw);

        return $parsed ?? Carbon::now();
    }

    /** CPF/CNPJ digits only; empty string if none. */
    public static function onlyDigitsCpfCnpj(array $contato): string
    {
        $raw = $contato['cpf_cnpj'] ?? $contato['cpfCnpj'] ?? '';

        return (string) preg_replace('/\D/', '', (string) $raw);
    }

    public static function formatCpfBr11(string $digits): string
    {
        if (strlen($digits) !== 11) {
            return $digits;
        }

        return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
    }

    public static function mapSexo(?string $tinySexo): ?string
    {
        if ($tinySexo === null || $tinySexo === '') {
            return null;
        }
        $s = mb_strtolower(trim($tinySexo));
        if ($s === 'masculino') {
            return 'Masculino';
        }
        if ($s === 'feminino') {
            return 'Feminino';
        }

        return null;
    }

    public static function parseDataNascimento(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $trim = trim($value);
        try {
            return Carbon::createFromFormat('d/m/Y', $trim, config('app.timezone'))->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Map Tiny contato to pacientes attributes (partial). Omits null/empty optional fields.
     * Does not set tiny_id / tiny_sync_at / tiny_updated_at.
     */
    public static function toPacienteAttributes(array $contato, bool $includeCpf = true): array
    {
        $attrs = [];

        if (! empty($contato['nome'])) {
            $attrs['nome'] = mb_substr((string) $contato['nome'], 0, 255);
        }

        if ($includeCpf) {
            $digits = self::onlyDigitsCpfCnpj($contato);
            if (strlen($digits) === 11) {
                $attrs['cpf'] = self::formatCpfBr11($digits);
            }
        }

        $email = $contato['email'] ?? null;
        if ($email !== null && $email !== '') {
            $attrs['email1'] = mb_substr((string) $email, 0, 255);
        }

        $fone = $contato['fone'] ?? $contato['telefone'] ?? null;
        if ($fone !== null && $fone !== '') {
            $attrs['telefone1'] = mb_substr((string) $fone, 0, 20);
        }

        $cel = $contato['celular'] ?? null;
        if ($cel !== null && $cel !== '') {
            $attrs['celular'] = mb_substr((string) $cel, 0, 20);
        }

        if (! empty($contato['endereco'])) {
            $attrs['endereco'] = mb_substr((string) $contato['endereco'], 0, 255);
        }
        if (isset($contato['numero']) && $contato['numero'] !== '' && $contato['numero'] !== null) {
            $attrs['numero'] = mb_substr((string) $contato['numero'], 0, 20);
        }
        if (! empty($contato['complemento'])) {
            $attrs['complemento'] = mb_substr((string) $contato['complemento'], 0, 255);
        }
        if (! empty($contato['bairro'])) {
            $attrs['bairro'] = mb_substr((string) $contato['bairro'], 0, 255);
        }
        if (! empty($contato['cidade'])) {
            $attrs['cidade'] = mb_substr((string) $contato['cidade'], 0, 255);
        }
        if (! empty($contato['uf'])) {
            $attrs['uf'] = mb_substr(trim((string) $contato['uf']), 0, 2);
        }
        if (! empty($contato['cep'])) {
            $attrs['cep'] = mb_substr((string) $contato['cep'], 0, 10);
        }
        if (! empty($contato['pais'])) {
            $attrs['pais'] = mb_substr((string) $contato['pais'], 0, 100);
        }

        $rg = $contato['rg'] ?? null;
        if ($rg !== null && $rg !== '') {
            $attrs['rg'] = mb_substr((string) $rg, 0, 20);
        }

        $sexo = self::mapSexo($contato['sexo'] ?? null);
        if ($sexo !== null) {
            $attrs['sexo'] = $sexo;
        }

        $dn = self::parseDataNascimento($contato['data_nascimento'] ?? $contato['dataNascimento'] ?? null);
        if ($dn !== null) {
            $attrs['data_nascimento'] = $dn;
        }

        return $attrs;
    }

    public static function contatoTemTipoCliente(array $contato): bool
    {
        $tipos = $contato['tipos_contato'] ?? $contato['tiposContato'] ?? [];
        if (! is_array($tipos)) {
            return false;
        }
        foreach ($tipos as $t) {
            $tipo = is_array($t) ? ($t['tipo'] ?? '') : $t;
            if (strcasecmp(trim((string) $tipo), 'Cliente') === 0) {
                return true;
            }
        }

        return false;
    }
}
