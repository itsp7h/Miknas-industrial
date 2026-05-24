# Purchase Request Form Upgrade — Design Spec
**Date:** 2026-05-24

## Overview

Upgrade the New Purchase Request form (`/purchase/requests/create`) with three improvements:

1. **Urgency picker** — replace the free-text "Required Date / Urgency" field with an interactive popup using icon cards (no typing required).
2. **Dropdowns from DB** — "Project / Site Name" and "Location / Site" become searchable dropdowns backed by database tables.
3. **Settings admin section** — a new "Settings" sidebar entry where Admins manage the Locations, Projects, and Urgency Levels lists.

---

## 1. Database Tables

Three new lookup tables, each managed by Admins via Settings pages.

### `settings_locations`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string(255) | e.g. "BaFa - Hidd", "Sitra" |
| is_active | boolean | default true |
| timestamps | | |

### `settings_projects`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| name | string(255) | e.g. "Steel Tech Co WLL" |
| is_active | boolean | default true |
| timestamps | | |

### `settings_urgency_levels`
| column | type | notes |
|---|---|---|
| id | bigint PK | |
| label | string(100) | e.g. "Critical", "Urgent" |
| emoji | string(10) | e.g. "🚨", "⚡" |
| color_bg | string(20) | Tailwind/hex bg, e.g. "#fee2e2" |
| color_text | string(20) | e.g. "#dc2626" |
| subtitle | string(100) | e.g. "Today", "1–3 days" |
| sort_order | integer | display order |
| is_active | boolean | default true |
| timestamps | | |

**Seeded defaults for urgency levels:**
| label | emoji | bg | text | subtitle | order |
|---|---|---|---|---|---|
| Critical | 🚨 | #fee2e2 | #dc2626 | Today | 1 |
| Urgent | ⚡ | #ffedd5 | #ea580c | 1–3 days | 2 |
| Normal | 📋 | #fef9c3 | #ca8a04 | This week | 3 |
| Planned | 🗓️ | #f0fdf4 | #16a34a | Pick date | 4 |

---

## 2. Purchase Request Form Changes

### "Project / Site Name" field
- Changed from `<input type="text">` to a `<select>` populated from `settings_projects` (active only).
- Searchable via Select2 or a lightweight Alpine.js custom dropdown (consistent with project stack).
- Stores the project name string (not ID) in `purchase_requests.project_name` — no FK needed, keeps existing column unchanged.

### "Location / Site" field
- Changed from `<input type="text">` to a `<select>` populated from `settings_locations` (active only).
- Same approach as above.
- Stores name string in existing `purchase_requests.location` column.

### "Required Date / Urgency" field
- Replaced with a read-only display field that opens a floating popup on click.
- **Popup design:** 2×2 grid of icon cards (style B), each showing emoji, label, and subtitle.
- Dynamically rendered from `settings_urgency_levels` (active, ordered by `sort_order`).
- When "Planned" (or any urgency where `label = 'Planned'`) is selected, a date input appears inline within the popup.
- On selection, the field displays a colored pill: `🚨 Critical` or `🗓️ 14 Jun 2026`.
- The hidden `<input name="required_date_text">` is updated with the final value before form submission.
- "Planned" with a date stores: `Planned — 2026-06-14`.

---

## 3. Settings Section

### Sidebar
- New "Settings" nav item (gear icon ⚙️) visible only to **Admin** role, placed at the bottom of the sidebar above the profile link.
- Sub-items: Locations, Projects, Urgency Levels.

### Settings Controllers — `app/Http/Controllers/Settings/`
Three resource controllers (index + store + update + destroy, no show/edit pages — inline editing):
- `LocationController.php`
- `ProjectSettingController.php`
- `UrgencyLevelController.php`

### Settings Views — `resources/views/settings/`
Each list page has:
- A table of existing entries with inline Edit (click to edit in place) and Delete (modal confirmation, not `confirm()`).
- An "Add New" form at the top or in a slide-in panel.
- Active/inactive toggle.
- Urgency Levels page also shows a live preview of how each card looks in the popup.

### Routes
```
prefix('settings')->name('settings.')->middleware(['auth','verified','role:Admin'])
  GET/POST  locations         settings.locations.*
  GET/POST  projects          settings.projects.*
  GET/POST  urgency-levels    settings.urgency-levels.*
```

---

## 4. Models

- `App\Models\Settings\Location` → table `settings_locations`
- `App\Models\Settings\ProjectSetting` → table `settings_projects`
- `App\Models\Settings\UrgencyLevel` → table `settings_urgency_levels`

All have `$fillable`, `$casts` for `is_active` boolean, and a scope `active()`.

---

## 5. Edit Form Compatibility

The existing `edit.blade.php` for purchase requests must receive the same three dropdown datasets and render the same urgency popup with the previously saved value pre-selected.

---

## 6. Out of Scope

- No changes to the items table, approval flow, print view, or PDF.
- No FK relationship between `purchase_requests` and the settings tables — names are stored as strings for historical integrity.
- No API endpoints; all interactions are server-rendered + Alpine.js.
