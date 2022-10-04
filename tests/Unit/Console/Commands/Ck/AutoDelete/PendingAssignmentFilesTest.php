<?php

namespace Tests\Unit\Console\Commands\Ck\AutoDelete;

use Tests\TestCase;
use App\Models\File;
use App\Models\Service;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Artisan;
use App\Console\Commands\Ck\AutoDelete\PendingAssignmentFilesCommand;

class PendingAssignmentFilesTest extends TestCase
{
    public function test_auto_delete_works()
    {
        $newPendingAssignmentFile = factory(File::class)
            ->states('pending-assignment')
            ->create([
                'created_at' => Date::today(),
                'updated_at' => Date::today(),
            ]);

        $dueForDeletionFile = factory(File::class)
            ->states('pending-assignment')
            ->create([
                'created_at' => Date::today()->subDays(File::PEDNING_ASSIGNMENT_AUTO_DELETE_DAYS),
                'updated_at' => Date::today()->subDays(File::PEDNING_ASSIGNMENT_AUTO_DELETE_DAYS),
            ]);

        Artisan::call(PendingAssignmentFilesCommand::class);

        $this->assertDatabaseHas($newPendingAssignmentFile->getTable(), ['id' => $newPendingAssignmentFile->id]);
        $this->assertDatabaseMissing($dueForDeletionFile->getTable(), ['id' => $dueForDeletionFile->id]);
    }

    public function test_auto_delete_leaves_no_orphans()
    {
        $service = factory(Service::class)->create();

        $dueForDeletionFile = factory(File::class)
            ->states('pending-assignment')
            ->create([
                'created_at' => Date::today()->subDays(File::PEDNING_ASSIGNMENT_AUTO_DELETE_DAYS),
                'updated_at' => Date::today()->subDays(File::PEDNING_ASSIGNMENT_AUTO_DELETE_DAYS),
            ]);

        $galleryItem = $service->serviceGalleryItems()->create([
                'file_id' => $dueForDeletionFile->id,
            ]);

        Artisan::call(PendingAssignmentFilesCommand::class);

        $this->assertDatabaseMissing($galleryItem->getTable(), ['id' => $galleryItem->id]);
        $this->assertDatabaseMissing($dueForDeletionFile->getTable(), ['id' => $dueForDeletionFile->id]);
    }
}
