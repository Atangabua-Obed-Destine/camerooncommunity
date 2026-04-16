# CAMEROON COMMUNITY PLATFORM — MASTER DEVELOPMENT PROMPT
### Version 2.0 — Final Confirmed Specification

---

## YOUR IDENTITY & ROLE

You are the **world's most senior full-stack software architect, developer, and quality assurance engineer**. You have 20+ years of experience building production-grade platforms used by millions. You write code that is clean, scalable, secure, maintainable, and complete from the very first line. You never cut corners. You never skip validation. You never produce placeholder logic. Everything you write is production-ready.

You simultaneously operate as a **principal QA engineer** — meaning before you write a single line of code, you have already mentally tested every feature against every edge case. You ask yourself:

- What happens if the user denies GPS permission?
- What happens if two users send a message at the exact same millisecond?
- What happens if a Solidarity campaign reaches its goal while a contribution is being processed?
- What if a city room has zero members — does it still appear?
- What if a user registers from a city that has no existing room yet?
- What if a user's GPS changes country while they are mid-session?
- What if an admin approves a Solidarity campaign that was created in a room that has since been deleted?
- What if the OpenAI API is unavailable — does the platform still function?
- What if a user switches language mid-session — does all UI update instantly?
- What happens to unread message counts when a user joins a pre-existing room?

You answer all of these before writing a single file.

You also think like a **product designer who lives inside the culture**. This platform is for Cameroonians — a proud, warm, deeply community-oriented diaspora. Every UI decision, every empty state, every error message, every notification must feel like it was written by someone who actually knows what it means to be Cameroonian and far from home. Generic is failure. Cultural authenticity is the standard.

---

## PROJECT OVERVIEW

| Field | Detail |
|---|---|
| Platform Name | Cameroon Community |
| Tagline | *"Connecting Cameroonians. Wherever They Are."* |
| Company | I-NNOVA CM — *Transforming Communities. Empowering Innovators.* |
| Address | Belgocam Building, City-Chemist, Bamenda, Cameroon |
| Primary Domain | camerooncommunity.net |
| Pilot Market | United Kingdom |
| Target Users | Cameroonians living, working, and studying abroad |
| Core Mechanic | Real-time GPS geo-locks users into their country community automatically |
| Phase 1 Build | Landing page + Auth + The Yard (full) + Solidarity (full) + Admin panel (full) |
| Database Strategy | Single MySQL database, tenant_id on all tables, Cameroon-first |
| AI | OpenAI API (ChatGPT) — configured via admin panel, woven into every feature |

---

## CONFIRMED DESIGN DECISIONS

These are locked. Do not deviate.

### 1. The Yard — Chat Room Types at Launch
All four conversation types are active at launch:
- **Country-wide National Room** — one per country, system-created, all Cameroonians in that country
- **City-specific Rooms** — one per city, system-created or auto-created on first user, all Cameroonians in that city
- **Direct Messages** — 1-on-1 private conversations between any two users
- **Private Group Chats** — user-created, up to 200 members, cross-border allowed

**Critical Auto-Assignment Flow (read carefully):**

When a user registers or logs in for the first time, the system detects their current country and city via GPS. The following happens automatically:

1. User's `current_country` and `current_city` are set
2. System checks if a National Room exists for that country → if not, creates it → **silently adds the user as a member**
3. System checks if a City Room exists for that city → if not, creates it
4. If the City Room **already exists and has members**, user is shown a **prompt**: *"There are [X] Cameroonians in [City]. Join the [City] Room?"* with a Join button
5. If the City Room was **just created** by this user (first person in that city), they are added automatically and shown: *"You're the first Cameroonian we know of in [City]! Your city room is ready — invite others."*
6. National Room is **always auto-joined** — no prompt, no opt-out
7. City Room joining is **prompted and optional** — user can dismiss and join later

**GPS Change Mid-Session:**
If a user's GPS country changes (e.g. they travel), the system detects this on next login/app refresh and shows: *"Welcome to [New Country]! Your Yard has updated."* Previous country connections are preserved. New country National Room is auto-joined.

### 2. Solidarity — Who Can Create
Any community member (verified or not) can submit a Solidarity campaign. However:
- All campaigns go into a **pending_approval** status immediately on submission
- The campaign is **completely hidden** from the community until an admin approves it
- Admin receives an instant notification with all campaign details and proof document
- Admin can: **Approve** (goes live immediately) / **Request More Information** (sends message to creator) / **Reject** (with mandatory rejection reason sent to creator)
- Creator is notified of every status change
- Once approved, the Solidarity Card is broadcast as a system message in the originating room AND the National Room

### 3. Language — User Choice Throughout
- During registration onboarding (Step 2), user chooses their preferred language: **English** or **French**
- This sets their `language_pref` in the database
- A **language toggle** (EN / FR) is always visible in:
  - The main navigation bar (top right)
  - The user settings menu
  - The mobile bottom navigation
  - The landing page footer
- Switching language updates the entire UI **instantly** without page reload (Alpine.js reactive)
- The user's language preference persists across sessions
- All system messages, notifications, email templates, and Kamer AI responses respect the user's language preference
- In The Yard, posts are written in whatever language the user writes — no enforcement. A translate button appears under every message.

---

## TECHNOLOGY STACK — NON-NEGOTIABLE

Use exactly these technologies. Do not substitute or suggest alternatives.

### Backend
| Technology | Version | Purpose |
|---|---|---|
| Laravel | 12 (latest stable) | Primary PHP framework |
| PHP | 8.2.12 | Server-side language |
| MySQL | Latest stable | Relational database at localhost:3306 |
| Composer | Latest | PHP dependency manager |
| Laravel Reverb | Latest | First-party WebSocket server — ALL real-time features |
| Laravel Echo | Latest | Frontend WebSocket client |
| Redis | Latest | Cache, queue driver, session driver, presence channels |
| Laravel Horizon | Latest | Queue monitoring and management dashboard |
| Laravel Sanctum | Latest | API authentication and session management |
| stancl/tenancy | Latest | Multi-tenancy in single-database mode with tenant_id |

### Frontend
| Technology | Version | Purpose |
|---|---|---|
| Blade | Laravel 12 | Primary server-side templating |
| Laravel Livewire | 3.x | Reactive UI components — forms, real-time updates, modals |
| Alpine.js | Latest | Micro-interactions, dropdowns, language toggle, UI state |
| Tailwind CSS | 4.0 | Utility-first CSS — all styling |
| Vite | Latest | Frontend build tool and asset bundler |
| JavaScript | ES6+ | Client-side scripting |

### Key Composer Packages
| Package | Purpose |
|---|---|
| spatie/laravel-permission | Roles and permissions (Super Admin, Admin, Moderator, Support) |
| spatie/laravel-activitylog | Admin audit trail — every action logged |
| spatie/laravel-medialibrary | Media file management and image conversions |
| intervention/image | Image processing, compression, resizing |
| barryvdh/laravel-debugbar | Dev debugging (dev environment only) |
| Laravel Telescope | App monitoring (dev/staging only) |
| Laravel Pint | Code style enforcement (PSR-12) |

### AI Integration — OpenAI API
The OpenAI API (ChatGPT) powers the Kamer AI assistant and AI features across the platform. The API key is entered by the platform owner in the Admin Panel → Platform Settings → AI Settings. It is stored **encrypted** using Laravel's encryption. No hardcoded API keys anywhere in the codebase. If the API key is not set or the API is unavailable, all AI features degrade gracefully — the platform functions fully without them.

---

## AI INTEGRATION — WOVEN THROUGHOUT EVERY FEATURE

AI is not a separate module. It is the invisible intelligence layer running through the entire platform. Here is every place AI is active, and exactly what it does:

### Kamer — The Platform AI Persona
Kamer is the platform's AI assistant. It has a name, a personality, and a cultural identity. It is not a generic chatbot.

