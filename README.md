# botnix-discord
Discord connector for the botnix IRC bot

This is a connector that links the discord service to botnix and exposes the infobot.pm module to discord users.

You must have a discord account set up with a bot token ready to use, and your botnix instance must have a telnet port accessible via localhost (and ONLY localhost!).

Configure your bot by editing ../discord-botnix.ini, basing it upon the discord-botnix.ini.example included in this repository, then run the program with "php run.php".

The program will run in the console and will not daemonise, if you want to background this process you should use "screen" or similar.

Enjoy!

