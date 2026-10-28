<?php

function isValidPassword(string $password): bool
{
    return strlen($password) >= 8
        && strlen($password) <= 72
        && preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1
        && preg_match('/[^A-Za-z0-9\s]/', $password) === 1;
}

function passwordPolicyMessage(): string
{
    return 'Password must be 8-72 characters and include an uppercase letter, a lowercase letter, a number, and a special character.';
}
