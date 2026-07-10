# Handoff: Schülerstundenpläne — Module Selection Screen Redesign

## Overview
Redesign of an existing "Module Selection" admin screen (step 1 of a 4-step student-timetable wizard: Auswahl → Module → Stundenplan → Übernahme). The original screen mixed too many colors (green/red/blue used for selection, status, and brand all at once) and had no visual hierarchy between dozens of same-size chips. This redesign fixes readability and makes selection state unmistakable.

Two directions are included — pick ONE to implement (or ask your developer to build both behind a flag and compare):
- **Option 2A — "Calm & Structured"**: single indigo accent, flat card layout, status shown via colored card headers + checkmark instead of color-only coding.
- **Option 2B — "Table-Based"**: left sidebar for student profile/selections, main content area uses a real table for the long module list instead of a wall of chips.

## About the Design Files
The files in this bundle are **design references built in HTML/CSS** — prototypes showing intended look and behavior, not production code to copy directly. Recreate these designs in the target codebase's existing environment (React, Vue, Angular, etc.) using its established components, state management, and styling approach. If no frontend framework exists yet, choose the one best suited to the project.

## Fidelity
**High-fidelity.** Colors, spacing, typography, border-radius, and chip/card states shown are final — implement pixel-close using the values below, adapted to the codebase's existing component library where equivalents exist (e.g. an existing Chip/Badge/Table component).

## Files
- `schedule_redesign_standalone.html` — open directly in any browser, fully self-contained (no server needed). Contains all screens below, scrollable/pannable canvas.
- `screenshot-2a.png`, `screenshot-2b.png`, `screenshot-3a.png`, `screenshot-4a.png`, `screenshot-5a.png` — visual reference of the top of each screen (viewport-cropped; the HTML file is the full source of truth).

## Screens / Views

### Option 2A — Calm & Structured
**Purpose:** Student picks their curriculum options (religion, language, branch, elective) and additional modules before proceeding to the next wizard step.

**Layout:**
- Single scrolling card, 1760px reference width, white background, 16px border radius, subtle shadow.
- Dark navy header bar (`#1e2433`) containing: eyebrow label, page title (28px/800), role-badge chips, and an active-scope pill on the right.
- Horizontal tab row below header (Stundenplan v2 / TT-Einträge / Importe / Fächer), active tab underlined in indigo.
- 4-step numbered stepper (circles connected by lines): step 1 filled indigo, steps 2–4 gray/inactive.
- Student summary bar: circular avatar initials (indigo fill), name/class/semester, contact line, edit/remove icons — light gray card, 12px radius.
- 4-column grid of selection categories (Religion, Sprache, Zweig, Wahlfach), each a label + wrapped row of chips.
- "Besuchte Module" (read-only history): wrapped chip row, small color-key legend above (gray = bestanden/passed, red = negativ/failed). Failed chips get a bold red "NEG" badge, not just red color.
- "Abgeschlossene Module" (selectable/completed modules the student can additionally pick): chip row with dashed border = selectable/unselected, filled indigo + ✓ = selected. Helper text next to heading: "— zum Auswählen anklicken".
- 4-column status card row (Negative / Frühere / Aktuelle / Zusätzliche Module): each card has a tinted header band (red/gray/green/indigo) with count + hours, and chips below. Selected/checked modules show ✓ prefix.
- Footer bar (light indigo tint): "Ausgewählt: X Module", hour count, max cap; Neustart (red outline) and Weiter → (solid indigo) buttons, right-aligned.

**Components — colors:**
- Header navy: `#1e2433`
- Primary accent (selection/CTA): `#4f46e5`, hover/border shade `#4338ca`
- Success/positive: bg `#f0fdf4`, text `#15803d`, border `#bbf7d0`
- Negative/error: bg `#fdecea`, text `#8f1f16`, border `#f3b9b3`, badge fill `#8f1f16`
- Neutral chip: bg `#f1f2f6`, text `#3d4451`/`#5b6472`, border `#d7dae2`
- Body background: `#eef0f3`
- Selected chips get `box-shadow: 0 2px 6px` in the accent's color at ~35–40% opacity ("lift" effect) plus a ✓ prefix — never rely on fill color alone.

**Typography:** Inter, weights 400–800. Page title 28px/800. Section labels 12px/700 uppercase, letter-spacing .04–.08em, color `#8991a3`. Body/chip text 13–15px/600–700.

### Option 2B — Table-Based
**Purpose:** Same task, restructured so the long module list is a scannable table and student context lives in a persistent side rail.

