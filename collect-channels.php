<?php

/* Botnix to discord connector for infobot.pm botnix module.
 * Connects to discord as a bot via the API and talks directly to the discord
 * module on a running instance of botnix on the same machine. If there is no instance
 * of botnix to connect to, the bot just answers with non-commital responses.
 *
 * Note that this does not fork as a demon and should be run via 'screen'.
 *
 * This bot uses an extensively modded version of team-reflex/discordphp - DO NOT
 * run "composer update" on it, or you'll lose compatibility changes with modern
 * discord API!
 *
 * - Brain#0001 Oct 2019.
 */

include __DIR__.'/vendor/autoload.php';
include __DIR__.'/globals.php';
include __DIR__.'/apiupdate.php';
include __DIR__.'/help.php';
include __DIR__.'/settings.php';
include __DIR__.'/infobot.php';

use Discord\Parts\User\Game;
use Discord\Parts\User\User;

/* Connect to discord */
$discord = new \Discord\Discord([
	'token' => $config['token'],
	'loadAllMembers'=>false,
	'retrieveBans'=>false,
]);

/* Discord API connection ready to serve! */
$discord->on('ready', function ($discord) use ($global_last_message) {
	echo "Bot '$discord->username#$discord->discriminator' ($discord->id) is ready.", PHP_EOL;
	foreach ($discord->guilds as $guild) {
		foreach ($guild->channels as $channel) {
			$dummy = getSettings($channel->id, $guild->id, ($channel->type == 0 ? '#' : '') . $channel->name, $channel->parent_id);
		}
		$total_members = $guild->members->count();
		$current = 0;
		foreach ($guild->members as $member) {
			global $conn;
			$safe_username = mysqli_real_escape_string($conn, $member->user->username);
			$safe_bot = $member->user->bot ? 1 : 0;
			mysqli_query($conn,"REPLACE INTO infobot_discord_user_cache (id,username,discriminator,avatar,bot) VALUES($member->id,'$safe_username','".$member->user->discriminator."','".$member->user->avatar."','$safe_bot')");
			printf("Caching members for '%s': %.0f%%...\r", $guild->name, ($current / $total_members) * 100);
			$current++;
		}
		print "\n";
	}
	exit(0);
});

$discord->run();

