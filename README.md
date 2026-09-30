# RTC App — API Documentation

A real-time chat API built with Laravel. It supports direct and group conversations, text/image/file messages, replies, pinned messages, reply notifications, tagging users, typing indicators, delivered/read receipts, and real-time broadcasting over private channels.

## Table of Contents

- [General](#general)
- [Authentication](#authentication)
- [Users](#users)
- [Conversations](#conversations)
- [Messages](#messages)
- [Attachments](#attachments)
- [Notifications](#notifications)
- [Real-time Events](#real-time-events)
- [Enums](#enums)
- [Response Objects](#response-objects)
- [Running the Backend](#running-the-backend)

---

## General

**Base URL:** `/api`

**Hosted base URL:** https://album-reliably-sureness.ngrok-free.dev

**Headers**

| Header          | Value                  | Required                     |
|-----------------|------------------------|------------------------------|
| `Accept`        | `application/json`     | Yes                          |
| `Authorization` | `Bearer {access_token}`| On all protected routes      |
| `Content-Type`  | `multipart/form-data`  | When uploading files         |

Routes marked 🔒 require authentication via Laravel Sanctum. Only `register` and `login` are public.

**Status codes used by this API**

| Status | Meaning                                               |
|--------|-------------------------------------------------------|
| `200`  | OK                                                    |
| `201`  | Created                                               |
| `204`  | No Content (empty body)                               |
| `401`  | Missing or invalid token                              |
| `403`  | Authenticated, but not allowed to perform this action |
| `404`  | Resource not found                                    |
| `405`  | HTTP method not allowed on this route                 |
| `422`  | Validation failed                                     |
| `500`  | Server or database error                              |

**Error body formats**

`401` / `403` / `404` / `500`:

```json
{ "message": "This action is unauthorized." }
```

`422` (validation, including rate limiting and wrong password):

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Two endpoints return their own error bodies: [Update Conversation](#update-conversation) and [Remove Users](#remove-users) (`{ "error": "..." }`), and [Update User](#update-user) (`422` with an empty body).

**Response wrapping**

- List endpoints wrap their results in `data`.
- Cursor-paginated lists (`GET /user`, `GET /message`) also include `links` and `meta`. Pass `meta.next_cursor` as `?cursor=` to get the next page:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": null, "last": null, "prev": null, "next": "https://.../api/user?cursor=eyJ..." },
  "meta": { "path": "https://.../api/user", "per_page": 20, "next_cursor": "eyJ...", "prev_cursor": null }
}
```

- [`GET /me`](#current-user) is also wrapped in `data`.
- All other endpoints return the object directly.

---

## Authentication

### Register

`POST /register`

Creates a new user and returns an access token. Rate limited to 5 attempts per email + IP. Broadcasts [`user.online`](#real-time-events).

| Field      | Type   | Required | Rules                                                                           |
|------------|--------|----------|---------------------------------------------------------------------------------|
| `name`     | string | Yes      | Letters and spaces only                                                         |
| `email`    | string | Yes      | Valid email, must be unique                                                     |
| `password` | string | Yes      | Min 7 chars, letters, mixed case, numbers, must not appear in a known data leak |
| `avatar`   | file   | No       | Image, `image/png` or `image/jpeg`, max 2 MB                                    |

**Responses**

| Status | When                                                                                     |
|--------|------------------------------------------------------------------------------------------|
| `201`  | User created                                                                             |
| `422`  | Validation failed, or too many attempts (`errors.error` + `errors.try_again` in seconds) |

```json
{
  "message": "login successful",
  "user": { "...": "User object, with avatar" },
  "access_token": "1|abc123...",
  "token_type": "bearer"
}
```

---

### Login

`POST /login`

Rate limited to 5 attempts per email + IP. Clears the user's `last_seen_at` (marks them online) and broadcasts [`user.online`](#real-time-events) to their conversations.

| Field      | Type   | Required | Rules                           |
|------------|--------|----------|---------------------------------|
| `email`    | string | Yes      | Must belong to an existing user |
| `password` | string | Yes      | —                               |

**Responses**

| Status | When                                                                                                                 |
|--------|----------------------------------------------------------------------------------------------------------------------|
| `200`  | Logged in                                                                                                            |
| `422`  | Validation failed, `errors.error: wrong_password`, or `errors.error: too many login attempts` + `errors.try_again`   |

```json
{
  "message": "login successful",
  "user": { "...": "User object" },
  "access_token": "2|xyz789...",
  "type": "bearer"
}
```

---

### Logout

`GET /logout` 🔒

Revokes the current token, sets the user's `last_seen_at` and broadcasts [`user.offline`](#real-time-events) to their conversations.

**Responses**

| Status | When          |
|--------|---------------|
| `200`  | Logged out    |
| `401`  | Not logged in |

```json
{ "message": "logout successful" }
```

---

### Current User

`GET /me` 🔒

Returns the authenticated user with their `avatar`, `conversations` and `created_conversations` loaded.

**Responses**

| Status | When          |
|--------|---------------|
| `200`  | OK            |
| `401`  | Not logged in |

```json
{ "data": { "...": "User object" } }
```

---

## Users

> Users are identified in URLs by their `friend_id` (a slug), **not** their numeric `id`.

### Search Users

`GET /user` 🔒

Searches users by name (excludes yourself), ordered by name descending. Each user includes their `avatar`. If `search` is omitted, all users are returned. Cursor-paginated, 20 per page.

| Query Param | Type   | Required | Rules                    |
|-------------|--------|----------|--------------------------|
| `search`    | string | No       | Letters and spaces only  |
| `cursor`    | string | No       | Cursor for the next page |

**Responses**

| Status | When                                                          |
|--------|---------------------------------------------------------------|
| `200`  | Paginated list of [User objects](#user)                       |
| `401`  | Not logged in                                                 |
| `422`  | `search` contains anything other than letters and spaces      |

---

### Get User

`GET /user/{friend_id}` 🔒

Returns the user along with the conversations you share with them.

**Responses**

| Status | When                                      |
|--------|-------------------------------------------|
| `200`  | [User object](#user) with `conversations` |
| `401`  | Not logged in                             |
| `404`  | No user with that `friend_id`             |

---

### Update User

`PUT /user/{friend_id}` / `PATCH /user/{friend_id}` 🔒

You can only update yourself. All fields are optional, but changing your password requires your current `password`.

| Field          | Type   | Required                        | Rules                                                 |
|----------------|--------|---------------------------------|-------------------------------------------------------|
| `name`         | string | No                              | Letters only (no spaces)                              |
| `email`        | string | No                              | Valid email, must be unique                           |
| `new_password` | string | No                              | Min 7 chars, letters, mixed case, numbers, not leaked |
| `password`     | string | Yes, if `new_password` is sent  | Your current password                                 |
| `avatar`       | file   | No                              | Image, `image/png` or `image/jpeg`, max 2 MB          |

**Responses**

| Status | When                                                                     |
|--------|--------------------------------------------------------------------------|
| `200`  | Updated; returns the [User object](#user)                                |
| `401`  | Not logged in                                                            |
| `403`  | Trying to update someone else                                            |
| `404`  | No user with that `friend_id`                                            |
| `422`  | Validation failed, or `password` is wrong (this case has an empty body)  |

---

### Delete User

`DELETE /user/{friend_id}` 🔒

You can only delete yourself.

**Responses**

| Status | When                          |
|--------|-------------------------------|
| `204`  | Deleted                       |
| `401`  | Not logged in                 |
| `403`  | Trying to delete someone else |
| `404`  | No user with that `friend_id` |

---

### Active Users

`GET /active-users` 🔒

Returns users who are currently logged in. Cached for 60 seconds. Users are returned as raw models, not [User objects](#user).

**Responses**

| Status | When          |
|--------|---------------|
| `200`  | OK            |
| `401`  | Not logged in |

```json
{
  "message": "currently_active_users",
  "users": [ { "...": "raw user model" } ]
}
```

---

## Conversations

**Roles:** each member has a role of `owner`, `admin`, or `member`. The creator is the `owner`.

| Action                                    | Who can do it      |
|-------------------------------------------|--------------------|
| View, send typing indicator, view pinned  | Any active member  |
| Update, add users, remove users, pin      | `owner` or `admin` |
| Delete, restore users, force-delete users | `owner` only       |

### List Conversations

`GET /conversation` 🔒

Returns all conversations you belong to, each with its `last_message`. Not paginated.

**Responses**

| Status | When                                                   |
|--------|--------------------------------------------------------|
| `200`  | `{ "data": [...] }` of [Conversation objects](#conversation) |
| `401`  | Not logged in                                          |

---

### Create Conversation

`POST /conversation` 🔒

You are automatically added to `users`. With exactly one other user the conversation is a **direct** conversation; with more it is a **group**. Only one direct conversation can exist between two users. Prevents two users from creating the same conversation at the same time. Broadcasts [`GroupCreated`](#real-time-events).

| Field   | Type         | Required                        | Rules                                                   |
|---------|--------------|---------------------------------|---------------------------------------------------------|
| `users` | array of int | Yes                             | At least 1 other user; existing user IDs; no duplicates |
| `name`  | string       | Yes, if more than 2 users total | Max 10 chars; ignored for direct conversations          |

**Responses**

| Status | When                                                                          |
|--------|-------------------------------------------------------------------------------|
| `201`  | Created; returns the [Conversation object](#conversation)                     |
| `401`  | Not logged in                                                                 |
| `422`  | Validation failed, or `A direct conversation with this user already exists.`  |

---

### Get Conversation with Messages

`GET /conversation/{id}/messages` 🔒

Returns the conversation with all its messages (newest first, each with its sender). `image`/`file` messages include their attachments in `body`.

Opening a conversation marks its new messages as **delivered**: it broadcasts [`MessageDelivered`](#real-time-events) to the other members with the IDs of the messages sent by others since your last read message. Messages are marked as **read** one at a time, when you open each one with [Get Message](#get-message).

| Query Param  | Type | Required | Description         |
|--------------|------|----------|---------------------|
| `created_by` | flag | No       | Include the creator |

Flags only need to be present, e.g. `?created_by`.

**Responses**

| Status | When                                                 |
|--------|------------------------------------------------------|
| `200`  | [Conversation object](#conversation) with `messages` |
| `401`  | Not logged in                                        |
| `403`  | You are not an active member                         |
| `404`  | Conversation not found                               |

---

### Update Conversation

`PUT /conversation/{id}` / `PATCH /conversation/{id}` 🔒

All fields are optional.

| Field              | Type         | Required | Rules                                                                       |
|--------------------|--------------|----------|-----------------------------------------------------------------------------|
| `name`             | string       | No       | Max 10 chars                                                                |
| `make_users_admin` | array of int | No       | Min 1; each must be an active member of this conversation                   |
| `created_by`       | int          | No       | Existing user ID — transfers ownership to that user (you become a `member`) |

**Responses**

| Status | When                                                          |
|--------|---------------------------------------------------------------|
| `200`  | Updated; returns the [Conversation object](#conversation)     |
| `401`  | Not logged in                                                 |
| `403`  | You are not an `owner` or `admin`                             |
| `404`  | Conversation not found, or `{ "error": "Record not found." }` |
| `422`  | Validation failed                                             |
| `500`  | `{ "error": "Database update failed." }`                      |

---

### Delete Conversation

`DELETE /conversation/{id}` 🔒

**Responses**

| Status | When                    |
|--------|-------------------------|
| `204`  | Deleted                 |
| `401`  | Not logged in           |
| `403`  | You are not the `owner` |
| `404`  | Conversation not found  |

---

### Add Users

`POST /conversation/{id}/users` 🔒

Adds users as `member`s. A direct conversation is turned into a group named `New Group`. Broadcasts [`UserAdded`](#real-time-events).

| Field       | Type         | Required | Rules                                                               |
|-------------|--------------|----------|---------------------------------------------------------------------|
| `add_users` | array of int | Yes      | Min 1; existing user IDs; not already active members; no duplicates |

**Responses**

| Status | When                                                                    |
|--------|-------------------------------------------------------------------------|
| `200`  | Added; returns the [Conversation object](#conversation) with `members`  |
| `401`  | Not logged in                                                           |
| `403`  | You are not an `owner` or `admin`                                       |
| `404`  | Conversation not found                                                  |
| `422`  | Validation failed                                                       |

---

### Remove Users

`DELETE /conversation/{id}/users` 🔒

Marks users as having left. If 1 or fewer members remain the conversation is deleted; if exactly 2 remain it becomes a direct conversation. Broadcasts [`UserDeleted`](#real-time-events).

| Field          | Type         | Required | Rules                                               |
|----------------|--------------|----------|-----------------------------------------------------|
| `delete_users` | array of int | Yes      | Min 1; each must be an active member; no duplicates |

**Responses**

| Status | When                                                                      |
|--------|---------------------------------------------------------------------------|
| `200`  | Removed; returns the [Conversation object](#conversation) with `members`  |
| `204`  | Removed, and the conversation was deleted as a result                     |
| `401`  | Not logged in                                                             |
| `403`  | You are not an `owner` or `admin`                                         |
| `404`  | Conversation not found                                                    |
| `422`  | Validation failed                                                         |
| `500`  | `{ "error": "Database update failed." }`                                  |

---

### Restore Removed Users

`POST /conversation/{id}/restore` 🔒

Re-adds users who were previously removed from the conversation. Broadcasts [`UserAdded`](#real-time-events).

| Field   | Type         | Required | Rules                                                 |
|---------|--------------|----------|-------------------------------------------------------|
| `users` | array of int | Yes      | Min 1; each must be a *removed* member; no duplicates |

**Responses**

| Status | When                    |
|--------|-------------------------|
| `200`  | Restored                |
| `401`  | Not logged in           |
| `403`  | You are not the `owner` |
| `404`  | Conversation not found  |
| `422`  | Validation failed       |

```json
{
  "message": "users have been restored",
  "users": [ { "...": "User object" } ]
}
```

---

### Permanently Remove Users

`DELETE /conversation/{id}/force_delete` 🔒

Permanently detaches previously removed users from the conversation.

| Field   | Type         | Required | Rules                                                 |
|---------|--------------|----------|-------------------------------------------------------|
| `users` | array of int | Yes      | Min 1; each must be a *removed* member; no duplicates |

**Responses**

| Status | When                    |
|--------|-------------------------|
| `204`  | Removed                 |
| `401`  | Not logged in           |
| `403`  | You are not the `owner` |
| `404`  | Conversation not found  |
| `422`  | Validation failed       |

---

### Typing Indicator

`POST /conversation/{id}/typing` 🔒

Broadcasts a `user.typing` event to other members. Throttled to once every 3 seconds per user per conversation (extra calls still return `204` but don't broadcast). No body.

**Responses**

| Status | When                         |
|--------|------------------------------|
| `204`  | OK                           |
| `401`  | Not logged in                |
| `403`  | You are not an active member |
| `404`  | Conversation not found       |


`POST /conversation/{id}/stopped-typing` 🔒

Broadcasts a `user.stopped.typing` event to other members. Throttled to once every 3 seconds per user per conversation (extra calls still return `204` but don't broadcast). No body. If a user is typing event exists, it will delete it.

**Responses**

| Status | When                         |
|--------|------------------------------|
| `204`  | OK                           |
| `401`  | Not logged in                |
| `403`  | You are not an active member |
| `404`  | Conversation not found       |

---

### Pin Message

`PUT /pin/{id}/add` 🔒

Pins a message in the conversation `{id}`. Any number of messages can be pinned at once, and pinning a message that is already pinned does nothing.

| Field         | Type | Required | Rules                                                   |
|---------------|------|----------|---------------------------------------------------------|
| `pin_message` | int  | Yes      | ID of a message in this conversation; not deleted       |

**Responses**

| Status | When                                                       |
|--------|------------------------------------------------------------|
| `200`  | Pinned; returns the [Conversation object](#conversation)   |
| `401`  | Not logged in                                              |
| `403`  | You are not an `owner` or `admin`                          |
| `404`  | Conversation not found                                     |
| `422`  | Validation failed                                          |

---

### Unpin Message

`PUT /pin/{id}/remove` 🔒

Unpins a message in the conversation `{id}`.

| Field                | Type | Required | Rules                                                          |
|----------------------|------|----------|----------------------------------------------------------------|
| `remove_pin_message` | int  | Yes      | ID of a *pinned* message in this conversation; not deleted     |

**Responses**

| Status | When                                                       |
|--------|------------------------------------------------------------|
| `200`  | Unpinned; returns the [Conversation object](#conversation) |
| `401`  | Not logged in                                              |
| `403`  | You are not an `owner` or `admin`                          |
| `404`  | Conversation not found                                     |
| `422`  | Validation failed, including when the message isn't pinned |

---

### List Pinned Messages

`GET /pin/{id}/pinned` 🔒

Returns the conversation with only its pinned messages (newest first, each with its sender). Unlike [Get Conversation with Messages](#get-conversation-with-messages), this doesn't broadcast `MessageDelivered`.

**Responses**

| Status | When                                                        |
|--------|-------------------------------------------------------------|
| `200`  | [Conversation object](#conversation) with pinned `messages` |
| `401`  | Not logged in                                               |
| `403`  | You are not an active member                                |
| `404`  | Conversation not found                                      |

---

## Messages

| Action                | Who can do it                                    |
|-----------------------|--------------------------------------------------|
| Send                  | Members of the conversation                      |
| View                  | Active members of the conversation               |
| Edit                  | The sender                                       |
| Delete (soft)         | The sender, or a conversation `owner` or `admin` |
| Restore, force-delete | A conversation `owner` or `admin`                |

### List My Messages

`GET /message` 🔒

Returns messages you have sent, with their replies. Cursor-paginated, 10 per page.

| Query Param   | Type   | Required | Description              |
|---------------|--------|----------|--------------------------|
| `attachments` | flag   | No       | Include attachments      |
| `cursor`      | string | No       | Cursor for the next page |

**Responses**

| Status | When                                          |
|--------|-----------------------------------------------|
| `200`  | Paginated list of [Message objects](#message) |
| `401`  | Not logged in                                 |

---

### Send Message

`POST /message` 🔒

Use `multipart/form-data` when sending an attachment. Sending a message:

- sets it as the conversation's `last_message`
- broadcasts [`MessageSent`](#real-time-events) to the conversation
- if `reply_to` is set, sends a [reply notification](#notifications) to the author of the original message (unless you are replying to yourself)

| Field             | Type   | Required                         | Rules                                     |
|-------------------|--------|----------------------------------|-------------------------------------------|
| `conversation_id` | int    | Yes                              | Existing conversation you are a member of |
| `type`            | string | Yes                              | One of `text`, `image`, `file`            |
| `body`            | string | Yes, if `type` is `text`         | —                                         |
| `attachment`      | file   | Yes, if `type` is `image`/`file` | Any file                                  |
| `reply_to`        | int    | No                               | ID of a message in the same conversation  |

**Responses**

| Status | When                                         |
|--------|----------------------------------------------|
| `201`  | Sent; returns the [Message object](#message). For `image`/`file` messages, `body` holds the stored attachment |
| `401`  | Not logged in                                |
| `403`  | You are not a member of the conversation     |
| `422`  | Validation failed                            |
| `500`  | The message could not be saved               |

---

### Get Message

`GET /message/{id}` 🔒

Always includes the sender (with avatar). Attachments are included for `image`/`file` messages.

Marks the message as **read** for you (sets it as your last read message in the conversation) and broadcasts [`MessageRead`](#real-time-events) to the other members. Call this for each message as the user reads it.

| Query Param                | Type | Required | Description                                           |
|----------------------------|------|----------|-------------------------------------------------------|
| `conversation_information` | flag | No       | Include the conversation (only shown to admins/owner) |
| `reply_to`                 | flag | No       | Include the message this one replies to               |
| `replies`                  | flag | No       | Include replies to this message                       |

**Responses**

| Status | When                       |
|--------|----------------------------|
| `200`  | [Message object](#message) |
| `401`  | Not logged in              |
| `403`  | You are not an active member of the conversation |
| `404`  | Message not found          |

---

### Update Message

`PUT /message/{id}` / `PATCH /message/{id}` 🔒

Text messages update their `body`; image/file messages replace their `attachment`. Broadcasts [`MessageUpdated`](#real-time-events) to the conversation.

| Field             | Type   | Required | Rules                                  |
|-------------------|--------|----------|----------------------------------------|
| `conversation_id` | int    | Yes      | Existing conversation                  |
| `body`            | string | No       | Used for text messages                 |
| `attachment`      | file   | No       | Max 2 MB; used for image/file messages |

**Responses**

| Status | When                                            |
|--------|-------------------------------------------------|
| `200`  | Updated; returns the [Message object](#message). For `image`/`file` messages, `body` holds the new attachment |
| `401`  | Not logged in                                   |
| `403`  | You are not the sender                          |
| `404`  | Message not found                               |
| `422`  | Validation failed                               |

---

### Delete Message

`DELETE /message/{id}` 🔒

Soft-deletes the message and broadcasts [`MessageDeleted`](#real-time-events) to the conversation.

**Responses**

| Status | When                                        |
|--------|---------------------------------------------|
| `204`  | Deleted                                     |
| `401`  | Not logged in                               |
| `403`  | You are not the sender, `owner`, or `admin` |
| `404`  | Message not found                           |

---

### Restore Message

`GET /message/{id}/restore` 🔒

Restores a soft-deleted message and broadcasts [`MessageRestored`](#real-time-events) to the conversation.

**Responses**

| Status | When                                             |
|--------|--------------------------------------------------|
| `201`  | Restored; returns the [Message object](#message) |
| `401`  | Not logged in                                    |
| `403`  | You are not an `owner` or `admin`                |
| `404`  | Message not found                                |

---

### Permanently Delete Message

`DELETE /message/{id}/force_delete` 🔒

Works on both normal and soft-deleted messages. Broadcasts [`MessageDeletedForever`](#real-time-events) to the conversation.

**Responses**

| Status | When                              |
|--------|-----------------------------------|
| `204`  | Deleted                           |
| `401`  | Not logged in                     |
| `403`  | You are not an `owner` or `admin` |
| `404`  | Message not found                 |

---

## Attachments

Every [Attachment object](#attachment) includes a `path`, relative to the storage disk it was saved on:

| Attachment   | Disk     | How to load it                                                     |
|--------------|----------|--------------------------------------------------------------------|
| Avatar       | `public` | `{APP_URL}/storage/{path}` — no token needed (requires `php artisan storage:link`) |
| Message file | `local`  | Private. Use [Download Attachment](#download-attachment) with a bearer token |

### Download Attachment

`GET /attachments/{id}/download` 🔒

Returns the attachment's file inline, with its content type and original file name. Works for both message attachments and avatars. Requires a bearer token, so it can't be used directly in an `<img src>`; fetch it with the token and create an object URL instead. For avatars, the `/storage` URL above is simpler.

| Attachment   | Who can download it                                   |
|--------------|-------------------------------------------------------|
| Avatar       | Any logged-in user                                    |
| Message file | Members of the message's conversation                 |

**Responses**

| Status | When                                                 |
|--------|------------------------------------------------------|
| `200`  | The file                                             |
| `401`  | Not logged in                                        |
| `403`  | Not a member of the message's conversation           |
| `404`  | Attachment not found, or its file is missing         |

---

## Notifications

Notifications are stored in the `notifications` table and broadcast in real time. They are queued, so `php artisan queue:work` must be running.

| Notification           | Sent to                                    | Triggered by                                                                             |
|------------------------|--------------------------------------------|------------------------------------------------------------------------------------------|
| `UserRepliedToMessage` | The author of the message being replied to | [Sending a message](#send-message) with `reply_to` (not sent when replying to yourself)  |
| `PingUser`             | The tagged user                            | [Tagging a user](#tag-a-user) in a conversation                                          |

### Tag a User

`POST /notify/{friend_id}/in/{conversation_id}` 🔒

Tags (pings) another user in a conversation, sending them a `PingUser` notification. Both you and the tagged user must be active members of the conversation, and you can't tag yourself. No body.

**Responses**

| Status | When                                                                       |
|--------|----------------------------------------------------------------------------|
| `204`  | Tagged                                                                     |
| `401`  | Not logged in                                                              |
| `403`  | Tagging yourself, or you or the tagged user is not an active member        |
| `404`  | No user with that `friend_id`, or conversation not found                   |

---

### List Unread Notifications

`GET /notifications` 🔒

Returns your unread notifications and marks them as read, so each notification is only returned once. Not wrapped in `data`.

**Responses**

| Status | When          |
|--------|---------------|
| `200`  | OK            |
| `401`  | Not logged in |

```json
[
  {
    "id": "9b1d6f0e-...",
    "type": "App\\Notifications\\PingUser",
    "notifiable_type": "App\\Models\\User",
    "notifiable_id": 5,
    "data": { "user": 3, "conversation": 7 },
    "read_at": "2026-09-28T10:00:00.000000Z",
    "created_at": "2026-09-28T09:58:00.000000Z",
    "updated_at": "2026-09-28T10:00:00.000000Z"
  }
]
```

`data` holds the same fields as the real-time payload below.

---

### Real-time Notifications

**Channel:** `private-App.Models.User.{id}`, where `{id}` is the numeric user ID. Only that user can subscribe.

```js
Echo.private(`App.Models.User.${userId}`)
    .notification((notification) => {
        switch (notification.type) {
            case 'App\\Notifications\\UserRepliedToMessage':
                // notification.message_id, notification.conversation_id, notification.replier_id
                break;
            case 'App\\Notifications\\PingUser':
                // notification.user (who tagged you), notification.conversation
                break;
        }
    });
```

**Payloads**

`UserRepliedToMessage`:

```json
{
  "id": "9b1d6f0e-...",
  "type": "App\\Notifications\\UserRepliedToMessage",
  "message_id": 42,
  "conversation_id": 7,
  "replier_id": 3
}
```

`PingUser`:

```json
{
  "id": "4c2a8e1b-...",
  "type": "App\\Notifications\\PingUser",
  "user": 3,
  "conversation": 7
}
```

- `user` — numeric ID of the user who tagged you
- `conversation` — numeric ID of the conversation you were tagged in

---

## Real-time Events

Events are broadcast on private channels. Authenticate channels with a bearer token via `POST /api/broadcasting/auth`.

**Channels**

| Channel                        | Who can join                                  |
|--------------------------------|-----------------------------------------------|
| `private-user.{id}`            | The user with that numeric ID                 |
| `private-conversation.{id}`    | Members of that conversation                  |
| `private-App.Models.User.{id}` | The user with that numeric ID ([notifications](#real-time-notifications)) |

**Events**

| Event          | Channel(s)                                        | Triggered by              | Payload                            |
|----------------|---------------------------------------------------|---------------------------|------------------------------------|
| `GroupCreated` | `user.{id}` for every member                      | Creating a conversation   | `conversation`                     |
| `MessageSent`  | `conversation.{id}`                               | Sending a message         | `message`                          |
| `MessageDelivered` | `conversation.{id}`                           | [Opening a conversation](#get-conversation-with-messages) | `conversationId`, `user`, `messageIds` |
| `MessageRead`  | `conversation.{id}`                               | [Getting a message](#get-message) | `conversationId`, `user`, `messageId` |
| `UserAdded`    | `conversation.{id}` + `user.{id}` of each added   | Adding or restoring users | `conversation`, `userIds`          |
| `UserDeleted`  | `conversation.{id}` + `user.{id}` of each removed | Removing users            | `conversationId`, `userIds`        |
| `MessageUpdated` | `conversation.{id}`                             | [Updating a message](#update-message) | `message`              |
| `MessageDeleted` | `conversation.{id}`                             | [Deleting a message](#delete-message) (soft delete) | `message` (includes `deleted_at`) |
| `MessageRestored` | `conversation.{id}`                            | [Restoring a message](#restore-message) | `message`             |
| `MessageDeletedForever` | `conversation.{id}`                      | [Permanently deleting a message](#permanently-delete-message) | `message` |
| `user.typing`  | `conversation.{id}`                               | [Typing indicator](#typing-indicator) endpoint | `conversationId`, `userId`, `name` |
| `user.stopped.typing` | `conversation.{id}`                        | [Stopped typing](#typing-indicator) endpoint | `conversationId`, `userId`, `name` |
| `user.online`  | `conversation.{id}` for every conversation the user belongs to | [Registering](#register) or [logging in](#login) | `user_id`, `name`, `friend_id` |
| `user.offline` | `conversation.{id}` for every conversation the user belongs to | [Logging out](#logout) | `user_id`, `name`, `friend_id`, `last_seen_at` |

Events are sent to everyone **except** the user who triggered them. The exceptions are `GroupCreated`, `MessageRestored`, `user.online` and `user.offline`, which go to all members.

Events with a dotted name (`user.typing`, `user.stopped.typing`, `user.online`, `user.offline`) are custom names, so listen for them with a leading dot, e.g. `.listen('.user.online', ...)`. The others use their class name, e.g. `.listen('MessageUpdated', ...)`.

**Delivered and read receipts**

1. When a user opens a conversation, the other members receive `MessageDelivered`. `messageIds` lists the messages (sent by others) that have just been delivered to `user`.
2. As the user reads each message, the client calls [`GET /message/{id}`](#get-message), which marks it as read and sends `MessageRead` with that `messageId`. Since read is tracked as the last read message, every earlier message in the conversation can be shown as read too.

```js
Echo.private(`conversation.${conversationId}`)
    .listen('MessageDelivered', (e) => { /* e.user, e.messageIds */ })
    .listen('MessageRead', (e) => { /* e.user, e.messageId */ });
```

---

## Enums

| Enum                  | Values                        |
|-----------------------|-------------------------------|
| Message type          | `text`, `image`, `file`       |
| Conversation type     | `direct_convo`, `group_convo` |
| Conversation role     | `owner`, `admin`, `member`    |
| Attachment collection | `avatar`, `attachment`        |
| Avatar image types    | `image/png`, `image/jpeg`     |

---

## Response Objects

Fields marked *optional* only appear when the relation is loaded or the condition is met.

### User

```json
{
  "id": 5,
  "name": "Jane Doe",
  "email": "jane@example.com",
  "friend_id": "jane-doe-1234",
  "avatar": {},
  "last_seen_at": "2026-09-27T10:00:00Z",
  "conversations": [],
  "created_conversations": []
}
```

- `id` — numeric user ID, used in request bodies such as `users` and `add_users` (URLs use `friend_id`)
- `avatar` — *optional*, an [Attachment object](#attachment)
- `last_seen_at` — *optional*, only shown when the user is offline
- `conversations`, `created_conversations` — *optional*

### Conversation

```json
{
  "id": 7,
  "type": "group_convo",
  "name": "Friends",
  "last_message": {},
  "created_by": {},
  "members": [],
  "messages": []
}
```

- `name` — `"direct_conversation"` for direct conversations
- `last_message`, `created_by`, `members`, `messages` — *optional*

### Message

```json
{
  "id": 42,
  "conversation_id": 1,
  "conversation_more_information": {},
  "sender": {},
  "reply_to": {},
  "replies": [],
  "type": "text",
  "body": "Hello!",
  "is_pinned": false,
  "edited_at": "2026-09-27T10:05:00Z"
}
```

- `body` — the text for `text` messages, or a list of [Attachment objects](#attachment) for `image`/`file` messages
- `conversation_more_information` — *optional*, only shown to conversation admins/owner
- `sender`, `reply_to`, `replies` — *optional*
- `is_pinned` — whether the message is pinned in its conversation (see [Pin Message](#pin-message))
- `edited_at` — *optional*, only shown if the message was edited

### Attachment

```json
{
  "attachable_type": "App\\Models\\Message",
  "attachable_id": 10,
  "collection": "attachment",
  "file_name": "abc123.png",
  "mime_type": "png",
  "size": 20480,
  "path": "attachments/abc123.png"
}
```

- `path` — relative to the storage disk; see [Attachments](#attachments) for how to load it

---

## Running the Backend
- Run the docker with redis first.
- open the port for it on windows defender
```sh
php artisan migrate               # includes the notifications table
php artisan storage:link          # serves avatars from /storage
php artisan serve                 # serve normally. dont use --port
php artisan queue:work            # broadcasts + notifications
php artisan reverb:start --debug
php artisan schedule:work
ngrok start --all
```
