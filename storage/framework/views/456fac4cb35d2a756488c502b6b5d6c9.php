<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia><?php echo e(config('app.name', 'Laravel Boilerplate')); ?></title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />

        <!-- Scripts -->
        <?php if(app()->environment('production') && file_exists(public_path('build/manifest.json'))): ?>
            
            <?php
                $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
                $cssEntry = $manifest['resources/css/app.css'] ?? null;
                $jsEntry = $manifest['resources/js/app.jsx'] ?? null;
            ?>
            <?php if($cssEntry && isset($cssEntry['file'])): ?>
                <link rel="stylesheet" href="<?php echo e(asset('build/' . $cssEntry['file'])); ?>">
            <?php endif; ?>
            <?php if($jsEntry && isset($jsEntry['file'])): ?>
                <script type="module" src="<?php echo e(asset('build/' . $jsEntry['file'])); ?>"></script>
            <?php endif; ?>
        <?php else: ?>
            
            <?php echo app('Illuminate\Foundation\Vite')->reactRefresh(); ?>
            <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.jsx']); ?>
        <?php endif; ?>
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->head; } ?>
    </head>
    <body class="font-sans antialiased">
        <?php if (!isset($__inertiaSsrDispatched)) { $__inertiaSsrDispatched = true; $__inertiaSsrResponse = app(\Inertia\Ssr\Gateway::class)->dispatch($page); }  if ($__inertiaSsrResponse) { echo $__inertiaSsrResponse->body; } else { ?><div id="app" data-page="<?php echo e(json_encode($page)); ?>"></div><?php } ?>
    </body>
</html>

<?php /**PATH /Users/darvin/Apps/revskin/resources/views/app.blade.php ENDPATH**/ ?>