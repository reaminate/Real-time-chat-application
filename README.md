# RTC App — API Documentation

A real-time chat API built with Laravel. It supports direct and group conversations, text/image/file messages, replies, typing indicators, and real-time broadcasting over private channels.

## Table of Contents

- [General](#general)
- [Authentication](#authentication)
- [Users](#users)
- [Conversations](#conversations)
- [Messages](#messages)
- [Attachments](#attachments)
- [Real-time Events](#real-time-events)
- [Enums](#enums)
- [Response Objects](#response-objects)

---

## General

**Base URL:** `/api`

**Headers**

| Header          | Value                  | Required                     |
|-----------------|------------------------|------------------------------|
| `Accept`        | `application/json`     | Yes                          |
| `Authorization` | `Bearer {access_token}`| On all protected routes      |
| `Content-Type`  | `multipart/form-data`  | When uploading files         |

All routes except `register`, `login`, and the signed attachment download require authentication via Laravel Sanctum.

**Common error responses**

| Status | Meaning                                                  |
|--------|----------------------------------------------------------|
| `401`  | Missing or invalid token                                 |
| `403`  | Authenticated, but not allowed to perform this action    |
| `404`  | Resource not found                                       |
| `422`  | Validation failed (response contains an `errors` object) |
| `500`  | Database update failed                                   |

---

## Authentication

### Register

`POST /register`

Creates a new user and returns an access token. Rate limited to 5 attempts per email + IP.

| Field      | Type   | Required | Rules                                                                              |
|------------|--------|----------|------------------------------------------------------------------------------------|
| `name`     | string | Yes      | Letters and spaces only                                                            |
| `email`    | string | Yes      | Valid email, must be unique                                                        |
| `password` | string | Yes      | Min 7 chars, letters, mixed case, numbers, must not appear in a known data leak    |
| `avatar`   | file   | No       | Image, `image/png` or `image/jpeg`, max 2 MB                                        |

**Response** `201 Created`

```json
{
  "message": "login successful",
  "user": { "...": "User object" },
  "access_token": "1|abc123...",
  "token_type": "bearer"
}
```

---

### Login

`POST /login`

Rate limited to 5 attempts per email + IP.

| Field      | Type   | Required | Rules                          |
|------------|--------|----------|--------------------------------|
| `email`    | string | Yes      | Must belong to an existing user |
| `password` | string | Yes      | —                              |

**Response** `200 OK`

```json
{
  "message": "login successful",
  "user": { "...": "User object" },
  "access_token": "2|xyz789...",
  "type": "bearer"
}
```

**Errors:** `422` with `error: wrong_password`, or `error: too many login attempts` + `try_again` (seconds).

---

### Logout

`GET /logout` 🔒

Revokes the current token and sets the user's `last_seen_at`.

**Response** `200 OK`

```json
{ "message": "logout successful" }
```

---

### Current User

`GET /me` 🔒

Returns the authenticated user with their `conversations` and `created_conversations` loaded.

**Response** `200 OK` — [User object](#user)

---

## Users

> Users are identified in URLs by their `friend_id` (a slug), **not** their numeric `id`.

### Search Users

`GET /user` 🔒

Searches users by name (excludes yourself). Cursor-paginated, 20 per page.

| Query Param | Type   | Required | Rules                    |
|-------------|--------|----------|--------------------------|
| `search`    | string | Yes      | Letters and spaces only  |
| `cursor`    | string | No       | Cursor for the next page |

**Response** `200 OK` — paginated list of [User objects](#user)

---

### Get User

`GET /user/{friend_id}` 🔒

Returns the user along with the conversations you share with them.

**Response** `200 OK` — [User object](#user)

---

### Update User

`PUT /user/{friend_id}` / `PATCH /user/{friend_id}` 🔒

You can only update yourself. All fields are optional, but changing your password requires your current `password`.

| Field          | Type   | Required                        | Rules                                                     |
|----------------|--------|---------------------------------|-----------------------------------------------------------|
| `name`         | string | No                              | Letters only                                              |
| `email`        | string | No                              | Valid email, must be unique                               |
| `new_password` | string | No                              | Min 7 chars, letters, mixed case, numbers, not leaked     |
| `password`     | string | Yes, if `new_password` is sent  | Your current password                                     |
| `avatar`       | file   | No                              | Image, `image/png` or `image/jpeg`, max 2 MB               |

**Response** `200 OK` (empty body)

**Errors:** `422` (empty body) if `new_password` is sent and `password` is incorrect.

---

### Delete User

`DELETE /user/{friend_id}` 🔒

You can only delete yourself.

**Response** `204 No Content`

---

### Active Users

`GET /active-users` 🔒

Returns users who are currently logged in. Cached for 60 seconds.

**Response** `200 OK`

```json
{
  "message": "currently_active_users",
  "users": [ { "...": "raw user model" } ]
}
```

---

## Conversations

**Roles:** each member has a role of `owner`, `admin`, or `member`. The creator is the `owner`.

| Action                                   | Who can do it       |
|------------------------------------------|---------------------|
| View, send typing indicator              | Any active member   |
| Update, add users, remove users          | `owner` or `admin`  |
| Delete, restore users, force-delete users| `owner` only        |

### List Conversations

`GET /conversation` 🔒

Returns all conversations you belong to, each with its `last_message`.

**Response** `200 OK` — list of [Conversation objects](#conversation)

---

### Create Conversation

`POST /conversation` 🔒

You are automatically added to `users`. With exactly one other user the conversation is a **direct** conversation; with more it is a **group**. Only one direct conversation can exist between two users.

| Field     | Type          | Required                         | Rules                                        |
|-----------|---------------|----------------------------------|----------------------------------------------|
| `users`   | array of int  | Yes                              | At least 1 other user; existing user IDs; no duplicates |
| `name`    | string        | Yes, if more than 2 users total  | Max 10 chars; ignored for direct conversations |

**Response** `201 Created` (empty body)

**Errors:** `422` — `A direct conversation with this user already exists.`

---

### Get Conversation with Messages

`GET /conversation/{id}/messages` 🔒

Returns the conversation with all its messages (newest first) and marks it as read for you.

| Query Param  | Type | Required | Description                         |
|--------------|------|----------|-------------------------------------|
| `created_by` | flag | No       | Include the creator                 |
| `members`    | flag | No       | Include the list of members         |

Flags only need to be present, e.g. `?members&created_by`.

**Response** `200 OK` — [Conversation object](#conversation)

---

### Update Conversation

`PUT /conversation/{id}` / `PATCH /conversation/{id}` 🔒

All fields are optional.

| Field              | Type         | Required | Rules                                                          |
|--------------------|--------------|----------|----------------------------------------------------------------|
| `name`             | string       | No       | Max 10 chars                                                   |
| `make_users_admin` | array of int | No       | Min 1; each must be an active member of this conversation      |
| `created_by`       | int          | No       | Existing user ID — transfers ownership to that user            |

**Response** `200 OK` (empty body)

---

### Delete Conversation

`DELETE /conversation/{id}` 🔒

**Response** `204 No Content`

---

### Add Users

`POST /conversation/{id}/users` 🔒

Adds users as `member`s. A direct conversation is turned into a group named `New Group`.

| Field       | Type         | Required | Rules                                                              |
|-------------|--------------|----------|--------------------------------------------------------------------|
| `add_users` | array of int | Yes      | Min 1; existing user IDs; not already active members; no duplicates |

**Response** `200 OK` (empty body)

---

### Remove Users

`DELETE /conversation/{id}/users` 🔒

Marks users as having left. If 1 or fewer members remain the conversation is deleted; if exactly 2 remain it becomes a direct conversation.

| Field          | Type         | Required | Rules                                              |
|----------------|--------------|----------|----------------------------------------------------|
| `delete_users` | array of int | Yes      | Min 1; each must be an active member; no duplicates |

**Response** `200 OK` (empty body), or `204 No Content` if the conversation was deleted.

---

### Restore Removed Users

`POST /conversation/{id}/restore` 🔒

Re-adds users who were previously removed from the conversation.

| Field   | Type         | Required | Rules                                                     |
|---------|--------------|----------|-----------------------------------------------------------|
| `users` | array of int | Yes      | Min 1; each must be a *removed* member; no duplicates     |

**Response** `200 OK`

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

| Field   | Type         | Required | Rules                                                     |
|---------|--------------|----------|-----------------------------------------------------------|
| `users` | array of int | Yes      | Min 1; each must be a *removed* member; no duplicates     |

**Response** `204 No Content`

---

### Typing Indicator

`POST /conversation/{id}/typing` 🔒

Broadcasts a `user.typing` event to other members. Throttled to once every 3 seconds per user per conversation. No body.

**Response** `204 No Content`

---

## Messages

| Action                | Who can do it                                    |
|-----------------------|--------------------------------------------------|
| Send                  | Members of the conversation                      |
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

**Response** `200 OK` — paginated list of [Message objects](#message)

---

### Send Message

`POST /message` 🔒

Use `multipart/form-data` when sending an attachment.

| Field             | Type   | Required                          | Rules                                                  |
|-------------------|--------|-----------------------------------|--------------------------------------------------------|
| `conversation_id` | int    | Yes                               | Existing conversation you are a member of              |
| `type`            | string | Yes                               | One of `text`, `image`, `file`                         |
| `body`            | string | Yes, if `type` is `text`          | —                                                      |
| `attachment`      | file   | Yes, if `type` is `image`/`file`  | Any file                                               |
| `reply_to`        | int    | No                                | ID of a message in the same conversation               |

**Response** `201 Created` (empty body)

---

### Get Message

`GET /message/{id}` 🔒

Always includes the sender and attachments.

| Query Param                | Type | Required | Description                                          |
|----------------------------|------|----------|------------------------------------------------------|
| `conversation_information` | flag | No       | Include the conversation (only shown to admins/owner) |
| `reply_to`                 | flag | No       | Include the message this one replies to              |
| `replies`                  | flag | No       | Include replies to this message                      |
| `attachments`              | flag | No       | Include attachments                                  |

**Response** `200 OK` — [Message object](#message)

---

### Update Message

`PUT /message/{id}` / `PATCH /message/{id}` 🔒

Text messages update their `body`; image/file messages replace their `attachment`.

| Field             | Type   | Required | Rules                                      |
|-------------------|--------|----------|--------------------------------------------|
| `conversation_id` | int    | Yes      | Existing conversation                      |
| `body`            | string | No       | Used for text messages                     |
| `attachment`      | file   | No       | Max 2 MB; used for image/file messages     |

**Response** `200 OK` (empty body)

---

### Delete Message

`DELETE /message/{id}` 🔒

Soft-deletes the message.

**Response** `204 No Content`

---

### Restore Message

`GET /message/{id}/restore` 🔒

Restores a soft-deleted message.

**Response** `200 OK` — [Message object](#message)

---

### Permanently Delete Message

`DELETE /message/{id}/force_delete` 🔒

**Response** `204 No Content`

---

## Attachments

### Download Attachment

`GET /attachments/{id}/download?expires=...&signature=...`

Downloads the file under its original name. This route does **not** use a bearer token — it is protected by a signature instead, so it can be used directly in `<img src>` or download links. Don't build this URL yourself; use the `download_url` from an [Attachment object](#attachment).

**Response** `200 OK` — the file

**Errors:** `403` if the signature is missing, invalid, or expired (after 30 minutes).

---

## Real-time Events

Events are broadcast on private channels. Authenticate channels via `/broadcasting/auth`.

**Channels**

| Channel                      | Who can join                        |
|------------------------------|-------------------------------------|
| `private-user.{id}`          | The user with that numeric ID       |
| `private-conversation.{id}`  | Members of that conversation        |

**Events**

| Event          | Channel(s)                                      | Triggered by                            | Payload                                   |
|----------------|-------------------------------------------------|-----------------------------------------|-------------------------------------------|
| `GroupCreated` | `user.{id}` for every member                    | Creating a conversation                 | `conversation`                            |
| `MessageSent`  | `conversation.{id}`                             | Sending a message                       | `message`                                 |
| `UserAdded`    | `conversation.{id}` + `user.{id}` of each added | Adding or restoring users               | `conversation`, `userIds`                 |
| `UserDeleted`  | `conversation.{id}` + `user.{id}` of each removed | Removing users                        | `conversationId`, `userIds`               |
| `user.typing`  | `conversation.{id}`                             | Typing indicator endpoint               | `conversationId`, `userId`, `name`        |

Events are sent to everyone **except** the user who triggered them (except `GroupCreated`, which goes to all members).

---

## Enums

| Enum                 | Values                                   |
|----------------------|------------------------------------------|
| Message type         | `text`, `image`, `file`                  |
| Conversation type    | `direct_convo`, `group_convo`            |
| Conversation role    | `owner`, `admin`, `member`               |
| Avatar image types   | `image/png`, `image/jpeg`                |

---

## Response Objects

Fields marked *optional* only appear when the relation is loaded or the condition is met.

### User

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "friend_id": "jane-doe-1234",
  "last_seen_at": "2026-09-27T10:00:00Z",
  "conversations": [],
  "created_conversations": []
}
```

- `last_seen_at` — *optional*, only shown when the user is offline
- `conversations`, `created_conversations` — *optional*

### Conversation

```json
{
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
  "conversation_id": 1,
  "conversation_more_information": {},
  "sender": {},
  "reply_to": {},
  "replies": [],
  "type": "text",
  "body": "Hello!",
  "edited_at": "2026-09-27T10:05:00Z"
}
```

- `body` — the text for `text` messages, or a list of [Attachment objects](#attachment) for `image`/`file` messages
- `conversation_more_information` — *optional*, only shown to conversation admins/owner
- `sender`, `reply_to`, `replies` — *optional*
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
  "download_url": "https://..."
}
```

- `download_url` — a signed URL valid for 30 minutes (see [Download Attachment](#download-attachment))
