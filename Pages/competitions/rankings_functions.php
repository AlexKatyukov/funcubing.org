<?php

namespace unofficial;

function getRankedRatings() {
    $sql_average = "
    select 
        unofficial_competitions.name competition_name,
        unofficial_competitions.id competition_id,
        coalesce(unofficial_competitions.rankedID, unofficial_competitions.secret) competition_secret,
        unofficial_competitions.date,
        unofficial_competitors.id competitor_id,
        trim(unofficial_competitors.name) competitor_name,
        unofficial_competitors.FCID FCID,
        unofficial_events_dict.id event_id,
        coalesce(unofficial_competitors_result.average,unofficial_competitors_result.mean) result,
        unofficial_competitors_result.order,
        unofficial_competitors_result.attempt1,
        unofficial_competitors_result.attempt2,
        unofficial_competitors_result.attempt3,
        unofficial_competitors_result.attempt4,
        unofficial_competitors_result.attempt5,
        unofficial_competitors_round.id round_id,
        unofficial_events_rounds.round round,
        unofficial_fc_wca.wcaid wcaid,
        unofficial_fc_wca.nonwca
    from `unofficial_competitors_result` 
    join `unofficial_competitors_round`  on unofficial_competitors_result.competitor_round=unofficial_competitors_round.id
    join `unofficial_competitors`  on unofficial_competitors.id=unofficial_competitors_round.competitor
    join `unofficial_events_rounds`  on unofficial_events_rounds.id=unofficial_competitors_round.round
    join `unofficial_events` on unofficial_events.id=unofficial_events_rounds.event
    join `unofficial_events_dict` on unofficial_events_dict.id=unofficial_events.event_dict
    join `unofficial_competitions` on unofficial_competitions.id=unofficial_events.competition
    LEFT OUTER JOIN unofficial_fc_wca on unofficial_fc_wca.FCID = unofficial_competitors.FCID
    where unofficial_competitions.ranked = 1 
        and unofficial_competitions.show = 1 
        and unofficial_events_dict.special = 0
        and coalesce(
            unofficial_competitors_result.average,
            unofficial_competitors_result.mean,
            'dnf') <> 'dnf'
        and coalesce(
            unofficial_competitors_result.average,
            unofficial_competitors_result.mean,
            '-cutoff') <> '-cutoff'
        and (unofficial_competitors_result.average <>'' or unofficial_competitors_result.mean <>'' )
        and  coalesce(unofficial_competitors.FCID,'')<>''  
    order by unofficial_competitors_result.order,
        unofficial_competitions.date 
    ";
    $sql_best = "
    select 
        unofficial_competitions.name competition_name,
        unofficial_competitions.id competition_id,
        coalesce(unofficial_competitions.rankedID, unofficial_competitions.secret) competition_secret,
        unofficial_competitions.date,
        unofficial_competitors.id competitor_id,
        trim(unofficial_competitors.name) competitor_name,
        unofficial_competitors.FCID FCID,
        unofficial_events_dict.id event_id,
        unofficial_competitors_result.best result,
        cast(replace(replace(best,'.',''),':','') as UNSIGNED) as 'order',
        unofficial_competitors_round.id round_id,
        unofficial_events_rounds.round round,
        unofficial_fc_wca.wcaid wcaid,
        unofficial_fc_wca.nonwca
    from `unofficial_competitors_result` 
    join `unofficial_competitors_round`  on unofficial_competitors_result.competitor_round=unofficial_competitors_round.id
    join `unofficial_competitors`  on unofficial_competitors.id=unofficial_competitors_round.competitor
    join `unofficial_events_rounds`  on unofficial_events_rounds.id=unofficial_competitors_round.round
    join `unofficial_events` on unofficial_events.id=unofficial_events_rounds.event
    join `unofficial_events_dict` on unofficial_events_dict.id=unofficial_events.event_dict
    join `unofficial_competitions` on unofficial_competitions.id=unofficial_events.competition
    LEFT OUTER JOIN unofficial_fc_wca on unofficial_fc_wca.FCID = unofficial_competitors.FCID
    where unofficial_competitions.ranked = 1 
        and unofficial_competitions.show = 1
        and unofficial_events_dict.special = 0
        and coalesce(
            unofficial_competitors_result.best,
            'dnf') <> 'dnf'
        and unofficial_competitors_result.best <>''
        and coalesce(unofficial_competitors.FCID,'')<>''
    order by cast(replace(replace(best,'.',''),':','') as UNSIGNED),
        unofficial_competitions.date ";

    $average = \db::rows($sql_average);
    $best = \db::rows($sql_best);
    $result_current = [];
    foreach ($average as $row) {
        if (!isset($result_current[$row->event_id]['average'][$row->FCID])) {
            $result_current[$row->event_id]['average'][$row->FCID] = $row;
        }
    }
    foreach ($best as $row) {
        if (!isset($result_current[$row->event_id]['best'][$row->FCID])) {
            $result_current[$row->event_id]['best'][$row->FCID] = $row;
        }
    }

    foreach ($result_current as $event => $event_ratings) {
        foreach ($event_ratings as $type => $type_ratings) {
            $order = 1;
            $count = 1;
            $prev_result = false;
            foreach ($type_ratings as $competitor => $rating) {
                if ($rating->result != $prev_result) {
                    $order = $count;
                    $prev_result = $rating->result;
                }
                $count++;
                $result_current[$event][$type][$competitor]->order = $order;
            }
        }
    }

    $result_history = [];
    $result_record_competition = [];
    $result_record_attempt = [];
    $orders = [];

    $history_average = $average;
    usort($history_average, function($a, $b) {
        return
        $a->date == $b->date ? $a->order > $b->order : $a->date > $b->date;
    });
    $history_best = $best;
    usort($history_best, function($a, $b) {
        return
        $a->date == $b->date ? $a->order > $b->order : $a->date > $b->date;
    });

    foreach ($history_average as $row) {
        if (!isset($result_history[$row->event_id]['average'][$row->date])
                and $row->order < ($orders[$row->event_id]['average'] ?? 99999)) {
            $orders[$row->event_id]['average'] = $row->order;
            $result_history[$row->event_id]['average'][$row->date] = $row;
            $row->type = 'average';
            $result_record_competition[$row->competition_id][$row->event_id][] = $row;
            $result_record_attempt['best'][] = $row->round_id;
        }
    }
    foreach ($history_best as $row) {
        if (!isset($result_history[$row->event_id]['best'][$row->date])
                and $row->order < ($orders[$row->event_id]['best'] ?? 99999)) {
            $orders[$row->event_id]['best'] = $row->order;
            $result_history[$row->event_id]['best'][$row->date] = $row;
            $row->type = 'best';
            $result_record_competition[$row->competition_id][$row->event_id][] = $row;
        }
    }

    return[
        'current' => $result_current,
        'history' => $result_history,
        'record_competition' => $result_record_competition
    ];
}

