<?php

function studentProgramYearLimit(string $programCode): int
{
    $yearLimits = [
        'BSSE' => 4,
        'BSMAD' => 4,
        'BSCS' => 4,
        'GPMC' => 4,
        'BSIT' => 4,
        'ADFSWD' => 2,
    ];

    return $yearLimits[$programCode] ?? 0;
}

function isValidStudentYearLevel(string $programCode, int $yearLevel): bool
{
    $yearLimit = studentProgramYearLimit($programCode);

    return $yearLimit > 0 && $yearLevel >= 1 && $yearLevel <= $yearLimit;
}

function studentFinalGradePasses(?float $finalGrade): bool
{
    return $finalGrade !== null && $finalGrade >= 1.0 && $finalGrade <= 3.0;
}

function isValidStudentProgression(
    string $status,
    int $targetYearLevel,
    ?int $previousYearLevel,
    ?string $previousAcademicYear,
    ?string $previousSemester,
    ?int $currentYearLevel,
    string $targetAcademicYear,
    string $targetSemester
): bool {
    $currentYearLevel ??= $previousYearLevel;
    $previousAcademicYearStart = $previousAcademicYear === null
        ? null
        : (int) substr($previousAcademicYear, 0, 4);
    $targetAcademicYearStart = (int) substr($targetAcademicYear, 0, 4);
    $previousPeriodIsEarlier = $previousAcademicYearStart !== null
        && ($previousAcademicYearStart < $targetAcademicYearStart
            || ($previousAcademicYearStart === $targetAcademicYearStart
                && $previousSemester === 'first'
                && $targetSemester === 'second'));

    return match ($status) {
        'new', 'initial' => $previousYearLevel === null && $targetYearLevel === 1,
        'continued' => $previousYearLevel !== null
            && $currentYearLevel !== null
            && $targetYearLevel === $currentYearLevel,
        'promoted' => $previousYearLevel !== null
            && $previousPeriodIsEarlier
            && $targetYearLevel === $previousYearLevel + 1,
        'retake' => $targetYearLevel === ($currentYearLevel ?? $previousYearLevel ?? 1),
        default => false,
    };
}