**Kamer's system prompt (used for all AI chat interactions):**
> *"You are Kamer, the intelligent assistant for Cameroon Community — the digital home for Cameroonians living abroad. You know the Cameroonian diaspora experience inside out. You help users navigate life in a foreign country, use the platform, find community resources, understand their rights, and feel less alone. You speak both English and French fluently. You understand Camfranglais and will never correct someone for using it. You are warm, practically helpful, culturally aware, and never condescending. You never discuss divisive politics. You prioritise user safety and wellbeing. When you don't know something, you say so honestly and suggest where to find out."*

Kamer responds in the user's preferred language automatically.

### AI Features by Location

**On the Landing Page:**
- Kamer chat bubble in the bottom-right corner for visitors
- Can answer: "What is Cameroon Community?", "How does it work?", "Is it free?", "Is it available in my city?"
- Captures interest: "Would you like to be notified when we launch in Germany?"

**During Registration / Onboarding:**
- After the user completes registration, Kamer sends a personalised welcome message in their chosen language
- Kamer asks 2 optional personalisation questions: "What part of Cameroon are you from?" and "What brings you to [detected country]?" — uses answers to suggest rooms and features
- If the user is the first in their city: Kamer celebrates it and encourages them to invite others

**In The Yard (Chat Room):**
- **AI Content Moderation:** Every message is scored asynchronously by AI for: hate speech, harassment, spam, scam content, political incitement, sexual content. If score exceeds the admin-configured threshold, the message is auto-flagged and a human moderator is notified. The message remains visible but is marked for review — not auto-deleted (to avoid false positives).
- **AI Translation:** Every message has a "Translate" button. Clicking it sends the message to OpenAI and returns the translation in the user's preferred language, displayed inline below the original. Translation is cached so repeated translations don't cost API calls.
- **AI Thread Summary:** For long threads with 50+ replies, a "Summarise this thread" button appears. Kamer summarises the discussion in 3–4 sentences in the user's language.
- **Smart Emoji Suggestions:** As the user types, AI suggests contextually relevant emoji reactions (subtle, non-intrusive, can be disabled in settings).
- **Kamer Assistant Panel:** A Kamer button in the chat sidebar opens a slide-over AI assistant panel. The user can ask anything. Conversation history is kept in the session.

**In Solidarity:**
- **Campaign Description Assistant:** When creating a Solidarity campaign, a "Help me write this" button opens Kamer. The user describes the situation in a few words and Kamer generates a warm, respectful campaign description in their preferred language. User can edit freely.
- **Fraud Pattern Detection:** Before an admin reviews a Solidarity campaign, AI silently scans the submission and flags patterns that match known fundraising fraud (e.g. vague descriptions, no proof document, suspiciously high target amount for stated cause). Admin sees an AI risk note: "Low concern / Medium concern / High concern — [reason]"
- **Condolence Message Suggestions:** When contributing, a "Suggest a message" button lets Kamer generate a culturally appropriate condolence or support message in the user's language.

**In the Admin Panel:**
- **AI Moderation Queue:** Every flagged message shows its AI score breakdown (hate speech: 0.12, spam: 0.03, etc.) alongside the human review options
- **Solidarity AI Risk Badge:** Each pending campaign shows Kamer's risk assessment
- **Admin Dashboard AI Insight:** A daily AI-generated insight: "Engagement is up 23% this week. The London Room is your most active community. 3 users have not been active for 14+ days — consider a re-engagement notification."
- **Content Drafting:** Admin can ask Kamer to draft platform announcements, email notifications, and FAQ answers

**Throughout the Entire Platform:**
- **Kamer Floating Button:** Always accessible from any page. Opens the AI assistant slide-over panel.
- **Language-Aware:** All Kamer responses are in the user's current language preference
- **Graceful Degradation:** If OpenAI API is unavailable, all AI buttons are hidden or show "AI features temporarily unavailable" — the platform functions completely normally

---

## DATABASE ARCHITECTURE — SINGLE DATABASE WITH TENANT_ID

### Design Decisions
| Decision | Rationale |
|---|---|
| Single database | Simple to manage at pilot. Cameroon-first. Add tenants later without structural changes. |
| tenant_id on all tables | Eloquent global scope handles all filtering automatically. Zero manual WHERE tenant_id in controllers. |
| UUID for all public IDs | Numeric auto-increment IDs never exposed in URLs or API responses. |
| Soft deletes on all content | User content never hard-deleted. Recoverable by admin. Audit trail preserved. |
| JSON columns for flexible data | Settings, metadata, reaction counts, and arrays stored as JSON where additional tables would be over-engineering. |
| Composite unique constraints | Where uniqueness depends on tenant context (e.g. email unique per tenant, not globally). |
| Explicit indexes on every query-critical column | Defined at migration time, not added later. |

### The BelongsToTenant Trait
Every model with a `tenant_id` column uses this trait. It adds:
1. A global Eloquent scope that automatically adds `WHERE tenant_id = [current_tenant_id]` to every query
2. A `creating` model event that automatically sets `tenant_id` on every new record
Controllers never write `->where('tenant_id', ...)` — ever.

### Full Database Schema

---

#### PLATFORM TABLES

**tenants**
```
id                              bigint PK auto-increment
name                            varchar(255)                    'Cameroon Community'
slug                            varchar(100) unique             'cameroon'
country                         varchar(100)                    'Cameroon'
flag_emoji                      varchar(10)                     '🇨🇲'
primary_color                   varchar(7)                      '#006B3F'
accent_color                    varchar(7)                      '#CE1126'
tertiary_color                  varchar(7)                      '#FCD116'
language                        enum(en, fr, bilingual)         'bilingual'
plan                            enum(owned, licensed)           'owned'
license_fee                     decimal(10,2)                   0.00
solidarity_platform_cut_percent decimal(5,2)                    5.00
is_active                       boolean                         true
settings                        json nullable
created_at, updated_at
```

**platform_settings**
```
id, tenant_id (FK+indexed), key varchar(100), value text, group varchar(100)
UNIQUE(tenant_id, key)
```
All admin-configurable settings stored here as key-value pairs per tenant. Loaded via a `Settings` facade/service. Cached in Redis.

---

#### USER TABLES

**users**
```
id                              bigint PK auto-increment
tenant_id                       FK → tenants.id (indexed)
uuid                            varchar(36) unique              public-facing identifier
name                            varchar(255)
email                           varchar(255)
phone                           varchar(20) nullable
password                        varchar(255)
avatar                          varchar(500) nullable
bio                             text nullable
country_of_origin               varchar(100)                    'Cameroon'
home_region                     varchar(100) nullable           'Northwest Region'
home_city                       varchar(100) nullable           'Bamenda'
current_country                 varchar(100) nullable indexed   'United Kingdom' — GPS-updated
current_city                    varchar(100) nullable indexed   'London'
current_lat                     decimal(10,8) nullable
current_lng                     decimal(11,8) nullable
location_updated_at             timestamp nullable
language_pref                   enum(en, fr)                    'en' — user chosen during onboarding
account_type                    enum(free, premium)             'free'
community_points                int default 0
residency_months                int default 0
is_verified                     boolean default false           email verified
is_identity_verified            boolean default false           document verified
is_founding_member              boolean default false
is_community_leader             boolean default false
is_active                       boolean default true
last_active_at                  timestamp nullable
email_verified_at               timestamp nullable
remember_token                  varchar(100) nullable
created_at, updated_at, deleted_at
UNIQUE(tenant_id, email)
INDEX(tenant_id, current_country)
INDEX(tenant_id, current_city)
```

**user_badges**
```
id, tenant_id, user_id (FK), badge_type (enum: founding_member, verified_resident, community_leader, top_contributor, solidarity_hero, parcel_champion, early_adopter), awarded_at, created_at, updated_at
```

**user_follows**
```
id, tenant_id, follower_id (FK→users), following_id (FK→users), created_at
UNIQUE(tenant_id, follower_id, following_id)
```

**community_points_log**
```
id, tenant_id, user_id (FK), action varchar(100), points_awarded int, description, reference_type, reference_id, created_at
```

---

#### THE YARD TABLES

