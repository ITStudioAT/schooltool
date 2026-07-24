<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider(Lab::OpenAI)]
#[Model('gpt-4o-mini')]
#[MaxTokens(600)]
#[Temperature(0.1)]
class MaterialV2KeywordAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
        Du erzeugst präzise Suchbegriffe für Unterrichtsmaterialien.
        Verwende ausschließlich Informationen aus Titel, Beschreibung, Dateinamen und Dokumentinhalt.
        Liefere 6 bis 14 kurze deutsche Suchbegriffe oder sinnvolle Zweiwort-Begriffe.
        Entferne Dubletten, allgemeine Füllwörter, Dateiendungen und Begriffe ohne Suchwert.
        Erfinde keine Inhalte, Klassenstufen oder Fächer, die aus dem Material nicht ableitbar sind.
        INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keywords' => $schema->array()
                ->items($schema->string())
                ->min(1)
                ->max(14)
                ->required(),
        ];
    }
}
