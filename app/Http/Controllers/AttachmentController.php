<?php

namespace App\Http\Controllers;

use App\Models\attachment;
use App\Http\Requests\StoreattachmentRequest;
use App\Http\Requests\UpdateattachmentRequest;

class AttachmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreattachmentRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(attachment $attachment)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateattachmentRequest $request, attachment $attachment)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(attachment $attachment)
    {
        //
    }
}
