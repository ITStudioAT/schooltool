<?php

namespace App\Services\NumberSystems;

use InvalidArgumentException;

class NumberSystemEngine
{
    public const VERSION = 1;

    public const DIRECTIONS = ['decimal_binary', 'binary_decimal', 'decimal_hex', 'hex_decimal', 'binary_hex', 'hex_binary'];

    public function count(int $minutes): int
    {
        return match ($minutes) {
            30 => 12, 40 => 18, 60 => 24, 75 => 30, default => throw new InvalidArgumentException('Unbekannte Spieldauer.')
        };
    }

    public function parse(string $input, int $base, ?int $width = null): ?int
    {
        if (! in_array($base, [2, 10, 16], true) || strlen($input) > 100) {
            return null;
        }
        $text = strtoupper(trim($input));
        if ($base === 2) {
            $text = preg_replace('/\s*\(DUAL\)$/', '', $text);
            $text = preg_replace('/^0B/', '', $text);
        } elseif ($base === 16) {
            $text = preg_replace('/^(#|0X)/', '', $text);
        }
        $text = preg_replace('/\s+/u', '', $text);
        $pattern = match ($base) {
            2 => '/\A[01]+\z/', 10 => '/\A[0-9]+\z/', 16 => '/\A[0-9A-F]+\z/'
        };
        if (! preg_match($pattern, $text) || strlen($text) > 32 || ($width !== null && strlen($text) !== $width)) {
            return null;
        }
        $value = intval($text, $base);

        return $value >= 0 && $value <= 4095 ? $value : null;
    }

    public function digits(int $value, int $base, ?int $width = null): string
    {
        if ($value < 0 || $value > 4095 || ! in_array($base, [2, 10, 16], true)) {
            throw new InvalidArgumentException('Zahl außerhalb des Lernbereichs.');
        }

        return str_pad(strtoupper(base_convert((string) $value, 10, $base)), $width ?? 1, '0', STR_PAD_LEFT);
    }

    public function notation(int $value, int $base, ?int $width = null): string
    {
        $digits = $this->digits($value, $base, $width);

        return match ($base) {
            2 => $digits.' (dual)', 16 => '#'.$digits, default => $digits
        };
    }

