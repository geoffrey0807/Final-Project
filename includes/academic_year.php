<?php

function academicPeriodFromValue(?string $value): ?array
{
    if (!is_string($value)
        || !preg_match('/\A(20\d{2})-(20\d{2}):(first|second)\z/', $value, $matches)) {
        return null;
    }

    $startYear = (int) $matches[1];
    if ((int) $matches[2] !== $startYear + 1) {
        return null;
    }

    $semester = $matches[3];
    $startDate = $semester === 'first'
        ? sprintf('%04d-07-01', $startYear)
        : sprintf('%04d-12-01', $startYear);
    $endDate = $semester === 'first'
        ? sprintf('%04d-12-01', $startYear)
        : sprintf('%04d-05-01', $startYear + 1);
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));
    $periodEnd = new DateTimeImmutable($endDate, new DateTimeZone('Asia/Manila'));

    return [
        'academic_year' => $matches[1] . '-' . $matches[2],
        'semester' => $semester,
        'semester_label' => $semester === 'first' ? 'First Semester' : 'Second Semester',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'read_only' => $periodEnd <= $today,
    ];
}

function academicPeriodDateFilter(string $column, string $suffix = ''): string
{
    global $academicPeriod;
    if ($academicPeriod === null) {
        return '';
    }

    $parameterPrefix = $suffix === '' ? 'academic' : 'academic_' . $suffix;
    return " AND $column >= :{$parameterPrefix}_start AND $column < :{$parameterPrefix}_end";
}

function academicPeriodDateParams(string $suffix = ''): array
{
    global $academicPeriod;
    if ($academicPeriod === null) {
        return [];
    }

    $parameterPrefix = $suffix === '' ? 'academic' : 'academic_' . $suffix;
    return [
        $parameterPrefix . '_start' => $academicPeriod['start_date'],
        $parameterPrefix . '_end' => $academicPeriod['end_date'],
    ];
}
