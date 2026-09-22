<?php

namespace App\Http\Controllers;

use App\Models\ConversationMember;
use App\Http\Requests\StoreConversationMemberRequest;
use App\Http\Requests\UpdateConversationMemberRequest;

class ConversationMemberController extends Controller
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
    public function store(StoreConversationMemberRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ConversationMember $conversationMember)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateConversationMemberRequest $request, ConversationMember $conversationMember)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ConversationMember $conversationMember)
    {
        //
    }
}
