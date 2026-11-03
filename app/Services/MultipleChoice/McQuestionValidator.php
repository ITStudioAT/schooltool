<?php

namespace App\Services\MultipleChoice;

class McQuestionValidator
{
    /** @return array<string, list<string>> */
    public function errors(array $questions, bool $requireReviewed = false): array
    {
        $errors = [];
        $questionIds = [];
        if ($questions === []) {
            return ['questions' => ['Der Katalog enthält keine Fragen.']];
        }
        foreach ($questions as $index => $question) {
            $path = "questions.{$index}";
            if (! is_array($question)) {
                $errors[$path][] = 'Eine Frage muss ein Objekt sein.';

                continue;
            }
            if ($requireReviewed && ($question['active'] ?? true) === false) {
                continue;
            }
            $id = $question['id'] ?? null;
            if (! is_string($id) || trim($id) === '' || isset($questionIds[$id])) {
                $errors["{$path}.id"][] = 'Fragen benötigen eindeutige, nicht leere Kennungen.';
            } else {
                $questionIds[$id] = true;
            }
            if (! is_string($question['prompt'] ?? null) || trim($question['prompt']) === '') {
                $errors["{$path}.prompt"][] = 'Der Fragetext fehlt.';
            }
            if (! in_array($question['type'] ?? null, ['single', 'multiple'], true)) {
                $errors["{$path}.type"][] = 'Wählen Sie Einfach- oder Mehrfachauswahl.';
            }
            foreach (['active', 'reviewed'] as $flag) {
                if (array_key_exists($flag, $question) && ! is_bool($question[$flag])) {
                    $errors["{$path}.{$flag}"][] = 'Der Wert muss wahr oder falsch sein.';
                }
            }
            if ($requireReviewed && ($question['reviewed'] ?? false) !== true) {
                $errors["{$path}.reviewed"][] = 'Die Frage muss vor der Testfreigabe geprüft werden.';
            }
            $options = $question['options'] ?? null;
            if (! is_array($options) || ! array_is_list($options) || count($options) < 2) {
                $errors["{$path}.options"][] = 'Mindestens zwei Antwortoptionen sind erforderlich.';
                $options = is_array($options) ? $options : [];
            }
            $optionIds = [];
            $optionTexts = [];
            foreach ($options as $optionIndex => $option) {
                if (! is_array($option)) {
                    $errors["{$path}.options.{$optionIndex}"][] = 'Eine Antwortoption muss ein Objekt sein.';

                    continue;
                }
                $optionId = $option['id'] ?? null;
                if (! is_string($optionId) || trim($optionId) === '' || isset($optionIds[$optionId])) {
                    $errors["{$path}.options.{$optionIndex}.id"][] = 'Antwortoptionen benötigen eindeutige Kennungen.';
                } else {
                    $optionIds[$optionId] = true;
                }
                $text = $option['text'] ?? null;
                if (! is_string($text) || trim($text) === '') {
                    $errors["{$path}.options.{$optionIndex}.text"][] = 'Der Antworttext fehlt.';
                } else {
                    $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
                    if (isset($optionTexts[$normalized])) {
                        $errors["{$path}.options.{$optionIndex}.text"][] = 'Gleiche Antworttexte sind nicht zulässig.';
                    }
                    $optionTexts[$normalized] = true;
                }
            }
            $correct = $question['correct_option_ids'] ?? null;
            if (! is_array($correct) || ! array_is_list($correct) || $correct === []) {
                $errors["{$path}.correct_option_ids"][] = 'Mindestens eine richtige Antwort ist erforderlich.';

                continue;
            }
            $seenCorrect = [];
            foreach ($correct as $correctId) {
                if (! is_string($correctId) || ! isset($optionIds[$correctId]) || isset($seenCorrect[$correctId])) {
                    $errors["{$path}.correct_option_ids"][] = 'Die Lösungen müssen eindeutig auf vorhandene Antworten verweisen.';
                } else {
                    $seenCorrect[$correctId] = true;
                }
            }
            if (($question['type'] ?? null) === 'single' && count($correct) !== 1) {
                $errors["{$path}.correct_option_ids"][] = 'Bei Einfachauswahl muss genau eine Antwort richtig sein.';
            }
            if (($question['type'] ?? null) === 'multiple' && count($correct) < 2) {
                $errors["{$path}.correct_option_ids"][] = 'Bei Mehrfachauswahl müssen mindestens zwei Antworten richtig sein.';
            }
        }

        return $errors;
    }
}
