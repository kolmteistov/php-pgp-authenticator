<?php

/**
 * config/pgp2fa.php
 *
 * Publish/copy this file to your own config directory and adjust as needed.
 * These values decouple the package from any app-specific "Settings" model —
 * everything here is a plain Laravel config value with an .env override.
 */

return [

    // Name shown inside the encrypted verification/challenge message.
    'site_name' => env('PGP_2FA_SITE_NAME', env('APP_NAME', 'My Application')),

    // Length (in hex characters) of the generated one-time PIN. Must be even.
    'pin_length' => 8,

    // How long a login challenge stays valid, in minutes.
    'login_challenge_ttl' => 10,

    // How long the "verify a newly added key" challenge stays valid, in minutes.
    'key_verification_ttl' => 15,

];
