<?php 

include_once 'ajax-search-lite/ajax-search-lite.php';
// include_once 'full-screen-popup/full-screen-popup.php';

/*
Plugin Name: MostIQ BaHam
Plugin URI: http://www.mostiq.com
Description: Register your posts to new search engine & SEO
Version: 0.2
Author: S.G.
Author URI:
License: GPL
*/
error_reporting(E_ERROR);


if(!class_exists('MIQ_BaHam')):

class MIQ_BaHam{

	public $plugin_url;
	public $plugin_path;

	private static $instance;

	/**
	 * Singleton
	 *
	 * @return sls_modules_manager
	 * @since 1.0
	 */
	public static function instance()
	{
		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof MIQ_BaHam ) ) {
			self::$instance = new MIQ_BaHam;
		}
		return self::$instance;
	}

	function __construct()
	{

		if(!session_id()) session_start();

		register_activation_hook( __FILE__, array( $this, 'install' ) );

		$this->plugin_url = plugin_dir_url(__FILE__);
		$this->plugin_path = plugin_dir_path(__FILE__);


		$this->includes();

		add_action( 'init', array($this, 'init'));//$this->init();
		add_action( 'wp_enqueue_scripts', array( $this , 'wp_enqueue_scripts' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
		
		add_action("wp_ajax_register_terms_aj", array($this, 'register_terms_aj'));
		add_action("wp_ajax_nopriv_register_terms_aj", array($this, 'register_terms_aj'));

		add_action("wp_ajax_start_index", array( $this, "start_index") );
		
		add_action("wp_ajax_get_terms_4_point", array($this, 'get_terms_4_point'));
		add_action("wp_ajax_nopriv_get_terms_4_point", array($this, 'get_terms_4_point'));
		
		
		add_filter("asl_results", array($this, 'miq_asl_results'), 10, 2);//change ajax search lite responses
		
		if(is_admin()){
			add_action( 'admin_enqueue_scripts', array( $this , 'admin_enqueue_scripts' ) );
			add_action('admin_init', array(&$this, 'admin_init'));//
		}


	}

	function install()
	{
		global $wpdb;

		/*
		 * We'll set the default character set and collation for this table.
		* If we don't do this, some characters could end up being converted
		* to just ?'s when saved in our table.
		*/
		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			
		$sql = "
				CREATE TABLE IF NOT EXISTS `miq_stop_words` (
			  	`msw_id` int(11) NOT NULL AUTO_INCREMENT,
			  	`msw_word` text COLLATE utf8_unicode_ci NOT NULL,
			  	PRIMARY KEY (`msw_id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;
		";
		dbDelta( $sql );

		$sql = "
			CREATE TABLE IF NOT EXISTS `miq_client_terms_relation` (
			  `mtr_id` int(11) NOT NULL AUTO_INCREMENT,
			  `mtr_post_type` varchar(16) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL COMMENT 'post/page/...',
			  `mtr_post_id` int(11) NOT NULL COMMENT 'group of terms',
			  `mtr_point` int(11) NOT NULL COMMENT 'terms group',
			  `mtr_term` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			  `mtr_reg_date` int(11) NOT NULL,
			  `mtr_is_yit` varchar(1) NOT NULL COMMENT 'is_your_introduced_term',
			  `mtr_1001_score` smallint(6) NOT NULL,
			  PRIMARY KEY (`mtr_id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
		";
		dbDelta( $sql );

		
		
		
		
		//options setting
		if(!get_option('miq_search_settings')){
			$search_settings = maybe_unserialize(get_option('miq_search_settings', array(
				'selected_search_order' => SO_MY_BLOG_NO_EXACT_MIQ,
				'miq_centeral_server' => 'http://mostiq.com/'
			)));
			update_option('miq_search_settings', maybe_serialize($search_settings));
		}
		
	}
	
	function admin_init()
	{
	}
	
	function admin_menu()
	{
		global $miq_baham_menu;
		$miq_baham_menu = new MIQ_BaHam_Menu();
		
		$main_page = add_menu_page( 
			__( 'Most IQ BaHam', 'mostiq_baham' ), 
			__( 'MIQ BaHam', 'mostiq_baham' ), 
			'manage_options',
			'mostiq_main_menu' , 
			array( $miq_baham_menu, 'mostiq_main_menu' ), 
			null, 
			'55.5' 
		);
		add_submenu_page(
			'mostiq_main_menu',
			__("Search Widget", 'mostiq_baham'),
			__("Search Widget", 'mostiq_baham'),
			'manage_options',
			'search_widget',
			array( $miq_baham_menu, 'search_widget' )  
		); 
		add_submenu_page(
			'mostiq_main_menu',
			__("Indexer Set", 'mostiq_baham'),
			__("Indexer Set", 'mostiq_baham'),
			'manage_options',
			'indexer_set',
			array( $miq_baham_menu, 'indexer_set' )  
		); 
		add_submenu_page(
			'mostiq_main_menu',
			__("Indexer List", 'mostiq_baham'),
			__("Indexer List", 'mostiq_baham'),
			'manage_options',
			'indexer_list',
			array( $miq_baham_menu, 'indexer_list' )  
		); 
		add_submenu_page(
			'mostiq_main_menu',
			__("Promote", 'mostiq_baham'),
			__("Promote", 'mostiq_baham'),
			'manage_options',
			'promote_content',
			array( $miq_baham_menu, 'promote_content' )  
		); 
	}
	
	function admin_enqueue_scripts()
	{ 
        wp_enqueue_script('jquery');
        wp_enqueue_script('miq-jquery-ui', $this->plugin_url.'assets/js/miq-jquery-ui.js', array( 'jquery' ));
		wp_enqueue_style('jquery-ui-css', $this->plugin_url.'assets/css/jquery-ui.css'); 
		wp_enqueue_style('promote_css', $this->plugin_url.'assets/css/promote.css'); 
        wp_register_script('baham_main', $this->plugin_url.'assets/js/baham_main.js', array( 'jquery' ));
        wp_enqueue_script('baham_main');
		wp_localize_script( 
			'baham_main',
			'local_values', 
			array(
				'ajaxurl' => admin_url('admin-ajax.php')
			) 
		);
		
// 		wp_enqueue_script( 'jquery-ui-core' );
// 		wp_enqueue_script( 'jquery-ui-autocomplete');
// 		wp_enqueue_script( 'jquery-ui-dialog' );
// 		wp_enqueue_script( 'jquery-ui-datepicker' );
// 		wp_enqueue_script( 'jquery-ui-sortable' );
		
	}
	function wp_enqueue_scripts()
	{
		wp_enqueue_style('baham_css', $this->plugin_url.'assets/css/baham.css');
		wp_enqueue_style('promote_css', $this->plugin_url.'assets/css/promote.css'); 
        wp_enqueue_script('baham_main', $this->plugin_url.'assets/js/baham_main.js', array( 'jquery' ));
        
		
	}


	function includes()
	{
		include_once 'classes/settings.php';
		include_once 'classes/baham_menu.php';
		include_once 'classes/remote_handler.php';
		include_once 'classes/functions.php';
	}

	function init()
	{
		global $wpdb;
		if(WP_DEBUG) $wpdb->show_errors = true;
	}

	function register_terms_aj($params=array())
	{
		$cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';
		$term1 = isset($_REQUEST['term1']) ? urldecode(trim($_REQUEST['term1'])) : '';
		$term2 = isset($_REQUEST['term2']) ? urldecode(trim($_REQUEST['term2'])) : '';
		$term3 = isset($_REQUEST['term3']) ? urldecode(trim($_REQUEST['term3'])) : '';
		$term_answer = isset($_REQUEST['term_answer']) ? urldecode(trim($_REQUEST['term_answer'])) : '';
		switch($cmd){
			case 'register_and_connect':
				$this->create_and_insert_combinations(array(
					'term1' => $term1,
					'term2' => $term2,
					'term3' => $term3,
					'term_answer' => $term_answer,
				));
				break;
		}
	}

	
	function miq_asl_results($results=array(), $term='')
	{
		
		global $search_priority;
		global $is_on_miq_core;
		if($is_on_miq_core){
			//completely rewrite the results
			global $mostiq_core;
			$new_res = $mostiq_core->retrieve_autocomplete_terms($term);
			return $new_res['obj_auto_terms'];
			
		}else{
			//depend on configurations, add also results based on term-graphs & mostiq database
			$search_settings = maybe_unserialize(get_option('miq_search_settings'));
			$selected_search_order = $search_settings['selected_search_order'];


			if($selected_search_order==SO_ONLY_MY_BLOG){
				//FIXME:later should be added (retrieve from local full term & local graph)
				return $results;
				
			}else{
				$has_exact_match = $has_partial_match = false;
				foreach($results as $local_res){
					if($local_res->title==$term) $has_exact_match = true;
					if(mb_stristr($local_res->title, $term)) $has_partial_match = true;
				} 
				
				if(BAHAM_DEBUG){
					MIQ_Log::log_it(array(
						'type' => 'notice',
						'message' => $search_priority[$selected_search_order]['key'].' '.($has_exact_match?'has_exact_match ':'').($has_partial_match?'has_partial_match ':'').' term:'.$term
					));
				}
				
				if(
					(($selected_search_order==SO_MY_BLOG_NO_EXACT_MIQ) && !$has_exact_match)
					||
					(($selected_search_order==SO_MY_BLOG_NO_PARTIAL_MIQ) && !$has_partial_match)
					|| 
					($selected_search_order==SO_MY_BLOG_AND_MIQ)
				){
					
					//retrieve headers from mostiq.com
					$remote_req = array(
						'cmd' => 'get_autocomplete', 
						'remote_term' => urlencode($term)
					);
					
					$response = $this->do_remote_req($remote_req);
					if($response['miq_stus']=='ok'){
						$auto_offers = $response['miq_body']; 
						$remote_res = array();
						foreach($auto_offers as $remote_resp){
							$result_row = new stdClass();
							$result_row->title = urldecode($remote_resp);
							$result_row->id = 0;
							$result_row->date = '';
							$result_row->content = '';
							$result_row->excerpt = '';
							$result_row->author = '';
							$result_row->ttid = '--1--';
							$result_row->post_type = 'post';
							$result_row->relevance = 34;
							$result_row->image = '';
							$result_row->link = $search_settings['miq_centeral_server'].urldecode($remote_resp);
							$result_row->target = '_new'; 
							$remote_res[] = $result_row;
						}
						
					} 
				}
			}
				
			return array_merge($results, $remote_res);
		}
	}

	function get_terms_4_point()
	{
		global $wpdb;
		$point_id = isset($_REQUEST['pid']) ? (int)$_REQUEST['pid'] : 0;
		if($point_id==0){
			echo json_encode(array(
				'stus' => false,		
				'msg' => 'Empty point id',		
			));
			die();
		}
		
		//retrieve all equal terms for this point
		$eql_q = "SELECT * FROM miq_client_terms_relation WHERE mtr_point=".$point_id;
		$eql_terms = $wpdb->get_results($eql_q, ARRAY_A);
		//print_r($eql_terms);
		echo json_encode($eql_terms);
		exit();
	}

	function start_index()
	{
		global $wpdb;
		
		//retrieve index settings

		$search_settings = maybe_unserialize(get_option('miq_search_settings'));
		
		$account_settings = maybe_unserialize(get_option('miq_account_settings', array(
				'miq_user_id' => '',
				'miq_uri_umeta_id' => '',
				'user_name' => '',
				'user_pass' => '',
				'user_email' => '',
				'has_acount' => false
		)));
		
		if(!$account_settings['has_acount']){
			//first must create an account in mostiq.com
			$miq_email = isset($_REQUEST['miq_email']) ? $_REQUEST['miq_email'] : ''; 
			$miq_password = isset($_REQUEST['miq_password']) ? $_REQUEST['miq_password'] : '';
			$remote_req = array(
				'cmd' => 'create_account',
				'miq_email' => urlencode($miq_email),
				'miq_password' => self::miq_hash_password($miq_password),
			);

			if(BAHAM_DEBUG){
				MIQ_Log::log_it(array(
					'type' => 'notice',
					'message' => 'send remote req (create_account): '.$miq_email.' '.$miq_password
				));
			}
			
			$response = $this->do_remote_req($remote_req);
			
			if('ok'==$response['miq_stus']){
				//save account info in acount setting for blog
				//FIXME: later multi author blog feature should be supported
				$account_settings = array(
					'miq_user_id' => $response['miq_body']['user_id'],
					'miq_uri_umeta_id' => $response['miq_body']['umeta_id'],
					'user_name' => $response['miq_body']['user_email'],
					'user_email' => $response['miq_body']['user_email'], 
					'user_pass' => $response['miq_body']['user_pass'],
					'has_acount' => true
				);
				update_option('miq_account_settings', maybe_serialize($account_settings));
				
				//print right command for js to start indexing
				echo json_encode(array(
					'stus' => 'success',	
					'cmd_done' => 'account_is_created'	
				));
				exit();
				
			}else{
				//print 
				echo json_encode(array(
					'stus' => 'fail',	
					'cmd_done' => 'account_is_not_created'	
				));
				exit();
			}
			exit();
				
		}
		
		
		//indexing
		$index_settings = maybe_unserialize(get_option('miq_index_settings'));
		
		$type_claus = "";
		foreach($index_settings['content_types'] as $type_row) $type_claus .= " post_type='".$type_row."' OR ";
		if(!empty($type_claus)) $type_claus = substr($type_claus, 0, -3);
		
		$stus_claus = "";
		foreach($index_settings['content_statuses'] as $stus_row) $stus_claus .= " post_status='".$stus_row."' OR ";
		if(!empty($stus_claus)) $stus_claus = substr($stus_claus, 0, -3);
		
		
		$content_q = "
			SELECT * FROM {$wpdb->posts} 
			WHERE 1 ".
			(!empty($type_claus)? "AND ($type_claus) ":"").
			(!empty($stus_claus)? "AND ($stus_claus) ":"").
			" ORDER BY post_date, post_type, post_status";
		$content_res = $wpdb->get_results($content_q, ARRAY_A);
		foreach($content_res as $client_content_row){
			//check if is indexed
			$is_indexed_q = "SELECT * FROM {$wpdb->postmeta} WHERE post_id=".$client_content_row['ID']." AND meta_key='miqm_is_indexed' AND meta_value='y' ";
			$is_indexed = $wpdb->get_row($is_indexed_q, ARRAY_A);
			if(!$is_indexed){
				//send remote request for indexing
				$excerpt = (!empty($client_content_row['post_excerpt']) ? $client_content_row['post_excerpt'] : miq_subword($client_content_row['post_content']).'...');
				$post_link = get_permalink($client_content_row['ID']);
				$remote_req = array(
					'cmd' => 'index_me',
					'miq_privacy' => 'u',//by default all indexed contents should be public
					'post_title' => urlencode($client_content_row['post_title']),
					'post_excerpt' => urlencode($excerpt),
					'permalink' => urlencode($post_link)
				);

				if(BAHAM_DEBUG){
					MIQ_Log::log_it(array( 
					'type' => 'notice',
					'message' => 'send remReq (index): '.$client_content_row['post_title']
								."\n".$excerpt
								."\n".$post_link
					)); 
				}
				
				$response = $this->do_remote_req($remote_req);
				if('ok'==$response['miq_stus']){

					$real_1001_score_for_term = $response['miq_body']['real_1001_score_for_term'];
					$is_new_term = $response['miq_body']['is_nt'];
					$is_your_introduced_term = $response['miq_body']['is_yit'];
					$is_new_response = $response['miq_body']['is_nr'];
					$is_new_response_for_this_term = $response['miq_body']['is_nr4tt'];
					//set postmeta as indexed
					update_post_meta($client_content_row['ID'], 'miqm_is_indexed', 'y');
					update_post_meta($client_content_row['ID'], 'miqm_1001_score', $real_1001_score_for_term);
					update_post_meta($client_content_row['ID'], 'miqm_is_yit', $is_your_introduced_term);
					update_post_meta($client_content_row['ID'], 'miqm_is_nr', $is_new_response);
					update_post_meta($client_content_row['ID'], 'miqm_is_nr4tt', $is_new_response_for_this_term);
					
					$search_settings = maybe_unserialize(get_option('miq_search_settings'));
					$miq_mtr_point = (int)get_option('miq_mtr_point');
					$miq_mtr_point++;
					update_option('miq_mtr_point', $miq_mtr_point);
					
					//insert indexed term in terms relations
					$wpdb->insert('miq_client_terms_relation', array(
						'mtr_post_type' => $client_content_row['post_type'],
						'mtr_post_id' => $client_content_row['ID'],
						'mtr_point' => $miq_mtr_point,
						'mtr_term' => $client_content_row['post_title'],
						'mtr_reg_date' => time(),
						'mtr_is_yit' => $is_your_introduced_term,
						'mtr_1001_score' => $real_1001_score_for_term
					));
					
					//print right command for js to continue indexing
					echo json_encode(array(
							'stus' => 'success',
							'cmd_done' => 'content_indexed',
							'msg' => '<div style="'.($is_your_introduced_term=='y'?'color:green; font-weight:bold; ':'').'" ><div>Content indexed: ('.$client_content_row['post_type'].') '.($is_your_introduced_term=='y'?'(NEW!) ':'').''.$client_content_row['post_title'].' </div>'
									 .'<div>Your <a href="'.trim($search_settings['miq_centeral_server'], '/').'/1001-score" target="_new" >1001 Score</a>: '.$response['miq_body']['real_1001_score_for_term'].'</div>'
									 .'</div>'
					));
					exit();
				
				}else{
					//print
					echo json_encode(array(
							'stus' => 'fail',
							'cmd_done' => 'indexing_is_failed'
					));
					exit();
				}
				//exit loop & script after indexing each post 
				exit();
			}
		}
		
		//there is no new content to index
		echo json_encode(array(
			'stus' => 'success',
			'cmd_done' => 'index_finished',
			'msg' => '<div style="color:green; font-weight:bold;" >Congratulation!<br />Indexing successfully finished. go to <a href="'.admin_url('admin.php?page=indexer_list').'" >Index Report</a> to see report.<div>'
		));
		exit();

		
		
	}


	function do_remote_req($params=array())
	{
		$remote = NEW MIQ_Remote_Handler();
		$res = $remote->do_remote_req($params);
		return $res;
	}
	

	static function miq_hash_password($str){
		return wp_hash_password($str);
	}
	
}


if( ! function_exists( 'miq_baham_init' ) ) {
	function miq_baham_init() {
		global $miq_baham;
		$miq_baham = MIQ_BaHam::instance();
		return $miq_baham;
	}
}

miq_baham_init();

endif;



?>