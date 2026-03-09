<?php

return array_merge([
    'app_url' => '',
    'output_dir' => 'deploy-package',
    'app_dir_name' => 'revskin',
    'database' => null,
], file_exists(__DIR__.'/deploy.local.php') ? require __DIR__.'/deploy.local.php' : []);