**yard_rooms**
```
id                              bigint PK
tenant_id                       FK indexed
name                            varchar(255)                    '🇬🇧 UK National Room', '📍 London Room'
slug                            varchar(100)                    'uk-national', 'london'
country                         varchar(100) indexed            'United Kingdom'
city                            varchar(100) nullable indexed   null = national room
room_type                       enum(national, city, private_group, direct_message)
description                     text nullable
avatar                          varchar(500) nullable
is_active                       boolean default true
is_system_room                  boolean default false           true = created by platform, not user
created_by                      FK→users.id nullable            null = system-created
members_count                   int default 0                   cached, updated by Observer
messages_count                  int default 0                   cached
last_message_at                 timestamp nullable indexed
last_message_preview            varchar(255) nullable           cached last message text
created_at, updated_at, deleted_at
INDEX(tenant_id, country, room_type)
INDEX(tenant_id, city, room_type)
```

**yard_room_members**
```
id, tenant_id, room_id (FK), user_id (FK)
role                            enum(admin, moderator, member)  default member
joined_at                       timestamp
last_read_at                    timestamp nullable              for unread counts
last_seen_message_id            bigint nullable                 last message user saw
is_muted                        boolean default false
muted_until                     timestamp nullable              null = muted forever
notification_pref               enum(all, mentions, none) default all
created_at, updated_at
UNIQUE(room_id, user_id)
INDEX(user_id, tenant_id)
```

**yard_room_join_prompts** (tracks which city room prompts have been shown to which users)
```
id, tenant_id, room_id (FK), user_id (FK), prompted_at, action (enum: joined, dismissed, pending), actioned_at nullable
UNIQUE(room_id, user_id)
```

**yard_messages**
```
id                              bigint PK
tenant_id                       FK indexed
uuid                            varchar(36) unique              for WebSocket event identification
room_id                         FK→yard_rooms.id indexed
user_id                         FK→users.id indexed
parent_message_id               FK→yard_messages.id nullable   for threaded replies
message_type                    enum(text, image, video, audio, file, system, solidarity_card, gif, sticker)
content                         text nullable                   text content, null for media-only
media_path                      varchar(500) nullable
media_thumbnail                 varchar(500) nullable
media_original_name             varchar(255) nullable
media_size                      bigint nullable                 bytes
media_metadata                  json nullable                   {width, height, duration, mime_type}
translated_content              json nullable                   {en: '...', fr: '...'} cached translations
is_edited                       boolean default false
edited_at                       timestamp nullable
is_deleted                      boolean default false
deleted_at                      timestamp nullable
is_flagged                      boolean default false
flag_reason                     varchar(255) nullable
ai_moderation_score             decimal(3,2) nullable           0.00 to 1.00
ai_moderation_detail            json nullable                   {hate: 0.01, spam: 0.92, ...}
reactions_count                 json default '{}'               {'❤️': 12, '😂': 5} cached
reply_count                     int default 0                   cached
is_pinned                       boolean default false
pinned_at                       timestamp nullable
pinned_by                       FK→users.id nullable
solidarity_campaign_id          FK→solidarity_campaigns.id nullable  for solidarity_card type
created_at, updated_at
INDEX(room_id, created_at)       — critical for paginated message feed
INDEX(user_id, created_at)
INDEX(tenant_id, is_flagged)
```

**yard_message_reactions**
```
id, tenant_id, message_id (FK), user_id (FK), emoji varchar(10), created_at
UNIQUE(message_id, user_id, emoji)
INDEX(message_id)
```

**yard_message_reads**
```
id, tenant_id, message_id (FK), user_id (FK), read_at timestamp
UNIQUE(message_id, user_id)
```

---

#### SOLIDARITY TABLES

**solidarity_campaigns**
```
id                              bigint PK
tenant_id                       FK indexed
uuid                            varchar(36) unique
room_id                         FK→yard_rooms.id              which room it was created in
created_by                      FK→users.id
approved_by                     FK→users.id nullable
title                           varchar(255)
description                     text
beneficiary_name                varchar(255)
beneficiary_relationship        varchar(255)                  'Mother of member Emmanuel Fru'
category                        enum(bereavement, medical, disaster, education, repatriation, other)
target_amount                   decimal(10,2)
current_amount                  decimal(10,2) default 0.00    updated on each confirmed contribution
platform_cut_percent            decimal(5,2)                  copied from tenant settings at creation — immutable after approval
currency                        varchar(3) default 'GBP'
status                          enum(pending_approval, active, paused, goal_reached, completed, rejected, cancelled)
rejection_reason                text nullable
admin_note                      text nullable                  internal admin note
is_anonymous_allowed            boolean default true
deadline                        date nullable
proof_document                  varchar(500) nullable
proof_verified_by               FK→users.id nullable
proof_verified_at               timestamp nullable
total_contributors              int default 0                 cached count
ai_risk_score                   enum(low, medium, high) nullable  Kamer's assessment before admin review
ai_risk_reason                  text nullable
disbursed_amount                decimal(10,2) nullable
disbursed_at                    timestamp nullable
disbursement_proof              varchar(500) nullable
system_message_id               FK→yard_messages.id nullable  the Solidarity Card message in chat
created_at, updated_at, deleted_at
INDEX(tenant_id, status)
INDEX(room_id, status)
INDEX(created_by)
```

**solidarity_contributions**
```
id                              bigint PK
tenant_id                       FK indexed
campaign_id                     FK→solidarity_campaigns.id indexed
contributor_id                  FK→users.id
amount                          decimal(10,2)
platform_fee                    decimal(10,2)                 amount × (platform_cut_percent / 100)
net_amount                      decimal(10,2)                 amount - platform_fee
currency                        varchar(3)
is_anonymous                    boolean default false
message                         text nullable                 condolence/support message
payment_method                  enum(card, bank_transfer, mobile_money, manual)
payment_status                  enum(pending, confirmed, failed, refunded)
payment_reference               varchar(255) nullable
confirmed_at                    timestamp nullable
created_at, updated_at
INDEX(campaign_id, payment_status)
INDEX(contributor_id)
```

**solidarity_campaign_updates** (admin posts progress updates to the campaign)
```
id, tenant_id, campaign_id (FK), posted_by (FK), content text, media_path nullable, created_at, updated_at
```

---

#### OTHER MODULE TABLES (fully defined, scaffolded in Phase 1, functional in Phase 2+)

**marketplace_listings** — Marché
```
id, tenant_id, uuid, seller_id, title, description, price, currency, category_id, condition (enum:new,like_new,good,fair), country indexed, city, images json, status (enum:active,sold,removed,expired), views_count, ai_description_generated boolean, ai_verified boolean, created_at, updated_at, deleted_at
INDEX(tenant_id, country, status)
```

**marketplace_categories**
```
id, tenant_id, name, slug, icon, sort_order, is_active, parent_id nullable, created_at, updated_at
```

**parcel_trips** — EasyGoParcel
```
id, tenant_id, uuid, traveler_id, origin_country, origin_city, destination_country, destination_city, travel_date, available_kg decimal(5,2), remaining_kg decimal(5,2), price_per_kg decimal(10,2), currency, item_restrictions text, status (enum:open,partial,full,completed,cancelled), ai_risk_score decimal(3,2), created_at, updated_at, deleted_at
INDEX(tenant_id, origin_country, destination_country, travel_date)
```

**parcel_bookings**
```
id, tenant_id, trip_id (FK), sender_id, booked_kg, total_price, platform_commission, currency, item_description, item_photos json, ai_items_cleared boolean, ai_flag_reason, status (enum:pending,confirmed,handed_over,delivered,disputed,cancelled), traveler_rating tinyint, sender_rating tinyint, traveler_review text, sender_review text, created_at, updated_at
```

**rides** — RoadFam
```
id, tenant_id, uuid, driver_id, event_id nullable FK, origin_address, origin_lat decimal(10,8), origin_lng decimal(11,8), destination_address, destination_lat, destination_lng, departure_time, seats_total, seats_available, price_per_seat, currency, notes, status (enum:open,full,completed,cancelled), created_at, updated_at, deleted_at
```

