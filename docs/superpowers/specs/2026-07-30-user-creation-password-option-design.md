# New User Modal — Optional Manual Password — Design Spec

**Date:** 2026-07-30

## Overview

The New User modal (added in [admin-creates-user](2026-07-30-admin-user-creation-design.md)) always sends a password-setup email link and never lets the Admin set a password directly. This spec adds a second path: the Admin can type a password for the new user instead, in which case no email is sent.

## Goals

- Keep the existing default: create user → email a password-setup link → no password known to the Admin.
- Add an explicit alternate path: Admin sets the password directly at creation time → no email sent.
- Both paths coexist behind an explicit choice in the same modal (no separate page/route).

## Non-goals

- No change to `Password::sendResetLink` / Breeze reset-password flow — untouched for the email path.
- No password-strength UI (meter, hints) beyond server-side `Rules\Password::defaults()` validation errors surfaced inline.
- No retroactive "convert an emailed user to a password user" action — this only affects creation.

## Architecture

**UI** (`resources/views/settings/users/index.blade.php`, New User modal):
- Two radio buttons, "Email setup link" (checked by default) and "Set password now", placed above the existing helper text.
- Selecting "Set password now" reveals `Password` and `Confirm Password` inputs (hidden via inline `style="display:none"` per the Tailwind-JIT gotcha, toggled by JS — not a Tailwind utility class); selecting "Email setup link" hides them again and clears their values.
- The helper text under the fields switches between "The new user will receive an email with a link to set their own password." and "The password below will be set immediately — no email will be sent."
- `openNewUserModal()` resets the radio to "Email setup link" and clears/hides the password fields, matching how it already clears name/email/roles.
- `createUser()` reads the checked radio. If "Set password now": include `mode: 'password'`, `password`, `password_confirmation` in the JSON body, and render server-side field errors for `password` the same way `name`/`email` errors are rendered today (a `<p id="new-user-password-error">` under the field). If "Email setup link": send `mode: 'email'` (or omit `mode`) with no password fields, unchanged from today.

**Backend** (`UserManagementController::store`):
- Validate `mode` as `['nullable', 'in:email,password']`, defaulting to `'email'`.
- When `mode === 'password'`: additionally validate `password` (`required`, `confirmed`, `Rules\Password::defaults()`).
- Branch on `mode`:
  - `'password'`: create the user with the submitted password (hashed via the model's `password` cast, same as today's `Str::random(40)` path) and `email_verified_at => now()`. Do **not** call `Password::sendResetLink`. Response message: `"{name} created."`
  - `'email'` (default, current behavior): unchanged — random password, `Password::sendResetLink`, message `"{name} created. A password-setup email has been sent."`
- `email_verified_at` is only auto-set on the `'password'` branch. The `'email'` branch leaves it `null`, as today — the user verifies implicitly by using the mailed link (Breeze's reset-password flow doesn't itself verify email, but this matches current behavior and is out of scope to change here).

## Why auto-verify on the password path

`User` does not currently implement `MustVerifyEmail`, so the `verified` middleware is a no-op today — this isn't about avoiding a verification wall that doesn't exist yet. Setting `email_verified_at` at creation records that the Admin vouched for the address at creation time, and pre-empts a future problem: if `User` is ever made to implement `MustVerifyEmail` (turning on real enforcement), password-path users will already be correctly marked verified, while email-path users (whose `email_verified_at` stays null — Breeze's reset-password flow doesn't set it) would need a backfill before enforcement could safely apply to them.

## Data Flow & Error Handling

- Duplicate email: unchanged, 422 with field error on `email`.
- `mode: 'password'` with mismatched/weak password: 422 with field error(s) on `password`, shown under the password field the same way name/email errors are shown.
- `mode: 'password'` success: user created, verified, no email sent, JSON response as above; frontend closes the modal, toasts the message, and reloads the row list — same as the existing success path.

## Testing

- Feature test: Admin creates a user with `mode: 'password'` — asserts user exists, password matches (`Hash::check`), `email_verified_at` is set, and `Password::sendResetLink` was **not** called.
- Feature test: Admin creates a user with `mode: 'password'` and a weak/mismatched password — asserts 422 with a `password` validation error.
- Existing `mode: 'email'` (or omitted `mode`) test from the prior feature continues to pass unchanged.
- No frontend test suite for Blade views (established convention) — modal verified manually.
