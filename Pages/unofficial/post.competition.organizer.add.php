<?php

$wcaid = db::escape(filter_input(INPUT_POST, 'wcaid'));
$person = wcaapi::getPerson($wcaid, __FILE__,[],false);
if($person->person ?? FALSE){
    competitor\actual($person->person);
}

db::exec("INSERT IGNORE INTO `unofficial_organizers`(competition, wcaid) VALUES ('$comp->id',UPPER('$wcaid'))");
