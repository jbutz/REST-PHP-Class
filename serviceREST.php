<?php
class clientREST
{
	var $curlObj;
	var $httpHeader;
	
	function __construct()
	{
		$this->curlObj = curl_init();
		$this->httpHeader[] = "Cache-Control: max-age=0"; 
		$this->httpHeader[] = "Connection: keep-alive"; 
		$this->httpHeader[] = "Keep-Alive: 300"; 
		curl_setopt($this->curlObj,CURLOPT_HEADER,false);
		curl_setopt($this->curlObj,CURLOPT_AUTOREFERER,true);
		curl_setopt($this->curlObj,CURLOPT_FRESH_CONNECT,true);
		curl_setopt($this->curlObj,CURLOPT_RETURNTRANSFER,true);
	}
	
	public function execRequest($httpURL,$httpMethod,$httpData)
	{
		curl_setopt($this->curlObj,CURLOPT_HTTPHEADER,$this->httpHeader);
		switch(strtolower($httpMethod))
		{
			case 'get':
				$this->_get($httpData,$httpURL);
				break;
			case 'post':
				$this->_post($httpData);
				break;
			case 'put':
				$this->_put($httpData);
				break;
			case 'delete':
				$this->_delete($httpData);
				break;
		}
		curl_setopt($this->curlObj,CURLOPT_URL,$httpURL);
		return curl_exec($this->curlObj);
	}
	
	public function setAcceptType($type)
	{
		// xml  -> text/xml
		// html -> text/html
		// json -> application/json
		// text -> text/plain
		// Else -> whatever was there
		if(is_array($type))
		{
			foreach($type as $k => $v)
			{
				$v = strtolower($v);
				if($v == "xml")
					$type[$k] = "text/xml";
				elseif($v == "html")
					$type[$k] = "text/html";
				elseif($v == "json")
					$type[$k] = "application/json";
				elseif($v == "text")
					$type[$k] = "text/plain";
			}
			$type = implode(",",$type);
		}
		$this->httpHeader[] = "Accept: ".$type;
	}

	private function _get($data = null,&$url)
	{
		curl_setopt($this->curlObj,CURLOPT_HTTPGET,true);
		if($data != null)
		{
			if(is_array($data))
			{
				$data = http_build_query($data,'arg');
			}
			else
			{
				parse_str($data,$tmp);
				$data = "";
				$first = true;
				foreach($tmp as $k => $v)
				{
					if(!$first)
					{
						$data .= "&";
					}
					$data .= $k . "=" . urlencode($v);
					$first = false;
				}
			}
			$url .= "?".$data;
		}
	}

	private function _post($data = null)
	{
		curl_setopt($this->curlObj,CURLOPT_POST,true);
		if($data != null)
		{
			if(is_array($data))
			{
				$data = http_build_query($data,'arg');
			}
			else
			{
				parse_str($data,$tmp);
				$data = "";
				$first = true;
				foreach($tmp as $k => $v)
				{
					if(!$first)
					{
						$data .= "&";
					}
					$data .= $k . "=" . urlencode($v);
					$first = false;
				}
			}
			curl_setopt($this->curlObj,CURLOPT_POSTFIELDS,$data);
		}
	}

	private function _put($data = null)
	{
		curl_setopt($this->curlObj,CURLOPT_PUT,true);
		$resource = fopen('php://temp', 'rw');
		$bytes = fwrite($resource,$data);
		rewind($resource);
		if($bytes !== false)
		{
			curl_setopt($this->curlObj,CURLOPT_INFILE,$resource);
			curl_setopt($this->curlObj,CURLOPT_INFILESIZE,$bytes);
		}
		else
		{
			throw new Exception('Could not write PUT data to php://temp');
		}
	}

	private function _delete($data = null)
	{
		curl_setopt($this->curlObj,CURLOPT_CUSTOMREQUEST,'DELETE');
		if($data != null)
		{
			$resource = fopen('php://temp', 'rw');
			$bytes = fwrite($resource,$data);
			rewind($resource);
			if($bytes !== false)
			{
				curl_setopt($this->curlObj,CURLOPT_INFILE,$resource);
				curl_setopt($this->curlObj,CURLOPT_INFILESIZE,$bytes);
			}
			else
			{
				throw new Exception('Could not write DELETE data to php://temp');
			}
		}
	}
}

class serverREST
{
	var $httpQuery;
	var $httpPath;
	var $httpMethod;
	var $validOutput;
	var $functionMap;

