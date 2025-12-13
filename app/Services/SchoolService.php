<?php

namespace App\Services;

use App\Http\Resources\Admin\LicenceResource;
use App\Http\Resources\Admin\UserResource;
use App\Models\Licence;
use App\Models\School;
use App\Models\SchoolLicence;
use App\Models\SchoolTool;
use App\Models\Schoolyear;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;


class SchoolService
{

    public function create($data)
    {
        // Merken falls upload_file gesetzt ist
        $path = $data['upload_file'] ?? null;
        unset($data['upload_file']);


        // Schule anlegen
        $school  = School::create($data);

        // Standard-Schuljahr anlegen
        $schoolyear = Schoolyear::create([
            'school_id' => $school->id,
            'name' => 'Schuljahr'
        ]);

        // Super-Admin anlegen
        $user = User::create([
            'school_id' => $school->id,
            'schoolyear_id' => $schoolyear->id,
            'last_name' => env('SA_LAST_NAME'),
            'first_name' => env('SA_FIRST_NAME'),
            'email' => env('SA_EMAIL'),
            'password' => env('SA_PW'),
            'email_verified_at' => now(),
            'confirmed_at' => now(),
        ]);

        $user->assignRole('super_admin');

        // SchoolTool - Record erzeugen 
        $schoolTool = SchoolTool::create([
            'school_id' =>  $school->id,
            'tutoring_student_must_be_confirmed' => false,
            'tutoring_confirmer_email' => '',
        ]);

        // Folder für LOogo etc anlegen
        $hlp_path = $school->id . '/temp';
        if (!Storage::directoryExists($hlp_path)) {
            Storage::makeDirectory($hlp_path);
        }
        $hlp_path = $school->id . '/excel';
        if (!Storage::directoryExists($hlp_path)) {
            Storage::makeDirectory($hlp_path);
        }
        $hlp_path = $school->id . '/pdf';
        if (!Storage::directoryExists($hlp_path)) {
            Storage::makeDirectory($hlp_path);
        }


        // Logo verschieben
        if ($path) {
            $school = $this->moveLogo($school, $path);
        }

        return $school;
    }

    public function update($school, $data)
    {

        // Merken falls upload_file gesetzt ist
        $path = $data['upload_file'] ?? null;
        unset($data['upload_file']);

        // Schule updaten
        $school->update($data);

        // Logo verschieben
        if ($path) {
            $school = $this->moveLogo($school, $path);
        }

        return $school;
    }

    public function deleteSchools($ids)
    {


        foreach ($ids as $id) {
            $this->deleteSchool($id);
        }
    }

    private function deleteSchool($id): bool
    {

        // Schule 1 kann nicht gelöscht werden (Big Boss Schule)
        if ($id == 1) return false;

        // Schule mit Registers kann nicht gelöscht werden
        if (School::where('id', $id)->has('registers')->exists()) return false;

        // Die Schule hat mehr User als nur den Super-Admin und kann daher nicht gelöscht werden
        if (School::where('id', $id)->has('users', '>', 1)->exists())  return false;

        $school = School::findOrFail($id);

        // Lizenzen löschen
        $school->licences()->detach();

        // Super-Admin löschen
        User::where('school_id', $id)->each(function ($user) {
            $user->syncRoles([]);   // removes all role assignments
            $user->delete();
        });


        // Schuljahr der Schule löschen
        Schoolyear::where('school_id', $id)->delete();

        // SchoolTool der Schule löschen
        SchoolTool::where('school_id', $id)->delete();

        // Schulen lösche
        $school = School::find($id);

        if ($school) {

            // Logo löschen, falls vorhanden
            if ($school->logo) {
                Storage::disk('public')->delete("images/{$school->logo}");
            }

            // Schule löschen
            $school->delete();
        }

        // Storage-Pfad löschen, falls vorhanden
        Storage::deleteDirectory("{$id}");

        return true;
    }

