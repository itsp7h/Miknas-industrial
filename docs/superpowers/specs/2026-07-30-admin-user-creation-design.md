# Admin-Creates-User + Close Public Registration — Design Spec

**Date:** 2026-07-30

## Overview

SteelERP currently has no way for an Admin to create employee accounts directly — the only path is Breeze's public `/register` page, open to anyone who finds the URL. This spec adds a "New User" action to the existing User Management page (`settings/users`, built in the prior access-control feature) and closes public registration, matching the reality that this is an internal ERP, not a self-signup product.

## Goals

- Let an Admin create a new user account (name, email, profile) from the Users page, without ever handling that person's password.
- The new user receives an email with a link to set their own password on first login.
- Public self-registration is no longer reachable.

## Non-goals

- No bulk/CSV user import — one user at a time, matching the page's existing single-user "Edit Access" pattern.
- No new password/token infrastructure — reuses Laravel's existing password-reset mechanism (Breeze) rather than building a separate invite-token system.
- No change to how *existing* users log in, reset forgotten passwords, or verify email — those Breeze flows are untouched.
- The new-user email is a system notification sent via the app's default mailer, not the per-domain `MailAccount` system used for supplier-facing communications (RFQ invites, LPO issuance) — that system exists for customer/supplier-facing email, not internal account setup.

## Architecture

- **UI**: a "New User" button on the existing Users page (`resources/views/settings/users/index.blade.php`) opens a modal styled like the existing "Edit Access" modal: `Name` and `Email` text inputs, plus the same profile checkboxes already used for editing access (no permission toggles here — those are set after creation via the existing Edit Access modal, keeping this modal focused on the one new concern: creating the account).
- **Backend**: `UserManagementController::store` (new method, alongside the existing `index`/`update`):
  1. Validates `name` (required), `email` (required, unique), `roles` (array, each must exist).
  2. Creates the `User` with `Hash::make(Str::random(40))` as the password — a random value nobody knows or can derive, making the account unusable until the person sets a real password.
  3. Assigns the submitted profile(s) via `syncRoles(...)`.
  4. Calls `Password::sendResetLink(['email' => $user->email])` — Laravel's built-in password-broker, which generates a reset token and sends Breeze's existing "Reset Password" email/notification. The recipient clicks through to Breeze's existing "set new password" page (already built, unmodified) and sets their own password — this is functionally a first-time setup link, reusing 100% of Breeze's existing reset-token/email/form code.
  5. Returns JSON (`{message, user: {...}}`) — the frontend appends the new user to the list and shows a success toast, per this project's AJAX-only convention.
- **Route**: `POST settings/users`, inside the same `role:Admin` middleware group as the rest of Settings and the existing `settings.users.*` routes.
- **Closing registration**: delete the `register` GET/POST route definitions from `routes/auth.php`. Remove the "Register" link from Breeze's guest/login layout (wherever it currently renders one). Visiting `/register` afterward 404s like any other undefined route.

## Data Flow & Error Handling

- If the submitted email is already taken, the backend returns a 422 with a field-level validation error (`email: "The email has already been taken."`), shown inline in the modal per the existing form-error pattern.
- If `Password::sendResetLink` fails (e.g. mail transport misconfigured), the user record is still created — an Admin can always trigger a normal "forgot password" resend later from the login page, so a transient mail failure doesn't strand the account in an unrecoverable state. The response still reports success for the user-creation part, since the account exists; a future improvement could surface a distinct warning if the email send specifically failed, but this spec doesn't add that (YAGNI — Laravel's password broker doesn't throw on send failure in a way that's straightforward to distinguish from "email doesn't exist," and over-engineering this distinction isn't worth it for an internal tool with a working "forgot password" fallback).
- Removing `/register` doesn't affect `/forgot-password` or `/reset-password` — those remain fully functional and are exactly what this feature reuses.

## Testing

- Feature tests for `UserManagementController::store`: Admin can create a user with a profile (asserts the user exists, has the role, and `Password::sendResetLink` was called — using `Notification::fake()` or `Password::shouldReceive()`-style faking, whichever fits the existing test conventions in this codebase); non-Admin gets 403; duplicate email gets 422.
- A test asserting `GET /register` and `POST /register` both return 404.
- No frontend test suite exists for Blade views in this codebase (established convention from the prior feature) — the modal is verified manually.
