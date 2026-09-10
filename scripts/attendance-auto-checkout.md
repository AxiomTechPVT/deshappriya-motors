Attendance midnight checkout
============================

`attendance-auto-checkout.php` closes checked-in Present / Half Day records
whose attendance date is before today in Asia/Colombo. The recorded checkout
is 23:59:59 (the attendance table stores a time without a checkout date).
Existing checkout times, today's records and absent records are unchanged.

On this Windows installation, Task Scheduler task
`DeshappriyaAttendanceAutoCheckout` runs the script every minute using
`C:\xampp\php\php.exe`. Its last verified result was 0 (success).
The task runs as the current Windows user and requires that user to be logged
in. Windows power settings may pause it on battery. MySQL must be running.
The employee module also closes overdue records on its next load, covering
missed executions while the computer or database was off.

For another deployment, schedule this CLI command every minute under the
application's ordinary service account (adjust paths):

    C:\xampp\php\php.exe C:\xampp\htdocs\deshappriya-motors\scripts\attendance-auto-checkout.php

Do not expose this script as an HTTP endpoint. It rejects non-CLI requests.
