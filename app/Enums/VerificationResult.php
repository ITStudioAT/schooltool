<?php

namespace App\Enums;

enum VerificationResult: string
{
    case VERIFICATION_SUCCESS = 'VERIFICATION_SUCCESS'; // E-Mail wurde erfolgreich verifiziert
    case EMAIL_SENT = 'EMAIL_SENT'; // E-Mail zur Verifikation wurde versandt
    case ALREADY_VERIFIED = 'ALREADY_VERIFIED'; // E-Mail zur Verifikation wurde versandt

}
