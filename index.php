<?
ini_set('display_errors', '1');

$json = json_decode($_POST['json']);

if (isset($json->func) && $json->func == 'set_public_key') {
  save_public_key();
} else {
  parse_pdf();
}

function save_public_key() {
  foreach ($_FILES as $key => $file) {
    move_uploaded_file($file['tmp_name'], './public_key');
    break;
  }

  $ret = new stdClass();
  $ret->message = 'public key saved';
  header('Content-type: application/json');
  echo json_encode($ret);
}

function parse_pdf() {
  global $json;

  header('Content-type: application/json');

  $pdf_folder = './pdfs/';
  $parsed_folder = './parsed_docs/';

  if (!is_dir($pdf_folder)) {
    mkdir($pdf_folder);
  }

  if (!is_dir($parsed_folder)) {
    mkdir($parsed_folder);
  }
  
  $fh = fopen('log', 'w');
  
  $files = $_FILES;
  $f = null;
  foreach ($files as $key => $file) {
    move_uploaded_file($file['tmp_name'], $pdf_folder . $file['name']);
    $f = $file;
    break;
  }
  
  $file_content = file_get_contents($pdf_folder . $f['name']);
  $pub_content = file_get_contents('./public_key');

  $verify = @openssl_verify($file_content, base64_decode($json->signature), $pub_content);
  unset($json->signature);
  if ($verify === 1) {
    $json->result = 'SUCCESS';
  } elseif ($verify === 0) {
    $json->result = 'INCORRECT PUBLIC KEY (please upload your public key again under settings menu)';
  } elseif ($verify === -1) {
    $json->result = 'ERROR';
  } else {
    $json->result = 'UNKNOWN ERROR (please upload your public key again under settings menu)';
  }

  
  fwrite($fh, print_r($json, true));
  fwrite($fh, 'verify result: ' . "'" . $verify . "'");
  
  echo json_encode($json);
  
  fclose($fh);
}

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
