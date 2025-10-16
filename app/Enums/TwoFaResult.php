<?php

namespace App\Enums;

enum TwoFaResult: string
{
    case TWO_FA_DELETE = 'TWO_FA_DELETE'; // Löschen der 2-FA-Authentifizieren
    case TWO_FA_OK = 'TWO_FA_OK'; // Zwei-Faktoren-Authenifizierung ok, 2-FA-E-Mail vorhanden und verifiziert
    case TWO_FA_EMAIL_MUST_BE_VERIFIED = 'TWO_FA_EMAIL_MUST_BE_VERIFIED'; // 2FA-EMail exists, but isnt verified at this moment => must be verified
    case TWO_FA_EMAIL_IS_NEW = 'TWO_FA_EMAIL_IS_NEW'; // 2FA-EMail is new and not verified
    case TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL = 'TWO_FA_EMAIL_AND_2FA_EMAIL_MUST_NOT_BE_EQUAL';  //
    case TWO_FA_ERROR = 'TWO_FA_ERROR';  // 2FA-ERROR
    case TWO_FA_SET = 'TWO_FA_SET';  // 2FA is set now

}
