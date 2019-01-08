<?php
class Http
{
    private $headers = [] ;
    private $recursive = 0 ;
    private $useragent = "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36" ;
    private $request = [] ;
	public function __construct($useragent = null)
    {
        if(isset($useragent) ) {
            $this->useragent = $useragent ;
        }   
    }
    protected function createAndSendRequest($method , $action , $data = [] ,  $request = []  )
    {
        $follow = isset($request["recursive"]) ? $request["recursive"] : false ;
        $request["action"] = $action;
        $request["method"] =  $method;
        $request["recursive"] = $follow ;
        $request["payload"] = $data ;
        $response = $this->send($request) ;


        if($action == "head")
        {
            return $follow ? implode("\r\n" , $this->headers) : $response["header"];
        }
      
        return $response["body"] ;
    }

    public function send($array)
    {
        
        $data = $this->endpoint($array) ;
        $request = $this->build($data , $array) ;
        if(isset($array["debug"]) && $array["debug"]) {
            echo $request ;
            echo "<BR> <HR> <BR>";
            die();
        }
        $data = $this->request( $data , $request , $array  ) ;
        $this->request[] = $request ;
        return $this->reponse = $data ;
        
       
    }
	public function getRequest()
	{
		return $this->request;
	}

    private function endpoint($data)
    {
        $proxy = null ;
        $parsed = parse_url(trim($data["action"])) ;
        if(isset($data["proxy"]) && $data["proxy"] != null && is_array($data["proxy"]))
		{
			$proxy = $data["proxy"] ; 
			if(isset($data["proxy"]["type"]) && $data["proxy"]["type"] == "tcp")
			{
				$server = "tcp://".$data["proxy"]["ip"] ; 
				$port   = 80 ; 
				$path   = $data["action"] ;
			}
			else
			{
				$server = $data["proxy"]["ip"] ; 
				$port   = $data["proxy"]["port"] ; 
				$path   = $data["action"] ;    
			}
            $host = $server ;
		}
        elseif($parsed["scheme"] == "https")
        {
            $server = "ssl://".$parsed["host"] ;
            $port = isset($parsed["port"]) ? $parsed["port"] : 443 ;
            $host = $parsed["host"] ; 
            $path = isset($parsed["path"]) ? $parsed["path"] : "/" ;
        }
        else
        {
            $server = $parsed["host"] ;
            $port = isset($parsed["port"]) ? $parsed["port"] : 80 ;
            $host  =  $parsed["host"] ; 
            $path = isset($parsed["path"]) ? $parsed["path"] : "/" ;
        }
        
        if(isset($parsed["query"]) && $proxy == null)
        {
            $path.="?".$parsed["query"] ; 
            $query = $parsed["query"] ;
        }
        else
        {
            $query = isset($parsed["query"]) ? $parsed["query"] : "" ;
        }

        return (object) [
            "server"    => $server ,
            "host"      => $host ,
            "port"      => $port ,
            "path"      => $path ,
            "query"     => $query ,
            "proxy"     => $proxy
        ];
    }
    private function build($action , $data = [])
    {
        
		$payload = null ;
        $request = [] ;
        $request["method"] = sprintf("%s %s HTTP/1.1" , strtoupper(isset($data["method"]) ? $data["method"] : 'get') , $action->path) ;
        $request["user-agent"] = isset($data["useragent"]) ? "User-Agent: {$data["useragent"]}" : "User-Agent: {$this->useragent}";
        $request["host"] = "Host: {$action->host}";
        if(isset($data["accept_lang"]) && $data["accept_lang"] != "" )
		{
			$request["accept-language"]  = "Accept-Language: {$data["accept_lang"]}";
		}
        if(isset($data["referer"]))
        {
            $request["referer"] = "Referer: {$data["referer"]}";
        }
        if(isset($data["auth"]))
        {
            $request["authorization"] = $this->auth($data) ;
        }
        if(isset($data["proxy"]) && isset($data["proxy"]["auth"]))
        {
            $request["proxy-authorization"] = "Proxy-Authorization: Basic ".base64_encode($data["proxy"]["auth"]["username"].":".$data["proxy"]["auth"]["password"]) ;
        }
        if(isset($data["method"]) && strtolower($data["method"]) == "post")
		{
			$payload = $this->payload($data) ;
			$request["content-length"] = "Content-Length: {$payload->length}" ;
			if($payload->contentType == "multipart")
			{
				$request["content-type"] = "Content-Type: multipart/form-data; boundary={$payload->boundery}";
			}
			elseif($payload->contentType == "json")
			{
				$request["content-type"] = "Content-Type: application/json" ;
			}
			elseif($payload->contentType == "xml")
			{
				$request["content-type"] = "Content-Type: text/xml" ;
			}
			elseif($payload->contentType == "form")
			{
				$request["content-type"] = "Content-Type: application/x-www-form-urlencoded" ;
			}
			else
			{
				$request["content-type"] = "Content-Type: {$payload->contentType}" ;
			}
		}
		if(isset($data["cookies"]))
        {
            $request["cookies"] = "Cookies: {$data["cookies"]}";
        }        
        if(isset($data["headers"]))
        {
            
            if(is_string($data["headers"]))
            {
                $data["headers"] = explode("\n" , $data["headers"]) ;   
            }
            foreach($data["headers"] as $line)
            {
                list($key , $value) = explode(":" , $line , 2);
                $request[strtolower(trim($key))] = trim($line);
            }
        }
        
        if(!isset($request["connection"]))
        {
            $request["Connection"] = "Connection: close";
        }
        
		$request = implode("\r\n" , $request)."\r\n\r\n" ;
		if($payload)
		{
			$request.=$payload->content ;	
		}
        
        
		
       
		return $request ;
    }
    private function payload($data)
    {
		$boundery = uniqid(); 
        $isMultipart = false ;
		$parameters = [] ;
		$normal = [] ;
		$return = [] ;
		$body = "" ;
		if(is_array($data["payload"]))
		{
			foreach($data["payload"] as $key => $value)
			{
				if(isset($value[0]) && is_string($value[0]) && $value[0] == "@" && file_exists(str_replace("@" , "" , $value))) {
					$isMultipart = true ;
					$parameters[$key] = [
						"multipart" => true ,
						"value"  => str_replace("@" , "" , $value) 
					] ;
					
				}
				else
				{
				   $parameters[$key] = [
						"multipart" => false ,
						"value"  => $value 
					] ;
				   
				   $normal[$key] = $value ; 
				}
			}
		}
		

		if(isset($data["contentType"]) && ( $data["contentType"] != "form" && $data["contentType"] != "multipart") )
		{
			if(isset($data["contentType"]) && $data["contentType"] == "json") {
				$json = is_array($data["payload"]) ? json_encode($data["payload"]) : $data["payload"] ;
				return (object) [
					"contentType" =>  "json" ,
					"boundery" 	  => null ,
					"content" 	  => $json ,
					"length"      => strlen($json) 
				] ;
			}
			
			
			if(isset($data["contentType"]) && $data["contentType"] == "xml") {
				return (object) [
					"contentType" =>  "xml" ,
					"boundery" 	  => null ,
					"content" 	  => $data["payload"],
					"length"      => strlen($data["payload"]) 
				] ;
			}
			
		}
		else
		{
			$payload =  http_build_query($normal) ;
		   if($isMultipart)
		   {
			   foreach($parameters as $key=>$param)
			   {
				   if(!$param["multipart"])
				   {
					   $body .= "--{$boundery}\r\n";
					   $body .= "Content-Disposition: form-data; name=\"{$key}\"\r\n\r\n";
					   $body .= "{$param['value']}\r\n";
				   }
				   else
				   {
					   if(file_exists($param['value']))
					   {
						   $fname = basename($param['value']);				
						   $binary= file_get_contents($param['value']);
						   $cType = $this->contentType($fname);
						   $body .= "--{$boundery}\r\n";
						   $body .= "Content-Disposition: form-data; name=\"{$key}\"; filename=\"$fname\"\r\n";
						   $body .= "Content-Type: $cType\r\n\r\n";
						   $body .= "$binary\r\n";
					   }
				   }
			   }
			   $body .="--{$boundery}--\r\n";
			   return (object) [
				   "contentType" => "multipart" ,
				   "boundery" => $boundery ,
				   "content" => $body ,
				   "length"  => strlen($body) 
			   ] ;
		   }
		   else
		   {
			   
			   return (object) [
				   "contentType" => "form" ,
				   "boundery" => null ,
				   "content" => $payload ,
				   "length"  => strlen($payload) 
			   ] ;
		   }
		}
       
		
		
    }
    private function auth($data)
    {
        
        if(isset($data["auth"]["type"]) && $data["auth"]["type"] == "bearer")
        {
             return "Authorization: Bearer ".$data["auth"]["key"]."\r\n" ; 
        }
        elseif(isset($data["auth"]["type"]) && $data["auth"]["type"] == "oauth")
        {
            
            $oauth_hash_array = array(
                "oauth_consumer_key" => $data["auth"]["consumerKey"],
                "oauth_nonce" => time(),
                "oauth_signature_method" => "HMAC-SHA1",
                "oauth_timestamp" => time(),
                "oauth_version" => "1.0",
                "oauth_token" => $data["auth"]["oAuthToken"]
            );
            if(strtolower($data["method"]) == "get")
            {
                foreach($data["vars"] as $key=>$value)
                {
                    $oauth_hash_array[$key] = $value ; 
                }            
            }
            
            ksort($oauth_hash_array);
            $oauth_hash = "";
            foreach($oauth_hash_array as $xkey=>$xvalue)
            {
                $oauth_hash.=$xkey."=".$xvalue."&";
            }
            $oauth_hash = substr($oauth_hash,0,-1);
        
            $base = strtoupper($data["method"]).'&' . rawurlencode($data["action"]) . '&' . rawurlencode($oauth_hash). rawurlencode($data["text_vars"]) ;  
            $key = rawurlencode($data["auth"]["consumerSecret"]) . '&' . rawurlencode($data["auth"]["oAuthSecret"]);
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
        
			return $oauth_header ;
            
        }
        elseif(isset($data["auth"]["type"]) && $data["auth"]["type"] == "basic")
        {
            return "Authorization: Basic ".base64_encode($data["auth"]["username"].":".$data["auth"]["password"]) ; 
        }
        else
        {
            return "Authorization:  ".$data["auth"][0] ; 
        }
    }
    private function contentType($file)
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

