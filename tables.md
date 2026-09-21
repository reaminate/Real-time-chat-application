# summary
- 6 tables: users, conversations, conversation_members (pivot), messages (soft deleted), attachments (polymorphic: user avatar + message files)
- a conversation is direct (2 users) or group; members have a role (owner/admin/member) and are never hard-deleted, `left_at` is set instead
- messages belong to a conversation and a sender, can reply to another message, and can carry attachments
- everything is scoped by conversation membership: non-members get no access to a conversation, its messages, or its attachments
- realtime is delivered over private Reverb channels, one per conversation, using the events listed under "other rules"
- currently implemented in code: only `users` and `GET /api/user`; everything else is planned

# users
## table
|id -> primary key|
|name -> string|
|email -> email, unique|
|password -> minimum 7, include both upper and lower|
|last_seen_at -> date, if same day show time|
### endpoints
- crud operations
- search functionality
- GET /api/user (implemented, auth:sanctum) - returns the authenticated user
### policies
- a user may only view/update/delete their own account
- password updates should require current password confirmation

# conversations
## table
|id -> primary key|
|type -> enum direct/group|
|name -> string, nullable if type is direct|
|latest_message-> fk=message->id, nullable|
|created_by -> fk = users->id, automatically owner|
### endpoints
- crud operations
- /api/conversations/{conversation}/members GET/POST/DELETE
- /api/conversations/direct POST
### policies
- only conversation members may access the conversation
- only authorized users should manage members
- no duplicate direct conversations between two same users
### rules
when creating, set the last read id to null. That way there wont be an error.
# conversation_members (pivot for users and conversations)
## table
|id-> primary key|
|user_id-> fk = users->id|
|conversation_id-> fk = conversations->id|
|last_read_id -> fk=messages->id, nullable|
|role -> enum owner, admin, member|
|joined_at -> date|
|left_at -> date, nullable| ->will delete after 10 days after this is set
|conversation_id + user_id = unique|
### endpoints
- managed through /api/conversations/{conversation}/members GET/POST/DELETE (see conversations)
### policies
- only owner/admin may add, remove, or change the role of a member
- a member may remove themself (leave); this should set left_at rather than deleting the row
### behavior
- if a user is deleted itll set to null
- if a convo is deleted itll set to null
- this way you can rejoin a convo.
- if both are null, itll get deleted.
# messages (employ soft deletion)
## table
|id -> primary key|
|conversation_id -> fk=conversations->id|
|sender_id -> fk = users, id|
|reply_to -> fk=messages, id, nullable|
|type -> enum text, image, file (image/file messages must have at least one attachement)|
|body -> nullabe|
|edited_at -> nullable, should be set when this message is updated|
### endpoints
- /api/conversations/{conversation}/messages GET/POST
- /api/conversations/{conversation}/messages/{message} PUT/PATCH/DELETE
### policies
- only conversation members may view or send messages
- only the sender may edit or delete their own message
- deleting a message should soft delete it, so other members still see it in conversation history

# attachments (polymorphic)
one table stores every uploaded file; the owner is any model via `attachable_type` + `attachable_id`. currently: `User` (avatar) and `Message` (files/images). could later serve `Conversation` (group avatar) with no schema change.
## table
|id -> primary key|
|attachable_type -> string, morph alias (`user`, `message`), registered with `Relation::enforceMorphMap`|
|attachable_id -> unsignedBigInteger|
|collection -> enum avatar, attachment (which slot on the owner)|
|original_name = string|
|file_name = string|
|mime_type = enum png,jpg,jpeg,pdf,docx (avatar: png,jpg,jpeg only)|
|size = int, max 5 mb (avatar: max 2 mb)|
|path = string|
|index on attachable_type + attachable_id (`$table->morphs('attachable')` creates it)|
### relations
- `Attachment::attachable()` -> `morphTo`
- `User::avatar()` -> `morphOne(Attachment::class, 'attachable')->where('collection', 'avatar')`
- `Message::attachments()` -> `morphMany(Attachment::class, 'attachable')->where('collection', 'attachment')`
- uploading a new avatar replaces (deletes file + row of) the old one, so a user never has more than one
- a database foreign key is not possible on a polymorphic column, so deletion is handled in code: deleting a user or force-deleting a message must delete its attachment rows and files (model `deleting` hook / observer). soft-deleting a message keeps its attachments
### endpoints
- /api/conversations/{conversation}/messages (message attachments are uploaded with the message)
- /api/user/avatar POST/DELETE
### policies
- message attachments: only conversation members may upload or download them (authorize through the message's conversation)
- message attachments: only the message sender may add or remove attachments on their message
- avatars: any authenticated user may view; only the user themself may upload or remove their own
- `AttachmentPolicy` must branch on `attachable_type`, since the owner decides who has access
- validate mime_type and size on upload, with the limit picked by `collection`


# other rules
## general rules
- all `/api/*` routes require `auth:sanctum` unless they are login/register
- every conversation-scoped endpoint must authorize membership through a Policy, never inline checks
- validate input with Form Requests, following the column rules above (password, avatar, size, mime types)
- use soft deletes on messages only; conversation_members use `left_at` instead of deleting
- keep unique constraints at the database level (conversation_id + user_id), not just in validation
- set `edited_at` whenever a message body is updated
- set `logged_off_at` on logout and update `last_seen_at` on activity
- broadcast events after the database change succeeds, and never leak data to non-members
- useronline/offline is driven by the Reverb presence connection; `last_seen_at` and `logged_off_at` are the persisted fallback
## private reverb channels
- each conversation must have an assoicated private channel
## required events
- messagesent/updated/deleted
- usertyping/stopped
- messagedelivered/read
- useronline/offline
- conversationcreated/updated
- memberadded/removed

