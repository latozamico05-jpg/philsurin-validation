<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Canonical grading stage and workflow status values.
 *
 * These mirror the ENUM definitions in database/schema.sql. Keeping them in one
 * place stops the stage codes drifting apart between controllers, services and
 * the C4.5 feature extractor.
 */
final class Stages
{
    public const GRADING_01 = '01_GRADING';
    public const GRADING_02 = '02_GRADING';
    public const GRADING_03 = '03_GRADING';
    public const GRADING_04 = '04_GRADING';
    public const HARVEST = 'HARVEST';

    /** Stages stored in validation_sessions. */
    public const VALIDATION_STAGES = [
        self::GRADING_01,
        self::GRADING_02,
        self::GRADING_03,
        self::GRADING_04,
    ];

    /** Harvest lives in harvest_records but participates in the same workflow. */
    public const ALL_STAGES = [
        self::GRADING_01,
        self::GRADING_02,
        self::GRADING_03,
        self::GRADING_04,
        self::HARVEST,
    ];

    /** Statuses shared by validation_sessions and harvest_records. */
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_REJECTED = 'REJECTED';

    public const WORKFLOW_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SUBMITTED,
        self::STATUS_VERIFIED,
        self::STATUS_REJECTED,
    ];

    /** Statuses a session may transition away from when it is submitted. */
    public const SUBMITTABLE_FROM = [self::STATUS_DRAFT];

    /** Statuses a session may be verified or rejected from. */
    public const REVIEWABLE_FROM = [self::STATUS_DRAFT, self::STATUS_SUBMITTED];

    /** Stages whose measurements are stored as replicate rows. */
    public const REPLICATE_STAGES = self::VALIDATION_STAGES;

    public const REPLICATE_MIN = 1;
    public const REPLICATE_MAX = 4;

    public static function isValidationStage(string $stage): bool
    {
        return in_array($stage, self::VALIDATION_STAGES, true);
    }

    public static function isWorkflowStatus(string $status): bool
    {
        return in_array($status, self::WORKFLOW_STATUSES, true);
    }

    /**
     * Human label for a stage code, used in recommendation explanations.
     */
    public static function label(string $stage): string
    {
        return match ($stage) {
            self::GRADING_01 => '01 Grading',
            self::GRADING_02 => '02 Grading',
            self::GRADING_03 => '03 Grading',
            self::GRADING_04 => '04 Grading',
            self::HARVEST => 'Harvest',
            default => $stage,
        };
    }
}
