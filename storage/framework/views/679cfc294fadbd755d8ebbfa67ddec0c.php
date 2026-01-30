<?php $__env->startComponent('mail::message'); ?>
# Sua exportação está pronta!

Olá <?php echo new \Illuminate\Support\EncodedHtmlString($exportRequest->user->name); ?>,

Sua exportação foi concluída com sucesso e está disponível para download.

**Detalhes da exportação:**
- **Tipo:** <?php echo new \Illuminate\Support\EncodedHtmlString(ucfirst($exportRequest->type)); ?>

- **Registros:** <?php echo new \Illuminate\Support\EncodedHtmlString($exportRequest->total_records ?? 'N/A'); ?>

- **Data:** <?php echo new \Illuminate\Support\EncodedHtmlString($exportRequest->completed_at?->format('d/m/Y H:i')); ?>


<?php $__env->startComponent('mail::button', ['url' => $downloadUrl]); ?>
Baixar Arquivo
<?php echo $__env->renderComponent(); ?>

**Atenção:** O arquivo estará disponível por tempo limitado.

Obrigado,<br>
<?php echo new \Illuminate\Support\EncodedHtmlString(config('app.name')); ?>

<?php echo $__env->renderComponent(); ?>

<?php /**PATH /Users/darvin/Apps/revskin/resources/views/emails/exports/ready.blade.php ENDPATH**/ ?>