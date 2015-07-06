<?
/*
Ahmad muhammad arafa .
6:58 AM .
25 NoV. 2010
updated at 1:00 AM may 01 2012 
update at 12:47 PM jun 24 2013
how to use


$HTTP=&new HTTP;

$
 = array(
  "method" => "post",
  "action" => "http://localhost/myClasses/tests/_test.php",
  "type"   => "multipart",                                  //to define is it multipart or no
  "multiData" => array("fileOne","fileTwo"),               // which inputs is multiData 
  "vars"=>array(
    "fileOne"=>"text.txt",
	"fileTwo"=>"../../image.jpg",
	"myName" => "ahmad arafa",
	"myNaame" => "ahmad arafa",
	"agea"    => "25y"
  ),
  "output"=>"body"                                     // output (header , body , botharray , bothString ) ,
  "saveTo" => "./"
);
$HTTP->send($post);
print_r( $HTTP->output() );

*/
Class http{
	public $method;
	public $cookies;
	public $host;
	public $port;
	public $path;
	public $query;
	public $handle;
	public $request;
	public $output;
	public $boundary;
	public $recursive;
	private $data  = null ;
	public $cookiesOutput = null ;
	function send($data)
	{
		$this->data = $data ;
		$useragent        	=      "Opera/9.90 (Windows NT 5.1; U; en) Presto/2.6.30 Version/10.60";
		$this->method   	=      $data["method"];
		$this->cookies  	=      $data["cookies"];
		/*$this->action     =      $data["action"] ;*/
		$parsedUrl        	=      parse_url($data["action"]);
		$this->host     	=      $parsedUrl["host"];
		$this->port        	=      array_key_exists("port",$parsedUrl) ? $parsedUrl["port"] : 80;
		$this->query        =      $parsedUrl["query"];
		$this->useragent  	=      array_key_exists("useragent",$data) ? $data["useragent"] : $useragent;
		$this->output       =      array_key_exists("output",$data)?$data["output"]:"bothstring";
		$this->type         =      $data["type"]=="multipart" ? "multipart" : "normal";
		$this->recursive    =      array_key_exists("recursive",$data)?$data["recursive"]:false;
		$this->referer      =      array_key_exists("referer",$data)?$data["referer"]:$parsedUrl["scheme"]."://".$parsedUrl["host"];
		$this->saveTo	= array_key_exists("saveTo" , $data) ? $data["saveTo"] : null ;
		
		$isQueried          =      false;
		$this->oAuth = null;
		if(array_key_exists("query",$parsedUrl))
		{
			$isQueried  = true;
			$this->path = $parsedUrl["path"]."?".$parsedUrl["query"];
		}
		else
		{
			$isQueried  = false;
			$this->path = $parsedUrl["path"];
		}
                
                $this->hostReq = $this->host;
		
		if($parsedUrl["scheme"]=="https")
		{
		        $this->hostReq = $this->host;
			$this->host = "ssl://".$this->host;
			$this->port = 443;
		}
		
		
		$this->vars       =   $this->Avars =   $data["vars"];
	
		if(is_array($this->vars))
		{
			
			$varsOut = "";
			foreach($this->vars as $key=>$value)
			{
				$varsOut.=rawurlencode($key)."=".rawurlencode($value)."&";
			}
			
			$this->vars = substr($varsOut,0,-1);
		}
		if(array_key_exists("oAuth",$data))
		{
		        $oAuthData = array();
		        $oAuthData = $data["oAuth"] ;
		        $oAuthData["action"] = $data["action"];
		        $oAuthData["method"] = $this->method;
		        $oAuthData["vars"] = "&".$this->vars;
		         $oAuthData["_vars"] = $this->Avars;
		        $this->createOAuth($oAuthData);
		}
		if($this->method == "get")
		{
			if($isQueried)
			{
				$this->path = $this->path."&".$this->vars;
			}
			else
			{
				$this->path = $this->path."?".$this->vars;
			}
			
		}
		if($this->type == "multipart")
		{
			$this->createMultiPartRequest($data["vars"],$data["multiData"]);
		}
		
		if(isset($data["proxy"]) AND is_array($data["proxy"]))
		{
			$this->host = $data["proxy"]["ip"] ; 
			$this->port = $data["proxy"]["port"] ; 
			$this->path = $data["action"] ; 
			
		}
		
		if(!$this->doConnect()) { 
			$this->out = false;
			return false;
		}
		$this->method();
		$this->request();
		
	}
	
	function request()
	{
		
		
		$fHandler = null ;
		if($this->saveTo != null )
		{
			
			$fHandler = fopen($this->saveTo , "w+") ;
			
			
			
			
			
			
			
		}
		if($this->handle)
		{
			fputs($this->handle,$this->request);
			$headerEnd = false; $isChuncked = false;
			if($this->output=="nill")
			{
				
				$this->out = "nill";
				return;
			}
			while(!feof($this->handle))
			{
				$requestOut = fgets($this->handle,128);
				if($headerEnd != true)
				{
					$header.=$requestOut;
					if($requestOut == "\r\n")
					{
						$headerEnd = true ;
						$bodyStart = true;
						
						if(preg_match("/Transfer-Encoding: chunked/i",$header))
						{
							$isChuncked = true ; 
						}
					}				
				}
				else
				{
						
						if($this->output != "header")
						{
							if($fHandler != null )
							{
								
								if($isChuncked)
								{
									
									fwrite($fHandler , $this->unChunk($requestOut) ) ;
								}
								else
								{
									
									fwrite($fHandler , $requestOut) ;
								}
								
								
								
							}
							else
							{
								$body.=$requestOut;								
							}
							
							
						}
						else
						{
							// continue;
							// should be break ! ;
							
							continue ; 
						}
				}
				
				
			}
			
			$this->parseHeader($header); 
			fclose($this->handle);
			if($fHandler != null){
				$body = $this->saveTo ; 
				fclose($fHandler) ;
			}
				
		
			if(strstr($header,"Location:") and $this->recursive)
			{
				
				$this->out = $this->redirect($header) ;
				
			}
			else
			{
				if($isChuncked)
				{
					$body = $this->unChunk($body) ; 
				}

				if($this->output=="botharray")
				{
					$this->out = array("header"=>$header,"body"=>$body);
				}
				elseif($this->output=="bothstring")
				{
					$this->out = implode("\r\n",array("header"=>$header,"body"=>$body));
				}
				elseif($this->output=="header")
				{
					$this->out = $header;
				}
				elseif($this->output=="body")
				{
					$this->out = $body;
				}
							
				preg_match_all("/Set-Cookie: ([^\;]+)/",$header,$cookiesInfo);

				$this->cookiesOutput = implode("; ",$cookiesInfo[1]);
				
			}



		}
		else
		{
			$this->out = false;
			return false;
		}

	}
	function createOAuth($data)
	{
	    $oauth_hash_array = array(
			"oauth_consumer_key" => $data["consumerKey"],
			"oauth_nonce" => time(),
			"oauth_signature_method" => "HMAC-SHA1",
			"oauth_timestamp" => time(),
			"oauth_version" => "1.0",
			"oauth_token" => $data["oAuthToken"]

	    );
	    if($data["method"] == "get")
	    {
			foreach($data["_vars"] as $key=>$value)
			{
				$oauth_hash_array[$key] = $value ; 
			}
			unset($data["vars"]);
	    
	    }
	    
		ksort($oauth_hash_array);
	    $oauth_hash = "";
	    foreach($oauth_hash_array as $xkey=>$xvalue)
	    {
			$oauth_hash.=$xkey."=".$xvalue."&";
	    }
		$oauth_hash = substr($oauth_hash,0,-1);

	    $base = strtoupper($data["method"]).'&' . rawurlencode($data["action"]) . '&' . rawurlencode($oauth_hash). rawurlencode($data["vars"]) ;  
	    $key = rawurlencode($data["consumerSecret"]) . '&' . rawurlencode($data["oAuthSecret"]);
	    $signature = base64_encode(hash_hmac("sha1", $base, $key, true));
	    $signature = rawurlencode($signature);
	    $oauth_hash_array["oauth_signature"] = $signature ;
	    ksort($oauth_hash_array);
	    $oauth_header = "";
	    foreach($oauth_hash_array as $xkey=>$xvalue)
	    {
			$oauth_header.=$xkey."=\"".$xvalue."\",";
	    }
		$oauth_header = substr($oauth_header,0,-1);
	    $oauth_header = ''.$oauth_header.'';

	    $this->oAuth = $oauth_header ;
	}
	function output()
	{
		return $this->out;
	}
	function unChunk($data) {
		// http://www.php.net/manual/en/function.fsockopen.php#96146 thank you Buoysel
		 $fp = 0;
		 $outData = "";
		 while ($fp < strlen($data)) {
			 $rawnum = substr($data, $fp, strpos(substr($data, $fp), "\r\n") + 2);
			 $num = hexdec(trim($rawnum));
			 $fp += strlen($rawnum);
			 $chunk = substr($data, $fp, $num);
			 $outData .= $chunk;
			 $fp += strlen($chunk);
		 }
		 return $outData;
	 }
	function method()
	{
		if($this->method == "get")
		{
			$in.="GET ".$this->path." HTTP/1.1\r\n";
			$in.= "User-Agent: ".$this->useragent."\r\n";
			$in.= "Accept-Language: en-us\r\n";
			$in.= "Host: ". $this->hostReq."\r\n";
			if($this->oAuth != null){
				$in.= "Authorization: Oauth ".$this->oAuth."\r\n";
			}
			$in.= "Referer: ".$this->referer."\r\n" ;
			$in.= "Connection: close\r\n";
			$in.= "Cookie: ".$this->cookies."\r\n\r\n";
			
			
			
		}
		elseif($this->method == "head")
		{
			$in.= "HEAD ".$this->path." HTTP/1.1\r\n";
			$in.= "User-Agent: ".$this->useragent."\r\n";
			$in.= "Accept-Language: en-us\r\n";
			$in.= "Host: ".$this->hostReq."\r\n";
			if($this->oAuth != null){
				$in.= "Authorization: Oauth ".$this->oAuth."\r\n";
			}
			$in.= "Cookie: ".$this->cookies."\r\n";
			$in.= "Connection: close\r\n\r\n";
		}
		elseif($this->method == "post")
		{
			if($this->type == "multipart")
			{
				$boundary = $this->boundary;
				$in.= "POST ".$this->path." HTTP/1.1\r\n";
				$in.= "User-Agent: ".$this->useragent."\r\n";
				$in.= "Accept-Language: en-us\r\n";
				$in.= "Host: ".$this->hostReq."\r\n";
				$in.= "Content-Length: ".strlen($this->vars)."\r\n";
				$in.= "Content-Type: multipart/form-data; boundary=$boundary\r\n";
				$in.= "Connection: close\r\n";
				$in.= "Cookie: ".$this->cookies."\r\n\r\n";
				$in.= $this->vars;		
				
			}
			else
			{
				$in.= "POST ".$this->path." HTTP/1.1\r\n";
				$in.= "User-Agent: ".$this->useragent."\r\n";
				$in.= "Accept-Language: en-us\r\n";
				$in.= "Host: ".$this->hostReq."\r\n";
				$in.= "Content-Length: ".strlen($this->vars)."\r\n";
				$in.= "Content-Type: application/x-www-form-urlencoded\r\n";
				if($this->oAuth != null){
				$in.= "Authorization: Oauth ".$this->oAuth."\r\n";
				}
				$in.= "Connection: close\r\n";
				$in.= "Cookie: ".$this->cookies."\r\n\r\n";
				$in.= $this->vars;
				
			}
		
		}
		$this->request = $in;

	}
	function chooseContentType($file)
	{
		$mime = array(
			'3g2'  => 'video/3gpp2'  							, 		'3gp'  => 'video/3gpp'			,
			'ai'   => 'application/postscript'					, 		'avi'  => 'video/x-ms-wmv'		,
			'bin'  => 'application/octet-stream'				, 		'bmp'  => 'image/x-ms-bmp'		,
			'css'  => 'text/css'								, 		'csv'  => 'text/plain'			,
			'doc'  => 'application/msword'						, 		'dot'  => 'application/msword'	,
			'eps'  => 'application/postscript'					, 		'flv'  => 'video/x-flv'			,
			'gif'  => 'image/gif'								, 		'gz'   => 'application/x-gzip'	,
			'htm'  => 'text/html'								, 		'html' => 'text/html'			,
			'ico'  => 'image/x-icon'							, 		'jpg'  => 'image/jpeg'			,
			'jpe'  => 'image/jpeg'								, 		'jpeg' => 'image/jpeg'			,
			'js'   => 'text/javascript'							, 		'json' => 'application/json'	,
			'mov'  => 'video/quicktime'							, 		'mp3'  => 'audio/mpeg'			,
			'mp4'  => 'video/mp4'								, 		'mpeg' => 'video/mpeg'			,
			'mpg'  => 'video/mpeg' 								,       'pdf'  => 'application/pdf'		,
			'png'  => 'image/x-png' 							, 	    'pot'  => 'application/vnd.ms-powerpoint',
			'php'  => 'application/x-php'					    , 		'pps'  => 'application/vnd.ms-powerpoint',
			'ppt'  => 'application/vnd.ms-powerpoint'   		, 		'qt'   => 'video/quicktime'		,
			'ra'   => 'audio/x-pn-realaudio'					, 		'ram'  => 'audio/x-pn-realaudio',
			'rar'  => 'application/x-rar-compressed'			, 		'rtf'  => 'application/rtf'		,
			'swf'  => 'application/x-shockwave-flash'		 	,		'tar'  => 'application/x-tar'	,
			'tgz'  => 'application/x-compressed'  				, 		'tif'  => 'image/tiff'			,
			'tiff' => 'image/tiff' 								, 		'txt'  => 'text/plain'			,
			'wmv'  => 'video/x-ms-wmv'							,   	'wav'  => 'audio/wav'			,
			'xls'  => 'application/vnd.ms-excel'				,		'xml'  => 'text/xml'			,
			'zip'  => 'application/zip'						    ,	 	'none' => 'application/octet-stream'
		);

		$ext = pathinfo($file); $ext = $ext['extension'];

		return array_key_exists($ext,$mime)? $mime[$ext] : $mime["none"];
	}
	function createMultiPartRequest($data,$multiData)
	{
		$this->boundary = $bound =  uniqid();
		$body = "";

		foreach($data as $name=>$value)
		{
			if(!empty($multiData))
			{
				$type = "normal";
				if(is_array($multiData)&&in_array($name,$multiData))
				{
					$type = "multiData";
				}
				else
				{
					if($name == $multiData)
					{
						$type = "multiData";
					}
					else
					{
						$type = "normal";
					}
				}
		
			}
			else
			{
				$type = "normal";
			}

			if($type == "normal")
			{
				$body .= "--$bound\r\n";
				$body .= "Content-Disposition: form-data; name=\"$name\"\r\n\r\n";
				$body .= "$value\r\n";
			}
			else
			{
				if(file_exists($value))
				{
					$fname = basename($value);				
					$binary= file_get_contents($value);
					$cType = $this->chooseContentType($fname);
					$body .= "--$bound\r\n";
					$body .= "Content-Disposition: form-data; name=\"$name\"; filename=\"$fname\"\r\n";
					$body .= "Content-Type: $cType\r\n\r\n";
					$body .= "$binary\r\n";
				}
			}
		}

		$body .="--$bound--\r\n";

		$this->vars = $body;
	}
	function redirect($header)
	{
	
		preg_match("/Location: (.*)/",$header,$location);
		preg_match_all("/Set-Cookie: (.*)/",$header,$out);
		$cookie = "" ;
		foreach($out[1] as $cookieLine)
		{
			$cookie.= trim($cookieLine)." ;";
		}
		$cookie = substr($cookie,0,-1);
		
		$req = $this->data ; 
		
		$req["action"] =  trim($location[1]) ;
		$req["cookie"] =  $cookie ;
		if(preg_match("/Referer: (.*)/",$header,$referer)){
			$req["referer"] =  trim($referer[1]) ;
		}
		

		$this->send($req);	
		return $this->output();
	
	}
	function get($url,$data="")
	{
		$req = array(
		"method" => "get",
		"action" => $url,
		"output"=> "body",
		
		);
		if(!empty($data))
		{
			foreach($data as $_req=>$_reqVal)
			{
				$req[$_req] = $_reqVal ;
			}
		}
	
		$this->send($req);	
		return $this->output();
	}
	function getFile($url,$saveTo,$data = array())
	{
		$req = array(
		"method" => "get",
		"action" => $url,
		"output"=> "body",
		"saveTo" => $saveTo
		
		);
		if(!empty($data))
		{
			foreach($data as $_req=>$_reqVal)
			{
				$req[$_req] = $_reqVal ;
			}
		}
	
		$this->send($req);	
		return  $this->output();
	}
	function head($url)
	{
		
		$data = array(
			"method" => "head",
			"action" => $url,
			"output"=> "header",
			"recursive"=> false ,
		);
		if(!empty($data))
		{
			foreach($data as $_req=>$_reqVal)
			{
				$req[$_req] = $_reqVal ;
			}
		}
		$this->send($req);	
		return  $this->output();
	}
	/*function parseHeader($header)
	{
		preg_match_all("/Set-Cookie: (.*)/",$header,$out);
		$cookie = "" ;
		foreach($out[1] as $cookieLine)
		{
			$cookie.= trim($cookieLine)." ;";
		}
		return substr($cookie,0,-1);
	}*/
	function parseHeader($data)
	{
		$return = array();
		preg_match_all("/(.*): (.*)/",$data,$out);
		for($i = 0 ; $i < count($out[1]) ; $i++)
		{
			$return[$out[1][$i]] = $out[2][$i] ;
		}	
		return($return);
	}
	function getCookies()
	{
		return $this->cookies ;
	}
	function getReferer()
	{
		return $this->cookies ;
	}
	function doConnect()
	{
		if(!isset($this->host))
		{
			
			return false;
		}
		$return =  @fsockopen($this->host,$this->port,$errno,$errorstr,30);
		
		
		$this->handle = $return ;
		return $return;
	}
}




?>
