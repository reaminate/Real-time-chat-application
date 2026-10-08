# RTC App — API Documentation

A real-time chat API built with Laravel. It supports direct and group conversations, text/image/file messages, replies, pinned messages, likes and dislikes on messages, reply and reaction notifications, tagging users, typing indicators, delivered/read receipts, and real-time broadcasting over private channels.

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
| `429`  | Too many requests ([reactions](#reaction-rate-limit) only) |
| `500`  | Server or database error                              |

**Error body formats**

`401` / `403` / `404` / `500`:

```json
{ "message": "This action is unauthorized." }
```

`422` (validation, including login/register rate limiting and wrong password):

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

`429` (see [Reaction Rate Limit](#reaction-rate-limit)):

```json
{ "message": "too many reactions in 1 minute" }
```

Two endpoints return their own error bodies: [Update Conversation](#update-conversation) and [Remove Users](#remove-users) (`{ "error": "..." }`), and [Update User](#update-user) (`422` with an empty body).

**Response wrapping**

- List endpoints wrap their results in `data`.
- Paginated lists also include `links` and `meta`, in one of two styles.
- Page-paginated lists (`GET /user`) take `?page=`. Keep requesting the next page until `links.next` is `null`:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": "https://.../api/user?page=1", "last": null, "prev": null, "next": "https://.../api/user?page=2" },
  "meta": { "current_page": 1, "from": 1, "path": "https://.../api/user", "per_page": 20, "to": 20 }
}
```

- Cursor-paginated lists (`GET /message`) take `?cursor=`. Pass `meta.next_cursor` to get the next page:

```json
{
  "data": [ { "...": "..." } ],
  "links": { "first": null, "last": null, "prev": null, "next": "https://.../api/message?cursor=eyJ..." },
  "meta": { "path": "https://.../api/message", "per_page": 10, "next_cursor": "eyJ...", "prev_cursor": null }
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

Searches users by name (excludes yourself), ordered by name descending. Each user includes their `avatar`. If `search` is omitted, all users are returned. [Page-paginated](#general), 20 per page.

| Query Param | Type   | Required | Rules                          |
|-------------|--------|----------|--------------------------------|
| `search`    | string | No       | Letters and spaces only        |
| `page`      | int    | No       | Page number, defaults to `1`   |

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

You can only update yourself. All fields are optional, but changing your password requires your current `password`. Broadcasts [`UserUpdatedInfo`](#real-time-events) to your conversations.

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

You can only delete yourself. Broadcasts [`UserDeletedForever`](#real-time-events) to every conversation you were in.

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
| View, list members, send typing indicator, view pinned | Any active member  |
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

| Query Param  | Type | Required | Description                                                    |
|--------------|------|----------|----------------------------------------------------------------|
| `created_by` | flag | No       | Include the creator as `created_by`                            |

Flags only need to be present, e.g. `?created_by`. To get the members, use [List Members](#list-members).

**Responses**

| Status | When                                                                                     |
|--------|------------------------------------------------------------------------------------------|
| `200`  | [Conversation object](#conversation) with `messages`, plus `created_by` if requested     |
| `401`  | Not logged in                                        |
| `403`  | You are not an active member                         |
| `404`  | Conversation not found                               |

---

### List Members

`GET /conversation/{id}/members_in` 🔒

Returns the conversation with its members. What you get depends on your role:

| Role                | Field         | Contains                                                                                                   |
|---------------------|---------------|------------------------------------------------------------------------------------------------------------|
| `admin` / `member`  | `members`     | Active members only                                                                                        |
| `owner`             | `all_members` | Active members **and** removed users who haven't been permanently removed. Removed users have a `left_at` |

The owner uses `all_members` to find users to [restore](#restore-removed-users) or [permanently remove](#permanently-remove-users): those are the ones with `left_at`. The owner's response has no `members` field.

**Responses**

| Status | When                                                     |
|--------|----------------------------------------------------------|
| `200`  | [Conversation object](#conversation)                     |
| `401`  | Not logged in                                            |
| `403`  | You are not an active member                             |
| `404`  | Conversation not found                                   |

```json
{
  "id": 7,
  "type": "group_convo",
  "name": "Friends",
  "all_members": [
    { "id": 1, "name": "Owner", "...": "User object" },
    { "id": 5, "name": "Jane Doe", "...": "User object", "left_at": "2026-09-30" }
  ]
}
```

---

### Update Conversation

`PUT /conversation/{id}` / `PATCH /conversation/{id}` 🔒

All fields are optional. Broadcasts [`ConversationUpdated`](#real-time-events) to every member.

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

Broadcasts [`ConversationDeleted`](#real-time-events) to every member.

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

Marks users as having left. If 1 or fewer members remain the conversation is deleted; if exactly 2 remain it becomes a direct conversation. Broadcasts [`UserDeleted`](#real-time-events), and also [`ConversationDeleted`](#real-time-events) if the conversation was deleted.

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
| Like, dislike         | Active members of the conversation               |
| Remove a reaction     | Your own: anyone who has reacted. Someone else's: a conversation `owner` or `admin` |

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

Always includes the sender (with avatar), `liked_users` and `disliked_users`. Attachments are included for `image`/`file` messages.

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

### Reaction Rate Limit

[Like](#like-message), [Dislike](#dislike-message) and [Remove Reaction](#remove-reaction) share a limit of **30 requests per minute per user**. All three endpoints count towards the same limit, across all messages. For example, 20 likes followed by 10 dislikes on different messages uses up the whole minute. Other message endpoints are not affected.

Once the limit is reached, the reaction endpoints return `429` until the minute is up:

```json
{ "message": "too many reactions in 1 minute" }
```

Every reaction response includes these headers:

| Header                  | Meaning                                         |
|-------------------------|-------------------------------------------------|
| `X-RateLimit-Limit`     | `30`                                            |
| `X-RateLimit-Remaining` | Requests left in the current minute             |
| `Retry-After`           | Only on `429`: seconds until you can react again |

---

### Like Message

`POST /message/{id}/like` 🔒

Likes the message. Each user has at most one reaction per message, so liking a message you disliked switches it to a like, and liking a message you already liked leaves the stored reaction unchanged. No body.

Every call broadcasts [`UserReacted`](#real-time-events) to the conversation and sends a [`UserReactedToYourMessage`](#notifications) notification to the sender (not when reacting to your own message), including when you repeat the same reaction.

**Responses**

| Status | When                                                                                  |
|--------|---------------------------------------------------------------------------------------|
| `200`  | Liked; returns the [Message object](#message) with `liked_users` and `disliked_users` |
| `401`  | Not logged in                                                                         |
| `403`  | You are not an active member of the conversation                                      |
| `404`  | Message not found                                                                     |
| `429`  | [Reaction rate limit](#reaction-rate-limit) reached                                   |

---

### Dislike Message

`POST /message/{id}/dislike` 🔒

Same as [Like Message](#like-message), but dislikes it. Disliking a message you liked switches it to a dislike.

**Responses**

| Status | When                                                                                     |
|--------|------------------------------------------------------------------------------------------|
| `200`  | Disliked; returns the [Message object](#message) with `liked_users` and `disliked_users` |
| `401`  | Not logged in                                                                            |
| `403`  | You are not an active member of the conversation                                         |
| `404`  | Message not found                                                                        |
| `429`  | [Reaction rate limit](#reaction-rate-limit) reached                                      |

---

### Remove Reaction

`DELETE /message/{id}/remove_reaction` 🔒

Removes a like or dislike. Without a body it removes your own reaction. A conversation `owner` or `admin` can pass `user_id` to remove someone else's.

If a reaction was removed, this broadcasts [`UserUnreacted`](#real-time-events) to the conversation. An admin removing a reaction that doesn't exist gets `200` with no broadcast.

| Field     | Type | Required | Rules                                                                       |
|-----------|------|----------|-----------------------------------------------------------------------------|
| `user_id` | int  | No       | Existing user ID. Defaults to you. Only an `owner` or `admin` can pass someone else |

**Responses**

| Status | When                                                                                  |
|--------|---------------------------------------------------------------------------------------|
| `200`  | Removed; returns the [Message object](#message) with `liked_users` and `disliked_users` |
| `401`  | Not logged in                                                                         |
| `403`  | You haven't reacted to this message, or you passed someone else's `user_id` without being an `owner` or `admin` |
| `404`  | Message not found                                                                     |
| `422`  | `user_id` is not an existing user                                                     |
| `429`  | [Reaction rate limit](#reaction-rate-limit) reached                                   |

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
| `UserReactedToYourMessage` | The sender of the message              | [Liking](#like-message) or [disliking](#dislike-message) a message, on every call including repeats (not sent when reacting to your own message) |

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
            case 'App\\Notifications\\UserReactedToYourMessage':
                // notification.message_id, notification.conversation_id, notification.reactor_id, notification.liked
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

`UserReactedToYourMessage`:

```json
{
  "id": "7e3f0a9c-...",
  "type": "App\\Notifications\\UserReactedToYourMessage",
  "message_id": 42,
  "conversation_id": 7,
  "reactor_id": 3,
  "liked": true
}
```

- `reactor_id` — numeric ID of the user who reacted
- `liked` — `true` for a like, `false` for a dislike

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
| `UserReacted`  | `conversation.{id}`                               | [Liking](#like-message) or [disliking](#dislike-message) a message (on every call, including repeats) | `message_id`, `user_id`, `liked`, `likes_count`, `dislikes_count` |
| `UserUnreacted` | `conversation.{id}`                              | [Removing a reaction](#remove-reaction) (only when one was removed) | `message_id`, `user_id`, `likes_count`, `dislikes_count` |
| `user.typing`  | `conversation.{id}`                               | [Typing indicator](#typing-indicator) endpoint | `conversationId`, `userId`, `name` |
| `user.stopped.typing` | `conversation.{id}`                        | [Stopped typing](#typing-indicator) endpoint | `conversationId`, `userId`, `name` |
| `user.online`  | `conversation.{id}` for every conversation the user belongs to | [logging in](#login) | `user_id`, `name`, `friend_id` |
| `user.offline` | `conversation.{id}` for every conversation the user belongs to | [Logging out](#logout) | `user_id`, `name`, `friend_id`, `last_seen_at` |
| `ConversationUpdated` | `user.{id}` for every member               | [Updating a conversation](#update-conversation) | `conversation` |
| `ConversationDeleted` | `user.{id}` for every member at the time of deletion | [Deleting a conversation](#delete-conversation), or [removing users](#remove-users) until 1 or fewer remain | `conversation`, `userIds` |
| `UserUpdatedInfo` | `conversation.{id}` for every conversation the user belongs to | [Updating a user](#update-user) | `user` |
| `UserDeletedForever` | `conversation.{id}` for every conversation the user belonged to | [Deleting a user](#delete-user) | `user`, `conversationIds` |

Events are sent to everyone **except** the user who triggered them. The exceptions are `GroupCreated`, `MessageRestored`, `ConversationUpdated`, `ConversationDeleted`, `user.online` and `user.offline`, which go to all members.

When a conversation is deleted by removing users, `ConversationDeleted` also reaches the users who were just removed (they receive `UserDeleted` as well).

Events with a dotted name (`user.typing`, `user.stopped.typing`, `user.online`, `user.offline`) are custom names, so listen for them with a leading dot, e.g. `.listen('.user.online', ...)`. The others use their class name, e.g. `.listen('MessageUpdated', ...)`.

**Delivered and read receipts**

1. When a user opens a conversation, the other members receive `MessageDelivered`. `messageIds` lists the messages (sent by others) that have just been delivered to `user`.
2. As the user reads each message, the client calls [`GET /message/{id}`](#get-message), which marks it as read and sends `MessageRead` with that `messageId`. Since read is tracked as the last read message, every earlier message in the conversation can be shown as read too.

```js
Echo.private(`conversation.${conversationId}`)
    .listen('MessageDelivered', (e) => { /* e.user, e.messageIds */ })
    .listen('MessageRead', (e) => { /* e.user, e.messageId */ });
```

**Reactions**

`UserReacted` and `UserUnreacted` carry the new totals, so the client can update the counts without fetching the message again. For `UserUnreacted`, `user_id` is the user whose reaction was removed, which may not be the user who made the request (an admin can remove someone else's).

```js
Echo.private(`conversation.${conversationId}`)
    .listen('UserReacted', (e) => { /* e.message_id, e.user_id, e.liked, e.likes_count, e.dislikes_count */ })
    .listen('UserUnreacted', (e) => { /* e.message_id, e.user_id, e.likes_count, e.dislikes_count */ });
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
  "left_at": "2026-09-30",
  "conversations": [],
  "created_conversations": []
}
```

- `id` — numeric user ID, used in request bodies such as `users` and `add_users` (URLs use `friend_id`)
- `avatar` — *optional*, an [Attachment object](#attachment)
- `last_seen_at` — *optional*, only shown when the user is offline
- `left_at` — *optional*, only shown in a conversation's member list for users who were removed from it
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
  "all_members": [],
  "messages": []
}
```

- `name` — `"direct_conversation"` for direct conversations
- `last_message`, `created_by`, `members`, `messages` — *optional*
- `all_members` — *optional*, only shown to the `owner` by [List Members](#list-members); active members plus removed users who haven't been permanently removed (those have `left_at`)

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
  "liked_users": [],
  "disliked_users": [],
  "edited_at": "2026-09-27T10:05:00Z"
}
```

- `body` — the text for `text` messages, or a list of [Attachment objects](#attachment) for `image`/`file` messages
- `conversation_more_information` — *optional*, only shown to conversation admins/owner
- `sender`, `reply_to`, `replies` — *optional*
- `is_pinned` — whether the message is pinned in its conversation (see [Pin Message](#pin-message))
- `liked_users`, `disliked_users` — *optional*, lists of [User objects](#user). Returned by [Get Message](#get-message) and the [reaction endpoints](#like-message)
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
php artisan storage:link          
php artisan serve                 # serve normally. dont use --port
php artisan queue:work            # broadcasts + notifications
php artisan reverb:start --debug
php artisan schedule:work
ngrok start --all
```
