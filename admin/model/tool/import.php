<?php
class ModelToolImport extends Model {
  private $table_name;
  private $file_name; 
  private $use_csv_header;
  private $field_separate_char;
  private $field_enclose_char;
  private $field_escape_char; 
  private $error;
  private $arr_csv_columns;
  private $table_exists;
  private $encoding;
  private $conn;
  private $loaded;
  private $mysql;
  private $store;

  public function Quick_CSV_import($file_name="")  {
    $this->file_name = $file_name;
    $this->arr_csv_columns = array();
    $this->use_csv_header = true;
    $this->field_separate_char = ",";
    $this->field_enclose_char  = "\"";
    $this->field_escape_char   = "\\";
    $this->table_exists = false;
    $this->store = $this->config->get('config_store_id');
  }

  public function mysql(){
    $mysql = new mysqli(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE);
    if ($mysql->connect_errno) {
        echo "connection error, exiting...";
        exit;
    }
    $this->mysql = $mysql;
    return $mysql;
  }

  public function getmodel() {
      global $loader, $registry;
      $loader->model('catalog/product');
      $model  = $registry->get('model_catalog_product');
      $result = $model;
      return $this->load->model('catalog/product');
  }

  public function modelx($model){
    global $registry;
    $file  = DIR_APPLICATION . 'model/' . $model . '.php';
    $class = 'Model' . preg_replace('/[^a-zA-Z0-9]/', '', $model);
    
    if (file_exists($file)) {
      include_once($file);
      
      $this->registry->set('model_' . str_replace('/', '_', $model), new $class($this->registry));
    } else {
      trigger_error('Error: Could not load model ' . $model . '!');
      exit();         
    }
  }

  public function import() {
      if($this->modelx('catalog/product')) {
        echo ' inluded ';
      }
      if($this->load->model('catalog/product')) {
        $load = 1;
      } else {
        $load = 0;
      }
      //echo $load;
      $l = $this->config->get('config_language_id');
      $pstore = $this->store;
      ini_set('memory_limit', '-1');
      $date = date("d_m_Y"); //table per day
      $this->session->data['date'] = $date;
      $date = $this->session->data['date'];
      $mysql = $this->mysql();
      if($this->table_name==""){
        $this->table_name = "products_upload_".$date;
      }
      
      $this->table_exists = false;
      $this->create_import_table();
      
      if(empty($this->arr_csv_columns)){
        $this->get_csv_header_fields();
      }

      if("" != $this->encoding && "default" != $this->encoding){
        $this->set_encoding();
      }
    
      if($this->table_exists) {
        $csv = file_get_contents($this->file_name);
        $array = array_map("str_getcsv", explode("\n", $csv));
        $dbcolumns = implode(',',array_values($array[0]));
        $sqls = 'INSERT INTO '.$this->table_name.' VALUES ';
        $valuesb = array();
        for($i = 1; $i < count($array); $i++) { 
          $arr = array();
          $productData = array();
          for($x = 0; $x < count($array[$i]); $x++) {
                $productData[$array[0][$x]] = $array[$i][$x];
                $value = '"'.$mysql->real_escape_string($array[$i][$x]).'"';
                $arr[] = $value;
          }
          $values = implode(',',$arr);
          $q = 'INSERT INTO '.$this->table_name.' VALUES ('.$values.')';
          $productData['product_description'][$l]['name']             = $productData['name'];
          $productData['product_description'][$l]['meta_description'] = $productData['meta_description'];
          $productData['product_description'][$l]['meta_keyword']     = $productData['meta_keywords'];
          $productData['product_description'][$l]['description']      = $productData['description'];
          $productData['product_description'][$l]['tag']              = $productData['tags'];
          $productData['product_store'][]                             = $pstore;
          $productData['product_category'][]                          = $productData['categories'];
          if(empty($productData['status'])) {
            $productData['status'] = 1;
          }
          if($mysql->query($q)) {
            $r = $this->addProduct($productData);
            foreach ($r as $key => $value) {
              echo $value;
            }
            /*
              if($this->model_catalog_product->addProduct($productData)){
                 echo '<p>'.$i.'  Product '.$productData['name'].' Saved Successfully </p>';
              } else {
                $this->addProduct($productData);
              }
            */
          } else {
            echo '<p>Error Saving <p>';
            echo "<p>Query : ".$q."</p>";
          }
        }
      }
  }

