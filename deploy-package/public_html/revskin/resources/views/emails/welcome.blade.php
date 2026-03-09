@extends('emails.layouts.base')

@section('title', 'Bem-vindo ao ' . config('app.name'))

@section('header', 'Bem-vindo!')

@section('content')
    <p>Olá <strong>{{ $user->name }}</strong>,</p>
    
    <p>Sua conta foi criada com sucesso no {{ config('app.name') }}!</p>

    <p>Para acessar o sistema, você precisa definir sua senha clicando no botão abaixo:</p>

    @include('emails.components.button', ['url' => $resetUrl, 'slot' => 'Definir Minha Senha'])

    <div class="section">
        <h3 class="section-title">Informações da sua conta</h3>
        <p style="margin: 0;">
            <strong>Nome:</strong> {{ $user->name }}<br>
            <strong>E-mail:</strong> {{ $user->email }}<br>
            <strong>Perfil:</strong> Médico
        </p>
    </div>

    <p class="text-muted">Se você não solicitou a criação desta conta, por favor ignore este email ou entre em contato com o administrador do sistema.</p>

    <p class="text-small text-muted">
        Caso o botão não funcione, copie e cole o link abaixo no seu navegador:<br>
        <a href="{{ $resetUrl }}">{{ $resetUrl }}</a>
    </p>

    <p>
        Atenciosamente,<br>
        <strong>Equipe {{ config('app.name') }}</strong>
    </p>
@endsection