    /** @return array<string, mixed> Internal definition; solution, hints and explanation never enter the initial player payload. */
    public function mission(string $seed, int $studentId, int $position, int $minutes, int $difficulty): array
    {
        $total = $this->count($minutes);
        if ($position < 0 || $position >= $total || ! in_array($difficulty, [1, 2, 3], true)) {
            throw new InvalidArgumentException('Ungültige Mission.');
        }
        $key = $seed.'|'.$studentId.'|'.$position.'|'.$minutes.'|'.$difficulty.'|v1';
        $draw = fn (string $label, int $min, int $max): int => $min + (int) (hexdec(substr(hash('sha256', $key.'|'.$label), 0, 8)) % ($max - $min + 1));
        $maximum = [1 => 31, 2 => 255, 3 => 4095][$difficulty];
        $edges = [0, 10, 15, 16, 31, 53, 127, 128, 154, 173, 255, 256, 260, 527, 4095];
        $edges = array_values(array_filter($edges, fn (int $value): bool => $value <= $maximum));
        $value = $draw('edge', 0, 2) === 0 ? $edges[$draw('edge-value', 0, count($edges) - 1)] : $draw('value', 0, $maximum);
        $chapter = min(3, (int) floor($position * 4 / $total));
        $chapters = ['Maschinenraum', 'Fehlerarchiv', 'Tresor', 'Funkzentrale'];
        $stories = ['Wecke die stillgelegten Rechner. Jeder richtige Datenweg bringt Energie zurück.',
            'Jemand hat die Protokolle verändert. Prüfe die Referenz und repariere die Daten.',
            'Die Sicherungskammer öffnet sich nur, wenn alle Darstellungen zusammenpassen.',
            'Stelle den Kontakt nach außen her. Zahlen werden wieder zu verständlichen Nachrichten.'];
        $mission = ['id' => substr(hash('sha256', $key), 0, 24), 'position' => $position, 'chapter' => $chapter,
            'chapter_title' => $chapters[$chapter], 'story' => $stories[$chapter], 'fields' => [], 'solution' => [], 'directions' => []];
        $directions = self::DIRECTIONS;
        usort($directions, fn (string $a, string $b): int => strcmp(hash('sha256', $seed.'|'.$a), hash('sha256', $seed.'|'.$b)));
        $direction = $directions[$position % 6];
        $kind = $position < 6 ? 'conversion' : match (true) {
            $position === $total - 1 => 'finale',
            $position === $total - 3 => 'decode',
            $position === $total - 2 => 'encode',
            default => ['repair', 'discover', 'vault', 'conversion', 'repair', 'discover'][($position - 6) % 6],
        };
        if ($kind === 'conversion') {
            [$source, $target] = match ($direction) {
                'decimal_binary' => [10, 2], 'binary_decimal' => [2, 10], 'decimal_hex' => [10, 16],
                'hex_decimal' => [16, 10], 'binary_hex' => [2, 16], 'hex_binary' => [16, 2],
            };
            $width = $value > 255 ? 12 : 8;
            $sourceText = $this->notation($value, $source, $source === 2 ? $width : null);
            $mission += ['kind' => $target === 2 ? 'bits' : ($target === 16 ? 'nibbles' : 'convert'),
                'title' => $target === 2 ? 'Schalte den Datenweg' : ($target === 16 ? 'Baue den Hex-Schlüssel' : 'Lies die Maschine'),
                'prompt' => 'Übertrage '.$sourceText.' in die '.self::baseLabel($target).'.',
                'source' => $sourceText, 'source_base' => $source, 'target_base' => $target, 'width' => $width];
            $mission['directions'] = [$direction];
            $this->field($mission, 'answer', self::baseLabel($target), $target, $value,
                $mission['kind'], $target === 2 ? $width : null);
            if ($target === 2 && $draw('repair-switch', 0, 1) === 1) {
                $mission['fields'][0]['initial'] = $this->digits($value ^ (1 << $draw('switch', 0, $width - 1)), 2, $width);
                $mission['title'] = 'Finde den Störschalter';
                $mission['prompt'] = 'Genau ein Bit der vorbereiteten Schaltung ist falsch. Repariere sie für die Vorgabe '.$sourceText.'.';
            }
            if ($source === 10) {
                $this->field($mission, 'quotient', 'Erste Division: ganzzahliger Quotient von '.$value.' : '.$target, 10, intdiv($value, $target));
                $this->field($mission, 'remainder', 'Rest dieser Division (dezimal)', 10, $value % $target);
            } elseif ($source === 2 && $target === 10) {
                $weights = $this->weights($value);
                $mission['fields'][] = ['name' => 'weights', 'label' => 'Welche Stellenwerte sind eingeschaltet?', 'widget' => 'weights', 'width' => $width];
                $mission['solution']['weights'] = ['type' => 'weights', 'value' => $weights];
            } elseif ($source === 16 && $target === 10) {
                $left = $this->digits($value, 16)[0];
                $this->field($mission, 'digit', 'Wert der linken Hexziffer '.$left.' (dezimal)', 10, intval($left, 16));
            } elseif ($source === 2 && $target === 16) {
                $groups = implode(' ', str_split($this->digits($value, 2, $width), 4));
                $mission['fields'][] = ['name' => 'groups', 'label' => 'Von rechts in Vierergruppen, links mit Nullen ergänzen', 'widget' => 'text', 'placeholder' => '0000 0000'];
                $mission['solution']['groups'] = ['type' => 'groups', 'value' => $groups];
            }
            $mission['explanation'] = $this->explain($value).' '.($source === 10 ? 'Erste Division: '.$value.' : '.$target.' = '.intdiv($value, $target).' Rest '.($value % $target).'. Reste von unten nach oben lesen.' : 'Jede Hexziffer entspricht genau vier Bits.');
        } elseif ($kind === 'repair') {
            $badBase = $draw('bad-base', 0, 1) ? 2 : 16;
            $badValue = ($value + (1 << $draw('bad-bit', 0, $value > 255 ? 11 : 7))) % ($maximum + 1);
            if ($badValue === $value) {
                $badValue = ($value + 1) % ($maximum + 1);
            }
            $mission += ['kind' => 'repair', 'title' => 'Repariere das Fehlerarchiv',
                'prompt' => 'Die Dezimalreferenz '.$value.' ist verbindlich. Genau eine andere Darstellung wurde verändert. Benenne sie und repariere das Protokoll.',
                'reference' => (string) $value];
            foreach ([2 => 'binary', 16 => 'hex'] as $base => $name) {
                $this->field($mission, $name, self::baseLabel($base), $base, $value);
                $mission['fields'][array_key_last($mission['fields'])]['initial'] = $this->notation($base === $badBase ? $badValue : $value, $base, $base === 2 ? ($value > 255 ? 12 : 8) : null);
            }
            $this->choice($mission, 'changed', 'Welche Darstellung war manipuliert?', [['id' => 'binary', 'label' => 'Dual'], ['id' => 'hex', 'label' => 'Hexadezimal']], $badBase === 2 ? 'binary' : 'hex');
            $mission['directions'] = ['decimal_binary', 'decimal_hex'];
            $mission['explanation'] = $this->explain($value).' Die manipulierte Angabe stellte dagegen '.$badValue.' dar. Die ausdrücklich genannte Dezimalreferenz macht die Reparatur eindeutig.';
        } elseif ($kind === 'decode' || $kind === 'encode') {
            $prefixes = ['TEAM', 'CODE', 'TOR', 'FUNK', 'NETZ'];
            $letters = chr(65 + $draw('letter-1', 0, 25)).chr(65 + $draw('letter-2', 0, 25)).chr(65 + $draw('letter-3', 0, 25));
            $word = $kind === 'decode' ? $prefixes[$draw('prefix', 0, 4)].' '.substr($letters, 0, 2) : $letters;
            $base = $draw('message-base', 0, 1) ? 2 : 16;
            $tokens = array_map(fn (string $char): string => $this->notation(ord($char), $base, $base === 2 ? 8 : 2), str_split($word));
            $mission += ['kind' => 'message', 'title' => $kind === 'decode' ? 'Empfange das Rufzeichen' : 'Sende ein Rufzeichen',
                'prompt' => $kind === 'decode' ? 'Entschlüssele die Zeichencodes mit der ASCII-Tabelle. Was steht in der Nachricht?' : 'Codiere jeden Buchstaben von '.$word.' als '.($base === 2 ? 'genau acht Bits' : 'Hex-Byte').'. Trenne die Bytes durch Leerzeichen.',
                'tokens' => $kind === 'decode' ? $tokens : [], 'word' => $kind === 'encode' ? $word : null,
                'ascii' => true, 'message_base' => $base,
                'note' => 'ASCII ist eine Zeichencodierung, kein Zahlensystem und keine sichere Verschlüsselung. ASCII umfasst 0–127; wir füllen hier jedes Zeichen auf acht Bits auf.'];
            $mission['fields'][] = ['name' => 'answer', 'label' => $kind === 'decode' ? 'Lesbare Nachricht' : 'Codierte Bytes', 'widget' => 'text'];
            $mission['solution']['answer'] = $kind === 'decode' ? ['type' => 'text', 'value' => $word]
                : ['type' => 'bytes', 'base' => $base, 'value' => array_map('ord', str_split($word))];
            if ($kind === 'decode') {
                $this->field($mission, 'first', 'ASCII-Nummer des ersten Zeichens (dezimal)', 10, ord($word[0]));
            }
            $mission['directions'] = $base === 2 ? ['binary_decimal', 'decimal_binary'] : ['hex_decimal', 'decimal_hex'];
            $mission['explanation'] = $word.': '.implode(' ', $tokens).'. Ein Zahlenwert wird über die ASCII-Zuordnung zum Zeichen; ein verändertes Bit kann einen anderen gültigen Buchstaben ergeben. Ohne Referenz ist eine beschädigte Nachricht nicht immer eindeutig reparierbar.';
        } elseif ($kind === 'discover') {
            $variant = $draw('discovery', 0, 3);
            $questions = [
                ['Warum lässt sich jede Hexziffer durch genau vier Bits ersetzen?', 'Vier Bits haben 16 Kombinationen mit den Werten 0–15.', ['Vier Bits können nur vier Werte darstellen.', 'Eine Hexziffer entspricht immer acht Bits.'], ['0000 (dual) = #0', '0111 (dual) = #7', '1111 (dual) = #F']],
                ['Was geschieht beim Ergänzen einer Null ganz links?', 'Der Zahlwert bleibt gleich.', ['Der Zahlwert verdoppelt sich.', 'Der Zahlwert wird um eins kleiner.'], ['101 (dual) = 5', '0101 (dual) = 5', '00101 (dual) = 5']],
                ['Ist ASCII ein weiteres Zahlensystem?', 'Nein. ASCII ordnet Zahlen Zeichen zu.', ['Ja. ASCII hat die Basis 128.', 'ASCII verschlüsselt Texte sicher.'], ['65 = #41 = 01000001 (dual)', 'Die ASCII-Tabelle ordnet diesem Wert das Zeichen A zu.']],
                ['Welche Regel verbindet benachbarte Stellenwerte?', 'Nach links mal Basis: dual mal 2, hexadezimal mal 16.', ['Nach links immer plus 2.', 'Die Stellenwerte bleiben gleich.'], ['Dual: 1, 2, 4, 8, 16', 'Hexadezimal: 1, 16, 256']],
            ];
            [$prompt, $correct, $wrong, $evidence] = $questions[$variant];
            $options = [$correct, ...$wrong];
            $offset = $draw('choice-offset', 0, 2);
            $options = array_merge(array_slice($options, $offset), array_slice($options, 0, $offset));
            $mission += ['kind' => 'discover', 'title' => 'Forscherpause im Logbuch', 'prompt' => 'Untersuche die Beispiele. '.$prompt,
                'evidence' => $evidence, 'note' => 'Erkläre die Regel einer anderen Person. Probiere danach ein eigenes Zahlenbeispiel aus.'];
            $this->choice($mission, 'answer', 'Welche Regel wird durch die Beispiele gestützt?', array_map(fn (string $text, int $index): array => ['id' => (string) $index, 'label' => $text], $options, array_keys($options)), (string) array_search($correct, $options, true));
            $mission['explanation'] = $correct.' '.implode(' · ', $evidence).' Verwende diese Regel gleich beim nächsten Datenweg.';
        } else {
            $mission += ['kind' => 'vault', 'title' => $kind === 'finale' ? 'Das letzte Signal' : 'Öffne die Sicherungskammer',
                'prompt' => $kind === 'finale' ? 'Verbinde alle drei Systeme. Erst wenn alle vier Schlüsselfelder stimmen, geht das Rechenzentrum wieder ans Netz.' : 'Vier Schlösser verbinden drei Zahlensysteme. Löse die Datenwege, um die Kammer zu öffnen.', 'finale' => $kind === 'finale'];
            foreach ([[2, 16], [16, 10], [10, 2], [10, 16]] as $index => [$source, $target]) {
                $number = $draw('vault-'.$index, 0, $maximum);
                $this->field($mission, 'lock'.$index, $this->notation($number, $source).' → '.self::baseLabel($target), $target, $number);
                $mission['fields'][array_key_last($mission['fields'])]['public_label'] = 'Schloss '.($index + 1).' · '.self::baseLabel($target);
                $mission['private_clues'][] = 'Schloss '.($index + 1).': Die Vorgabe lautet '.$this->notation($number, $source).'. Gesucht ist die '.self::baseLabel($target).'.';
            }
            $mission['directions'] = ['binary_hex', 'hex_decimal', 'decimal_binary', 'decimal_hex'];
            $mission['explanation'] = implode(' ', array_map(fn (array $spec): string => $this->explain($spec['value']), $mission['solution']));
        }
        $mission['hints'] = [$this->strategy($mission), 'Nutze das Logbuch: Stellenwerte von rechts, Hex in Vierergruppen. Prüfe Ergebnis und Rechenschritt getrennt.',
            'Durchgerechnete Hilfe: '.$this->solutionText($mission).' '.$mission['explanation']];

        return $mission;
    }

