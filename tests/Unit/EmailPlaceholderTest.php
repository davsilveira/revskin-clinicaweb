<?php

namespace Tests\Unit;

use App\Support\EmailPlaceholder;
use PHPUnit\Framework\TestCase;

class EmailPlaceholderTest extends TestCase
{
    public function test_reconhece_os_tres_dominios_de_marcacao(): void
    {
        $this->assertTrue(EmailPlaceholder::ehPlaceholder('21999998888@cadastraremail.rsk'));
        $this->assertTrue(EmailPlaceholder::ehPlaceholder('21999998888@cadastraremail.com'));
        $this->assertTrue(EmailPlaceholder::ehPlaceholder('21999998888@CADASTRAR_EMAIL.COM'));

        $this->assertFalse(EmailPlaceholder::ehPlaceholder('maria@gmail.com'));
        $this->assertFalse(EmailPlaceholder::ehPlaceholder(null));
        $this->assertFalse(EmailPlaceholder::ehPlaceholder(''));
    }

    public function test_normaliza_dominio_antigo_preservando_o_telefone(): void
    {
        $this->assertSame(
            '21999998888@cadastraremail.rsk',
            EmailPlaceholder::normalizar('21999998888@cadastrar_email.com')
        );
        $this->assertSame(
            '21999998888@cadastraremail.rsk',
            EmailPlaceholder::normalizar('  21999998888@cadastraremail.com  ')
        );
    }

    public function test_nao_mexe_em_email_de_verdade_e_devolve_null_para_vazio(): void
    {
        $this->assertSame('maria@gmail.com', EmailPlaceholder::normalizar('maria@gmail.com'));
        $this->assertNull(EmailPlaceholder::normalizar('   '));
        $this->assertNull(EmailPlaceholder::normalizar(null));
    }

    public function test_gera_a_partir_do_primeiro_telefone_utilizavel(): void
    {
        $this->assertSame('21999998888@cadastraremail.rsk', EmailPlaceholder::gerar('(21) 99999-8888'));
        // Celular vazio cai para o telefone fixo.
        $this->assertSame('2133334444@cadastraremail.rsk', EmailPlaceholder::gerar('', '(21) 3333-4444'));
        // Sem telefone utilizável não há placeholder — o cadastro fica sem e-mail mesmo.
        $this->assertNull(EmailPlaceholder::gerar(null, '1234'));
    }

    public function test_o_dominio_escolhido_passa_na_validacao_de_email(): void
    {
        $this->assertNotFalse(
            filter_var('21999998888@'.EmailPlaceholder::DOMINIO, FILTER_VALIDATE_EMAIL)
        );
    }
}
