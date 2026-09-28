<?php

namespace App\Http\Controllers;

use App\Enums\AttachmentCollectionEnum;
use App\Models\Attachment;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    //all of this is mostly done in the message controller so no need.
    // /**
    //  * Display a listing of the resource.
    //  */
    // public function index()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(StoreAttachmentRequest $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(Attachment $attachment)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(UpdateAttachmentRequest $request, Attachment $attachment)
    // {
    //     //
    // }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(Attachment $attachment)
    // {
    //     //
    // }
    /**
     * streams the file, avatars live on the public disk and message attachments on the local disk
     */
    public function download(Attachment $attachment, Request $request)
    {
        if($request->user()->cannot('view', $attachment)){
            abort(403);
        }
        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($attachment->__get('collection') === AttachmentCollectionEnum::AVATAR ? 'public' : 'local');
        $path = $attachment->__get('path');
        if(!$storage->exists($path)){
            abort(404);
        }
        return $storage->response($path, $attachment->__get('original_name'));
    }

}