    /** @return array<string, mixed> */
    public function publicMission(array $mission): array
    {
        unset($mission['solution'], $mission['hints'], $mission['explanation'], $mission['private_clues']);

        return $mission;
    }

    /** @return array{correct: bool, result_correct: bool, step_correct: bool, message: string} */
    public function check(array $mission, array $draft): array
    {
        $checks = [];
        foreach ($mission['solution'] as $name => $spec) {
            $input = $draft[$name] ?? null;
            $checks[$name] = match ($spec['type']) {
                'number' => is_string($input) && $this->parse($input, $spec['base'], $spec['width'] ?? null) === $spec['value'],
                'weights' => is_array($input) && $this->validWeights($input, $spec['value']),
                'groups' => is_string($input) && trim(preg_replace('/\s+/', ' ', $input)) === $spec['value'],
                'text' => is_string($input) && mb_strtoupper(trim(preg_replace('/\s+/', ' ', $input))) === $spec['value'],
                'choice' => is_string($input) && $input === $spec['value'],
                'bytes' => is_string($input) && $this->bytes($input, $spec['base']) === $spec['value'],
            };
        }
        $result = $checks['answer'] ?? ! in_array(false, $checks, true);
        $step = ! in_array(false, array_diff_key($checks, ['answer' => true]), true);
        $correct = ! in_array(false, $checks, true);

        return ['correct' => $correct, 'result_correct' => $result, 'step_correct' => $step,
            'message' => $correct ? 'Datenweg bestätigt. '.$mission['explanation']
                : ($result && ! $step ? 'Das Ergebnis passt bereits. Prüfe noch den Rechenschritt; er gehört zu diesem Schloss.' : 'Noch nicht ganz. Prüfe die benannten Zahlensysteme und Stellenwerte. Führende Nullen sind wertgleich; eine feste Bitbreite muss zusätzlich stimmen. Du darfst ohne Punkteverlust korrigieren oder Hilfe holen.')];
    }

