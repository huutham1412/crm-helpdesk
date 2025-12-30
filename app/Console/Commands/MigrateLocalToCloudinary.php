<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Cloudinary\Cloudinary;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class MigrateLocalToCloudinary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cloudinary:migrate-local
                            {--delete-local : Delete local files after successful upload}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate local attachments to Cloudinary';

    private ?Cloudinary $cloudinary = null;

    private int $successCount = 0;
    private int $failCount = 0;
    private int $skipCount = 0;

    public function __construct()
    {
        parent::__construct();

        // Initialize Cloudinary
        if (
            config('cloudinary.cloud_name') &&
            config('cloudinary.api_key') &&
            config('cloudinary.api_secret')
        ) {
            $this->cloudinary = new Cloudinary([
                'cloud_name' => config('cloudinary.cloud_name'),
                'api_key' => config('cloudinary.api_key'),
                'api_secret' => config('cloudinary.api_secret'),
            ]);
        }
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->cloudinary) {
            $this->error('Cloudinary credentials not configured!');
            $this->info('Please set CLOUDINARY_CLOUD_NAME, CLOUDINARY_API_KEY, and CLOUDINARY_API_SECRET in .env file.');
            return 1;
        }

        $this->info('Starting migration from local storage to Cloudinary...');

        // Get all attachments that don't have cloudinary_public_id
        $attachments = Attachment::whereNull('cloudinary_public_id')->get();

        if ($attachments->isEmpty()) {
            $this->info('No local attachments found to migrate.');
            return 0;
        }

        $this->info("Found {$attachments->count()} attachments to migrate.");
        $this->newLine();

        $this->output->progressStart($attachments->count());

        DB::beginTransaction();

        try {
            foreach ($attachments as $attachment) {
                $this->migrateAttachment($attachment, $this->option('delete-local'));
                $this->output->progressAdvance();
            }

            DB::commit();

            $this->output->progressFinish();

            $this->newLine();
            $this->info('Migration completed!');
            $this->info("Success: {$this->successCount}");
            $this->info("Failed: {$this->failCount}");
            $this->info("Skipped: {$this->skipCount}");

            if ($this->option('delete-local')) {
                $this->warn('Local files were deleted after successful upload.');
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->output->progressFinish();
            $this->error('Migration failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function migrateAttachment(Attachment $attachment, bool $deleteLocal = false): void
    {
        try {
            // Build local file path
            $localPath = storage_path('app/public/' . $attachment->file_path);

            // Check if local file exists
            if (!file_exists($localPath)) {
                $this->warn("File not found: {$attachment->filename} (ID: {$attachment->id})");
                $this->skipCount++;
                return;
            }

            // Determine if it's an image
            $isImage = $attachment->isImage();
            $folder = config('cloudinary.folder', 'crm-helpdesk/attachments') . '/' . $attachment->ticket_id;

            // Upload to Cloudinary
            $uploadResult = $this->cloudinary->uploadApi()->upload(
                $localPath,
                [
                    'folder' => $folder,
                    'public_id' => uniqid(),
                    'resource_type' => $isImage ? 'image' : 'raw',
                    'use_filename' => false,
                ]
            );

            // Update database
            $attachment->update([
                'cloudinary_public_id' => $uploadResult['public_id'],
                'cloudinary_url' => $uploadResult['secure_url'],
            ]);

            // Delete local file if requested
            if ($deleteLocal) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $this->successCount++;

        } catch (\Exception $e) {
            $this->error("Failed to migrate {$attachment->filename}: " . $e->getMessage());
            $this->failCount++;
        }
    }
}
