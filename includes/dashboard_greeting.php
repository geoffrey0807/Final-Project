<?php
function dashboardGreeting(DateTimeInterface $date): string
{
    $hour = (int) $date->format('G');

    return $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
}
