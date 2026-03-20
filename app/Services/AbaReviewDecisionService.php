<?php

namespace App\Services;

class AbaReviewDecisionService
{
    /**
     * @param  array<string,mixed>  $validation
     * @return array{state:string,auto_approved:bool,reason:string,threshold:float}
     */
    public function decide(array $validation): array
    {
        $errors = is_array($validation['errors'] ?? null) ? $validation['errors'] : [];
        $warnings = is_array($validation['warnings'] ?? null) ? $validation['warnings'] : [];
        $missingFields = is_array($validation['missing_fields'] ?? null) ? $validation['missing_fields'] : [];
        $finalConfidence = is_numeric($validation['final_confidence'] ?? null)
            ? (float) $validation['final_confidence']
            : 0.0;

        $threshold = (float) config('aba_analysis.auto_approve_confidence', 0.82);
        if ($errors !== []) {
            return [
                'state' => 'review_required',
                'auto_approved' => false,
                'reason' => 'validation_errors',
                'threshold' => $threshold,
            ];
        }

        if ($missingFields !== []) {
            return [
                'state' => 'review_required',
                'auto_approved' => false,
                'reason' => 'missing_fields',
                'threshold' => $threshold,
            ];
        }

        if ($finalConfidence < $threshold) {
            return [
                'state' => 'review_required',
                'auto_approved' => false,
                'reason' => 'confidence_below_threshold',
                'threshold' => $threshold,
            ];
        }

        if ($warnings !== []) {
            return [
                'state' => 'review_required',
                'auto_approved' => false,
                'reason' => 'warnings_present',
                'threshold' => $threshold,
            ];
        }

        return [
            'state' => 'auto_approved',
            'auto_approved' => true,
            'reason' => 'all_checks_passed',
            'threshold' => $threshold,
        ];
    }
}
