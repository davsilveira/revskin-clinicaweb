<?php

namespace App\Support;

/**
 * Interpreta o texto longo da coluna {@code descricao} da tabela legada {@code produto}
 * (ClinicaWeb) no padrão: 1ª linha = nome; linhas seguintes = fórmula até "Modo de uso"; resto = modo de uso.
 */
final class LegadoProdutoDescricaoParser
{
    /**
     * @return array{nome: string, formula: string, modo_uso: string}
     */
    public static function parse(string $texto): array
    {
        $linhas = preg_split('/\r\n|\r|\n/', $texto);
        $linhas = array_map(trim(...), $linhas);
        $linhas = array_values(array_filter($linhas, fn ($l) => $l !== ''));

        if ($linhas === []) {
            return ['nome' => '', 'formula' => '', 'modo_uso' => ''];
        }

        $nome = $linhas[0];
        $formula = '';
        $modoUso = '';
        $inicioModoUso = null;

        for ($i = 1, $n = count($linhas); $i < $n; $i++) {
            $linha = $linhas[$i];
            $linhaLower = mb_strtolower($linha);

            if ($inicioModoUso === null && self::isInicioModoUso($linha, $linhaLower)) {
                $inicioModoUso = $i;
            }

            if ($inicioModoUso !== null) {
                $modoUso .= ($modoUso !== '' ? "\n" : '').$linha;
            } else {
                $formula .= ($formula !== '' ? "\n" : '').$linha;
            }
        }

        return [
            'nome' => $nome,
            'formula' => trim($formula),
            'modo_uso' => trim($modoUso),
        ];
    }

    private static function isInicioModoUso(string $linha, string $linhaLower): bool
    {
        if ($linha === '') {
            return false;
        }

        if (preg_match('/\buso\s*:/u', $linhaLower)) {
            return true;
        }

        if (str_contains($linhaLower, 'uso como') || str_contains($linhaLower, 'uso para')) {
            return true;
        }

        if (str_contains($linhaLower, 'modo de uso')) {
            return true;
        }

        if (str_starts_with($linhaLower, 'inicie ')) {
            return true;
        }

        if (str_starts_with($linhaLower, 'deixe agir')) {
            return true;
        }

        if (str_starts_with($linhaLower, 'nas primeiras')) {
            return true;
        }

        if (preg_match('/^\d+\.\s*(uso|aplique|aplicar|espalhe)/iu', $linha)) {
            return true;
        }

        if (preg_match('/^(aplique|aplicar|usar|utilize|utilizar|espalhe)\b/u', $linhaLower)) {
            return true;
        }

        return false;
    }
}
