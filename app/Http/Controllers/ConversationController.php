<?php

namespace App\Http\Controllers;

use App\Models\conversation;
use App\Http\Requests\StoreconversationRequest;
use App\Http\Requests\UpdateconversationRequest;

class ConversationController extends Controller
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
    public function store(StoreconversationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(conversation $conversation)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateconversationRequest $request, conversation $conversation)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(conversation $conversation)
    {
        //
    }
}
