<?php

namespace Database\Factories;

use App\Models\DropboxConnection;
use App\Models\TeachingCourseWork;
use App\Models\TeachingWorkDropboxFolder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeachingWorkDropboxFolder>
 */
class TeachingWorkDropboxFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'dropbox_connection_id' => DropboxConnection::factory(),
            'teaching_course_work_id' => TeachingCourseWork::factory(),
            'folder_id' => 'id:'.fake()->uuid(),
            'folder_name' => 'Leistungsarbeit',
        ];
    }
}
