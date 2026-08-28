# Outstanding Work — Handoff

Handoff for picking this up in a fresh session. Captures the deferred items, the context
needed to execute them, and the environment/dev conventions used this session.

> Written 2026-08-28 after a long session that shipped: the items/loadout overhaul, the
> Wordle-style daily → **endless "race to a target"** challenge with a dedicated
> Challenges page, a hub "DAILY" seat, an admin **Seeders** page, flat-button styling,
> and various fixes. All 82 tests pass; the frontend builds clean.

---

## Environment & dev conventions (read first)

- **No Docker running.** Use **Herd PHP** for all PHP/artisan: `/c/Users/Michael/.config/herd/bin/php.bat` (there is no plain `php` on PATH). Example: `/c/Users/Michael/.config/herd/bin/php.bat artisan migrate --force`.
- **Frontend build:** `npm run build` (Vite). Changes to `.vue`/`.css`/`.ts` **require a build** to show in the running app (Herd serves `trusted-advisors.test`). Realtime (Reverb) isn't running locally — the `ws://127.0.0.1:8080` / favicon 302 console errors are harmless env noise.
- **Lint:** `npx eslint --fix <files>` on any `.vue`/`.ts` you touch (config present, eslint has final say alongside no Prettier install). Match local conventions: newer controllers use `declare(strict_types=1)`; models use `$fillable` (no strict_types, no e2es traits — this project does NOT follow the global org standards, don't retrofit).
- **Tests:** `/c/Users/Michael/.config/herd/bin/php.bat vendor/bin/phpunit --no-coverage` (in-memory SQLite via `RefreshDatabase`; ~20s for the full suite). Feature tests seed `Card`/`Event` rows or the deck-build loop in `GameController::start()` **infinite-loops** (no available cards). No `pint.json`/PHPStan config in project → those steps are correctly skipped.
- **Kill stray php between runs** if backgrounded `artisan test` piles up: `taskkill //F //IM php.exe`.
- **Seeders** are not tracked like migrations. Admin → **Seeders** page (`AdminSeeders.vue` + `Admin\SeederController`) shows Pending/Applied (inferred from data) and runs them. New seeders must be registered in `database/seeders/DatabaseSeeder.php`.

## Key architecture touchpoints (for the items below)

- **Challenges/daily backend:** `GameController::challenges()` (`GET /api/challenges`), `challengePayload()`, `challengeStats()` (platform avgs), `startDailyRun()` / `startDailyChallenge()` / `runDailyChallenge()`, `dailyGoalMet()` (endless win check, in `nextRound()` coop game-over block ~line 1910). Finalisation + `rounds_taken` in `GameCompletionService::checkDailyChallenge()`. Generator: `app/Console/Commands/GenerateDailyChallenge.php` (endless single-target templates, `--ahead=N` rolling window, scheduled in `routes/console.php` at 00:12).
- **Challenges frontend:** `ChallengesPage.vue` (route `/challenges`), hub seat in `GameSetup.vue` (`.seat-tc`), `DailyChallengeIntro.vue` / `DailyChallengeArchive.vue` (older modals, still used by `DailyChallengeBanner.vue` which is now unused on the hub).
- **Entry model:** `DailyChallengeEntry` has `status` (pending|in_progress|won|lost), `rounds_taken`, `started_at`, `game_id`.
- **Admin:** child routes in `app.ts` + sidebar links in `AdminLayout.vue`; controllers in `app/Http/Controllers/Admin`; shared admin CSS classes (`.page-header`, `.list-panel`, `.list-row`, `.btn-primary`, `.status-badge`) are global in `App.vue`.

---

## Outstanding items

> **Update 2026-08-28 (follow-up session):** Items #1–#4 are now **done** (93 tests pass,
> build clean). Remaining: #5 (MCP server — needs scoping) and #6 (admin redesign — its own
> session). Detail of what shipped for #1–#4 is under "Completed this session" at the bottom.

### 1. Challenge "Quit" button (small) — completes the endless-mode gap
Endless runs end on target (win) or stat-collapse (loss), but a player can't **quit** early;
abandoning leaves the entry `in_progress`.
- **Backend:** add `POST /api/daily-challenges/{game}/quit` (or reuse `forfeitGame`) that, for an `is_daily` game, sets it `completed`/`win=false` and calls `GameCompletionService::processCompletion()` → `checkDailyChallenge()` finalises it **lost** (goal not met → lost, `rounds_taken` null). Guard: only the owning user, only while active.
- **Frontend:** a "Quit trial" button on the game board (`GameBoard.vue`) shown when `game.is_daily`, with a confirm; on success route to `/challenges`.
- **Test:** quitting an in-progress daily marks the entry `lost`.