		$ext = pathinfo($file);
		$ext = $ext['extension'];
		return isset($mime[$ext]) ? $mime[$ext] : $mime["none"];
	}
    private function request($send , $request , $raw)
    {
       
        $data = null ;
        $temp = "" ;
        $header = "" ;
        $body = "" ;
        $chunk = "" ;
        $isChunked = false ;
		$isGziped = false ;
        $startBody = false ;
        $chunkSize = 0 ;
        $handler = @fsockopen($send->server,$send->port,$errno,$errstr,10);
		if($handler && is_resource($handler))
        {
			fputs($handler , $request );
			if(isset($raw["output"]) && $raw["output"] == null) return ;
            while(!feof($handler))
            {
                $data = fgets($handler) ;
                if($data == "\r\n" && $header == "")
                {
                    $startBody = true ;
                    $header = $temp ;
                    $temp = "" ;
                    
                    $this->headers[] = $header ;

                    
                    if(strpos($header , "Content-Encoding: gzip") !== false ){
                        $isGziped = true ;
                    }
                    
                    if(strpos($header  , "Transfer-Encoding: chunked") !== false ){
                        $isChunked = true ;
                    }
                    
                    if( strpos($header  , "Location") !== false && isset($raw["recursive"]) && $raw["recursive"])  {
                        
                        return $this->redirect($header , $raw) ;
                        
                        fclose($handler) ;
                    }
                    
                    
                    if((isset($raw["output"]) && $raw["output"] == "header" ) || isset($raw["method"]) && strtolower($raw["method"]) == 'head')
                    {
                        fclose($handler) ;
                        return [
                            "header" => $header ,
                            "body" => null 
                        ] ;
                    }
                    
                    continue ;
                }
                
                if($startBody)
                {
                    if($isChunked)
                    {
                        if($chunkSize == 0)
                        {
                            $chunkSize = hexdec(trim($data)) ;
                            continue ;
                        }
                        
                        if(strlen($chunk) >= $chunkSize)
                        {
                            $body.=$chunk ;
                            $chunkSize = hexdec(trim($data)) ;
                            $chunk = "" ;
                        }
                        else
                        {
                            $chunk.=$data ;
                        }
                    }
                    else
                    {
                        $body.=$data ;
                    }
                }
                else
                {
                    $temp.=$data ;
                }
                
                
            }
            return [
                "header" => $header,
                "body" => $isGziped ? gzdeflate($body) : $body 
            ] ;
		}
        else
        {
            throw new \Exception("xCan not Access to {$send->server}:{$send->port} error {$errno} {$errstr}") ;
        }
    }
    private function cookies($header , $send = true )
    {
        if(preg_match_all("/Set-Cookie: (.*)/",$header,$out))
        {
            $temp = "" ;
            $cookie = "" ;
            if($send)
            {
                foreach($out[1] as $cookieLine)
                {
                    $temp = explode(";" , $cookieLine , 2);
                    $cookie.= $temp[0] ." ;";
                }
            }
            else
            {
                foreach($out[1] as $cookieLine)
                {
                    $cookie.= trim($cookieLine)." ;";
                }
                $cookie = substr($cookie,0,-1);
            }
            return $cookie ;
        }
        else
        {
            return null;
        }
    }
    private function redirect($header , $data )
    {
        $this->recursive+=1 ;
        if( $this->recursive <= 10)
        {
            
            $referer = $data["action"] ;
            preg_match("/Location: (.*)/",$header,$location);
            $data["referer"] = $referer ;
            $data["action"] = $location[1] ;
            if($cookie = $this->cookies($header))
            {
                $data["cookies"] =  $cookie;
            }
            
            return $this->send($data) ; 
        }
        else
        {
            throw new \Exception("HTTP Infint redirection");
        }
    }
    public function headers($key = null)
    {
		$headers = count($this->headers) > 1 ? $this->headers : $this->headers[0] ;

		if($key == null && is_string($headers))
		{
			// <TODO>
		}
    }
	private function getCookies($key)
	{
		// <TODO>
    }
    
    public static function __callStatic($method , $args)
    {
        if(in_array($method , ["get" , "post" ])) {
            
            $http = new self();
            return $http->createAndSendRequest($method , ...$args );
        }
        throw new \Exception("{$method} is not exists");
    }
    public  function __call($method , $args)
    {
        if(in_array($method , ["get" , "post" ])) {
            
            $http = new self();
            return $http->createAndSendRequest($method , ...$args );
        }
        throw new \Exception("{$method} is not exists");
    }
}