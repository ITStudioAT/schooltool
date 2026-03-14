<?php

namespace App\ABA\Knowledge;

/**
 * Atomar normalisierte Wissenseinheit für ABA-Regelwerk.
 *
 * classification values:
 *   requirement | recommendation | deadline | formatting_rule |
 *   submission_rule | evaluation_rule | ai_policy | citation_rule |
 *   uncertainty | best_practice
 *
 * normative_strength values: binding | official | recommended | unclear | location_dependent
 */
readonly class AbaKnowledgeClaim
{
    public function __construct(
        public string $claim_key,
        public string $topic,
        public string $statement,
        public string $classification,
        public string $authority_level,
        public string $normative_strength,
        public bool $is_uncertain,
        public string $applies_to,
        public ?string $valid_from,
        public ?string $valid_to,
        public array $source_refs,
        public array $tags,
        public ?string $note = null,
    ) {}

    public function toArray(): array
    {
        return [
            'claim_key' => $this->claim_key,
            'topic' => $this->topic,
            'statement' => $this->statement,
            'classification' => $this->classification,
            'authority_level' => $this->authority_level,
            'normative_strength' => $this->normative_strength,
            'is_uncertain' => $this->is_uncertain,
            'applies_to' => $this->applies_to,
            'valid_from' => $this->valid_from,
            'valid_to' => $this->valid_to,
            'source_refs' => $this->source_refs,
            'tags' => $this->tags,
            'note' => $this->note,
        ];
    }

    public function toJsonl(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
