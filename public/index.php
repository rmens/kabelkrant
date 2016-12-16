<?php

define('NEWS_ITEM_LIMIT', 10);
define('ONGOING_EVENT_LIMIT', 5);
define('UPCOMING_EVENT_LIMIT', 3);
define('USE_THUMBOR', true);
define('THUMBOR_BASEURL', "http://icecast.zuidwestfm.nl:8000/unsafe/560x440/smart/filters:format(webp)/");

setlocale(LC_ALL, 'nl_NL.utf8');

require_once __DIR__ . '/../vendor/autoload.php';

function getImageUrl($url) {if(USE_THUMBOR) {return THUMBOR_BASEURL . urlencode($url); } return $url;}

$today = new DateTime();
$today->setTime(0, 0, 0);
$tomorrow = new DateTime('tomorrow');
$tomorrow->setTime(0, 0, 0);

$vandaagOpTv = null;
$tvgids = file_get_contents('http://www.zuidwesttv.nl/feeds/tvgids');
$tvgids = simplexml_load_string($tvgids);
foreach ($tvgids->xpath('//item') as $item) {
    $date = new DateTime($item->date);

    if ($date->format('Y-m-d') == $today->format('Y-m-d')) {
        $vandaagOpTv = (string)$item->title;
        break;
    }
}

$morgenOpTv = null;
foreach ($tvgids->xpath('//item') as $item) {
    $date = new DateTime($item->date);

    if ($date->format('Y-m-d') == $tomorrow->format('Y-m-d')) {
        $morgenOpTv = (string)$item->title;
        break;
    }
}

$nuOpFm = file_get_contents("http://www.zuidwesttv.nl/teksttv/rds_programma.php")

class NewsItem
{
    public $title;
    public $date;
    public $content;
    public $photo;

    public function isNews()
    {
        return true;
    }
}


$items = [];

$nieuws = file_get_contents('http://www.zuidwesttv.nl/feeds/nieuws');
$nieuws = simplexml_load_string($nieuws);
$i = 0;
foreach ($nieuws->xpath('//item') as $nieuwsitem) {
    $item = new NewsItem();
    $item->title = (string)$nieuwsitem->title;
    $item->date = DateTime::createFromFormat('D, d M Y H:i:s T', (string)$nieuwsitem->pubDate);
    $item->content = (string)$nieuwsitem->tekst;

    $photo = (string)$nieuwsitem->photo;
    if (!empty($photo)) {
        $item->photo = getImageUrl($nieuwsitem->photourl . $photo);
    }

    $items[] = $item;

    $i++;
    if ($i === NEWS_ITEM_LIMIT) {
        break;
    }
}

class EventItem
{
    public $title;
    public $description;
    public $photo;
    public $location;
    public $date;

    public function isNews()
    {
        return false;
    }
}

$ongoingEvents = [];
$upcomingEvents = [];
$agenda = file_get_contents('http://www.zuidwesttv.nl/feeds/agenda');
$agenda = simplexml_load_string($agenda);
$i = 0;
foreach ($agenda->xpath('//item') as $agendaItem) {
    $beginDate = DateTime::createFromFormat('D, d M Y H:i:s T', (string)$agendaItem->startdate);
    $endDate = DateTime::createFromFormat('d-m-Y', $agendaItem->enddate);
    $endDate->setTime(23, 59, 59);

    $diff = $endDate->diff($today);
    if ($diff->invert == 0) {
        // Event over
        continue;
    }

    $item = new EventItem();
    $item->title = (string)$agendaItem->title;
    $item->description = (string)$agendaItem->description;
    $item->location = (string)$agendaItem->location;

    $duration = $beginDate->diff($endDate);
    if ($duration->days == 0) {
        // TODO: don't show current year?
        $item->date = strftime('%e %B %Y', $beginDate->getTimestamp());
    } else {
        // TODO: 5-12 november, 12 november-6 december, 31 december 2016-5 januari 2017
        $item->date = strftime('%e %B %Y', $beginDate->getTimestamp()) . ' - ' . strftime('%e %B %Y', $endDate->getTimestamp());
    }

    $photo = (string)$agendaItem->photo;
    if (!empty($photo)) {
        $item->photo = getImageUrl($agendaItem->photourl . $photo);
    }

    $beginDiff = $beginDate->diff($today);
    if ($beginDiff->invert == 0) {
        $ongoingEvents[] = $item;
        continue;
    }

    if ($beginDiff->days <= 7) {
        $upcomingEvents[] = $item;
    }
}

$picked = array_rand($ongoingEvents, min(count($ongoingEvents), ONGOING_EVENT_LIMIT));
foreach ($picked as $i) {
    $items[] = $ongoingEvents[$i];
}

$picked = array_rand($upcomingEvents, min(count($upcomingEvents), UPCOMING_EVENT_LIMIT));
foreach ($picked as $i) {
    $items[] = $upcomingEvents[$i];
}

shuffle($items);
$loader = new Twig_Loader_Filesystem(__DIR__ . '/../templates/');
$twig = new Twig_Environment($loader, array(
    'debug' => true,
    'cache' => __DIR__ . '/../cache',
));

echo $twig->render('main.twig', ['vandaagoptv' => $vandaagOpTv, 'items' => $items]);
