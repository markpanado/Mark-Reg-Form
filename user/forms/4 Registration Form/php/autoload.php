<?php 

class FBsignUp{
	var $fb;
	var $app_id;
	var $app_secret;
	var $graph_v;
	var $permissions;
	var $login_url;
	var $helper;
	var $graph_fields;
	var $user_data;
	var $mapping;

	function __construct($app_id, $app_secret, $graph_v = 'v2.7'){
		$this->app_id 		= $app_id;
		$this->app_secret 	= $app_secret;

		$this->permissions = [
			'email',
			'user_birthday',
			'user_location',
			// 'user_about_me',
			'user_education_history',
			'user_likes',
			'user_relationships',
			'user_religion_politics',
			'user_work_history',
		]; // optional

		$this->graph_fields = [
			'name',
			'first_name',
			'last_name',
			'email',
			'birthday',
			'location',
			'gender',
			'age_range',
			'link',
			'locale',
			'picture',
			'timezone',
			'updated_time',
			'verified',
			// 'about',
			'education',
			'languages',
			'relationship_status',
			'religion',
			'work'
		];

		$this->mapping = [
			[
				'type'		=> 'string',
				'db_field'	=> 'first_name',
				'fb_field'	=> 'first_name'
			],
			[
				'type'		=> 'string',
				'db_field'	=> 'last_name',
				'fb_field'	=> 'last_name'
			],
			[
				'type'		=> 'string',
				'db_field'	=> 'email',
				'fb_field'	=> 'email'
			],
			[
				'type'		=> 'object',
				'db_field'	=> 'dob',
				'fb_field'	=> 'birthday',
				'fb_prop'	=> 'date',
			],
			[
				'type'		=> 'array',
				'db_field'	=> 'school_company',
				'fb_field'	=> 'work',
				'fb_index'	=> 0,
				'fb_prop'	=> ['employer', 'name'],
			],
			[
				'type'		=> 'array',
				'db_field'	=> 'course_position',
				'fb_field'	=> 'work',
				'fb_index'	=> 0,
				'fb_prop'	=> ['position', 'name'],
			],
		];

		$this->FBinit();

		$this->FBlogOut();

		$this->FBloginCallBack();

		$this->FBgetUserData();

		$this->FBlogin();

	}

	function FBinit(){
		# CHECK SESSION
		if(session_status() == PHP_SESSION_NONE):
			session_start();
		endif;

		$this->fb = new Facebook\Facebook([
			'app_id' 		=> $this->app_id,
			'app_secret' 	=> $this->app_secret,
			'default_graph_version' => $this->graph_v,
		]);

		$this->helper = $this->fb->getRedirectLoginHelper();
	}

	function FBlogin(){
		$this->login_url = $this->helper->getLoginUrl(get_site_url().'/?callback', $this->permissions);
	}

	function FBloginCallBack(){
		if(isset($_GET['callback'])):
			try {
				$access_token = $this->helper->getAccessToken();
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				// CLEAR SESSION THEN REDIRECT INSTEAD
				unset($_SESSION['facebook_access_token']);
				header("Location:".get_site_url());

				// When Graph returns an error
				error_log('Graph returned an error: ' . $e->getMessage());
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				// CLEAR SESSION THEN REDIRECT INSTEAD
				unset($_SESSION['facebook_access_token']);
				header("Location:".get_site_url());
				
				// When validation fails or other local issues
				error_log('Facebook SDK returned an error: ' . $e->getMessage());
				exit;
			}

			if (isset($access_token)) {
				// Logged in!
				$_SESSION['facebook_access_token'] = (string) $access_token;

				// Now you can redirect to another page and use the
				// access token from $_SESSION['facebook_access_token']

				header('Location:'.get_site_url().'/#register');
				die();
			}
		endif;	
	}

	function FBlogOut(){
		if(isset($_GET['logout'])):
			if(isset($_SESSION['facebook_access_token'])):
				session_unset($_SESSION['facebook_access_token']);
				header("Location:".get_site_url().'/#register');
				die();
			endif;
		endif;
	}

	function FBgetUserData(){
		if(isset($_SESSION['facebook_access_token'])):
			// Logged in!
			$this->fb->setDefaultAccessToken($_SESSION['facebook_access_token']);

			try {
				$response = $this->fb->get('/me?fields='.implode(',', $this->graph_fields));
				$userNode = $response->getGraphUser();
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				// CLEAR SESSION THEN REDIRECT INSTEAD
				unset($_SESSION['facebook_access_token']);
				header("Location:".get_site_url());

				// When Graph returns an error
				error_log('Graph returned an error: ' . $e->getMessage());
				exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				// CLEAR SESSION THEN REDIRECT INSTEAD
				unset($_SESSION['facebook_access_token']);
				header("Location:".get_site_url());

				// When validation fails or other local issues
				error_log('Facebook SDK returned an error: ' . $e->getMessage());
				exit;
			}

			$this->user_data = $userNode->asArray();

			// echo '<pre>';
			// var_dump($userNode->getProperty('education'));
			// var_dump(json_encode($userNode['work']->asArray()));
			// var_dump($userNode['relationship_status']);
			// var_dump($this->user_data['work'][0]['employer']['name']);
			// var_dump(get_object_vars($this->user_data['birthday'])['date']);
			// echo '</pre>';

			// session_unset();
			// session_destroy();
		endif;
	}

	function render(){
		$return = '';
		$msg 	= 'Sign up using Facebook';
		$close 	= '';

		if(!empty($this->user_data)):
			$msg 	= $this->user_data['name'];
			$close 	= '<a href="/?logout" class="logout" title="Log Out"></a>';
		endif;

		$return .= '<div id="FBsignUp">';	
			$return .= '<a href="'.$this->login_url.'">'.$msg.'</a>';
			$return .= $close;
		$return .= '</div>';	

		return $return;
	}

	function debug(){
		echo '<pre>';
		print_r($this->fb);
		echo '</pre>';
	}
}

// $this->FB_sign_up = new FBsignUp('1122682714436623', 'a21f1d1173e65b5028dedd3449ebda79');

// $MjFB->debug();





