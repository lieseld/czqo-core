<?php

namespace App\Console;

use App\Jobs\ProcessRosterInactivity;
use App\Jobs\ProcessSessionLogging;
use App\Jobs\ProcessSessionReminders;
use App\Jobs\ProcessSoloCertExpiryWarnings;
use App\Models\Roster\RosterMember;
use App\Notifications\Network\OneWeekInactivityReminder;
use App\Notifications\Network\TwoWeekInactivityReminder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use RestCord\DiscordClient;


class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param \Illuminate\Console\Scheduling\Schedule $schedule
     *
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Activitybot session logging
        $schedule->job(new ProcessSessionLogging())->everyMinute();

        //Inactivity checks
        $schedule->job(new ProcessRosterInactivity())->cron('00 00 01 JAN,APR,JUL,OCT *');

        //CRONS FOR INACTIVITY EMAILS 2 weeks
        $schedule->call(function () {
            //Loop through controllers
            $count = 0;
            foreach (RosterMember::all() as $rosterMember) {
                //Do they meet the requirements?
                if (!$rosterMember->meetsActivityRequirement()) {
                    $count++;
                    $rosterMember->user->notify(new TwoWeekInactivityReminder($rosterMember, 'n/a'));
                }
            }

            //Tell Discord all about it
            $discord = new DiscordClient(['token' => config('services.discord.token')]);
            $discord->channel->createMessage(['channel.id' => 482817715489341441, 'content' => 'Sent '.$count.' two-week warning inactivity emails']);
        })->cron('00 00 16 MAR,JUN,SEP,DEC *'); // 2 weeks before end of quarter

        // 1 week
        $schedule->call(function () {
            //Loop through controllers
            $count = 0;
            foreach (RosterMember::all() as $rosterMember) {
                //Do they meet the requirements?
                if (!$rosterMember->meetsActivityRequirement()) {
                    $count++;
                    $rosterMember->user->notify(new OneWeekInactivityReminder($rosterMember, 'n/a'));
                }
            }
            
            //Tell Discord all about it
            $discord = new DiscordClient(['token' => config('services.discord.token')]);
            $discord->channel->createMessage(['channel.id' => 482817715489341441, 'content' => 'Sent '.$count.' one-week warning inactivity emails']);
        })->cron('00 00 23 MAR,JUN,SEP,DEC *'); // 1 week before end of quarter*/

        /// Monthly leaderboard wipe
        $schedule->call(function () {
            // Loop through all roster members
            foreach (RosterMember::all() as $rosterMember) {
                // Reset the hours for every member
                $rosterMember->monthly_hours = 0.0;
                $rosterMember->save();
            }
        })->monthlyOn(1, '00:00');

        // Quarterly Currency Wipe
        $schedule->call(function () {
            // Loop through all roster members
            foreach (RosterMember::all() as $rosterMember) {
                // Reset the hours for every member
                $rosterMember->currency = 0.0;
                $rosterMember->save();
            }
        })->cron('15 00 01 JAN,APR,JUL,OCT *');

        //Solo cert expiry warning
        $schedule->job(new ProcessSoloCertExpiryWarnings())->daily();

        //Training/OTS session reminders
        $schedule->job(new ProcessSessionReminders())->daily();

        // Discord role updating
        //$schedule->job(new UpdateDiscordUserRoles)->twiceDaily(6, 18);
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
