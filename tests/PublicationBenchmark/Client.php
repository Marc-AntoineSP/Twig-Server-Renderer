<?php
namespace App\Tests\PublicationBenchmark;

/** Real form-login/CSRF client; cookies/passwords/headers are never exported. */
final class Client
{
    private \CurlHandle $curl;
    private string $jar;
    private string $csrf;
    public function __construct(array $account,array $owner)
    {
        $this->jar = tempnam(sys_get_temp_dir(),'publication-http-'); chmod($this->jar,0600);
        $this->curl = curl_init();
        curl_setopt_array($this->curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$this->jar,CURLOPT_COOKIEFILE=>$this->jar,CURLOPT_FOLLOWLOCATION=>false]);
        $challenge=bin2hex(random_bytes(32));
        $proof=$this->request('GET','/health/browser-run?challenge='.$challenge);
        if($proof['status']!==200||($proof['data']->runId??'')!==$owner['runId']||!hash_equals(hash_hmac('sha256',$challenge,$owner['secret']),$proof['data']->proof??''))throw new \RuntimeException('HTTP target ownership proof failed; refusing login or publication.');
        $page = $this->request('GET','/login');
        $crawler = new \Symfony\Component\DomCrawler\Crawler($page['body'],'http://studio/login');
        $values = $crawler->selectButton('Sign in')->form()->getValues(); $values['_username']=$account['email'];$values['_password']=$account['password'];
        $answer = $this->request('POST','/login',http_build_query($values),['Content-Type: application/x-www-form-urlencoded']);
        if ($answer['status'] !== 302) throw new \RuntimeException('Owned publication login failed.');
        $csrf = $this->request('GET','/api/v1/csrf');
        if ($csrf['status'] !== 200) throw new \RuntimeException('Owned publication CSRF lookup failed.');
        $this->csrf = $csrf['data']->token;
    }
    public function json(string $method,string $path,array $body,array $headers=[],int $timeoutMs=120000): array
    {
        return $this->request($method,$path,json_encode($body,JSON_THROW_ON_ERROR),['Content-Type: application/json','X-CSRF-Token: '.$this->csrf,...$headers],$timeoutMs);
    }
    public function request(string $method,string $path,?string $body=null,array $headers=[],int $timeoutMs=120000): array
    {
        curl_setopt_array($this->curl,[CURLOPT_URL=>'http://studio'.$path,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT_MS=>$timeoutMs]);
        $content = curl_exec($this->curl); $errno = curl_errno($this->curl);
        return ['status'=>curl_getinfo($this->curl,CURLINFO_RESPONSE_CODE),'curlError'=>$errno,'elapsedMs'=>round(curl_getinfo($this->curl,CURLINFO_TOTAL_TIME)*1000,3),'connectMs'=>round(curl_getinfo($this->curl,CURLINFO_CONNECT_TIME)*1000,3),'ttfbMs'=>round(curl_getinfo($this->curl,CURLINFO_STARTTRANSFER_TIME)*1000,3),'responseBytes'=>strlen($content ?: ''),'body'=>$content ?: '', 'data'=>$content ? json_decode($content) : null];
    }
    public static function metrics(array $response): array { unset($response['body'],$response['data']);return $response; }
    public function __destruct()
    {
        if (isset($this->curl)) {curl_setopt($this->curl,CURLOPT_COOKIEJAR,null);unset($this->curl);}
        if (isset($this->jar) && is_file($this->jar)) unlink($this->jar);
    }
}