function getRankedRecordbyCompetition($competition_id) {
    $record = getRankedRatings()['record_competition'][$competition_id] ?? [];
    return $record;
}

function getRankedCompetitions($competitor_fcid = false) {
    $where_fcid = false;
    if ($competitor_fcid) {
        $where_fcid = " and unofficial_competitions.id in (select competition from unofficial_competitors where unofficial_competitors.FCID='$competitor_fcid') ";
    }
    $sql = "SELECT
        unofficial_competitions.website,
        unofficial_competitions.id,
        unofficial_competitions.show, 
        unofficial_competitions.competitor,
        coalesce(unofficial_competitions.rankedID, unofficial_competitions.secret) secret,
        unofficial_competitions.name,
        unofficial_competitions.details,
        unofficial_competitions.date,
        unofficial_competitions.date_to,
        unofficial_competitions.ranked,
        unofficial_competitions.rankedApproved approved,
        (date(unofficial_competitions.date) > current_date) upcoming,
        (date(unofficial_competitions.date) = current_date 
        or date(unofficial_competitions.date_to) = current_date ) run,
        dict_competitors.name competitor_name,
        dict_competitors.country competitor_country,
        competition_competitors.count competitors
    FROM unofficial_competitions
    JOIN dict_competitors on dict_competitors.wid = unofficial_competitions.competitor 
    LEFT OUTER JOIN (select count(*) count, uc.competition
        FROM unofficial_competitors uc
        WHERE coalesce(uc.FCID,'')<>''
        GROUP BY uc.competition
        ) competition_competitors ON competition_competitors.competition = unofficial_competitions.id
    WHERE  unofficial_competitions.ranked = 1 
        and unofficial_competitions.show = 1
        AND unofficial_competitions.id not in(" . \config::get('MISC', 'competition_exclude') . ")
        " . $where_fcid . "
    ORDER BY unofficial_competitions.date DESC
    ";

    return \db::rows($sql);
}