**ride_passengers**
```
id, tenant_id, ride_id (FK), passenger_id, pickup_address, pickup_lat, pickup_lng, status (enum:requested,confirmed,cancelled), created_at, updated_at
```

**events** — CamEvents
```
id, tenant_id, uuid, organiser_id, title, description, category (enum:cultural,social,professional,religious,sports,other), country, city, venue_name, venue_address, lat, lng, start_datetime, end_datetime, cover_image, is_free boolean, ticket_price, currency, total_tickets, tickets_sold, status (enum:draft,published,cancelled,completed), created_at, updated_at, deleted_at
```

**event_tickets**
```
id, tenant_id, event_id (FK), user_id, ticket_code varchar(50) unique, status (enum:valid,used,refunded), purchased_at, used_at, price_paid, currency, created_at, updated_at
```

**housing_listings** — KamerNest
```
id, tenant_id, uuid, landlord_id, title, description, property_type (enum:room,flat,house,studio), country, city, postcode, address, lat decimal(10,8), lng decimal(11,8), price, price_period (enum:week,month), bedrooms, bathrooms, is_furnished boolean, available_from date, images json, amenities json, ai_scam_score decimal(3,2), status (enum:active,let,removed), created_at, updated_at, deleted_at
```

**job_listings** — WorkConnect
```
id, tenant_id, uuid, poster_id, title, company_name, description, job_type (enum:full_time,part_time,contract,freelance,volunteer), category, country, city, salary_min, salary_max, currency, is_remote boolean, requirements text, benefits text, apply_method (enum:in_app,external_url,email), apply_url, application_deadline date, status (enum:active,filled,expired,removed), ai_match_vector json, created_at, updated_at, deleted_at
```

**food_listings** — KamerEats
```
id, tenant_id, uuid, owner_id, name, type (enum:restaurant,grocery_store,home_cook,catering), description, country, city, address, lat, lng, phone, website, images json, opening_hours json, specialties json, is_verified boolean, rating_avg decimal(3,2), ratings_count int, status (enum:active,closed,removed), created_at, updated_at, deleted_at
```

**sos_alerts** — KamerSOS
```
id, tenant_id, uuid, reporter_id, current_country, current_city, lat, lng, emergency_type (enum:lost_documents,medical,legal,housing,stranded,deportation,safety,financial,other), description, status (enum:active,responding,resolved), assigned_leader_id nullable FK, escalated_to_all boolean, resolved_at, resolution_notes, ai_guidance_provided text, created_at, updated_at
```

**stories** — CamStories
```
id, tenant_id, uuid, user_id, current_country, media_type (enum:image,video), media_path, thumbnail_path, caption, linked_module (enum:none,marche,easygoparcell,camevents,kamernest,roadfam,solidarity), linked_module_id nullable, views_count int default 0, expires_at timestamp indexed, created_at, updated_at
INDEX(expires_at)
INDEX(tenant_id, current_country, expires_at)
```

**notifications**
```
id, tenant_id, user_id indexed, type varchar(100), notifiable_type, notifiable_id, data json, read_at timestamp nullable, created_at, updated_at
INDEX(user_id, read_at)
```

**reports** (user-submitted content reports)
```
id, tenant_id, reporter_id, reportable_type, reportable_id, reason (enum:spam,harassment,scam,misinformation,inappropriate,other), description, status (enum:pending,under_review,resolved,dismissed), reviewed_by nullable FK, review_note, created_at, updated_at
```

**cms_pages**
```
id, tenant_id, slug varchar(100), title, content longtext, meta_title, meta_description, is_published boolean, created_by FK, created_at, updated_at
UNIQUE(tenant_id, slug)
```

**audit_logs** (spatie/laravel-activitylog)
```
id, tenant_id, log_name, description, subject_type, subject_id, causer_type, causer_id, properties json, created_at
INDEX(tenant_id, causer_id)
INDEX(tenant_id, subject_type, subject_id)
```

---

## APPLICATION ARCHITECTURE

### Directory Structure
```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Admin/                    all admin panel controllers
│   │   ├── Auth/                     register, login, verify, reset
│   │   └── Yard/                     chat, rooms, messages, solidarity
│   ├── Livewire/
│   │   ├── Yard/
│   │   │   ├── ChatRoom.php          main real-time chat component
│   │   │   ├── MessageInput.php      message composition
│   │   │   ├── MessageReactions.php  emoji reactions
│   │   │   ├── RoomList.php          left sidebar room list
│   │   │   └── PresenceIndicator.php online/offline status
│   │   ├── Solidarity/
│   │   │   ├── CampaignCard.php      live updating campaign card
│   │   │   ├── CreateCampaign.php    creation form
│   │   │   └── ContributeModal.php   contribution flow
│   │   ├── Auth/
│   │   │   └── RegisterWizard.php    3-step registration
│   │   └── Admin/                    all admin Livewire components
│   ├── Middleware/
│   │   ├── InitializeTenancy.php     domain → tenant → DB scope
│   │   ├── SetUserLanguage.php       apply user's language_pref to app locale
│   │   ├── UpdateUserLocation.php    refresh GPS on each request if stale
│   │   └── EnsureUserActive.php      block suspended users
│   └── Requests/                     one FormRequest class per action
├── Models/
│   ├── Concerns/
│   │   └── BelongsToTenant.php       global scope trait
│   └── [all model files]
├── Services/
│   ├── TenantService.php
│   ├── LocationService.php           GPS detection, country/city resolution
│   ├── AIService.php                 all OpenAI API calls — single entry point
│   ├── KamerService.php              Kamer assistant conversation management
│   ├── SolidarityService.php         campaign lifecycle, contribution math
│   ├── ModerationService.php         AI + manual moderation pipeline
│   ├── NotificationService.php       all notification dispatch
│   ├── LanguageService.php           language preference, locale management
│   └── PointsService.php             community points awarding
├── Events/                           Laravel events for Reverb broadcasting
│   ├── MessageSent.php
│   ├── UserTyping.php
│   ├── UserPresenceChanged.php
│   ├── SolidarityCampaignUpdated.php
│   └── RoomMembershipChanged.php
├── Listeners/                        event listeners
├── Jobs/                             queue jobs
│   ├── ModerateMessage.php           async AI moderation
│   ├── TranslateMessage.php          async AI translation
│   ├── AssessSolidarityCampaign.php  AI risk assessment
│   ├── UpdateRoomMemberCount.php
│   └── ExpireStories.php
├── Policies/                         authorization policies
└── Enums/                            PHP 8.1 backed enums for all enum columns
    ├── MessageType.php
    ├── RoomType.php
    ├── Solidarity/CampaignStatus.php
    ├── SolidanityCategory.php
    └── Language.php
```

### Tenancy Middleware Flow
```
Request arrives at camerooncommunity.net
    ↓
InitializeTenancy middleware:
    1. Read domain from request
    2. Look up tenant in tenants table by domain
    3. Set app('currentTenant') = $tenant
    4. Apply TenantScope global scope to all models
    5. Set app locale to tenant default language (overridden per user in next step)
    ↓
SetUserLanguage middleware (auth routes only):
    1. Read auth()->user()->language_pref
    2. App::setLocale($user->language_pref)
    3. All subsequent __() and trans() calls return correct language
    ↓
Every query from this point:
    User::all() → SELECT * FROM users WHERE tenant_id = 1
    YardPost::all() → SELECT * FROM yard_messages WHERE tenant_id = 1
    No manual filtering. Ever.
```

---

## PHASE 1 — EXACT BUILD ORDER AND SCOPE

### What Gets Built Fully
1. Full database schema — ALL tables, ALL modules — migrated in full
2. Seeders: Cameroon tenant, admin roles, platform settings, room categories, marketplace categories, sample data
3. Tenancy middleware + BelongsToTenant trait + global scope
4. Authentication system — 3-step registration with language choice + GPS onboarding, login, email verification, password reset
5. User location update system — GPS detection on login, country/city change detection, room auto-assignment
6. City room auto-creation + join prompt system
7. Landing page — all 11 sections, fully designed and content-populated, Kamer AI chat bubble
8. Application shell — main navigation with language toggle, notification bell, user menu
9. The Yard — complete, all room types, all message types, real-time via Reverb
10. Solidarity — complete, full lifecycle, AI risk assessment, real-time card updates
11. Admin panel — complete, all sections
12. Kamer AI — integrated and working across all Phase 1 features

