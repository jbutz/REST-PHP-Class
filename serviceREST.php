<?php
class clientREST
{
	var $curlObj;
	var $httpHeader;
	
	function _construct()
	{
		$this->curlObj = curl_init();
		$this->httpHeader[] = "Cache-Control: max-age=0"; 
		$this->httpHeader[] = "Connection: keep-alive"; 
		$this->httpHeader[] = "Keep-Alive: 300"; 
		curl_setopt($this->curlObj,CURL_HEADER,true);
		curl_setopt($this->curlObj,CURL_AUTOREFERER,true);
		curl_setopt($this->curlObj,CURL_FRESH_CONNECT,true);
	}
	
	public function execRequest($httpURL,$httpMethod,$httpData)
	{
		curl_setopt($this->curlObj,CURL_HTTPHEADER,$this->httpHeader);
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
				$data = urlencode($data);
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
				$data = urlencode($data);
			}
			curl_setopt($this->curlObj,CURLOPT_POSTFIELDS,$data);
		}
	}

	private function _put($data = null)
	{
		curl_setopt($this->curlObj,CURLOPT_PUT,true);
		$resource = fopen('php://temp', 'w');
		$bytes = fwrite($resource,$data);
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
			$resource = fopen('php://temp', 'w');
			$bytes = fwrite($resource,$data);
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
?>
