<?php
require_once("../vendor/autoload.php");

$environment = array_merge($_SERVER,$_ENV);
$redis = new Redis();
$redis->pconnect(
    $environment['REDIS_HOST'] ?? "redis",
$environment['REDIS_PORT'] ?? 6379
);

$matches = $redis->keys("swarm:*");
$fleet = [
    'Status' => "Okay",
];
foreach($matches as $match){
    $machine = explode(":", $match)[1];

    $thisMachine = $redis->hGetAll("swarm:{$machine}");

    foreach(explode(",", $thisMachine['labels']) as $label){
        $label = empty(trim($label)) ? "Unlabeled" : trim($label);
        foreach($thisMachine as $key => $value) {
            switch($key){
                case "updated_at":
                case "labels":
                    break;
                default:
                    if(isset($fleet['ByLabel'][$label][$key])){
                        $fleet['ByLabel'][$label][$key] += $value;
                    }else{
                        $fleet['ByLabel'][$label][$key] = $value;
                    }
            }
        }
    }

    $fleet['Machines'][$machine] = $thisMachine;
}
header('Content-type: application/json');
echo json_encode($fleet);

