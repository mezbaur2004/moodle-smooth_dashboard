# Smooth dashboard (block_smooth_dashboard)

A Moodle Dashboard block that shows a student their day at a glance, across all their courses,
in a clean card layout that works on phones and in the Moodle app.

| Section | What it shows |
|---|---|
| **Greeting** | "Good morning, Rafi", today's date and a one-line summary: classes today, things due soon |
| **Next class** | The next Zoom class with a live countdown, *Starting soon* from 15 minutes before, *Live now* with the time left while it runs, and a **Join** button. When it ends, the card moves on to the following class by itself |
| **Coming up** | The Zoom classes after that one, with day and time |
| **Due soon** | Quizzes and assignments still to do, overdue ones first, each with its status (*Overdue*, *Today*, *Tomorrow*, *In 3 days*) and its action (*Add submission*, *Attempt quiz now*) |
| **My courses** | A card per course in progress: completion progress, how many activities are left, the next ones in course order (*Up next*), Zoom attendance and leaderboard place, and a **Continue** button to the first activity left |
| **Leaderboards** | Per course with a leaderboard: the top 3 and the student's own place |

Every section can be switched off for the whole site. A section with nothing to show shows a short
friendly message (*All caught up*, *No classes scheduled*). Statuses are always written out, so
nothing relies on colour alone, and the ticking countdown is hidden from screen readers, which
read the start time instead.

Teachers see the same layout: their classes, the grading the calendar asks of them, their courses
(without progress, which Moodle only tracks for students) and the leaderboards.

## Requirements

- Moodle 5.0 or later (tested on 5.0.12)
- Optional, each adds its section when installed:
  - [mod_zoom](https://github.com/ncstate-delta/moodle-mod_zoom) for *Next class* and *Coming up*
  - [mod_leaderboard](https://github.com/mezbaur2004/moodle-leaderboard) for leaderboard places
  - [local_zoomattendance](https://github.com/mezbaur2004/moodle-local_zoomattendance) for attendance

## Installing

The folder must be called `smooth_dashboard`:

| Moodle version | Folder |
|---|---|
| 5.0 | `blocks/smooth_dashboard` |
| 5.1 and later | `public/blocks/smooth_dashboard` |

Then visit *Site administration > Notifications* (or run `php admin/cli/upgrade.php`).

To show it to everyone: *Site administration > Appearance > Default Dashboard page*, turn editing
on, add the *Smooth dashboard* block, move it to the top of the main region, then
*Reset Dashboard for all users*. The block hides its own header outside editing mode: the
greeting is the heading.

The block only goes on the Dashboard. One per page.

## Settings

*Site administration > Plugins > Blocks > Smooth dashboard*

| Setting | Default | Meaning |
|---|---|---|
| Greeting, Next class, Coming up, Due soon, My courses, Leaderboards, Zoom attendance | on | Switch each section on or off |
| Accent colour | theme's primary colour | Next class card, buttons and progress bars |
| Second accent colour | violet | The Next class card fades into it |
| Classes listed | 5 | Classes in *Coming up* after the next one |
| Classes ahead (days) | 14 | How far ahead to look for classes |
| Activity types | Assignment, Quiz | Which activities *Due soon* lists |
| Due soon ahead (days) | 14 | How far ahead to look for due dates |
| Overdue kept (days) | 7 | How long overdue items stay listed (while they can still be submitted) |
| Items listed | 6 | Most items in *Due soon* |
| Courses shown | 6 | Course cards, the most recently visited course first |
| Activities listed per course | 3 | Activities under *Up next* on each card |

## Where the data comes from

The block stores nothing of its own. It reads:

- **Classes:** the calendar events mod_zoom writes, one per scheduled class (a single meeting, or
  each occurrence of a recurring meeting with a fixed time). Recurring meetings without a fixed
  time have no schedule, so they are not listed. Only classes of Zoom activities the student can
  open appear, so hidden activities and access restrictions are respected. **Join** opens
  mod_zoom's own join link.
- **Due soon:** Moodle's calendar action events, the same API as the Timeline block. Each student's
  extensions and user or group overrides are already applied, and items drop out once they are
  submitted or attempted.
- **Courses:** the student's courses in progress, as *My courses* classifies them (courses they
  removed from view there are left out). Progress and *Up next* come from activity completion.
- **Leaderboards:** `\mod_leaderboard\api::find_course_leaderboard()` and
  `\mod_leaderboard\output\board::export_summary(3)`, so the leaderboard's access checks, names
  and anonymous rules apply.
- **Attendance:** the student's *Course overall* from local_zoomattendance, coloured by its
  thresholds (75 % / 50 % by default) and labelled *Low* below the lower one. Cached per user for up
  to an hour, and rebuilt as soon as local_zoomattendance's data changes.

## Moodle app

The block also shows on the Moodle app's Dashboard, with the same sections as native app cards
and lists, and the countdown ticking. Tapping a class, activity or course opens it in the app.
The site needs mobile services enabled. After installing or upgrading, purge Moodle's caches;
app users may need to log out and in again.

## Theme

The block uses its own scoped styles (everything under `.block_smooth_dashboard .sd`), with inline
icons, so it looks the same in Boost and in other themes such as New Learning. It picks up the
theme's Bootstrap colours where the theme defines them, and has darker-mode colours for themes
that set `data-bs-theme="dark"` (not yet tested with a dark theme).

## Privacy

The block stores no personal data.

## For developers

```sh
# PHPUnit
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_smooth_dashboard_testsuite

# JavaScript (after changing amd/src)
npx grunt amd --root=blocks/smooth_dashboard
```

## License

GNU GPL v3 or later. Copyright 2026 Pedago Academy.
