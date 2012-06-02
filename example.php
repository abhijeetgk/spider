<?php
  // include Spider class file
  require_once('spider.class.php');
set_time_limit(0);
error_reporting(0);
  // create new Spider object
$spider = new Spider('http://www.angara.com/');
$spider->fetch_urls('http://www.diamond.com/');
//
//  // allow files with extension *.txt being spidered
//  $spider->allowType('txt');
//
//  // and disable files with that extension
//  $spider->restrictType('txt');
//
//  // set it to true if you want to see what is happening on the screen
//  $spider->setVerbose(false);
//
//  // start spidering website
//  $spider->startSpider();
//
//  // all found and fetched links are in that variable
//  $links = $spider->all_links;
  $keywords=$spider->keyword_array;
  // print it out
  echo "<pre>";
  print_r($keywords);
?>