    /** @return list<int>|null */
    public function bytes(string $text, int $base): ?array
    {
        $parts = preg_split('/\s+/', trim($text));
        if (count($parts) > 20) {
            return null;
        }
        $values = [];
        foreach ($parts as $part) {
            $value = $this->parse($part, $base, $base === 2 ? 8 : null);
            if ($value === null || $value > 127) {
                return null;
            }
            $values[] = $value;
        }

        return $values;
    }

    public static function baseLabel(int $base): string
    {
        return [2 => 'Dualdarstellung', 10 => 'Dezimaldarstellung', 16 => 'Hexdarstellung'][$base];
    }

    /** @return list<int> */
    private function weights(int $value): array
    {
        return array_values(array_filter([1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048], fn (int $weight): bool => ($value & $weight) !== 0));
    }

    private function validWeights(array $input, array $expected): bool
    {
        foreach ($input as $weight) {
            if (! is_int($weight) && (! is_string($weight) || ! ctype_digit($weight))) {
                return false;
            }
        }
        $values = array_map('intval', $input);
        sort($values);

        return $values === $expected;
    }

    private function field(array &$mission, string $name, string $label, int $base, int $value, string $widget = 'text', ?int $width = null): void
    {
        $mission['fields'][] = ['name' => $name, 'label' => $label, 'widget' => $widget, 'base' => $base, 'width' => $width ?? ($widget === 'nibbles' ? (int) ceil(strlen($this->digits($value, 2, $value > 255 ? 12 : 8)) / 4) : null)];
        $mission['solution'][$name] = ['type' => 'number', 'base' => $base, 'value' => $value, 'width' => $width];
    }

