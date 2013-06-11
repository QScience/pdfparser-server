<?php
ini_set('display_errors', '1');

if (isset($_GET['rmdirs'])) {
  echo 'rmdir';
  rrmdir('./pdfs');
  rrmdir('./parsed_docs');
  rrmdir('./public_keys');
  exit;
}


$l = fopen('log', 'a');
fwrite($l, "#" . date('Y-m-d H:i:s') . "\n");

$json = json_decode($_POST['json']);

if (isset($json->func) && $json->func == 'set_public_key') {
  save_public_key();
}
elseif (isset($json->func) && $json->func == 'send_pdf_to_parse') {
  parse_pdf();
}
else {
  echo 'Empty request.';
}

fwrite($l, "\n");
fclose($l);

function rrmdir($dir) {
  if (is_dir($dir)) {
    foreach(glob($dir . '/*') as $file) {
      if(is_dir($file)) {
        rrmdir($file);
      }
      else {
        unlink($file);
      }
    }
    rmdir($dir);
  }
}

function save_public_key() {
  global $json, $l;

  if (!is_dir('public_keys')) {
    mkdir('public_keys');
  }

  $pub_key_path = './public_keys/'. $json->ip .'.public_key';
  file_put_contents($pub_key_path, base64_decode($json->public_key));
  $success = TRUE;
  
  $ret = new stdClass();
  $ret->result = $success === TRUE ? 0 : 1;
  header('Content-type: application/json');
  echo json_encode($ret);


  fwrite($l, "  " . $json->ip . "\n");
  fwrite($l, "  uploading public key: " . $pub_key_path . "\n");
  fwrite($l, "\n");
}

function parse_pdf() {
  global $json, $l;

  header('Content-type: application/json');

  $pub_key_file = './public_keys/'. $json->ip .'.public_key';
  if (!is_file($pub_key_file)) {
    $json->result = 3;
  }
  else {

    $pdf_folder = './pdfs/';

    if (!is_dir($pdf_folder)) {
      mkdir($pdf_folder);
    }

    $f = $json->filename;
    unset($json->filename);
    $file_content = base64_decode($json->file);
    unset($json->file);
    file_put_contents($pdf_folder . $f, $file_content);

    $pub_content = file_get_contents($pub_key_file);
    $file_name = $f;
    $signature = $json->signature;
    $verify = @openssl_verify($file_content, base64_decode($signature), $pub_content);
    unset($json->signature);
    //fwrite($l, "  signature: ". base64_decode($signature) ."\n");
    //fwrite($l, "  dbg_sign: ". $crypted_hash ."\n");
    if ($verify === 1) {
      $json->result = 0;
      $start = microtime(TRUE);
      $data = run_java_parser($pdf_folder . $file_name);
      if ($data->result !== 0) {
        $json->result = $data->result;
      } else {
        $json->ellapse = microtime(TRUE) - $start;
        $json->xml_citations = $data->citations;
        $json->xml_header = $data->header;
        $json->authors = $data->names;
        $json->xml_name = $file_name.'.out.txt';
        //fwrite($l, "  signature: ". base64_decode($signature) ."\n");
      }
    } elseif ($verify === 0) {
      $json->result = 1;
    } elseif ($verify === -1) {
      $json->result = 2;
    } else {
      $json->result = 4;
    }
  }
  
  echo json_encode($json);
}

function run_java_parser($pdf_path) {
  global $json, $l;

  $path = $pdf_path;
  // TODO: call java to parse pdf
  $java = 'java -jar PDFPreprocess.jar ' . $path;
  $perl_cit = './parscit/bin/citeExtract.pl -m extract_citations '. $path . '.txt ' . $path . '.txt.citations';
  $perl_head = './parscit/bin/citeExtract.pl -m extract_header '. $path . '.txt ' . $path . '.txt.header';
  $authors = './python/extract_authors.py '. $path . '.txt.header ' . $path . '.txt.names';

  fwrite($l, "  " . $json->ip . "\n");
  fwrite($l, "  executing: " . $java . "\n");
  fwrite($l, "  executing: " . $perl_cit . "\n");
  fwrite($l, "  executing: " . $perl_head . "\n");
  fwrite($l, "  executing: " . $authors . "\n");

  $start = microtime(TRUE);
  exec($java, $retval);
  $json->javarun = microtime(TRUE) - $start;

  if (!is_file($path . '.txt')) {
    // cannot parse pdf
    $data = new stdClass();
    $data->result = 5;
    return $data;
  }
  $start = microtime(TRUE);
  exec($perl_cit, $retval);
  $json->perl_cit = microtime(TRUE) - $start;

  $start = microtime(TRUE);
  exec($perl_head, $retval);
  $json->perl_head = microtime(TRUE) - $start;

  $start = microtime(TRUE);
  exec($authors, $retval);
  $json->python = microtime(TRUE) - $start;
  
  $data = new stdClass();
  $data->citations = base64_encode(file_get_contents($pdf_path . '.txt.citations'));
  $data->header = base64_encode(file_get_contents($pdf_path . '.txt.header'));
  if(is_file($pdf_path . '.txt.names')) {
    $data->names = base64_encode(file_get_contents($pdf_path . '.txt.names'));
  } else {
    $data->names = '';
  }
  $data->result = 0;

  return $data;
  
}

?>
