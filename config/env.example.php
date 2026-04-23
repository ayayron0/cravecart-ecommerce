<?php

declare(strict_types=1);

// Environment-specific application configuration.
// ------------------------------------------------
// TODO:
// 1. Copy this file and rename the copy to env.php (in the same folder)
// 2. Fill in your values below
// 3. Never commit env.php — it is already in .gitignore

return [
    // Database
    'host'     => 'localhost',
    'database' => 'cravecart',
    'username' => 'root',
    'password' => '',

    // SMTP (used for 2FA email codes)
    // Ask a teammate for the Gmail App Password
    'smtp_host'     => 'smtp.gmail.com',
    'smtp_username' => 'your-gmail@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_port'     => 587,
    'smtp_from'     => 'your-gmail@gmail.com',
    'smtp_name'     => 'CraveCart',
];
