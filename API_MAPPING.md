# API Structure Mapping

This file maps the previous API endpoint locations to the new structure, which is now categorized under `admin` and `student`.

## Student Features
| Feature | Previous Location | New Location |
|---------|------------------|--------------|
| Auth: Login | `api/auth/login.php` | `api/auth/login.php` |
| Auth: Register | `api/auth/register.php` | `api/auth/register.php` |
| Announcements: Published List | `api/announcements/read.php` | `api/student/announcements/read.php` |
| Announcements: Published Detail | — | `api/student/announcements/read_single.php` |
| Labs: Read All | `api/lab/read.php` | `api/lab/read.php` |
| Profile: Read | `api/student/read_single.php` | `api/student/profile/read.php` |
| Profile: Update | `api/student/update.php` | `api/student/profile/update.php` |
| Profile: Upload Pic | `api/student/upload_profile.php` | `api/student/profile/upload_pic.php` |
| Sit-In: Time-In | `api/sitin/time_in.php` | `api/student/sitin/time_in.php` |
| Sit-In: Time-Out | `api/sitin/time_out.php` | `api/student/sitin/time_out.php` |
| Sit-In: End Session | `api/sitin/end_session.php` | `api/student/sitin/end_session.php` |
| Sit-In: History | `api/sitin/read_by_student.php` | `api/student/sitin/read.php` |
| Sit-In: Personal Stats | — | `api/student/sitin/stats.php` |
| Sit-In: Current | `api/sitin/read_single.php` | `api/student/sitin/read_single.php` |
| Notifications: Personal List | — | `api/student/notifications/read_all.php` |
| Notifications: Unread Count | — | `api/student/notifications/unread_count.php` |
| Notifications: Mark Read | — | `api/student/notifications/mark_read.php` |
| Notifications: Mark All Read | — | `api/student/notifications/mark_all_read.php` |

## Admin Features
| Feature | Previous Location | New Location |
|---------|------------------|--------------|
| Auth: Login | `api/auth/login.php` | `api/auth/login.php` |
| Dashboard Stats | `api/admin/dashboard_stats.php` | `api/admin/dashboard_stats.php` |
| Announcements (CRUD) * | `api/admin/announcements/` | `api/admin/announcements/` |
| Notifications | `api/admin/notifications/` | `api/admin/notifications/` |
| Sit-In: All Logs | `api/sitin/read.php` | `api/admin/sitin/read.php` |
| Sit-In: Active | `api/sitin/read_active.php` | `api/admin/sitin/read_active.php` |
| Sit-In: Create | `api/sitin/create.php` | `api/admin/sitin/create.php` |
| Sit-In: Feedback * | `api/admin/sitin/feedback.php` | `api/admin/sitin/feedback.php` |
| Student: Delete | `api/student/delete.php` | `api/admin/student/delete.php` |
| Student: Read All | `api/student/read.php` | `api/admin/student/read.php` |
| Student: Reset | `api/student/reset_sessions.php` | `api/admin/student/reset_sessions.php` |
| Student: Search | `api/student/search.php` | `api/admin/student/search.php` |

*\* These actions now automatically trigger student-side notifications.*