  public function get_csv_header_fields()  {
    $mysql = $this->mysql();
    $this->arr_csv_columns = array();
    $fpointer = fopen($this->file_name, "r");
    $e_rr = array();
    if ($fpointer) {
      $arr = fgetcsv($fpointer, 10*1024, $this->field_separate_char);
      if(is_array($arr) && !empty($arr)) {   
        if($this->use_csv_header) {
      $missingFs = '';
      if(!in_array('sku',$arr)) { 
          $e_rr[] = '* sku missing';
      }
      if(!in_array('name',$arr)) { 
          $e_rr[] = '* product name missing';
      }
      if(!in_array('model',$arr)) { 
          $e_rr[] = '* product model missing';
      }
      $missingFs = implode('*',$e_rr);
      $ercount = count($e_rr);
      if(!empty($ercount))
            exit('<script type="text/javascript"> var pr = confirm("Users Not Uploaded. See below : '.$missingFs.', Click ok to try again. ");if(pr){window.location = window.location.href;} else { window.location = "index.php?route=common/home&token='.$this->request->get["token"].'"; }</script>');
      
          foreach($arr as $val)
            if(trim($val)!="")
              $this->arr_csv_columns[] = $val;
        
        }
        else
        {
          $i = 1;
          foreach($arr as $val)
            if(trim($val)!="")
              $this->arr_csv_columns[] = "column".$i++;
        }
      }
      unset($arr);
      fclose($fpointer);
    }
    else
      $this->error = "file cannot be opened: ".(""==$this->file_name ? "[empty]" : $mysql->real_escape_string($this->file_name));
    return $this->arr_csv_columns;
  }

  public function create_import_table()  {
    $mysql = $this->mysql();
    $sql = "CREATE TABLE IF NOT EXISTS ".$this->table_name." (";
    
    if(empty($this->arr_csv_columns))
      $this->get_csv_header_fields();
    
    if(!empty($this->arr_csv_columns)) {
      $arr = array();
    
      for($i=0; $i<sizeof($this->arr_csv_columns); $i++)
          $arr[] = "`".$this->arr_csv_columns[$i]."` VARCHAR(100) ";
          $sql .= implode(",", $arr);
          $sql .= ")";
          $res = $mysql->query($sql);
        $this->error = '';//$mysql->error;
      $this->table_exists = ""== '';//$mysql->error;
    }
  }

  public function get_encodings()  {
    $mysql = $this->mysql();
    $rez = array();
    $sql = "SHOW CHARACTER SET";
    $res = $mysql->query($sql);
    if($res->num_rows > 0) {
      foreach ($res->rows as $row) {
        $rez[$row["Charset"]] = ("" != $row["Description"] ? $row["Description"] : $row["Charset"]); 
      }
    }
    return $rez;
  }

  public function set_encoding()  {
    $mysql = $this->mysql();
    $encoding = $this->encoding;
      if("" == $encoding)
      $res = $mysql->set_charset($encoding);
      return '';//$mysql->error;
  }

