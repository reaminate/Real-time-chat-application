<?php

use App\Enums\AttachmentCollectionEnum;
use App\Enums\AttachmentFileTypeEnum;
use App\Enums\AttachmentImageTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            // polymorphic owner (user avatar / message file); also creates the index
            $table->morphs('attachable');
            $table->enum('collection', array_column(AttachmentCollectionEnum::cases(), 'value'));
            $table->string('original_name');
            $table->string('file_name');
            $table->enum('mime_type', array_merge(
                array_column(AttachmentImageTypeEnum::cases(), 'value'),
                array_column(AttachmentFileTypeEnum::cases(), 'value'),
            ));
            // bytes; the 5 MB / 2 MB (avatar) limit is enforced in the Form Request
            $table->unsignedInteger('size');
            $table->string('path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
