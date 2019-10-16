<?php

function GetHelp($section, $botusername, $botid, $author = "") {
	if (file_exists("help/$section.json")) {
		$content = file_get_contents("help/$section.json");
		$content = str_replace(":id:", $botid, $content);
		$content = str_replace(":user:", $botusername, $content);
		$content = str_replace(":author:", $author, $content);
		return json_decode($content);
	} else {
		return ["color"=>0xff0000, "description"=>"No such help section '$section'!"];
	}
}