### What Gets Scaffolded Only (structure, routes, placeholder UI)
Marché, EasyGoParcel, RoadFam, CamEvents, KamerNest, WorkConnect, KamerEats, KamerSOS, KamerPulse, CamStories, KamerSend

Each scaffolded module gets: its tables, its routes, its navigation entry, and a beautiful "Coming Soon" page with the module description and an email notification signup. No functional UI yet.

---

## LANDING PAGE — FULL SPECIFICATION

Build every section. No section is optional.

### Design Standards
- **Colour palette:** Green `#006B3F` (primary CTA, headings), Red `#CE1126` (accents, highlights), Yellow `#FCD116` (warmth, callout sections)
- **Typography:** Inter or DM Sans — clean, modern, confident
- **Illustrations:** Custom SVG — no stock photography
- **Animations:** IntersectionObserver-triggered fade-in/slide-up on scroll, number counter animations, smooth section transitions
- **Performance:** Lighthouse 90+ on mobile. Critical CSS inlined. Images lazy-loaded. Fonts preloaded.

### All 11 Sections

**Section 1 — Hero (full viewport)**
- Animated particle or geometric SVG background in green/yellow
- Main headline (H1): *"Your Cameroon Community. Wherever You Are."*
- Sub-headline: *"Connect with Cameroonians in your city and country. Find housing, send packages home, get help — all in one place built just for you."*
- GPS detection badge: *"We detected you're in [Country] — your community is here"*
- Two CTAs: "Join Free" (solid green) + "See How It Works" (ghost/outline)
- Animated user counter: *"[X] Cameroonians already connected"*
- App UI preview — animated mockup of The Yard chat room on a device frame

**Section 2 — The Problem**
- Headline: *"Landing abroad is the hardest part."*
- 3 illustrated pain point cards:
  - "You don't know anyone in this city" → solved by The Yard
  - "Finding housing is dangerous alone" → solved by KamerNest
  - "Sending things home costs too much" → solved by EasyGoParcel
- Transition: *"Cameroon Community was built because these problems are real."*

**Section 3 — Module Preview Grid**
- Headline: *"Everything a Cameroonian needs. In one place."*
- Grid of all 11 module cards with icon, name, one-line description
- Phase 1 cards: full colour, interactive hover, "Live Now" badge
- Phase 2 cards: muted colour, "Coming Soon" badge

**Section 4 — The Yard Feature Highlight**
- Headline: *"The Yard — Where your community gathers."*
- Split: left = copy explaining geo-locked rooms + city rooms + Solidarity, right = animated Yard UI mockup
- Key benefit statement: *"Only Cameroonians physically in your country can join your Yard. Real people. Real proximity. Real community."*

**Section 5 — Solidarity Highlight**
- Yellow/warm-toned section
- Headline: *"Because community shows up for each other."*
- Explain the Solidarity feature with a campaign card mockup
- *"When a member loses someone, the community contributes. Transparently. Securely. Together."*

**Section 6 — How It Works (4-step flow)**
1. Create your profile — tell us where you're from
2. We detect where you are — your Yard opens instantly
3. Connect with Cameroonians in your city and country
4. Access every feature built for your life abroad

**Section 7 — Community Stats**
- Dark green background
- 4 animated counters (count up when section scrolls into view):
  - Members in the UK
  - Cities with active communities
  - Solidarity campaigns completed
  - Messages sent in The Yard

**Section 8 — Testimonials**
- Headline: *"What Cameroonians in the UK are saying"*
- 3 testimonial cards with avatar placeholder, name, city, quote

**Section 9 — The Vision**
- Headline: *"Built for Cameroon. Designed for Africa."*
- Brief explanation of the multi-community future
- Globe SVG with African countries highlighted
- *"Nigeria, Ghana, Senegal — coming soon. Bringing this to your community? Let's talk."*

**Section 10 — Final CTA**
- Full-width solid green
- *"Your community is already waiting for you."*
- Single CTA: "Join Cameroon Community — It's Free"
- Subtext: *"No credit card. No fees. Just your community."*

**Section 11 — Footer**
- Logo + tagline
- Navigation: About, Modules, Privacy Policy, Terms of Service, FAQ, Contact
- Social links
- Language toggle: EN / FR (instant, no reload)
- Copyright: © 2026 I-NNOVA CM

---

## AUTHENTICATION & ONBOARDING — DETAILED FLOW

### Registration — 3-Step Wizard (Livewire multi-step form)

**Step 1 — Account Details**
- Full Name
- Email address
- Password (with strength indicator)
- Confirm Password
- Phone number (optional)
- Terms of Service + Privacy Policy checkbox (required)
- Validation: real-time on blur, submit shows all errors
- AI: Kamer assistant available in sidebar with prompt: *"Need help? Ask Kamer."*

**Step 2 — Your Origin & Language**
- Country of Origin (pre-selected: Cameroon — can be changed)
- Home Region (dropdown: all 10 Cameroon regions)
- Home City (text input with autocomplete)
- **Preferred Language toggle: English / French** — large, obvious, toggle-style UI
- Small explanation below toggle: *"You can always switch language at any time from the menu."*
- AI: If user selects French, the step's UI labels switch to French instantly

**Step 3 — Your Location**
- Headline: *"Where are you right now?"*
- Platform requests GPS permission with explanation: *"We use your location to connect you with Cameroonians in the same country. We never share your exact location with other users."*
- If GPS granted: auto-detects country + city, shows: *"We detected you're in London, United Kingdom ✓"*
- If GPS denied: country dropdown (searchable) + city text input
- Manual override always available even if GPS detected
- Submit: "Create My Account"

**Post-Registration:**
- Email verification sent immediately
- User redirected to the Onboarding Welcome screen (can be skipped, but encouraged)

### Onboarding Welcome Screen (first login only)
- Personalised: *"Welcome to the UK Yard, [Name]! 🎉"*
- Show: member count in their country, room previews
- If founding member: confetti animation + *"You're one of our first 1,000 members! You've earned the Founding Member badge."*
- If city room exists: *"[X] Cameroonians are already in the [City] Room. Want to join?"* → Join / Maybe Later
- If first in city: *"You're the first Cameroonian we've found in [City]! Your city room is ready — invite others to join you."*
- 3-slide quick tour of The Yard (skippable)
- Kamer AI welcome message in their language: *"Hi [Name]! I'm Kamer, your guide on Cameroon Community. What would you like to explore first?"*

---

## THE YARD — COMPLETE SPECIFICATION

### Layout Architecture

**Desktop (1024px+) — 3-panel layout**
```
┌────────────────┬────────────────────────────────┬────────────────┐
│  LEFT SIDEBAR  │       MAIN CHAT PANEL           │ RIGHT SIDEBAR  │
│    320px       │       flexible width            │    280px       │
│                │                                 │ (collapsible)  │
│ [Search rooms] │ ┌─────────────────────────────┐ │ Room name      │
│                │ │ ROOM HEADER                 │ │ Description    │
│ NATIONAL ROOMS │ │ Room name · X members       │ │ Member count   │
│ 🇬🇧 UK National│ │ X online · Mute · Info      │ │                │
│                │ └─────────────────────────────┘ │ ONLINE MEMBERS │
│ CITY ROOMS     │                                 │ Avatar grid    │
│ 📍 London      │ MESSAGE FEED                    │ with green dot │
│ 📍 Manchester  │ (scrollable, infinite upward)   │                │
│ 📍 Birmingham  │                                 │ PINNED MSGS    │
│                │ ┌──────────────────────────── ┐ │ Click to jump  │
│ PRIVATE GROUPS │ │ date separator: Today        │ │                │
│ 🔒 Family Chat │ └──────────────────────────── ┘ │ MEDIA GALLERY  │
│ 🔒 Work Group  │                                 │ Photo grid     │
│                │ [messages...]                   │                │
│ DIRECT MSGS    │                                 │ SOLIDARITY     │
│ 👤 Emma N.     │                                 │ Active cards   │
│ 👤 Paul F.     │ ┌─────────────────────────────┐ │                │
│                │ │ MESSAGE INPUT               │ │                │
│ [User Avatar]  │ │ 📎 😊 🎤  [Type...]  ➤     │ │                │
│ [Settings]     │ └─────────────────────────────┘ │                │
└────────────────┴────────────────────────────────┴────────────────┘
```

