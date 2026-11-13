<?php

namespace App\Support;

final class InstagramUrl
{
    public static function fromHandle(?string $handle): ?string
    {
        if ($handle === null || trim($handle) === '') {
            return null;
        }

        $user = ltrim(trim($handle), '@');
        if ($user === '') {
            return null;
        }

        return 'https://www.instagram.com/'.rawurlencode($user).'/';
    }
}
