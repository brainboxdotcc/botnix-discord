<?php

$global_last_message = null;

/* Updates immediately, doesnt matter if it spams botnix, it can take it ;-) */
$last_update_status = time() - 600;

/* First update an hour from now, prevents spamming the API if the bot is in a short crashloop */
$last_botsite_webapi_update = time() + 3600;

/* Current IRC nickname reported from botnix core, used to address bot via telnet session */
$ircnick = "";

/* Live config should be in the directory above this one. It's like this to make sure you never accidentally commit it to a repository. */
$config = parse_ini_file("../botnix-discord.ini");

$randoms = [];

$conn = mysqli_connect($config['dbhost'], $config['dbuser'], $config['dbpass']);
mysqli_select_db($conn, $config['db']);
