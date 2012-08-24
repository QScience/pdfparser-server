<?php
ini_set('display_errors', '1');

if (isset($_GET['rmdirs'])) {
  echo 'rmdir';
  rrmdir('./pdfs');
  rrmdir('./parsed_docs');
  rrmdir('./public_keys');
  exit;
}

$json = json_decode($_POST['json']);

if (isset($json->func) && $json->func == 'set_public_key') {
  save_public_key();
}
elseif (!empty($_FILES)) {
  parse_pdf();
}
else {
  echo 'Empty request.';
}

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
  if (!is_dir('public_keys')) {
    mkdir('public_keys');
  }

  $pub_key_path = './public_keys/'. $_SERVER['REMOTE_ADDR'] .'.public_key';
  
  $success = FALSE;
  foreach ($_FILES as $key => $file) {
    $success = @move_uploaded_file($file['tmp_name'], $pub_key_path);
    break;
  }

  $ret = new stdClass();
  $ret->result = $success === TRUE ? 0 : 1;
  header('Content-type: application/json');
  echo json_encode($ret);


  $l = fopen('log', 'a');
  fwrite($l, "#" . date('Y-m-d H:i:s') . "\n");
  fwrite($l, "  " . $_SERVER['REMOTE_ADDR'] . "\n");
  fwrite($l, "  uploading public key: " . $pub_key_path . "\n");
  fwrite($l, "\n");
  fclose($l);
}

function parse_pdf() {
  global $json;

  header('Content-type: application/json');

  $pub_key_file = './public_keys/'. $_SERVER['REMOTE_ADDR'] .'.public_key';
  if (!is_file($pub_key_file)) {
    $json->result = 3;
  }
  else {

    $pdf_folder = './pdfs/';

    if (!is_dir($pdf_folder)) {
      mkdir($pdf_folder);
    }

    $files = $_FILES;
    $f = null;
    foreach ($files as $key => $file) {
      move_uploaded_file($file['tmp_name'], $pdf_folder . $file['name']);
      $f = $file;
      break;
    }
  
    $file_content = file_get_contents($pdf_folder . $f['name']);
    $pub_content = file_get_contents($pub_key_file);

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
      $json->result = 4;
    }
  }
  
  echo json_encode($json);
}

function run_java_parser($pdf_path) {
  // TODO: call java to parse pdf
  $java = 'java -jar PDFPreprocess.jar ' . $pdf_path;
  $perl = './parscit/bin/citeExtract.pl -m extract_all '. $pdf_path . '.txt ' . $pdf_path . '.txt.out';

  $l = fopen('log', 'a');
  fwrite($l, "#" . date('Y-m-d H:i:s') . "\n");
  fwrite($l, "  " . $_SERVER['REMOTE_ADDR'] . "\n");
  fwrite($l, "  executing: " . $java . "\n");
  fwrite($l, "  executing: " . $perl . "\n");
  fwrite($l, "\n");

  $ll = exec($java, $retval);
  $lines = implode("\n", $retval);

  $ll = exec($perl, $retval);
  $lines = implode("\n", $retval);
  
  fclose($l);

  return base64_encode(file_get_contents($pdf_path . '.txt.out'));
  
}

//function do_post_answer($json, $filename, $filepath) {
//  $f = fopen('log', 'w');
//  $data = "";
//  $boundary = "----------------" . substr(md5(rand(0, 32000)), 0, 10);
//  // POSTing JSON data
//  $data .= '--' . $boundary . "\n";
//  $data .= 'Content-Disposition: form-data; name="json"' . "\n";
//  $data .= 'Content-type: application/json' . "\n\n";
//
//  $data .= $json . "\n";
//
//  // POSTing the pdf data
//  $data .= "--$boundary\n";
//  $file_contents = file_get_contents($filepath);
//  $data .= "Content-Disposition: form-data; name=\"{$filename}\"; filename=\"{$filename}\"\n";
//  $data .= "Content-Type: text/plain\n\n";
//
//  $data .= "imitating base 64 coded data" . "\n";
// 
//  $data .= "--$boundary--\n";
//  // compiling the post request
//
//  $wrapper = "Content-Type: multipart/form-data; boundary=" . $boundary . "\n";
//  $wrapper .= $data;
//
//  fwrite($f, $wrapper);
//  fclose($f);
//
//  echo $wrapper;
//
//}

?>
