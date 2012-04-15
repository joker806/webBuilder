<?php

use Inspirio\Database\cDBFeederBase;

include_once 'common.php';

$database = new Inspirio\Database\cDatabase( DATABASE, HOST, USER, PASSWORD );

$url = '/'. $_GET['route'];


$webPageFeeder = new Inspirio\Database\cDBFeederBase( '\\Inspirio\\cWebPage', $database );
$webPage       = $webPageFeeder->whereColumnEq( 'url_name', $url )->getOne();

$simplePageFeeder = new cDBFeederBase( '\\Inspirio\\cSimplePage', $database );
$simplePage       = $simplePageFeeder->whereColumnEq( 'web_page_ID', $webPage->getID() )->getOne();

$webPage->setContentItem( $simplePage );
$simplePage->setWebPage( $webPage );

// 404
if( $webPage == null ) {
	header("HTTP/1.0 404 Not Found");
	echo '<h1 style="color:red;">404 - Page not found</h1>';
	exit;
}

$builder = new WebBuilder\WebBuilder( $database, array( 'debug' => true ));
echo $builder->render( $webPage );