  public function addProduct($data) {
      $mysql = $this->mysql();
      $r = 'SELECT * FROM '.DB_PREFIX.'product WHERE sku = "'.$mysql->real_escape_string($data['sku']).'"';
      $result = $mysql->query($r);
      $log = array();
      if(!empty($result->num_rows)) {
        $row = $result->fetch_array();
        $product_id = $row['product_id'];
        //$this->deleteProduct($product_id); //remove b4 insert
        //$this->addnow($data);
        $this->editProduct($product_id, $data); //edit if exists
      } else {
        $this->addnow($data);
      }
  }
  public function addnow($data){
        $mysql = $this->mysql();
        if($mysql->query("INSERT INTO " . DB_PREFIX . "product SET model = '" . $mysql->real_escape_string($data['model']) . "', sku = '" . $mysql->real_escape_string($data['sku']) . "', upc = '" . $mysql->real_escape_string($data['upc']) . "', ean = '" . $mysql->real_escape_string($data['ean']) . "', jan = '" . $mysql->real_escape_string($data['jan']) . "', isbn = '" . $mysql->real_escape_string($data['isbn']) . "', mpn = '" . $mysql->real_escape_string($data['mpn']) . "', location = '" . $mysql->real_escape_string($data['location']) . "', quantity = '" . (int)$data['quantity'] . "', minimum = '" . (int)$data['minimum'] . "', subtract = '" . (int)$data['subtract'] . "', stock_status_id = '" . (int)$data['stock_status_id'] . "', date_available = '" . $mysql->real_escape_string($data['date_available']) . "', manufacturer_id = '" . (int)$data['manufacturer_id'] . "', shipping = '" . (int)$data['shipping'] . "', price = '" . (float)$data['price'] . "', points = '" . (int)$data['points'] . "', weight = '" . (float)$data['weight'] . "', weight_class_id = '" . (int)$data['weight_class_id'] . "', length = '" . (float)$data['length'] . "', width = '" . (float)$data['width'] . "', height = '" . (float)$data['height'] . "', length_class_id = '" . (int)$data['length_class_id'] . "', status = '" . (int)$data['status'] . "', tax_class_id = '" . $mysql->real_escape_string($data['tax_class_id']) . "', sort_order = '" . (int)$data['sort_order'] . "', date_added = NOW()")){
          echo '<p> '.$mysql->error. ' Product '.$data['name'].' added Successfully </p>';
        } else {
          echo '<p> '.$mysql->error. ' Error adding Product '.$data['name'].' to store </p>';
        }
        $product_id = $mysql->insert_id;
          
          if (isset($data['image'])) {
            $mysql->query("UPDATE " . DB_PREFIX . "product SET image = '" . $mysql->real_escape_string(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE product_id = '" . (int)$product_id . "'");
          }
          
          foreach ($data['product_description'] as $language_id => $value) {
            $mysql->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $mysql->real_escape_string($value['name']) . "', meta_keyword = '" . $mysql->real_escape_string($value['meta_keyword']) . "', meta_description = '" . $mysql->real_escape_string($value['meta_description']) . "', description = '" . $mysql->real_escape_string($value['description']) . "', tag = '" . $mysql->real_escape_string($value['tag']) . "'");
          }
          
          if (isset($data['product_store'])) {
            foreach ($data['product_store'] as $store_id) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
            }
          }

          if (isset($data['product_attribute'])) {
            foreach ($data['product_attribute'] as $product_attribute) {
              if ($product_attribute['attribute_id']) {
                $mysql->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");
                
                foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {       
                  $mysql->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $mysql->real_escape_string($product_attribute_description['text']) . "'");
                }
              }
            }
          }
        
          if (isset($data['product_option'])) {
            foreach ($data['product_option'] as $product_option) {
              if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
                $mysql->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");
              
                $product_option_id = $mysql->insert_id;
              
                if (isset($product_option['product_option_value']) && count($product_option['product_option_value']) > 0 ) {
                  foreach ($product_option['product_option_value'] as $product_option_value) {
                    $mysql->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$product_option_value['price'] . "', price_prefix = '" . $mysql->real_escape_string($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $mysql->real_escape_string($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $mysql->real_escape_string($product_option_value['weight_prefix']) . "'");
                  } 
                }else{
                  $mysql->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '".$product_option_id."'");
                }
              } else { 
                $mysql->query("INSERT INTO " . DB_PREFIX . "product_option SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value = '" . $mysql->real_escape_string($product_option['option_value']) . "', required = '" . (int)$product_option['required'] . "'");
              }
            }
          }
          
