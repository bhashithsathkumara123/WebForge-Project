
<?php
// Load the local database configuration.

$localConfig = __DIR__ . '/db.local.php';

if (!file_exists($localConfig)) {
    die(
        'Local database configuration not found. ' .
        'Please create db.local.php using the project instructions.'
    );
}

require_once $localConfig;