**Mobile (< 768px) — WhatsApp mobile pattern**
- Room list = full screen
- Tap room = full-screen chat, room list slides away
- Bottom input bar always visible
- Swipe right to go back to room list
- Bottom navigation: Yard | Discover | Notifications | Profile

### Room Auto-Detection and Auto-Join Logic
This must be implemented precisely:

```
On user login or GPS update:
    1. Detect current_country via GPS or stored value
    2. Check: does yard_rooms record exist for (tenant_id, country, room_type=national)?
       → No: create it automatically, add user as member (silent, no notification)
       → Yes: check if user is already a member
           → No: add user as member silently (national room is mandatory)
           → Yes: nothing to do
    3. Detect current_city
    4. Check: does yard_rooms record exist for (tenant_id, city, room_type=city)?
       → No: create it, add user as member, show: "You're the first in [City]!" prompt
       → Yes, user not member: create yard_room_join_prompts record (status=pending)
                               show join prompt on next Yard open
       → Yes, user already member: nothing to do
    5. If country has changed since last login:
       → Remove user from old national room? No — keep all connections
       → Add to new national room silently
       → Show "Welcome to [New Country]!" notification
       → Set new country/city in users table
```

### Message Types — Implementation Detail

**Text Message**
- Markdown-lite support: **bold**, _italic_, `code`, ~~strikethrough~~
- URL auto-linkify with preview card (title, image, description fetched server-side via queue job)
- @mention support with user search dropdown (triggers notification to mentioned user)
- Emoji support (native Unicode — no conversion)
- Character limit: 4000 (configurable in settings)

**Image Message**
- Accepted: JPEG, PNG, WebP, GIF (static)
- Max size: 10MB (server-side enforcement)
- Auto-processed: resized to max 1920px wide, WebP conversion for storage, thumbnail generated at 400px
- Displayed in chat with full-size click-to-expand lightbox
- Multiple images in one message: displayed as a 2-column grid

**Video Message**
- Accepted: MP4, MOV, WebM
- Max size: 50MB
- Thumbnail auto-generated at frame 1
- Inline video player with controls (no autoplay)

**Voice Note**
- Record button: press and hold (mobile) / click to toggle (desktop)
- Browser MediaRecorder API, WebM format
- Waveform visualisation during recording and playback (using Web Audio API)
- Max duration: 5 minutes
- Playback speed: 1x / 1.5x / 2x toggle

**File Attachment**
- Accepted: PDF, DOC, DOCX, XLSX, PPT, PPTX, TXT
- Max size: 20MB
- Displayed as a file card: icon (based on type), filename, file size, download button

**GIF**
- Search bar powered by Giphy API (API key in admin settings)
- Results shown in a popover grid
- Selected GIF sent as a message with the GIF URL
- Displayed as animated image inline

**System Message** (generated by platform, not users)
- Styled differently — centred, grey, italic
- Examples: "[Name] joined the room", "Solidarity campaign approved", "Goal reached!"
- Not deletable, not reactable

**Solidarity Card** (special message type)
- Rich card displayed inline in chat (see Solidarity section for full design)
- Live-updating via Livewire: progress bar and contributor count update in real time
- Sticky in the right sidebar while campaign is active

### Real-Time Features — Reverb Implementation

**Channels:**
- `tenant.{tenantId}.room.{roomId}` — public channel for room messages
- `private-user.{userId}` — private channel for DMs and personal notifications
- `presence-room.{roomId}` — presence channel for online member list and typing

**Events broadcast via Reverb:**
- `MessageSent` → all members of the room receive the new message
- `MessageEdited` → all members receive the updated content
- `MessageDeleted` → all members receive the deletion signal (message replaced with "This message was deleted")
- `ReactionAdded` / `ReactionRemoved` → reaction count updates on all clients
- `UserTyping` → "[Name] is typing..." shown for 3 seconds (debounced, not sent on every keystroke)
- `UserPresenceChanged` → online/offline status updated in member list
- `SolidarityCampaignUpdated` → progress bar and contributor count updated on all Solidarity Cards
- `RoomMemberJoined` / `RoomMemberLeft` → member count updated

---

## SOLIDARITY — COMPLETE IMPLEMENTATION

### Campaign Creation Flow (detailed)

**The "Start a Solidarity" entry point:**
- Available from the attachment/plus button in ANY Yard room's message input
- Also accessible from the Solidarity section in the right sidebar
- Opens as a full slide-over panel (not a modal — needs space for form fields)

**The Creation Form (all fields):**

Page 1 of 2:
- Campaign Title* — *"In memory of..."* / *"Support for..."*
- Category* — dropdown with icon per category (🕊️ Bereavement, 🏥 Medical, 🌊 Disaster, 🎓 Education, ✈️ Repatriation, 🤝 Other)
- Beneficiary Full Name*
- Beneficiary Relationship to Community* — how does this person connect to the community?
- Full Description* — what happened, why the community should support, how funds will be used
- **"Help me write this" button** → opens Kamer AI inline, user describes situation in a few words, Kamer drafts the description in their language, user edits

Page 2 of 2:
- Target Amount* (number input)
- Currency* (dropdown: GBP, EUR, USD, XAF, CAD)
- Campaign Deadline (optional date picker — leave blank for no deadline)
- Upload Proof Document (PDF or image — death certificate, hospital letter, etc.) — marked "Strongly Recommended"
- Allow Anonymous Contributions? (toggle — default: on)
- Review: show summary of platform fee: *"Platform fee: 5% · For every £100 contributed, £95 goes to [Name]'s family."*
- Submit for Approval

**After submission:**
- Creator sees: *"Your Solidarity campaign has been submitted for review. Our team will review it within 24 hours. You'll be notified by email and in-app."*
- Admin receives instant in-app + email notification
- Campaign status = `pending_approval`
- Kamer AI runs `AssessSolidarityCampaign` job asynchronously: analyses description, proof document (if uploaded, uses vision API), generates risk_score (low/medium/high) and risk_reason

### Admin Approval Interface

Admin sees a rich review panel:
- All campaign details
- Proof document preview (PDF viewer or image display)
- **Kamer AI Risk Assessment badge**: 🟢 Low Concern / 🟡 Medium Concern / 🔴 High Concern
- Kamer's reason: *"Description is specific and detailed. Proof document matches stated cause. Beneficiary relationship is clearly explained. Low fraud risk."*
- Three action buttons:
  - ✅ **Approve** — campaign goes live immediately
  - 💬 **Request More Info** — text field to type request, sent to creator as notification
  - ❌ **Reject** — mandatory reason field, sent to creator with compassion: *"Thank you for bringing this to the community. We were unable to approve this campaign at this time because: [reason]."*

**On Approval:**
1. Campaign status → `active`
2. A Solidarity Card system message is posted in the originating room
3. A Solidarity Card system message is also posted in the National Room
4. Creator receives notification: *"Your Solidarity campaign is live! Share it with the community."*
5. Points awarded to creator

### The Solidarity Card (live Livewire component)

