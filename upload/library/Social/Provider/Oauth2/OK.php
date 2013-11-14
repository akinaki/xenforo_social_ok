<?php

/**
 * Helper for OK integration.
 */
class Social_Provider_Oauth2_OK extends Social_Provider_Oauth2_Abstract
{
	public $provider = 'ok';
	public $tokenUrl = 'http://api.odnoklassniki.ru/oauth/token.do';
	public $authUrl = 'http://www.odnoklassniki.ru/oauth/authorize';
	public $scope = '';


	public function getAccessToken($redirectUri)
	{
		if (!$code = $this->_controller->getInput()->filterSingle('code', XenForo_Input::STRING))
		{
			throw $this->_controller->responseException(
				$this->_controller->responseMessage(new XenForo_Phrase('social_invalid_authorization_code'))
			);
		}

		$tokenUrl = 'http://api.odnoklassniki.ru/oauth/token.do';
		$client = XenForo_Helper_Http::getClient($tokenUrl);

		$client->setParameterPost(array(
		                               'client_id' => XenForo_Application::getOptions()->okAppId,
		                               'client_secret' => XenForo_Application::getOptions()->okAppSecret,
		                               'redirect_uri' => $redirectUri,
		                               'code' => $code,
		                               'grant_type' => 'authorization_code',
		                          ));

		$body = $client->request('POST')->getBody();

		$response = json_decode($body, true);
		return $response;
	}

	public function isValidToken($token)
	{
		return !empty($token['access_token']);
	}

	public function getProfile($redirectUri=null)
	{
		$token=XenForo_Application::getSession()->get($this->provider.'_token');

		if ($token===false)
		{
			$redirectUri = XenForo_Link::buildPublicLink('canonical:register/'.$this->provider);
			if (isset($_GET['assoc']))
			{

				$redirectUri = XenForo_Link::buildPublicLink('canonical:register/'.$this->provider) . '&assoc=1';
			}
			$response = $this->getAccessToken($redirectUri);

			if (isset($response['access_token']))
			{
				$access_token = $response['access_token'];
			}
		}
		else
		{
			$access_token = $token['access_token'];
		}


		if (isset($access_token))
		{

			$client = XenForo_Helper_Http::getClient('http://api.odnoklassniki.ru/fb.do');
			$sign= md5('application_key=CBAMHBILABABABABAmethod=users.getCurrentUser'.md5($access_token.'BC8D71BE69C43EE8FA1BB33F'));
			$get = array(

				'access_token' =>$access_token,
				'application_key' => 'CBAMHBILABABABABA',
				'sig' => $sign,
				'method' =>	'users.getCurrentUser',
			);
			$client->setParameterGet($get);
			$body = $client->request('GET')->getBody();
			$response = json_decode($body,true);

		$profile['auth_id'] = isset($response['uid']) ? $response['uid'] : 0;
		$profile['screen_name'] = isset($response['screen_name']) ? $response['screen_name'] : '';
		$profile['first_name'] = isset($response['first_name']) ? $response['first_name'] : '';
		$profile['last_name'] = isset($response['last_name']) ? $response['last_name'] : '';
		$profile['username'] = !empty($response['nickname']) ? $response['nickname'] : $profile['first_name'].' '.$profile['last_name'];
		$profile['profile_url'] = 'http://vk.com/'. $profile['screen_name'];

		if(isset($response['photo_big']) && strpos($response['photo_big'],'http')!==false)
		{
			$profile['avatar_url'] = $response['photo_big'];
		}

		if(isset($response['bdate']))
		{
			$birthdayParts = explode('.', $response['bdate']);

			if (count($birthdayParts) == 3)
			{
				list($profile['dob_day'], $profile['dob_month'], $profile['dob_year']) = $birthdayParts;
			}
		}

		if (isset($response['sex']))
		{
			switch ($response['sex'])
			{
				case '2': $profile['gender'] = 'male'; break;
				case '1': $profile['gender'] = 'female'; break;
			}
		}
		return $profile;
	}

	}
}