<?php

$data = file_get_contents('https://api.worldoftanks.ru/wot/?application_id=1b7bc64858d79aed49d1bc479248fa1a');
$data = json_decode($data, true);
/*
  echo '<pre>';
  var_dump($data['methods'][1]);
  echo '</pre>';
 */
$api_name = 'api_' . @$data['name'] . '_';
foreach ($data['methods'] as $method) {
  $documentation = "";
  $function = "";
  $varibles = "";

  $function_name = explode('/', $method['url']);

  $input_form_info = array();
  $input_fields = array();
  foreach ($method['input_form_info']['fields'] as $fields) {
    if ($fields['required']) {
      $input_form_info['required'][] = $fields;
    } else {
      $input_form_info['other'][] = $fields;
    }
    $input_fields[] = $fields['name'];
  }
  sort($input_fields);
  unset($method['input_form_info']);

  foreach ($method['errors'] as &$error) {
    $error[0] = (int) $error[0];
    $error[1] = (string) $error[1];
    $error[2] = (string) $error[2];
    $error = "{$error[0]} => array('message' => \"{$error[1]}\", 'code' => {$error[0]}, 'value' => \"{$error[2]}\")";
  }
  $varibles .= "\$errors = array(" . implode(", ", $method['errors']) . ");\n";
  unset($method['errors']);

  if (isset($method['allowed_protocols'])) {
    if (is_array($method['allowed_protocols'])) {
      $varibles .= "\$allowed_protocols = array('" . implode("', '", $method['allowed_protocols']) . "'); \$protocol = \$this->protocol; if (!in_array(\$protocol, \$allowed_protocols)) {\$protocol = \$allowed_protocols[0];} unset(\$allowed_protocols);\n";
    }
    unset($method['allowed_protocols']);
  }

  if (isset($method['allowed_http_methods'])) {
    if (is_array($method['allowed_http_methods'])) {
      $varibles .= "\$allowed_http_methods = array('" . implode("', '", $method['allowed_http_methods']) . "'); \$http_method = \$this->http_method; if (!in_array(\$http_method, \$allowed_http_methods)) {\$http_method = \$allowed_http_methods[0];} unset(\$allowed_http_methods);\n";
    }
    unset($method['allowed_http_methods']);
  }

  if (!in_array('access_token', $input_fields)) {
    $varibles .= "\$server = true;\n";
  }
  $varibles .= "if (!isset(\$input['access_token']) && \$this->token) { \$input['access_token'] = (string) \$this->token;}\n";
  $varibles .= "if (!isset(\$input['language'])) { \$input['language'] = (string) \$this->language;}\n";
  $varibles .= "if (isset(\$input['fields'])) { if (is_array(\$input['fields'])) { foreach (\$input['fields'] as &\$field) { \$field = trim((string) \$field);} \$input['fields'] = implode(',', \$input['fields']);} else { \$input['fields'] = trim((string) \$input['fields']);}}\n";
  $varibles .= "if (!isset(\$input['application_id'])) { if (!\$server && isset(\$input['access_token'])) { \$input['application_id'] = (string) \$this->standalone;} else { \$input['application_id'] = (string) \$this->server; unset(\$input['access_token']);}}\n";
  $varibles .= "\$input_fields = array('" . implode("', '", $input_fields) . "'); foreach (\$input as \$k => \$v) {if (!in_array(\$k, \$input_fields)) {unset(\$input[\$k]);}} unset(\$input_fields);\n";

  $documentation .= "{$method['name']}\n";
  $documentation .= "{$method['description']}\n";
  $documentation .= "@category {$method['category_name']}\n";
  $documentation .= "@link {$method['url']}\n";
  if ($method['deprecated']) {
    $documentation .= "@todo deprecated\n";
  }
  unset($method['name'], $method['description'], $method['category_name'], $method['deprecated']);
  $type_field = array();
  foreach ($input_form_info as $input_form) {
    foreach ($input_form as $fields) {
      $fields['doc_type'] = str_replace(array(', ', 'numeric'), array('|', 'integer'), $fields['doc_type']);
      $documentation .= "@param {$fields['doc_type']} \$input['{$fields['name']}'] {$fields['help_text']}\n";
      if ($fields['deprecated']) {
        $documentation .= "@todo deprecated \$input['{$fields['name']}'] {$fields['deprecated_text']}\n";
      }
      switch ($fields['doc_type']) {
        case 'string':
        case 'string|list':
          $type_field['string'][] = $fields['name'];
          break;
        case 'timestamp/date':
        case 'integer':
        case 'integer|list':
          $type_field['integer'][] = $fields['name'];
          break;
        case 'float':
        case 'float|list':
          $type_field['float'][] = $fields['name'];
          break;
        case 'boolean':
          $type_field['boolean'][] = $fields['name'];
          break;
      }
    }
  }
  foreach ($type_field as $type => $field) {
    if (count($field) > 0) {
      $varibles .= "foreach(array('" . implode("', '", $field) . "') as \$field) {if (isset(\$input[\$field])) { if (is_array(\$input[\$field])) { foreach (\$input[\$field] as &\$field) { \$field = ({$type}) \$field;} \$input[\$field] = implode(',', \$input[\$field]);} else { \$input[\$field] = ({$type}) \$input[\$field];}}}\n";
    }
  }
  $documentation .= "@return array\n";
  $documentation = "/**\n * " . str_replace("\n", "\n * ", trim($documentation)) . "\n */\n";
  $varibles .= "\$output = \$this->send('{$method['url']}', \$input, \$http_method, \$protocol);\n";



  echo '<pre>';
  echo ($documentation);
  echo ($varibles);
  //var_dump($input_fields);
  //var_dump($method['output_form_info']['fields']);
  //var_dump($method['output_form_info']['fields']);
  echo '</pre>';
  echo '<hr>';
}