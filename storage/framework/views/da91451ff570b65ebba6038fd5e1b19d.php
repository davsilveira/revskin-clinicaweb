<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['url', 'color' => 'primary']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['url', 'color' => 'primary']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
$colors = [
    'primary' => '#059669',
    'secondary' => '#4b5563',
    'danger' => '#dc2626',
];
$bgColor = $colors[$color] ?? $colors['primary'];
?>

<div class="button-container" style="text-align: center; margin: 32px 0;">
    <a href="<?php echo e($url); ?>" 
       class="button" 
       style="display: inline-block; padding: 14px 28px; background-color: <?php echo e($bgColor); ?>; color: #ffffff !important; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; text-align: center;">
        <?php echo e($slot); ?>

    </a>
</div>
<?php /**PATH /Users/darvin/Apps/revskin/resources/views/emails/components/button.blade.php ENDPATH**/ ?>