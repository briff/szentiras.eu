<?php 
session_start();
header('Content-type: text/html; charset=utf-8'); 

//print_R($_REQUEST);

require_once('bibleconf.php');
require_once("biblefunc.php");
require_once('func.php');
require_once('quote.php');


if(isset($_REQUEST['reftrans']) AND is_numeric($_REQUEST['reftrans']) AND $_REQUEST['reftrans'] > 0 ) $reftrans = $_REQUEST['reftrans'];
else $reftrans = 1;
if(isset($_REQUEST['q'])) $q = $_REQUEST['q'];
else $q = 'showbible';
if(isset($_REQUEST['texttosearch']) AND $_REQUEST['texttosearch'] != '') $texttosearch = $_REQUEST['texttosearch'];
else $texttosearch = false; 

if(isset($_REQUEST['rewrite']) AND $_REQUEST['rewrite'] != '') {
	$uri = rtrim($_REQUEST['rewrite'],'/');
	$uri = explode('/',$uri);
	if(count($uri)==2 AND $uri[0] == 'kereses') {
		$q = 'searchbible';
		$texttosearch = $uri[1];
	}
	elseif(count($uri) == 2) {
		foreach($translations as $tdtrans) {
			if($tdtrans['abbrev'] == $uri[0]) {
				$q = 'searchbible';
				$reftrans = $tdtrans['did']; 
				$texttosearch = $uri[1];
				break;
			}
		}
	}
	elseif($uri[0] == 'API') {
		$q = 'api';
		$api = $uri[1];
	}
	elseif(count($uri)==1 ) {
		$isit = isquotetion($uri[0]);
		if($isit != false) {
		$q = 'searchbible';
		$texttosearch = $uri[0];
		$reftrans = 1;
		}
	}
}

if($q != false AND file_exists($q.'.php')) require_once($q.'.php');
else {
	$title = 'A kért oldal nem található!';
	$content = 'Elnézést kérünk a kellemetlenségért.';
}

$menu = new Menu();
	$menu->add_item("Bibliaolvasás","showbible");
	$menu->add_item("Keresés a Bibliában","searchbible");
	$menu->add_pause();
	foreach($translations as $tdtrans) {
		$menu->add_item($tdtrans['name']." (".$tdtrans['abbrev'].")","showtrans?reftrans=".$tdtrans['did']);
	}
	
	//$menu->add_item("Katolikus fordítás (Jeromos)","showtrans?reftrans=3");
	//$menu->add_item("Protestáns fordítás","showtrans?reftrans=2");
	$menu->add_pause();

	$form .= "<form action='".$baseurl."index.php' method='get'>\n";
		$form .= "<input type='hidden' name='q' value='searchbible'>\n";
		$form .= "<input type='hidden' name='reftrans' value='".$reftrans."'>\n";
		$form .= "<input type=text name='texttosearch' size=10 maxlength=80 value='".$texttosearch."' class='alap' style='width:92%;margin-bottom:5px'>\n";
		$form .= "<input type=submit value='Keresés' class='alap'>\n";
		$form .= "</form>\n";
	
	$menu->add_item("Görög újszövetségi honlap","http://www.ujszov.hu/");
	$menu->add_item("Újszövetség: hangfájlok","http://www.kereszteny.hu/biblia/hang/");
	//$menu->add_item("A templom egere","http://templom-egere.kereszteny.hu/");
	$menu->add_pause();
		$menu->add_text($form);
		
	
	$menu->add_pause();
	$menu->add_item("FEJLESZTŐKNEK",$baseurl."API");
	$menu->add_item("Újdonságaink","info");
	/*
	$menu->add_item("Katolikus igenaptár","http://www.katolikus.hu/igenaptar/");
	$menu->add_item("Zsolozsma","http://zsolozsma.katolikus.hu/");
	
	/*
	$content = preg_replace('/biblia\/([a-z]*?)\.php(\?|)/','biblia2/INDEX?q=$1&',$content);
	$content = preg_replace('/(=\'|=")([a-z]*?)\.php(\?|)/','$1INDEX?q=$2&',$content);
	$content = preg_replace('/(=\'|=")(http:\/\/www\.kereszteny\.hu\/biblia2\/)(.*?)\.php\?(.*?)(\'|")/i','$1$2INDEX?q=$3&$4$5',$content);
	$content = preg_replace('/INDEX/s','index.php',$content);
	*/
	
	//$meta .= '<meta property="og:description" content="The Rock" />';

include 'template.php';	
?>
