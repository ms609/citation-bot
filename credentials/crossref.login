<?php
const CROSSREFUSERNAME = 'martins@gmail.com';
const NYTUSERNAME   = 'citation_bot';
const ADSABSAPIKEY  = 'Dl6Dp2GU1rOl3Nu3OkfAhee6ywC42rC5wh9dtpUk'; # Replace this with a working key
const ISBN_KEY = '268OHQMW';  // Does not work anymore
// const GOOGLE_KEY = '&key=AIzaSyBNhyC5a5EirreJEDQ1muw0ZBAmSMs8R4E' ;  // only works from tool labs
// const GOOGLE_CONTEXT = NULL ;
const GOOGLE_KEY = '&key=AIzaSyC7Sx7pAK5MsYY1yxeEHKmnU-P4WxGQPj4' ; // Only works on test servers
     $GOOGLE_CONTEXT  = stream_context_create(
                            array('http'=>array(
                            'header'=>"Accept: text/xml,application/xml,application/json,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5Cache-Control: max-age=0Connection: keep-aliveKeep-Alive: 300Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7Accept-Language: en-us,en;q=0.5\r\n".
                            "Referer: travis-ci.org\r\n"))
                            );
    