    private function choice(array &$mission, string $name, string $label, array $options, string $correct): void
    {
        $mission['fields'][] = ['name' => $name, 'label' => $label, 'widget' => 'choice', 'options' => $options];
        $mission['solution'][$name] = ['type' => 'choice', 'value' => $correct];
    }

    private function explain(int $value): string
    {
        $weights = $this->weights($value);
        $hex = $this->digits($value, 16);
        $terms = [];
        foreach (str_split($hex) as $index => $digit) {
            $terms[] = intval($digit, 16).' × '.(16 ** (strlen($hex) - $index - 1));
        }

        return $this->notation($value, 2).' = '.($weights ? implode(' + ', array_reverse($weights)) : '0').' = '.$value.' = #'.$hex.' ('.implode(' + ', $terms).').';
    }

    private function strategy(array $mission): string
    {
        return match ($mission['kind']) {
            'bits' => 'Von rechts nach links: 1, 2, 4, 8, … . Schalte nur die benötigten Stellenwerte ein. Die Summe muss zur Vorgabe passen.',
            'nibbles' => '16 = 2⁴: Jede Hexziffer ersetzt genau vier Bits. Gruppiere von rechts und fülle nur links mit Nullen auf. Bei Dezimalzahlen hilft Division durch 16.',
            'repair' => 'Die ausdrücklich genannte Referenz ist verbindlich. Prüfe jede andere Darstellung durch Rückrechnung, statt die beiden verdächtigen Angaben als Referenz zu nehmen.',
            'message' => 'ASCII ist die Zeichentabelle. Ermittle erst den Zahlenwert eines Bytes, dann das Zeichen. A hat die Nummer 65, ein Leerzeichen die Nummer 32.',
            'discover' => 'Vergleiche die Beispiele im Logbuch. Eine Regel muss zu allen Beispielen passen; prüfe sie an einer eigenen kleinen Zahl.',
            default => 'Dual und Hex: Stellenwerte addieren. Dezimal in eine andere Basis: wiederholt dividieren, Reste von unten nach oben lesen.',
        };
    }

    private function solutionText(array $mission): string
    {
        $parts = [];
        foreach ($mission['solution'] as $name => $spec) {
            $value = match ($spec['type']) {
                'number' => $this->notation($spec['value'], $spec['base'], $spec['width'] ?? null),
                'weights' => implode(' + ', $spec['value']) ?: 'keine (Summe 0)',
                'bytes' => implode(' ', array_map(fn (int $value): string => $spec['base'] === 2 ? $this->digits($value, 2, 8) : $this->notation($value, 16, 2), $spec['value'])),
                'choice' => collect($mission['fields'])->firstWhere('name', $name)['options'][array_search($spec['value'], array_column(collect($mission['fields'])->firstWhere('name', $name)['options'], 'id'), true)]['label'],
                default => $spec['value'],
            };
            $label = collect($mission['fields'])->firstWhere('name', $name)['label'];
            $parts[] = $label.': '.$value;
        }

        return implode('; ', $parts).'.';
    }
}
