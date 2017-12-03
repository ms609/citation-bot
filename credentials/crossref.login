<?php
const CROSSREFUSERNAME = 'martins@gmail.com';
const NYTUSERNAME   = 'citation_bot';
const ADSABSAPIKEY  = 'Dl6Dp2GU1rOl3Nu3OkfAhee6ywC42rC5wh9dtpUk'; # Replace this with a working key
const ISBN_KEY = '268OHQMW';  // Does not work anymore
// const GOOGLE_KEY = '&key=AIzaSyBNhyC5a5EirreJEDQ1muw0ZBAmSMs8R4E' ;  // only works from tool labs
$GOOGLE_CONTEXT = NULL ;

// For the test servers
const GOOGLE_KEY = '&key=AIzaSyC7Sx7pAK5MsYY1yxeEHKmnU-P4WxGQPj4' ; // Only works on test servers
{
$google_header[] = "Accept: text/xml,application/xml,application/json,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
$google_header[] = "Cache-Control: max-age=0";
$google_header[] = "Connection: keep-alive";
$google_header[] = "Keep-Alive: 300";
$google_header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
$google_header[] = "Accept-Language: en-us,en;q=0.5";
$google_referer = "travis-ci.org";
$google_opts = array('http'=>array(
                     'header'=>implode("\r\n",$google_header)."\r\n".
                     "Referer: $google_referer\r\n"));
$GOOGLE_CONTEXT = stream_context_create($google_opts);
}
// End test servers
