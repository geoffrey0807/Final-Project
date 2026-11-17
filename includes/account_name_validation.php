<?php

function isValidAccountName(string $name): bool
{
    return preg_match('/\A[\p{L}\p{M} ]+\z/uD', $name) === 1;
}