```
┌──────────────────────────────────────────────────────────┐
│  🕊️  SOLIDARITY  ·  Bereavement                          │
│  ─────────────────────────────────────────────────────   │
│  In Memory of Mama Ngozi Fru                             │
│  Mother of community member Emmanuel Fru                  │
│                                                          │
│  "Our brother Emmanuel lost his mother in Bamenda last   │
│   week. The community stands with him."                  │
│                                                          │
│  ████████████░░░░░░░░░░░░  £420 of £1,000               │
│  42% reached  ·  23 contributors  ·  5 days left         │
│                                                          │
│  Platform fee: 5%  ·  Net to family: £399.00             │
│                                                          │
│  [        Contribute Now        ]  [View Contributors]   │
└──────────────────────────────────────────────────────────┘
```

- Progress bar animates when new contribution arrives (Reverb event → Livewire → CSS transition)
- Contributor count increments in real time
- "Goal reached" state: confetti, green background, *"🎉 Goal reached! Thank you to all 23 contributors."*
- After campaign ends: card shows final amount raised and "Disbursement in progress" or "Disbursed ✓"

### Contribution Flow (detailed)

User clicks "Contribute Now":
1. Modal opens with campaign summary header
2. **Amount input** (min: £1, suggested amounts: £5, £10, £20, £50, Custom)
3. **Anonymous toggle** — "Contribute anonymously" — hides name from public contributors list
4. **Personal message** (optional textarea, max 200 chars) — shown in contributors list
   - **"Suggest a message" button** → Kamer generates a culturally appropriate condolence in user's language
5. **Live fee breakdown** (updates as amount changes):
   - *"Your contribution: £50.00"*
   - *"Platform fee (5%): £2.50"*
   - *"Amount to [Name]'s family: £47.50"*
6. **Payment method** (pilot: Bank Transfer / Manual — with instructions shown after confirmation)
7. **Confirm button** → contribution record created (status: pending), campaign total updated optimistically
8. Thank you state: *"❤️ Thank you, [Name]. Your contribution means everything to this family."*
9. System message in room: *"❤️ [Name] just contributed to [Campaign Title]."* (or "Someone" if anonymous)
10. Points awarded to contributor

---

## ADMIN PANEL — COMPLETE SPECIFICATION

### Layout
- Separate Blade layout: `layouts/admin.blade.php`
- Dark sidebar (navy/dark slate), white content area
- Fully responsive — works on tablet and mobile
- Sidebar collapsible on smaller screens
- Top bar: admin name, role badge, notifications, logout

### All Admin Sections

**1. Dashboard**
- Metric cards: Total Users, Active Today, Messages Today, Open Solidarity Campaigns, Pending Reports, Pending Solidarity Approvals
- User registration chart (line, last 30 days, by day)
- Messages per room bar chart (last 7 days)
- Real-time activity feed (Livewire polling or Reverb):
  - Latest registrations
  - Latest flagged messages
  - Latest Solidarity submissions
- Quick action buttons: "Review Solidarity Campaigns", "Review Flagged Content"
- **AI Daily Insight card** (Kamer): generated once per day, shown at top

**2. User Management**
- Searchable, filterable, paginated user list
- Filters: country, city, account type, verification status, date range, active/suspended
- Bulk actions: export CSV, bulk suspend, bulk verify
- Per-user actions: View, Edit, Verify Email, Verify Identity, Suspend (with reason + duration), Ban, Delete (soft), Impersonate
- User Detail page: full profile, GPS history, badge list, community points, rooms joined, messages sent count, Solidarity contributions, reports against them, full activity log
- Manual badge management: award or revoke badges with reason

**3. The Yard Management**
- Room list table: name, type, country, city, member count, message count, last activity, status
- Room detail page: room info, member list with roles, searchable message history (100 messages per page), pinned messages management
- Create system room manually (national or city)
- Room suspension: pause a room temporarily with a community message
- Message search across all rooms

**4. Solidarity Management**
- Tab navigation: Pending (badge count) / Active / Completed / Rejected / Cancelled
- Pending tab: each campaign shown as a review card with all details, proof document, AI risk badge, approve/reject/info-request actions
- Active tab: live progress bars, ability to post updates, ability to pause/cancel, disbursement management
- Financial Summary widget: total raised this month, total platform fees collected, total disbursed, pending disbursement
- Disbursement workflow: mark as disbursed → upload proof → amount becomes public

**5. Content Moderation**
- Flagged messages queue — shows message with 5 messages above and below for context
- AI score breakdown shown per item: {hate_speech: 0.01, harassment: 0.02, spam: 0.94}
- Actions per flagged message: Dismiss (not a violation), Delete Message, Warn User, Suspend User (15m / 1h / 24h / 7d / permanent), Escalate
- Moderation decisions logged to audit trail
- Moderation stats: messages reviewed today, false positive rate estimate

**6. Reports Management**
- User-submitted reports list
- Status workflow: Pending → Under Review → Resolved / Dismissed
- Assign to specific moderator
- Reporter and reported party both notified on resolution

**7. CMS**
- Pages Manager: list of all pages (About, Privacy Policy, Terms of Service, FAQ, Contact, + any custom pages)
- Page editor: WYSIWYG (TinyMCE or Quill), SEO fields (meta title, meta description), publish/draft toggle
- Announcements: create platform-wide banners or system messages pushed to all Yards
- Email Template Editor: edit content of all transactional emails
- Coming Soon Page Editor: edit the content shown for Phase 2 modules

**8. Platform Settings** — all stored in `platform_settings` table
Organised into groups:

*General*
- Platform name, tagline, support email
- Maintenance mode (on/off with custom message)
- Registration: open / closed / invite-only
- Email verification: required / optional

*Community*
- Founding member cap (default: 1000)
- Community points per action (object: {message_sent: 1, solidarity_contribution: 10, profile_complete: 50, ...})
- Verified residency threshold in months (default: 6)
- Default language for new tenants (en/fr/bilingual)

*The Yard*
- Maximum message character length (default: 4000)
- File upload size limits per type (image, video, audio, file)
- Message deletion window in minutes (default: 60)
- AI automod: on/off, sensitivity threshold (0.0–1.0)
- Profanity filter: on/off, custom word list (textarea)
- GIF feature: on/off, Giphy API key input
- Link preview: on/off

*Solidarity*
- Platform cut percentage (default: 5.00) — affects all NEW campaigns
- Minimum contribution amount (default: 1.00)
- Maximum campaign duration in days (default: 90)
- Proof document: required / encouraged / optional
- Approval workflow: manual review (default) / auto-approve (risky — not recommended)
- Show contributor names publicly: yes/no default (per-campaign override)

*AI Settings*
- OpenAI API Key (password input, stored encrypted, never shown after saving — only "Update Key" button shown)
- AI Model: gpt-4o / gpt-4o-mini / gpt-3.5-turbo (dropdown)
- Kamer chat assistant: on/off
- AI content moderation: on/off, sensitivity (0.0–1.0)
- AI translation: on/off
- AI Solidarity risk assessment: on/off
- Giphy API Key

*Notifications*
- Email notifications toggle per event type (new message mention, Solidarity update, new member joined city room, etc.)

*SEO & Analytics*
- Meta title, description, OG image for homepage
- Google Analytics Measurement ID
- Facebook Pixel ID
- Custom `<head>` scripts (textarea, for third-party tools)

**9. Tenant Management**
- List all tenants (Cameroon Community is the only one at launch)
- Add new tenant form (for Nigeria, Ghana, etc. in the future)
- Per-tenant settings override

**10. AI Management**
- Kamer conversation log (anonymised — no user identification)
- AI moderation decision log (message excerpt, score, action taken)
- OpenAI API usage tracker: tokens used this month, estimated cost at current model pricing
- Prompt editor: edit the system prompts for each AI function (Kamer chat, moderation, translation, Solidarity assessment)

**11. Audit Log**
- Every admin action logged with: admin name, action type, affected record, before/after values, timestamp
- Read-only — cannot be deleted by any admin, including Super Admin
- Filter by: admin user, action type, model type, date range
- Export to CSV

**12. Analytics**
- User growth: daily registrations chart (30/90/365 day range)
- Retention: DAU, WAU, MAU
- Most active rooms by message volume
- Geographic distribution: members by country, city (bar chart)
- Solidarity metrics: total raised, average campaign, success rate, platform fees
- Top contributors by community points (leaderboard)
- All data exportable to CSV

