<?php

namespace app\models;

class Player extends BaseModel
{

    static $connection = "default";
    static $table = "players";
    static $fields = [
        'id', 'name', 'created'
    ];
    static $relations = [
        'submissions' => ['type' => 'has_many', 'class' => Submission::class, 'local' => 'id', 'foreign' => 'player_id'],
    ];

    public static function list()
    {
    	$all = static::find();
    	$list = [];
    	foreach ($all as $p) {
    		$list[$p->id] = $p->name;
    	}
    	return $list;
    }

    public static function scoreboard()
    {
        $all = static::findAsArray([], ['with' => 'submissions']);
        foreach ($all as $key => &$player) {
            $score = 0; $stars = 0; $subs = 0;
            foreach ($player->submissions() as $sub) {
                if ($sub->accepted && $sub->hs) {
                    $score += $sub->score;
                    $stars += $sub->stars;
                    $subs += 1;
                } 
            }
            $player->subs = $subs;
            $player->score = $score;
            $player->stars = $stars;
        }
        return $all;
    }

    public static function scoreboardForSet($set)
    {
        $set = 1;
        $s = (int) $set;
        $q = "SELECT `p`.`id` AS `pid`, `name` AS `player`, `total` FROM `players` AS `p`
                LEFT JOIN (
                    SELECT `s`.`player_id` AS `pid`, SUM(`s`.`score`) AS `total`
                    FROM `submissions` AS `s`
                    LEFT JOIN `challenges` AS `c` ON (`s`.`challenge_id` = `c`.`id`)
                    WHERE `s`.`accepted` = 1 AND `s`.`hs` = 1 AND `c`.`setnr` = {$s}
                    GROUP BY `s`.`player_id`
                ) AS `inner` ON (`p`.`id` = `inner`.`pid`)
                WHERE `inner`.`total` > 0";
        $result = static::db()->query($q);

        $challenges_in_set = Challenge::findAsArray(['setnr' => $set]);
        $scoreboards = [];
        foreach ($challenges_in_set as $c) {
            $scoreboards[$c->id] = Submission::scoreboard($c->id);
        }
        $out = [];
        foreach ($result as $row) {
            $row['week'] = [];
            foreach ($scoreboards as $cid => $scores) {
                foreach ($scores as $sub) {
                    if ($row['pid'] == $sub->player_id) {
                        $row['week'][] = ['score' => $sub->score, 'stars' => $sub->stars];
                    }
                }
            }
            $out[] = $row;
        }
        return $out;
    }
}