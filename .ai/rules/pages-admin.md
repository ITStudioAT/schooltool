---
paths:
  - '{resources/css/admin-page-design.css,resources/js/pages/admin/**}'
---

# Pages Admin

## Keep signed-in admin pages aligned with the light Home design
Use the shared admin-page-design.css surfaces inside .admin-workspace: pale #f7f8fa background, full-width white page headers, restrained cards and dark readable text. Reuse AdminSectionHero/AdminCompactSectionHero with each module's own content. Keep the outer app bar/drawer and user school-color preference separate, preserve status colors and dense table/timetable widths, and do not apply these overrides to public authentication screens, dialogs or document canvases.

## Use the accepted branded header for registration and teaching
Anmeldetool and Lehrerbereich use AdminPageHeader: SchoolTool icon/name and location on the left, contextual metrics in the middle, current config school/user identity on the right. Other modules keep their existing header contents until explicitly requested. Registration opening status is scoped to the selected schoolyear (details use the selected register); distinguish unloaded/error state from no open systems. Teacher course/student counts and next lessons are scoped to the current selected teacher context (config.user after account switching), selected school and selected schoolyear; preserve existing student deduplication. Keep live date/time, lesson countdown and academic-year/semester progress in the separate responsive block below the teacher header. Registration sections and its Schoolyears menu-style variant follow the Unterricht navigation appearance without changing switching/edit-lock logic.

## Limit the branded ABA rollout to its main page
The ABA main page uses AdminPageHeader with SchoolTool branding, current section, current schoolyear, and dynamic school/user identity. Its overview/settings/schoolyear navigation follows the accepted Unterricht menu styling, preserving refresh, editing locks and the existing year dialog. Scope new menu CSS to .aba-nav only. ABA detail pages and evaluation/extraction subviews retain their existing headers and menus until explicitly requested.

## Keep the Restaurant branded header change on the landing page
Restaurant.vue uses AdminPageHeader only when main_action is overview, with SchoolTool branding, Restaurant location and dynamic current school/user. Restaurant navigation was explicitly excluded from this request. Preserve existing headers on other sections, all menu/tab styling and actions, and the outer schoolbar/sidebar unless a later explicit request expands the scope.

## Use the accepted branded header and navigation for Profile
The signed-in Profile page uses AdminPageHeader with Profil location and dynamic current school/user. Keep its header hidden when embedded. Its own navigation uses the Unterricht/Anmeldetool appearance while retaining existing profile, appearance, password, two-factor and Hopper sections and their selection/action handlers. Do not alter authentication, profile save/verification, personal shell-color preference or account switching behavior as part of cosmetic styling.