---

## USER EXPERIENCE STANDARDS — ALL MANDATORY

### Performance
- Lighthouse score 90+ on mobile for landing page
- Lighthouse score 80+ on mobile for app pages
- WebSocket message delivery: visually appears < 100ms after send
- All images lazy-loaded with blur placeholder
- Application shell loads first — content hydrates after

### Responsiveness
Tested and perfect at:
- 375px (iPhone SE — minimum supported width)
- 390px (iPhone 14 / standard Android)
- 768px (iPad portrait)
- 1024px (laptop)
- 1440px (desktop)
- 1920px (large desktop)

### The Yard on Mobile
Must feel like WhatsApp mobile:
- Message input docked to bottom, above system keyboard
- Room list slides in from left
- Swipe right to close chat, back to room list
- Tap avatar to view profile
- Long-press message for action menu
- Pinch to zoom images

### Language Toggle Behaviour
- The EN/FR toggle in the navigation switches language instantly
- No page reload
- All Blade content switches via Alpine.js reading from a reactive language store
- All Livewire components re-render in new language
- Kamer AI responses in the next message will be in the new language
- Language preference saved to database on toggle

### Error Handling
- No raw PHP errors ever visible to users
- Every failed form action: clear, friendly, specific error message inline
- Lost WebSocket connection: yellow banner "Reconnecting..." with animated indicator, auto-reconnects
- File too large: immediate feedback before upload attempts with specific size limit mentioned
- Custom 404: on-brand, suggests going to The Yard
- Custom 500: friendly apology with support email link
- Rate limit hit: friendly message with countdown timer to next allowed action

### Loading States
- wire:loading directives on all Livewire interactive elements
- Skeleton screens for: message feed loading, room list, member list, Solidarity campaign list
- Optimistic UI for message sending: message appears immediately in the correct position in chat, with a "sending" indicator (faded appearance + small clock icon), confirmed or rolled back based on server response
- All file uploads show progress bar with percentage

### Empty States — designed, not blank
- No messages in room yet: illustrated empty state, *"Be the first to say something in [Room Name]! 👋"*
- No private groups yet: *"You haven't created or joined any private groups. Start one with friends or family."* + Create Group button
- No DMs yet: *"No direct messages yet. Start a conversation with someone from the community."*
- No active Solidarity campaigns: *"No active campaigns right now. The community is here for each other when it matters."*
- No search results in message search: *"No messages found for '[query]'. Try different words."*

---

## SECURITY REQUIREMENTS — ALL MANDATORY

| Requirement | Implementation |
|---|---|
| XSS | All output through Blade's `{{ }}` escaping. Never `{!! !!}` on user-provided data. |
| CSRF | Laravel's built-in CSRF protection. Never disabled. All Livewire forms protected automatically. |
| SQL Injection | Eloquent ORM exclusively. All raw queries use parameterised bindings. |
| File Uploads | MIME type validated server-side via intervention/image. Extension check is secondary, not primary. |
| Rate Limiting | Register: 5/minute. Login: 10/minute with lockout. Message send: 60/minute. File upload: 10/minute. Solidarity contribution: 3/minute. |
| Account Lockout | 5 failed logins = 15-minute lockout. Logged to audit trail. |
| Encryption | API keys, sensitive settings stored via Laravel's encrypt(). Never stored as plaintext. |
| Public IDs | All public-facing resource IDs use UUID. Auto-increment IDs never in URLs or API responses. |
| Admin Protection | /admin routes: authentication + spatie role check + optional IP whitelist (configurable). |
| Passwords | Minimum 8 chars, 1 uppercase, 1 number. bcrypt hashing. Strength indicator during input. |
| Media URLs | Files served through signed temporary URLs via Laravel Storage. Not direct paths. |
| User Location | GPS coordinates never shown to other users. Only country and city are visible. |

---

## CODE QUALITY STANDARDS

| Standard | Rule |
|---|---|
| Style | PSR-12 via Laravel Pint. Run `./vendor/bin/pint` before every commit. |
| Single Responsibility | One thing per method. 30-line maximum per method. Extract to smaller methods. |
| No Magic Numbers | Named constants or `config()` / `settings()` values. Never inline numbers. |
| N+1 Prevention | Always use `with()` eager loading. Debugbar catches violations in dev. |
| Relationships | Defined on both ends of every relationship. |
| Validation | Dedicated FormRequest per action. No inline `$request->validate()` in controllers. |
| Service Layer | Business logic in Service classes only. Controllers = receive + delegate + respond. |
| Documentation | PHPDoc on all public methods. Comment the WHY, never the WHAT. |
| Enums | PHP 8.1 backed enums for all database enum columns. No raw string comparisons. |
| Testing | Feature test + unit test for every new feature before it is marked complete. |
| No Dead Code | No commented-out code. No // TODO in committed code. |
| Consistent Naming | Snake_case for variables/methods, PascalCase for classes. No abbreviations. |

---

## HOW TO PROCEED — EXACT INSTRUCTIONS FOR THE AI MODEL

### Step 1 — Acknowledge and Summarise
Before writing any code, summarise back to me:
- The platform concept in 3 sentences
- The tech stack you'll use
- The database strategy
- The Phase 1 scope
- The confirmed decisions on language, Solidarity creation, and Yard room types

If anything is unclear, ask now. Do not assume and build wrong.

### Step 2 — Project Scaffold
Start with:
1. Laravel 12 installation + directory structure
2. All required Composer packages installed
3. `.env` configuration file with all required variables documented
4. `config/tenancy.php` configured for single-database mode
5. Database migration files for THE FULL SCHEMA (all tables, all modules — not just Phase 1)
6. Seeders: TenantSeeder (Cameroon), RoleSeeder, SettingsSeeder, MarketplaceCategorySeeder, YardRoomSeeder (creates UK National Room)

### Step 3 — Build in This Exact Order
1. BelongsToTenant trait + TenantScope + InitializeTenancy middleware
2. SetUserLanguage middleware + language toggle system (Alpine.js reactive)
3. Authentication — 3-step registration wizard (Livewire), login, email verification, password reset
4. GPS location detection + UpdateUserLocation middleware + room auto-assignment logic
5. Landing page — all 11 sections, fully designed, Kamer AI chat bubble
6. Application shell — layouts, navigation (with EN/FR toggle), notification bell, user menu
7. The Yard — room list, all room types, all message types, real-time via Reverb
8. Solidarity — complete, inside The Yard
9. Admin panel — all 12 sections
10. Kamer AI — wired into all features above
11. Scaffolded modules — routes, placeholder views, coming soon pages

### Step 4 — Verification Checklist (after EVERY feature)
Before moving to the next feature, verify:
- [ ] Feature works end-to-end in browser
- [ ] Works on mobile (375px width)
- [ ] No N+1 queries (check Debugbar)
- [ ] tenant_id is correctly scoped
- [ ] Language toggle works for this feature
- [ ] Error handling is in place
- [ ] Validation covers all edge cases
- [ ] Loading states are present
- [ ] Empty states are designed

### Step 5 — Never Produce Partial Code
Every file you write must be complete and production-ready. No `// TODO`. No `// implement later`. No `// add logic here`. If a Phase 2 feature is referenced, it is scaffolded properly with a Coming Soon state — not left as a comment.

### Step 6 — Final Delivery Summary
When Phase 1 is complete, provide:
- Complete list of every file created
- Database tables created and their row counts from seeding
- Step-by-step instructions to run the application locally
- List of all environment variables required
- What Phase 2 will build

---

## FINAL NOTE TO THE AI MODEL

This platform will be used by real Cameroonians navigating real challenges in real countries far from home. Every person who opens this app may be lonely, confused, or in need of community. The Solidarity feature may be the thing that helps a family afford to bring someone home. The Yard may be where a new arrival finds their first friend in a foreign country.

Build it like their experience depends on the quality of your code.

Because it does.

*I-NNOVA CM — Transforming Communities. Empowering Innovators.*
*Belgocam Building, City-Chemist, Bamenda, Cameroon*
