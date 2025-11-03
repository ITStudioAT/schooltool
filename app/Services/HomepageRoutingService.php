<?php

namespace App\Services;

use App\Models\School;

class HomepageRoutingService
{

    public function checkRoute($school_load, $licence_load)
    {

        $redirect = '/homepage';

        // Prüfen der Schule
        if ($school_load) {
            info("SCHOOL_LOAD");
            $school = School::where('short_name', $school_load)->first();
            if (!$school) return ['status' => 'error', 'msg' => 'Die Schule konnte nicht gefunden werden.'];
            $redirect = "/homepage/?school=" . $school_load;

            // Prüfen der Lizenz
            if ($licence_load) {
                info("LICENCE_LOAD");
                $licenceService = new LicenceService();
                $answer = $licenceService->checkLicence($school, $licence_load);

                if ($answer['status'] == 'error')  return $answer;

                $redirect .= $answer['redirect'];
            }
        }

        return ['status' => 'ok', 'redirect' => $redirect];
    }
}
