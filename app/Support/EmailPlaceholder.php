<?php

namespace App\Support;

/**
 * E-mail de marcação ("ainda falta o e-mail de verdade deste paciente").
 *
 * Decisão do cliente (job b6a8f395, respondendo à análise do job e2c44ae4):
 *   - o domínio passa a ser `@cadastraremail.rsk` — não é um TLD real de propósito, então
 *     nenhuma campanha sai daqui por engano, e passa na validação de e-mail (ao contrário
 *     de `cadastrar_email.com`, com underline, que é inválido e travava 150 cadastros);
 *   - o placeholder é gerado SÓ na importação do oList, para as atendentes revisarem o que
 *     falta. Em cadastro novo daqui o campo fica vazio: o e-mail virou opcional e inventar
 *     um endereço só faria o médico achar que já tinha digitado.
 */
class EmailPlaceholder
{
    public const DOMINIO = 'cadastraremail.rsk';

    /**
     * Domínios que já existem na base/no oList e significam a mesma coisa. O do underline é
     * inválido como e-mail — por isso a normalização, não só o reconhecimento.
     *
     * @var list<string>
     */
    public const DOMINIOS = [
        'cadastraremail.rsk',
        'cadastraremail.com',
        'cadastrar_email.com',
    ];

    /** É um e-mail de marcação (qualquer um dos domínios), e não um endereço de verdade? */
    public static function ehPlaceholder(?string $email): bool
    {
        $dominio = self::dominioDe($email);

        return $dominio !== null && in_array($dominio, self::DOMINIOS, true);
    }

    /**
     * Monta o placeholder a partir do telefone: `<dígitos>@cadastraremail.rsk`.
     * Sem telefone não há o que gerar (115 pacientes da base estão nessa situação) — devolve
     * null, e o cadastro fica sem e-mail mesmo, que agora é um estado válido.
     */
    public static function gerar(?string ...$telefones): ?string
    {
        foreach ($telefones as $telefone) {
            $digitos = preg_replace('/\D/', '', (string) $telefone);
            if (strlen((string) $digitos) >= 10) {
                return $digitos.'@'.self::DOMINIO;
            }
        }

        return null;
    }

    /**
     * Deixa um e-mail pronto para gravar: placeholder de domínio antigo vira `@cadastraremail.rsk`
     * (preservando a parte antes do @, que é o telefone de quem cadastrou); e-mail de verdade
     * passa intacto; vazio vira null.
     */
    public static function normalizar(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '') {
            return null;
        }

        if (! self::ehPlaceholder($email)) {
            return $email;
        }

        $local = trim(mb_substr($email, 0, (int) mb_strrpos($email, '@')));

        return $local === '' ? null : mb_strtolower($local).'@'.self::DOMINIO;
    }

    private static function dominioDe(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));
        $pos = mb_strrpos($email, '@');
        if ($pos === false || $pos === 0) {
            return null;
        }

        $dominio = mb_substr($email, $pos + 1);

        return $dominio === '' ? null : $dominio;
    }
}