**Layout:**
- Two-column card: left rail (300px, dark teal `#0b3b39`) holds branding, avatar, student info, and the 4 selection categories as plain label/value pairs (no chips) — ends with an "✎ Auswahl bearbeiten" button pinned to the bottom.
- Right main area: tab row + step indicator (small numbered circles, teal active state), then a **table** for "Module — Übersicht" (columns: Modul / Std. / Status) replacing the old chip wall — each row's Status cell is a small colored badge (bg/text pair matching category, e.g. green "Aktuell", red "Negativ").
- Below the table: "Abgeschlossene Module" as a secondary chip row (dashed = selectable, filled teal + ✓ = selected), with a helper line "auswählbar — 2 ausgewählt".
- 4-box category summary strip (Negativ / Früher / Aktuell / Zusätzlich): each a bordered/tinted box with count + hours, no chips inside — just the numbers, since detail lives in the table above.
- Footer: light teal bar with summary text + Neustart/Weiter buttons (teal accent instead of indigo).

**Components — colors:** Same status palette as 2A but accent is teal: `#0f766e` (primary), `#14b8a6` (lighter/selected fill), rail background `#0b3b39`.

### Option 3A — Module (wizard step 2), adapted to 2A's system
**Purpose:** After picking curriculum options in step 1, the student/admin reviews the actual course offerings ("Angebote") matched to each selected module code (e.g. BU2, CH1, M4) and accepts/rejects the group or individual course variants.

**Layout:**
- Same shell as 2A: tab row (Stundenplan v2 active) with a settings gear icon top-right, 4-step stepper — step 2 "Module" is now the active/filled circle, step 1 "Auswahl" shown complete (connecting line filled indigo up to the active step).
- Student summary bar: avatar + name/class/semester inline, a vertical divider, religion, and the email rendered as an indigo "chip" (not a plain link) with a copy icon. Below that, a second row of chips restates the step-1 selections (Semester, Ethik/Religion, Sprache, Zweig) — read-only here.
- "Ausgewählte Angebote" section: one card per module code (BU2, CH1, M4...). Card header = light indigo band with the module code (bold), a pill showing the count of course variants, and two action icons on the right — green check (accept whole group) and red ✕ (reject whole group).
- Inside each card, one row per course variant (e.g. `BU2-2Q-HER`): green check icon (included), the course ID in bold, a small indigo "module code" chip, a status chip for delivery mode (`Kompaktunterricht` = amber, `Fernunterricht` = blue — was raw orange text in the original, now a proper chip), and the schedule string in gray.
- Footer: Neustart (red outline, left), Zurück + Weiter → (right) — same button styling as 2A/2B.

**Components — colors:**
- Group header band: bg `#eef1ff`, code text `#1e2433`, count pill `#4f46e5`/white
- Accept icon: bg `#dcfce7`, icon `#15803d`; Reject icon: bg `#fdecea`, icon `#b3261e`
- Course row (accepted state): bg `#f0fdf4`, border `#bbf7d0`, check `#15803d`
- Delivery-mode chips: Kompaktunterricht bg `#fef3e2` / text `#b45309`; Fernunterricht bg `#e0f2fe` / text `#0369a1`
- Module-code mini chip: bg `#eef1ff`, text `#4338ca`

**Typography:** consistent with 2A — course IDs 14px/700, schedule text 13px/500 gray, section heading 16px/700.

### Option 4A — Stundenplan (wizard step 3), brought into the shared system
**Purpose:** Shows the generated timetable(s) that satisfy the module selections from steps 1–2; user pages through valid combinations and "übernimmt" (commits) one.

**Layout:** Same shell as 2A/3A (tabs, stepper — step 3 now active/filled). Student summary bar identical to 3A. "Gewählte Module" section: each selected module as an indigo chip with a fraction (e.g. `BU2 5/5`) and an ✕ to remove. "Stundenpläne" header row with 3 actions: "+ Mehr Module" and "⚙ Optionen" (light indigo chip-buttons), and a primary teal "🗓 Stundenplan Nr. 1 übernehmen" button (teal reused from 2B to mark it as the one true commit action, distinct from indigo navigation). Success banner (green, same tint as 2A's positive state) with counts ("60 Stundenpläne gesamt", "38 gültig" in green, "22 Konflikte" in red) and a pager (‹ 1/38 gültige ›, Nr. input). Neustart/Zurück row. Timetable: header row in light indigo, time column bold, populated cells get a green tint with module code, course ID, and delivery-mode tag (colors match 3A's Kompaktunterricht/Fernunterricht/2-wöchig tokens).

