<?php $__env->startSection('title', 'Bem-vindo ao ' . config('app.name')); ?>

<?php $__env->startSection('header', 'Bem-vindo!'); ?>

<?php $__env->startSection('content'); ?>
    <p>Olá <strong><?php echo e($user->name); ?></strong>,</p>
    
    <p>Sua conta foi criada com sucesso no <?php echo e(config('app.name')); ?>!</p>

    <p>Para acessar o sistema, você precisa definir sua senha clicando no botão abaixo:</p>

    <?php echo $__env->make('emails.components.button', ['url' => $resetUrl, 'slot' => 'Definir Minha Senha'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="section">
        <h3 class="section-title">Informações da sua conta</h3>
        <p style="margin: 0;">
            <strong>Nome:</strong> <?php echo e($user->name); ?><br>
            <strong>E-mail:</strong> <?php echo e($user->email); ?><br>
            <strong>Perfil:</strong> Médico
        </p>
    </div>

    <p class="text-muted">Se você não solicitou a criação desta conta, por favor ignore este email ou entre em contato com o administrador do sistema.</p>

    <p class="text-small text-muted">
        Caso o botão não funcione, copie e cole o link abaixo no seu navegador:<br>
        <a href="<?php echo e($resetUrl); ?>"><?php echo e($resetUrl); ?></a>
    </p>

    <p>
        Atenciosamente,<br>
        <strong>Equipe <?php echo e(config('app.name')); ?></strong>
    </p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('emails.layouts.base', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/darvin/Apps/revskin/resources/views/emails/welcome.blade.php ENDPATH**/ ?>