    public function schoolInfos($school_id)
    {
        $licences = School::find($school_id)->licences->sortBy('name');
        $data = [
            'licences' => LicenceResource::collection($licences)
        ];

        \Debugbar::info($school_id);
        $roles = ['admin', 'register_admin', 'super_admin', 'tutoring_admin'];
        $users = User::where('school_id', $school_id)
            ->role($roles)
            ->orderBy('last_name')
            ->get();

        $data['admins'] = UserResource::collection($users);

        return $data;
    }

    public function loadSwitchableSchools($user)
    {

        $users = User::where('email', $user->email)->get();
        $ids = $users->pluck('id');

        $schools = User::whereIn('id', $ids)
            ->with('selectedSchool')
            ->get()
            ->pluck('selectedSchool')
            ->filter()
            ->unique('id')
            ->sortBy('long_name')
            ->values();

        return $schools;
    }

    public function switchSchool($user, $school_id)
    {
        // Prüfen, ob es den User mit der Schule gibt
        $targetUser = User::where('email', $user->email)
            ->where('school_id', $school_id)
            ->first();

        if (! $targetUser) abort(403, 'Wechsel zu der Schule nicht möglich.');

        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('web')->login($targetUser, true);
        session()->regenerate();

        return $targetUser;
    }

    private function moveLogo($school, $path)
    {
        $relPath = Str::before(ltrim($path, '/'), '?'); // strip leading slash + ?t=...

        // 1) Absolute paths
        $source = storage_path('app/public/' . $relPath);      // /storage/app/private/temp/1/logo.jpg
        $destDir = storage_path('app/public/images');           // /storage/app/public/images

        // 2) Build new filename
        $baseName  = pathinfo($source, PATHINFO_FILENAME);      // "logo"
        $extension = pathinfo($source, PATHINFO_EXTENSION);     // "jpg"
        $newFilename = "{$baseName}_{$school->id}.{$extension}";  // "logo_12.jpg"
        $destPath = $destDir . DIRECTORY_SEPARATOR . $newFilename;


        // 3) copy the file
        // make sure the target directory exists
        File::ensureDirectoryExists(dirname($destDir));

        // copy the file
        File::copy($source, $destPath);

        // 4) Save only the pure filename in DB
        $school->logo = $newFilename;   // e.g. "logo_12.jpg"
        $school->save();

        return $school;
    }

    public function addAdmin($school_id, $schoolyear_id, $data, $roles): User
    {

        if ($user = User::where('school_id', $school_id)->where('email', $data['email'])->first()) abort(409, "Dieser Admin existiert bereits und kann daher nicht angelegt werden.");

        // Check if the user shoul get an super_admin role, but there exists an super_admin user ==> not allowed
        $role = "super_admin";
        if (in_array($role, $roles)) {
            if ($users = User::where('school_id', $school_id)
                ->role($role) // provided by Spatie\Permission\Traits\HasRoles
                ->count() > 0
            ) abort(409, "Für diese Schule existiert bereits ein Super-Admin.");
        }

        $data['school_id'] = $school_id;
        $data['schoolyear_id'] = $schoolyear_id;
        $data['email_verified_at'] = now();
        $data['password'] = Hash::make(now());
        $data['confirmed_at'] = now();

        $user = User::create($data);
        $user->assignRole($roles);
        return $user;
    }

    public function deleteAdmin($user_id, $is_delete_complete)
    {

        if ($user_id == 1) abort(409, "Der Big-Boss-User kann nicht gelöscht werden.");
        $user = User::findOrFail($user_id);

        $roles = ['admin', 'register_admin'];
        foreach ($roles as $role) {
            $user->removeRole($role);
        }

        if ($is_delete_complete) {
            if (count($user->registerDateBookings) > 0) abort(409, 'Der Benutzer hat noch gebuchte Anmeldungen und kann nicht gelöscht werden. Seine Rollen wurden gelöscht.');

            $user->delete();
        }
    }
}
