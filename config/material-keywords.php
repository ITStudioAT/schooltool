<?php

return [
    'algorithm' => 'local-hybrid-keyphrases',
    'algorithm_version' => '2026-07-25.3',
    'maximum_tags' => 10,
    'minimum_score' => 3.5,
    'minimum_tag_length' => 3,
    'maximum_tag_length' => 80,
    'maximum_words_per_tag' => 4,
    'maximum_processed_characters' => 150000,
    'maximum_heading_characters' => 120,
    'minimum_body_occurrences_for_single_term' => 2,

    'weights' => [
        'base' => 1.0,
        'material_title' => 4.0,
        'title_affinity' => 3.0,
        'attachment_title' => 1.5,
        'heading' => 2.75,
        'term_frequency' => 1.6,
        'document_specificity' => 2.0,
        'multi_word_phrase' => 1.25,
        'early_occurrence' => 1.0,
        'generic_term_penalty' => 8.0,
    ],

    'german_stop_words' => [
        'aber', 'alle', 'allem', 'allen', 'aller', 'alles', 'als', 'also', 'am', 'an', 'andere', 'anderem',
        'anderen', 'anderer', 'anderes', 'auch', 'auf', 'aus', 'bei', 'beim', 'beide', 'beiden', 'bis', 'da',
        'dabei', 'dadurch', 'dafür', 'dagegen', 'damit', 'danach', 'dann', 'darauf', 'daraus', 'das', 'dass',
        'davon', 'dazu', 'dein', 'deine', 'dem', 'den', 'denn', 'der', 'des', 'die', 'dies', 'diese', 'diesem',
        'diesen', 'dieser', 'dieses', 'doch', 'dort', 'du', 'durch', 'ein', 'eine', 'einem', 'einen', 'einer',
        'eines', 'er', 'es', 'etwas', 'euch', 'euer', 'für', 'gegen', 'hat', 'haben', 'hier', 'hin', 'hinter',
        'ich', 'ihm', 'ihn', 'ihnen', 'ihr', 'ihre', 'im', 'in', 'indem', 'ins', 'ist', 'jede', 'jedem',
        'jeden', 'jeder', 'jedes', 'kann', 'kein', 'keine', 'mit', 'nach', 'nicht', 'noch', 'nun', 'nur',
        'a', 'an', 'ob', 'oder', 'ohne', 'sehr', 'sein', 'seine', 'seit', 'selbst', 'sich', 'sie', 'sind', 'so', 'solche',
        'über', 'um', 'und', 'uns', 'unser', 'unter', 'vom', 'von', 'vor', 'war', 'waren', 'was', 'weil',
        'welche', 'welchem', 'welchen', 'welcher', 'welches', 'wenn', 'werden', 'wie', 'wieder', 'wird', 'wir',
        'the', 'wo', 'zu', 'zum', 'zur', 'zwischen',
    ],

    'english_stop_words' => [
        'about', 'after', 'again', 'against', 'all', 'also', 'an', 'and', 'any', 'are', 'as', 'at', 'be',
        'because', 'been', 'before', 'being', 'between', 'both', 'but', 'by', 'can', 'could', 'did', 'do',
        'does', 'doing', 'down', 'during', 'each', 'few', 'for', 'from', 'further', 'had', 'has', 'have',
        'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how', 'i', 'if', 'in',
        'into', 'is', 'it', 'its', 'itself', 'me', 'more', 'most', 'my', 'myself', 'no', 'nor', 'not', 'of',
        'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'out', 'over', 'own', 'same', 'she',
        'should', 'so', 'some', 'such', 'than', 'that', 'the', 'their', 'them', 'then', 'there', 'these',
        'they', 'this', 'those', 'through', 'to', 'too', 'under', 'until', 'up', 'very', 'was', 'we', 'were',
        'what', 'when', 'where', 'which', 'while', 'who', 'why', 'will', 'with', 'would', 'you', 'your',
    ],

    'phrase_connectors' => [
        'and', 'der', 'des', 'die', 'of', 'und', 'von',
    ],

    'example_heading_markers' => [
        'beispiel', 'clip', 'filmszene', 'kurzfilm',
    ],

    'german_adjective_suffixes' => [
        'al', 'bar', 'e', 'en', 'end', 'er', 'es', 'isch', 'lich', 'los', 'sam', 'voll',
    ],

    'generic_terms' => [
        'absatz', 'angabe', 'anlage', 'antwort', 'arbeit', 'arbeitsauftrag', 'arbeitsblatt', 'aufgabe',
        'aufgaben', 'aufnahme', 'aufnahmen', 'beispiel', 'beispiele', 'bereich', 'beschreibung', 'bild',
        'bilder', 'datei', 'dialog', 'dokument', 'dokumente', 'einleitung', 'einstellung', 'einstellungen',
        'ende', 'ergebnis', 'ergebnisse', 'erstellt', 'film', 'filme', 'frage', 'fragen', 'gruppe', 'gruppen',
        'bedeutung', 'geschichte', 'hinweis', 'hinweise', 'information', 'informationen', 'inhalt', 'inhalte',
        'kapitel', 'klasse', 'lösung', 'lösungen', 'material', 'materialien', 'merksatz', 'methode', 'mittel',
        'moment', 'name', 'nummer', 'person', 'personen', 'praxisauftrag', 'produkt', 'reihenfolge', 'schritt',
        'schritte', 'sekunde', 'sekunden', 'seite', 'seiten', 'text', 'thema', 'themenblatt', 'tür',
        'situation',
        'unterricht', 'video', 'weitere informationen', 'word', 'worksheet',
        'answer', 'answers', 'chapter', 'content', 'document', 'documents', 'example', 'examples', 'exercise',
        'file', 'files', 'information', 'introduction', 'lesson', 'material', 'page', 'pages', 'question',
        'questions', 'result', 'results', 'section', 'task', 'tasks', 'text',
    ],

    'verb_terms' => [
        'arbeitet', 'beantworten', 'befindet', 'beginnt', 'bleibt', 'bilden', 'beschreibt', 'darstellen',
        'dargestellt', 'durchgeführt', 'entdeckt', 'entsteht', 'erhalten', 'erkennen', 'erklären', 'erstellt',
        'erzeugt', 'folgt', 'funktioniert', 'gezeigt', 'gestalten', 'gestaltet', 'gibt', 'glauben', 'halten',
        'können', 'lenken', 'liest', 'lügt', 'machen', 'nehmen', 'nutzen', 'ordnet', 'passiert', 'prüfen', 'sammeln', 'sehen',
        'soll', 'sollen', 'steht', 'untersuchen', 'verändert', 'verbinden', 'vergleichen', 'vermutet',
        'verwenden', 'wählen', 'wissen', 'wirkt', 'zeigt',
        'appears', 'are', 'becomes', 'can', 'contains', 'creates', 'describes', 'does', 'has', 'is', 'makes',
        'shows', 'uses', 'was', 'were', 'will',
    ],
];
