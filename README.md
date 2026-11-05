# Laravel Scheduler on Windows (XAMPP)

The expired stock reservation cleanup is registered in `routes/console.php`. To run Laravel's scheduler every minute on this Windows/XAMPP machine:

1. Open **Task Scheduler** and choose **Import Task**.
2. Select `scheduler/BlessedStores-Laravel-Scheduler.xml`.
3. Review the action paths. The task expects PHP at `C:\xampp\php\php.exe` and this project at `C:\xampp\htdocs\blessedstore`.
4. Save the task, then use **Run** to start it immediately.
5. Confirm the task's **Last Run Result** is `0x0`. Laravel will invoke `stock-reservations:release-expired` every five minutes through its schedule.

For a different installation, update the PHP executable and project working directory in the XML before importing. The task uses the current Windows account and does not run while that account is logged off.

