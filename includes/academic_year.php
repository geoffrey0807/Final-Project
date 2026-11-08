<?php

const ACADEMIC_YEAR_FIRST_START = 2022;
const ACADEMIC_YEAR_END_START = 2028;

function academicYearStartOptions(): array
{
    return range(ACADEMIC_YEAR_FIRST_START, ACADEMIC_YEAR_END_START - 1);
}

function currentAcademicPeriodValue(): ?string
{
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $year = (int) $now->format('Y');
    $month = (int) $now->format('n');
    $startYear = $month >= 7 ? $year : $year - 1;

    if ($startYear < ACADEMIC_YEAR_FIRST_START || $startYear >= ACADEMIC_YEAR_END_START) {
        return null;
    }

    $semester = $month >= 7 && $month <= 11 ? 'first' : 'second';
    return sprintf('%04d-%04d:%s', $startYear, $startYear + 1, $semester);
}

function academicPeriodFromValue(?string $value): ?array
{
    if (
        !is_string($value)
        || !preg_match('/\A(20\d{2})-(20\d{2}):(first|second)\z/', $value, $matches)
    ) {
        return null;
    }

    $startYear = (int) $matches[1];
    if ((int) $matches[2] !== $startYear + 1
        || $startYear < ACADEMIC_YEAR_FIRST_START
        || $startYear >= ACADEMIC_YEAR_END_START) {
        return null;
    }

    $semester = $matches[3];
    $startDate = $semester === 'first'
        ? sprintf('%04d-07-01', $startYear)
        : sprintf('%04d-12-01', $startYear);
    $endDate = $semester === 'first'
        ? sprintf('%04d-12-01', $startYear)
        : sprintf('%04d-07-01', $startYear + 1);
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

function academicPeriodIsWritable(?array $period, ?string $status): bool
{
    if ($period === null || $status === null) {
        return false;
    }
    if ($status === 'open') {
        return true;
    }
    if ($period['read_only']) {
        return false;
    }

    $periodValue = $period['academic_year'] . ':' . $period['semester'];
    if ($periodValue === currentAcademicPeriodValue()) {
        return true;
    }

    return false;
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

function academicPeriodRecordFilter(string $alias, string $dateColumn, string $suffix = ''): string
{
    global $academicPeriod;
    if ($academicPeriod === null) {
        return '';
    }
    if (
        !preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/D', $alias)
        || !preg_match('/\A[A-Za-z][A-Za-z0-9_]*\.[A-Za-z][A-Za-z0-9_]*\z/D', $dateColumn)
    ) {
        throw new InvalidArgumentException('Invalid academic-period SQL identifier.');
    }

    $parameterPrefix = $suffix === '' ? 'academic' : 'academic_' . $suffix;
    return " AND (($alias.academic_year = :{$parameterPrefix}_year
        AND $alias.semester = :{$parameterPrefix}_semester)
        OR ($alias.academic_year IS NULL
            AND $dateColumn >= :{$parameterPrefix}_start
            AND $dateColumn < :{$parameterPrefix}_end))";
}

function academicPeriodRecordParams(string $suffix = ''): array
{
    global $academicPeriod;
    if ($academicPeriod === null) {
        return [];
    }

    $parameterPrefix = $suffix === '' ? 'academic' : 'academic_' . $suffix;
    return [
        $parameterPrefix . '_year' => $academicPeriod['academic_year'],
        $parameterPrefix . '_semester' => $academicPeriod['semester'],
        $parameterPrefix . '_start' => $academicPeriod['start_date'],
        $parameterPrefix . '_end' => $academicPeriod['end_date'],
    ];
}

function nextAcademicPeriod(array $period): array
{
    if ($period['semester'] === 'first') {
        $value = $period['academic_year'] . ':second';
    } else {
        $nextStartYear = (int) substr($period['academic_year'], 0, 4) + 1;
        $value = $nextStartYear . '-' . ($nextStartYear + 1) . ':first';
    }

    return academicPeriodFromValue($value);
}
