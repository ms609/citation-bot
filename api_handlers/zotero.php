<?php 
function query_url_api($ids, $templates) {
  foreach ($templates as $template) expand_by_zotero($template);
}

function zotero_request($url) {
  
  $ch = curl_init('http://' . TOOLFORGE_IP . '/translation-server/web');
  
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
  curl_setopt($ch, CURLOPT_USERAGENT, "Citation_bot");  
  curl_setopt($ch, CURLOPT_POSTFIELDS, $url);  
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);      
  
  $zotero_response = curl_exec($ch);
  if ($zotero_response === FALSE) {
    throw new Exception(curl_error($ch), curl_errno($ch));
  }
  curl_close($ch);
  return $zotero_response;
}
  
function expand_by_zotero(&$template, $url = NULL) {
  if (!$template->incomplete()) return FALSE; // Nothing to gain by wasting an API call
  if (is_null($url)) $url = $template->get('url');
  $zotero_response = zotero_request($url, getenv('TRAVIS'));
  $zotero_data = @json_decode($zotero_response, FALSE);
  if (!isset($zotero_data) || !isset($zotero_data[0]) || !isset($zotero_data[0]->{'title'})) {
    report_warning("Zotero translation server returned invalid json for URL ". $url . ": $zotero_response");
    return FALSE;
  } else {
    $result = $zotero_data[0];
  }
  if (substr(strtolower(trim($result->{'title'})), 0, 9) == 'not found') {
    report_info("Zotero translation server could not resolve URL ". $url);
    return FALSE;
  }
  
  // Verify that Zotero translation server did not think that this was a website and not a journal
  if (strtolower(substr(trim($result->{'title'}), -9)) === ' on jstor') {
    $template->add_if_new('title', substr(trim($result->{'title'}), 0, -9)); // Add the title without " on jstor"
    return FALSE; // Not really "expanded"
  }
  
  if (isset($result->{'bookTitle'})) {
    $template->add_if_new('title', $result->{'bookTitle'});
    if (isset($result->{'title'}))      $template->add_if_new('chapter',   $result->{'title'});
    if (isset($result->{'publisher'}))  $template->add_if_new('publisher', $result->{'publisher'});
  } else {
    if (isset($result->{'title'}))      $template->add_if_new('title'  , $result->{'title'});
  }
    
  if ( isset($result->{'ISBN'}))             $template->add_if_new('isbn'   , $result->{'ISBN'});
  if ( isset($result->{'issue'}))            $template->add_if_new('issue'  , $result->{'issue'});
  if ( isset($result->{'pages'}))            $template->add_if_new('pages'  , $result->{'pages'});
  if ( isset($result->{'publicationTitle'})) $template->add_if_new('journal', $result->{'publicationTitle'});
  if ( isset($result->{'volume'}))           $template->add_if_new('volume' , $result->{'volume'});
  if ( isset($result->{'date'}))             $template->add_if_new('date'   , $result->{'date'});
  if ( isset($result->{'DOI'}))              $template->add_if_new('doi'    , $result->{'DOI'});
  if ( isset($result->{'series'}))           $template->add_if_new('series' , $result->{'series'});
  $i = 0;
  while (isset($result->{'author'}[$i])) {
      if ( isset($result->{'author'}[$i][0])) $template->add_if_new('first' . ($i+1), $result->{'author'}[$i][0]);
      if ( isset($result->{'author'}[$i][1])) $template->add_if_new('last'  . ($i+1), $result->{'author'}[$i][1]);
      $i++;
  }
  
  if (isset($result->itemType)) {
    switch ($result->itemType) {
      case 'newspaperArticle': $template->change_name_to('cite newspaper'); break;
      case 'journalArticle': $template->change_name_to('cite journal'); break;
      case 'webpage': $template->change_name_to('cite journal'); break;
      default: report_warning("Unhandled itemType: " . $result->itemType);
    }
  }
  return TRUE;
}

?>