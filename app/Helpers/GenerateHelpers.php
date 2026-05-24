<?php

namespace App\Helpers;

use App\Models\MstApp;

class GenerateHelpers
{
    public function generateApiKey(): string
    {
        return bin2hex(random_bytes(32)); // Generate a 64-character hexadecimal API key
    }

    public function generateApiLicense(): string
    {
        return bin2hex(random_bytes(32)); // Generate a 64-character hexadecimal API license
    }

    public static function generateAppCode(): string
    {
        $lastApp = MstApp::latest('id')->first();
        $nextId = $lastApp ? $lastApp->id + 1 : 1;
        return 'app-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    public static function getNextAppCode(): string
    {
        return self::generateAppCode();
    }
}
