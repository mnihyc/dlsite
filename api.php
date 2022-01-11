<?php

namespace OneDrive
{
    define('OneDrive\ENABLED', FALSE);
    // Follow instructions on "https://docs.microsoft.com/en-us/onedrive/developer/rest-api/getting-started/graph-oauth?view=odsp-graph-online" (Code flow) to get the following token(s).
    // Required scopes: offline_access Files.Read Files.Read.All Files.ReadWrite Files.ReadWrite.All
    // Do NOT share it with other applications!
    // May also get the following token by "rclone authorize"
    define('OneDrive\CLIENT_ID','');
    define('OneDrive\CLIENT_SECRET','');
    /* After having it initialized properly, please leave REFRESH_TOKEN blank */
    /* refresh_token in database will be replaced by the following value if not empty */
    /* Set this only when an update of refresh_token is needed */
    define('OneDrive\REFRESH_TOKEN','');
    /* Same as the one used when requesting $refresh_token (no accessibility required) */
    define('OneDrive\REDIRECT_URI','');
}
namespace GoogleDrive
{
    
    
}

// ^----------------------------------------------

namespace
{
    class Request
    {
        private $ch=null;
        public $httpcode=0;
        public function __construct()
        {
            $this->ch=curl_init();
            curl_setopt($this->ch,CURLOPT_TIMEOUT,6);
            curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,TRUE);
            curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,TRUE);
            curl_setopt($this->ch,CURLOPT_USERAGENT,'NONISV|mnihyc|dlsite/1.0');
        }
        public function __destruct()
    	{
    		curl_close($this->ch);
    	}
    	public function setauth($auth)
    	{
    	    curl_setopt($this->ch,CURLOPT_HTTPHEADER,array("Authorization: bearer {$auth}"));
    	}
    	public function get($url)
    	{
    	    curl_setopt($this->ch,CURLOPT_POST,FALSE);
    		curl_setopt($this->ch,CURLOPT_URL,$url);
    		$result=curl_exec($this->ch);
    		$this->httpcode=curl_getinfo($this->ch,CURLINFO_HTTP_CODE);
    		return $result;
    	}
    	public function post($url,$params)
    	{
    	    if(is_array($params))
    			curl_setopt($this->ch,CURLOPT_POSTFIELDS,http_build_query($params));
    		else if(is_string($params))
    			curl_setopt($this->ch,CURLOPT_POSTFIELDS,$params);
    		curl_setopt($this->ch,CURLOPT_POST,TRUE);
    		curl_setopt($this->ch,CURLOPT_URL,$url);
    		$result=curl_exec($this->ch);
    		$this->httpcode=curl_getinfo($this->ch,CURLINFO_HTTP_CODE);
    		return $result;
    	}
    	public function getloc($url)
    	{
    	    curl_setopt($this->ch,CURLOPT_HEADER,TRUE);
            curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,FALSE);
            $res=$this->get($url);
            $loc='';
            if(preg_match('~Location: (.*)~i',$res,$match))
                $loc=trim($match[1]);
            curl_setopt($this->ch,CURLOPT_HEADER,FALSE);
            curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,TRUE);
            return $loc;
    	}
    }
    
    include_once 'inc.php';
    define('_TOKEN_PREFIX','_DO_NOT_MODIFY_');
    $_onedrive_error_string='';
}
namespace OneDrive
{
    define('OneDrive\PREURL',"https://graph.microsoft.com/v1.0");
    function getlasterror()
    {
        global $_onedrive_error_string;
        return $_onedrive_error_string;
    }
    function setlasterror($str)
    {
        global $_onedrive_error_string;
        $_onedrive_error_string=$str;
    }
    function getaccesstoken()
    {
        if(!empty(getlasterror()))
            return false;
        $ret=\getaccesstoken(\_TOKEN_PREFIX,'OneDrive');
        /* update when REFRESH_TOKEN is set or accesstoken not found */
        if(!empty(REFRESH_TOKEN) || $ret===false)
        {
            $ret=\getrefreshtoken(\_TOKEN_PREFIX,'OneDrive');
            if(!empty(REFRESH_TOKEN))
            {
                \updaterefreshtoken(\_TOKEN_PREFIX,'OneDrive',REFRESH_TOKEN);
                $ret=REFRESH_TOKEN;
            }
            $arr=updateaccesstoken($ret);
            \updateaccesstoken(\_TOKEN_PREFIX,'OneDrive',time()+intval($arr['expires_in']),$ret=$arr['access_token']);
            \updaterefreshtoken(\_TOKEN_PREFIX,'OneDrive',$arr['refresh_token']);
        }
        return $ret;
    }
    function updateaccesstoken($refresh_token)
    {
        if(!empty(getlasterror()))
            return false;
        $arr=array(
            'client_id' => CLIENT_ID,
            'redirect_uri' => REDIRECT_URI,
            'client_secret' => CLIENT_SECRET,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        );
        $req=new \Request;
        $res=$req->post('https://login.microsoftonline.com/common/oauth2/v2.0/token',$arr);
        if($req->httpcode!=200)
        {
            setlasterror("API calls (access_token) failed with HTTP CODE {$req->httpcode}");
            return false;
        }
        $arr=json_decode($res,true);
        if(empty($arr) || $arr['token_type']!=='Bearer')
        {
            setlasterror("API calls (access_token) failed unexpectedly");
            return false;
        }
        return $arr;
    }
    function getid($path)
    {
        if(!empty(getlasterror()))
            return false;
        $access_token=getaccesstoken();
        $req=new \Request;
        $req->setauth($access_token);
        $path=\encodeapidir($path);
        $res=$req->get(PREURL."/me/drive/root:{$path}");
        //var_export(PREURL."/me/drive/root:{$path}");var_export($res);
        if($req->httpcode==404)
        {
            setlasterror("404 File not found");
            return false;
        }
        if($req->httpcode==429)
        {
            setlasterror("API calls (Get item) being rate limited, please try again later");
            return false;
        }
        if($req->httpcode!=200 && $req->httpcode!=401)
        {
            setlasterror("API calls (Get item) failed with HTTP CODE {$req->httpcode}");
            return false;
        }
        $arr=json_decode($res,true);
        if(empty($arr) || empty($arr['id']))
        {
            {
            setlasterror("API calls (Get item) failed unexpectedly");
            return false;
        }
        }
        return $arr['id'];
    }
    function getlink($path)
    {
        if(!empty(getlasterror()))
            return false;
        $access_token=getaccesstoken();
        $req=new \Request;
        $req->setauth($access_token);
        $path=\encodeapidir($path);
        $loc=$req->getloc(PREURL."/me/drive/root:{$path}:/content");
        if($req->httpcode==404)
        {
            setlasterror("404 File not found");
            return false;
        }
        if($req->httpcode==429)
        {
            setlasterror("API calls (Download) being rate limited, please try again later");
            return false;
        }
        if($req->httpcode!=200 && $req->httpcode!=302)
        {
            setlasterror("API calls (Download) failed with HTTP CODE {$req->httpcode}");
            return false;
        }
        if(empty($loc))
        {
            setlasterror("API calls (Download) failed unexpectedly");
            return false;
        }
        return $loc;
    }
    function getpreview($path)
    {
        if(!empty(getlasterror()))
            return false;
        $id=getid($path);
        if($id===false)
            return false;
        $access_token=getaccesstoken();
        $req=new \Request;
        $req->setauth($access_token);
        $res=$req->post(PREURL."/me/drive/items/{$id}/preview",array());
        if($req->httpcode==429)
        {
            setlasterror("API calls (Preview) being rate limited, please try again later");
            return false;
        }
        if($req->httpcode!=200 && $req->httpcode!=401)
        {
            setlasterror("API calls (Preview) failed with HTTP CODE {$req->httpcode}");
            return false;
        }
        $arr=json_decode($res,true);
        if(empty($arr) || empty($arr['getUrl']))
        {
            setlasterror("API calls (Preview) failed unexpectedly");
            return false;
        }
        return $arr['getUrl'];
    }
}
?>