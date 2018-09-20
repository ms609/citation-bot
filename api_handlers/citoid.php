<?php 
function expand_by_citoid(&$template, $url = NULL) {
  if (is_null($url)) $url = $template->get('url');

  if (getenv('TRAVIS') || TRUE) {
    // Public API limited to 200 requests/day: enough for testing, perhaps, but not for production
    $ch = curl_init('https://en.wikipedia.org/api/rest_v1/data/citation/mediawiki/' . urlencode($url));    
  } else {
    $ch = curl_init('http://127.0.0.1:1969/web');
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $url);  
  }
  
  curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: text/plain']);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);      
  $citoid_response = curl_exec($ch);
  var_dump($citoid_response);
  if ($citoid_response === FALSE) {
    throw new Exception(curl_error($ch), curl_errno($ch));
  }
  curl_close($ch);
  $citoid_data = @json_decode($citoid_response, FALSE);
  if (!isset($citoid_data) || !isset($citoid_data[0]) || !isset($citoid_data[0]->{'title'})) {
    report_warning("Citoid API returned invalid json for URL ". $url);
    return FALSE;
  } else {
    $result = $citoid_data[0];
  }
  if (substr(strtolower(trim($result->{'title'})), 0, 9) == 'not found') {
    report_info("Citoid API could not resolve URL ". $url);
    return FALSE;
  }
  
  // Verify that Citoid did not think that this was a website and not a journal
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