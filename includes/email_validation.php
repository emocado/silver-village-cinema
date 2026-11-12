<?php

function isValidCinemaEmail(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = strtolower(substr(strrchr($email, '@'), 1));
    $labelCount = substr_count($domain, '.') + 1;

    return !(str_ends_with($domain, '.com') && $labelCount !== 2)
        && !(str_ends_with($domain, '.sg') && $labelCount > 4)
        && !str_starts_with($domain, 'gmail.com.')
        && !str_ends_with($domain, '.sg.sg');
}