function getCompetitorRankings($competitor_fcid) {
    if (!strlen($competitor_fcid) == 4) {
        return false;
    }
    return \db::row("SELECT "
                    . " unofficial_competitors.ID, "
                    . " unofficial_competitors.name, "
                    . " unofficial_competitors.FCID "
                    . " FROM unofficial_competitors"
                    . " WHERE upper(unofficial_competitors.FCID) = upper('$competitor_fcid') limit 1");
}

function getResutsByCompetitorRankings($competitor_fcid) {
    return \db::rows("SELECT"
                    . " unofficial_events_dict.id event_dict,"
                    . " COALESCE(unofficial_events_dict.name, unofficial_events.name) event_name,"
                    . " unofficial_events_dict.image event_image,"
                    . " unofficial_events_rounds.round,"
                    . " unofficial_competitors_result.place, "
                    . " unofficial_competitions.id competition_id, "
                    . " unofficial_competitions.name competition_name, "
                    . " unofficial_competitions.date competition_date_to, "
                    . " unofficial_competitions.date competition_date_from, "
                    . " coalesce(unofficial_competitions.rankedID, unofficial_competitions.secret) secret,"
                    . " CASE WHEN unofficial_events_rounds.round = unofficial_events.rounds THEN 1 ELSE 0 END final,"
                    . " CASE WHEN unofficial_events.rounds = unofficial_events_rounds.round AND unofficial_competitors_result.place<=3 and upper(unofficial_competitors_result.best)!='DNF' THEN 1 ELSE 0 END podium,"
                    . "unofficial_rounds_dict.name round_name,  "
                    . " unofficial_competitors_result.attempt1, "
                    . " unofficial_competitors_result.attempt2, "
                    . " unofficial_competitors_result.attempt3, "
                    . " unofficial_competitors_result.attempt4, "
                    . " unofficial_competitors_result.attempt5, "
                    . " unofficial_competitors_result.average, "
                    . " unofficial_competitors_result.mean, "
                    . " unofficial_competitors_result.best,"
                    . " unofficial_competitors.name competitor_name,"
                    . " unofficial_competitors.id competitor_id, "
                    . " unofficial_competitors_round.id result_id"
                    . " FROM unofficial_competitions "
                    . " JOIN unofficial_competitors ON unofficial_competitors.competition = unofficial_competitions.id "
                    . " JOIN unofficial_competitors_round ON unofficial_competitors_round.competitor = unofficial_competitors.id"
                    . " JOIN unofficial_competitors_result ON unofficial_competitors_result.competitor_round = unofficial_competitors_round.id"
                    . " JOIN unofficial_events_rounds ON unofficial_events_rounds.id = unofficial_competitors_round.round"
                    . " JOIN unofficial_events ON unofficial_events.id = unofficial_events_rounds.event"
                    . " JOIN unofficial_events_dict on unofficial_events_dict.id = unofficial_events.event_dict "
                    . " JOIN unofficial_rounds_dict on unofficial_rounds_dict.id = unofficial_events_rounds.round "
                    . " WHERE unofficial_competitors.FCID = '$competitor_fcid'"
                    . " AND unofficial_competitions.ranked = 1 "
                    . " AND unofficial_competitions.show = 1 "
                    . " AND unofficial_events_dict.special = 0 "
                    . " AND unofficial_competitions.id not in(" . \config::get('MISC', 'competition_exclude') . ")"
                    . " ORDER BY "
                    . " unofficial_events_dict.name,"
                    . " unofficial_events_rounds.round DESC");
}

function getRankedCompetitionsbyCompetitor($competitor_fcid) {
    return
            getRankedCompetitions($competitor_fcid);
}

function getRankedCompetitors() {
    return \db::rows("SELECT"
                    . " count(distinct unofficial_competitions.id) competitions, "
                    . " min(unofficial_competitors.name) name, "
                    . " unofficial_competitors.FCID, "
                    . " unofficial_fc_wca.wcaid, "
                    . " unofficial_fc_wca.nonwca "
                    . " FROM unofficial_competitions "
                    . " JOIN unofficial_competitors ON unofficial_competitors.competition = unofficial_competitions.id "
                    . " LEFT OUTER JOIN unofficial_fc_wca on unofficial_fc_wca.FCID = unofficial_competitors.FCID "
                    . " WHERE unofficial_competitors.FCID is not null and  unofficial_competitors.FCID<>''"
                    . " AND unofficial_competitions.show = 1 and unofficial_competitions.ranked = 1 "
                    . " GROUP BY unofficial_competitors.FCID, unofficial_fc_wca.wcaid "
                    . " ORDER BY 2 ");
}