### 2. Anthropic-key admin input UI (small) — backend already done
`AiGeneratorController::getApiKey()` now reads `GameRule::getValue('anthropic_api_key')`
with `config('services.anthropic.api_key')` fallback. Missing: an admin **input field**.
- **Backend:** small `Admin\SettingsController` (or extend an existing admin controller): `GET` returns `{ anthropic_key_set: bool, masked: '••••1234' }` (never return the raw key); `PUT` validates a string and `GameRule::updateOrCreate(['key'=>'anthropic_api_key'], ['value'=>...])`. Routes under the `admin` group.
- **Frontend:** minimal `AdminSettings.vue` (or add to an existing admin page) with a password input + Save, showing whether a key is set. Add route in `app.ts` + `AdminLayout.vue` nav link.
- Note: it's already settable via the generic `PUT /api/admin/rules/{key}` (`GameRuleController`) if a quick path is wanted.

### 3. Manual challenge editing (small–medium)
`AdminChallenges.vue` + `Admin\DailyChallengeController` already do CRUD, but the form was
built for the **old** criteria shape. Update it for the **endless** shape:
`criteria = { mode, rounds, start:{all}, goal:{type:'stat_threshold', stat, value}, seed_character_id, seed_loadout:[itemIds] }`.
- Expose editable fields: title, description, reward_xp, date, target **stat + value**, assigned **character** (dropdown), **seed loadout** (multi-select of items, max 3), rounds cap.
- Validate + persist into `criteria` JSON. Keep the deterministic-generation path for auto ones; manual edits set `is_manual = true`.

### 4. Admin scheduled-jobs UI (medium)
A panel to view/trigger the scheduled commands (currently only in `routes/console.php`).
- **Backend:** `Admin\ScheduleController@index` returns the known scheduled commands (name, cron/description, and where useful a "last outcome"); `@run` runs one via `Artisan::call()` with an **allowlist** (mirror `SeederController`). Relevant commands: `app:generate-daily-challenge --ahead=6`, `app:generate-monthly-season`, `app:generate-weekly-challenge`, `app:process-season-end`, `app:process-league-week`.
- **Frontend:** `AdminSchedule.vue` list + Run buttons + last-run output (reuse the Seeders page pattern). Route + nav link.
- Optional: a real `schedule:run` needs cron/worker on the server — document that; the admin "Run now" is for manual triggering.

### 5. MCP server (large — needs scoping first)
"Create an MCP server" — **undefined scope.** Before building, agree:
- **What it exposes** (e.g. read game/challenge stats, admin content CRUD, trigger seeders/schedules?) and **to whom** (auth model — a token? admin-only?).
- **Transport/host** (stdio vs HTTP; standalone Node/TS package vs a Laravel route surface).
- Likely a separate small package or an authenticated HTTP surface wrapping existing admin endpoints. **Do not start without a scoping conversation.**

### 6. Full admin-UI redesign (IN PROGRESS)

