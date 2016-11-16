<?php
define("WWW_ROOT",'http://lublin.eu/WydarzeniaCsv/');
define("CACHE","../../tmp/cache");

$type=$_GET['type'];
//$start_date=$_GET['start_date'];
//$end_date=$_GET['end_date'];

//$date=strtotime($_GET['start_date']);
//$date_new=date('d-m-Y',$date);

$start_date=new Datetime($_GET['start_date']);
$end_date=new Datetime($_GET['end_date']);


$C=curl_init();

$options=Array(
CURLOPT_URL=>WWW_ROOT,
CURLOPT_RETURNTRANSFER=>1,
CURLOPT_REFERER=>WWW_ROOT,
CURLOPT_POST=>1,
CURLOPT_POSTFIELDS=>'dzien-csv='.$start_date->format('d-m-Y').'&kategoria-csv=34',
);

curl_setopt_array($C,$options);

$key="date".$start_date->format('d-m-Y');
$out=apc_fetch($key,$good);
if (!$good) {
    $r=curl_exec($C);
    $tab=explode("\n",$r);
    unset($tab[0]); //naglowek
    $out=Array();
    foreach($tab as $csv) {
	$x=explode(';',$csv);
	if (@!$x[1]) continue;
	$t['name']=$x[0];
	$t['place']=$x[5];
	$t['who']=$x[6];
	$t['contact']=$x[7];
	$t['time_start']=$x[1];
	$t['date_start']=$x[2];
	$t['time_stop']=$x[3];
	$t['date_stop']=$x[4];
	$hash=(int)(crc32($t['name'].$t['date_start'])/10);
	$t['hash']=$hash;
	apc_store("hash".$hash,$t,3600);
	if ($t['date_stop']) 
		$out['groups'][]=$t;
	else 
		$out['events'][]=$t;
    }
    apc_store($key,$out,3600);
}

switch($type) {
    case 'events_and_groups':
	$xml='<?xml version="1.0" encoding="UTF-8"?><events_and_groups></events_and_groups>';
	$x=new SimpleXMLElement($xml);
	$x->addChild('events_groups');
	$x->addChild('events');
	$cnt=1;
	foreach($out['events'] as $event) {
	    $tmp=$x->events->addChild('event');
	    $tmp->addAttribute('id',$event['hash']);
	    $tmp->addAttribute('start_date',$event['date_start']);
	    $tmp->addAttribute('start_time',substr($event['time_start'],0,5));
	    $tmp->addChild('name',$event['name']);
	    $tmp->addChild('category','spektakl');
	    $tmp->addChild('description',$event['date_start'].' g. '.substr($event['time_start'],0,5)."\n".'<br>'.$event['who']);
	    $tmp->addChild('location',$event['place']);
	    $tmp->addChild('image_url','http://lublin.eu/szablony/portal/images/herb.png');
	}
	foreach($out['groups'] as $event) {
	    $tmp=$x->events_groups->addChild('events_group');
	    $tmp->addAttribute('id',$event['hash']);
	    $tmp->addAttribute('start_date',$event['date_start']);
	    $tmp->addAttribute('start_time',substr($event['time_start'],0,5));
	    $tmp->addAttribute('end_date',$event['date_stop']);
	    $tmp->addAttribute('end_time',substr($event['time_stop'],0,5));
	    $tmp->addChild('name',$event['name']);
	    $tmp->addChild('category','spektakl');
	    $tmp->addChild('description',$event['date_start'].' g. '.substr($event['time_start'],0,5)."\n".'<br>'.$event['who']);
	    $tmp->addChild('location',$event['place']);
	    $tmp->addChild('image_url','http://lublin.eu/szablony/portal/images/herb.png');
	}
	break;
case 'events_count':
    $xml='<?xml version="1.0" encoding="UTF-8"?><events_count></events_count>';
    $x=new SimpleXMLElement($xml);
    $start=new Datetime($_GET['start_date']);
    $stop=new Datetime($_GET['end_date']);
    $period=new DatePeriod($start,new DateInterval('P1D'),$stop);
    foreach($period as $d){
    $tmp=$x->addChild('date_count');
    $tmp->addAttribute('date',$d->format('Y-m-d'));
    $tmp->addAttribute('count','1');
    }
    break;
case 'event':
    if (!$hash=$_GET['id']) die('error');
    $event=apc_fetch("hash".$hash);
    $xml='<?xml version="1.0" encoding="UTF-8"?><event></event>';
    $x=new SimpleXMLElement($xml);
    $x->addAttribute('id',$hash);
    $x->addAttribute('start_date',$event['date_start']);
    $x->addAttribute('start_time',substr($event['time_start'],0,5));
    $x->addChild('name',$event['name']);
    $x->addChild('category','spektakl');
    $x->addChild('description',$event['date_start'].' g. '.substr($event['time_start'],0,5)."\n".'<br>'.$event['who']);
    $x->addChild('location',$event['place']);
    $x->location->addAttribute('latitude','51.25');
    $x->location->addAttribute('longitude','22.55');
    $x->addChild('image_url','http://lublin.eu/szablony/portal/images/loga/logo.png');
    $x->addChild('portal_url','http://lublin.eu/');
    break;
}

header('Content-type: text/xml');
echo $x->asXML();