function getFCIDlistbyName($name) {
    $FCIDlist = [];
    foreach (\db::rows("select FCID "
            . "from `unofficial_competitors` "
            . "where upper(trim(name)) = upper(trim('$name')) "
            . "and coalesce(FCID,'')<>''") as $FCID) {
        $FCIDlist[] = $FCID->FCID;
    }
    return $FCIDlist;
}

function getRankedJudges($filter = null) {
    $RU = t('', 'RU');
    $where = '';
    if (isset($filter['is_archive'])) {
        $where .= ' and is_archive = ' . ($filter['is_archive'] + 0);
    }
    $sql = "
        select     
            coalesce(dc.name$RU ,dc.name) name,
            dc.name nameEN,
            dc.nameRU nameRU,   
            j.rank$RU rank,
            j.rank rankEN,
            j.rankRU rankRU,
            j.is_archive,
            j.wcaid
            from `unofficial_judges` j
            left outer join `dict_competitors` dc on dc.wcaid = j.wcaid
            where 1=1 $where
            order by coalesce(dc.name$RU ,dc.name)
    ";

    return \db::rows($sql);
}

function getJudgeRolesDict() {
    $RU = t('', 'RU');
    $rows = \db::rows("SELECT "
                    . " id, role$RU role"
                    . " FROM unofficial_judge_roles_dict "
                    . " ORDER BY id");
    $roles_dict = [];
    foreach ($rows as $row) {
        $roles_dict[$row->id] = $row;
    }
    return $roles_dict;
}

function existsCompetitionEvent($competition_id, $event_id) {
    return \db::rows("SELECT 1 "
                    . " FROM unofficial_events"
                    . " WHERE competition = $competition_id and event_dict = $event_id");
}

function rus_lat($rus) {
    $map = [
        'А' => 'A',
        'Б' => 'B',
        'В' => 'V',
        'Г' => 'G',
        'Д' => 'D',
        'Е' => 'E',
        'Ё' => 'E',
        'Ж' => 'Z',
        'З' => 'Z',
        'И' => 'I',
        'Й' => 'I',
        'К' => 'K',
        'Л' => 'L',
        'М' => 'M',
        'Н' => 'N',
        'О' => 'O',
        'П' => 'P',
        'Р' => 'R',
        'С' => 'S',
        'Т' => 'T',
        'У' => 'U',
        'Ф' => 'F',
        'Х' => 'H',
        'Ч' => 'C',
        'Ц' => 'C',
        'Ш' => 'S',
        'Щ' => 'S',
        'Ъ' => 'X',
        'Ы' => 'I',
        'Ъ' => 'X',
        'Э' => 'E',
        'Ю' => 'U',
        'Я' => 'A'
    ];

    if (!$rus) {
        return 'X';
    }
    return $map[strtoupper($rus)] ?? $rus;
}

function get_wca($FCID) {
    if ($FCID) {
        $wca = \db::row("SELECT wcaid id, nonwca "
                        . " FROM unofficial_fc_wca"
                        . " WHERE lower(FCID) = lower('$FCID')");
        if ($wca->id ?? false) {
            $wca->nonwca = false;
        }
        return $wca;
    }
}

function get_wca_person($wca_id) {
    $person = json_decode(\db::row("SELECT value FROM wca_api_cash WHERE `key`='persons/$wca_id:'")->value ?? null)->person ?? null;
    if (!$person) {
        \wcaapi::getPerson($wca_id, __FILE__);
        $person = json_decode(\db::row("SELECT value FROM wca_api_cash WHERE `key`='persons/$wca_id:'")->value ?? null)->person ?? null;
    }
    return $person;
}

function set_wca($FCID, $wcaid, $nonwca, $current_FCID = false) {
    $wcaid = strtoupper($wcaid);
    \db::exec("INSERT IGNORE INTO unofficial_fc_wca (FCID,wcaid,nonwca) values ('$FCID','$wcaid',$nonwca)");

    if ($current_FCID != $FCID) {
        \db::exec("DELETE from  unofficial_fc_wca where FCID='$current_FCID'");
    }

    \db::exec("UPDATE unofficial_fc_wca
        SET wcaid = '$wcaid', nonwca = $nonwca
        WHERE FCID='$FCID'");
}
