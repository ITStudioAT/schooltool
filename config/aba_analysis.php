<?php

return [
    'openai_normalization_enabled' => (bool) env('ABA_ANALYSIS_OPENAI_NORMALIZATION_ENABLED', false),
    'openai_model' => env('ABA_ANALYSIS_OPENAI_MODEL'),
    'auto_approve_confidence' => (float) env('ABA_ANALYSIS_AUTO_APPROVE_CONFIDENCE', 0.82),
    'max_block_text_length' => (int) env('ABA_ANALYSIS_MAX_BLOCK_TEXT_LENGTH', 2000),
    'max_section_text_length' => (int) env('ABA_ANALYSIS_MAX_SECTION_TEXT_LENGTH', 16000),
];
