<?php 
class ControllerToolImport extends Controller { 
	private $error = array();
	
	public function index() {		
		$this->load->language('tool/import');

		$this->document->setTitle($this->language->get('heading_title'));
		
		$this->load->model('tool/import');
				
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->user->hasPermission('modify', 'tool/import')) {
			if (is_uploaded_file($this->request->files['file_source']['tmp_name'])) {
				$content = file_get_contents($this->request->files['file_source']['tmp_name']);
			} else {
				$content = false;
			}
			if ($content) {
				if($this->import()) {
					$this->session->data['success'] = $this->language->get('text_success');
					$this->redirect($this->url->link('tool/import', 'token=' . $this->session->data['token'], 'SSL'));
				} else {
					$this->error['warning'] = ' Try Again ';
				}
			} else {
				$this->error['warning'] = $this->language->get('error_empty');
			}
		}

		$this->data['heading_title'] = $this->language->get('heading_title');
		
		$this->data['text_select_all'] = $this->language->get('text_select_all');
		$this->data['text_unselect_all'] = $this->language->get('text_unselect_all');
		
		$this->data['entry_restore'] = $this->language->get('entry_restore');
		$this->data['entry_import'] = $this->language->get('entry_import');
		 
		$this->data['button_import'] = $this->language->get('button_import');
		$this->data['button_restore'] = $this->language->get('button_restore');
		
		if (isset($this->session->data['error'])) {
    		$this->data['error_warning'] = $this->session->data['error'];
    
			unset($this->session->data['error']);
 		} elseif (isset($this->error['warning'])) {
			$this->data['error_warning'] = $this->error['warning'];
		} else {
			$this->data['error_warning'] = '';
		}
		
		if (isset($this->session->data['success'])) {
			$this->data['success'] = $this->session->data['success'];
		
			unset($this->session->data['success']);
		} else {
			$this->data['success'] = '';
		}
		
  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL'),     		
      		'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('tool/import', 'token=' . $this->session->data['token'], 'SSL'),
      		'separator' => ' :: '
   		);
		
		$this->data['restore'] = $this->url->link('tool/import', 'token=' . $this->session->data['token'], 'SSL');

		$this->data['import'] = $this->url->link('tool/import/import', 'token=' . $this->session->data['token'], 'SSL');

		//$this->load->model('tool/import');
			
		//$this->data['tables'] = $this->model_tool_import->getTables();

		// Registry
	    $registry = new Registry();

	    // Loader
	    $loader = new Loader($registry);
	    $registry->set('load', $loader);

	    // Config
	    $config = new Config();
	    $registry->set('config', $config);

	    // Database
	    $db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
	    $registry->set('db', $db);

		$this->template = 'tool/import.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);
				
		$this->response->setOutput($this->render());
	}
	
	public function import() {
		$this->load->language('tool/import');
		
		if (!isset($this->request->post['Go'])) {
			$this->session->data['error'] = $this->language->get('error_import');
			$this->redirect($this->url->link('tool/import', 'token=' . $this->session->data['token'], 'SSL'));
		} elseif ($this->user->hasPermission('modify', 'tool/import')) {
				$this->load->model('tool/import');
			//import
				$vars = $this->request->post;
				$this->data['vars'] = $vars; 
				$charset = '';
				$description = '';
				$use_csv_header       = $this->request->post["use_csv_header"];
				$encoding             = $this->request->post["encoding"];
				$field_separate_char  = $this->request->post['field_separate_char'];
				$field_enclose_char   = $this->request->post['field_enclose_char'];
				$field_escape_char    = $this->request->post['field_escape_char'];

				$arr_encodings = $this->model_tool_import->get_encodings(); //take possible encodings list
				$arr_encodings["default"] = "[default database encoding]"; //set a default (when the default database encoding should be used)

				if(!isset($_POST["encoding"]))
				$_POST["encoding"] = "default"; //set default encoding for the first page show (no POST vars)
				//$this->model_tool_import->loaded = $this->load->model('catalog/product');
				$this->model_tool_import->Quick_CSV_import($this->request->files['file_source']['tmp_name']);

				//optional parameters
				/*$this->model_tool_import->use_csv_header      = $use_csv_header;
				$this->model_tool_import->field_separate_char = $field_separate_char;
				$this->model_tool_import->field_enclose_char  = $field_enclose_char;
				$this->model_tool_import->field_escape_char   = $field_escape_char;
				$this->model_tool_import->encoding            = $encoding;*/
				//$this->model_tool_import->import();
			$this->response->setOutput($this->model_tool_import->import());
		} else {
			$this->session->data['error'] = $this->language->get('error_permission');
			$this->redirect($this->url->link('tool/import', 'token=' . $this->session->data['token'], 'SSL'));			
		}
	}
}
?>