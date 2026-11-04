<?php

function isValidApexEmail(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $domain = strtolower((string) substr(strrchr($email, '@'), 1));
    return $domain === 'apex.edu.ph';
}

function apexEmailPolicyMessage(): string
{
    return 'Please use an APEX Email ending in @apex.edu.ph.';
}
