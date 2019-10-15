<?php

function GetHelp($section, $botusername, $botid, $author = "") {
	$content = file_get_contents("help/$section.json");
	$content = str_replace(":id:", $botid, $content);
	$content = str_replace(":user:", $botusername, $content);
	$content = str_replace(":author:", $author, $content);
	return json_decode($content);
}