> **Started 2026-08-28.** Direction chosen: **"Scriptorium"** — light parchment ground,
> white panels, brass accent, sans-serif data with Cinzel kept only on titles/nav labels.
>
> **Done:**
> - **Theme foundation** — `resources/css/admin.css` (imported in `app.ts`). Remaps the game
>   theme tokens (`--bg-*`, `--accent-gold`, `--text-*`, `--border-gold`, `--wood-*`) to the
>   Scriptorium palette **scoped under `#game-app.is-admin`**, so every token-driven admin
>   style re-skins at once. Also overrides the wood button/inputs and defines the shared
>   component classes **once** (`.page-header`, `.page-title`, `.list-*`, `.status-badge`,
>   `.badge-*`, `.form-grid`, `.modal-*`, `.admin-table`, `.tab-btn`). `AdminLayout.vue`
>   sidebar rewritten to the light shell. Build clean.
> - **Preview infra** — `admin/AdminPreviewModal.vue`: a reusable wrapper that **`Teleport`s to
>   `<body>`** so its slotted content escapes the admin light remap and renders in the real
>   dark game skin (dark game-ground stage, "In-game preview" header).
> - **Previews wired (6 content types):** **Cards** (`CardDisplay.vue`), **Characters**
>   (`CharacterInfoModal.vue`, teleported), **Events** (`EventBanner.vue`), **Kingdom Styles**
>   (`KingdomStats.vue` with a stub `previewGame`), **Curses** (`CurseSelectionOverlay.vue`,
>   teleported — added a close × since it only emits `selected`), **Items** (new
>   `admin/ItemPreviewCard.vue`, since `PlayerItems` is too game-state-coupled to render a
>   single item). Small correct type-widenings made along the way: `CharacterInfoModal` dice
>   `string[][] → (number|string)[][]`; `KingdomStats` `KingdomCssVariables` given an index
>   signature so admin `Record<string,string>` css_vars is assignable (no casts anywhere).
> - **Refinements (per feedback):** admin font switched to **Roboto** (loaded in `welcome.blade.php`,
>   forced across admin via an id-scoped universal `font-family` rule — no more Cinzel in admin);
>   sidebar **nav icons removed**; sidebar spacing tightened.
>
> **The wiring pattern (repeat per page):** add a `Preview` button in the row/actions →
> `openPreview(entity)` sets a `previewX` ref → render the game display component inside
> `<AdminPreviewModal :visible="previewX !== undefined" @close="previewX = undefined">`
> (or teleport a self-contained modal like CharacterInfoModal directly).
>
> **Previews — COMPLETE for the brief's list (9) + Achievement:** Card, Event, Character,
>   Item, Curse, **Cosmetic**, **Dice theme**, Kingdom Style, **Daily Challenge**, **Achievement**.
>   New purpose-built cards (all mirror `ItemPreviewCard.vue`, render in the teleported dark
>   game-skin modal): `admin/ItemPreviewCard.vue`, `DiceThemePreviewCard.vue`,
>   `CosmeticPreviewCard.vue` (reuses `LeagueCrest` + the `CosmeticPicker` visual vocabulary),
>   `ChallengePreviewCard.vue` (endless goal/stats/loadout), `AchievementPreviewCard.vue`
>   (reuses `AppIcon`/`resolveAchievementIcon`). No casts used anywhere; all agents verified
>   zero new type errors against baseline.
>
> **Contrast/holdout polish — DONE:** `AdminIcons.vue` and `IconPicker.vue` were the only two
>   pages that bypassed the tokens with a hardcoded dark palette (dark rows, gold titles, cream
>   text) — both re-themed to the admin light tokens. A full-admin grep confirms no remaining
>   hardcoded dark backgrounds (outside the intentionally-dark `*PreviewCard`/`AdminPreviewModal`)
>   and no hardcoded light text that would be invisible on parchment. Every other page re-skins
>   correctly via the token remap.
>
> **Remaining (all optional / low-value):**
> - Optional previews for **Addon / Unlockable / Season** (config-ish, not in the brief's list).
> - **De-dup sweep** — the copy-pasted shared classes across ~28 pages are dead weight (global
>   `admin.css` overrides them by specificity). Deleting them is invisible maintainability and
>   carries real risk (many pages carry *page-specific* `.form-group`/`.stat-cell`/`.effects-*`
>   tweaks not in the global sheet), so it needs careful per-page review, not a blind delete.
> - **De-dup sweep (maintainability, not visual)** — the shared classes are still copy-pasted
>   into ~28 pages' scoped `<style>`; the global `admin.css` now overrides them by specificity,
>   so they're dead weight. Delete per page and spot-check. Hardcoded-hex holdouts are mostly
>   harmless (`.modal-overlay` rgba already overridden; `var(--wood-*, #darkfallback)` resolves
>   to the remapped light value).
> - **Per-page polish** — charts (`AdminBalance`/`AdminRetention`) and the effect editors
>   (`AdminCards`/`AdminCharacters`) have bespoke styling worth a light contrast pass on parchment.
>
> Original brief retained below for reference.


"Remove the game UI designs (except when previewing cards etc.) and make a cleaner,
more manageable admin UI. Every element has a **Preview** option showing how it looks on
the frontend."
- Introduce an **admin-only theme** (neutral/clean; not the gold "wood" game buttons) scoped under the admin layout — override the global game button/card styling within `.admin-*` containers, or give admin its own CSS layer so `App.vue`'s global button styles don't bleed in.
- Keep the **game-styled preview** components: for each content type (card, event, character, item, curse, cosmetic, dice theme, kingdom style, daily challenge), add a **"Preview"** action that renders it with the real frontend component (`CardDisplay.vue`, `PlayerItems.vue`, `KingdomStats.vue`, `AppIcon.vue`, the challenge briefing, etc.) in a modal.
- Audit all `admin/*` pages for consistency (tables/forms), reduce game-flavoured chrome, tighten spacing. Large sweep — plan per-page.

---

## Suggested sequencing
1. **Batch the three small ones** first: #1 Quit, #2 Anthropic key UI, #3 challenge editing.
2. Then **#4 scheduled-jobs UI** (reuses the Seeders pattern).
3. **#6 admin redesign** as a dedicated session (per-page sweep + shared preview modal).
4. **#5 MCP server** last, after a scoping chat.

## Completed this session (2026-08-28 follow-up)

- **#1 Challenge Quit** — `POST /api/daily-challenges/{game}/quit` (`GameController::quitDailyChallenge`, owner-only + active-only guards) finalises an active daily game as a loss via `GameCompletionService::processCompletion` → entry settles `lost`, `rounds_taken` null. Frontend: **Quit Trial** menu item + confirm modal on `GameBoard.vue` (`isDaily` computed, routes to `/challenges`). Tests: happy path + non-owner 403 in `DailyChallengeRunTest`.
- **#2 Anthropic key UI** — `Admin\SettingsController` (`GET /api/admin/settings`, `PUT|DELETE /api/admin/settings/anthropic-key`); never returns the raw key (masked to last 4). `AdminSettings.vue` (super-admin nav, masked password input, Save/Clear, env-fallback hint). Tests in `AdminSettingsAndScheduleTest` assert the key is stored raw for the generators but only ever returned masked.
- **#3 Manual challenge editing** — `Admin\DailyChallengeController` store/update now validate the **endless** criteria shape (`mode`/`rounds`/`start.all`/`goal.{type,stat,value}`/`seed_character_id`/`seed_loadout[≤3]`); update always sets `is_manual=true`. `generateRange` now reuses the artisan generator per-date (removed the stale flat-shape template list). `AdminChallenges.vue` daily form rebuilt: target stat+value, starting stats, rounds cap, advisor dropdown, seed-loadout multi-select (max 3). Weekly tab untouched. Tests cover create + missing-goal rejection.
- **#4 Scheduled-jobs UI** — `Admin\ScheduleController` (`GET /api/admin/schedule`, `POST /api/admin/schedule/run`) with an **allowlist** of 5 jobs (mirrors `routes/console.php`), runs via `Artisan::call`. `AdminSchedule.vue` (super-admin nav, per-job Run-now + last output). Tests cover the list, unknown-key 422, and an allowlisted run.

## Daily challenge customisation + AI blurbs (2026-08-28)

Daily challenges now carry the full rotating-event-style config, all **seed-deterministic**
(every player gets a byte-identical run — same deck, event order, curses, random starts):
- **Criteria shape:** `{ mode, rounds, start:{ all, per_stat:{stat:val} }, goal, seed_character_id,
  seed_loadout, house_rules:{...}, card_pool?, item_pool?, event_pool?, curse_pool?, reward_coins }`.
  Goal types: `stat_threshold`, `stat_threshold_all` (`targets` map), `no_stat_below`.
- **Determinism backbone** (in `GameController::start`): pool branches use `orderBy('id')` +
  `$rng->shuffle` (never `inRandomOrder`), `random_starting_stats` seeds via `$rng->int`,
  `draw_curse_per_round` is deterministic (seeded curse deck + position draw, solo). `runDailyChallenge`
  maps criteria → `custom_rules` (`house_rules`, pools, `starting_stats_map`) + `game_type`.
  Per-stat starts applied in the coop setup block. Reward coins awarded in `checkDailyChallenge`.
- **Generator** (`app:generate-daily-challenge`): fully rewritten to compose rich, varied,
  date-deterministic challenges via `SeededRng` (goal variety, 1–2 per-stat weaknesses, 0–2 house
  rules, curses, advisor + loadout). New `--force` flag regenerates existing dates. Scheduled job
  still runs `--ahead=6` (no force, fills missing days only).
- **AI blurb** (`app/Services/ChallengeBlurbGenerator.php`): writes the second-person "You are
  {advisor}, drafted to {goal}, low on {weak stats}, a curse hangs over the land…" briefing from the
  challenge's real metrics. Calls Claude (`claude-opus-4-8`, raw Laravel Http, key from
  `GameRule('anthropic_api_key')` → env fallback) when a key is set; **deterministic templated
  fallback** otherwise so generation never fails. **No key is currently set** → current blurbs are
  templated. To get AI blurbs: set the key in Admin → Settings, then re-run the generator with
  `--force` (a `--force` schedule entry would need adding to `Admin\ScheduleController` — not done).
- **Admin form** (`AdminChallenges.vue`, daily tab): exposes goal-type selector, house-rule
  checkboxes, the four content-pool pickers, and reward coins. Tests: determinism with pools + house
  rules, seeded random-stats, per-stat starts, and a generator-command lifecycle test.

## Loose ends / notes
- `dailyGoalMet()` and `evaluateDailyGoal()` handle `stat_threshold` / `stat_threshold_all` / `no_stat_below`; endless generator only uses single `stat_threshold` now.
- Endless daily games use `total_rounds = 120` as a safety cap (validation max is 120). If a run must be able to exceed that, raise the `total_rounds` validation max and the generator value together.
- The 3D dice "W" face: `dddice-service.ts` now accepts a `label`, and `RoundResults.vue` passes `label:'W'` for wild faces — but the standard numeric dddice theme **bakes numbers into the face texture**, so a literal "W" needs a **custom dddice theme** (asset task), not just the label. 2D result panel already shows "W".
- The old passive daily banner (`DailyChallengeBanner.vue`) is no longer mounted on the hub; safe to delete if desired (also removes `DailyChallengeIntro`/`DailyChallengeArchive` usage — verify before deleting).
