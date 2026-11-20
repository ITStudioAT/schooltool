<?php

namespace App\Exceptions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DropboxExerciseRequestFailed extends ValidationException
{
    public const OPERATIONS = [
        'authorization' => 'Dropbox-Freigabe prüfen',
        'folder_inventory' => 'Ordnerinhalt prüfen',
        'folder_parent_metadata' => 'Übergeordneten Ordner vor der Anlage prüfen',
        'folder_create' => 'Ordner anlegen',
        'folder_child_metadata' => 'Neu angelegten Ordner rückprüfen',
        'document_parent_metadata' => 'Dateiordner prüfen',
        'document_metadata' => 'Datei prüfen',
        'document_download' => 'Datei zurücklesen',
        'document_upload' => 'Datei speichern',
    ];

    public const ENDPOINTS = [
        'authorization' => 'oauth2/token',
        'folder_inventory' => 'files/list_folder',
        'folder_parent_metadata' => 'files/get_metadata',
        'folder_create' => 'files/create_folder_v2',
        'folder_child_metadata' => 'files/get_metadata',
        'document_parent_metadata' => 'files/get_metadata',
        'document_metadata' => 'files/get_metadata',
        'document_download' => 'files/download',
        'document_upload' => 'files/upload',
    ];

    public const CATEGORIES = [
        'network' => 'Die Verbindung zu Dropbox wurde unterbrochen oder hat nicht rechtzeitig geantwortet.',
        'malformed_response' => 'Dropbox hat das Ergebnis nicht eindeutig bestätigt.',
        'rate_limit' => 'Dropbox begrenzt derzeit die Anfragen. Bitte kurz warten und erneut versuchen.',
        'provider_unavailable' => 'Dropbox ist vorübergehend nicht verfügbar.',
        'request_rejected' => 'Dropbox hat die Anfrage als ungültig abgelehnt (HTTP 400).',
        'document_too_large' => 'Die Prüfungsdatei überschreitet die unterstützte Dateigröße.',
        'authorization_denied' => 'Dropbox hat die Freigabe abgelehnt. Bitte erneut verbinden.',
        'authorization_unconfirmed' => 'Die Dropbox-Freigabe konnte nicht bestätigt werden.',
        'access_restricted' => 'Dropbox hat den Zugriff auf das Ziel abgelehnt.',
        'not_found' => 'Dropbox konnte das Ziel nicht finden.',
        'conflict' => 'Am vorgesehenen Dropbox-Ziel besteht bereits ein Konflikt.',
        'invalid_path' => 'Dropbox hat den Pfad als ungültig abgelehnt.',
        'no_write_permission' => 'Für den Dropbox-Zielordner fehlt die Schreibberechtigung.',
        'insufficient_space' => 'Im Dropbox-Ziel ist nicht genügend Speicherplatz verfügbar.',
        'disallowed_name' => 'Dropbox erlaubt den vorgesehenen Ordnernamen nicht.',
        'too_many_write_operations' => 'Dropbox begrenzt derzeit gleichzeitige Schreibvorgänge. Bitte erneut versuchen.',
        'operation_suppressed' => 'Dropbox hat den Vorgang unterdrückt.',
        'unclassified' => 'Dropbox hat die Anfrage mit einem nicht näher bestimmten Konflikt abgelehnt.',
    ];

    public readonly string $safePublicMessage;

    private readonly string $operation;

    private readonly string $category;

    private readonly ?int $httpStatus;

    public function __construct(string $operation, string $category, ?int $httpStatus = null)
    {
        $this->operation = array_key_exists($operation, self::OPERATIONS) ? $operation : 'folder_create';
        $this->category = array_key_exists($category, self::CATEGORIES) ? $category : 'unclassified';
        $this->httpStatus = $httpStatus !== null && $httpStatus >= 400 && $httpStatus <= 599 ? $httpStatus : null;
        $this->safePublicMessage = self::OPERATIONS[$this->operation].'. '.self::CATEGORIES[$this->category];

        $validator = Validator::make([], []);
        $validator->errors()->add('dropbox', $this->safePublicMessage);
        parent::__construct($validator);
    }

    /** @return array{operation: string, category: string, http_status: ?int} */
    public function diagnostic(): array
    {
        return ['operation' => $this->operation, 'category' => $this->category, 'http_status' => $this->httpStatus];
    }

    public function endpoint(): string
    {
        return self::ENDPOINTS[$this->operation];
    }
}