**Colors:** timetable populated cell bg `#dcfce7`/`#f0fdf4`-family green, header row `#eef1ff`, primary commit button `#0f766e` (teal), conflict count `#fdecea`/`#b3261e`.

### Option 5A — "Mehr Module" overlay panel, fixed
**Purpose:** Sub-panel (opened from 4A's "+ Mehr Module") for adding an already-completed module into the timetable; picking a tile shows its available course offerings below to accept.

**Fixes over the original screenshot:**
- Module codes were truncating ("!! Ko...") in narrow tiles — now every tile is wide enough to show the full code, no truncation, ever.
- Selected vs. flagged/conflicting tiles used near-identical teal/green/red at low contrast — now: selected = green fill + green border + ✓ icon; flagged/conflict = light red fill + small red circular "!" badge with a tooltip explaining the conflict; neutral/unselected = plain gray. All three are distinguishable at a glance and are never confused with each other.
- The background page behind the overlay was faded pastel (still legible, distracting) — now it's dimmed/desaturated and marked `pointer-events:none`, a standard modal-scrim pattern.
- Header keeps Zurück (back) and Abbruch (cancel, red) actions; tile grid below; course-detail list at the bottom (green rows with course ID, schedule, delivery-mode tag) with Anwenden (apply, green) / Schließen (close, red) actions — same treatment as 3A/4A's course-row and chip styling.

**Colors:** neutral tile `#f1f2f6`/border `#e0e3eb`; selected tile `#f0fdf4`/border `#22c55e`/text `#166534`; flagged tile `#fef2f2`/border `#f3b9b3`/text `#8f1f16`, warning badge `#b3261e`.

## Interactions & Behavior
- **3A specific:** the group-level accept/reject icons apply to every course variant in that card at once; each row can presumably also be toggled individually (not shown as a separate state in the mock — confirm exact row-level interaction model with product owner). Accepted rows show the green check + tinted background; a rejected state isn't mocked but should mirror the red/negative treatment used in 2A's status cards.
- Chips in "Besuchte Module" are **read-only** (history) — no click state.
- Chips in "Abgeschlossene Module" are **toggleable**: default = dashed outline/white bg (unselected); clicking selects it → solid accent fill + ✓ prefix + shadow lift; clicking again deselects.
- Selecting/deselecting an "Abgeschlossene" or category chip should update the footer's "Ausgewählt: X Module" / hour totals live.
- "Weiter →" advances to step 2 (Module) of the wizard; disable it if selections exceed the max (10/30 shown) — exact validation rule to be confirmed with product owner, not fully specified in these mocks.
- "Neustart" resets all selections back to the initial state (confirm dialog recommended, not shown in mock).
- Tabs (Stundenplan v2 / TT-Einträge / Importe / Fächer) are navigation to sibling views — not built out in this mock, just the active/inactive tab styling.

## State Management
- Student record (name, class, semester, religion, contact) — read-only display data.
- Category selections: religion / language / branch / elective — each single-select within its group.
- Module selections: list of selected "Abgeschlossene Module" codes.
- Derived: total selected module count, total hours, remaining capacity (shown as "Maximal 10/30").
- Status buckets (Negative/Frühere/Aktuelle/Zusätzliche) are likely server-derived from the student's academic record + current selections, not client state.

## Design Tokens
**Colors**
- Accent (2A): `#4f46e5` / `#4338ca` (indigo)
- Accent (2B): `#0f766e` / `#14b8a6` (teal)
- Success: `#15803d` on `#f0fdf4`, border `#bbf7d0`
- Error/negative: `#8f1f16` on `#fdecea`, border `#f3b9b3`
- Neutral text: `#1e2433` (headings), `#5b6472`/`#8991a3` (secondary)
- Neutral surfaces: `#f1f2f6`, `#f7f8fb`, page bg `#eef0f3`
- Borders: `#d7dae2`, `#e7e9ef`

**Spacing:** 8px chip gaps, 16px card-grid gaps, 24–32px section padding, 12–16px border radius on cards, chips ~8–10px radius.

**Typography:** Inter (Google Fonts), 400/500/600/700/800 weights. Base body ~14px, section headings 15px/700, page title 28px/800, small labels 11–12px/700 uppercase.

## Assets
No external images — avatars are initials-in-circle, no icons beyond a checkmark (✓) and a small legend square. No brand/logo assets used.