	function __construct()
	{
		// We have to figure out the path
		// It is a little complicated
		$protocol = (array_key_exists('HTTPS',$_SERVER) && ($_SERVER['HTTPS'] != "" || $_SERVER['HTTPS'] != 'off')) ? "https" : "http";
		$rUri = $_SERVER['REQUEST_URI'];
		if(!array_key_exists('REQUEST_URI',$_SERVER) || $_SERVER['REQUEST_URI'] == "")
			$rUri = $_SERVER['PHP_SELF'];
		$baseUri = strpos($rUri,$_SERVER['SCRIPT_NAME']) === 0 ? $_SERVER['SCRIPT_NAME'] : str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
		$baseUri = rtrim($baseUri, '/');
		$uri = '';
		if(array_key_exists('PATH_INFO',$_SERVER) && $_SERVER['PATH_INFO'] != "")
			$uri = $_SERVER['PATH_INFO'];
		else
		{
			if($_SERVER['REQUEST_URI'] != "")
				$uri = parse_url($protocol.'://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],PHP_URL_PATH);
			elseif($_SERVER['PHP_SELF'] == "")
				$uri = $_SERVER['PHP_SELF'];
			else
				throw new Exception("Couldn't detect path.");
		}
		if($baseUri != "" && strpos($uri,$baseUri) === 0)
			$uri = substr($uri,strlen($baseUri));
		$uri = trim($uri,'/');
		$this->httpPath    = $uri;
		$this->validOutput = explode(',',substr($_SERVER['HTTP_ACCEPT'],0,strpos($_SERVER['HTTP_ACCEPT'],';')));
		$this->httpMethod  = $_SERVER['REQUEST_METHOD'];
		$this->functionMap = array();
		switch(strtoupper($this->httpMethod))
		{
			case 'GET':
				$this->httpQuery = $_GET;
				break;
			case 'POST':
				$this->httpQuery = $_POST;
				break;
			case 'PUT':
			case 'DELETE':
				$this->httpQuery = file_get_contents('php://input');
				break;
		}
	}

	public function addFunction($functionName, $method, $path)
	{
		$path = trim($path,"/");
		$this->functionMap[$path][strtoupper($method)] = $functionName;
		return true;
	}

	public function addObject($className, $path)
	{
		$path = trim($path,"/");
		$this->functionMap[$path] = $className;
		return true;
	}
	
	public function execServer()
	{
		if(array_key_exists(trim($this->httpPath,'/'),$this->functionMap))
		{
			// We have a function mapped there!
			$map = $this->functionMap[trim($this->httpPath)];
			// If it is an array then we are dealing with a function
			//		otherwise it is an object
			$dataArr = array(
					'method' => strtoupper($this->httpMethod),
					'path'   => trim($this->httpPath,'/'),
					'query'  => $this->httpQuery);
			if(is_array($map))
			{
				$f = $map[strtoupper($this->httpMethod)];
				$tmp = $f($dataArr);
			}
			else
			{
				$obj = new $map();
				$tmp = $obj->run($dataArr);
			}
			$statusCode = $tmp['statusCode'];
			$data = $tmp['data'];
			$type = $tmp['type'];
			$return = true;
		}
		else
		{
			$statusCode = "500";
			$data = "";
			$type = null;
			$return = false;
		}
		$this->_output($statusCode,$data,$type);
		return $return;
	}

	private function _httpStatus($statusCode)
	{
		// HTTP Response Codes
		$codes = Array(
				100 => 'Continue',
				101 => 'Switching Protocols',
				200 => 'OK',
				201 => 'Created',
				202 => 'Accepted',
				203 => 'Non-Authoritative Information',
				204 => 'No Content',
				205 => 'Reset Content',
				206 => 'Partial Content',
				300 => 'Multiple Choices',
				301 => 'Moved Permanently',
				302 => 'Found',
				303 => 'See Other',
				304 => 'Not Modified',
				305 => 'Use Proxy',
				306 => '(Unused)',
				307 => 'Temporary Redirect',
				400 => 'Bad Request',
				401 => 'Unauthorized',
				402 => 'Payment Required',
				403 => 'Forbidden',
				404 => 'Not Found',
				405 => 'Method Not Allowed',
				406 => 'Not Acceptable',
				407 => 'Proxy Authentication Required',
				408 => 'Request Timeout',
				409 => 'Conflict', 
				410 => 'Gone',
				411 => 'Length Required',
				412 => 'Precondition Failed',
				413 => 'Request Entity Too Large',
				414 => 'Request-URI Too Long',
				415 => 'Unsupported Media Type',
				416 => 'Requested Range Not Satisfiable',
				417 => 'Expectation Failed',
				500 => 'Internal Server Error',
				501 => 'Not Implemented',
				502 => 'Bad Gateway',
				503 => 'Service Unavailable',
				504 => 'Gateway Timeout',
				505 => 'HTTP Version Not Supported');
		if(array_key_exists($statusCode,$codes))
			return $codes[$statusCode];
		else
			throw new Exception("Invalid HTTP Status Code");
	}

	private function _output($statusCode, $data, $type = null)
	{
		$outputGood = true;
		$changed = false;
		if($type != null)
		{

			if(!in_array($type,$this->validOutput))
			{
				$found = false;
				foreach($this->validOutput as $v)
				{
					$t = explode('/',$v);
					if($t[1] == $v)
					{
						$found = true;
						$type = $v;
						$changed = true;
					}
				}
				$outputGood = $found;
			}
		}
		if(!$outputGood)
		{
			throw new Exception("Data type not expected by client");
		}
		$statusText = $this->_httpStatus($statusCode);
		if(!is_array($data) &&($data == "" || $data == null)) // Sometimes we want empty JSON
		{
			$data = "<html><head><title>$statusCode $statusText</title></head><body></body></html>";
		}
		
		if($changed)
		{
			header("Content-type: $changed");
		}
		elseif(is_array($data))
		{
			$data = json_encode($data);
			header('Content-type: application/json');
		}
		elseif(strip_tags($data) != $data)
		{
			header('Content-type: text/html');
		}
		else
		{
			header('Content-type: text/plain',false);
		}

		header("HTTP/1.1 $statusCode $statusText");
		echo $data;
	}
}

/*
$c = new serverREST();
$c->addFunction("helloWorld","GET","/hello/world/");
$c->addFunction("helloWorldPost","post","hello/world");
//$c->addObject("helloWorldObj","hello/world");
print_r($c);
*/
?>
