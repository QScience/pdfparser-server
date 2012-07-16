<?php
ini_set('display_errors', '1');

$json = json_decode($_POST['json']);

if (isset($json->func) && $json->func == 'set_public_key') {
  save_public_key();
} else {
  parse_pdf();
}

function save_public_key() {
  $success = FALSE;
  foreach ($_FILES as $key => $file) {
    $success = move_uploaded_file($file['tmp_name'], './public_key');
    break;
  }

  $ret = new stdClass();
  $ret->result = $success === TRUE ? 0 : 1;
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
    $json->result = 0;
    $json->xml_content = run_java_parser($pdf_folder . $f['name']);
    $json->xml_name = $f['name'].'.out.txt';
  } elseif ($verify === 0) {
    $json->result = 1;
  } elseif ($verify === -1) {
    $json->result = 2;
  } else {
    $json->result = 3;
  }
  
  echo json_encode($json);
}

function run_java_parser($pdf_path) {
  // TODO: call java to parse pdf
  
  return base64_encode(file_get_contents('./parsed_docs/sfi_0412039.txt.out'));
  
}

function do_post_answer($json, $filename, $filepath) {
  $f = fopen('log', 'w');
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

  $data .= "imitating base 64 coded data" . "\n";
  
  $data .= "--$boundary--\n";
  // compiling the post request

  $wrapper = "Content-Type: multipart/form-data; boundary=" . $boundary . "\n";
  $wrapper .= $data;

  fwrite($f, $wrapper);
  fclose($f);

  echo $wrapper;

  //$ctx = stream_context_create($params);
  //$fp = fopen($url, 'rb', FALSE, $ctx);
  //if (!$fp) {
  //  throw new Exception("Problem with $url, $php_errormsg");
 // }
  //$response = @stream_get_contents($fp);
  //if ($response === FALSE) {
  //  throw new Exception("Problem reading data from $url, $php_errormsg");
  //}
 // return $response;
}

?>
