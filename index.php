<?

ini_set('display_errors', '1');
$fh = fopen('log', 'w');

$files = $_FILES;
$f = null;
foreach ($files as $key => $file) {
  move_uploaded_file($file['tmp_name'], './pdfs/' . $file['name']);
  $f = $file;
  break;
}

$file_content = file_get_contents('./pdfs/'.$f['name']);
$pub_content = file_get_contents('./public_key');

$json = json_decode($_POST['json']);

$verify = openssl_verify($file_content, base64_decode($json->signature), $pub_content);
unset($json->signature);
if ($verify === 1) {
  $json->result = 'SUCCESS';
} elseif ($verify === 0) {
  $json->result = 'INCORRECT';
} elseif ($verify === -1) {
  $json->result = 'ERROR';
} else {
  $json->result = 'UNKNOWN';
}

fwrite($fh, print_r($json, true));
fwrite($fh, 'verify result: ' . "'" . $verify . "'");

header('Content-type: application/json');
echo json_encode($json);

fclose($fh);

function do_post_answer($json, $filename, $filepath) {
  $data = "";
  $boundary = "----------------" . substr(md5(rand(0, 32000)), 0, 10);
  // POSTing JSON data
  $data .= '--' . $boundary . "\n";
  $data .= 'Content-Disposition: form-data; name="json"' . "\n";
  $data .= 'Content-type: application/json' . "\n\n";

  $data .= $json . "\n";

  // POSTing the pdf data
  $data .= "--$boundary\n";
  $file_contents = file_get_contents($filepath);
  $data .= "Content-Disposition: form-data; name=\"{$filename}\"; filename=\"{$filename}\"\n";
  $data .= "Content-Type: text/plain\n\n";

  $data .= $file_contents . "\n";
  $data .= "--$boundary--\n";
  // compiling the post request

  $wrapper = "Content-Type: multipart/form-data; boundary=" . $boundary . "\n";
  $wrapper = "";

  $ctx = stream_context_create($params);
  $fp = fopen($url, 'rb', FALSE, $ctx);
  if (!$fp) {
    throw new Exception("Problem with $url, $php_errormsg");
  }
  //$response = @stream_get_contents($fp);
  //if ($response === FALSE) {
  //  throw new Exception("Problem reading data from $url, $php_errormsg");
  //}
  return $response;
}

?>
