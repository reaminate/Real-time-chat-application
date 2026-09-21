<?php

namespace App\Http\Controllers;

use App\Models\conversation_member;
use App\Http\Requests\Storeconversation_memberRequest;
use App\Http\Requests\Updateconversation_memberRequest;

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
    public function store(Storeconversation_memberRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(conversation_member $conversation_member)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Updateconversation_memberRequest $request, conversation_member $conversation_member)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(conversation_member $conversation_member)
    {
        //
    }
}