          if (isset($data['product_discount'])) {
            foreach ($data['product_discount'] as $product_discount) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$product_discount['price'] . "', date_start = '" . $mysql->real_escape_string($product_discount['date_start']) . "', date_end = '" . $mysql->real_escape_string($product_discount['date_end']) . "'");
            }
          }

          if (isset($data['product_special'])) {
            foreach ($data['product_special'] as $product_special) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$product_special['price'] . "', date_start = '" . $mysql->real_escape_string($product_special['date_start']) . "', date_end = '" . $mysql->real_escape_string($product_special['date_end']) . "'");
            }
          }
          
          if (isset($data['product_image'])) {
            foreach ($data['product_image'] as $product_image) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $mysql->real_escape_string(html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8')) . "', sort_order = '" . (int)$product_image['sort_order'] . "'");
            }
          }
          
          if (isset($data['product_download'])) {
            foreach ($data['product_download'] as $download_id) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
            }
          }
          
          if (isset($data['product_category'])) {
            foreach ($data['product_category'] as $category_id) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
            }
          }
          
          if (isset($data['product_filter'])) {
            foreach ($data['product_filter'] as $filter_id) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
            }
          }
          
          if (isset($data['product_related'])) {
            foreach ($data['product_related'] as $related_id) {
              $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
              $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
            }
          }

          if (isset($data['product_reward'])) {
            foreach ($data['product_reward'] as $customer_group_id => $product_reward) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$product_reward['points'] . "'");
            }
          }

          if (isset($data['product_layout'])) {
            foreach ($data['product_layout'] as $store_id => $layout) {
              if ($layout['layout_id']) {
                $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
              }
            }
          }
                  
          if ($data['keyword']) {
            $mysql->query("INSERT INTO " . DB_PREFIX . "url_alias SET query = 'product_id=" . (int) $product_id . "', keyword = '" . $mysql->real_escape_string($data['keyword']) . "'");
          }

          if (isset($data['product_profiles'])) {
            foreach ($data['product_profiles'] as $profile) {
              $mysql->query("INSERT INTO `" . DB_PREFIX . "product_profile` SET `product_id` = " . (int) $product_id . ", customer_group_id = " . (int) $profile['customer_group_id'] . ", `profile_id` = " . (int) $profile['profile_id']);
            }
          }
  }

  public function editProduct($product_id, $data) {
    $mysql = $this->mysql();
    if($mysql->query("UPDATE " . DB_PREFIX . "product SET model = '" . $mysql->real_escape_string($data['model']) . "', sku = '" . $mysql->real_escape_string($data['sku']) . "', upc = '" . $mysql->real_escape_string($data['upc']) . "', ean = '" . $mysql->real_escape_string($data['ean']) . "', jan = '" . $mysql->real_escape_string($data['jan']) . "', isbn = '" . $mysql->real_escape_string($data['isbn']) . "', mpn = '" . $mysql->real_escape_string($data['mpn']) . "', location = '" . $mysql->real_escape_string($data['location']) . "', quantity = '" . (int)$data['quantity'] . "', minimum = '" . (int)$data['minimum'] . "', subtract = '" . (int)$data['subtract'] . "', stock_status_id = '" . (int)$data['stock_status_id'] . "', date_available = '" . $mysql->real_escape_string($data['date_available']) . "', manufacturer_id = '" . (int)$data['manufacturer_id'] . "', shipping = '" . (int)$data['shipping'] . "', price = '" . (float)$data['price'] . "', points = '" . (int)$data['points'] . "', weight = '" . (float)$data['weight'] . "', weight_class_id = '" . (int)$data['weight_class_id'] . "', length = '" . (float)$data['length'] . "', width = '" . (float)$data['width'] . "', height = '" . (float)$data['height'] . "', length_class_id = '" . (int)$data['length_class_id'] . "', status = '" . (int)$data['status'] . "', tax_class_id = '" . $mysql->real_escape_string($data['tax_class_id']) . "', sort_order = '" . (int)$data['sort_order'] . "', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'")) {
        echo '<p> '.$mysql->error.' Product '.$data['name'].' ( Product ID '.$product_id.' ) Updated Successfully </p>';
      } else {
        echo '<p> '.$mysql->error. ' Error Updating Product '.$data['name'].' (Product ID '.$product_id.') </p>';
      }

    if (isset($data['image'])) {
      $mysql->query("UPDATE " . DB_PREFIX . "product SET image = '" . $mysql->real_escape_string(html_entity_decode($data['image'], ENT_QUOTES, 'UTF-8')) . "' WHERE product_id = '" . (int)$product_id . "'");
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int)$product_id . "'");
    
    foreach ($data['product_description'] as $language_id => $value) {
      $mysql->query("INSERT INTO " . DB_PREFIX . "product_description SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language_id . "', name = '" . $mysql->real_escape_string($value['name']) . "', meta_keyword = '" . $mysql->real_escape_string($value['meta_keyword']) . "', meta_description = '" . $mysql->real_escape_string($value['meta_description']) . "', description = '" . $mysql->real_escape_string($value['description']) . "', tag = '" . $mysql->real_escape_string($value['tag']) . "'");
    }

    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int) $product_id . "'");

    if (isset($data['product_store'])) {
      foreach ($data['product_store'] as $store_id) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_store SET product_id = '" . (int) $product_id . "', store_id = '" . (int) $store_id . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "'");

    if (!empty($data['product_attribute'])) {
      foreach ($data['product_attribute'] as $product_attribute) {
        if ($product_attribute['attribute_id']) {
          $mysql->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$product_attribute['attribute_id'] . "'");
          
          foreach ($product_attribute['product_attribute_description'] as $language_id => $product_attribute_description) {       
            $mysql->query("INSERT INTO " . DB_PREFIX . "product_attribute SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$product_attribute['attribute_id'] . "', language_id = '" . (int)$language_id . "', text = '" .  $mysql->real_escape_string($product_attribute_description['text']) . "'");
          }
        }
      }
    }

    $mysql->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int)$product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_option'])) {
      foreach ($data['product_option'] as $product_option) {
        if ($product_option['type'] == 'select' || $product_option['type'] == 'radio' || $product_option['type'] == 'checkbox' || $product_option['type'] == 'image') {
          $mysql->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', required = '" . (int)$product_option['required'] . "'");
        
          $product_option_id = $mysql->insert_id;
        
          if (isset($product_option['product_option_value'])  && count($product_option['product_option_value']) > 0 ) {
            foreach ($product_option['product_option_value'] as $product_option_value) {
              $mysql->query("INSERT INTO " . DB_PREFIX . "product_option_value SET product_option_value_id = '" . (int)$product_option_value['product_option_value_id'] . "', product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value_id = '" . (int)$product_option_value['option_value_id'] . "', quantity = '" . (int)$product_option_value['quantity'] . "', subtract = '" . (int)$product_option_value['subtract'] . "', price = '" . (float)$product_option_value['price'] . "', price_prefix = '" . $mysql->real_escape_string($product_option_value['price_prefix']) . "', points = '" . (int)$product_option_value['points'] . "', points_prefix = '" . $mysql->real_escape_string($product_option_value['points_prefix']) . "', weight = '" . (float)$product_option_value['weight'] . "', weight_prefix = '" . $mysql->real_escape_string($product_option_value['weight_prefix']) . "'");
            }
          }else{
            $mysql->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_option_id = '".$product_option_id."'");
          }
        } else { 
          $mysql->query("INSERT INTO " . DB_PREFIX . "product_option SET product_option_id = '" . (int)$product_option['product_option_id'] . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$product_option['option_id'] . "', option_value = '" . $mysql->real_escape_string($product_option['option_value']) . "', required = '" . (int)$product_option['required'] . "'");
        }         
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int)$product_id . "'");
 
    if (isset($data['product_discount'])) {
      foreach ($data['product_discount'] as $product_discount) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_discount SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_discount['customer_group_id'] . "', quantity = '" . (int)$product_discount['quantity'] . "', priority = '" . (int)$product_discount['priority'] . "', price = '" . (float)$product_discount['price'] . "', date_start = '" . $mysql->real_escape_string($product_discount['date_start']) . "', date_end = '" . $mysql->real_escape_string($product_discount['date_end']) . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_special'])) {
      foreach ($data['product_special'] as $product_special) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_special SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$product_special['customer_group_id'] . "', priority = '" . (int)$product_special['priority'] . "', price = '" . (float)$product_special['price'] . "', date_start = '" . $mysql->real_escape_string($product_special['date_start']) . "', date_end = '" . $mysql->real_escape_string($product_special['date_end']) . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_image'])) {
      foreach ($data['product_image'] as $product_image) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_image SET product_id = '" . (int)$product_id . "', image = '" . $mysql->real_escape_string(html_entity_decode($product_image['image'], ENT_QUOTES, 'UTF-8')) . "', sort_order = '" . (int)$product_image['sort_order'] . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_download'])) {
      foreach ($data['product_download'] as $download_id) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_download SET product_id = '" . (int)$product_id . "', download_id = '" . (int)$download_id . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_category'])) {
      foreach ($data['product_category'] as $category_id) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_category SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
      }   
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int)$product_id . "'");
    
    if (isset($data['product_filter'])) {
      foreach ($data['product_filter'] as $filter_id) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_filter SET product_id = '" . (int)$product_id . "', filter_id = '" . (int)$filter_id . "'");
      }   
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int)$product_id . "'");

    if (isset($data['product_related'])) {
      foreach ($data['product_related'] as $related_id) {
        $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$product_id . "' AND related_id = '" . (int)$related_id . "'");
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$product_id . "', related_id = '" . (int)$related_id . "'");
        $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int)$related_id . "' AND related_id = '" . (int)$product_id . "'");
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_related SET product_id = '" . (int)$related_id . "', related_id = '" . (int)$product_id . "'");
      }
    }
    
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int)$product_id . "'");

    if (isset($data['product_reward'])) {
      foreach ($data['product_reward'] as $customer_group_id => $value) {
        $mysql->query("INSERT INTO " . DB_PREFIX . "product_reward SET product_id = '" . (int)$product_id . "', customer_group_id = '" . (int)$customer_group_id . "', points = '" . (int)$value['points'] . "'");
      }
    }

    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int)$product_id . "'");

    if (isset($data['product_layout'])) {
      foreach ($data['product_layout'] as $store_id => $layout) {
        if ($layout['layout_id']) {
          $mysql->query("INSERT INTO " . DB_PREFIX . "product_to_layout SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "', layout_id = '" . (int)$layout['layout_id'] . "'");
        }
      }
    }
            
    $mysql->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id. "'");
    
    if ($data['keyword']) {
      $mysql->query("INSERT INTO " . DB_PREFIX . "url_alias SET query = 'product_id=" . (int)$product_id . "', keyword = '" . $mysql->real_escape_string($data['keyword']) . "'");
    }
            
    $mysql->query("DELETE FROM `" . DB_PREFIX . "product_profile` WHERE product_id = " . (int) $product_id);   if (isset($data['product_profiles'])) {     
      foreach ($data['product_profiles'] as $profile) {       
        $mysql->query("INSERT INTO `" . DB_PREFIX . "product_profile` SET `product_id` = " . (int) $product_id . ", customer_group_id = " . (int) $profile['customer_group_id'] . ", `profile_id` = " . (int) $profile['profile_id']);     
      }   
    }   
    $this->cache->delete('product');
  }

  public function deleteProduct($product_id) {
    $mysql = $this->mysql();
    $mysql->query("DELETE FROM " . DB_PREFIX . "product WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_attribute WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_description WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_filter WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_option WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_option_value WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_related WHERE related_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_reward WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_special WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_category WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_download WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_layout WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_to_store WHERE product_id = '" . (int) $product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "product_profile WHERE product_id = " . (int) $product_id);
    $mysql->query("DELETE FROM " . DB_PREFIX . "review WHERE product_id = '" . (int)$product_id . "'");
    $mysql->query("DELETE FROM " . DB_PREFIX . "url_alias WHERE query = 'product_id=" . (int)$product_id. "'");

  }
}
?>