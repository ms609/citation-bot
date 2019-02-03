
function safely_echo ($string) {
  echo echoable($string);
}
function echoable($string) {
  return HTML_OUTPUT ? htmlspecialchars($string) : $string;
}
function pubmed_link($identifier, $pm) {
  return HTML_OUTPUT 
       ? '<a href="https://www.ncbi.nlm.nih.gov/pubmed/' . urlencode($pm) . '" target="_blank">'
         . strtoupper($identifier) . ' ' . $pm . "</a>"
       : strtoupper($identifier) . ' ' . $pm;
}
function bibcode_link($id) {
  return HTML_OUTPUT
    ? '<a href="http://adsabs.harvard.edu/abs/' . urlencode($id) . '" target="_blank">'
      . $id . '</a>'
    : $id;
}
function doi_link($doi) {
  return HTML_OUTPUT
    ? '<a href="http://dx.doi.org/' . urlencode($doi) . '" target="_blank">' . $doi . '</a>'
    : $doi;
}
function jstor_link($id) {
  return HTML_OUTPUT
    ? '<a href="https://www.jstor.org/citation/ris/' . urlencode($id) . '" target="_blank">JSTOR ' . $id . '</a>'
    : "JSTOR $id";
}
